# Usability Report — Viewer — 2026-07-05

Usuario: `viewer@zia.com` (Bucarretes S.A.S., creado vía tinker por el gap
de Task 1 de superadmin).

## Task 1: Navegar el dashboard consolidado
- Steps expected: 1
- Steps actually taken: 1
- Friction points: ninguno visible en UI (el Panel de Completitud está
  ausente, como se espera para este rol). Sin embargo, `GET
  /api/dashboard/summary` incluye la clave `admin_panel` completa
  (`registration_progress`, `by_user`) en el payload de red incluso para
  `viewer` — el filtrado es solo del lado del frontend (`*ngIf`), no del
  backend. Un usuario con herramientas de desarrollador puede ver datos
  administrativos de otros usuarios que no debería.
- Time to complete: ~00:05
- Result: ⚠️ Pass with friction (hallazgo de seguridad, no de UX)

## Task 2: Revisar historial
- Steps expected: 1
- Steps actually taken: 1
- Friction points: ninguno. El botón "Subir soporte" ya no aparece
  (comportamiento correcto, fix confirmado).
- Time to complete: ~00:05
- Result: ✅ Pass

## Task 3: Generar un reporte
- Steps expected: 1 (confirmar que el botón existe y está habilitado)
- Steps actually taken: 1
- Friction points: el botón "Generar reportes" está presente y habilitado
  para `viewer` — confirma que el rol sí puede exportar en el estado
  actual del sistema. No se re-probó la generación real del PDF (ver
  Hallazgo de reporte roto, ya confirmado transversalmente en el reporte
  de `user`).
- Time to complete: ~00:05
- Result: ⚠️ Pass with friction (comportamiento confirmado, no necesariamente deseado)

## Findings
1. **[Alto — seguridad]** El payload de `GET /api/dashboard/summary`
   incluye `admin_panel` (datos administrativos por usuario) para el rol
   `viewer`, aunque la UI no lo muestre. El backend debe excluir esa clave
   según rol, no depender solo del `*ngIf` del frontend. Handoff:
   Cybersecurity + Backend Dev.
2. **[Pregunta de producto]** `viewer` puede exportar reportes igual que
   roles con más privilegios — ¿es esto intencional dado el propósito del
   rol? Candidato a decisión de Luis, no a fix automático.
