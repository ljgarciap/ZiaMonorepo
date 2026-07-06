"""Tests for execute_tool — all httpx calls are mocked via respx."""
import json
import sys
import os

import pytest
import respx
import httpx

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
from main import execute_tool  # noqa: E402 — must come after sys.path insert


# ─── get_company_profile ─────────────────────────────────────────────────────

async def test_get_company_profile_returns_profile(
    backend_url, auth_token, company_id, sample_company_response, sample_periods_response
):
    """Company found with active period — result has active_period_id."""
    with respx.mock:
        respx.get(f"{backend_url}/api/companies").mock(
            return_value=httpx.Response(200, json=sample_company_response)
        )
        respx.get(f"{backend_url}/api/companies/{company_id}/periods").mock(
            return_value=httpx.Response(200, json=sample_periods_response)
        )
        raw = await execute_tool(
            "get_company_profile",
            {"company_id": company_id},
            auth_token=auth_token,
            company_id=company_id,
        )
        result = json.loads(raw)
        assert result["id"] == company_id
        assert result["active_period_id"] == 10
        assert result["name"] == "ECONOVA"


async def test_get_company_profile_company_not_found(backend_url, auth_token):
    """Empty company list returns an error dict."""
    with respx.mock:
        respx.get(f"{backend_url}/api/companies").mock(
            return_value=httpx.Response(200, json=[])
        )
        raw = await execute_tool(
            "get_company_profile",
            {"company_id": 99},
            auth_token=auth_token,
            company_id=99,
        )
        result = json.loads(raw)
        assert "error" in result


# ─── get_questionnaire ────────────────────────────────────────────────────────

async def test_get_questionnaire_returns_rules(backend_url, auth_token, company_id):
    """Returns full rule list when no scope_filter given."""
    rules = [
        {"emission_factor_id": 1, "scope_id": 1, "scope_name": "Alcance 1", "questionnaire_label": "Combustibles"},
        {"emission_factor_id": 2, "scope_id": 2, "scope_name": "Alcance 2", "questionnaire_label": "Electricidad"},
    ]
    with respx.mock:
        respx.get(f"{backend_url}/api/dictionaries/questionnaire").mock(
            return_value=httpx.Response(200, json=rules)
        )
        raw = await execute_tool(
            "get_questionnaire",
            {"sector_code": "servicios"},
            auth_token=auth_token,
            company_id=company_id,
        )
        result = json.loads(raw)
        assert len(result) == 2


async def test_get_questionnaire_with_scope_filter(backend_url, auth_token, company_id):
    """scope_filter=[1] returns only Alcance 1 items."""
    rules = [
        {"emission_factor_id": 1, "scope_id": 1, "scope_name": "Alcance 1", "questionnaire_label": "Combustibles"},
        {"emission_factor_id": 2, "scope_id": 2, "scope_name": "Alcance 2", "questionnaire_label": "Electricidad"},
        {"emission_factor_id": 3, "scope_id": 3, "scope_name": "Alcance 3", "questionnaire_label": "Viajes"},
    ]
    with respx.mock:
        respx.get(f"{backend_url}/api/dictionaries/questionnaire").mock(
            return_value=httpx.Response(200, json=rules)
        )
        raw = await execute_tool(
            "get_questionnaire",
            {"sector_code": "servicios", "scope_filter": [1]},
            auth_token=auth_token,
            company_id=company_id,
        )
        result = json.loads(raw)
        assert len(result) == 1
        assert result[0]["scope_id"] == 1


# ─── get_emission_factors ─────────────────────────────────────────────────────

async def test_get_emission_factors_no_filter(backend_url, auth_token, company_id):
    """Returns factor list when no filters are applied."""
    factors = [
        {"id": 1, "name": "Gas Natural", "unit": "m3"},
        {"id": 2, "name": "Electricidad Red", "unit": "kWh"},
    ]
    with respx.mock:
        respx.get(f"{backend_url}/api/dictionaries/factors").mock(
            return_value=httpx.Response(200, json=factors)
        )
        raw = await execute_tool(
            "get_emission_factors",
            {},
            auth_token=auth_token,
            company_id=company_id,
        )
        result = json.loads(raw)
        assert isinstance(result, list)
        assert len(result) == 2


async def test_get_emission_factors_with_scope_id(backend_url, auth_token, company_id):
    """scope_id is forwarded as a query parameter to the backend."""
    factors = [{"id": 1, "name": "Gas Natural", "scope_id": 1, "unit": "m3"}]
    with respx.mock:
        route = respx.get(f"{backend_url}/api/dictionaries/factors").mock(
            return_value=httpx.Response(200, json=factors)
        )
        raw = await execute_tool(
            "get_emission_factors",
            {"scope_id": 1},
            auth_token=auth_token,
            company_id=company_id,
        )
        result = json.loads(raw)
        assert isinstance(result, list)
        # Verify the request included scope_id as a query param
        assert route.called
        called_url = str(route.calls[0].request.url)
        assert "scope_id=1" in called_url


