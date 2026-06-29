"""Tests for FastAPI endpoints /health and /chat."""
import json
import sys
import os
from unittest.mock import MagicMock, patch

import pytest
from fastapi.testclient import TestClient

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
import main  # noqa: E402 — conftest sets env vars before this import

client = TestClient(main.app)


# ─── /health ─────────────────────────────────────────────────────────────────

def test_health_endpoint_returns_ok():
    """/health must return 200 with status='ok' and service='zia-agent'."""
    response = client.get("/health")
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "ok"
    assert data["service"] == "zia-agent"


def test_health_shows_no_providers_when_keys_empty():
    """With empty API keys, providers_ready must be an empty list."""
    response = client.get("/health")
    data = response.json()
    assert data["providers_ready"] == []


# ─── /chat — error cases ──────────────────────────────────────────────────────

def test_chat_endpoint_returns_503_when_no_providers():
    """Without any AI provider configured, /chat must return 503."""
    payload = {
        "message": "Hola",
        "company_id": 1,
        "auth_token": "test-token",
        "history": [],
    }
    response = client.post("/chat", json=payload)
    assert response.status_code == 503


def test_chat_endpoint_validates_required_fields():
    """Payload missing 'message' must return 422 Unprocessable Entity."""
    response = client.post("/chat", json={"company_id": 1, "auth_token": "x"})
    assert response.status_code == 422


def test_chat_endpoint_validates_company_id_type():
    """String value for company_id (integer field) must return 422."""
    response = client.post(
        "/chat",
        json={"message": "test", "company_id": "abc", "auth_token": "x"},
    )
    assert response.status_code == 422


# ─── /chat — happy path (mocked providers) ───────────────────────────────────

async def _fake_stream(messages, auth_token, company_id):
    yield f'data: {json.dumps({"type": "text", "content": "Hola, soy ZIA"})}\n\n'
    yield f'data: {json.dumps({"type": "done"})}\n\n'


def test_chat_endpoint_accepts_valid_payload_structure():
    """With mocked providers and stream, a valid payload returns 200."""
    payload = {
        "message": "Hola ZIA",
        "company_id": 1,
        "auth_token": "test-token",
        "history": [],
    }
    with patch("main.mistral_client", MagicMock()), \
         patch("main.anthropic_client", MagicMock()), \
         patch("main.agent_stream", _fake_stream):
        response = client.post("/chat", json=payload)
    assert response.status_code == 200


def test_chat_endpoint_returns_streaming_response():
    """Response Content-Type must be text/event-stream for the /chat endpoint."""
    payload = {
        "message": "Hola ZIA",
        "company_id": 1,
        "auth_token": "test-token",
        "history": [],
    }
    with patch("main.mistral_client", MagicMock()), \
         patch("main.anthropic_client", MagicMock()), \
         patch("main.agent_stream", _fake_stream):
        response = client.post("/chat", json=payload)
    content_type = response.headers.get("content-type", "")
    assert "text/event-stream" in content_type
