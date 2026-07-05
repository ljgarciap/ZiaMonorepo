# Usability Report — IoT Tech — 2026-07-05

Usuario: `iot@zia.com` (Bucarretes S.A.S., creado vía tinker por el gap de
Task 1 de superadmin).

## Task 1: Registrar un dispositivo
- Steps expected: 6 (nombre, tipo, ubicación, unidad, ID ThingsBoard, guardar)
- Steps actually taken: 6 para el formulario; el dispositivo nunca aparece
  después
- Friction points: `POST /api/companies/{id}/iot-devices` → 201 Created, y
  el `GET` posterior (incluso tras recargar la página completa) confirma
  que el dispositivo existe en la base de datos — pero la tabla de
  dispositivos sigue mostrando "No hay dispositivos registrados en esta
  empresa." `device-management.ts` asigna `this.devices = devices` como
  propiedad de clase plana dentro de un `.subscribe()`, sin señales ni
  `ChangeDetectorRef` — mismo bug de fondo que en Admin/User.
- Time to complete: ~00:50 (formulario), resultado invisible
- Result: ❌ Fail — el dispositivo existe pero es inutilizable desde la UI

## Task 2: Calibrar un dispositivo
- Steps expected: 2 (click en ícono calibración → completar `window.prompt()`)
- Steps actually taken: 0 — no se pudo intentar por UI porque el
  dispositivo recién creado no aparece en la tabla (bloqueado por Task 1)
- Friction points: se validó `POST /api/iot-devices/{id}/calibrate`
  directamente por API (200 OK) para confirmar que el backend funciona.
  No se pudo evaluar la experiencia real del `window.prompt()` nativo.
- Time to complete: N/A
- Result: ❌ Fail (bloqueado, no evaluable)

## Task 3: Resolver una alerta
- No evaluado — no había alertas pendientes y el flujo depende de la misma
  tabla de dispositivos bloqueada en Task 1.
- Result: — No evaluado

## Task 4: Confirmar que NO tiene acceso al Dashboard de emisiones
- Steps expected: 1 (click en Dashboard, observar)
- Steps actually taken: 1
- Friction points: el backend bloquea correctamente
  (`GET /api/dashboard/summary` y `/trends` → 403 Forbidden), pero el
  frontend no muestra ningún mensaje de error al usuario — simplemente
  presenta un dashboard vacío con todos los valores en 0.00 y "No hay datos
  para mostrar". Un usuario real no distingue esto de "mi empresa no tiene
  datos todavía" vs. "no tengo permiso para ver esto".
- Time to complete: ~00:05
- Result: ⚠️ Pass with friction (el bloqueo funciona, el mensaje no)

## Findings
1. **[Crítico]** Dispositivo IoT recién creado no aparece en la tabla pese
   a existir en base de datos — bloquea el flujo completo de
   calibración/resolución de alertas para dispositivos nuevos. Handoff:
   Frontend Dev.
2. **[Medio]** El bloqueo de acceso al Dashboard para `iot_tech` es
   silencioso — no hay mensaje de "no tienes acceso", solo un dashboard en
   ceros. Candidato a mensaje explícito. Handoff: UX/UI Designer.
3. **[Bajo]** Menú lateral inconsistente: "Dispositivos IoT" no aparece en
   el menú estando en `/dashboard`, y "Huella de Carbono/Smart
   Intake/Simulador" no aparecen estando en `/iot/devices` — mismo usuario,
   mismo rol, distinta ruta. Handoff: Frontend Dev.
