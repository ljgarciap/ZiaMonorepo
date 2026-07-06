# ADR-003 — Smart Intake basado en sector/subsector en vez de tags jerárquicos many-to-many

**Estado:** Aceptado, con limitación conocida sin resolver
**Fecha:** 2026-07-05 (documentado retroactivamente)
**Autores:** Backend Dev, Arquitecto
**Revisado por:** Luis García

---

## Contexto

El documento de requerimientos técnicos (`Emanuel_Requerimientos_de_la_plataforma_de_Zia.md`,
secciones 13-14) especifica un sistema de formularios dinámicos donde:

1. Un banco de preguntas (`questions`) se etiqueta con **tags
   jerárquicos** (ej. `alcance_1.fuentes_moviles.combustibles.diesel`)
   vía una tabla intermedia many-to-many (`question_tags`)
2. Un **pre-formulario** separado, con sus propias preguntas de
   caracterización del negocio (¿tiene flota?, ¿usa refrigerantes?,
   ¿consume energía eléctrica?, respondidas de forma independiente),
   **resuelve qué tags se habilitan** para esa empresa
3. El formulario dinámico final se arma cruzando los tags resueltos
   contra las preguntas etiquetadas con esos tags

Esto permite que **dos empresas del mismo sector con operaciones
distintas** (una con flota vehicular, otra sin) reciban formularios
distintos, ajustados a su realidad operativa específica.

## Decisión

Se implementó una versión más simple: la tabla `sector_questionnaire_rules`
mapea directamente `sector_code` + `subsector_code` → una pregunta
específica con su `emission_factor_id` ya vinculado. No existe un
pre-formulario de caracterización separado, ni una tabla `tags`, ni
`question_tags`. El "criterio de selección" de qué preguntas ve una
empresa es únicamente su sector/subsector, definido una vez al crear la
empresa.

## Por qué se decidió así

**1. El sector/subsector ya es un dato que la empresa provee al
registrarse** (`Company.company_sector_id`, `subsector_code`) — no
requería construir una pantalla ni un flujo nuevo de "pre-formulario".
Usar un dato que ya existe fue la ruta más rápida hacia un Smart Intake
funcional dentro del plazo del Entregable 2.

**2. El objetivo explícitamente declarado en el requerimiento —
"agregar, editar o eliminar preguntas... sin modificar código" — no
depende de que el mecanismo de selección sea tags jerárquicos.** Una
fila nueva en `sector_questionnaire_rules` logra exactamente ese
objetivo con muchísimo menos código (sin tablas intermedias, sin lógica
de resolución de jerarquía de tags, sin UI de pre-formulario).

**3. Para los sectores objetivo del piloto (ECONOVA y los aliados
mencionados en las actas de kick-off — UDES, IMEBU, UCC, TP SENA,
Cámara de Comercio), el sector/subsector es, en la práctica, un
predictor razonable de qué preguntas aplican** — son organizaciones de
servicios/educación con perfiles operativos similares dentro de cada
categoría. La necesidad real de diferenciar por características
adicionales (flota, refrigerantes) puede no haberse manifestado todavía
con el conjunto de empresas piloto.

## Por qué esto sigue siendo una limitación real (no solo una diferencia de arquitectura)

A diferencia de otros puntos de este roadmap donde "diferente" resultó
"igual de bueno", aquí hay una capacidad genuinamente ausente: **el
sistema no puede diferenciar dos empresas del mismo sector con
operaciones distintas.** Si UDES tiene flota vehicular e IMEBU no,
ambas — si comparten sector/subsector — reciben exactamente el mismo
formulario hoy. Esto no es un problema de UX menor: puede llevar a que
una empresa vea preguntas irrelevantes para su operación, o — más
grave — que no vea una pregunta relevante porque el sistema no tiene
forma de saber que aplica, más allá de forzar sectores/subsectores
artificialmente granulares (lo cual no escala y ensucia el catálogo de
sectores).

## Camino de mejora si se decide cerrar esto

No hace falta reconstruir el sistema de tags jerárquicos completo del
requerimiento original para resolver el caso de uso real. Una opción
intermedia, más barata:

1. Agregar una tabla `company_characteristics` (o columnas booleanas en
   `companies`: `has_fleet`, `has_refrigerants`, `has_boilers`, etc.) —
   capturadas una vez al onboardear la empresa (esto sí sería el
   "pre-formulario" simplificado)
2. Extender `sector_questionnaire_rules` con una columna opcional
   `requires_characteristic` que, si está presente, filtra la pregunta
   según el valor de esa característica en la empresa
3. Esto logra el 80% del valor de los tags jerárquicos (diferenciar por
   características operativas) sin la complejidad de una jerarquía de
   tags many-to-many completa

## Consecuencias

**Positivas:**
- Smart Intake funcional dentro del plazo, sin bloquear el Entregable 2
- Agregar preguntas nuevas por sector no requiere código, solo datos (`SectorQuestionnaireRuleSeeder` como referencia)
- Modelo de datos más simple de mantener y razonar sobre él

**Negativas / limitaciones aceptadas:**
- Empresas del mismo sector con operaciones distintas reciben el mismo formulario
- No hay pre-formulario de caracterización independiente del sector

## Referencias

- `backend/app/Models/SectorQuestionnaireRule.php`
- `backend/database/seeders/SectorQuestionnaireRuleSeeder.php`
- `docs/architecture/data-model.md`, sección `sector_questionnaire_rules`
- `docs/roadmap-auditor-walkthrough.md`, puntos 12-13 — estado de cumplimiento contra el requerimiento original
