"""Tests for the agent_stream provider dispatcher."""
import json
import sys
import os
from unittest.mock import MagicMock, patch

import pytest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
from main import agent_stream  # noqa: E402


# ─── helpers ──────────────────────────────────────────────────────────────────

async def collect_events(generator) -> list[dict]:
    """Drain an async event generator and parse each SSE line to dict."""
    events = []
    async for raw in generator:
        for line in raw.splitlines():
            line = line.strip()
            if line.startswith("data: "):
                events.append(json.loads(line[len("data: "):]))
    return events


def make_mistral_mock(content: str = "Hola desde Mistral"):
    mock_msg = MagicMock()
    mock_msg.content = content
    mock_msg.tool_calls = None

    mock_choice = MagicMock()
    mock_choice.message = mock_msg
    mock_choice.finish_reason = "stop"

    mock_response = MagicMock()
    mock_response.choices = [mock_choice]

    mock_client = MagicMock()
    mock_client.chat.complete.return_value = mock_response
    return mock_client


def make_anthropic_mock(text: str = "Hola desde Anthropic"):
    mock_block = MagicMock()
    mock_block.text = text
    mock_block.type = "text"

    mock_response = MagicMock()
    mock_response.content = [mock_block]
    mock_response.stop_reason = "end_turn"

    mock_client = MagicMock()
    mock_client.messages.create.return_value = mock_response
    return mock_client


# ─── dispatch tests ───────────────────────────────────────────────────────────

async def test_dispatch_uses_mistral_when_available(auth_token, company_id):
    """When mistral_client is available, agent_stream_mistral is used."""
    mock_mistral = make_mistral_mock("Respuesta Mistral")
    messages = [{"role": "user", "content": "Hola"}]

    with patch("main.mistral_client", mock_mistral), \
         patch("main.anthropic_client", None):
        events = await collect_events(agent_stream(messages, auth_token, company_id))

    # Mistral mock was called and a text event was produced
    mock_mistral.chat.complete.assert_called_once()
    types = [e["type"] for e in events]
    assert "text" in types
    assert "done" in types


async def test_dispatch_skips_mistral_when_key_empty(auth_token, company_id):
    """With mistral_client=None, dispatcher skips Mistral and uses Anthropic."""
    mock_anthropic = make_anthropic_mock("Respuesta Anthropic")
    messages = [{"role": "user", "content": "Hola"}]

    with patch("main.mistral_client", None), \
         patch("main.anthropic_client", mock_anthropic):
        events = await collect_events(agent_stream(messages, auth_token, company_id))

    mock_anthropic.messages.create.assert_called_once()
    types = [e["type"] for e in events]
    assert "text" in types
    assert "done" in types


async def test_dispatch_error_event_when_both_unavailable(auth_token, company_id):
    """With both clients None, agent_stream emits an error event."""
    messages = [{"role": "user", "content": "Hola"}]

    with patch("main.mistral_client", None), patch("main.anthropic_client", None):
        events = await collect_events(agent_stream(messages, auth_token, company_id))

    error_events = [e for e in events if e.get("type") == "error"]
    assert len(error_events) >= 1
    assert "No AI provider" in error_events[0].get("message", "")


async def test_dispatch_done_event_always_present(auth_token, company_id):
    """Done event is always emitted, even when no providers are available."""
    messages = [{"role": "user", "content": "Hola"}]

    with patch("main.mistral_client", None), patch("main.anthropic_client", None):
        events = await collect_events(agent_stream(messages, auth_token, company_id))

    done_events = [e for e in events if e.get("type") == "done"]
    assert len(done_events) >= 1
