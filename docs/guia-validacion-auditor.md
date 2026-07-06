# Guía de Validación Funcional para Auditor — Plataforma ZIA

**Fecha**: 2026-07-06
**Propósito**: Este documento es una guía práctica, paso a paso, para que
un auditor externo **valide con sus propias manos** que la plataforma
funciona correctamente — no es el documento de cumplimiento contractual
(ver `docs/roadmap-auditor-walkthrough.md` para eso) ni el manual de
usuario (ver `docs/manuals/manual-usuario-consolidado.md`). Aquí el
objetivo es: entrar, hacer clic, y confirmar que lo que el sistema
calcula y muestra es correcto y consistente.

Incluye dos anexos técnicos: el **Anexo A** explica cómo funciona el
motor de cálculo de emisiones por dentro, con un ejemplo numérico real
que el auditor puede reproducir a mano y contrastar contra el sistema;
el **Anexo B** explica el ciclo de vida de los datos — cómo se crean
empresas y períodos nuevos, qué pasa al agregar datos a un período que
ya existe, y cómo se corrige un dato mal ingresado.

---

## Antes de empezar

**Entorno**: instancia local/demo en `http://localhost:8080` (frontend)
y `http://localhost:8000` (API). Si el auditor valida sobre un entorno
distinto, ajustar las URLs de los pasos siguientes.

**Credenciales de prueba** (entorno de desarrollo/demo — cambiar en
producción):
| Rol | Usuario | Contraseña |
|---|---|---|
| Superadministrador | `superadmin@zia.com` | `password` |

El resto de los usuarios (admin, user, auditor, iot_tech, viewer) se
crean durante el walkthrough, o pueden solicitarse ya creados si el
entorno de validación es compartido.

**Datos reales ya cargados (alternativa a crear datos desde cero)**:
además de crear tu propia empresa de prueba (paso 1.2), el sistema ya
tiene empresas con historial real de varios períodos, por si prefieres
validar cálculos y reportes sobre datos existentes en vez de datos que
tú mismo generes en el momento:

| Empresa | Períodos | Emisiones registradas |
|---|---|---|
| Bucarretes S.A.S. | 4 | 18 |
| EcoTech Solutions S.A.S. | 2 | 13 |
| Industrias Verdes Ltda. | 5 | 40 |
| ECONOVA | 2 | 13 |

Como superadmin, selecciona cualquiera de estas empresas desde el
selector de contexto y entra directo a su Dashboard, Histórico de
Emisiones o Reportes — el superadmin ve el Dashboard de cualquier
empresa igual que lo vería su propio Admin, sin necesitar crear un
usuario nuevo para eso.

**Validación cruzada sugerida**: toma cualquier registro del histórico
de emisiones de una de estas empresas (`GET
/api/companies/<ID>/emissions/history`) y reproduce el cálculo a mano
con la fórmula del **Anexo A**, usando el factor de emisión y la
cantidad de ese registro — debe coincidir exactamente con
`calculated_co2e`. De hecho, el ejemplo trabajado del Anexo A (50
galones de "Gasolina E10", empresa EcoTech Solutions, período 2024) es
precisamente uno de estos registros reales, no un cálculo teórico.

---

## Parte 1 — Walkthrough funcional (qué hacer, qué debe pasar)

### 1.1 Acceso y estructura de roles
1. Entra a `http://localhost:8080` con el usuario superadmin.
2. **Qué debe pasar**: acceso inmediato al panel ejecutivo de
   plataforma, sin selector de empresa (el superadmin ve el
   consolidado de todas las empresas).
3. Abre **Administración → Usuarios** — confirma que existen 6 roles
   distintos (superadmin, admin, user, iot_tech, auditor, viewer), cada
   uno con una descripción de qué puede hacer.

### 1.2 Crear una empresa y su primer usuario
1. **Administración → Empresas → Nueva Empresa**. Completa nombre,
   NIT, sector, año base y metodología (GHG Protocol / ISO 14064).
2. **Qué debe pasar**: la empresa aparece de inmediato en el listado;
   el año base y la metodología elegidos quedan pendientes de
   "aprobación de metodología" por el superadmin (un botón separado).
