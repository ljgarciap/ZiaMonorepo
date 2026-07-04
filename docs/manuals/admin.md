# Manual de Usuario — Administrador de Empresa

**Rol**: `admin`
**Última actualización**: 2026-07-04
**Alcance**: ZIA Carbon Control

## ¿Qué puedes hacer con este rol?
Administras la cuenta de tu empresa dentro de ZIA: usuarios de tu
organización, unidades operativas, emisiones, reportes y observaciones
de auditoría. No tienes acceso a otras empresas ni a la configuración
global de la plataforma (esa la administra el Superadministrador de ZIA).

## Dashboard
### Para qué sirve
Ver el consolidado de huella de carbono de tu empresa completa (no solo
lo que tú capturaste — el consolidado de todos los usuarios).

### Paso a paso
1. Inicia sesión y entra a **Dashboard**

### Qué vas a ver
El consolidado de empresa, más el **Panel de Completitud Administrativa**
(qué unidades/usuarios tienen datos pendientes de cargar) — este panel
no lo ve el rol `user` ni `viewer`.

## Registro de emisiones
### Para qué sirve
Registrar y consultar los datos de emisión de tu empresa.

### Paso a paso
1. **Formulario de captura** o **Smart Intake** (cuestionario guiado)
2. Puedes editar el estado de calidad de un registro (validar, marcar
   con observación, resetear validación)
3. **Borrar un registro**: solo puedes hacerlo si el período correspondiente
   sigue **abierto**. Si el período ya está cerrado, el borrado requiere al
   Superadministrador — es una protección para no alterar datos de un
   período ya reportado sin dejar rastro

### Errores comunes
- Si intentas borrar un registro de un período cerrado, la acción será
  rechazada — no es un error del sistema, es la protección esperada

## Reportes
### Para qué sirve
Generar el PDF resumen de cumplimiento, el Excel detallado, el reporte
de avance y el reporte de dispositivos IoT.

### Paso a paso
1. Selecciona el período desde el Dashboard
2. **Generar reportes** → elige el tipo de reporte

### Qué vas a ver
El PDF resumen siempre refleja el total de la empresa, nunca datos
parciales de un solo usuario.

## Usuarios de tu empresa
### Para qué sirve
Invitar y administrar las cuentas de tu organización.

### Paso a paso
1. **Administración → Usuarios**
2. Puedes crear/editar usuarios, pero **solo con rol `user`** — no puedes
   asignar roles `admin`, `superadmin`, `auditor`, `iot_tech` ni `viewer`
   a nadie; eso lo hace el Superadministrador de ZIA
3. Solo ves usuarios de tu propia empresa (nunca de otra empresa, ni
   usuarios con rol igual o superior al tuyo)

### Errores comunes
- **No puedes eliminar ni bloquear/desbloquear cuentas** — si necesitas
  suspender a alguien, pídeselo al Superadministrador de ZIA

## Unidades operativas
### Para qué sirve
Organizar tu empresa en unidades (plantas, sedes, áreas) y asignar
usuarios a cada una.

### Paso a paso
1. **Administración → Unidades Operativas**
2. Crear/editar unidades, asignar y desasignar usuarios

## Períodos de reporte
### Para qué sirve
Consultar el estado de los períodos de tu empresa (abierto, cerrado, en revisión, archivado).

### Errores comunes
- Ves la pantalla en modo **solo lectura**: no aparecen botones de
  cerrar, reabrir, archivar o enviar a revisión — esas acciones son
  exclusivas del Superadministrador de ZIA. Si necesitas cerrar un
  período, coordina con soporte

## Datos de tu empresa
### Para qué sirve
Consultar la configuración de tu empresa (metodología, año base, factores habilitados).

### Paso a paso
1. **Administración → Mi Empresa** (vista de solo lectura)
2. **Sí puedes** habilitar/deshabilitar qué factores de emisión usa tu
   empresa del catálogo general
3. **No puedes** editar los datos generales de la empresa ni aprobar su
   metodología — ambas son responsabilidad del Superadministrador de ZIA

## Observaciones de auditoría
### Para qué sirve
Ver y moderar las observaciones/dictámenes que deja un Auditor externo
sobre los períodos de tu empresa.

### Paso a paso
1. **Auditoría → Observaciones**
2. Puedes **moderar o eliminar** observaciones existentes

### Errores comunes
- **No puedes crear** observaciones nuevas — solo el Auditor externo (o
  el Superadministrador) puede dejar un dictamen

## Bitácora de actividad
### Para qué sirve
Ver el registro de acciones realizadas por los usuarios de tu empresa.

### Paso a paso
1. **Administración → Bitácora**

### Qué vas a ver
Solo actividad de usuarios de tus propias empresas, nunca de otras.

## Dispositivos IoT (solo lectura)
### Para qué sirve
Consultar el estado de los dispositivos IoT de tu empresa: lecturas, alertas pendientes, última calibración.

### Paso a paso
1. **Administración → Dispositivos IoT**

### Errores comunes
- No puedes registrar, editar, calibrar ni eliminar dispositivos, ni
  resolver alertas — esas acciones son del Técnico IoT o el
  Superadministrador. Tu pantalla no muestra esos botones

## Lo que no puedes hacer
- No tienes acceso a los catálogos globales (tags maestros, factores
  maestros, sectores, unidades de medida, cuestionarios)
- No ves el panel ejecutivo de plataforma ni datos de otras empresas
