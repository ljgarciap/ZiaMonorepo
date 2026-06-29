# ZIA Backend — API Reference (Laravel 11)

**Última actualización:** 2026-06-29 | **Responsable:** Tech Writer  
**Base URL:** `http://localhost:8000/api`

---

## Autenticación

Todas las rutas (excepto las marcadas como **Público**) requieren:

```
Authorization: Bearer <access_token>
```

El token se obtiene en `POST /api/login`. Las rutas de contexto de empresa requieren además:

```
X-Company-ID: <company_id>
```

El header `X-Context-Role` es opcional; si se omite, el sistema usa el rol del pivot `company_user`.

---

## Resumen de rutas

| Dominio | Método | Ruta | Rol mínimo |
|---|---|---|---|
| **Auth** | POST | `/login` | Público |
| | POST | `/register` | Público |
| | POST | `/logout` | Autenticado |
| | GET | `/user` | Autenticado |
| | GET | `/health` | Público |
| **Emisiones** | POST | `/periods/{period}/emissions` | user |
| | GET | `/periods/{period}/emissions` | user |
| | GET | `/companies/{company}/emissions/history` | user |
| | DELETE | `/emissions/{emission}` | user |
| **Dashboard** | GET | `/dashboard/summary` | user |
| | GET | `/dashboard/trends` | user |
| **Diccionarios** | GET | `/companies` | user |
| | GET | `/companies/{id}/periods` | user |
| | GET | `/dictionaries/factors` | user |
| | GET | `/dictionaries/questionnaire` | user |
| **Reportes** | GET | `/reports/periods/{period}/pdf` | user |
| | GET | `/reports/periods/{period}/excel` | user |
| **Agente ZIA** | POST | `/ai/chat` | user |
| | GET | `/ai/recommendations` | user |
| **Admin — Empresas** | GET/POST | `/admin/companies` | admin |
| | PUT/DELETE | `/admin/companies/{company}` | admin |
| | POST | `/admin/companies/{company}/periods` | admin |
| | PUT/DELETE | `/admin/periods/{period}` | admin |
| | GET/PUT | `/admin/companies/{company}/factors` | admin |
| **Admin — Usuarios** | GET/POST | `/admin/users` | admin |
| | PUT/DELETE | `/admin/users/{user}` | admin |
| | POST | `/admin/users/{id}/restore` | superadmin |
| | GET | `/admin/audit-logs` | admin |
| **Admin — Master data** | GET/POST/DELETE | `/admin/categories` | superadmin |
| | POST/PUT/DELETE | `/admin/factors` | superadmin |
| | CRUD | `/admin/formulas` | superadmin |
| | CRUD | `/admin/units` | superadmin |
| | CRUD | `/admin/scopes` | superadmin |
| | CRUD | `/admin/sectors` | superadmin |
| **Admin — Grupos** | GET/POST | `/admin/groups` | superadmin |
| | GET | `/admin/groups/{group}/summary` | superadmin |
| | POST/DELETE | `/admin/groups/{group}/companies` | superadmin |
| | DELETE | `/admin/groups/{group}` | superadmin |
| **Interno** | POST | `/internal/calculate` | InternalOnly† |

† Solo accesible desde la red Docker interna. Requiere header `X-Internal-Secret`.

---

## Auth

### POST /login

**Público**

```json
// Request
{ "email": "user@empresa.co", "password": "secret" }

// Response 200
{
  "token_type": "Bearer",
  "access_token": "eyJ...",
  "user": { "id": 1, "name": "...", "email": "...", "role": "user" }
}

// Errores
// 401 — credenciales inválidas
// 422 — campos requeridos faltantes
```

### POST /register

**Público**

```json
// Request
{ "name": "Ana López", "email": "ana@empresa.co", "password": "secret", "password_confirmation": "secret" }

// Response 201
{ "token_type": "Bearer", "access_token": "eyJ...", "user": { ... } }
```

