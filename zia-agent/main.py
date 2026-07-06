import os
import json
import asyncio
from contextlib import asynccontextmanager
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

try:
    from langfuse import Langfuse
except ImportError:
    Langfuse = None


@asynccontextmanager
async def lifespan(app: FastAPI):
    # refresh_credentials/_credentials_refresh_loop se definen más abajo en
    # este mismo módulo — se resuelven por nombre recién cuando arranca el
    # servidor, no cuando se define este lifespan, así que el orden es seguro.
    await refresh_credentials()
    task = asyncio.create_task(_credentials_refresh_loop())
    yield
    task.cancel()


app = FastAPI(title="ZIA Agent", version="2.0.0", lifespan=lifespan)

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

LANGFUSE_PUBLIC_KEY = os.getenv("LANGFUSE_PUBLIC_KEY", "")
LANGFUSE_SECRET_KEY = os.getenv("LANGFUSE_SECRET_KEY", "")
LANGFUSE_HOST       = os.getenv("LANGFUSE_HOST", "https://cloud.langfuse.com")

# Copia inmutable de lo que este contenedor trajo en su propio .env — Laravel
# solo conoce overrides guardados en su BD (nunca el .env de este servicio),
# así que "sin override" debe caer aquí, no a una cadena vacía. Sin esto, un
# superadmin que borra un override (pensando "vuelve al default") deja al
# agente sin ninguna key, así haya una real en este .env.
_ENV_ANTHROPIC_KEY = ANTHROPIC_KEY
_ENV_MISTRAL_KEY = MISTRAL_KEY
_ENV_LANGFUSE_PUBLIC_KEY = LANGFUSE_PUBLIC_KEY
_ENV_LANGFUSE_SECRET_KEY = LANGFUSE_SECRET_KEY

langfuse_client: "Langfuse | None" = None
if Langfuse and LANGFUSE_PUBLIC_KEY and LANGFUSE_SECRET_KEY:
    langfuse_client = Langfuse(
        public_key=LANGFUSE_PUBLIC_KEY,
        secret_key=LANGFUSE_SECRET_KEY,
        host=LANGFUSE_HOST,
    )

CREDENTIALS_REFRESH_INTERVAL = int(os.getenv("CREDENTIALS_REFRESH_INTERVAL_SECONDS", "60"))


async def refresh_credentials() -> None:
    """Refresca las credenciales de IA/Langfuse consultando a Laravel
    (/api/internal/credentials), para que un superadmin pueda rotarlas desde
    la UI sin reiniciar este contenedor. Si Laravel no responde, o no hay
    INTERNAL_API_SECRET configurado, conserva silenciosamente los clientes
    actuales — nunca debe tumbar el servicio por un refresco fallido."""
    global anthropic_client, mistral_client, langfuse_client
    global ANTHROPIC_KEY, MISTRAL_KEY, LANGFUSE_PUBLIC_KEY, LANGFUSE_SECRET_KEY

    if not INTERNAL_SECRET:
        return

    try:
        async with httpx.AsyncClient(timeout=10.0) as http:
            r = await http.get(
                f"{BACKEND_URL}/api/internal/credentials",
                headers={"X-Internal-Secret": INTERNAL_SECRET},
            )
        if r.status_code != 200:
            return
        data = r.json()
    except Exception:
        return

    # None/ausente = "sin override en Laravel" -> cae al .env propio de este
    # contenedor, nunca a cadena vacía (ver _ENV_* arriba).
    new_anthropic = data.get("anthropic_api_key") or _ENV_ANTHROPIC_KEY
    new_mistral = data.get("mistral_api_key") or _ENV_MISTRAL_KEY
    new_lf_public = data.get("langfuse_public_key") or _ENV_LANGFUSE_PUBLIC_KEY
    new_lf_secret = data.get("langfuse_secret_key") or _ENV_LANGFUSE_SECRET_KEY

    if new_anthropic != ANTHROPIC_KEY:
        ANTHROPIC_KEY = new_anthropic
        anthropic_client = anthropic.Anthropic(api_key=ANTHROPIC_KEY) if ANTHROPIC_KEY else None

    if new_mistral != MISTRAL_KEY:
        MISTRAL_KEY = new_mistral
        mistral_client = Mistral(api_key=MISTRAL_KEY) if (Mistral and MISTRAL_KEY) else None

    if (new_lf_public, new_lf_secret) != (LANGFUSE_PUBLIC_KEY, LANGFUSE_SECRET_KEY):
        LANGFUSE_PUBLIC_KEY = new_lf_public
        LANGFUSE_SECRET_KEY = new_lf_secret
        langfuse_client = (
            Langfuse(public_key=LANGFUSE_PUBLIC_KEY, secret_key=LANGFUSE_SECRET_KEY, host=LANGFUSE_HOST)
            if Langfuse and LANGFUSE_PUBLIC_KEY and LANGFUSE_SECRET_KEY
            else None
        )


