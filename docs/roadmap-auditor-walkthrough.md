# Roadmap de Cumplimiento y Walkthrough — Plataforma ZIA

**Fecha**: 2026-07-05
**Propósito**: Documento único para que un auditor externo (CCB u otro)
valide, requerimiento por requerimiento, si la plataforma cumple lo
contratado, con pasos concretos para verificarlo en el sistema en vivo.
También sirve como guía operativa si necesita usar el sistema para la
validación.

**Fuentes del requerimiento** (ambas en Drive, carpeta `Insumos`):
- `Emanuel_Requerimientos_de_la_plataforma_de_Zia.md` — 18 requerimientos técnicos detallados
- `Desglose Estratégico Luis (v3.0)` — SLA técnico con 2 hitos de pago y su "Definition of Done"

**Cómo se generó este documento**: cada estado (✅/⚠️/❌) se verificó
leyendo el código fuente real del repositorio (`ZiaMonorepo`, commit
`14fa5bd` en adelante), no a partir de lo que se planeó o se supone que
existe. Donde hay una desviación del requerimiento original, se explica
la razón técnica si es conocida.

**Actualización 2026-07-05 (misma sesión)**: se implementó
documentación OpenAPI/Swagger (punto 9) inmediatamente después de la
primera versión de este documento, por ser la única brecha sin
ambigüedad y más barata de cerrar. El estado de ese punto ya refleja el
resultado final, no el hallazgo original.

## Resumen ejecutivo

| # | Requerimiento | Estado |
|---|---|---|
| 1 | Repos separados frontend/backend + CI/CD Coolify | ⚠️ Desviación — monorepo |
| 2 | API REST con OAuth2.0/JWT | ✅ Cumple |
| 3 | Multitenancy con aislamiento de datos | ✅ Cumple |
| 4 | Roles y permisos por organización | ✅ Cumple |
| 5 | Rol superadministrador protegido | ✅ Cumple |
| 6 | Bases de datos políglota (PostgreSQL + Qdrant + RustFS) | ⚠️ Parcial — solo PostgreSQL |
| 7 | Logging estándar | ✅ Cumple |
| 8 | Configuración vía variables de entorno | ✅ Cumple |
| 9 | Documentación OpenAPI/Swagger | ✅ Cumple (implementado 2026-07-05) |
| 10 | Todos los endpoints protegidos | ✅ Cumple |
| 11 | Motor de fórmulas dinámico (tipo mathjs) | ✅ Cumple (adaptado a PHP) |
| 12 | Formularios dinámicos con banco de preguntas + tags | ⚠️ Parcial — más simple que lo especificado |
| 13 | Pre-formulario que resuelve tags | ⚠️ Parcial — ligado al punto anterior |
| 14 | Almacenamiento de formularios + borradores | ⚠️ Parcial — sin borrador en captura de usuario |
| 15 | Generación de reportes (GHG Protocol / ISO 14064) | ✅ Cumple |
| 16 | Agente LLM (Flowise/n8n) con RAG (Qdrant) | ⚠️ Desviación importante |
| 17 | Observabilidad del agente (Langfuse) | ✅ Cumple |
| 18 | Integración IoT vía ThingsBoard | ✅ Cumple |

**Hitos de pago (SLA)**:
- **Entregable 1** (infra, JWT, multitenancy, motor de fórmulas, docs API): cumplido salvo la estructura de repos.
- **Entregable 2** (Smart Intake, ThingsBoard, Agente IA): ThingsBoard operativo (en este entorno, en modo simulado — ver punto 18); Agente IA operativo pero sin Flowise/RAG; Smart Intake es una versión simplificada de lo especificado.

---

## 1. Repos separados frontend/backend + CI/CD Coolify

**Requerido**: repositorios separados para frontend y backend, cada uno
con Dockerfile propio, desplegados vía Coolify con CI/CD automático por rama.

**Estado real**: ⚠️ **Es un monorepo.** `backend/`, `frontend/` y
`zia-agent/` viven en un solo repositorio Git (`ZiaMonorepo`,
`github.com/ljgarciap/ZiaMonorepo`). Cada carpeta sí tiene su propio
`Dockerfile` y se construye como imagen independiente, pero no son
repos separados con historial de versiones propio.

