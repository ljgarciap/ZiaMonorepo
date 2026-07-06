# Manual de Usuario — Superadministrador

**Rol**: `superadmin`
**Última actualización**: 2026-07-05
**Alcance**: ZIA Carbon Control
**Audiencia**: uso interno del equipo (no se distribuye a empresas clientes)

## ¿Qué puedes hacer con este rol?
Eres el único rol con visibilidad y control sobre toda la plataforma —
todas las empresas, todos los usuarios, y los catálogos globales que
usan todas las empresas (factores de emisión, tags, sectores, unidades).
El resto de los roles (`admin`, `user`, `iot_tech`, `auditor`, `viewer`)
solo ven su propia empresa.

## Panel ejecutivo de plataforma
### Para qué sirve
Ver el estado consolidado de todas las empresas: KPIs globales, top 5
empresas por huella, tendencia de 5 años.

### Paso a paso
1. Entra a **Administración → Plataforma** (`/admin/platform`)
2. El panel carga automáticamente el consolidado — no requiere seleccionar empresa

### Qué vas a ver
Solo tú ves esta pantalla. Ningún otro rol tiene acceso, ni siquiera admin.

## Dashboard por empresa
### Para qué sirve
Ver el consolidado de huella de carbono de una empresa específica, igual
que lo vería su Admin.

### Paso a paso
1. Selecciona la empresa desde el selector de contexto
2. Entra a **Dashboard**

### Qué vas a ver
El consolidado completo (nunca scoped a "tus propias" emisiones — eso
solo le pasa al rol `user`). También ves el Panel de Completitud
Administrativa (por unidad/por usuario), que los roles `user`, `auditor`
y `viewer` no ven.

## Gestión de empresas
### Para qué sirve
Dar de alta nuevas empresas clientes y administrar las existentes.

### Paso a paso
1. **Administración → Empresas** (`/admin/companies`)
2. Crear/editar/eliminar — eres el único rol que puede escribir aquí; el
   Admin de una empresa solo puede **leer** los datos de la suya (pantalla
   separada, de solo lectura)
3. **Aprobar metodología**: cuando una empresa define su metodología de
   cálculo (año base, enfoque de descarbonización), debes aprobarla
   explícitamente. Si el Admin cambia metodología/año base después,
   la aprobación se revoca automáticamente y debes volver a aprobarla

### Qué vas a ver
Un listado de todas las empresas de la plataforma, no solo las tuyas.

## Gestión de usuarios
### Para qué sirve
Crear, editar, bloquear y eliminar cuentas de cualquier rol, en cualquier empresa.

### Paso a paso
1. **Administración → Usuarios** (`/admin/users`)
2. Puedes asignar cualquier rol: `superadmin`, `admin`, `user`, `iot_tech`, `auditor`, `viewer`
3. **Bloquear cuenta** (sin eliminarla): útil para suspender acceso temporalmente. Un usuario bloqueado no puede iniciar sesión, y si ya tenía una sesión abierta, se le rechaza en la siguiente petición — no basta con esperar a que expire el token
4. **Eliminar cuenta**: acción exclusiva tuya — ni el Admin de empresa puede hacerlo

### Qué vas a ver
Todos los usuarios de la plataforma, incluidos los eliminados (marcados como tal).

### Errores comunes
- Si entras "como admin" (auto-degradado, sin seleccionar una empresa
  específica) y no tienes tú mismo una fila de pertenencia a esa empresa,
  vas a ver listas vacías de usuarios/empresas en ese contexto — no es un
  bug, es que estás viendo la empresa desde el lente de "admin sin
  empresa asignada"

## Períodos de reporte
### Para qué sirve
Administrar el ciclo de vida completo de un período (crear, cerrar,
reabrir, enviar a revisión, archivar).

### Paso a paso
1. **Administración → Períodos**
2. Eres el único rol que puede cerrar/reabrir/archivar un período — el
   Admin solo puede leerlo (ver nota abajo)

