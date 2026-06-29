import os
import json
import asyncio
from typing import AsyncIterator

import anthropic
import httpx
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import StreamingResponse
from pydantic import BaseModel

try:
    from mistralai import Mistral
except ImportError:
    Mistral = None

app = FastAPI(title="ZIA Agent", version="2.0.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

BACKEND_URL     = os.getenv("ZIA_BACKEND_URL", "http://backend:8000")
INTERNAL_SECRET = os.getenv("INTERNAL_API_SECRET", "")
ANTHROPIC_KEY   = os.getenv("ANTHROPIC_API_KEY", "")
MISTRAL_KEY     = os.getenv("MISTRAL_API_KEY", "")
MISTRAL_MODEL   = os.getenv("MISTRAL_MODEL", "mistral-small-latest")

anthropic_client = anthropic.Anthropic(api_key=ANTHROPIC_KEY) if ANTHROPIC_KEY else None
mistral_client   = Mistral(api_key=MISTRAL_KEY) if (Mistral and MISTRAL_KEY) else None

# ─── Tool definitions (Anthropic format) ─────────────────────────────────────

TOOLS = [
    {
        "name": "get_company_profile",
        "description": (
            "Returns the company profile: name, sector code, subsector, active period ID, "
            "num_employees, floor_sqm. Always call this first at the start of a conversation."
        ),
        "input_schema": {
            "type": "object",
            "properties": {
                "company_id": {"type": "integer", "description": "Company ID in ZIA"}
            },
            "required": ["company_id"],
        },
    },
    {
        "name": "get_questionnaire",
        "description": (
            "Returns the list of questionnaire questions applicable to a sector, "
            "each with emission_factor_id, label, unit, scope_id and scope_name. "
            "Use this to know WHAT data to ask the user."
        ),
        "input_schema": {
            "type": "object",
            "properties": {
                "sector_code": {
                    "type": "string",
                    "description": "Sector code: 'servicios', 'industria', 'transporte', 'energia', 'publico', 'tecnologia'",
                },
                "company_id": {
                    "type": "integer",
                    "description": "Company ID to filter out factors disabled for this company. Always pass it when available.",
                },
                "scope_filter": {
                    "type": "array",
                    "items": {"type": "integer"},
                    "description": "Optional: filter by scope IDs [1,2,3]",
                },
            },
            "required": ["sector_code"],
        },
    },
    {
        "name": "get_emission_factors",
        "description": (
            "Lists emission factors filtered by scope and/or category name. "
            "Use when the user mentions a specific fuel or activity to confirm the correct factor."
        ),
        "input_schema": {
            "type": "object",
            "properties": {
                "scope_id":      {"type": "integer", "description": "Filter by scope (1, 2 or 3)"},
                "category_name": {"type": "string",  "description": "Partial category name: 'Electricidad', 'Refrigerantes', etc."},
                "company_id":    {"type": "integer", "description": "Company ID to filter out factors disabled for this company. Always pass it when available."},
            },
        },
    },
    {
        "name": "calculate_ghg",
        "description": (
            "Calculates GHG emissions for an activity value and emission factor. "
            "ALWAYS use this tool to calculate — NEVER compute tCO2e values yourself. "
            "Returns calculated_co2e in tCO2e plus uncertainty."
        ),
        "input_schema": {
            "type": "object",
            "properties": {
                "emission_factor_id": {
                    "type": "integer",
                    "description": "ID of the emission factor in ZIA database",
                },
                "monthly_values": {
                    "type": "array",
                    "items": {"type": "number"},
                    "description": "Array of monthly activity values (1 to 12 numbers)",
                },
            },
            "required": ["emission_factor_id", "monthly_values"],
        },
    },
    {
        "name": "save_emission",
        "description": (
            "Persists a calculated emission record to the database. "
            "Only call AFTER calculate_ghg returns a valid result AND the user explicitly confirms. "
            "Returns the emission record ID."
        ),
        "input_schema": {
            "type": "object",
            "properties": {
                "period_id":         {"type": "integer", "description": "Active period ID"},
                "emission_factor_id": {"type": "integer"},
                "quantity":          {"type": "number",  "description": "Total activity data value"},
                "calculated_co2e":   {"type": "number",  "description": "Result from calculate_ghg"},
                "notes":             {"type": "string",  "description": "Description of the emission source"},
            },
            "required": ["period_id", "emission_factor_id", "quantity", "calculated_co2e"],
        },
    },
    {
        "name": "get_pending_questions",
        "description": (
            "Compares the sector questionnaire against already-registered emissions for this period. "
            "Returns which question labels have not been captured yet. "
            "Use this to guide the user proactively toward a complete GHG inventory."
        ),
        "input_schema": {
            "type": "object",
            "properties": {
                "company_id":  {"type": "integer"},
                "period_id":   {"type": "integer"},
                "sector_code": {"type": "string"},
            },
            "required": ["company_id", "period_id", "sector_code"],
        },
    },
]

# Mistral/OpenAI tool format derived from TOOLS
TOOLS_MISTRAL = [
    {
        "type": "function",
        "function": {
            "name": t["name"],
            "description": t["description"],
            "parameters": t["input_schema"],
        },
    }
    for t in TOOLS
]

SYSTEM_PROMPT = """Eres ZIA, el asistente inteligente de captura de emisiones de carbono de la plataforma ZIA Carbon Control.

Tu rol: guiar al usuario (responsable de sostenibilidad o de operaciones) para registrar correctamente las emisiones de GHG de su empresa, siguiendo el Protocolo GHG (Alcances 1, 2 y 3).

REGLAS CRÍTICAS:
1. NUNCA calcules tCO2e directamente en texto. SIEMPRE usa la herramienta calculate_ghg.
2. NUNCA inventes factores de emisión. SIEMPRE usa get_emission_factors o get_questionnaire para obtener los IDs correctos de la base de datos.
3. Antes de guardar (save_emission), SIEMPRE muestra el resultado al usuario y pide confirmación explícita.
4. Si el usuario confirma guardar, SIEMPRE llama save_emission con los parámetros exactos de calculate_ghg.
5. Si el usuario da un dato ambiguo de unidad, pregunta la unidad antes de calcular.
6. El inventario GHG es un documento legal/auditable. La exactitud es prioritaria sobre la velocidad.

FLUJO RECOMENDADO para onboarding de un período nuevo:
1. Llama get_company_profile para conocer sector y período activo.
2. Llama get_pending_questions para saber qué fuentes faltan por registrar.
3. Guía al usuario fuente por fuente, comenzando por las obligatorias (is_required=true).
4. Para cada dato: calculate_ghg → muestra resultado → pide confirmación → save_emission.
5. Al final, informa el total de tCO2e registrado en el período.

TONO: Profesional pero accesible. Explica brevemente qué significa cada alcance cuando sea relevante. Responde siempre en español."""

# ─── Tool execution ──────────────────────────────────────────────────────────

async def execute_tool(tool_name: str, tool_input: dict, auth_token: str, company_id: int) -> str:
    headers = {
        "Authorization": f"Bearer {auth_token}",
        "X-Company-Context": str(company_id),
        "Content-Type": "application/json",
        "Accept": "application/json",
    }
    internal_headers = {
        "X-Internal-Secret": INTERNAL_SECRET,
        "Content-Type": "application/json",
    }

    async with httpx.AsyncClient(timeout=30.0) as http:
        try:
            if tool_name == "get_company_profile":
                cid = tool_input["company_id"]
                r = await http.get(f"{BACKEND_URL}/api/companies", headers=headers)
                companies = r.json() if r.status_code == 200 else []
                company = next((c for c in companies if c["id"] == cid), None)
                if not company:
                    return json.dumps({"error": f"Company {cid} not found"})
                rp = await http.get(f"{BACKEND_URL}/api/companies/{cid}/periods", headers=headers)
                periods = rp.json() if rp.status_code == 200 else []
                active = next((p for p in periods if p.get("status") == "active"), None)
                return json.dumps({
                    "id":               company["id"],
                    "name":             company["name"],
                    "sector_code":      company.get("sector", {}).get("code") if isinstance(company.get("sector"), dict) else None,
                    "sector_name":      company.get("sector", {}).get("name") if isinstance(company.get("sector"), dict) else None,
                    "subsector_code":   company.get("subsector_code"),
                    "num_employees":    company.get("num_employees"),
                    "floor_sqm":        company.get("floor_sqm"),
                    "active_period_id": active["id"] if active else None,
                    "active_period_year": active["year"] if active else None,
                })

            elif tool_name == "get_questionnaire":
                sector = tool_input["sector_code"]
                params: dict = {"sector": sector}
                if "company_id" in tool_input and tool_input["company_id"]:
                    params["company_id"] = tool_input["company_id"]
                r = await http.get(
                    f"{BACKEND_URL}/api/dictionaries/questionnaire",
                    params=params,
                    headers=headers,
                )
                rules = r.json() if r.status_code == 200 else []
                if "scope_filter" in tool_input and tool_input["scope_filter"]:
                    rules = [rule for rule in rules if rule.get("scope_id") in tool_input["scope_filter"]]
                return json.dumps(rules)

            elif tool_name == "get_emission_factors":
                params = {}
                if "scope_id" in tool_input:
                    params["scope_id"] = tool_input["scope_id"]
                if "company_id" in tool_input and tool_input["company_id"]:
                    params["company_id"] = tool_input["company_id"]
                r = await http.get(
                    f"{BACKEND_URL}/api/dictionaries/factors",
                    params=params,
                    headers=headers,
                )
                return json.dumps(r.json() if r.status_code == 200 else [])

            elif tool_name == "calculate_ghg":
                r = await http.post(
                    f"{BACKEND_URL}/api/internal/calculate",
                    json={
                        "emission_factor_id": tool_input["emission_factor_id"],
                        "monthly_values":     tool_input["monthly_values"],
                    },
                    headers=internal_headers,
                )
                return json.dumps(r.json() if r.status_code == 200 else {"error": r.text})

            elif tool_name == "save_emission":
                r = await http.post(
                    f"{BACKEND_URL}/api/periods/{tool_input['period_id']}/emissions",
                    json={
                        "emission_factor_id": tool_input["emission_factor_id"],
                        "quantity":           tool_input["quantity"],
                        "notes":              tool_input.get("notes", ""),
                    },
                    headers=headers,
                )
                return json.dumps(r.json() if r.status_code == 201 else {"error": r.text})

            elif tool_name == "get_pending_questions":
                sector     = tool_input["sector_code"]
                period_id  = tool_input["period_id"]
                company_id = tool_input.get("company_id")

                q_params: dict = {"sector": sector}
                if company_id:
                    q_params["company_id"] = company_id

                rq = await http.get(
                    f"{BACKEND_URL}/api/dictionaries/questionnaire",
                    params=q_params,
                    headers=headers,
                )
                all_questions = rq.json() if rq.status_code == 200 else []

                re_ = await http.get(
                    f"{BACKEND_URL}/api/periods/{period_id}/emissions",
                    headers=headers,
                )
                existing = re_.json() if re_.status_code == 200 else []
                registered_factor_ids = {e["emission_factor_id"] for e in existing}

                pending = [
                    {
                        "emission_factor_id":  q["emission_factor_id"],
                        "questionnaire_label": q["questionnaire_label"],
                        "is_required":         q["is_required"],
                        "scope_name":          q["scope_name"],
                    }
                    for q in all_questions
                    if q["emission_factor_id"] not in registered_factor_ids
                ]
                return json.dumps({"pending": pending, "total": len(all_questions), "remaining": len(pending)})

            else:
                return json.dumps({"error": f"Unknown tool: {tool_name}"})

        except Exception as e:
            return json.dumps({"error": str(e)})


# ─── History normalization (Mistral ↔ Anthropic format conversion) ───────────

def normalize_history_for_anthropic(messages: list) -> list:
    """Convert Mistral/OpenAI-format history entries to Anthropic format.

    Handles:
    - {"role": "assistant", "tool_calls": [...]} → assistant with tool_use blocks
    - {"role": "tool", ...} messages (one or more) → single user message with tool_result blocks
    - Simple text messages pass through unchanged.
    """
    result = []
    i = 0
    while i < len(messages):
        msg = messages[i]
        role = msg.get("role", "")
        content = msg.get("content", "")

        # Mistral assistant turn with tool_calls → Anthropic tool_use blocks
        if role == "assistant" and msg.get("tool_calls"):
            blocks = []
            if content:
                blocks.append({"type": "text", "text": content})
            for tc in msg["tool_calls"]:
                fn = tc["function"]
                raw_args = fn.get("arguments", "{}")
                blocks.append({
                    "type":  "tool_use",
                    "id":    tc["id"],
                    "name":  fn["name"],
                    "input": json.loads(raw_args) if isinstance(raw_args, str) else raw_args,
                })
            result.append({"role": "assistant", "content": blocks})
            i += 1
            continue

        # Consecutive Mistral tool-result messages → single Anthropic user message
        if role == "tool":
            tool_results = []
            while i < len(messages) and messages[i].get("role") == "tool":
                m = messages[i]
                tool_results.append({
                    "type":        "tool_result",
                    "tool_use_id": m.get("tool_call_id", ""),
                    "content":     m.get("content", ""),
                })
                i += 1
            result.append({"role": "user", "content": tool_results})
            continue

        result.append(msg)
        i += 1

    return result


def normalize_history_for_mistral(messages: list) -> list:
    """Convert Anthropic-format history entries to Mistral/OpenAI format.

    Handles:
    - {"role": "assistant", "content": [tool_use blocks]} → assistant with tool_calls array
    - {"role": "user", "content": [tool_result blocks]}  → separate {"role": "tool"} messages
    - Simple text messages pass through unchanged.
    """
    result = []
    for msg in messages:
        role    = msg.get("role", "")
        content = msg.get("content", "")

        # Anthropic assistant turn with tool_use blocks → Mistral tool_calls
        if role == "assistant" and isinstance(content, list):
            text_parts = [b.get("text", "") for b in content if b.get("type") == "text"]
            tool_uses  = [b for b in content if b.get("type") == "tool_use"]
            if tool_uses:
                result.append({
                    "role":    "assistant",
                    "content": " ".join(text_parts) if text_parts else "",
                    "tool_calls": [
                        {
                            "id":   tu["id"],
                            "type": "function",
                            "function": {
                                "name":      tu["name"],
                                "arguments": json.dumps(tu["input"])
                                             if isinstance(tu["input"], dict) else tu["input"],
                            },
                        }
                        for tu in tool_uses
                    ],
                })
            else:
                result.append({"role": "assistant", "content": " ".join(text_parts)})
            continue

        # Anthropic user turn with tool_result blocks → separate Mistral tool messages
        if role == "user" and isinstance(content, list) and any(
            b.get("type") == "tool_result" for b in content
        ):
            for block in content:
                if block.get("type") == "tool_result":
                    result.append({
                        "role":         "tool",
                        "content":      block.get("content", ""),
                        "tool_call_id": block.get("tool_use_id", ""),
                        "name":         "",
                    })
                elif block.get("type") == "text" and block.get("text"):
                    result.append({"role": "user", "content": block["text"]})
            continue

        # Simple text — ensure content is a string for Mistral
        if isinstance(content, list):
            text = " ".join(b.get("text", str(b)) for b in content if b.get("type") == "text")
            result.append({**msg, "content": text})
        else:
            result.append(msg)

    return result


# ─── Mistral agentic loop ────────────────────────────────────────────────────

async def agent_stream_mistral(
    messages: list,
    auth_token: str,
    company_id: int,
) -> AsyncIterator[str]:
    history = [{"role": "system", "content": SYSTEM_PROMPT}]
    for m in normalize_history_for_mistral(messages):
        if m.get("tool_calls") or m.get("role") == "tool":
            history.append(m)
        else:
            content = m["content"] if isinstance(m["content"], str) else json.dumps(m["content"])
            history.append({"role": m["role"], "content": content})

    while True:
        response = mistral_client.chat.complete(
            model=MISTRAL_MODEL,
            messages=history,
            tools=TOOLS_MISTRAL,
            tool_choice="auto",
        )

        msg    = response.choices[0].message
        finish = response.choices[0].finish_reason

        if msg.content:
            yield f"data: {json.dumps({'type': 'text', 'content': msg.content})}\n\n"

        if finish == "stop" or not msg.tool_calls:
            yield f"data: {json.dumps({'type': 'done'})}\n\n"
            break

        # Append assistant turn with tool calls
        history.append({
            "role": "assistant",
            "content": msg.content or "",
            "tool_calls": [
                {
                    "id":       tc.id,
                    "type":     "function",
                    "function": {"name": tc.function.name, "arguments": tc.function.arguments},
                }
                for tc in msg.tool_calls
            ],
        })

        for tc in msg.tool_calls:
            name      = tc.function.name
            tool_input = json.loads(tc.function.arguments)

            yield f"data: {json.dumps({'type': 'tool_start', 'tool': name, 'input': tool_input})}\n\n"
            result = await execute_tool(name, tool_input, auth_token, company_id)
            yield f"data: {json.dumps({'type': 'tool_end', 'tool': name})}\n\n"

            history.append({
                "role":         "tool",
                "content":      result,
                "tool_call_id": tc.id,
                "name":         name,
            })


# ─── Anthropic agentic loop (fallback) ───────────────────────────────────────

async def agent_stream_anthropic(
    messages: list,
    auth_token: str,
    company_id: int,
) -> AsyncIterator[str]:
    history = normalize_history_for_anthropic(list(messages))

    while True:
        response = anthropic_client.messages.create(
            model="claude-haiku-4-5-20251001",
            max_tokens=4096,
            system=SYSTEM_PROMPT,
            tools=TOOLS,
            messages=history,
        )

        for block in response.content:
            if hasattr(block, "text") and block.text:
                yield f"data: {json.dumps({'type': 'text', 'content': block.text})}\n\n"

        if response.stop_reason == "end_turn":
            yield f"data: {json.dumps({'type': 'done'})}\n\n"
            break

        if response.stop_reason == "tool_use":
            tool_results = []
            for block in response.content:
                if block.type == "tool_use":
                    yield f"data: {json.dumps({'type': 'tool_start', 'tool': block.name, 'input': block.input})}\n\n"
                    result = await execute_tool(block.name, block.input, auth_token, company_id)
                    yield f"data: {json.dumps({'type': 'tool_end', 'tool': block.name})}\n\n"
                    tool_results.append({
                        "type":        "tool_result",
                        "tool_use_id": block.id,
                        "content":     result,
                    })

            history.append({"role": "assistant", "content": response.content})
            history.append({"role": "user",      "content": tool_results})
        else:
            yield f"data: {json.dumps({'type': 'done'})}\n\n"
            break


# ─── Provider dispatcher with retry + exponential backoff ────────────────────

MISTRAL_MAX_RETRIES = 3
MISTRAL_BACKOFF_BASE = 1.0  # seconds; delays: 1s, 2s before 3rd attempt

async def agent_stream(
    messages: list,
    auth_token: str,
    company_id: int,
) -> AsyncIterator[str]:
    if mistral_client:
        for attempt in range(MISTRAL_MAX_RETRIES):
            try:
                async for event in agent_stream_mistral(messages, auth_token, company_id):
                    yield event
                return
            except Exception:
                if attempt < MISTRAL_MAX_RETRIES - 1:
                    await asyncio.sleep(MISTRAL_BACKOFF_BASE * (2 ** attempt))

        # All retries exhausted — warn and fall through to Anthropic
        yield f"data: {json.dumps({'type': 'warning', 'message': f'Mistral unavailable after {MISTRAL_MAX_RETRIES} attempts, switching to Anthropic'})}\n\n"

    if anthropic_client:
        async for event in agent_stream_anthropic(messages, auth_token, company_id):
            yield event
        return

    yield f"data: {json.dumps({'type': 'error', 'message': 'No AI provider configured'})}\n\n"
    yield f"data: {json.dumps({'type': 'done'})}\n\n"


# ─── FastAPI endpoints ────────────────────────────────────────────────────────

class ChatRequest(BaseModel):
    message:    str
    company_id: int
    period_id:  int | None = None
    history:    list       = []
    auth_token: str


@app.post("/chat")
async def chat(req: ChatRequest):
    if not mistral_client and not anthropic_client:
        raise HTTPException(status_code=503, detail="No AI provider configured")

    messages = list(req.history)
    messages.append({"role": "user", "content": req.message})

    return StreamingResponse(
        agent_stream(messages, req.auth_token, req.company_id),
        media_type="text/event-stream",
        headers={
            "Cache-Control":    "no-cache",
            "X-Accel-Buffering": "no",
        },
    )


@app.get("/health")
async def health():
    providers = []
    if mistral_client:
        providers.append("mistral")
    if anthropic_client:
        providers.append("anthropic")
    return {
        "status":          "ok",
        "service":         "zia-agent",
        "primary":         providers[0] if providers else None,
        "fallback":        providers[1] if len(providers) > 1 else None,
        "providers_ready": providers,
    }