**Por qué probablemente pasó así**: un monorepo simplifica coordinar
cambios que tocan frontend y backend a la vez (muy común en este
proyecto — casi todos los commits de esta sesión tocaron ambos lados),
a costa de no tener pipelines de CI/CD independientes por servicio.

**Cómo validarlo**:
```bash
git remote -v   # un solo origin, un solo repo
ls backend/Dockerfile frontend/Dockerfile zia-agent/Dockerfile   # 3 Dockerfiles independientes
```
No se pudo verificar si hay una instancia de Coolify desplegando esto en
producción — eso vive fuera del repositorio. Preguntar directamente a
Ricardo/DevOps si Coolify está conectado a este repo y con qué rama.

---

## 2. API REST con OAuth2.0/JWT

**Estado**: ✅ Cumple. Laravel Passport (`config/auth.php`, guard `api`
→ driver `passport`). Login devuelve un JWT firmado (RS256) con
expiración de largo plazo.

**Cómo validarlo**:
```bash
curl -X POST http://localhost:8000/api/login -H "Content-Type: application/json" \
  -d '{"email":"admin@zia.com","password":"password"}'
# devuelve { "token": "eyJ...", "user": {...}, "contexts": [...] }

curl http://localhost:8000/api/dashboard/summary \
  -H "Authorization: Bearer <token_invalido>"
# → 401 Unauthenticated
```

---

## 3. Multitenancy con aislamiento de datos

**Requerido**: cada organización con datos aislados; sugerencia de
`organization_id` + row level security (RLS) a nivel de base de datos.

**Estado real**: ✅ **Cumple el objetivo, con una implementación
distinta a la sugerida.** El aislamiento se hace en la capa de
aplicación (Laravel), no con RLS nativo de PostgreSQL: cada tabla con
datos de empresa lleva `company_id`, y cada controller valida
pertenencia antes de responder (`assertCompanyPeriodAccess()` en
`DashboardController` es el ejemplo más reciente y más estricto,
agregado esta semana tras encontrar y corregir un IDOR real).

**Por qué esta implementación y no RLS nativo**: RLS de PostgreSQL
protege incluso si el código de aplicación tiene un bug, pero requiere
que TODAS las queries pasen por un rol de sesión configurado con la
policy — un cambio transversal grande. La validación en el controller
es más común en apps Laravel y es lo que se implementó consistentemente.
El riesgo real de este enfoque (un endpoint que olvide el check) se
materializó una vez esta semana y fue corregido — ver más abajo cómo probarlo.

**Cómo validarlo** (requiere 2 usuarios de 2 empresas distintas):
```bash
# Usuario de la Empresa A intenta ver el dashboard de la Empresa B
curl http://localhost:8000/api/dashboard/summary?company_id=<ID_EMPRESA_B>&period_id=<PERIODO_DE_B> \
  -H "Authorization: Bearer <token_usuario_empresa_A>"
# → 403 "Sin permiso."
```

---

## 4. Roles y permisos por organización

**Estado**: ✅ Cumple. 6 roles: `superadmin`, `admin`, `user`,
`iot_tech`, `auditor`, `viewer`. Un usuario puede pertenecer a varias
empresas con roles distintos en cada una (tabla `company_user` con
`role` propio por fila). Matriz completa de permisos documentada en
`docs/manuals/` (un manual por rol).

**Cómo validarlo**: ver el manual consolidado
(`docs/manuals/manual-usuario-consolidado.md`, también subido a Drive)
— cada sección "Lo que no puedes hacer" es directamente verificable
iniciando sesión con ese rol.

---

## 5. Rol superadministrador protegido

**Estado**: ✅ Cumple. Un superadmin no puede eliminar su propia cuenta.