### Errores comunes
- La pantalla de gestión de períodos es visible para el Admin también,
  pero si el Admin intenta cerrar/reabrir/archivar, la acción falla — esas
  operaciones son exclusivas tuyas aunque la pantalla no lo distinga
  visualmente. Si un Admin te reporta un error al intentarlo, es
  comportamiento esperado, no un bug

## Emisiones — borrado en período cerrado
### Para qué sirve
Corregir datos de un período ya cerrado, con auditoría completa vía bitácora.

### Paso a paso
1. Desde el histórico de emisiones de la empresa, localiza el registro
2. Solo tú puedes borrar un registro si su período está `closed` — el
   Admin puede borrar únicamente en períodos abiertos
3. Cualquier borrado queda registrado en la bitácora de actividad

## Catálogo de tags, factores de emisión y otros catálogos globales
### Para qué sirve
Mantener los catálogos que usan todas las empresas: tags, factores de
emisión (con versionado — puedes ver el historial de cambios de cada
factor), fórmulas, unidades, alcances, sectores.

### Paso a paso
1. **Administración → Tags / Factores / Sectores / Unidades** según corresponda
2. Los cambios a un factor de emisión quedan versionados — el motor de
   cálculo sigue usando el valor vigente, pero puedes consultar la línea
   de tiempo de cambios: al editar un factor, el botón **"Ver historial"**
   (esquina inferior izquierda del diálogo) muestra cada versión con
   quién la cambió, cuándo, y qué campos exactos cambiaron

### Errores comunes
- Habilitar/deshabilitar qué factores usa una empresa específica también
  lo puede hacer el Admin de esa empresa — no es exclusivo tuyo, a
  diferencia del catálogo maestro

## Dispositivos IoT
### Para qué sirve
Gestión completa de dispositivos de cualquier empresa, sin restricción de pertenencia.

### Paso a paso
1. **Administración → Dispositivos IoT** (vista global de toda la plataforma) o
2. Navega directo a `/iot/devices` para gestionar los de una empresa puntual

### Errores comunes
- El link de "Dispositivos IoT" en el menú lateral solo aparece
  automáticamente para el rol `iot_tech`. Como superadmin tienes acceso
  a la pantalla, pero debes navegar directo a la URL — el link no
  aparece en tu menú

## Auditoría — asignación de auditores externos
### Para qué sirve
Dar de alta a un Auditor externo y definir a qué empresa y **período
exacto** tiene acceso, con fecha de vencimiento.

### Paso a paso
1. **Administración → Asignaciones de Auditor** (`/admin/auditor-assignments`)
2. Crea la asignación: auditor + empresa + período + vencimiento
3. Esto es independiente de si el auditor ya tiene acceso general a la
   empresa (esa es otra capa: pertenencia a empresa con su propio
   vencimiento). Ambas capas deben estar vigentes para que el auditor
   vea y dictamine sobre ese período puntual

### Qué vas a ver
También puedes crear observaciones de auditoría tú mismo (igual que el
Auditor), y moderar/eliminar cualquier observación (igual que el Admin).

## Cuestionarios (Smart Intake)
### Para qué sirve
Crear y versionar las plantillas de cuestionario que las empresas usan para capturar datos.

### Paso a paso
1. **Administración → Cuestionarios**
2. Crear, versionar, publicar, archivar preguntas y plantillas

## Gestión de Grupos de Empresas
### Para qué sirve
Agrupar empresas que comparten infraestructura (ej. varios inquilinos de
un mismo edificio) o pertenecen al mismo holding, para ver su huella de
carbono **consolidada** como si fueran una sola unidad — sin mezclar sus
datos individuales de forma permanente.

### Paso a paso
1. **Administración → Grupos de Empresas** (`/admin/company-groups`)
2. **Nuevo Grupo**: nombre, descripción opcional, y opcionalmente
   selecciona ya las empresas que lo integran
