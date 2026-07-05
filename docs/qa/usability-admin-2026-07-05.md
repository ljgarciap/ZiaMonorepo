# Usability Report — Admin — 2026-07-05

Usuario: `admin@zia.com` (contexto "Portal de Administrador").

## Task 1: Invitar un usuario nuevo con rol `user`
- Steps expected: 4
- Steps actually taken: 4
- Friction points: ninguno — el selector de rol correctamente solo ofrece
  "Usuario" para un admin (no puede ver/seleccionar admin/superadmin). Esto
  es exactamente el comportamiento deseado.
- Time to complete: ~00:35
- Result: ✅ Pass

## Task 2: Revisar el consolidado de empresa y el panel de completitud
- Steps expected: 2 (ir a Dashboard, ubicar panel)
- Steps actually taken: panel nunca aparece
- Friction points: el "Panel de Completitud Administrativa" nunca se
  renderiza pese a que `GET /api/dashboard/summary` sí devuelve
  `admin_panel` con datos completos (`registration_progress`, `by_unit`,
  `by_user`) y el rol activo es `admin` (`isAdminOrAbove` = true). Es un bug
  de fondo, no un problema de permisos — ver Hallazgo 1 del reporte
  consolidado (change detection en Angular zoneless).
- Time to complete: N/A — la tarea es imposible de completar tal cual
- Result: ❌ Fail

## Task 3: Ver dispositivos IoT de su empresa (solo lectura)
- Steps expected: 1 (click en "Dispositivos IoT")
- Steps actually taken: 1
- Friction points: ninguno. El subtítulo dice explícitamente "Consulta de
  sensores de Portal de Administrador (solo lectura)" y no hay ningún botón
  de crear/editar/eliminar/calibrar visible.
- Time to complete: ~00:05
- Result: ✅ Pass

## Task 4: Ver gestión de Períodos (solo lectura)
- Steps expected: 1
- Steps actually taken: bloqueado — pantalla se queda en "Cargando
  períodos..." indefinidamente
- Friction points: `admin-periods.ts::load()` sí recibe la respuesta de
  `GET /api/admin/companies` (200 OK, confirmado por red) y pone
  `this.loading = false`, pero la vista nunca sale del spinner. Mismo bug
  de change detection que en Task 2.
- Time to complete: N/A
- Result: ❌ Fail

## Findings
1. **[Crítico]** Panel de Completitud Administrativa invisible pese a datos
   correctos del backend — bug de change detection (Angular zoneless sin
   `markForCheck`/signals). Handoff: Frontend Dev / Architect.
2. **[Crítico]** Gestión de Períodos se queda perpetuamente en estado de
   carga — mismo bug de fondo, componente distinto. Handoff: Frontend Dev.
3. **[Bajo/positivo]** Restricción de rol en "Invitar Usuario" e
   indicadores de "solo lectura" en Dispositivos IoT funcionan
   correctamente y son claros para el usuario.