# ─── calculate_ghg ───────────────────────────────────────────────────────────

async def test_calculate_ghg_returns_result(backend_url, auth_token, company_id, sample_emission_factor_id):
    """200 response from backend is returned as-is."""
    calc_result = {
        "calculated_co2e": 1.234,
        "unit": "tCO2e",
        "factor_name": "Electricidad Red",
        "emissions_co2": 1.0,
        "emissions_ch4": 0.1,
        "emissions_n2o": 0.134,
    }
    with respx.mock:
        respx.post(f"{backend_url}/api/internal/calculate").mock(
            return_value=httpx.Response(200, json=calc_result)
        )
        raw = await execute_tool(
            "calculate_ghg",
            {"emission_factor_id": sample_emission_factor_id, "monthly_values": [100.0] * 12},
            auth_token=auth_token,
            company_id=company_id,
        )
        result = json.loads(raw)
        assert "calculated_co2e" in result
        assert result["calculated_co2e"] == pytest.approx(1.234)


async def test_calculate_ghg_backend_error(backend_url, auth_token, company_id, sample_emission_factor_id):
    """500 response from backend returns an error dict."""
    with respx.mock:
        respx.post(f"{backend_url}/api/internal/calculate").mock(
            return_value=httpx.Response(500, text="Internal Server Error")
        )
        raw = await execute_tool(
            "calculate_ghg",
            {"emission_factor_id": sample_emission_factor_id, "monthly_values": [100.0]},
            auth_token=auth_token,
            company_id=company_id,
        )
        result = json.loads(raw)
        assert "error" in result


# ─── save_emission ────────────────────────────────────────────────────────────

async def test_save_emission_success(backend_url, auth_token, company_id):
    """201 response returns the created emission record with an id."""
    saved = {"id": 42, "calculated_co2e": 1.5, "period_id": 10}
    with respx.mock:
        respx.post(f"{backend_url}/api/periods/10/emissions").mock(
            return_value=httpx.Response(201, json=saved)
        )
        raw = await execute_tool(
            "save_emission",
            {
                "period_id": 10,
                "emission_factor_id": 5,
                "quantity": 1200.0,
                "calculated_co2e": 1.5,
                "notes": "Electricidad enero-diciembre",
            },
            auth_token=auth_token,
            company_id=company_id,
        )
        result = json.loads(raw)
        assert "id" in result
        assert result["id"] == 42


async def test_save_emission_error(backend_url, auth_token, company_id):
    """400 response returns an error dict."""
    with respx.mock:
        respx.post(f"{backend_url}/api/periods/10/emissions").mock(
            return_value=httpx.Response(400, text="Bad Request")
        )
        raw = await execute_tool(
            "save_emission",
            {
                "period_id": 10,
                "emission_factor_id": 5,
                "quantity": 0.0,
                "calculated_co2e": 0.0,
            },
            auth_token=auth_token,
            company_id=company_id,
        )
        result = json.loads(raw)
        assert "error" in result


# ─── get_pending_questions ────────────────────────────────────────────────────

async def test_get_pending_questions_returns_pending_list(backend_url, auth_token, company_id):
    """Pending = all questions minus already-registered emission factor IDs."""
    all_questions = [
        {"emission_factor_id": 1, "questionnaire_label": "Combustibles", "is_required": True, "scope_name": "Alcance 1"},
        {"emission_factor_id": 2, "questionnaire_label": "Electricidad", "is_required": True, "scope_name": "Alcance 2"},
        {"emission_factor_id": 3, "questionnaire_label": "Viajes", "is_required": False, "scope_name": "Alcance 3"},
    ]
    existing_emissions = [
        {"emission_factor_id": 1, "calculated_co2e": 0.5}  # factor 1 already registered
    ]
    with respx.mock:
        respx.get(f"{backend_url}/api/dictionaries/questionnaire").mock(
            return_value=httpx.Response(200, json=all_questions)
        )
        respx.get(f"{backend_url}/api/periods/10/emissions").mock(
            return_value=httpx.Response(200, json=existing_emissions)
        )
        raw = await execute_tool(
            "get_pending_questions",
            {"company_id": company_id, "period_id": 10, "sector_code": "servicios"},
            auth_token=auth_token,
            company_id=company_id,
        )
        result = json.loads(raw)
        assert "pending" in result
        assert result["remaining"] == 2
        assert result["total"] == 3
        pending_ids = [p["emission_factor_id"] for p in result["pending"]]
        assert 1 not in pending_ids  # already registered
        assert 2 in pending_ids
        assert 3 in pending_ids


