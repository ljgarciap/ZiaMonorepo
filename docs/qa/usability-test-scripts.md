# Scripts de prueba de usabilidad — primera ronda por rol

**Fecha de redacción**: 2026-07-04
**Spec de referencia**: `docs/specs/usability-testing-scope.md`
**Ejecutor**: QA Performance, con Playwright MCP (pendiente de reinicio de sesión para activarse)
**Entorno**: stack local (`docker compose up`), `http://localhost` según `docker-compose.yml`

> Playwright solo debe usarse para esta ronda programada, no para
> verificaciones ad hoc de cambios menores — ver memoria de feedback del
> equipo. Al terminar esta ronda, no se vuelve a invocar hasta la
> siguiente ronda deliberada de QA.

## Precondición bloqueante: usuarios de prueba

`backend/database/seeders/UserSeeder.php` solo crea 3 usuarios:

| Email | Password | Rol |
|---|---|---|
| `superadmin@zia.com` | `password` | superadmin |
| `admin@zia.com` | `password` | admin |
| `user@zia.com` | `password` | user |

**No existen usuarios sembrados para `iot_tech`, `auditor` ni `viewer`.**
Antes de ejecutar las tareas de esos 3 roles, hay dos opciones:

1. **Crearlos vía UI como parte de la Tarea 1 de `superadmin`** (recomendado
   — además valida el flujo real de creación de usuarios, ver abajo)
2. Extender `UserSeeder.php` con los 3 roles faltantes (más rápido pero no
   ejercita el flujo de creación real)

Para `auditor`, además se necesita una `auditor_assignments` vigente
(empresa + período + vencimiento futuro) — créala desde
`/admin/auditor-assignments` como parte de la Tarea de superadmin, o el
auditor no verá ningún período al iniciar sesión.

## Login (común a todos los roles)
1. Navegar a `/login`
2. Llenar `Email` (`input[formControlName="email"]`) y `Contraseña` (`input[formControlName="password"]`)
3. Click en el botón de submit
4. Si el usuario tiene más de un contexto disponible, aparece una pantalla de selección de contexto — elegir el correspondiente

---

## SUPERADMIN

### Tarea 1: Crear los usuarios de prueba faltantes (setup + primera tarea real)
1. Login como `superadmin@zia.com`
2. **Administración → Usuarios** (`/admin/users`)
3. Crear usuario con rol `iot_tech` (email `iot@zia.com`, password `password`), asignado a la empresa de prueba
4. Repetir para `auditor` (email `auditor@zia.com`) y `viewer` (email `viewer@zia.com`)
5. **Registrar**: ¿el formulario permite seleccionar los 6 roles sin error? ¿queda claro a qué empresa se asigna cada uno?

### Tarea 2: Asignar auditor a un período
1. **Administración → Asignaciones de Auditor** (`/admin/auditor-assignments`)
2. Crear asignación: auditor recién creado + empresa de prueba + período existente + vencimiento a futuro
3. **Registrar**: ¿es claro qué período se está asignando? ¿el formulario previene fechas de vencimiento inválidas (pasadas)?

### Tarea 3: Versionar un factor de emisión
1. **Administración → Factores de Emisión** (`/admin/metadata`)
2. Editar un factor existente (cambiar su valor)
3. Verificar que aparece en el historial de versiones (`GET /admin/factors/{id}/versions` desde la UI si existe botón para verlo)
4. **Registrar**: ¿se encuentra fácilmente el historial de versiones, o hay que buscarlo?

---

## ADMIN

### Tarea 1: Invitar un usuario nuevo con rol `user`
1. Login como `admin@zia.com`
2. **Administración → Usuarios**
3. Crear usuario con rol `user`
4. **Registrar**: ¿el formulario deja claro que solo puede asignar rol `user`, o intenta seleccionar otro rol y falla sin explicación?

### Tarea 2: Revisar el consolidado de empresa y el panel de completitud
1. **Dashboard**
2. Ubicar el Panel de Completitud Administrativa
3. **Registrar**: tiempo hasta identificar qué unidad/usuario tiene datos pendientes

### Tarea 3: Ver dispositivos IoT de su empresa (solo lectura — fix reciente)
1. **Administración → Dispositivos IoT** (nuevo link agregado en el fix de consistencia RBAC)
2. **Registrar**: ¿confirma que NO aparecen botones de crear/editar/eliminar/calibrar? ¿es obvio que es de solo lectura?

