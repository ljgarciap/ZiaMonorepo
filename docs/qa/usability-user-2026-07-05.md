# Usability Report — User — 2026-07-05

Usuario: `user@zia.com` (EcoTech Solutions S.A.S.).

## Task 1: Registrar una emisión
- Steps expected: 4 (nombre de huella → seleccionar factor → cantidad → cargar → guardar)
- Steps actually taken: 4
- Friction points: ninguno relevante. El contador "N / 13 fuentes cargadas"
  se actualiza correctamente en tiempo real (uno de los pocos flujos donde
  el re-render SÍ funciona). `POST /api/periods/{id}/emissions` → 201.
- Time to complete: ~00:45
- Result: ✅ Pass

## Task 2: Ver "Mi Huella" en el Dashboard
- Steps expected: 1 (ir a Dashboard, leer el título)
- Steps actually taken: 1
- Friction points: el título muestra "Huella Total" en vez de "Mi Huella",
  pese a que `GET /api/dashboard/summary` devuelve `scope: "own"`
  correctamente. El template usa
  `{{summary?.scope === 'own' ? 'Mi Huella' : 'Huella Total'}}` — la
  condición es correcta, pero la vista no refleja el dato ya cargado
  (mismo bug de change detection de fondo).
- Time to complete: N/A — dato correcto en backend, incorrecto en pantalla
- Result: ❌ Fail (fix de la spec 1.2.3 no visible para el usuario final)

## Task 3: Generar su reporte de período
- Steps expected: 3 (seleccionar período → Generar reportes → PDF)
- Steps actually taken: 3, pero el PDF nunca se genera
- Friction points: `GET /api/reports/periods/{id}/pdf` devuelve 500. Causa
  raíz: `backend/resources/views/reports/summary.blade.php` líneas 337-338
  tienen dos `@if(...)...@endif` en una sola línea cuyo `@endif` no
  compila (queda como texto literal), rompiendo el PHP generado
  (`ParseError: unexpected end of file`). Afecta a cualquier rol, no es
  específico de `user`.
- Time to complete: N/A
- Result: ❌ Fail

## Findings
1. **[Crítico]** Generación de reporte PDF completamente rota (500) para
   cualquier usuario — bug de compilación Blade en
   `reports/summary.blade.php:337-338`. Handoff: Backend Dev (fix
   inmediato: reescribir esas 2 líneas como bloques `@if/@endif`
   multilínea).
2. **[Alto]** "Mi Huella" vs "Huella Total": el fix de la spec 1.2.3
   (commit 15b5f61) está implementado correctamente en backend pero no se
   ve reflejado en pantalla — mismo bug de change detection transversal.
