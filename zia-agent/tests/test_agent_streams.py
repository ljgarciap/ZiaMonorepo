"""Tests for agent_stream_mistral, agent_stream_anthropic, and agent_stream."""
import json
import sys
import os
from unittest.mock import MagicMock, patch

import pytest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
from main import agent_stream_mistral, agent_stream_anthropic, agent_stream  # noqa: E402


# ─── helpers ──────────────────────────────────────────────────────────────────

async def collect_events(generator) -> list[dict]:
    """Drain an async event generator and parse each SSE line to dict."""
    events = []
    async for raw in generator:
        # Each raw event is "data: {...}\n\n"
        for line in raw.splitlines():
            line = line.strip()
            if line.startswith("data: "):
                events.append(json.loads(line[len("data: "):]))
    return events


def make_mistral_mock(content: str = "Hola desde Mistral", tool_calls=None, finish_reason: str = "stop"):
    """Build a mock Mistral client that returns a single non-streaming response."""
    mock_msg = MagicMock()
    mock_msg.content = content
    mock_msg.tool_calls = tool_calls

    mock_choice = MagicMock()
    mock_choice.message = mock_msg
    mock_choice.finish_reason = finish_reason

    mock_response = MagicMock()
    mock_response.choices = [mock_choice]

    mock_client = MagicMock()
    mock_client.chat.complete.return_value = mock_response
    return mock_client


def make_anthropic_mock(text: str = "Hola desde Anthropic", stop_reason: str = "end_turn"):
    """Build a mock Anthropic client that returns a text block response."""
    mock_block = MagicMock()
    mock_block.text = text
    mock_block.type = "text"

    mock_response = MagicMock()
    mock_response.content = [mock_block]
    mock_response.stop_reason = stop_reason

    mock_client = MagicMock()
    mock_client.messages.create.return_value = mock_response
    return mock_client


# ─── agent_stream: provider dispatch ─────────────────────────────────────────

async def test_agent_stream_uses_anthropic_when_no_mistral(auth_token, company_id):
    """With mistral_client=None, agent_stream must delegate to anthropic."""
    mock_anthropic = make_anthropic_mock(text="Solo Anthropic disponible")
    messages = [{"role": "user", "content": "Hola"}]

    with patch("main.mistral_client", None), patch("main.anthropic_client", mock_anthropic):
        events = await collect_events(agent_stream(messages, auth_token, company_id))

    types = [e["type"] for e in events]
    assert "text" in types
    assert "done" in types
    mock_anthropic.messages.create.assert_called_once()


async def test_agent_stream_falls_back_to_anthropic_on_mistral_error(auth_token, company_id):
    """If agent_stream_mistral raises, anthropic is used as fallback."""

    async def failing_mistral(*args, **kwargs):
        raise RuntimeError("Mistral API down")
        yield  # makes this an async generator

    mock_anthropic = make_anthropic_mock(text="Fallback OK")
    messages = [{"role": "user", "content": "test"}]

    with patch("main.mistral_client", MagicMock()), \
         patch("main.anthropic_client", mock_anthropic), \
         patch("main.agent_stream_mistral", failing_mistral):
        events = await collect_events(agent_stream(messages, auth_token, company_id))

    types = [e["type"] for e in events]
    assert "done" in types
    mock_anthropic.messages.create.assert_called_once()


# ─── agent_stream_anthropic: event format ────────────────────────────────────

async def test_agent_stream_yields_text_event(auth_token, company_id):
    """Text response must yield an event with type='text' and a content field."""
    mock_anthropic = make_anthropic_mock(text="Respuesta de prueba")
    messages = [{"role": "user", "content": "Hola"}]

    with patch("main.anthropic_client", mock_anthropic):
        events = await collect_events(agent_stream_anthropic(messages, auth_token, company_id))

    text_events = [e for e in events if e.get("type") == "text"]
    assert len(text_events) >= 1
    assert text_events[0]["content"] == "Respuesta de prueba"


async def test_agent_stream_yields_tool_start_event(auth_token, company_id):
    """When Anthropic returns a tool_use block, a tool_start event is emitted."""
    import respx
    import httpx

    # First response: tool_use (set text=None so the text-yield guard skips it)
    tool_block = MagicMock()
    tool_block.type = "tool_use"
    tool_block.name = "get_company_profile"
    tool_block.input = {"company_id": 1}
    tool_block.id = "tu_abc123"
    tool_block.text = None  # prevent MagicMock from being JSON-serialized as text

    first_response = MagicMock()
    first_response.content = [tool_block]
    first_response.stop_reason = "tool_use"

    # Second response (after tool result): end_turn
    text_block = MagicMock()
    text_block.type = "text"
    text_block.text = "Perfil obtenido correctamente"

    second_response = MagicMock()
    second_response.content = [text_block]
    second_response.stop_reason = "end_turn"

    mock_anthropic = MagicMock()
    mock_anthropic.messages.create.side_effect = [first_response, second_response]

    messages = [{"role": "user", "content": "Dame el perfil de la empresa"}]

    with respx.mock:
        respx.get("http://test-backend:8000/api/companies").mock(
            return_value=httpx.Response(200, json=[{"id": 1, "name": "ECONOVA", "sector": {"code": "servicios"}}])
        )
        respx.get("http://test-backend:8000/api/companies/1/periods").mock(
            return_value=httpx.Response(200, json=[{"id": 10, "year": 2024, "status": "active"}])
        )
        with patch("main.anthropic_client", mock_anthropic):
            events = await collect_events(agent_stream_anthropic(messages, auth_token, company_id))

    tool_start_events = [e for e in events if e.get("type") == "tool_start"]
    assert len(tool_start_events) >= 1
    assert tool_start_events[0]["tool"] == "get_company_profile"


async def test_agent_stream_yields_done_event(auth_token, company_id):
    """The final event from any agent_stream function must be type='done'."""
    mock_anthropic = make_anthropic_mock(text="Respuesta final")
    messages = [{"role": "user", "content": "Hola"}]

    with patch("main.anthropic_client", mock_anthropic):
        events = await collect_events(agent_stream_anthropic(messages, auth_token, company_id))

    assert events[-1]["type"] == "done"


async def test_agent_stream_yields_error_when_no_providers(auth_token, company_id):
    """With both clients None, agent_stream yields an error event followed by done."""
    messages = [{"role": "user", "content": "Hola"}]

    with patch("main.mistral_client", None), patch("main.anthropic_client", None):
        events = await collect_events(agent_stream(messages, auth_token, company_id))

    types = [e["type"] for e in events]
    assert "error" in types
    assert "done" in types
    # error must come before done
    assert types.index("error") < types.index("done")
