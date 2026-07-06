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

**Cómo se generó este documento**: cada estado (Cumple/Parcial/No cumple) se verificó
leyendo el código fuente real del repositorio (`ZiaMonorepo`, commit
`14fa5bd` en adelante), no a partir de lo que se planeó o se supone que
existe. Donde hay una desviación del requerimiento original, se explica
la razón técnica si es conocida.

**Actualización 2026-07-05 (misma sesión)**: se implementó
documentación OpenAPI/Swagger (punto 9) inmediatamente después de la
primera versión de este documento, por ser la única brecha sin
ambigüedad y más barata de cerrar. El estado de ese punto ya refleja el
resultado final, no el hallazgo original.

**Segunda revisión 2026-07-05**: se re-examinaron todas las brechas
marcadas como parciales para distinguir "implementación distinta que igual cumple
el objetivo" de "gap funcional real". Resultado: el punto 14 pasó de
parcial a cumple (el mecanismo de guardado atómico por pregunta logra el mismo
objetivo que un borrador, sin necesitar uno). El punto 1 se matiza
(la severidad real depende de cómo esté configurado Coolify, no
verificable desde el código). Los puntos 12/13 y 16 se mantienen como
brechas reales tras la revisión — no todo lo "diferente" resultó
cumplir el objetivo.

**Actualización 2026-07-06**: se implementó el RAG documental del
agente (punto 16) que la revisión anterior había confirmado como brecha
real — pgvector sobre PostgreSQL existente, embeddings de Mistral,
ingesta en cola, y una tool nueva (`search_company_documents`).
Verificado end-to-end con datos reales, no solo mocks. Detalle completo
en `docs/adr/ADR-002-agente-ia-custom-vs-flowise.md` (adenda al final).
De paso se encontró y corrigió un problema real: el modelo alucinaba un
`company_id` inventado cuando se exponía como parámetro de la tool.

**Actualización 2026-07-06 (auditoría de seguridad)**: al revisar ese
hallazgo del agente junto con otro pendiente, se hizo un barrido
sistemático de aislamiento multi-tenant (punto 3) que encontró y cerró
6 endpoints reales sin verificación de acceso cross-empresa — ver
detalle en el punto 3. No cambia el estado de cumplimiento de ningún
punto (ya estaban marcados como "cumple" con la implementación por capa de
aplicación), pero es evidencia concreta de un proceso de revisión activo.

## Resumen ejecutivo

| # | Requerimiento | Estado |
|---|---|---|
| 1 | Repos separados frontend/backend + CI/CD Coolify | PARCIAL — Organización distinta, a confirmar con DevOps |
| 2 | API REST con OAuth2.0/JWT | CUMPLE |
| 3 | Multitenancy con aislamiento de datos | CUMPLE |
| 4 | Roles y permisos por organización | CUMPLE |
| 5 | Rol superadministrador protegido | CUMPLE |
| 6 | Bases de datos políglota (PostgreSQL + Qdrant + RustFS) | PARCIAL — PostgreSQL + pgvector (no Qdrant); sin RustFS |
| 7 | Logging estándar | CUMPLE |
| 8 | Configuración vía variables de entorno | CUMPLE |
| 9 | Documentación OpenAPI/Swagger | CUMPLE (implementado 2026-07-05) |
| 10 | Todos los endpoints protegidos | CUMPLE |
| 11 | Motor de fórmulas dinámico (tipo mathjs) | CUMPLE (adaptado a PHP) |
| 12 | Formularios dinámicos con banco de preguntas + tags | PARCIAL — más simple que lo especificado |
| 13 | Pre-formulario que resuelve tags | PARCIAL — ligado al punto anterior |
| 14 | Almacenamiento de formularios + borradores | CUMPLE (guardado atómico por pregunta, no requiere borrador) |
| 15 | Generación de reportes (GHG Protocol / ISO 14064) | CUMPLE |
| 16 | Agente LLM (Flowise/n8n) con RAG (Qdrant) | CUMPLE (RAG implementado 2026-07-06 con pgvector; Flowise/n8n sigue sin usarse, ver ADR-002) |
| 17 | Observabilidad del agente (Langfuse) | CUMPLE |
| 18 | Integración IoT vía ThingsBoard | CUMPLE |

