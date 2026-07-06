# ADR-002 — Agente de IA custom (FastAPI + tool-calling directo) en vez de Flowise/n8n

**Estado:** Aceptado
**Fecha:** 2026-07-05 (documentado retroactivamente — la decisión se tomó durante el desarrollo del Entregable 2)
**Autores:** AI Architect, Backend Dev
**Revisado por:** Luis García

---

## Contexto

El documento de requerimientos técnicos (`Emanuel_Requerimientos_de_la_plataforma_de_Zia.md`,
sección 17) y el SLA técnico (`Desglose Estratégico Luis v3.0`, sección
3) piden explícitamente:

> "Orquestación: No programar frameworks de IA desde cero. Utilizar
> Flowise / n8n integrado mediante API REST al backend de Zia, con
> observabilidad en Langfuse."

Y además: RAG automático sobre documentos subidos por organización
(Qdrant), con el agente sugiriendo fórmulas/tags/preguntas nuevas via
salida estructurada.

Lo que se construyó (`zia-agent/`, ver `docs/architecture/ai-agent.md`
para el detalle técnico completo) es un servicio FastAPI propio en
Python, con los SDKs oficiales de Anthropic y Mistral usados
directamente, tool-calling estructurado contra 6 herramientas que
llaman al backend de Laravel, y streaming SSE — sin Flowise ni n8n.

## Decisión

Se construyó el agente como servicio FastAPI custom con tool-calling
directo, en vez de usar Flowise o n8n como capa de orquestación visual.

**Langfuse sí se mantuvo** tal como pedía el requerimiento — es la
única pieza de la sección de IA que coincide exactamente con lo
especificado.

**RAG sobre documentos (Qdrant) no se implementó** — ver sección
"Lo que esto no resuelve" más abajo. Esto es una brecha real, no una
decisión deliberada de mejora.

## Por qué se decidió así

**1. Garantía de exactitud del cálculo, no negociable para este dominio.**
El requerimiento de negocio más estricto de todo el proyecto es que el
agente **nunca calcule tCO2e por su cuenta** — todo cálculo debe pasar
por `calculate_ghg`, que llama al motor de fórmulas real del backend
(`FormulaEvaluationService`). Un LLM alucinando un número de emisiones
en un contexto de cumplimiento normativo (ISO 14064, auditorías
externas) es un riesgo real, no teórico. Con tool-calling directo vía
SDK, esta garantía se codifica explícitamente en el prompt del sistema
y se puede testear (`zia-agent/tests/`, 51 tests incluyen verificación
de que el agente siempre invoca la tool antes de reportar un número).
En una plataforma visual como Flowise, esa misma garantía existe, pero
es más difícil de testear con la misma rigurosidad de una suite pytest
versionada junto al código.

**2. Fallback multi-proveedor con lógica de negocio específica.**
El agente implementa una política de reintentos concreta: 3 intentos
con backoff exponencial sobre Mistral (proveedor primario, más barato)
antes de caer a Anthropic (fallback). Esta lógica de negocio —
reintentar, medir fallos, decidir cuándo cambiar de proveedor — es
código imperativo natural en Python; modelarla dentro de los nodos
visuales de Flowise/n8n habría sido más frágil y menos debuggeable que
una función Python con sus propios tests unitarios.

**3. Normalización de historial entre proveedores.**
Mistral y Anthropic usan formatos de tool-calling incompatibles en el
historial de conversación. El agente normaliza automáticamente entre
ambos (`normalize_history_for_anthropic`/`normalize_history_for_mistral`,
16 tests dedicados) para poder cambiar de proveedor **a mitad de una
conversación** sin corromper el contexto. Esto es un problema de
ingeniería de bajo nivel que un framework no-code no resuelve mejor que
código explícito.

**4. Equipo ya competente en Python/FastAPI, sin curva de aprendizaje de Flowise.**
El SLA técnico da 7 semanas para el TRL 6 completo. Flowise/n8n tienen
su propia curva de aprendizaje (conceptos de nodos, credenciales,
versionado de flows fuera de Git). Un servicio FastAPI se integra en el
mismo flujo de CI/CD, control de versiones y testing que el resto del
proyecto, sin herramientas ni procesos adicionales.

## Cómo esto va más allá del requerimiento mínimo

- **`get_pending_questions`** compara el cuestionario del sector contra
  lo ya registrado y guía proactivamente al usuario hacia un inventario
  completo — cumple directamente "analizar la data ingresada y generar
  insights" del requerimiento, con una implementación concreta y
  testeada, no solo una promesa de que un LLM "podría" hacerlo.
- El agente puede **operar el flujo de captura completo
  conversacionalmente** (`calculate_ghg` → mostrar resultado → esperar
  confirmación explícita → `save_emission`), no solo responder
  preguntas — una capacidad operativa que el requerimiento no pedía
  explícitamente pero que agrega valor real al usuario final.
- Observabilidad end-to-end vía Langfuse en cada tool call, igual que
  el requerimiento pedía.

## Lo que esto no resuelve (brecha real, no cubierta por lo anterior)

El **RAG automático sobre documentos de la organización** (subida de
facturas/certificados → embeddings en Qdrant → contexto para el
agente) **no existe**. El agente de hoy solo puede razonar sobre datos
estructurados que están en la base de datos de Zia via sus tools — no
puede leer el contenido de un PDF subido por el usuario. Esta es la
única parte del requerimiento 17 que sigue sin resolverse, y no hay
forma de reencuadrarla como "cumplida de otra forma": es una capacidad
que simplemente no existe hoy.

**Camino de mejora si se decide cerrar esto**: no hace falta adoptar
Flowise para tener RAG — se puede agregar un vector store (Qdrant o
`pgvector` sobre el PostgreSQL ya existente, más simple
operacionalmente) y una tool nueva (`search_company_documents`) que el
agente invoque igual que las 6 actuales, sin cambiar la arquitectura
base. `pgvector` en particular evita levantar un servicio nuevo en el
`docker-compose.yml`.

## Consecuencias

**Positivas:**
- Cobertura de tests real (51 tests Python) sobre la lógica crítica del agente
- Sin dependencia de una plataforma externa (Flowise/n8n) para el flujo core del producto
- Fallback multi-proveedor y normalización de historial resueltos con código propio, versionado junto al resto del sistema

**Negativas / limitaciones aceptadas:**
- Sin RAG documental — el agente no puede usar contexto de documentos subidos
- Cualquier cambio al comportamiento del agente requiere un deploy de código (vs. editar un flow visual sin deploy) — tradeoff aceptado a cambio de testabilidad

## Referencias

- `docs/architecture/ai-agent.md` — arquitectura técnica completa, tools, SSE, normalización de historial
- `zia-agent/main.py` — implementación
- `zia-agent/tests/` — 51 tests
- `docs/roadmap-auditor-walkthrough.md`, punto 16 — estado de cumplimiento contra el requerimiento original
