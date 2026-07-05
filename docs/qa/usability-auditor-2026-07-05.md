# Usability Report — Auditor — 2026-07-05

Usuario: `auditor@zia.com`, con asignación a Bucarretes S.A.S. / período
2024 creada vía tinker (gap de Task 2 de superadmin).

## Task 1: Revisar el consolidado de empresa asignada
- Steps expected: 1
- Steps actually taken: 1
- Friction points: ninguno. El dashboard muestra el consolidado completo de
  la empresa (Huella Total 53,880.29 tCO2e), correcto para el rol.
- Time to complete: ~00:05
- Result: ✅ Pass

## Task 2: Ver el historial
- Steps expected: 1
- Steps actually taken: 1
- Friction points: ninguno. El link "Historial" es visible en el menú y
  funciona; no aparece el botón "Subir soporte".
- Time to complete: ~00:05
- Result: ✅ Pass

## Task 3: Dejar una observación de auditoría
- Steps expected: 4 (seleccionar período → escribir hallazgo →
  seleccionar dictamen → registrar)
- Steps actually taken: 4 para enviar (`POST` → 201 Created); la
  observación nunca aparece en la lista después
- Friction points: (a) el desplegable de Período parece vacío al primer
  click aunque ya tiene 2024 preseleccionado — confusión menor de UX; (b)
  el campo "Dictamen" exige seleccionar explícitamente
  Conforme/Observado/No conforme para habilitar el botón ("Sin dictamen"
  no cuenta, correctamente evita envíos vacíos); (c) tras crear la
  observación con éxito (201), la lista sigue mostrando "Sin observaciones
  registradas para este período" — mismo bug de change detection visto en
  Admin/User/IoT Tech.
- Time to complete: ~00:40
- Result: ⚠️ Pass with friction (el registro funciona, la confirmación visual no)

## Findings
1. **[Crítico]** Observación de auditoría creada exitosamente pero
   invisible en la lista tras enviarla — mismo bug de change detection
   transversal. Handoff: Frontend Dev.
2. **[Bajo]** El selector de Período no comunica claramente que ya tiene
   un valor preseleccionado al abrirse por primera vez. Handoff: UX/UI
   Designer.
