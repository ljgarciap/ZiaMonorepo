# Guía de testing — ZIA Carbon Control

Cómo ejecutar, escribir y entender los tests de los tres servicios.

---

## Resumen de cobertura actual

| Servicio | Framework | Tests | Notas |
|---|---|---|---|
| Backend (Laravel) | PHPUnit | 176 tests / 434 assertions | SQLite en memoria |
| Frontend (Angular) | Vitest | 89 tests | ≥ 60% coverage (statements/branches/functions/lines) |
| ZIA Agent (Python) | pytest | 51 tests | mock de llamadas HTTP al backend |

---

## Backend (Laravel)

### Ejecutar

```bash
# Dentro de Docker (recomendado)
docker compose exec backend php artisan test

# Local (requiere PHP + composer instalados)
cd backend && php artisan test

# Suite específica
docker compose exec backend php artisan test --testsuite=Feature
docker compose exec backend php artisan test --testsuite=Unit

# Test específico
docker compose exec backend php artisan test --filter=ReportControllerTest
```

### Configuración

La suite usa **SQLite en memoria** (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`). No requiere PostgreSQL ni Redis — configurado en `backend/phpunit.xml`.

Variables de test relevantes:
```xml
<env name="INTERNAL_API_SECRET" value="test-secret-ci"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="CACHE_STORE" value="array"/>
```

### Estructura de tests

```
backend/tests/
├── Unit/
│   ├── CarbonFootprintServiceTest.php    Motor de cálculo GWP AR6
│   ├── FormulaEvaluationServiceTest.php  Fórmulas dinámicas
│   └── ...
└── Feature/
    ├── AuthControllerTest.php
    ├── CarbonEmissionControllerTest.php
    ├── DashboardControllerTest.php
    ├── AISidecarControllerTest.php       SSE proxy tests
    ├── ReportControllerTest.php          PDF + Excel (PDF mock)
    ├── Admin/
    │   ├── AdminMasterDataControllerTest.php
    │   ├── AdminUserControllerTest.php
    │   └── CompanyGroupControllerTest.php
    └── ...
```

### Patrones importantes

**Autenticación en tests:**
```php
$user = User::factory()->create(['role' => 'superadmin']);
$token = $user->createToken('test')->accessToken;
$this->withHeader('Authorization', "Bearer $token");
```

**Contexto de empresa:**
```php
$this->withHeaders([
    'Authorization' => "Bearer $token",
    'X-Company-ID' => $company->id,
]);
```

**Mock de PDF (Barryvdh DomPDF):**
```php
Pdf::shouldReceive('loadView')
    ->once()
    ->andReturnSelf();   // andReturnSelf() satisface el return type `self`
Pdf::shouldReceive('download')
    ->once()
    ->andReturn(response()->make('PDF', 200, ['Content-Type' => 'application/pdf']));
```

**SSE Content-Type:**
```php
// NO usar assertHeader — Laravel agrega '; charset=utf-8'
$this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));
```

**Agente ZIA no disponible:**
```php
// Fuerza connection refused en el proxy SSE
config(['services.zia_agent_url' => 'http://localhost:1']);
```

### Controladores excluidos de coverage

Definidos en `backend/phpunit.xml` → `<exclude>`:
- `CompanyController.php` — requiere refactor previo
- `AI/` services — sin API keys en CI
- `ThingsBoardService.php` — sin ThingsBoard en CI
- `ActivityLog.php`, `IotDevice.php` — modelos sin lógica de negocio

---

## Frontend (Angular)

### Ejecutar

```bash
cd frontend
npx ng test --watch=false

# Con reporte de coverage
npx ng test --watch=false --code-coverage
```

> El runner es **Vitest** (no Karma). Configurado en `angular.json` bajo `"test": { "builder": "@angular/build:unit-test" }`.

### Thresholds de cobertura

Configurados en `angular.json`:
```json
"coverageThreshold": {
  "global": {
    "statements": 60,
    "branches": 60,
    "functions": 60,
    "lines": 60
  }
}
```

El build falla si baja de 60% en cualquiera de las cuatro métricas.

### Estructura de tests

```
frontend/src/app/
├── components/
│   ├── dashboard/dashboard.component.spec.ts
│   ├── form/form.component.spec.ts
│   ├── zia-chat/zia-chat.component.spec.ts
│   └── admin/...
└── services/
    ├── auth.service.spec.ts
    ├── carbon.service.spec.ts
    └── dashboard.service.spec.ts
```

### Patrones comunes

**Mock de HttpClient:**
```typescript
const httpMock = TestBed.inject(HttpTestingController);
service.getEmissions().subscribe();
const req = httpMock.expectOne('/api/periods/1/emissions');
req.flush([{ id: 1, calculated_co2e: 0.45 }]);
```

**Mock de servicio con spyOn:**
```typescript
const authService = TestBed.inject(AuthService);
spyOn(authService, 'isAuthenticated').and.returnValue(true);
```

---

## ZIA Agent (Python)

### Ejecutar

```bash
cd zia-agent
source .venv/bin/activate     # crear con: python3 -m venv .venv && pip install -r requirements.txt

pytest                        # todos los tests
pytest -v                     # verbose
pytest tests/test_history_normalization.py   # suite específica
pytest -k "mistral"           # tests con "mistral" en el nombre
```

### Estructura de tests

```
zia-agent/tests/
├── conftest.py                      Fixtures compartidos (mock de httpx)
├── test_agent_streams.py            Agentic loop completo (Mistral + Anthropic)
├── test_api_endpoints.py            Endpoints /chat y /health
├── test_execute_tool.py             Ejecución de las 6 MCP tools
├── test_history_normalization.py    Normalización Mistral↔Anthropic (16 tests)
└── test_provider_dispatch.py        Selección de proveedor y fallback
```

### Patrones importantes

**Mock de llamadas HTTP al backend:**
```python
# conftest.py usa httpx_mock (pytest-httpx)
httpx_mock.add_response(
    url="http://backend:8000/api/internal/calculate",
    json={"calculated_co2e": 0.45}
)
```

**Test de normalización idempotente:**
```python
# Aplicar dos veces debe dar el mismo resultado
result1 = normalize_history_for_anthropic(messages)
result2 = normalize_history_for_anthropic(result1)
assert result1 == result2
```

**Test de fallback Mistral → Anthropic:**
```python
# Configurar Mistral para fallar 3 veces → debe caer a Anthropic
with patch('mistralai.client.MistralClient.chat', side_effect=Exception("timeout")):
    response = client.post("/chat", json={...})
assert any(e["type"] == "warning" for e in parse_sse(response))
```

---

## Agregar tests nuevos

### Backend — Feature test mínimo

```php
class MiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->postJson('/api/mi-endpoint', []);
        $response->assertStatus(401);
    }

    public function test_happy_path(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->accessToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/mi-endpoint', ['campo' => 'valor']);

        $response->assertStatus(200);
        $response->assertJsonStructure(['id', 'campo']);
    }
}
```

### ZIA Agent — Test de nueva tool

```python
def test_nueva_tool_happy_path(httpx_mock):
    httpx_mock.add_response(
        url="http://backend:8000/api/nueva-ruta",
        json={"resultado": "ok"}
    )
    result = execute_tool("nueva_tool", {"param": "valor"}, auth_token="test")
    assert result["resultado"] == "ok"

def test_nueva_tool_error(httpx_mock):
    httpx_mock.add_response(
        url="http://backend:8000/api/nueva-ruta",
        status_code=404
    )
    result = execute_tool("nueva_tool", {"param": "valor"}, auth_token="test")
    assert "error" in result
```