async def _credentials_refresh_loop() -> None:
    while True:
        await asyncio.sleep(CREDENTIALS_REFRESH_INTERVAL)
        await refresh_credentials()

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
            "Use this to guide the user proactively toward a complete GHG inventory. "
            "Scoped automatically to the current company — do not ask the user for a company ID."
        ),
        "input_schema": {
            "type": "object",
            "properties": {
                "period_id":   {"type": "integer"},
                "sector_code": {"type": "string"},
            },
            "required": ["period_id", "sector_code"],
        },
    },
    {
        "name": "search_company_documents",
        "description": (
            "Searches the documents uploaded by this company (invoices, prior reports, "
            "certificates) for content relevant to a query, using semantic similarity. "
            "Use this when the user asks about something that might be documented in "
            "files they've uploaded, rather than in ZIA's structured data. "
            "Scoped automatically to the current company — do not ask the user for a company ID."
        ),
        "input_schema": {
            "type": "object",
            "properties": {
                "query": {"type": "string", "description": "What to search for, in natural language"},
            },
            "required": ["query"],
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
7. Si el usuario pregunta algo que podría estar en un documento que subió (facturas, certificados, reportes previos) en vez de en los datos estructurados de ZIA, usa search_company_documents antes de responder que no tienes esa información.

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
                # company_id viene del request autenticado, nunca del modelo —
                # mismo hallazgo que search_company_documents (ver ADR-002).
                sector    = tool_input["sector_code"]
                period_id = tool_input["period_id"]

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

            elif tool_name == "search_company_documents":
                # company_id viene del request autenticado, NUNCA del modelo — un
                # LLM puede alucinar un ID numérico plausible si se lo pedimos
                # como parámetro (confirmado: Mistral inventó 12345 en una prueba
                # real). Ver ADR-002 para el detalle de este hallazgo.
                r = await http.post(
                    f"{BACKEND_URL}/api/internal/search-documents",
                    json={
                        "company_id": company_id,
                        "query":      tool_input["query"],
                    },
                    headers=internal_headers,
                )
                if r.status_code != 200:
                    return json.dumps({"error": r.text})
                results = r.json().get("results", [])
                if not results:
                    return json.dumps({"results": [], "message": "No hay documentos que coincidan, o la empresa no tiene documentos subidos todavía."})
                return json.dumps({"results": results})

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
    # Build lookup tool_use_id → tool_name so tool messages carry the correct name
    tool_name_map: dict[str, str] = {}
    for m in messages:
        if m.get("role") == "assistant" and isinstance(m.get("content"), list):
            for block in m["content"]:
                if block.get("type") == "tool_use":
                    tool_name_map[block["id"]] = block["name"]

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
                    tool_use_id = block.get("tool_use_id", "")
                    result.append({
                        "role":         "tool",
                        "content":      block.get("content", ""),
                        "tool_call_id": tool_use_id,
                        "name":         tool_name_map.get(tool_use_id, ""),
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
    trace=None,
) -> AsyncIterator[str]:
    history = [{"role": "system", "content": SYSTEM_PROMPT}]
    for m in normalize_history_for_mistral(messages):
        if m.get("tool_calls") or m.get("role") == "tool":
            history.append(m)
        else:
            content = m["content"] if isinstance(m["content"], str) else json.dumps(m["content"])
            history.append({"role": m["role"], "content": content})

    while True:
        generation = trace.generation(
            name="mistral-completion",
            model=MISTRAL_MODEL,
            input=history,
        ) if trace else None

        response = mistral_client.chat.complete(
            model=MISTRAL_MODEL,
            messages=history,
            tools=TOOLS_MISTRAL,
            tool_choice="auto",
        )

        msg    = response.choices[0].message
        finish = response.choices[0].finish_reason

        if generation:
            usage = response.usage
            generation.end(
                output=msg.content or "",
                usage={"input": usage.prompt_tokens, "output": usage.completion_tokens} if usage else None,
            )

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

            if trace:
                trace.event(
                    name="tool_call",
                    input={"tool": name, "input": tool_input},
                    output=result,
                )

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
    trace=None,
) -> AsyncIterator[str]:
    history = normalize_history_for_anthropic(list(messages))

    while True:
        _model = "claude-haiku-4-5"
        generation = trace.generation(
            name="anthropic-completion",
            model=_model,
            input=history,
        ) if trace else None

        response = anthropic_client.messages.create(
            model=_model,
            max_tokens=4096,
            system=SYSTEM_PROMPT,
            tools=TOOLS,
            messages=history,
        )

        output_text = " ".join(b.text for b in response.content if hasattr(b, "text") and b.text)
        if generation:
            generation.end(
                output=output_text,
                usage={"input": response.usage.input_tokens, "output": response.usage.output_tokens},
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

                    if trace:
                        trace.event(
                            name="tool_call",
                            input={"tool": block.name, "input": block.input},
                            output=result,
                        )

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
    last_user_msg = next(
        (m["content"] for m in reversed(messages) if m.get("role") == "user"), ""
    )
    trace = langfuse_client.trace(
        name="zia-chat",
        user_id=str(company_id),
        input=last_user_msg,
        metadata={"history_len": len(messages)},
    ) if langfuse_client else None

    collected_output: list[str] = []

    try:
        if mistral_client:
            for attempt in range(MISTRAL_MAX_RETRIES):
                try:
                    async for event in agent_stream_mistral(messages, auth_token, company_id, trace):
                        if trace and '"type": "text"' in event:
                            try:
                                collected_output.append(json.loads(event[6:])["content"])
                            except Exception:
                                pass
                        yield event
                    return
                except Exception:
                    if attempt < MISTRAL_MAX_RETRIES - 1:
                        await asyncio.sleep(MISTRAL_BACKOFF_BASE * (2 ** attempt))

            yield f"data: {json.dumps({'type': 'warning', 'message': f'Mistral unavailable after {MISTRAL_MAX_RETRIES} attempts, switching to Anthropic'})}\n\n"

        if anthropic_client:
            async for event in agent_stream_anthropic(messages, auth_token, company_id, trace):
                if trace and '"type": "text"' in event:
                    try:
                        collected_output.append(json.loads(event[6:])["content"])
                    except Exception:
                        pass
                yield event
            return

        yield f"data: {json.dumps({'type': 'error', 'message': 'No AI provider configured'})}\n\n"
        yield f"data: {json.dumps({'type': 'done'})}\n\n"

    finally:
        if trace:
            trace.update(output=" ".join(collected_output))
            langfuse_client.flush()


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


class EmbedRequest(BaseModel):
    texts: list[str]


@app.post("/embed")
async def embed(req: EmbedRequest):
    """Genera embeddings via Mistral (mistral-embed) para el RAG de documentos.
    Llamado internamente por el backend Laravel — no por el usuario final."""
    if not mistral_client:
        raise HTTPException(status_code=503, detail="Mistral no está configurado (requerido para embeddings)")

    if not req.texts:
        return {"embeddings": []}

    response = mistral_client.embeddings.create(model="mistral-embed", inputs=req.texts)
    return {"embeddings": [item.embedding for item in response.data]}


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
        "observability":   "langfuse" if langfuse_client else "disabled",
        "langfuse_host":   LANGFUSE_HOST if langfuse_client else None,
    }
