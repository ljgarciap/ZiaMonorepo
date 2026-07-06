"""Tests for the /embed endpoint (Mistral embeddings for the RAG del agente)."""
import sys
import os
from unittest.mock import MagicMock, patch

from fastapi.testclient import TestClient

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
import main  # noqa: E402 — conftest sets env vars before this import

client = TestClient(main.app)


def test_embed_returns_503_when_mistral_not_configured():
    """Sin MISTRAL_API_KEY (default en el entorno de tests), debe fallar explícito."""
    response = client.post("/embed", json={"texts": ["hola"]})
    assert response.status_code == 503


def test_embed_returns_empty_list_for_empty_input():
    """Lista vacía de texts no debe intentar llamar a Mistral."""
    with patch("main.mistral_client", MagicMock()):
        response = client.post("/embed", json={"texts": []})
    assert response.status_code == 200
    assert response.json() == {"embeddings": []}


def test_embed_returns_one_vector_per_input_text():
    """Cada texto de entrada debe producir exactamente un vector de salida, en orden."""
    fake_client = MagicMock()
    fake_client.embeddings.create.return_value = MagicMock(
        data=[
            MagicMock(embedding=[0.1, 0.2, 0.3]),
            MagicMock(embedding=[0.4, 0.5, 0.6]),
        ]
    )

    with patch("main.mistral_client", fake_client):
        response = client.post("/embed", json={"texts": ["primer chunk", "segundo chunk"]})

    assert response.status_code == 200
    embeddings = response.json()["embeddings"]
    assert len(embeddings) == 2
    assert embeddings[0] == [0.1, 0.2, 0.3]
    assert embeddings[1] == [0.4, 0.5, 0.6]
    fake_client.embeddings.create.assert_called_once_with(
        model="mistral-embed", inputs=["primer chunk", "segundo chunk"]
    )


def test_embed_validates_request_body():
    """Body sin 'texts' debe devolver 422, no un 500."""
    response = client.post("/embed", json={})
    assert response.status_code == 422