3. **Administración → Usuarios → Invitar Usuario**: crea un usuario
   rol `admin` asociado a la empresa recién creada.
4. Cierra sesión, entra con ese usuario `admin`.
5. **Qué debe pasar**: el admin solo ve **su** empresa — no aparece
   ningún dato de otras empresas en ninguna pantalla.

### 1.3 Registrar una emisión y confirmar el cálculo
1. Como `admin`, ve a **Huella de Carbono → Registrar Emisión**.
2. Elige un factor de emisión conocido (ej. un combustible) e ingresa
   una cantidad.
3. **Qué debe pasar**: el sistema devuelve inmediatamente el
   `calculated_co2e` (toneladas de CO2 equivalente) — ver **Anexo A**
   para la fórmula exacta y un ejemplo que puedes reproducir a mano
   para confirmar que el número es correcto, no solo "que aparece algo".

### 1.4 Dashboard y reportes
1. Ve a **Dashboard** — confirma que el total de tCO2e coincide con la
   suma de las emisiones registradas, desglosado por Alcance 1/2/3.
2. **Reportes → Generar PDF** del período — confirma que el PDF
   descargado muestra el mismo total que el Dashboard, con desglose por
   alcance y comparación multi-año.
3. **Qué debe pasar**: los tres números (registro individual, Dashboard,
   PDF) deben coincidir exactamente — son la misma consulta a la base
   de datos, presentada de tres formas distintas.

### 1.5 Aislamiento entre empresas (multitenancy)
1. Crea una segunda empresa y un segundo usuario `admin` asociado solo
   a ella.
2. Con las credenciales del segundo admin, intenta acceder a datos de
   la primera empresa cambiando el ID en la URL, por ejemplo:
   ```
   GET http://localhost:8000/api/companies/<ID_EMPRESA_1>/emissions/history
   Authorization: Bearer <token_del_segundo_admin>
   ```
3. **Qué debe pasar**: `403 {"error": "Sin permiso."}` — nunca datos de
   la otra empresa. Repetir contra `/dashboard/summary`,
   `/reports/periods/{period}/pdf` y `/companies/{company}/units` con el
   mismo resultado esperado.

### 1.6 Versionado de factores de emisión
1. Como superadmin, **Administración → Factores** → edita un factor
   existente (ej. cambia su valor de CO2).
2. Abre **"Ver historial"** sobre ese mismo factor.
3. **Qué debe pasar**: aparece una entrada nueva mostrando el valor
   anterior y el nuevo, con quién hizo el cambio y cuándo — el
   versionado es automático, no requiere ninguna acción manual adicional.

### 1.7 Auditoría — tu propio acceso como rol Auditor
1. Como superadmin, **Administración → Asignaciones de Auditor**: crea
   una asignación para un usuario rol `auditor`, sobre la empresa y el
   período que quieres validar, con fecha de vencimiento.
2. Entra con ese usuario auditor.
3. **Qué debe pasar**: acceso de solo lectura, limitado exactamente al
   período asignado — no a todos los períodos de la empresa. Puedes
   dejar una **observación de auditoría** sobre un dato específico.
4. Prueba acceder a un período **no** asignado — debe devolver 403.

