# Spec: Ajuste de roles del equipo de agentes a la estructura ZiaMonorepo

**Date**: 2026-06-16
**Requested by**: Luis
**Status**: Approved
**Project**: ZiaMonorepo (antes referenciado como `zia-carbon-control/`)

## Problem
Se creó `D:\SOFTCLASS\ZiaMonorepo`, que integra en un solo repositorio
el backend (Laravel 11) y el frontend (Angular) que antes correspondían
al proyecto "ZIA Carbon Control" (carpeta `zia-carbon-control/`, ya
inexistente). El CLAUDE.md global todavía referencia la carpeta y stack
antiguos, y se añadió una `.claude/` local a ZiaMonorepo sin definir
cómo se relaciona con el equipo de agentes global.

## Solution summary
ZiaMonorepo reemplaza la entrada "ZIA Carbon Control" en la tabla de
Proyectos activos del CLAUDE.md global (carpeta y stack actualizados,
incluyendo Redis que no estaba documentado). El equipo de agentes
(`analyst`, `architect`, `ai-architect`, `pm`, `backend-dev`,
`frontend-dev`, `devops`, `senior-reviewer`, `qa`, `tech-writer`) y los
skills siguen siendo globales (`D:\SOFTCLASS\.claude\agents`,
`...\skills`) — no se duplican dentro del monorepo. La `.claude/` local
de ZiaMonorepo se limita a configuración local (`settings.local.json`).
Sesiones (`.claude/sessions/ZiaMonorepo.md`) y notificaciones
(`.claude/scripts/notify.sh`, `handoff-to-phone.sh`) también siguen
viviendo en el workspace global, igual que con GuepardAI. Las specs de
features de este proyecto se guardan dentro del propio monorepo en
`ZiaMonorepo/docs/specs/`.

## Users and roles
No cambia el flujo de roles definido en el CLAUDE.md global
(Analista → Arquitecto ⟷ AI Architect → PM → Backend/Frontend/DevOps →
Senior Reviewer → QA → Luis). Este ajuste es organizativo, no afecta
permisos de usuarios finales de la aplicación ZIA.

## Acceptance criteria
- [x] La tabla "Proyectos activos" del CLAUDE.md global apunta a la
  carpeta `ZiaMonorepo/` en la fila correspondiente a ZIA Carbon Control.
- [x] El stack documentado incluye Redis (presente en docker-compose.yml
  pero ausente antes en la tabla).
- [x] No se duplican agentes ni skills dentro de `ZiaMonorepo/.claude/`.
- [x] Existe `ZiaMonorepo/docs/specs/` para futuras specs del proyecto.
- [ ] Próxima vez que se invoque al Architect o PM sobre este proyecto,
  deben leer el CLAUDE.md global (no uno local, que no existe) más esta
  spec como contexto de la migración.

## Edge cases and error scenarios
- Si en el futuro se crea un `ZiaMonorepo/CLAUDE.md` propio, debe
  declarar explícitamente que hereda el flujo de agentes del global y
  documentar solo particularidades del monorepo (p.ej. cómo correr
  backend y frontend juntos vía docker-compose).
- Si se reintroduce una carpeta `zia-carbon-control/` por error (p.ej.
  clon antiguo), no debe haber dos entradas activas para el mismo
  proyecto en la tabla global.

## Out of scope
- No se migra ni reescribe código de la aplicación ZIA Carbon Control.
- No se crean agentes/skills nuevos ni se modifican los existentes.
- No se cambia `active_project` manualmente — lo gestiona
  `handoff-to-phone.sh` automáticamente al cierre de cada tarea.

## Open questions
Ninguna — decisiones confirmadas por Luis:
1. ZiaMonorepo reemplaza la entrada `zia-carbon-control` (mismo proyecto).
2. Los roles/skills siguen siendo globales, sin copia local.
3. Sesiones y specs siguen el mismo patrón que GuepardAI (sesiones
   globales, specs dentro del repo del proyecto).

## References
- CLAUDE.md global: `D:\SOFTCLASS\CLAUDE.md`
- Agentes: `D:\SOFTCLASS\.claude\agents\*.md`
- Skills: `D:\SOFTCLASS\.claude\skills\*.md`
- docker-compose.yml: `ZiaMonorepo/docker-compose.yml` (servicios db,
  redis, backend, frontend)
