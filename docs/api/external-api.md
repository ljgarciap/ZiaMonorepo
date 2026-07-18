# API externa de Zia — para terceros sin cuenta de usuario

**Última actualización:** 2026-07-18 | **Responsable:** Backend Dev

Esta es la API que un tercero externo (ej. el equipo de IoT, u otra
herramienta de la empresa) puede consumir **sin tener una cuenta de usuario
en Zia** — a diferencia del resto de la API (`/api/...`), que requiere login
con Passport (`auth:api`) y un rol asignado a la empresa.

Es de **solo lectura**. No hay manera de escribir datos a través de esta API.

---

## Protocolo

### Base URL

```
https://<tu-dominio-de-zia>/api/external/v1
```

### Autenticación

Cada request debe incluir la API key en el header `X-Api-Key`:

```
GET /api/external/v1/telemetry-readings
X-Api-Key: zia_live_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
```

- No hay OAuth, no hay firma de request, no hay expiración automática — la
  key es válida hasta que un Admin/Superadmin la revoque.
- La key determina la empresa: **no existe ningún parámetro para pedir datos
  de otra empresa** — aunque se envíe un `device_id` o `emission_factor_id`
  que pertenezca a otra empresa, la respuesta simplemente no incluye nada
  (nunca hay un error que confirme o niegue que ese recurso existe en otra
  empresa).

### Errores

| Código | Cuándo | Body |
|---|---|---|
| `401` | Falta el header `X-Api-Key`, la key no existe, o fue revocada | `{"error": "Falta el header X-Api-Key."}` o `{"error": "API key inválida o revocada."}` |
| `422` | Un parámetro de filtro no pasa validación (ej. `year` fuera de rango) | `{"message": "...", "errors": {...}}` (formato estándar de validación de Laravel) |
| `429` | Se superó el límite de tasa | — |

### Límite de tasa

**60 requests por minuto, por API key** (no por IP — dos integraciones
distintas de la misma empresa, cada una con su propia key, tienen límites
independientes). Al superarlo, la respuesta es `429 Too Many Requests`.

### Paginación

Todas las respuestas de listado usan la paginación estándar de Laravel:
`per_page` (default 50, máximo 200) y el parámetro implícito `page`. El JSON
incluye `data`, `links` y `meta` (total de resultados, página actual, etc.)
— formato estándar, no hace falta documentarlo aparte.

---

## Endpoints

### `GET /telemetry-readings`

Lecturas de telemetría de los sensores IoT de tu empresa.

**Filtros (query params, todos opcionales):**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `device_id` | integer | Solo lecturas de ese dispositivo |
| `metric_name` | string | Ej. `energy_active_import_wh`, `water_m3`, `weight_kg` |
| `from` | date | Lecturas desde esta fecha (inclusive) |
| `to` | date | Lecturas hasta esta fecha (inclusive) |
| `per_page` | integer | 1–200, default 50 |

**Ejemplo:**

```bash
curl "https://zia.example.com/api/external/v1/telemetry-readings?metric_name=water_m3&from=2026-07-01" \
  -H "X-Api-Key: zia_live_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
```

**Estructura de cada elemento en `data`:**

```json
{
  "id": 4821,
  "device_id": 12,
  "metric_name": "water_m3",
  "value": 1.42,
  "timestamp": "2026-07-17T14:05:00.000000Z",
  "created_at": "2026-07-17T14:05:03.000000Z",
  "updated_at": "2026-07-17T14:05:03.000000Z",
  "device": {
    "id": 12,
    "name": "Medidor de Agua Principal",
    "type": "water",
    "unit": "m3"
  }
}
```

> **Nota sobre `value`**: para `type=energy` este valor ya es el delta del
> intervalo en kWh (no el contador crudo del medidor) — ver
> [`thingsboard-integration.md`](../architecture/thingsboard-integration.md)
> si necesitás el detalle de cómo se calcula por tipo de sensor.

---

### `GET /emissions`

Emisiones de carbono calculadas de tu empresa (`CarbonEmission`), incluye
tanto las cargadas manualmente como las generadas automáticamente por IoT.

**Filtros (query params, todos opcionales):**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `year` | integer | Año del período (2000–2100) |
| `emission_factor_id` | integer | Solo emisiones de ese factor |
| `per_page` | integer | 1–200, default 50 |

**Ejemplo:**

```bash
curl "https://zia.example.com/api/external/v1/emissions?year=2026" \
  -H "X-Api-Key: zia_live_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
```

**Estructura de cada elemento en `data`:**

```json
{
  "id": 301,
  "period_id": 7,
  "emission_factor_id": 14,
  "source": "iot",
  "quantity": 1540.25,
  "calculated_co2e": 0.194,
  "notes": "Auto-ingested from IoT: Medidor Eléctrico General",
  "created_at": "2026-07-17T00:05:00.000000Z",
  "updated_at": "2026-07-17T18:34:49.000000Z",
  "period": { "id": 7, "year": 2026, "status": "open" },
  "factor": { "id": 14, "name": "Energía Interconectada" }
}
```

- `source`: `"manual"` (cargado por un usuario) o `"iot"` (calculado
  automáticamente desde telemetría).
- `calculated_co2e` está en **toneladas de CO2e**.

---

## Cómo obtener una API key

Las keys se gestionan desde Zia mismo, no desde esta API — solo un
Admin o Superadmin de la empresa puede crear/revocar:

```
POST   /api/admin/companies/{company}/api-keys   { "name": "Integración X" }
GET    /api/admin/companies/{company}/api-keys
DELETE /api/admin/api-keys/{apiKey}
```

(Requieren sesión de usuario normal de Zia — `auth:api` + rol
`admin`/`superadmin` con acceso a esa empresa. No forman parte de la API
externa, son parte de la Zia normal.)

**La key en texto plano se muestra una única vez**, en la respuesta del
`POST` (`response.key`) — Zia no la guarda en texto plano, solo su hash, así
que si se pierde hay que crear una key nueva y revocar la anterior. No hay
forma de "recuperar" una key existente.

---

## Referencias

- `backend/app/Http/Middleware/ApiKeyAuth.php` — autenticación
- `backend/app/Http/Controllers/Api/External/` — controllers de los endpoints
- `backend/app/Http/Controllers/Api/Admin/ApiKeyController.php` — gestión de keys
- `backend/app/Models/ApiKey.php` — generación/hash de la key
- `backend/tests/Feature/ExternalApiTest.php` — cobertura de auth, aislamiento entre empresas, filtros y rate limit
- `backend/tests/Feature/Admin/ApiKeyControllerTest.php` — cobertura de gestión de keys
