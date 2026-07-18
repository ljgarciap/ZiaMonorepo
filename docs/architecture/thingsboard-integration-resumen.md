# Integración IoT (ThingsBoard) — Resumen funcional

**Para quién es este documento:** una explicación no técnica de qué hace la
integración, cómo se conecta, qué datos trae y dónde se usan — sin el
detalle de implementación. El detalle técnico completo (endpoints, código,
semántica exacta por tipo de sensor) está en
[`thingsboard-integration.md`](thingsboard-integration.md).

---

## Qué hace

Zia no tiene sensores propios. Cada 5-15 minutos, un proceso automático
(`zia:sync-telemetry`) le pregunta a **ThingsBoard** — la plataforma que el
equipo de IoT ya opera — "¿cuánto consumió/pesó cada sensor desde la
última vez que pregunté?", y convierte esa respuesta en:

1. Un registro crudo de la lectura (para historial y gráficos).
2. Una alerta si el consumo es anómalo (fuera de horario, pico inusual).
3. Una emisión de carbono calculada automáticamente — sin que nadie tenga
   que cargar ese dato a mano.

## Cómo se conecta

- Zia le habla a la **API REST de ThingsBoard** (no hay conexión directa a
  los sensores — ThingsBoard es el intermediario).
- Autenticación por usuario/contraseña (JWT), configurada por empresa desde
  **Administración → Credenciales API** en Zia (no hay que tocar código ni
  redeploy para rotar credenciales o apuntar a otro tenant).
- Hay un **modo simulado** (activo hoy por defecto) que genera datos
  sintéticos realistas sin necesitar una instancia real de ThingsBoard —
  útil para demos y para que el resto del sistema (alertas, reportes) se
  pueda probar sin depender del equipo de IoT.
- Se validó en 2026-07 contra un tenant de prueba real
  (`thingsboard.meeldavlab.xyz`) con dispositivos reales publicando datos.

## Qué extrae

Cada sensor conectado a Zia (`IotDevice`) tiene un tipo, y cada tipo se lee
distinto porque el dato crudo que expone el sensor no siempre significa lo
mismo:

| Tipo de sensor | Qué mide | Cómo lo interpreta Zia |
|---|---|---|
| Energía | Medidor eléctrico — un contador que solo sube | Zia calcula cuánto subió desde la última lectura (el consumo real del período) |
| Agua | Consumo del período directamente | Se toma el valor tal cual |
| Residuos / papel | Báscula que reporta un peso cada vez que se pesa algo, y vuelve a cero | Zia junta todos los pesajes ocurridos desde la última vez que preguntó, no solo "el último" |

## Dónde se usa

Los datos extraídos alimentan, dentro de Zia:

- **Dashboard y reportes de la empresa** (`/telemetry/live`,
  `/telemetry/history`, reporte de avance IoT) — lo que ve un usuario o
  auditor cuando entra a revisar consumo o huella de carbono.
- **Dashboard global de superadmin** (todas las empresas con sensores).
- **La huella de carbono del período** (`CarbonEmission`) — el número que
  finalmente aparece en los reportes de emisiones de la empresa, calculado
  automáticamente a partir del consumo real.
- **Alertas operativas** cuando el consumo se sale de lo esperado.

Hoy esto **no** alimenta al asistente de IA de Zia (que responde sobre
documentos subidos por la empresa, no sobre telemetría) — sería una
extensión futura, no algo que exista ahora.

---

## Los tres caminos para obtener este dato — consolidado

Hay tres formas de que un consumo/emisión termine en Zia (o de que alguien
consuma ese dato), cada una con su propio protocolo. Esta sección junta las
tres con su forma de uso y un ejemplo real de payload/response — el detalle
exhaustivo de cada una sigue viviendo en su documento propio (linkeado en
cada sección).

### Camino 1 — Carga manual (dentro de Zia, sin sensores)

Una empresa **no necesita tener sensores conectados** para usar Zia. Un
usuario autenticado carga el consumo directamente, y ese dato termina en la
misma tabla que alimenta el IoT (`CarbonEmission`) — ambos caminos conviven:
una empresa puede tener parte de sus emisiones a mano y otra parte
automatizada, sin conflicto (Zia nunca deja que una lectura de sensor
sobreescriba algo cargado a mano — ver `source` en
[`thingsboard-integration.md`](thingsboard-integration.md)).

**Cómo se usa:**

- Requiere sesión de usuario Zia normal (login con Passport,
  `Authorization: Bearer {token}`) con un rol operativo asignado a la
  empresa (no Auditor, no Técnico IoT).
- Endpoint: `POST /api/periods/{period}/emissions`.

**Payload de ejemplo:**

