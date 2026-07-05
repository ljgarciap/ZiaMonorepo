# Ronda de Usabilidad — Resumen Consolidado — 2026-07-05

Ronda programada de QA Performance/UX ejecutada con Playwright MCP contra
el stack local, cubriendo los 6 roles según
`docs/qa/usability-test-scripts.md`. Reportes individuales:
- [Superadmin](usability-superadmin-2026-07-05.md)
- [Admin](usability-admin-2026-07-05.md)
- [User](usability-user-2026-07-05.md)
- [IoT Tech](usability-iot-tech-2026-07-05.md)
- [Auditor](usability-auditor-2026-07-05.md)
- [Viewer](usability-viewer-2026-07-05.md)

## Hallazgo 0 — Imágenes Docker desactualizadas (bloqueante, ya corregido en sesión)
`docker-compose.yml` construye `backend`/`frontend` desde `Dockerfile`
(sin bind-mount de código fuente). Las imágenes llevaban desde 2026-06-28
sin reconstruirse, mientras el repo tenía commits hasta el 2026-07-04
(incluyendo el fix de RBAC del mismo día). Esto invalidaba cualquier prueba
contra "código actual" — se reconstruyeron ambas imágenes y se corrieron ~20
migraciones pendientes antes de iniciar la ronda real. **Recomendación**:
agregar un paso de `docker compose build` al flujo de DevOps antes de
cualquier ronda de QA, o mover a bind-mount + hot-reload en desarrollo.

## Hallazgo 1 — ~~P0~~ CERRADO: era artefacto del entorno de automatización, no bug de producto
Los clicks simulados con mouse real (mousedown+mouseup, indistinguibles de
un click humano) no disparaban de forma confiable el `(click)` de botones
`mat-button`/`mat-stroked-button` (confirmado en login, selector de
contexto) — reproducido ~17% de las veces contra el stack en Docker vía
Playwright/CDP. Se descartaron dos hipótesis (ripple loader de Material,
migración zoneless incompleta) antes de sospechar del propio entorno de
automatización.

**Resolución (2026-07-05)**: Luis probó manualmente en un navegador de
escritorio normal (no headless, no CDP) — 16 intentos de click reales
entre login y selector de contexto, **0 fallos**. Con una tasa base de
fallo del ~17%, la probabilidad de 0/16 por azar si el bug siguiera activo
es ~4.6% — evidencia suficiente para cerrar. Conclusión: el fallo era
específico del pipeline de Playwright/CDP headless contra este stack
Docker, no un bug real end-user-facing. No requiere cambio de código.
Handoff: ninguno — cerrado sin acción de Frontend Dev.

## Hallazgo 2 — P0: Change detection no re-renderiza tras respuestas async (frontend, transversal)
Reproducido **5 veces** en componentes distintos, todos con el mismo
patrón (propiedad de clase plana mutada dentro de un `.subscribe()`, sin
signals ni `ChangeDetectorRef.markForCheck()/detectChanges()`), en una app
Angular 21 **sin `zone.js`** (no está en `package.json`, `angular.json` ni
hay `provideZonelessChangeDetection()` explícito — no está claro si es
zoneless intencional o una migración incompleta):
1. Panel de Completitud Administrativa (dashboard-content.ts) — no aparece
   pese a `admin_panel` presente en la API y rol correcto.
2. Gestión de Períodos (admin-periods.ts) — `Cargando períodos...` infinito
   pese a `GET /api/admin/companies` 200 OK.
3. Título "Mi Huella" (dashboard-content.ts) — muestra "Huella Total" pese
   a `scope: "own"` en la API.
4. Tabla de Dispositivos IoT (device-management.ts) — dispositivo recién
   creado (201 Created, confirmado en BD) nunca aparece, ni tras recargar.
5. Lista de Observaciones de Auditoría — observación creada (201 Created)
   nunca aparece en la lista.

**Impacto**: bloquea o degrada severamente 5 flujos distintos en 4 roles.
Este es el hallazgo de mayor prioridad de toda la ronda — más urgente que
cualquier hallazgo de UX puntual. Handoff: Architect + Frontend Dev —
decidir si se completa la migración a zoneless (convertir estado a
signals) o se reintroduce `zone.js`; no debe quedar a medias.

