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

## Alternativas de uso

### Uso manual (sin sensores)

Una empresa **no necesita tener sensores conectados** para usar Zia. La
carga manual de consumo/emisiones ya existe de forma independiente (mismo
lugar donde termina el dato del sensor — la tabla de emisiones), así que
ambos caminos conviven: una empresa puede tener parte de sus emisiones
cargadas a mano y otra parte automatizada por IoT, sin conflicto (Zia
distingue el origen de cada dato y nunca deja que un sensor sobreescriba
algo que alguien cargó a mano).

### Uso externo (fuera de Zia)

**Actualización 2026-07-18: el tercer camino ya está implementado.**

Zia ahora expone una **API externa de solo lectura**
(`/api/external/v1/...`) para que un tercero consuma lecturas de
telemetría y emisiones de carbono de su empresa **sin necesitar una
cuenta de usuario en Zia** — solo una API key emitida por un Admin/
Superadmin desde la propia Zia. Aislamiento estricto por empresa: la key
determina qué empresa puede ver, nunca un parámetro del request.

Detalle completo del protocolo, la estructura JSON de cada endpoint, y
cómo generar/revocar una key, en
[`docs/api/external-api.md`](../api/external-api.md).

Las tres opciones para un tercero, entonces:

1. **API externa de Zia (nuevo)** — la opción recomendada si lo que se
   necesita es telemetría o huella de carbono ya procesada por Zia
   (ej. el delta de energía calculado, no el contador crudo del medidor).
2. **Consultar ThingsBoard directamente** — sigue siendo válido si lo que
   se necesita es el dato crudo del sensor tal como lo ve ThingsBoard,
   sin pasar por la interpretación de Zia (delta, agregación por empresa,
   etc.). Zia no es dueña de ese dato, solo lo replica.
3. **Pedir acceso a un usuario de Zia** con el rol correspondiente
   (Técnico IoT, Admin, Auditor) y usar los mismos endpoints autenticados
   que usa el frontend — sigue siendo la opción si se necesita algo que la
   API externa no expone (ej. gestión de dispositivos, no solo lectura).