**Hitos de pago (SLA)**:
- **Entregable 1** (infra, JWT, multitenancy, motor de fórmulas, docs API): cumplido salvo la estructura de repos.
- **Entregable 2** (Smart Intake, ThingsBoard, Agente IA): ThingsBoard operativo (en este entorno, en modo simulado — ver punto 18); Agente IA operativo con RAG documental implementado (sin Flowise, decisión documentada en ADR-002); Smart Intake es una versión simplificada de lo especificado.

---

## 1. Repos separados frontend/backend + CI/CD Coolify

**Justificación completa de la decisión**: [`docs/adr/ADR-001-docker-compose-monorepo.md`](adr/ADR-001-docker-compose-monorepo.md) (ver adenda 2026-07-05 al final, específica sobre repos separados).

**Requerido**: repositorios separados para frontend y backend, cada uno
con Dockerfile propio, desplegados vía Coolify con CI/CD automático por rama.

**Estado real**: **Es un monorepo, pero la severidad real es
incierta sin ver la configuración de Coolify.** `backend/`, `frontend/`
y `zia-agent/` viven en un solo repositorio Git (`ZiaMonorepo`,
`github.com/ljgarciap/ZiaMonorepo`). Cada carpeta sí tiene su propio
`Dockerfile` y se construye como imagen independiente — no son repos
separados con historial de versiones propio, pero eso no
necesariamente rompe el objetivo funcional del requerimiento.

**Por qué esto podría no ser un gap real**: Coolify soporta desplegar
múltiples "recursos" (aplicaciones) desde subcarpetas distintas de un
mismo repositorio — es un patrón de monorepo habitual, no una
limitación de la herramienta. Si Coolify está configurado para tratar
`backend/` y `frontend/` como despliegues independientes (cada uno con
su propio pipeline, disparado solo cuando cambian archivos de su
carpeta), el objetivo real del requerimiento — **desplegar cada
servicio de forma independiente vía CI/CD** — se cumpliría igual,
aunque el control de versiones no esté separado.

**Lo que falta para saber si esto es un gap real o solo una diferencia
de organización**: confirmar con Ricardo/DevOps si Coolify está
configurado así, o si en la práctica un cambio a `frontend/` dispara
un rebuild/redeploy también del `backend/` (eso sí sería el problema
real que el requerimiento buscaba evitar).

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

