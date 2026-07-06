# Manual de Usuario — ZIA Carbon Control

**Última actualización**: 2026-07-06 (agrega Documentos / base de conocimiento del Asistente ZIA para Superadministrador y Administrador)
**Alcance**: ZIA Carbon Control
**Audiencia**: usuarios de la plataforma, por rol. La sección de
Superadministrador es de uso interno del equipo y no se distribuye a
empresas clientes.

Este documento consolida los 6 manuales por rol en una sola referencia.
Cada rol ve solo las secciones y pantallas que le corresponden — si
tienes dudas sobre qué rol tienes asignado, consulta con tu
Administrador de empresa o con el equipo de soporte.

## Índice
1. [Superadministrador](#superadministrador)
2. [Administrador de Empresa](#administrador-de-empresa)
3. [Usuario](#usuario)
4. [Técnico IoT](#técnico-iot)
5. [Auditor Externo](#auditor-externo)
6. [Viewer (Solo Lectura)](#viewer-solo-lectura)

---

## Superadministrador

**Rol**: `superadmin`

### ¿Qué puedes hacer con este rol?
Eres el único rol con visibilidad y control sobre toda la plataforma —
todas las empresas, todos los usuarios, y los catálogos globales que
usan todas las empresas (factores de emisión, tags, sectores, unidades).
El resto de los roles (`admin`, `user`, `iot_tech`, `auditor`, `viewer`)
solo ven su propia empresa.

### Panel ejecutivo de plataforma
**Para qué sirve**: Ver el estado consolidado de todas las empresas:
KPIs globales, top 5 empresas por huella, tendencia de 5 años.

**Paso a paso**:
1. Entra a **Administración → Plataforma** (`/admin/platform`)
2. El panel carga automáticamente el consolidado — no requiere seleccionar empresa

**Qué vas a ver**: Solo tú ves esta pantalla. Ningún otro rol tiene acceso, ni siquiera admin.

### Dashboard por empresa
**Para qué sirve**: Ver el consolidado de huella de carbono de una
empresa específica, igual que lo vería su Admin.

**Paso a paso**:
1. Selecciona la empresa desde el selector de contexto
2. Entra a **Dashboard**

**Qué vas a ver**: El consolidado completo (nunca scoped a "tus propias"
emisiones — eso solo le pasa al rol `user`). También ves el Panel de
Completitud Administrativa (por unidad/por usuario), que los roles
`user`, `auditor` y `viewer` no ven.

### Gestión de empresas
**Para qué sirve**: Dar de alta nuevas empresas clientes y administrar las existentes.

**Paso a paso**:
1. **Administración → Empresas** (`/admin/companies`)
2. Crear/editar/eliminar — eres el único rol que puede escribir aquí; el
   Admin de una empresa solo puede **leer** los datos de la suya (pantalla
   separada, de solo lectura)
3. **Aprobar metodología**: cuando una empresa define su metodología de
   cálculo (año base, enfoque de descarbonización), debes aprobarla
   explícitamente. Si el Admin cambia metodología/año base después,
   la aprobación se revoca automáticamente y debes volver a aprobarla

**Qué vas a ver**: Un listado de todas las empresas de la plataforma, no solo las tuyas.

### Gestión de usuarios
**Para qué sirve**: Crear, editar, bloquear y eliminar cuentas de cualquier rol, en cualquier empresa.

**Paso a paso**:
1. **Administración → Usuarios** (`/admin/users`)
2. Puedes asignar cualquier rol: `superadmin`, `admin`, `user`, `iot_tech`, `auditor`, `viewer`
3. **Bloquear cuenta** (sin eliminarla): útil para suspender acceso
   temporalmente. Un usuario bloqueado no puede iniciar sesión, y si ya
   tenía una sesión abierta, se le rechaza en la siguiente petición — no
   basta con esperar a que expire el token
4. **Eliminar cuenta**: acción exclusiva tuya — ni el Admin de empresa puede hacerlo

**Qué vas a ver**: Todos los usuarios de la plataforma, incluidos los eliminados (marcados como tal).

**Errores comunes**:
- Si entras "como admin" (auto-degradado, sin seleccionar una empresa
  específica) y no tienes tú mismo una fila de pertenencia a esa empresa,
  vas a ver listas vacías de usuarios/empresas en ese contexto — no es un
  bug, es que estás viendo la empresa desde el lente de "admin sin
  empresa asignada"

### Períodos de reporte
**Para qué sirve**: Administrar el ciclo de vida completo de un período
(crear, cerrar, reabrir, enviar a revisión, archivar).

**Paso a paso**:
1. **Administración → Períodos**
2. Eres el único rol que puede cerrar/reabrir/archivar un período — el
   Admin solo puede leerlo (ver nota abajo)

**Errores comunes**:
- La pantalla de gestión de períodos es visible para el Admin también,
  pero si el Admin intenta cerrar/reabrir/archivar, la acción falla — esas
  operaciones son exclusivas tuyas aunque la pantalla no lo distinga
  visualmente. Si un Admin te reporta un error al intentarlo, es
  comportamiento esperado, no un bug

### Emisiones — borrado en período cerrado
**Para qué sirve**: Corregir datos de un período ya cerrado, con auditoría completa vía bitácora.

**Paso a paso**:
1. Desde el histórico de emisiones de la empresa, localiza el registro
2. Solo tú puedes borrar un registro si su período está `closed` — el
   Admin puede borrar únicamente en períodos abiertos
3. Cualquier borrado queda registrado en la bitácora de actividad

### Catálogo de tags, factores de emisión y otros catálogos globales
**Para qué sirve**: Mantener los catálogos que usan todas las empresas:
tags, factores de emisión (con versionado), fórmulas, unidades, alcances, sectores.

**Paso a paso**:
1. **Administración → Tags / Factores / Sectores / Unidades** según corresponda
2. Los cambios a un factor de emisión quedan versionados — el motor de
   cálculo sigue usando el valor vigente, pero puedes consultar la línea
   de tiempo de cambios: al editar un factor, el botón **"Ver historial"**
   (esquina inferior izquierda del diálogo) muestra cada versión con
   quién la cambió, cuándo, y qué campos exactos cambiaron

**Errores comunes**:
- Habilitar/deshabilitar qué factores usa una empresa específica también
  lo puede hacer el Admin de esa empresa — no es exclusivo tuyo, a
  diferencia del catálogo maestro

### Dispositivos IoT
**Para qué sirve**: Gestión completa de dispositivos de cualquier empresa, sin restricción de pertenencia.

**Paso a paso**:
1. **Administración → Dispositivos IoT** (vista global de toda la plataforma) o
2. Navega directo a `/iot/devices` para gestionar los de una empresa puntual

**Errores comunes**:
- El link de "Dispositivos IoT" en el menú lateral solo aparece
  automáticamente para el rol `iot_tech`. Como superadmin tienes acceso
  a la pantalla, pero debes navegar directo a la URL — el link no
  aparece en tu menú

### Auditoría — asignación de auditores externos
**Para qué sirve**: Dar de alta a un Auditor externo y definir a qué
empresa y **período exacto** tiene acceso, con fecha de vencimiento.

**Paso a paso**:
1. **Administración → Asignaciones de Auditor** (`/admin/auditor-assignments`)
2. Crea la asignación: auditor + empresa + período + vencimiento
3. Esto es independiente de si el auditor ya tiene acceso general a la
   empresa (esa es otra capa: pertenencia a empresa con su propio
   vencimiento). Ambas capas deben estar vigentes para que el auditor
   vea y dictamine sobre ese período puntual

**Qué vas a ver**: También puedes crear observaciones de auditoría tú
mismo (igual que el Auditor), y moderar/eliminar cualquier observación
(igual que el Admin).

### Cuestionarios (Smart Intake)
**Para qué sirve**: Crear y versionar las plantillas de cuestionario que
las empresas usan para capturar datos.

**Paso a paso**:
1. **Administración → Cuestionarios**
2. Crear, versionar, publicar, archivar preguntas y plantillas

### Gestión de Grupos de Empresas
**Para qué sirve**: Agrupar empresas que comparten infraestructura (ej.
varios inquilinos de un mismo edificio) o pertenecen al mismo holding,
para ver su huella de carbono **consolidada** como si fueran una sola
unidad — sin mezclar sus datos individuales de forma permanente.

**Paso a paso**:
1. **Administración → Grupos de Empresas** (`/admin/company-groups`)
2. **Nuevo Grupo**: nombre, descripción opcional, y opcionalmente
   selecciona ya las empresas que lo integran
3. **Ver Resumen**: abre el detalle del grupo — huella total, desglose
   por alcance y por empresa, con selector de año (o "Todos los
   períodos" para ver el histórico completo)
4. Desde el detalle puedes **agregar** o **quitar** empresas del grupo
   en cualquier momento
5. **Eliminar grupo**: las empresas no se eliminan, solo se desagrupan

**Errores comunes**:
- No hay forma de renombrar o editar la descripción de un grupo ya
  creado — si te equivocaste, elimina el grupo (es barato, solo tiene
  nombre/descripción/empresas) y crea uno nuevo
- Un grupo sin empresas asignadas, o sin datos en el año seleccionado,
  muestra el resumen en cero — no es un error

### Gestión de Documentos (base de conocimiento del Asistente ZIA)
**Para qué sirve**: Subir documentos de una empresa (facturas,
certificados, reportes previos) para que el **Asistente ZIA** (el chat)
pueda responder preguntas sobre su contenido — no solo sobre los datos
ya registrados en el sistema.

**Paso a paso**:
1. Selecciona la empresa en el selector de contexto
2. **Administración → Documentos** (`/admin/company-documents`)
3. **Subir documento**: acepta PDF, TXT o Markdown (máx. 20 MB)
4. El documento pasa por 3 estados: **En cola** → **Procesando** →
   **Listo** (o **Error** si el archivo no se pudo leer, ej. un PDF
   escaneado sin texto real)
5. Una vez en estado **Listo**, pregúntale al Asistente ZIA algo que
   solo esté en ese documento — el chat lo va a encontrar y citar

**Qué vas a ver**: Solo los documentos de la empresa seleccionada en el
selector de contexto — nunca de otra empresa.

**Errores comunes**:
- Un documento se queda en "Procesando" más de unos segundos: revisa
  que el archivo tenga texto real (no una imagen escaneada sin OCR)
- Borrar un documento es definitivo — el archivo y su contenido
  indexado para el Asistente se eliminan por completo, no hay papelera

---

## Administrador de Empresa

**Rol**: `admin`

### ¿Qué puedes hacer con este rol?
Administras la cuenta de tu empresa dentro de ZIA: usuarios de tu
organización, unidades operativas, emisiones, reportes y observaciones
de auditoría. No tienes acceso a otras empresas ni a la configuración
global de la plataforma (esa la administra el Superadministrador de ZIA).

### Dashboard
**Para qué sirve**: Ver el consolidado de huella de carbono de tu
empresa completa (no solo lo que tú capturaste — el consolidado de
todos los usuarios).

**Paso a paso**:
1. Inicia sesión y entra a **Dashboard**

**Qué vas a ver**: El consolidado de empresa, más el **Panel de
Completitud Administrativa** (qué unidades/usuarios tienen datos
pendientes de cargar) — este panel no lo ve el rol `user` ni `viewer`.

### Registro de emisiones
**Para qué sirve**: Registrar y consultar los datos de emisión de tu empresa.

**Paso a paso**:
1. **Formulario de captura** o **Smart Intake** (cuestionario guiado)
2. Puedes editar el estado de calidad de un registro (validar, marcar
   con observación, resetear validación)
3. **Borrar un registro**: solo puedes hacerlo si el período
   correspondiente sigue **abierto**. Si el período ya está cerrado, el
   borrado requiere al Superadministrador — es una protección para no
   alterar datos de un período ya reportado sin dejar rastro

**Errores comunes**:
- Si intentas borrar un registro de un período cerrado, la acción será
  rechazada — no es un error del sistema, es la protección esperada

### Reportes
**Para qué sirve**: Generar el PDF resumen de cumplimiento, el Excel
detallado, el reporte de avance y el reporte de dispositivos IoT.

**Paso a paso**:
1. Selecciona el período desde el Dashboard
2. **Generar reportes** → elige el tipo de reporte

**Qué vas a ver**: El PDF resumen siempre refleja el total de la
empresa, nunca datos parciales de un solo usuario.

### Usuarios de tu empresa
**Para qué sirve**: Invitar y administrar las cuentas de tu organización.

**Paso a paso**:
1. **Administración → Usuarios**
2. Puedes crear/editar usuarios, pero **solo con rol `user`** — no puedes
   asignar roles `admin`, `superadmin`, `auditor`, `iot_tech` ni `viewer`
   a nadie; eso lo hace el Superadministrador de ZIA
3. Solo ves usuarios de tu propia empresa (nunca de otra empresa, ni
   usuarios con rol igual o superior al tuyo)

**Errores comunes**:
- **No puedes eliminar ni bloquear/desbloquear cuentas** — si necesitas
  suspender a alguien, pídeselo al Superadministrador de ZIA

### Unidades operativas
**Para qué sirve**: Organizar tu empresa en unidades (plantas, sedes,
áreas) y asignar usuarios a cada una.

**Paso a paso**:
1. **Administración → Unidades Operativas**
2. Crear/editar unidades, asignar y desasignar usuarios

### Períodos de reporte
**Para qué sirve**: Consultar el estado de los períodos de tu empresa
(abierto, cerrado, en revisión, archivado).

**Errores comunes**:
- Ves la pantalla en modo **solo lectura**: no aparecen botones de
  cerrar, reabrir, archivar o enviar a revisión — esas acciones son
  exclusivas del Superadministrador de ZIA. Si necesitas cerrar un
  período, coordina con soporte

### Datos de tu empresa
**Para qué sirve**: Consultar la configuración de tu empresa
(metodología, año base, factores habilitados).

**Paso a paso**:
1. **Administración → Mi Empresa** (vista de solo lectura)
2. **Sí puedes** habilitar/deshabilitar qué factores de emisión usa tu
   empresa del catálogo general
3. **No puedes** editar los datos generales de la empresa ni aprobar su
   metodología — ambas son responsabilidad del Superadministrador de ZIA

### Observaciones de auditoría
**Para qué sirve**: Ver y moderar las observaciones/dictámenes que deja
un Auditor externo sobre los períodos de tu empresa.

**Paso a paso**:
1. **Auditoría → Observaciones**
2. Puedes **moderar o eliminar** observaciones existentes

**Errores comunes**:
- **No puedes crear** observaciones nuevas — solo el Auditor externo (o
  el Superadministrador) puede dejar un dictamen

### Bitácora de actividad
**Para qué sirve**: Ver el registro de acciones realizadas por los
usuarios de tu empresa.

**Paso a paso**:
1. **Administración → Bitácora**

**Qué vas a ver**: Solo actividad de usuarios de tus propias empresas, nunca de otras.

### Dispositivos IoT (solo lectura)
**Para qué sirve**: Consultar el estado de los dispositivos IoT de tu
empresa: lecturas, alertas pendientes, última calibración.

**Paso a paso**:
1. **Administración → Dispositivos IoT**

**Errores comunes**:
- No puedes registrar, editar, calibrar ni eliminar dispositivos, ni
  resolver alertas — esas acciones son del Técnico IoT o el
  Superadministrador. Tu pantalla no muestra esos botones

### Documentos (base de conocimiento del Asistente ZIA)
**Para qué sirve**: Subir documentos de tu empresa (facturas,
certificados, reportes previos) para que el **Asistente ZIA** pueda
responder preguntas sobre su contenido.

**Paso a paso**:
1. **Administración → Documentos**
2. Sube el archivo (PDF, TXT o Markdown, máx. 20 MB) y espera a que
   pase a estado **Listo**
3. Pregúntale al chat algo que solo esté en ese documento

**Qué vas a ver**: Solo los documentos de tu propia empresa.

### Lo que no puedes hacer
- No tienes acceso a los catálogos globales (tags maestros, factores
  maestros, sectores, unidades de medida, cuestionarios)
- No ves el panel ejecutivo de plataforma ni datos de otras empresas

---

## Usuario

**Rol**: `user`

### ¿Qué puedes hacer con este rol?
Registras los datos de emisión que te corresponden y consultas tu
propia huella de carbono. No ves el consolidado de toda la empresa —
solo lo que tú mismo registraste. Si necesitas ver el total de la
empresa, contacta a tu Administrador.

### Dashboard — "Mi Huella"
**Para qué sirve**: Ver tu huella de carbono personal: solo los datos
que tú registraste, no el consolidado de la empresa.

**Paso a paso**:
1. Inicia sesión y entra a **Dashboard**
2. El título dirá "Mi Huella" (no "Huella Total de Empresa" — esa
   distinción es intencional)

**Qué vas a ver**: Solo tus propios registros y tu tendencia personal.
No verás el Panel de Completitud Administrativa (eso es solo para Administradores).

### Registro de emisiones
**Para qué sirve**: Cargar tus datos de actividad para que se calcule tu huella de carbono.

**Paso a paso**:
1. **Formulario de captura** o **Smart Intake** (cuestionario guiado)
2. El sistema asigna automáticamente tu unidad operativa según tu perfil
3. Adjunta evidencia de soporte (facturas, recibos, fotos) si aplica

**Errores comunes**:
- No puedes registrar datos en un período que ya está **cerrado**
- No puedes borrar, validar ni marcar observaciones sobre registros — eso lo hace tu Administrador
- Solo puedes borrar la evidencia que tú mismo subiste, no la de otros usuarios

### Reportes
**Para qué sirve**: Generar tu reporte de período (PDF, Excel, avance).

**Paso a paso**:
1. Selecciona el período desde el Dashboard
2. **Generar reportes**

**Qué vas a ver**: **Importante**: el PDF resumen de cumplimiento
siempre muestra el total de la empresa (no solo tus datos), aunque tu
Dashboard interactivo te muestre solo lo tuyo. Es intencional — el
reporte oficial de cumplimiento nunca se recorta por usuario.

### Telemetría en vivo
**Para qué sirve**: Consultar datos de sensores/dispositivos IoT de tu empresa en tiempo real.

**Paso a paso**:
1. **Telemetría en Vivo**

### Simulador de escenarios
**Para qué sirve**: Probar escenarios hipotéticos de reducción de emisiones con ayuda de IA.

**Paso a paso**:
1. **Simulador**

### Lo que no puedes hacer
- No ves el consolidado de la empresa, solo lo tuyo
- No administras usuarios, unidades ni períodos
- No ves ni gestionas dispositivos IoT (solo la telemetría en vivo)
- No tienes acceso a la bitácora de auditoría ni a observaciones de auditoría
- No tienes acceso a ninguna pantalla de "Administración"

---

## Técnico IoT

**Rol**: `iot_tech`

### ¿Qué puedes hacer con este rol?
Tu rol está enfocado exclusivamente en la gestión de dispositivos IoT:
alta, edición, baja, calibración y resolución de alertas. No tienes
acceso a los datos de emisión, reportes ni al dashboard de huella de
carbono — ese es un módulo separado que administran otros roles.

### Dispositivos IoT
**Para qué sirve**: Dar de alta, editar, calibrar y dar de baja los
dispositivos de medición de tu empresa.

**Paso a paso**:
1. **Dispositivos IoT** (`/iot/devices`) — este link aparece automáticamente en tu menú
2. **Registrar dispositivo**: asígnalo a la unidad operativa correspondiente
3. **Calibrar**: registra la calibración cuando corresponda
4. **Resolver alertas**: cuando un dispositivo reporta una alerta, la resuelves desde aquí

**Qué vas a ver**: Solo los dispositivos de las empresas donde tienes acceso asignado.

### Telemetría en vivo
**Para qué sirve**: Consultar las lecturas en tiempo real e históricas de los dispositivos.

**Paso a paso**:
1. **Telemetría en Vivo**

### Lo que no puedes hacer
- **No tienes acceso al Dashboard** de huella de carbono — el link
  aparece en tu menú (es el mismo para todos los roles), pero al entrar
  verás un aviso explícito de que tu rol no tiene acceso, en vez de
  datos en cero. Esto es esperado: tu rol no participa del cálculo ni
  consulta de emisiones
- **No puedes** registrar, ver ni exportar datos de emisiones ni reportes
- **No tienes acceso** al Simulador de IA, Huella de Carbono/Smart
  Intake, Historial, ni Observaciones de Auditoría — tu menú lateral
  solo muestra Dashboard, Zia Live y Dispositivos IoT
- **No tienes acceso** a la bitácora de auditoría ni a ninguna pantalla
  de "Administración"

---

## Auditor Externo

**Rol**: `auditor`

### ¿Qué puedes hacer con este rol?
Revisas los datos de emisión de la empresa para la que fuiste asignado
y dejas tu dictamen (conforme, no conforme, u observado) sobre un
período específico. Tu acceso es **temporal y acotado**: solo ves la(s)
empresa(s) y período(s) para los que el Superadministrador te asignó
explícitamente, y ese acceso vence en la fecha configurada.

### Antes de empezar: entiende tu acceso
Tu acceso tiene dos niveles, y ambos deben estar vigentes:
1. **Acceso a la empresa**: te da entrada al contexto general de la empresa
2. **Asignación a un período específico**: te da acceso a los datos y al dictamen de ESE período en particular

Si tu acceso a un período venció, dejarás de verlo aunque tu acceso
general a la empresa siga activo. Si esto pasa y necesitas seguir
revisando ese período, pide al equipo de la empresa o al
Superadministrador que renueve tu asignación.

### Dashboard
**Para qué sirve**: Ver el consolidado de huella de carbono de la
empresa que estás auditando.

**Paso a paso**:
1. Inicia sesión — si tienes acceso a más de una empresa, selecciona la que vas a auditar
2. **Dashboard**

**Qué vas a ver**: El consolidado completo de la empresa (no solo un
usuario). La tendencia histórica que ves está acotada a los períodos
para los que tienes asignación activa — no al histórico completo de la
empresa.

### Emisiones — solo lectura
**Para qué sirve**: Revisar el histórico de emisiones y la evidencia de
soporte cargada por la empresa.

**Paso a paso**:
1. **Historial** (visible en tu menú lateral)

**Errores comunes**:
- No puedes crear, editar ni borrar registros de emisión — tu rol es de auditoría, no de captura
- No puedes subir evidencia nueva, solo consultar la existente

### Reportes
**Para qué sirve**: Generar los reportes oficiales (PDF, Excel, avance) de la empresa que auditas.

**Paso a paso**:
1. Selecciona el período
2. **Generar reportes**

### Observaciones de auditoría — tu función principal
**Para qué sirve**: Dejar tu dictamen formal sobre un período: conforme,
no conforme, u observado, con el detalle correspondiente.

**Paso a paso**:
1. **Auditoría → Observaciones**
2. **Crear observación**: selecciona el período (debe ser uno donde
   tengas asignación vigente), escribe el detalle y el dictamen
3. Una vez creada, no puedes editarla ni eliminarla — si necesitas
   corregirla, contacta al Administrador de la empresa o al
   Superadministrador para que la moderen

**Qué vas a ver**: Solo las observaciones de las empresas/períodos donde tienes acceso.

### Bitácora de actividad
**Para qué sirve**: Consultar el registro de acciones de la empresa que auditas.

**Paso a paso**:
1. Esta sección puede requerir navegación directa si no aparece en tu
   menú — tu acceso está condicionado a tener una asignación vigente
   para esa empresa

### Lo que no puedes hacer
- No registras ni editas emisiones
- No administras usuarios, unidades operativas ni períodos
- No editas ni eliminas tus propias observaciones una vez creadas
- No tienes acceso a dispositivos IoT, catálogos globales ni al Simulador de IA

---

## Viewer (Solo Lectura)

**Rol**: `viewer`

### ¿Qué puedes hacer con este rol?
Consultas la información de tu empresa en modo solo lectura: dashboard,
histórico de emisiones, reportes y telemetría. No registras datos ni
administras nada — este rol está pensado para quien necesita visibilidad
sin capacidad de edición (por ejemplo, un stakeholder interno o un
consultor externo sin función de auditoría formal).

### Dashboard
**Para qué sirve**: Ver el consolidado de huella de carbono de tu empresa.

**Paso a paso**:
1. Inicia sesión y entra a **Dashboard**

**Qué vas a ver**: El consolidado completo de la empresa (no un recorte
personal). No verás el Panel de Completitud Administrativa — eso es
solo para Administradores.

### Histórico de emisiones
**Para qué sirve**: Consultar los registros de emisión cargados por la empresa.

**Paso a paso**:
1. **Histórico** (solo lectura — no verás el botón de "Subir soporte",
   ya que tu rol no carga evidencia)

### Reportes
**Para qué sirve**: Generar y descargar los reportes de la empresa (PDF, Excel, avance).

**Paso a paso**:
1. Selecciona el período desde el Dashboard
2. **Generar reportes**

### Telemetría en vivo
**Para qué sirve**: Consultar lecturas de dispositivos IoT en tiempo real.

**Paso a paso**:
1. **Telemetría en Vivo**

### Lo que no puedes hacer
- No registras, editas ni borras emisiones
- No subes evidencia (aunque el botón aparezca en pantalla)
- No administras usuarios, unidades, períodos ni dispositivos IoT
- No tienes acceso a la bitácora de auditoría ni a observaciones de auditoría
- No tienes acceso al Simulador de IA (aunque el link aparezca en el
  menú, no está disponible para tu rol)
- No tienes acceso a ninguna pantalla de "Administración"
