"""Tests for refresh_credentials() — permite rotar API keys desde la UI de
Laravel sin reiniciar el contenedor del agente."""
import sys
import os
from unittest.mock import MagicMock, patch

import httpx
import pytest
import respx

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
import main  # noqa: E402


@pytest.fixture(autouse=True)
def restore_credential_globals():
    """refresh_credentials() reassigns module-level globals — sin restaurarlos,
    un test que deja un cliente falso (un `object()` sentinel) contamina
    cualquier otro test que importe `main` después, sin importar el archivo."""
    saved = (
        main.ANTHROPIC_KEY, main.MISTRAL_KEY,
        main.LANGFUSE_PUBLIC_KEY, main.LANGFUSE_SECRET_KEY,
        main.anthropic_client, main.mistral_client, main.langfuse_client,
        main.INTERNAL_SECRET,
        main._ENV_ANTHROPIC_KEY, main._ENV_MISTRAL_KEY,
        main._ENV_LANGFUSE_PUBLIC_KEY, main._ENV_LANGFUSE_SECRET_KEY,
    )
    yield
    (
        main.ANTHROPIC_KEY, main.MISTRAL_KEY,
        main.LANGFUSE_PUBLIC_KEY, main.LANGFUSE_SECRET_KEY,
        main.anthropic_client, main.mistral_client, main.langfuse_client,
        main.INTERNAL_SECRET,
        main._ENV_ANTHROPIC_KEY, main._ENV_MISTRAL_KEY,
        main._ENV_LANGFUSE_PUBLIC_KEY, main._ENV_LANGFUSE_SECRET_KEY,
    ) = saved


async def test_refresh_updates_client_when_key_changes(backend_url):
    main.ANTHROPIC_KEY = ""
    main.anthropic_client = None
    main.INTERNAL_SECRET = "test-secret-ci"

    with respx.mock:
        respx.get(f"{backend_url}/api/internal/credentials").mock(
            return_value=httpx.Response(200, json={
                "mistral_api_key": "",
                "anthropic_api_key": "new-anthropic-key",
                "langfuse_public_key": "",
                "langfuse_secret_key": "",
            })
        )
        with patch("main.anthropic.Anthropic") as fake_anthropic_cls:
            fake_anthropic_cls.return_value = MagicMock()
            await main.refresh_credentials()

    assert main.ANTHROPIC_KEY == "new-anthropic-key"
    fake_anthropic_cls.assert_called_once_with(api_key="new-anthropic-key")
    assert main.anthropic_client is not None


async def test_refresh_is_noop_when_key_unchanged(backend_url):
    main.ANTHROPIC_KEY = "same-key"
    sentinel_client = object()
    main.anthropic_client = sentinel_client
    main.INTERNAL_SECRET = "test-secret-ci"

    with respx.mock:
        respx.get(f"{backend_url}/api/internal/credentials").mock(
            return_value=httpx.Response(200, json={
                "mistral_api_key": "",
                "anthropic_api_key": "same-key",
                "langfuse_public_key": "",
                "langfuse_secret_key": "",
            })
        )
        await main.refresh_credentials()

    # Same key -> client object must not be rebuilt
    assert main.anthropic_client is sentinel_client


async def test_refresh_keeps_current_clients_when_backend_unreachable(backend_url):
    main.MISTRAL_KEY = "still-the-old-key"
    sentinel_client = object()
    main.mistral_client = sentinel_client
    main.INTERNAL_SECRET = "test-secret-ci"

    with respx.mock:
        respx.get(f"{backend_url}/api/internal/credentials").mock(
            side_effect=httpx.ConnectError("connection refused")
        )
        await main.refresh_credentials()

    assert main.MISTRAL_KEY == "still-the-old-key"
    assert main.mistral_client is sentinel_client


async def test_refresh_falls_back_to_the_containers_own_env_key_when_override_is_removed(backend_url):
    """Regresión: encontrado en verificación real end-to-end. Un superadmin
    guarda un override, luego lo borra esperando volver al valor real del
    .env de este contenedor — Laravel reporta null (sin override), y eso NO
    debe traducirse en una key vacía que rompe el agente."""
    main._ENV_MISTRAL_KEY = "real-key-from-this-containers-env"
    main.MISTRAL_KEY = "leftover-override-value"
    main.mistral_client = object()
    main.INTERNAL_SECRET = "test-secret-ci"

    with respx.mock:
        respx.get(f"{backend_url}/api/internal/credentials").mock(
            return_value=httpx.Response(200, json={
                "mistral_api_key": None,  # sin override en Laravel
                "anthropic_api_key": None,
                "langfuse_public_key": None,
                "langfuse_secret_key": None,
            })
        )
        with patch("main.Mistral") as fake_mistral_cls:
            fake_mistral_cls.return_value = MagicMock()
            await main.refresh_credentials()

    assert main.MISTRAL_KEY == "real-key-from-this-containers-env"
    fake_mistral_cls.assert_called_once_with(api_key="real-key-from-this-containers-env")


async def test_refresh_skips_the_http_call_when_no_internal_secret(backend_url):
    main.INTERNAL_SECRET = ""

    with respx.mock:
        route = respx.get(f"{backend_url}/api/internal/credentials").mock(
            return_value=httpx.Response(200, json={})
        )
        await main.refresh_credentials()

    assert route.call_count == 0
