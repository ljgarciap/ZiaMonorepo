import os
import pytest

# Set empty keys BEFORE importing main to prevent client initialization
os.environ.setdefault("ANTHROPIC_API_KEY", "")
os.environ.setdefault("MISTRAL_API_KEY", "")
os.environ.setdefault("INTERNAL_API_SECRET", "test-secret-ci")
os.environ.setdefault("ZIA_BACKEND_URL", "http://localhost:8000")


@pytest.fixture
def backend_url():
    return os.environ.get("ZIA_BACKEND_URL", "http://localhost:8000")


@pytest.fixture
def auth_token():
    return "test-bearer-token"


@pytest.fixture
def company_id():
    return 1


@pytest.fixture
def sample_emission_factor_id():
    return 5


@pytest.fixture
def sample_company_response():
    return [
        {
            "id": 1,
            "name": "ECONOVA",
            "sector": {"code": "servicios", "name": "Servicios"},
            "num_employees": 50,
            "floor_sqm": 1200,
        }
    ]


@pytest.fixture
def sample_periods_response():
    return [{"id": 10, "year": 2024, "status": "active"}]