### POST /logout

Invalida el token actual.

```
Response 200: { "message": "Logged out successfully" }
```

### GET /user

Devuelve el usuario autenticado con sus empresas.

---

## Emisiones de carbono

Requieren `X-Company-ID`. El middleware `context.aware` valida que el usuario tenga acceso a esa empresa.

### POST /periods/{period}/emissions

Registra una emisión calculada para un período.

```json
// Request
{
  "emission_factor_id": 5,
  "quantity": 1000,            // dato de actividad total (alternativo a monthly_inputs)
  "monthly_inputs": [80, 90, 95, 100, 85, 90, 88, 92, 87, 95, 91, 97],  // 1-12 valores
  "notes": "Electricidad red nacional 2024"
}

// Response 201
{
  "id": 42,
  "period_id": 3,
  "emission_factor_id": 5,
  "quantity": 1090,
  "calculated_co2e": 0.4523,
  "uncertainty_result": 3.21,
  "notes": "Electricidad red nacional 2024",
  "created_at": "2024-06-15T10:30:00Z"
}

// Errores
// 422 — emission_factor_id requerido o inexistente
// 404 — período no encontrado
```

Si se envían `monthly_inputs`, `quantity` se calcula como la suma. Si se envían ambos, se usan `monthly_inputs`.

### GET /periods/{period}/emissions

Lista todas las emisiones del período con el factor asociado.

```json
// Response 200 — array de emisiones con relación factor
[
  { "id": 1, "calculated_co2e": 0.45, "factor": { "name": "Electricidad", ... }, ... }
]
```

### GET /companies/{company}/emissions/history

Historial paginado con filtros opcionales.

```
?page=1&per_page=20&scope_id=1&year=2024&factor_id=5
```

```json
// Response 200
{
  "data": [ ... ],
  "total": 48,
  "per_page": 20,
  "current_page": 1
}
```

### DELETE /emissions/{emission}

Soft delete de una emisión.

```
Response 204 — sin contenido
```

---

## Dashboard

Requieren `?company_id=&period_id=` como query params.

### GET /dashboard/summary

Resumen de huella total desglosada por alcance.

```json
// Response 200
{
  "huella_total": 12.345,
  "alcances": [
    { "scope": 1, "label": "Alcance 1", "total": 5.2, "color": "#1a237e" },
    { "scope": 2, "label": "Alcance 2", "total": 4.1, "color": "#00897b" },
    { "scope": 3, "label": "Alcance 3", "total": 3.0, "color": "#f59e0b" }
  ],
  "donut_data": [ ... ],
  "details": [
    { "scope": 1, "source": "Gasolina E10", "total": 2.1, "percentage": 17.0 }
  ]
}

// Error 400 — falta company_id o period_id
```

### GET /dashboard/trends

Tendencia de emisiones por año para la empresa.

```json
// Response 200
{
  "company_name": "ECONOVA",
  "trends": [
    { "year": 2023, "total_co2e": 10.2 },
    { "year": 2024, "total_co2e": 12.3 }
  ]
}
```

---

## Diccionarios (master data de uso)

### GET /companies

Lista las empresas a las que tiene acceso el usuario autenticado.

### GET /companies/{id}/periods

Lista todos los períodos de una empresa.

### GET /dictionaries/factors

Lista factores de emisión. Filtrables por `?scope_id=1`.

```json
[
  {
    "id": 5,
    "name": "Electricidad SIN Colombia",
    "factor_total_co2e": 0.000386,
    "unit": { "symbol": "kWh" },
    "category": { "name": "Electricidad", "scope_id": 2 }
  }
]
```

### GET /dictionaries/questionnaire

Cuestionario GHG para un sector. Requiere `?sector=servicios`.

```json
[
  {
    "emission_factor_id": 5,
    "questionnaire_label": "¿Cuántos kWh de electricidad consumió?",
    "is_required": true,
    "scope_id": 2,
    "scope_name": "Alcance 2"
  }
]
```