### Tarea 4: Ver gestión de Períodos (solo lectura — fix reciente)
1. **Administración → Gestión de Períodos**
2. **Registrar**: ¿confirma que no hay botones de acción, solo el estado del período?

---

## USER

### Tarea 1: Registrar una emisión
1. Login como `user@zia.com`
2. **Huella de Carbono** (`/form`)
3. Completar y enviar un registro
4. **Registrar**: tiempo total, cualquier campo confuso o error de validación no claro

### Tarea 2: Ver "Mi Huella" en el Dashboard
1. **Dashboard**
2. **Registrar**: ¿el título dice claramente "Mi Huella" y no genera confusión con el consolidado de empresa?

### Tarea 3: Generar su reporte de período
1. Seleccionar período → **Generar reportes** → PDF
2. **Registrar**: ¿nota la diferencia entre su dashboard (scoped) y el PDF (empresa completa)? — esto es intencional pero puede confundir, vale la pena observar la reacción

---

## IOT_TECH

### Tarea 1: Registrar un dispositivo
1. Login como `iot@zia.com` (creado en la Tarea 1 de superadmin)
2. **Dispositivos IoT** (`/iot/devices`)
3. Click "Registrar dispositivo", completar formulario, guardar
4. **Registrar**: tiempo, campos confusos (tipo, unidad, ID ThingsBoard)

### Tarea 2: Calibrar un dispositivo
1. Click en el ícono de calibración de un dispositivo existente
2. **Registrar**: el flujo usa un `window.prompt()` nativo del navegador — anotar si esto se siente fuera de lugar comparado con el resto de la UI (candidato a hallazgo de UX/UI)

### Tarea 3: Resolver una alerta
1. Si hay alertas pendientes, click "Diagnosticar y resolver"
2. **Registrar**: mismo punto sobre `window.prompt()`

### Tarea 4 (verificar fix reciente): Confirmar que NO tiene acceso al Dashboard de emisiones
1. Click en "Dashboard" desde el menú
2. **Registrar**: ¿qué ve exactamente? (el link es visible pero la llamada a la API debería fallar — confirmar que el mensaje de error, si aparece, no es confuso o técnico para el usuario final)

---

## AUDITOR

### Tarea 1: Revisar el consolidado de empresa asignada
1. Login como `auditor@zia.com` (con asignación creada en Tarea 2 de superadmin)
2. **Dashboard**
3. **Registrar**: si tiene más de una empresa, ¿el selector de contexto es claro?

### Tarea 2: Ver el historial (fix reciente — ahora visible en menú)
1. **Historial** (ahora visible en el menú lateral)
2. **Registrar**: confirmar que el link aparece y funciona; confirmar que NO aparece el botón de "Subir soporte"

### Tarea 3: Dejar una observación de auditoría
1. **Observaciones de Auditoría** (`/audit/observations`)
2. Crear observación: seleccionar el período asignado, escribir dictamen
3. **Registrar**: ¿es claro que solo puede dictaminar sobre el período específico asignado (no cualquiera)?

---

## VIEWER

### Tarea 1: Navegar el dashboard consolidado
1. Login como `viewer@zia.com` (creado en Tarea 1 de superadmin)
2. **Dashboard**
3. **Registrar**: confirmar ausencia del Panel de Completitud

### Tarea 2: Revisar historial (fix reciente — ya no muestra botón de subir)
1. **Histórico**
2. **Registrar**: confirmar que el botón "Subir soporte" ya NO aparece (antes aparecía y fallaba silenciosamente)

### Tarea 3: Generar un reporte
1. Seleccionar período → **Generar reportes**
2. **Registrar**: el viewer SÍ puede exportar en el estado actual del sistema (comportamiento confirmado, no es bug) — anotar si esto se siente inesperado para el propósito del rol; candidato a pregunta de producto para Luis, no a fix automático

---

## Formato de reporte de salida
Al ejecutar, usar la plantilla de `qa-performance.md`:
`docs/qa/usability-{role}-{fecha}.md`, con tabla de tareas, fricción,
tiempo y resultado (✅/⚠️/❌), y sección de hallazgos priorizados para
handoff al UX/UI Designer.