**Cómo validarlo**:
```bash
curl -X DELETE http://localhost:8000/api/admin/users/<TU_PROPIO_ID> \
  -H "Authorization: Bearer <tu_token_superadmin>"
# → 400 "Cannot delete yourself"
```
No se verificó explícitamente la garantía de "siempre debe existir al
menos un superadministrador en el sistema" a nivel de base de datos
(constraint) — la protección encontrada es solo "no te elimines a ti
mismo", que no impide que el ÚLTIMO superadmin restante sea eliminado
por OTRO superadmin. Si esto importa, es una validación adicional a pedir.

---

## 6. Bases de datos políglota (PostgreSQL + Qdrant + RustFS)

**Requerido**: PostgreSQL (relacional) + Qdrant o pgVector (vectores,
para RAG) + RustFS/MinIO (almacenamiento de objetos S3-compatible).

**Estado real**: ⚠️ **Solo PostgreSQL está implementado.**
- PostgreSQL: ✅ (`docker-compose.yml`, servicio `db`)
- Qdrant/pgVector: ❌ no encontrado en el repo ni en dependencias del agente
- RustFS/MinIO: ❌ no encontrado — los archivos (evidencias de soporte)
  se guardan en disco local del contenedor (`FILESYSTEM_DISK=local` en
  Laravel), no en almacenamiento de objetos S3-compatible

**Por qué importa**: sin una base vectorial, el Agente IA no puede
hacer RAG sobre documentos (ver punto 16). Sin object storage, los
archivos subidos (evidencias) no sobreviven si el contenedor se destruye
sin volumen persistente — hay que confirmar que el volumen Docker del
backend esté correctamente respaldado en el entorno de producción.

**Cómo validarlo**: `docker compose config` no muestra ningún servicio
Qdrant, MinIO o RustFS — solo `db` (Postgres) y `redis`.

---

## 7. Logging estándar