---

## Reportes

### GET /reports/periods/{period}/pdf

Descarga el reporte de emisiones del período en PDF.

```
Response 200 — application/pdf (file download)
Filename: zia_reporte_{empresa}_{año}_{fecha}.pdf
```

### GET /reports/periods/{period}/excel

Descarga los datos de emisiones en Excel.

```
Response 200 — application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
Filename: zia_datos_{empresa}_{año}_{fecha}.xlsx
```

---

## Agente ZIA

### POST /ai/chat

Inicia o continúa una conversación con el agente ZIA. La respuesta es **SSE (Server-Sent Events)** — la conexión permanece abierta mientras el agente procesa.

```json
// Request
{
  "message": "¿Cuánto CO₂ emití este año?",
  "company_id": 3,
  "period_id": 7,           // opcional
  "history": [ ... ],       // mensajes previos (formato {role, content})
  "auth_token": "eyJ..."    // token del usuario para las tool calls internas
}
```

**Eventos SSE:**

| Tipo | Datos | Descripción |
|---|---|---|
| `text` | `{ "type": "text", "content": "..." }` | Fragmento de texto del agente |
| `tool_start` | `{ "type": "tool_start", "tool": "calculate_ghg", "input": {...} }` | El agente llama una tool |
| `tool_end` | `{ "type": "tool_end", "tool": "calculate_ghg" }` | Tool ejecutada |
| `warning` | `{ "type": "warning", "message": "..." }` | Alerta (ej. Mistral caído, fallback a Anthropic) |
| `error` | `{ "type": "error", "message": "..." }` | Error irrecuperable |
| `done` | `{ "type": "done" }` | Conversación terminada |

```
// Errores HTTP (antes de abrir SSE)
// 422 — message o company_id faltantes / company_id inexistente
// 503 — ningún proveedor de IA configurado
```

### GET /ai/recommendations

Devuelve recomendaciones ecológicas contextualizadas y un resumen de las últimas 5 emisiones registradas.

Requiere header `X-Company-Context: {company_id}`.

```json
// Response 200
{
  "company_name": "ECONOVA",
  "recommendations": [],        // reservado para implementación futura
  "summary": [
    { "id": 42, "quantity": 1000, "calculated_co2e": 0.45, "year": 2024 }
  ],
  "timestamp": "2024-06-15T10:30:00Z"
}

// Error 400 — falta X-Company-Context
```

---

## Admin — Empresas y períodos

Requieren rol `admin` o `superadmin`.

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/admin/companies` | Lista empresas (superadmin: todas; admin: las suyas) |
| POST | `/admin/companies` | Crea empresa |
| PUT | `/admin/companies/{company}` | Actualiza empresa |
| DELETE | `/admin/companies/{company}` | Soft delete de empresa |
| POST | `/admin/companies/{company}/periods` | Crea período para empresa |
| PUT | `/admin/periods/{period}` | Actualiza período (ej. cambiar status) |
| DELETE | `/admin/periods/{period}` | Elimina período |
| GET | `/admin/companies/{company}/factors` | Lista factores con estado habilitado |
| PUT | `/admin/companies/{company}/factors` | Activa/desactiva factores para la empresa |

---

## Admin — Usuarios

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/admin/users` | admin | Superadmin: todos (incl. softDeleted); admin: de sus empresas |
| POST | `/admin/users` | admin | Crea usuario. Si el email existe y está softDeleted, lo restaura |
| PUT | `/admin/users/{user}` | admin | Actualiza nombre/rol. Admin no puede asignar `admin`/`superadmin` |
| DELETE | `/admin/users/{user}` | admin | Soft delete. No puede eliminarse a sí mismo (400) |
| POST | `/admin/users/{id}/restore` | superadmin | Restaura usuario soft-deleted |
| GET | `/admin/audit-logs` | admin | Logs de auditoría (superadmin: todos; admin: los de sus empresas) |