```json
{
  "emission_factor_id": 14,
  "quantity": 500,
  "notes": "Consumo eléctrico de julio (factura del proveedor)"
}
```

**Response de ejemplo (201):**

```json
{
  "id": 301,
  "period_id": 7,
  "user_id": 22,
  "unit_id": 3,
  "emission_factor_id": 14,
  "source": "manual",
  "quantity": "500.0000",
  "emissions_co2": "0.0605",
  "emissions_ch4": "0.0000012",
  "emissions_n2o": "0.0000006",
  "calculated_co2e": "0.063000",
  "biogenic_co2e": "0.000000",
  "uncertainty_result": "0.0031",
  "activity_data_total": "500.0000",
  "notes": "Consumo eléctrico de julio (factura del proveedor)",
  "created_at": "2026-07-17T00:05:00.000000Z"
}
```

### Camino 2 — Consultar ThingsBoard directamente (sin pasar por Zia)

Válido cuando lo que se necesita es el dato **crudo** tal como lo ve
ThingsBoard (el contador del medidor sin el delta que calcula Zia, o
cualquier telemetry key que Zia no esté sincronizando). Zia no es dueña de
este dato, solo lo replica — un tercero puede pedirle acceso al equipo de
IoT y consultarlo sin que Zia esté en el medio.

**Cómo se usa** (protocolo de ThingsBoard, no de Zia — detalle completo y
semántica por tipo de sensor en
[`thingsboard-integration.md`](thingsboard-integration.md)):

1. Login para obtener un JWT:

```bash
curl -X POST "https://{tu-instancia}.thingsboard.cloud/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"usuario@empresa.com","password":"********"}'
```

```json
{ "token": "eyJhbGciOiJIUzI1NiJ9...", "refreshToken": "eyJhbGciOiJIUzI1NiJ9..." }
```

2. Consultar telemetría con ese token:

```bash
curl "https://{tu-instancia}.thingsboard.cloud/api/plugins/telemetry/DEVICE/{deviceId}/values/timeseries?keys=energy_active_import_wh" \
  -H "X-Authorization: Bearer eyJhbGciOiJIUzI1NiJ9..."
```

```json
{
  "energy_active_import_wh": [
    { "ts": 1784293321168, "value": "4125.6" }
  ]
}
```

- El token expira a las ~2.5 horas (estándar de ThingsBoard); hay que
  volver a loguearse o usar el `refreshToken`.
- No hay aislamiento por "empresa Zia" acá — el aislamiento lo maneja
  ThingsBoard con su propio modelo de tenant/customer, ajeno a Zia.

### Camino 3 — API externa de Zia (nuevo, 2026-07-18)

La opción recomendada cuando lo que se necesita es el dato **ya procesado**
por Zia (el delta de energía en kWh, la huella de carbono calculada) sin
pasar por ThingsBoard ni tener una cuenta de usuario en Zia — solo una API
key emitida por un Admin/Superadmin de esa empresa. Aislamiento estricto:
la key determina la empresa, nunca un parámetro del request. Protocolo
completo, todos los filtros y ambos endpoints en
[`docs/api/external-api.md`](../api/external-api.md).

**Cómo se usa:**

```bash
curl "https://zia.example.com/api/external/v1/emissions?year=2026" \
  -H "X-Api-Key: zia_live_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
```

**Response de ejemplo:**

```json
{
  "data": [
    {
      "id": 301,
      "period_id": 7,
      "emission_factor_id": 14,
      "source": "iot",
      "quantity": 1540.25,
      "calculated_co2e": 0.194,
      "notes": "Auto-ingested from IoT: Medidor Eléctrico General",
      "period": { "id": 7, "year": 2026, "status": "open" },
      "factor": { "id": 14, "name": "Energía Interconectada" }
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "per_page": 50, "total": 1 }
}
```

### Resumen comparativo

| | Camino 1 — Manual | Camino 2 — ThingsBoard directo | Camino 3 — API externa Zia |
|---|---|---|---|
| Quién lo usa | Usuario de la empresa, dentro de Zia | Equipo de IoT / terceros con acceso a ThingsBoard | Terceros sin cuenta Zia |
| Auth | Login Zia (Passport) | Login ThingsBoard (JWT, expira ~2.5h) | API key por empresa (no expira, revocable) |
| Qué dato da | El que el usuario cargue | Crudo, tal como lo ve el sensor | Procesado por Zia (delta, huella de carbono) |
| Escribe datos | Sí | N/A (fuera del alcance de Zia) | No — solo lectura |
| Aislamiento por empresa | Por rol/pertenencia a la empresa | Lo maneja ThingsBoard, no Zia | Por la API key misma |