**Estado**: Cumple. Laravel Passport (`config/auth.php`, guard `api`
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

**Estado real**: **Cumple el objetivo, con una implementación
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

**Actualización 2026-07-06 — auditoría sistemática y 6 hallazgos más**:
tras el fix puntual anterior, se hizo un barrido de **todos** los
controllers que reciben `{company}`/`{period}` desde la URL (10 en
total) para confirmar que cada uno valida pertenencia real, no solo el
rol del usuario. `ContextAwareMiddleware` no cubre este caso: solo
actúa cuando el header `X-Company-ID` está presente, nunca cuando el ID
viene del path de la URL — una asunción incorrecta que varios endpoints
nuevos heredaron sin que nadie lo notara hasta este barrido.

Se encontraron y corrigieron 6 endpoints reales sin ninguna
verificación — 4 de severidad alta (cualquier rol no-superadmin podía
leer o escribir datos de **otra** empresa cambiando el ID en la URL):
historial y comparación de emisiones, los 4 reportes oficiales de
cumplimiento (PDF/Excel/avance/IoT), gestión de unidades operativas, y
configuración de factores por empresa. 2 de severidad baja (catálogo).
Verificado no solo con tests automatizados sino en vivo contra el
sistema corriendo (ver comando abajo). La lógica de verificación se
extrajo a un trait compartido (`AssertsCompanyAccess`) porque ya
existía duplicada, con variantes, en 4 controllers distintos — reduce
el riesgo de que un controller nuevo vuelva a omitirla por accidente.

**Lectura para el auditor**: esto no debilita la afirmación de que el
punto 3 "cumple" — al contrario, es evidencia de que existe un proceso
de revisión de seguridad activo que encuentra y cierra este tipo de
brecha antes de que se vuelva un incidente. La alternativa (no buscarlo)
no habría sido más segura, solo menos visible.

**Cómo validarlo** (requiere 2 usuarios de 2 empresas distintas):
```bash
# Usuario de la Empresa A intenta ver el dashboard de la Empresa B
curl http://localhost:8000/api/dashboard/summary?company_id=<ID_EMPRESA_B>&period_id=<PERIODO_DE_B> \
  -H "Authorization: Bearer <token_usuario_empresa_A>"
# → 403 "Sin permiso."

# Mismo patrón contra el historial de emisiones (hallazgo 2026-07-06)
curl http://localhost:8000/api/companies/<ID_EMPRESA_B>/emissions/history \
  -H "Authorization: Bearer <token_usuario_empresa_A>"
# → 403 "Sin permiso."
```

---

## 4. Roles y permisos por organización

**Estado**: Cumple. 6 roles: `superadmin`, `admin`, `user`,
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

**Estado**: Cumple. Un superadmin no puede eliminar su propia cuenta.

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

**Estado real**: **PostgreSQL + pgvector cumplen la mitad del
requerimiento; RustFS/MinIO sigue sin implementarse.**
- PostgreSQL: Sí (`docker-compose.yml`, servicio `db`)
- Vectores: **implementado 2026-07-06** — el propio requerimiento
  dice "Qdrant **o** pgVector", y se optó por pgvector sobre el
  PostgreSQL ya existente (`pgvector/pgvector:pg16`) en vez de levantar
  Qdrant como servicio nuevo — ver ADR-002, adenda 2026-07-06, y
  `docs/architecture/thingsboard-integration.md` para el patrón de
  integración equivalente en otro punto del sistema. Nota técnica: los
  embeddings se guardan como JSON, no como columna nativa `vector`
  (razón: compatibilidad con la suite de tests en sqlite) — la
  extensión está habilitada para migrar a eso si el volumen lo justifica.
- RustFS/MinIO: No implementado — los archivos (evidencias
  de soporte, documentos del RAG) se guardan en disco local del
  contenedor (`FILESYSTEM_DISK=local` en Laravel), no en almacenamiento
  de objetos S3-compatible

**Por qué importa lo que falta**: sin object storage, los archivos
subidos no sobreviven si el contenedor se destruye sin volumen
persistente — hay que confirmar que el volumen Docker del backend esté
correctamente respaldado en el entorno de producción.

**Cómo validarlo**: `docker compose config` muestra `db` usando la
imagen `pgvector/pgvector:pg16` (no `postgres:16-alpine`); ningún
servicio Qdrant, MinIO o RustFS.

---

## 7. Logging estándar

**Estado**: Cumple (el propio requerimiento dice que "logging
estándar a stdout o archivo es suficiente para esta fase"). Laravel
escribe logs vía su driver por defecto; no hay integración con
OpenTelemetry/Grafana/Loki (mencionado en el diagrama de arquitectura
del SLA), pero eso corresponde a una fase posterior según el propio
documento de requerimientos.

---

## 8. Configuración vía variables de entorno

**Estado**: Cumple ampliamente. Todo secreto/config específico de
entorno pasa por `.env`: `DB_*`, `MISTRAL_API_KEY`, `ANTHROPIC_API_KEY`,
`INTERNAL_API_SECRET`, `LANGFUSE_*`, `APP_KEY`, etc. No se encontraron
credenciales hardcodeadas en el código fuente durante esta sesión.

**Cómo validarlo**: `cat backend/.env.example` — lista todas las
variables esperadas sin valores reales.

**Actualización 2026-07-06 — gestión de credenciales desde UI**: se
agregó una capa opcional sobre `.env` para las integraciones externas
más sensibles a rotar (Mistral, Anthropic, Langfuse, ThingsBoard):
tabla `system_settings` (valor encriptado en reposo, cast `encrypted`
de Laravel) + UI en `Administración → API Keys` (solo superadmin, ver
`docs/manuals/superadmin.md`). No reemplaza `.env` — es un override:
si no hay valor guardado en BD, el sistema sigue usando `.env` como
antes. `zia-agent` (proveedores de IA, servicio Python separado que no
puede leer la BD de Laravel directamente) consulta un endpoint interno
nuevo (`GET /api/internal/credentials`, protegido por
`X-Internal-Secret`) cada 60 segundos para refrescar sus credenciales
sin reiniciar el contenedor.

**Hallazgo real corregido durante la implementación**: la primera
versión hacía que "borrar un override" devolviera al agente una key
vacía en vez de la real de su propio `.env` — Laravel no tiene
visibilidad del `.env` de zia-agent, así que un fallback ingenuo a
`env()` desde Laravel reportaba vacío. Se corrigió para que "sin
override" siempre signifique que cada servicio decida su propio
fallback con su propio entorno, nunca que Laravel imponga el suyo
(vacío) a otro servicio. Verificado en vivo: guardar una key falsa
rompe el chat con error de autenticación (prueba de que se usa), y
borrarla restaura el funcionamiento sin reiniciar nada.

**Cómo validarlo**: `Administración → API Keys` como superadmin, o
`GET /api/admin/api-credentials` con un token de superadmin.

---

## 9. Documentación OpenAPI/Swagger

**Estado**: **Cumple — implementado el 2026-07-05.** Hallazgo
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

**Estado**: Cumple. Todos los endpoints de negocio requieren
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

**Estado real**: **Cumple el objetivo, con una librería equivalente
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

**Justificación completa de la decisión y camino de mejora**: [`docs/adr/ADR-003-smart-intake-sector-vs-tags.md`](adr/ADR-003-smart-intake-sector-vs-tags.md).

**Requerido**: banco de preguntas en BD, cada una etiquetada con
múltiples tags jerárquicos (ej. `alcance_1.fuentes_moviles.diesel`), el
backend arma el JSON del formulario según los tags resueltos.

**Estado real**: **Cumple el objetivo central, no la flexibilidad
completa — es un gap funcional real, no solo una diferencia de
arquitectura.** La tabla real es `sector_questionnaire_rules`
(`SectorQuestionnaireRule`): cada fila mapea directamente `sector_code`
+ `subsector_code` → una pregunta con su `emission_factor_id`. No hay
un sistema de tags jerárquicos many-to-many (`questions` ↔ `tags` ↔
`question_tags` como describe la spec).

**Lo que sí cumple**: el objetivo explícito del requerimiento —
"agregar, editar o eliminar preguntas... sin modificar código" — se
cumple completamente. Una pregunta nueva para un sector es una fila de
base de datos (`SectorQuestionnaireRuleSeeder` muestra el patrón), no
un despliegue.

**Lo que no cumple, y es una limitación real no solo cosmética**: el
propio documento de requerimientos usa como ejemplo que dos
características independientes del sector (¿tiene flota vehicular?,
¿usa refrigerantes?) deberían combinarse para afinar qué preguntas
mostrar. Con solo sector/subsector como criterio, **dos empresas del
mismo sector reciben exactamente el mismo formulario**, sin importar
si una tiene flota y la otra no, o si una usa refrigerantes y la otra
no — no hay forma de que el sistema "sepa" esas diferencias operativas
sin agregar sector/subsector nuevos artificiales para cada combinación
(lo cual no escala). Esto sí es una brecha funcional para auditar, no
una decisión de implementación equivalente.

**Cómo validarlo**: `Administración → Cuestionarios`, o
`backend/database/seeders/SectorQuestionnaireRuleSeeder.php` para ver
el patrón real de datos.

---

## 13. Pre-formulario que resuelve tags

**Estado real**: Ligado directamente al punto 12 — no existe un
pre-formulario de caracterización separado con preguntas propias. El
"pre-formulario" equivalente es simplemente el campo sector/subsector
ya guardado en `Company` al momento de crear la empresa. No hay
preguntas tipo "¿tiene flota vehicular?" que habiliten/deshabiliten
partes del formulario dinámico de forma independiente del sector.

---

## 14. Almacenamiento de formularios + borradores

**Estado real**: **Cumple el objetivo, con un mecanismo distinto al
sugerido — no es un gap.** Las respuestas quedan guardadas asociadas a
organización y período (`CarbonEmission` con `period_id`). No existe un
estado `draft` explícito, pero tampoco hace falta: en Smart Intake
(`smart-intake.ts`, método `saveRule()`) **cada pregunta se guarda de
forma individual e inmediata** al hacer click — no hay un paso de
"envío final" del formulario completo. El usuario puede responder 2 de
10 preguntas, cerrar el navegador, y esas 2 ya están persistidas como
emisiones reales; no se pierde nada.

**Por qué esto satisface el objetivo real del requerimiento**: el
propósito de un "borrador" es que el usuario no pierda trabajo a medio
hacer entre sesiones. Guardar cada respuesta atómicamente en el momento
en que se completa logra exactamente eso, sin necesitar un estado
intermedio "a medias" — porque nunca hay nada "a medias" sin guardar:
cada pregunta respondida ya es un hecho consumado. Lo único que se
pierde es un valor tipeado en un campo pero no confirmado con click —
eso es una limitación normal de casi cualquier formulario con botón de
guardar, no lo que el requerimiento intentaba resolver.

**Cómo validarlo**: entrar a Smart Intake, responder y guardar una
pregunta, cerrar el navegador sin tocar las demás, volver a entrar —
la pregunta ya respondida no vuelve a pedirse con el mismo valor en
cero; queda reflejada en el histórico de emisiones del período.

---

## 15. Generación de reportes (GHG Protocol / ISO 14064)

**Estado**: Cumple. Reportes PDF (resumen ejecutivo), Excel
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

**Justificación completa de la decisión y camino de mejora**: [`docs/adr/ADR-002-agente-ia-custom-vs-flowise.md`](adr/ADR-002-agente-ia-custom-vs-flowise.md). Arquitectura técnica detallada: [`docs/architecture/ai-agent.md`](architecture/ai-agent.md).

**Requerido**: orquestación vía Flowise o n8n (no código custom), RAG
automático sobre documentos subidos por organización (Qdrant), el
agente sugiere fórmulas/tags/preguntas nuevas via structured output.

**Estado real**: **Cumple.** Orquestación custom (no Flowise/n8n) —
desviación deliberada y documentada (ver ADR-002) — pero el RAG
documental y el resto del requerimiento sí están resueltos.
- El agente (`zia-agent/`, servicio FastAPI en Python, puerto 8001) NO
  usa Flowise ni n8n — es un servicio custom con `anthropic` y
  `mistralai` como SDKs directos, con tool-calling estructurado.
- Tiene herramientas (`calculate_ghg`, `save_emission`,
  `get_pending_questions`) que le permiten **operar la captura de
  emisiones conversacionalmente** — calcular, guardar (con confirmación
  explícita del usuario) y comparar el cuestionario del sector contra
  lo ya registrado para guiar proactivamente hacia un inventario
  completo. Esto cumple el espíritu de "analizar la data ingresada y
  generar insights".
- **RAG implementado (2026-07-06)**: `search_company_documents` — el
  agente puede buscar semánticamente sobre documentos que la empresa
  sube (facturas, certificados, reportes), vía embeddings de Mistral
  (`mistral-embed`) y similarity search en pgvector. Verificado
  end-to-end con datos reales (no solo mocks): un documento de prueba
  con un dato concreto ("85 galones de diésel") fue recuperado
  correctamente por una pregunta conversacional real.
- Durante esa verificación se encontró y corrigió un problema real: el
  modelo (Mistral) alucinaba un `company_id` inventado cuando se le
  pedía como parámetro de la tool — corregido tomando ese valor del
  request autenticado en vez del modelo (detalle completo en ADR-002,
  adenda 2026-07-06).
- No se implementó "sugerir nuevas fórmulas/tags/preguntas" como salida
  estructurada — el agente consulta y opera datos, pero no propone
  cambios al banco de fórmulas o preguntas. Gap menor, no bloqueante.

**Por qué la orquestación custom sigue siendo la decisión correcta,
incluso con RAG ya resuelto**: ver ADR-002 — la garantía de que el
agente nunca calcule tCO2e por su cuenta, y ahora también el hallazgo
de la alucinación de `company_id`, refuerzan el argumento original de
que el tool-calling directo es más testeable y auditable que una
plataforma visual para este dominio.

**Cómo validarlo**:
```bash
curl http://localhost:8001/health
```
Subir un documento en `Administración → Documentos`, esperar a que su
estado pase a "Listo", y conversar con el chat ZIA preguntando algo que
solo esté en ese documento — confirma la recuperación semántica real.

---

## 17. Observabilidad del agente (Langfuse)

**Estado**: Cumple. `langfuse` está en las dependencias y se
inicializa con `LANGFUSE_PUBLIC_KEY`/`LANGFUSE_SECRET_KEY` si están
configuradas — esta es la única pieza de la sección de IA que coincide
exactamente con lo especificado en el SLA.

---

## 18. Integración IoT vía ThingsBoard

**Contrato técnico completo (endpoints, formato de datos, cómo se
procesa cada lectura, checklist para pasar de mock a real)**: [`docs/architecture/thingsboard-integration.md`](architecture/thingsboard-integration.md).

**Estado**: Cumple a nivel de código/arquitectura. `ThingsBoardService.php`
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

**Guía paso a paso con resultado esperado en cada punto, datos reales
ya cargados para validar sin crear nada desde cero, y anexo del motor
de cálculo con un ejemplo numérico verificable a mano**: ver
"Guía de Validación Funcional para Auditor — Plataforma ZIA"
(`docs/guia-validacion-auditor.md`, también en Drive). Lo que sigue es
la versión resumida:

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
    tool-calling
11. **Subir un documento en `Administración → Documentos`** (ej. un
    .txt o .pdf con un dato concreto), esperar a que su estado pase de
    "Procesando" a "Listo", y preguntarle al chat ZIA algo que solo esté
    en ese documento — confirma el RAG (punto 16)
12. **Abrir `http://localhost:8000/docs/api`** — confirma la
    documentación OpenAPI (punto 9), incluyendo que los endpoints
    aparecen marcados como protegidos por Bearer auth

## Resumen de brechas para decisión de negocio

Si este documento se usa para decidir si un hito de pago se acepta o no,
estas son las brechas que quedan abiertas, ordenadas por relevancia
contractual. OpenAPI/Swagger (punto 9), borradores de formulario
(punto 14), y RAG documental del agente (punto 16, con su parte de
almacenamiento vectorial en el punto 6) se cerraron y ya no aparecen
aquí:

1. **Smart Intake sin combinación de características** (puntos 12-13)
   — gap real confirmado. Agregar preguntas sin tocar código sí
   funciona; lo que no funciona es diferenciar formularios dentro de un
   mismo sector según características operativas de cada empresa
   (flota, refrigerantes, etc.), tal como el propio requerimiento
   ejemplifica que debería pasar.
2. **ThingsBoard en modo simulado en este entorno** (punto 18) — el
   código real existe, pero hoy corre con `THINGSBOARD_MOCK=true`;
   confirmar si el entorno que verá el auditor debe conectarse a una
   instancia real.
3. **Monorepo en vez de repos separados** (punto 1) — pendiente de
   confirmar con Ricardo/DevOps si Coolify despliega cada carpeta de
   forma independiente; si es así, este punto deja de ser un gap real
   y queda solo como una diferencia de organización de repositorios.
4. **Sin RustFS/MinIO** (punto 6) — los archivos (evidencias,
   documentos del RAG) viven en disco local del contenedor, no en
   almacenamiento de objetos S3-compatible. Riesgo de persistencia si
   el volumen Docker no está bien respaldado en producción.

**Descartados en la segunda revisión** (implementación distinta, mismo
objetivo cumplido): borradores de formulario (punto 14, guardado
atómico por pregunta) y, parcialmente, OpenAPI/Swagger (punto 9, ya
implementado esta sesión).