## Hallazgo 3 — P0: Reporte PDF roto para todos los roles (backend)
`backend/resources/views/reports/summary.blade.php` líneas 337-338: dos
directivas `@if(...)...@endif` en una sola línea no compilan el `@endif`
(queda como texto literal en el PHP generado), causando
`ParseError: unexpected end of file` y 500 en
`GET /api/reports/periods/{id}/pdf` para cualquier usuario. Reproducido
como `user@zia.com`. Fix: reescribir esas 2 líneas como bloques
`@if/@endif` multilínea (igual que la línea 336, que sí compila
correctamente por tener `<br>` antes del `@endif`). Handoff: Backend Dev
— fix inmediato, es un blocker de release.

## Hallazgo 4 — Alto (seguridad): fuga de datos administrativos en el payload del dashboard
`GET /api/dashboard/summary` incluye la clave `admin_panel` (progreso de
registro por usuario) para **cualquier rol**, incluido `viewer`. El
frontend la oculta con `*ngIf="isAdminOrAbove"`, pero el backend no la
excluye del payload según rol — es visible con cualquier herramienta de
red. Handoff: Cybersecurity + Backend Dev.

## Hallazgo 5 — Alto (producto): roles operativos no creables desde la UI
El modal "Invitar Usuario" (`admin-dialogs.ts`) solo ofrece
Usuario/Administrador/Super Admin. No hay forma de crear usuarios
`iot_tech`, `auditor` o `viewer` desde la UI, pese a que el backend los
soporta plenamente. Se tuvieron que crear vía `tinker` para poder ejecutar
el resto de la ronda. Handoff: UX/UI Designer + Frontend Dev.

## Hallazgos menores — todos cerrados (2026-07-05)
- ~~Historial de versiones de factores de emisión (`/versions`) funciona
  mas no tiene ningún punto de entrada en la UI.~~ **Resuelto**: botón
  "Ver historial" en el diálogo de edición de factor (`FactorDialog`),
  abre `FactorVersionsDialog` con el diff de campos entre versiones.
- ~~Mensajes de acceso denegado silenciosos (dashboard bloqueado para
  `iot_tech` no muestra ningún aviso, solo ceros).~~ **Resuelto**:
  `dashboard-content.ts` detecta 403 y muestra un aviso explícito en vez
  de dejar los valores en cero.
- ~~Menú lateral inconsistente según ruta actual para el mismo rol/usuario
  (visto en `iot_tech` y `auditor`).~~ **Cerrado sin cambio de código**:
  el sidebar (`dashboard.html`) es un único shell compartido, gateado
  solo por `authService.currentContext()?.role` (un signal), sin ninguna
  lógica dependiente de la ruta. Luis verificó manualmente navegando
  `/dashboard` ↔ `/iot/devices` (iot_tech) y `/dashboard` ↔
  `/audit/observations` (auditor): el menú no cambió. No reproducible en
  el código actual — probablemente artefacto del entorno de Playwright,
  igual que H1.
- ~~Selector de Período en Observaciones de Auditoría no comunica que ya
  tiene un valor preseleccionado.~~ **Resuelto**: `selectedPeriodId` era
  una propiedad plana mutada dentro de un `.subscribe()` (mismo patrón
  de bug de Hallazgo 2) — convertida a signal en `observations.ts`.

## Positivos confirmados (no tocar)
- Restricción de rol en "Invitar Usuario" para `admin` (solo ve "Usuario").
- Indicadores de solo-lectura en Dispositivos IoT y Gestión de Períodos
  para `admin`.
- Botón "Subir soporte" correctamente ausente para `auditor`/`viewer`.
- Contador de fuentes cargadas en el formulario de Huella de Carbono se
  actualiza en tiempo real (única vista sin el bug de change detection).
- Bloqueo de dictamen vacío en Observaciones de Auditoría.

## Siguiente paso
Playwright no se vuelve a invocar hasta la próxima ronda deliberada de QA
(por acuerdo del equipo). Los hallazgos 1-5 deben pasar a Senior
Reviewer/Architect para priorización antes de cualquier release.