3. **Ver Resumen**: abre el detalle del grupo — huella total, desglose
   por alcance y por empresa, con selector de año (o "Todos los
   períodos" para ver el histórico completo)
4. Desde el detalle puedes **agregar** o **quitar** empresas del grupo
   en cualquier momento
5. **Eliminar grupo**: las empresas no se eliminan, solo se desagrupan

### Errores comunes
- No hay forma de renombrar o editar la descripción de un grupo ya
  creado — si te equivocaste, elimina el grupo (es barato, solo tiene
  nombre/descripción/empresas) y crea uno nuevo
- Un grupo sin empresas asignadas, o sin datos en el año seleccionado,
  muestra el resumen en cero — no es un error

## Gestión de Documentos (base de conocimiento del Asistente ZIA)
### Para qué sirve
Subir documentos de una empresa (facturas, certificados, reportes
previos) para que el **Asistente ZIA** (el chat) pueda responder
preguntas sobre su contenido — no solo sobre los datos ya registrados
en el sistema.

### Paso a paso
1. Selecciona la empresa en el selector de contexto
2. **Administración → Documentos** (`/admin/company-documents`)
3. **Subir documento**: acepta PDF, TXT o Markdown (máx. 20 MB)
4. El documento pasa por 3 estados: **En cola** → **Procesando** →
   **Listo** (o **Error** si el archivo no se pudo leer, ej. un PDF
   escaneado sin texto real)
5. Una vez en estado **Listo**, pregúntale al Asistente ZIA algo que
   solo esté en ese documento — el chat lo va a encontrar y citar

### Qué vas a ver
Solo los documentos de la empresa seleccionada en el selector de
contexto — nunca de otra empresa.

### Errores comunes
- Un documento se queda en "Procesando" más de unos segundos: revisa
  que el archivo tenga texto real (no una imagen escaneada sin OCR)
- Borrar un documento es definitivo — el archivo y su contenido
  indexado para el Asistente se eliminan por completo, no hay papelera

## API Keys (credenciales de integraciones externas)
### Para qué sirve
Configurar o rotar las credenciales que usan las integraciones
externas de la plataforma — proveedores de IA del Asistente ZIA
(Mistral, Anthropic), observabilidad (Langfuse) y telemetría IoT
(ThingsBoard) — sin editar archivos de configuración del servidor ni
pedir un redeploy.

### Paso a paso
1. **Administración → API Keys** (`/admin/api-credentials`)
2. Verás una tarjeta por cada credencial gestionable: Mistral,
   Anthropic, Langfuse (pública/secreta) y ThingsBoard (host, usuario,
   contraseña)
3. Escribe el nuevo valor en el campo y presiona el botón de guardar
   (ícono de disco). Si la key ya estaba configurada, verás su valor
   actual enmascarado (solo los últimos 4 caracteres) antes de
   reemplazarla
4. El Asistente ZIA (Mistral/Anthropic/Langfuse) recoge el cambio
   automáticamente en menos de un minuto — no hace falta reiniciar
   nada. ThingsBoard se aplica de inmediato en la próxima sincronización

### Qué vas a ver
Un estado "Configurada" o "No configurada" por cada key, y quién la
actualizó por última vez. El valor completo de una key **nunca** se
muestra, ni siquiera a ti mismo después de guardarla — solo los
últimos 4 caracteres, para poder confirmar que guardaste la correcta
sin exponer el secreto completo en pantalla.

### Errores comunes
- **Quitar (ícono de basura)** borra el override guardado aquí — el
  sistema entonces vuelve a usar el valor que tenga configurado el
  servidor por su cuenta (si existe), no lo deja "vacío" a propósito
- Guardar aquí las credenciales de Mistral/Anthropic no reemplaza la
  necesidad de que existan cuentas activas y con saldo en esos
  proveedores — esto solo gestiona **cuál** credencial usa el sistema,
  no valida que funcione hasta que el Asistente intente usarla
