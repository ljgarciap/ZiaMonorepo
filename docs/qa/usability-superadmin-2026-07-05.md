# Usability Report — Superadmin — 2026-07-05

Ejecutado con Playwright MCP sobre stack local, tras reconstruir las imágenes
Docker (estaban desactualizadas ~6 días — ver Hallazgo 0 en el reporte
consolidado). Usuario: `superadmin@zia.com`.

## Task 1: Crear los usuarios de prueba faltantes (iot_tech, auditor, viewer)
- Steps expected: ~4 (abrir Usuarios → Invitar Usuario → llenar → seleccionar rol → enviar, x3)
- Steps actually taken: bloqueado en el paso "seleccionar rol"
- Friction points: el modal "Invitar Usuario" solo ofrece 3 roles (Usuario,
  Administrador, Super Admin) — no hay forma de crear un usuario `iot_tech`,
  `auditor` o `viewer` desde la UI, pese a que el backend valida y soporta
  los 6 roles (`AdminUserController@store`). Se resolvió creando los usuarios
  vía `php artisan tinker` (opción 2 del script de prueba).
- Time to complete: N/A (no completable por UI)
- Result: ❌ Fail (no se puede completar la tarea tal como está especificada)

## Task 2: Asignar auditor a un período
- Steps expected: 3 (Asignaciones de Auditor → crear → seleccionar auditor/empresa/período/vencimiento)
- Steps actually taken: no ejecutado por UI — mismo bloqueo que Task 1 (el
  auditor de prueba no existía vía UI); se creó la asignación directamente
  vía tinker (`AuditorAssignment::updateOrCreate`).
- Friction points: no se pudo validar el formulario real por el bloqueo previo.
- Time to complete: N/A
- Result: ⚠️ Pass with friction (funcionalidad de backend confirmada, UI no ejercida)

## Task 3: Versionar un factor de emisión
- Steps expected: 3 (Factores → editar factor → guardar → ver historial)
- Steps actually taken: 3 para editar y guardar; historial no accesible desde la UI
- Friction points: no existe ningún botón o enlace al historial de versiones
  en el diálogo de edición del factor. El endpoint `GET
  /api/admin/factors/{id}/versions` funciona correctamente y devuelve el
  historial (`changed_by`, `changes`, timestamp), pero es inalcanzable sin
  conocer la URL directamente.
- Time to complete: ~00:40 (edición) — historial no localizable, tiempo N/A
- Result: ⚠️ Pass with friction (funciona, pero el historial "no se
  encuentra fácilmente" — respuesta a la pregunta del script: no se
  encuentra en absoluto)

## Findings
1. **[Alto]** Modal "Invitar Usuario" no permite crear roles `iot_tech`,
   `auditor`, `viewer` — gap de producto que bloquea el onboarding real de
   esos 3 roles. Handoff: UX/UI Designer + Backend/Frontend Dev.
2. **[Medio]** Historial de versiones de factores de emisión no tiene
   entrada visible en la UI (solo vía API). Candidato a botón "Ver
   historial" en el diálogo de edición. Handoff: UX/UI Designer.