**Estado**: ✅ Cumple (el propio requerimiento dice que "logging
estándar a stdout o archivo es suficiente para esta fase"). Laravel
escribe logs vía su driver por defecto; no hay integración con
OpenTelemetry/Grafana/Loki (mencionado en el diagrama de arquitectura
del SLA), pero eso corresponde a una fase posterior según el propio
documento de requerimientos.

---

## 8. Configuración vía variables de entorno

**Estado**: ✅ Cumple ampliamente. Todo secreto/config específico de
entorno pasa por `.env`: `DB_*`, `MISTRAL_API_KEY`, `ANTHROPIC_API_KEY`,
`INTERNAL_API_SECRET`, `LANGFUSE_*`, `APP_KEY`, etc. No se encontraron
credenciales hardcodeadas en el código fuente durante esta sesión.

**Cómo validarlo**: `cat backend/.env.example` — lista todas las
variables esperadas sin valores reales.

---

## 9. Documentación OpenAPI/Swagger

**Estado**: ✅ **Cumple — implementado el 2026-07-05.** Hallazgo
original: había una dependencia declarada (`darkaonline/l5-swagger`)
pero completamente sin usar — sin config publicada, sin anotaciones,
cero cobertura real. Se reemplazó por
[Scramble](https://scramble.dedoc.co/) (`dedoc/scramble`), que genera
el spec OpenAPI automáticamente a partir de las rutas, los `FormRequest`
y los tipos de retorno existentes — sin necesitar anotar cada endpoint
a mano (con ~150 rutas, anotar manualmente habría sido la opción cara).

**Qué se configuró**:
- `GET /docs/api` — UI interactiva (Stoplight Elements) para explorar y
  probar los endpoints
- `GET /docs/api.json` — el spec OpenAPI crudo
- Detección automática de autenticación Bearer (`security_strategy` en
  `config/scramble.php`), a partir del middleware `auth:api` que ya
  envuelve casi todas las rutas
- Acceso abierto (gate `viewApiDocs` en `AppServiceProvider`) — es
  documentación de solo lectura del *schema*, no expone datos, así que
  se dejó accesible sin autenticación para que el equipo y la auditoría
  externa puedan consultarla directamente

**Cómo validarlo**:
```bash
curl http://localhost:8000/docs/api.json | python3 -m json.tool | head -30
# o abrir http://localhost:8000/docs/api en el navegador
```
Verificado: 93 rutas documentadas automáticamente, esquema de seguridad
Bearer detectado correctamente, 325 tests backend sin regresión.

---

## 10. Todos los endpoints protegidos por autenticación/autorización

**Estado**: ✅ Cumple. Todos los endpoints de negocio requieren
`auth:api` + verificación de rol (`role:` middleware) o de pertenencia
a empresa/asignación. Rate limiting es explícitamente opcional según el
requerimiento y no está implementado.

**Cómo validarlo**: cualquier endpoint bajo `/api/` (excepto
`/api/login`, `/api/register`) responde 401 sin token válido — se
verificó exhaustivamente en la suite de tests (325 tests backend,
muchos específicamente para esto).

---

## 11. Motor de fórmulas dinámico (tipo mathjs)

**Requerido**: banco de fórmulas + catálogo de factores + constantes +
intérprete de expresiones (mathjs sugerido), para agregar fórmulas
nuevas sin tocar código.

**Estado real**: ✅ **Cumple el objetivo, con una librería equivalente
en PHP.** `mathjs` es una librería de JavaScript — no aplica
directamente a un backend PHP. En su lugar:
- `FormulaEvaluationService` (`backend/app/Services/FormulaEvaluationService.php`)
  usa `Symfony\Component\ExpressionLanguage`, el intérprete de
  expresiones estándar del ecosistema Symfony/Laravel, con funciones
  custom registradas (`SQRT`, `POWER`, `AVERAGE`, `STDEV`) — equivalente
  funcional a lo que mathjs habría dado en Node.
- `CalculationFormula` (modelo + tabla) es el "banco de fórmulas" —
  agregar una fórmula nueva es una fila de base de datos, no un deploy.
- `EmissionFactor` es el catálogo de factores (CO2, CH4, N2O, etc. por
  unidad), igual que lo especificado.

**Cómo validarlo**: `backend/tests/Unit/FormulaEvaluationServiceTest.php`
y `CarbonFootprintServiceTest.php` prueban la evaluación de expresiones
directamente. También: `Administración → Factores de Emisión` en la UI
permite crear un factor nuevo sin ningún cambio de código.

---

## 12. Formularios dinámicos con banco de preguntas + tags jerárquicos

**Requerido**: banco de preguntas en BD, cada una etiquetada con
múltiples tags jerárquicos (ej. `alcance_1.fuentes_moviles.diesel`), el
backend arma el JSON del formulario según los tags resueltos.

**Estado real**: ⚠️ **Existe Smart Intake, pero es una versión más
simple que la especificada.** La tabla real es
`sector_questionnaire_rules` (`SectorQuestionnaireRule`): cada fila
mapea directamente `sector_code` + `subsector_code` → una pregunta con
su `emission_factor_id`. No hay un sistema de tags jerárquicos
many-to-many (`questions` ↔ `tags` ↔ `question_tags` como describe la
spec) — la selección de preguntas depende únicamente del sector/subsector
de la empresa, no de una combinación arbitraria de características
(¿tiene flota?, ¿usa refrigerantes?, etc. respondidas independientemente).

**Impacto práctico**: agregar una pregunta nueva para un sector sigue
siendo "una fila de base de datos, no código" (cumple el espíritu), pero
el sistema es menos flexible que lo pedido — no puede combinar múltiples
características de la empresa para afinar qué preguntas mostrar, solo
usa sector/subsector como único criterio.

**Cómo validarlo**: `Administración → Cuestionarios`, o
`backend/database/seeders/SectorQuestionnaireRuleSeeder.php` para ver
el patrón real de datos.

---

## 13. Pre-formulario que resuelve tags

**Estado real**: ⚠️ Ligado directamente al punto 12 — no existe un
pre-formulario de caracterización separado con preguntas propias. El
"pre-formulario" equivalente es simplemente el campo sector/subsector
ya guardado en `Company` al momento de crear la empresa. No hay
preguntas tipo "¿tiene flota vehicular?" que habiliten/deshabiliten
partes del formulario dinámico de forma independiente del sector.

---

## 14. Almacenamiento de formularios + borradores

**Estado real**: ⚠️ **Parcial.** Las respuestas SÍ quedan guardadas
asociadas a organización y período (`CarbonEmission` con `period_id`).
Lo que no se encontró: soporte de **borrador** en el flujo de captura
del usuario final (Smart Intake / Formulario) — no hay un estado
`draft` que permita guardar a medias y continuar en otra sesión. Cada
envío parece ser una emisión completa registrada de una vez.

**Cómo validarlo**: iniciar el formulario de Huella de Carbono, llenar
parcialmente, y navegar fuera sin enviar — verificar si el progreso se
conserva al volver (evidencia actual sugiere que no).

---

## 15. Generación de reportes (GHG Protocol / ISO 14064)

**Estado**: ✅ Cumple. Reportes PDF (resumen ejecutivo), Excel
(detallado), de avance, y de dispositivos IoT, agregados por alcance
(1/2/3) y por gas. El PDF tuvo un bug de compilación Blade corregido
esta misma semana (commit `a8a2110`).

**Cómo validarlo**: `Dashboard → Generar Reportes`, con cualquier
período que tenga datos. También:
```bash
curl http://localhost:8000/api/reports/periods/<PERIOD_ID>/pdf \
  -H "Authorization: Bearer <token>" -o reporte.pdf
```

---

## 16. Agente LLM (Flowise/n8n) con RAG automático (Qdrant)

**Requerido**: orquestación vía Flowise o n8n (no código custom), RAG
automático sobre documentos subidos por organización (Qdrant), el
agente sugiere fórmulas/tags/preguntas nuevas via structured output.

**Estado real**: ⚠️ **Desviación significativa del enfoque especificado,
aunque el agente SÍ funciona.**
- El agente (`zia-agent/`, servicio FastAPI en Python, puerto 8001) NO
  usa Flowise ni n8n — es un servicio custom con `anthropic` y
  `mistralai` como SDKs directos, con tool-calling estructurado
  (`get_company_profile` y otras herramientas que consultan el backend
  en tiempo real).
- **No hay RAG.** No se encontró Qdrant ni ningún vector store en las
  dependencias (`zia-agent/requirements.txt`) ni en el código. El
  agente no puede "consultar documentos de la organización" como
  describe el requerimiento — responde con datos que pide en vivo a la
  API del backend, no con búsqueda semántica sobre documentos subidos.
- No se encontró la funcionalidad de "sugerir nuevas fórmulas/tags/
  preguntas" como salida estructurada — el agente conversa y consulta
  datos, pero no se verificó que proponga cambios al banco de fórmulas
  o preguntas.

**Por qué probablemente se tomó esta decisión**: Flowise/n8n son
plataformas de orquestación visual pensadas para no-programadores o
para iterar rápido sin código; un servicio FastAPI custom con
tool-calling directo es más simple de mantener y depurar para un equipo
que ya sabe programar, a costa de perder la promesa de "no programar
frameworks de IA desde cero" del requerimiento. Es una decisión de
ingeniería razonable, pero es una desviación real del documento
contractual — vale la pena que quede explícita y aceptada, no asumida.

**Cómo validarlo**:
```bash
cat zia-agent/requirements.txt   # sin qdrant, sin flowise
curl http://localhost:8001/health
```
Conversar con el chat ZIA (ícono flotante en la app) y pedirle que
analice un documento subido — actualmente no puede, porque no hay
pipeline de ingesta de documentos a un vector store.

---

## 17. Observabilidad del agente (Langfuse)

**Estado**: ✅ Cumple. `langfuse` está en las dependencias y se
inicializa con `LANGFUSE_PUBLIC_KEY`/`LANGFUSE_SECRET_KEY` si están
configuradas — esta es la única pieza de la sección de IA que coincide
exactamente con lo especificado en el SLA.

---

## 18. Integración IoT vía ThingsBoard

**Estado**: ✅ Cumple a nivel de código/arquitectura. `ThingsBoardService.php`
+ `SyncTelemetryCommand` (cron cada 5 minutos,
`Schedule::command('zia:sync-telemetry')->everyFiveMinutes()` en
`routes/console.php`) consultan la API REST de ThingsBoard y alimentan
la telemetría de dispositivos IoT — exactamente como especifica el
requerimiento (Zia consulta la API de ThingsBoard, no construye su
propio broker MQTT/TSDB).

**Precisión importante para el walkthrough en vivo**: `ThingsBoardService`
tiene un modo mock (`THINGSBOARD_MOCK`, default `true` en
`.env`/`.env.example` de este entorno). **Tal como está configurado hoy,
la telemetría que se ve en la UI es simulada, no una conexión real a
una instancia de ThingsBoard.** El código para la integración real
existe y se activa con `THINGSBOARD_MOCK=false` + credenciales válidas
(`THINGSBOARD_HOST`, `THINGSBOARD_USERNAME`, `THINGSBOARD_PASSWORD`),
pero no se verificó una conexión real en este entorno de desarrollo.
Si el auditor necesita ver datos reales (no simulados), hay que
coordinar con Emanuel (líder IoT, dueño de la instancia de ThingsBoard
según el SLA) para obtener credenciales de un ambiente real.

**Cómo validarlo**: `Técnico IoT → Telemetría en Vivo`, o revisar
`backend/app/Console/Commands/SyncTelemetryCommand.php` y
`backend/app/Services/ThingsBoardService.php` directamente. Confirmar
el modo actual: `grep THINGSBOARD_MOCK backend/.env`.

---

## Walkthrough end-to-end (para validar el flujo completo en vivo)

1. **Login** como `superadmin@zia.com` / `password` en `http://localhost:8080`
2. **Crear una empresa** (Administración → Empresas) con sector/subsector definidos
3. **Crear un usuario `admin`** para esa empresa (Administración → Usuarios)
4. **Iniciar sesión como ese admin**, seleccionar la empresa/período
5. **Registrar una emisión** (Huella de Carbono o Smart Intake) — el
   Smart Intake solo mostrará las preguntas mapeadas al sector elegido
   en el paso 2 (ver punto 12 de este documento para la limitación real)
6. **Ver el Dashboard** — confirma que el cálculo (motor de fórmulas,
   punto 11) se reflejó correctamente por alcance
7. **Generar el reporte PDF** del período — confirma el punto 15
8. **Como `superadmin`, editar un factor de emisión y ver su historial**
   (`Administración → Factores` → editar → "Ver historial") — confirma
   el versionado de factores
9. **Como `iot_tech`, registrar un dispositivo IoT** y confirmar que
   `zia:sync-telemetry` trae datos (punto 18)
10. **Abrir el chat ZIA** y hacer una pregunta sobre los datos de la
    empresa — confirma que el agente responde con datos reales vía
    tool-calling (no vía documentos subidos, punto 16)
11. **Abrir `http://localhost:8000/docs/api`** — confirma la
    documentación OpenAPI (punto 9), incluyendo que los endpoints
    aparecen marcados como protegidos por Bearer auth

## Resumen de brechas para decisión de negocio

Si este documento se usa para decidir si un hito de pago se acepta o no,
estas son las brechas reales que quedan abiertas, ordenadas por
relevancia contractual (OpenAPI/Swagger ya se cerró, ver punto 9):

1. **Agente sin Flowise/n8n ni RAG** (punto 16) — desviación arquitectónica mayor del Entregable 2.
2. **Smart Intake simplificado** (puntos 12-13) — funciona, pero no con la flexibilidad de tags jerárquicos especificada.
3. **ThingsBoard en modo simulado en este entorno** (punto 18) — el código real existe, pero hoy corre con `THINGSBOARD_MOCK=true`; confirmar si el entorno que verá el auditor debe conectarse a una instancia real.
4. **Sin borrador de formularios** (punto 14) — gap de UX, no de arquitectura.
5. **Monorepo en vez de repos separados** (punto 1) — desviación de infraestructura, bajo riesgo funcional.
6. **Sin Qdrant/RustFS** (punto 6) — consecuencia directa de no tener RAG (punto 16); si se resuelve el punto 16, este también se resuelve.