---

## Admin — Master data (superadmin)

### Categorías de emisión

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/admin/categories` | Lista todas (incluye soft-deleted con `withTrashed`) |
| POST | `/admin/categories` | Crea categoría. Body: `{name, scope_id, description?}` |
| DELETE | `/admin/categories/{category}` | Soft delete |

### Factores de emisión

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/admin/factors` | Crea factor. Body: `{emission_category_id, name, measurement_unit_id, factor_total_co2e, factor_co2?, ...}` |
| PUT | `/admin/factors/{factor}` | Actualiza factor (cualquier campo del `$fillable`) |
| DELETE | `/admin/factors/{factor}` | Soft delete |

### Recursos apiResource (CRUD completo)

| Recurso | Prefijo | Campos principales |
|---|---|---|
| Fórmulas | `/admin/formulas` | `name` (único), `expression`, `description` |
| Unidades | `/admin/units` | `name`, `symbol` (único) |
| Alcances | `/admin/scopes` | `name`, `description`, `documentation_text` |
| Sectores | `/admin/sectors` | `name`, `code`, `description` |

Todos usan **soft delete**. El delete devuelve 409 si el recurso tiene dependencias activas (ej. eliminar scope con categorías, o unit usada por factores).

---

## Admin — Grupos de empresas (superadmin)

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/admin/groups` | Lista grupos con sus empresas miembro |
| POST | `/admin/groups` | Crea grupo. Body: `{name, description?, company_ids?: [...]}` |
| GET | `/admin/groups/{group}/summary` | Huella total agregada del grupo. Params: `?year=2024` |
| POST | `/admin/groups/{group}/companies` | Añade empresa al grupo. Body: `{company_id}` |
| DELETE | `/admin/groups/{group}/companies` | Elimina empresa del grupo. Body: `{company_id}` |
| DELETE | `/admin/groups/{group}` | Soft delete del grupo |

**Summary response:**
```json
{
  "group": "Edificio Torre Norte",
  "year": 2024,
  "total_co2e": 45.7,
  "companies": [
    { "id": 1, "name": "ECONOVA", "co2e": 22.3 },
    { "id": 2, "name": "TechCorp", "co2e": 23.4 }
  ],
  "by_scope": { "1": 20.1, "2": 18.6, "3": 7.0 }
}
```

---

## Endpoint interno (solo red Docker)

### POST /internal/calculate

Usado exclusivamente por el agente ZIA para ejecutar el motor de cálculo GHG.

**Requiere:** Header `X-Internal-Secret: {INTERNAL_API_SECRET}`

```json
// Request
{
  "emission_factor_id": 5,
  "monthly_values": [100.0, 90.0, 85.0]
}

// Response 200
{
  "calculated_co2e": 0.1234,
  "activity_data_total": 275.0,
  "emissions_co2": 0.1200,
  "emissions_ch4": 0.0020,
  "emissions_n2o": 0.0014,
  "uncertainty_result": 3.5
}

// Error 403 — secret inválido o faltante
// Error 404 — factor no encontrado
```

---

## Formatos de error comunes

| Código | Cuándo ocurre |
|---|---|
| 400 | Parámetro faltante no validado vía `$request->validate()` |
| 401 | Token ausente, inválido o expirado |
| 403 | Rol insuficiente o acceso a empresa no autorizada |
| 404 | Recurso no encontrado (incluyendo soft-deleted) |
| 409 | Conflicto de dependencia (ej. delete de recurso en uso) |
| 422 | Error de validación — formato depende del controlador: `{"field": ["mensaje"]}` |
| 503 | Ningún proveedor IA configurado |

> **Nota sobre 422:** Los controladores que usan `$request->validate()` (estándar Laravel) devuelven `{"message": "...", "errors": {"field": [...]}}`. Los que usan `Validator::make()` manualmente devuelven `{"field": [...]}` sin wrapper `errors`.
