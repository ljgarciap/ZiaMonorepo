# Manual de Usuario — Técnico IoT

**Rol**: `iot_tech`
**Última actualización**: 2026-07-05
**Alcance**: ZIA Carbon Control

## ¿Qué puedes hacer con este rol?
Tu rol está enfocado exclusivamente en la gestión de dispositivos IoT:
alta, edición, baja, calibración y resolución de alertas. No tienes
acceso a los datos de emisión, reportes ni al dashboard de huella de
carbono — ese es un módulo separado que administran otros roles.

## Dispositivos IoT
### Para qué sirve
Dar de alta, editar, calibrar y dar de baja los dispositivos de medición de tu empresa.

### Paso a paso
1. **Dispositivos IoT** (`/iot/devices`) — este link aparece automáticamente en tu menú
2. **Registrar dispositivo**: asígnalo a la unidad operativa correspondiente
3. **Calibrar**: registra la calibración cuando corresponda
4. **Resolver alertas**: cuando un dispositivo reporta una alerta, la resuelves desde aquí

### Qué vas a ver
Solo los dispositivos de las empresas donde tienes acceso asignado.

## Telemetría en vivo
### Para qué sirve
Consultar las lecturas en tiempo real e históricas de los dispositivos.

### Paso a paso
1. **Telemetría en Vivo**

## Lo que no puedes hacer
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