### 1.8 Asistente ZIA (chat) y Documentos (RAG)
1. Abre el ícono de chat flotante y pregunta algo sobre los datos ya
   registrados de la empresa (ej. "¿cuál es mi huella total del último
   período?").
2. **Qué debe pasar**: el asistente responde con el dato real (verifica
   contra el Dashboard), no un valor inventado.
3. **Administración → Documentos**: sube un archivo de texto con un
   dato inventado y específico (ej. "el generador de respaldo consumió
   85 galones de diésel en enero"). Espera a que pase a estado
   **Listo**.
4. Pregúntale al chat algo que **solo** esté en ese documento.
5. **Qué debe pasar**: el asistente encuentra y cita el dato exacto del
   documento — confirma que la búsqueda semántica (RAG) funciona con
   contenido real, no solo con datos ya estructurados en la base de datos.

### 1.9 Documentación técnica de la API
1. Abre `http://localhost:8000/docs/api`.
2. **Qué debe pasar**: documentación OpenAPI/Swagger completa,
   navegable, con cada endpoint marcado según requiere autenticación
   Bearer o no.

---

## Parte 2 — Qué NO se puede validar en este entorno

- **ThingsBoard (IoT) en modo real**: el entorno de validación corre
  con `THINGSBOARD_MOCK=true` — los dispositivos IoT y sus lecturas son
  simulados. El código de integración real existe y está documentado en
  `docs/architecture/thingsboard-integration.md`, pero conectarlo a una
  instancia real de ThingsBoard requiere credenciales que no forman
  parte de este entorno de prueba.
- **Estructura de repositorios/CI-CD (Coolify)**: este es un aspecto de
  infraestructura de despliegue, no validable desde el uso de la
  aplicación en sí.

---

## Anexo A — Motor de Cálculo de Emisiones GHG

Este anexo explica, con precisión técnica, cómo el sistema convierte un
dato de actividad (ej. "50 galones de gasolina") en toneladas de CO2
equivalente (`calculated_co2e`), para que puedas verificar la fórmula
de forma independiente — no solo confiar en que "el sistema dio un
número".

### A.1 — La fórmula estándar

Cada **factor de emisión** en el catálogo (`Administración → Factores`)
almacena, por separado, cuántos kg de cada gas se emiten por unidad de
actividad:

| Campo | Significado |
|---|---|
| `factor_co2` | kg de CO2 por unidad de actividad |
| `factor_ch4` | kg de CH4 (metano) por unidad |
| `factor_n2o` | kg de N2O (óxido nitroso) por unidad |
| `factor_nf3` | kg de NF3 por unidad (solo electrónica) |
| `factor_sf6` | kg de SF6 por unidad (solo equipos eléctricos) |

El sistema **no** guarda estos valores ya combinados con su potencial
de calentamiento global (GWP) — el GWP se aplica en el momento del
cálculo, gas por gas, usando constantes fijas (IPCC AR6 / GHG Protocol,
agosto 2024):

| Gas | GWP (100 años) |
|---|---|
| CO2 | 1.0 |
| CH4 (fósil) | 29.8 |
| N2O | 273.0 |
| NF3 | 17 400.0 |
| SF6 | 25 200.0 |

**Fórmula por gas** (el resultado de cada línea está en toneladas,
porque el factor está en kg y se divide entre 1000):

```
emisiones_CO2 = (cantidad × factor_co2) / 1000
emisiones_CH4 = (cantidad × factor_ch4) / 1000
emisiones_N2O = (cantidad × factor_n2o) / 1000
... (igual para NF3, SF6)

co2e_CO2 = emisiones_CO2 × 1.0
co2e_CH4 = emisiones_CH4 × 29.8
co2e_N2O = emisiones_N2O × 273.0
... (igual para NF3, SF6)

calculated_co2e = co2e_CO2 + co2e_CH4 + co2e_N2O + co2e_NF3 + co2e_SF6
```

### A.2 — Ejemplo real, verificable

Este es un registro **real** ya almacenado en el sistema (empresa
"EcoTech Solutions S.A.S.", período 2024) que puedes pedir que te
muestren o volver a crear tú mismo para confirmar la fórmula a mano:

**Factor**: "Gasolina E10 (Mezcla comercial)"
- `factor_co2` = 7.618000
- `factor_ch4` = 0.000263
- `factor_n2o` = 0.000026

**Cantidad registrada**: 50 galones

**Cálculo esperado (hazlo con calculadora)**:
```
emisiones_CO2 = (50 × 7.618)   / 1000 = 0.3809
emisiones_CH4 = (50 × 0.000263) / 1000 = 0.00001315
emisiones_N2O = (50 × 0.000026) / 1000 = 0.0000013

co2e_CO2 = 0.3809      × 1.0   = 0.3809
co2e_CH4 = 0.00001315  × 29.8  = 0.00039187
co2e_N2O = 0.0000013   × 273.0 = 0.0003549

calculated_co2e = 0.3809 + 0.00039187 + 0.0003549 = 0.38164677 tCO2e
```

**Lo que debe mostrar el sistema para ese mismo registro**:
`calculated_co2e = 0.38164677` — exactamente el resultado de la cuenta
a mano. Si pides ver ese registro específico vía
`GET /api/companies/2/emissions/history`, el campo `calculated_co2e`
debe coincidir dígito por dígito con lo calculado arriba.

### A.3 — Caso especial: electricidad en Colombia (factor FECOC)

Para el Alcance 2 (electricidad), si el factor se llama algo que
contenga "Colombia", el sistema **reemplaza automáticamente** el
`factor_co2` guardado por el valor oficial FECOC (Factor de Emisión de
CO2 de la red eléctrica colombiana, fuente XM/UPME) correspondiente al
**año exacto del período** que se está calculando — no usa un valor
fijo genérico. Valores usados actualmente: 2024 → 0.1083 kgCO2e/kWh,
2020-2023 → 0.1260, 2019 → 0.1320. Si no hay valor FECOC para el año
solicitado, usa el valor que tenga guardado el factor por defecto.

### A.4 — Fórmulas dinámicas (para factores especiales)

Algunos factores (ej. fugas de refrigerantes, combustión móvil) no
usan la fórmula estándar por gas — tienen una **fórmula personalizada**
asociada (`Administración → Fórmulas`), evaluada con variables como
`activity_data`, `factor_co2`, `gwp_ch4`, etc. Ejemplos reales del
catálogo:

```
Combustión Estándar:   activity_data * factor_total_co2e / 1000
Fugas de Refrigerante: activity_data * (factor_total_co2e / 1000)
```

**Nota de transparencia técnica**: el requerimiento original pedía un
motor "tipo mathjs" (librería de JavaScript). Como el backend es
Laravel/PHP, se implementó con `Symfony ExpressionLanguage` (el
evaluador de expresiones oficial de Symfony), extendido con funciones
propias (`SQRT`, `POWER`, `AVERAGE`, `STDEV`). Es una adaptación
funcionalmente equivalente, no un port literal de mathjs — vale la pena
que quede explícito para que no se lea como "se implementó mathjs" en
la documentación de auditoría.

### A.5 — Incertidumbre

El sistema calcula un porcentaje de incertidumbre (`uncertainty_result`)
combinando dos fuentes, según la metodología estándar IPCC de "raíz de
la suma de cuadrados":

1. **Incertidumbre del dato de actividad**: si se registran varios
   valores (ej. consumo mes a mes en vez de un solo total anual), el
   sistema calcula la desviación estándar de esos valores y aplica una
   corrección estadística (tabla t de Student) según cuántos datos hay.
   Con un solo dato, esta componente es cero.
2. **Incertidumbre del factor de emisión**: para CO2, viene del campo
   `uncertainty_upper` del factor (configurable por el superadmin). Para
   CH4/N2O/NF3/SF6, son valores fijos estándar (110%, 11%, 11%, 11%
   respectivamente) — no configurables por factor individual.

**Nota de transparencia técnica**: el campo `uncertainty_lower` (límite
inferior) y `uncertainty_distribution` (tipo de distribución) existen
en el formulario de edición de factores, pero **no tienen efecto** en
el cálculo actual — solo se usa `uncertainty_upper`, tratado como un
valor de incertidumbre simétrico. No es un error visible para el
usuario, pero es correcto que quede documentado para el auditor.

### A.6 — Emisiones biogénicas, remociones y balance neto

- **CO2 biogénico** (factores marcados `is_biogenic`, ej. combustión de
  biomasa): el CO2 se resta del total (`calculated_co2e`) y se reporta
  aparte en `biogenic_co2e` — así lo exige el GHG Protocol. El CH4/N2O
  de esa misma fuente **sí** se queda dentro del total.
- **Remociones/sumideros** (factores marcados `is_removal`, ej.
  reforestación): el resultado se guarda como **negativo** en el mismo
  campo `calculated_co2e`.
- **Balance neto** (en el reporte PDF) = suma de todas las emisiones
  positivas − valor absoluto de las remociones (negativas). Se calcula
  exactamente así, sin ajustes adicionales.
- **Nota de transparencia técnica**: los campos `carbon_stored`
  (carbono almacenado) y `avoided_emissions` (emisiones evitadas)
  existen en la base de datos y aparecen en el reporte PDF como líneas
  informativas, pero **ningún flujo actual del sistema los calcula o
  llena** — hoy siempre aparecen en cero. No afectan el balance neto
  porque nunca tienen valor, pero es importante que el auditor sepa que
  son campos de la estructura de datos, no resultados de un cálculo
  activo todavía.

### A.7 — Alcance 2: método "location-based" vs "market-based"

El sistema permite guardar una etiqueta `scope2_method` en cada
emisión de electricidad (pensada para distinguir el método de cálculo
GHG Protocol de "basado en la red" vs. "basado en contratos de
energía"). **Hallazgo técnico**: hoy esta etiqueta se guarda tal cual
la envíe quien registra el dato, sin validación de valores permitidos,
y **no cambia el cálculo** — el resultado numérico es el mismo sin
importar qué método se seleccione. Los factores FECOC usados (ver A.3)
son todos "location-based" por su origen (red nacional interconectada);
no existe hoy una tabla de factores "market-based" (específicos de
proveedor/certificados de energía renovable) implementada.

### A.8 — Una ruta de cálculo distinta para datos de IoT

Cuando una emisión se genera automáticamente desde un dispositivo IoT
(telemetría), el sistema usa una fórmula **más simple** que no pasa por
el motor descrito arriba:

```
calculated_co2e = cantidad_total × factor_total_co2e   (redondeado a 6 decimales)
```

Esta ruta no aplica el desglose por gas, no evalúa fórmulas
personalizadas, no calcula incertidumbre, y **no divide entre 1000**
como sí hace la ruta estándar — depende de que `factor_total_co2e` para
factores usados en dispositivos IoT esté expresado en la escala
correcta (toneladas por unidad, no kilogramos). Vale la pena que el
auditor sepa que existen dos motores de cálculo distintos en el sistema
hoy, uno para captura manual y otro para IoT, y que no son
intercambiables sin ajustar la escala del factor.

### A.9 — Redondeo

El sistema **no aplica un redondeo explícito** a `calculated_co2e` al
guardarlo desde el registro manual — el valor se guarda con la
precisión completa que entrega el cálculo, y la base de datos
(PostgreSQL, columnas `decimal(15,8)`) trunca a 8 decimales. Los
reportes de comparación multi-año sí redondean a 4 decimales al
mostrarse, pero eso es solo presentación, no afecta el dato almacenado.

---

## Resumen de hallazgos técnicos para el auditor (motor de cálculo)

Ninguno de estos representa un error funcional visible al usuario — la
plataforma calcula y reporta correctamente hoy — pero son precisiones
técnicas que un auditor riguroso debe conocer:

1. El GWP se aplica en código con constantes AR6, no viene pre-cargado
   en los factores.
2. "Motor tipo mathjs" se implementó como Symfony ExpressionLanguage
   (ver A.4) — arquitectura distinta, función equivalente.
3. Solo `uncertainty_upper` afecta el cálculo de incertidumbre;
   `uncertainty_lower` y `uncertainty_distribution` se guardan pero no
   se usan (ver A.5).
4. `carbon_stored` y `avoided_emissions` existen en el esquema pero
   ningún flujo los calcula hoy — siempre están en cero (ver A.6).
5. `scope2_method` (location vs. market-based) no tiene efecto en el
   cálculo — es una etiqueta sin validación (ver A.7).
6. Existen dos motores de cálculo distintos (captura manual vs. IoT),
   con fórmulas no directamente equivalentes (ver A.8).
7. No hay conversión de unidades de medida en el motor — depende de que
   la cantidad ingresada ya esté en la unidad exacta del factor.

## Referencias técnicas (para quien quiera profundizar en el código)

- `backend/app/Services/CarbonFootprintService.php` — motor principal
- `backend/app/Services/FormulaEvaluationService.php` — fórmulas dinámicas
- `backend/app/Services/IoTCarbonIngestionService.php` — ruta de cálculo IoT
- `backend/app/Http/Controllers/Api/CarbonEmissionController.php` — punto de entrada de la API
- `backend/app/Http/Controllers/Api/ReportController.php` — balance neto y reportes
- `backend/tests/Unit/CarbonFootprintServiceTest.php` y
  `backend/tests/Unit/FormulaEvaluationServiceTest.php` — tests que
  confirman el comportamiento descrito en este anexo

---

## Anexo B — Ciclo de vida de datos: empresas, períodos y correcciones

Esta es la columna vertebral operativa del sistema — cómo entra un dato
nuevo, qué pasa cuando ya existe un período con datos, y cómo se
corrige un error. El motor de cálculo (Anexo A) no depende en absoluto
de qué tan nueva sea la empresa o el período: solo necesita un factor
de emisión válido. Lo que sí importa es el **estado** del período.

### B.1 — Empresa y período nuevos

Crear una empresa **no crea automáticamente un período** — son dos
pasos separados:

1. `Administración → Empresas → Nueva Empresa` (nombre, sector, año
   base, metodología)
2. Desde esa empresa, crear un **período** (año + estado). Sin esto,
   no se puede registrar ninguna emisión — toda emisión se ata a un
   `period_id` que debe existir de antemano.

Una vez existe el período, el motor de cálculo funciona exactamente
igual que en una empresa con años de historial — no hay ninguna
diferencia de comportamiento por antigüedad.

### B.2 — Añadir datos a un período que ya existe

- **Período abierto/activo** (`open` / `active`): sin límite. Cada
  emisión nueva es simplemente otra fila — el Dashboard y los reportes
  suman todo lo que exista para ese período automáticamente, no hay
  tope ni bloqueo por cantidad de registros.
- **Período cerrado, en revisión, o archivado** (`closed` /
  `in_review` / `archived`): el sistema **rechaza** el registro nuevo
  con `422`.
- Un superadmin puede **reabrir** un período cerrado
  (`Administración → Períodos → Reabrir`) para volver a agregar datos.

**Hallazgo corregido 2026-07-06**: hasta esta fecha, la validación de
"período no editable" solo revisaba explícitamente el estado `closed`
— los otros dos estados de solo-lectura del ciclo de vida
(`in_review`, `archived`) no estaban bloqueados en ese mismo punto del
código, aunque la UI normal no permitía llegar a esa situación. Se
corrigió cambiando de una lista negra ("bloquea solo lo que reconozcas
explícitamente") a una lista blanca ("permite escribir solo en los
estados abiertos; cualquier otro estado queda bloqueado por diseño,
incluso uno que se agregue en el futuro y nadie recuerde sumar a la
lista de bloqueo"). Verificado con tests que reproducen los 3 estados
no-abiertos contra el endpoint real.

### B.3 — No existe "editar" una emisión — solo crear y borrar

El sistema no tiene una operación de actualización sobre un registro
de emisión ya guardado. Si un dato se ingresó mal, el patrón de
corrección es siempre el mismo: **borrar el registro** y **volver a
registrarlo correcto** — nunca un "edit" silencioso sobre el valor
existente.

- Un `admin` solo puede borrar emisiones de un período **abierto**.
- Un `superadmin` puede borrar incluso en un período **cerrado**, y esa
  acción queda registrada en la bitácora de actividad (auditable).

Esto es una decisión intencional de trazabilidad, no una limitación
accidental: nunca hay un cambio de valor sin dejar rastro de que el
dato anterior existió y fue eliminado.

### Cómo validarlo

1. Crea una empresa nueva **sin** crear un período todavía — intenta
   registrar una emisión: debe fallar (no existe período al cual atarla).
2. Crea el período y registra una emisión — confirma que aparece en el
   Dashboard de inmediato.
3. **Administración → Períodos → Cerrar** ese período, e intenta
   registrar otra emisión — debe devolver `422`.
4. **Reabre** el período — la misma operación ahora debe funcionar.
5. Confirma que no existe ningún botón "editar" sobre una emisión ya
   guardada en el histórico de emisiones — solo "eliminar".