async def test_get_pending_questions_all_registered(backend_url, auth_token, company_id):
    """When all factors are registered, pending list is empty."""
    all_questions = [
        {"emission_factor_id": 1, "questionnaire_label": "Combustibles", "is_required": True, "scope_name": "Alcance 1"},
        {"emission_factor_id": 2, "questionnaire_label": "Electricidad", "is_required": True, "scope_name": "Alcance 2"},
    ]
    existing_emissions = [
        {"emission_factor_id": 1, "calculated_co2e": 0.5},
        {"emission_factor_id": 2, "calculated_co2e": 1.2},
    ]
    with respx.mock:
        respx.get(f"{backend_url}/api/dictionaries/questionnaire").mock(
            return_value=httpx.Response(200, json=all_questions)
        )
        respx.get(f"{backend_url}/api/periods/10/emissions").mock(
            return_value=httpx.Response(200, json=existing_emissions)
        )
        raw = await execute_tool(
            "get_pending_questions",
            {"company_id": company_id, "period_id": 10, "sector_code": "servicios"},
            auth_token=auth_token,
            company_id=company_id,
        )
        result = json.loads(raw)
        assert result["pending"] == []
        assert result["remaining"] == 0


# ─── search_company_documents ─────────────────────────────────────────────────

async def test_search_company_documents_returns_results(backend_url, auth_token, company_id):
    """200 response with results is passed through as-is."""
    search_result = {
        "results": [
            {"document_id": 1, "document_title": "factura.pdf", "content": "Consumo de diésel...", "similarity": 0.91},
        ]
    }
    with respx.mock:
        respx.post(f"{backend_url}/api/internal/search-documents").mock(
            return_value=httpx.Response(200, json=search_result)
        )
        raw = await execute_tool(
            "search_company_documents",
            {"query": "¿Cuánto diésel consumió la flota?"},
            auth_token=auth_token,
            company_id=company_id,
        )
        result = json.loads(raw)
        assert len(result["results"]) == 1
        assert result["results"][0]["document_title"] == "factura.pdf"


async def test_search_company_documents_no_matches_returns_friendly_message(backend_url, auth_token, company_id):
    """Empty results list still returns 200 — surface a helpful message, not an error."""
    with respx.mock:
        respx.post(f"{backend_url}/api/internal/search-documents").mock(
            return_value=httpx.Response(200, json={"results": []})
        )
        raw = await execute_tool(
            "search_company_documents",
            {"query": "algo que no existe"},
            auth_token=auth_token,
            company_id=company_id,
        )
        result = json.loads(raw)
        assert result["results"] == []
        assert "message" in result


async def test_search_company_documents_backend_error(backend_url, auth_token, company_id):
    """500 response from backend returns an error dict, not a crash."""
    with respx.mock:
        respx.post(f"{backend_url}/api/internal/search-documents").mock(
            return_value=httpx.Response(500, text="Internal Server Error")
        )
        raw = await execute_tool(
            "search_company_documents",
            {"query": "algo"},
            auth_token=auth_token,
            company_id=company_id,
        )
        result = json.loads(raw)
        assert "error" in result


async def test_search_company_documents_ignores_a_company_id_hallucinated_by_the_llm(
    backend_url, auth_token, company_id
):
    """Regression test: a Mistral run once hallucinated company_id=12345 when it was
    exposed as a model-fillable tool parameter. The tool no longer accepts it from
    tool_input at all — this confirms the trusted request-level company_id is what
    actually gets sent to the backend, even if a model somehow injects one anyway."""
    captured = {}

    def capture_request(request):
        captured["body"] = json.loads(request.content)
        return httpx.Response(200, json={"results": []})

    with respx.mock:
        respx.post(f"{backend_url}/api/internal/search-documents").mock(side_effect=capture_request)
        await execute_tool(
            "search_company_documents",
            {"company_id": 999999, "query": "algo"},  # como si el modelo lo hubiera inventado
            auth_token=auth_token,
            company_id=company_id,
        )

    assert captured["body"]["company_id"] == company_id


# ─── unknown tool ─────────────────────────────────────────────────────────────

async def test_execute_tool_unknown_tool_returns_gracefully(auth_token, company_id):
    """Unknown tool name returns a JSON error dict without raising."""
    with respx.mock:
        raw = await execute_tool(
            "nonexistent_tool",
            {},
            auth_token=auth_token,
            company_id=company_id,
        )
        result = json.loads(raw)
        assert "error" in result
        assert "nonexistent_tool" in result["error"]
