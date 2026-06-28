<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\EmissionFactor;

class SectorQuestionnaireRuleSeeder extends Seeder
{
    public function run(): void
    {
        $factor = fn($name) => EmissionFactor::where('name', 'like', "%{$name}%")->value('id');

        $rules = [
            // ─── SECTOR: servicios (Comercio y Servicios / Real Estate / ECONOVA) ────
            'servicios' => [
                [
                    'factor_name'        => 'FE Colombia (Interconectado)',
                    'questionnaire_label'=> 'Energía activa consumida de la red (kWh)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'kWh',
                    'is_required'        => true,
                    'display_order'      => 1,
                    'help_text'          => 'Valor del medidor de energía eléctrica del período.',
                ],
                [
                    'factor_name'        => 'Gas Natural (Fijo)',
                    'questionnaire_label'=> 'Consumo de gas natural (m³)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'm3',
                    'is_required'        => false,
                    'display_order'      => 2,
                    'help_text'          => 'Gas natural usado en calefacción, cocinas o equipos de respaldo.',
                ],
                [
                    'factor_name'        => 'R-410A (HFC)',
                    'questionnaire_label'=> 'Refrigerante R-410A recargado en HVAC (kg)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'kg',
                    'is_required'        => false,
                    'display_order'      => 3,
                    'help_text'          => 'Cantidad de refrigerante cargado durante mantenimiento de aires acondicionados.',
                ],
                [
                    'factor_name'        => 'R-22 (HCFC-22)',
                    'questionnaire_label'=> 'Refrigerante R-22 recargado en equipos legacy (kg)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'kg',
                    'is_required'        => false,
                    'display_order'      => 4,
                    'help_text'          => 'Aplica a equipos de aire acondicionado fabricados antes de 2010.',
                ],
                [
                    'factor_name'        => 'Gasolina E10 (Mezcla comercial)',
                    'questionnaire_label'=> 'Gasolina E10 consumida en flota corporativa (Gal)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'Gal',
                    'is_required'        => false,
                    'display_order'      => 5,
                    'help_text'          => 'Galones de gasolina para vehículos de la empresa.',
                ],
                [
                    'factor_name'        => 'Diesel B10',
                    'questionnaire_label'=> 'Diesel B10 consumido en flota o generadores (Gal)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'Gal',
                    'is_required'        => false,
                    'display_order'      => 6,
                    'help_text'          => 'Galones de diesel en vehículos pesados o planta eléctrica de respaldo.',
                ],
                [
                    'factor_name'        => 'CO2 (Extintor)',
                    'questionnaire_label'=> 'CO2 descargado en recarga de extintores (kg)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'kg',
                    'is_required'        => false,
                    'display_order'      => 7,
                    'help_text'          => 'Peso del CO2 cargado al recargar extintores de gas.',
                ],
                [
                    'factor_name'        => 'Agua Potable Consumida',
                    'questionnaire_label'=> 'Agua potable consumida (m³)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'm3',
                    'is_required'        => false,
                    'display_order'      => 8,
                    'help_text'          => 'Lectura del medidor de agua del edificio en el período.',
                ],
                [
                    'factor_name'        => 'Aguas Residuales Tratadas',
                    'questionnaire_label'=> 'Aguas residuales tratadas in-situ (m³)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'm3',
                    'is_required'        => false,
                    'display_order'      => 9,
                    'help_text'          => 'Solo aplica si el edificio tiene planta de tratamiento propia.',
                ],
                [
                    'factor_name'        => 'Vuelo Nacional',
                    'questionnaire_label'=> 'Distancia total en vuelos nacionales (km)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'km',
                    'is_required'        => false,
                    'display_order'      => 10,
                    'help_text'          => 'Suma de km recorridos por empleados en vuelos nacionales de negocio.',
                ],
                [
                    'factor_name'        => 'Vuelo Internacional',
                    'questionnaire_label'=> 'Distancia total en vuelos internacionales (km)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'km',
                    'is_required'        => false,
                    'display_order'      => 11,
                    'help_text'          => 'Suma de km recorridos por empleados en vuelos internacionales de negocio.',
                ],
                [
                    'factor_name'        => 'Empleado Remoto',
                    'questionnaire_label'=> 'Días de trabajo remoto del equipo (días-persona)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'día',
                    'is_required'        => false,
                    'display_order'      => 12,
                    'help_text'          => 'Total de días trabajados desde casa por todos los empleados en el período.',
                ],
                [
                    'factor_name'        => 'Residuos Sólidos en Vertedero',
                    'questionnaire_label'=> 'Residuos sólidos enviados a vertedero (ton)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'Ton',
                    'is_required'        => false,
                    'display_order'      => 13,
                    'help_text'          => 'Toneladas de residuos no reciclados recogidos por el operador de aseo.',
                ],
            ],

            // ─── SECTOR: tecnologia ─────────────────────────────────────────────────
            'tecnologia' => [
                [
                    'factor_name'        => 'FE Colombia (Interconectado)',
                    'questionnaire_label'=> 'Energía activa consumida (kWh) — incluir PUE si es data center',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'kWh',
                    'is_required'        => true,
                    'display_order'      => 1,
                    'help_text'          => 'Para centros de datos, multiplicar kWh IT × PUE antes de ingresar.',
                ],
                [
                    'factor_name'        => 'Diesel / ACPM (Fijo)',
                    'questionnaire_label'=> 'Diesel para generadores de respaldo UPS (Gal)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'Gal',
                    'is_required'        => false,
                    'display_order'      => 2,
                    'help_text'          => 'Galones consumidos en pruebas o activación de planta eléctrica.',
                ],
                [
                    'factor_name'        => 'R-410A (HFC)',
                    'questionnaire_label'=> 'Refrigerante R-410A en sistemas de enfriamiento (kg)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'kg',
                    'is_required'        => false,
                    'display_order'      => 3,
                    'help_text'          => 'Recarga de refrigerante en sistemas CRAC/CRAH del data center.',
                ],
                [
                    'factor_name'        => 'Empleado Remoto',
                    'questionnaire_label'=> 'Días de trabajo remoto del equipo (días-persona)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'día',
                    'is_required'        => false,
                    'display_order'      => 4,
                    'help_text'          => 'Total de días trabajados desde casa por todos los empleados.',
                ],
                [
                    'factor_name'        => 'Vuelo Nacional',
                    'questionnaire_label'=> 'Distancia total en vuelos nacionales (km)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'km',
                    'is_required'        => false,
                    'display_order'      => 5,
                    'help_text'          => null,
                ],
            ],

            // ─── SECTOR: transporte ─────────────────────────────────────────────────
            'transporte' => [
                [
                    'factor_name'        => 'Gasolina E10 (Mezcla comercial)',
                    'questionnaire_label'=> 'Gasolina E10 en flota (Gal)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'Gal',
                    'is_required'        => true,
                    'display_order'      => 1,
                    'help_text'          => 'Consumo total de gasolina en vehículos de la flota.',
                ],
                [
                    'factor_name'        => 'Diesel B10',
                    'questionnaire_label'=> 'Diesel B10 en flota pesada o maquinaria (Gal)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'Gal',
                    'is_required'        => true,
                    'display_order'      => 2,
                    'help_text'          => 'Consumo total de diesel en camiones, tractomulas y maquinaria.',
                ],
                [
                    'factor_name'        => 'Gas Natural Vehicular (GNV)',
                    'questionnaire_label'=> 'Gas Natural Vehicular en flota a GNV (m³)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'm3',
                    'is_required'        => false,
                    'display_order'      => 3,
                    'help_text'          => 'Aplica solo si parte de la flota usa GNV.',
                ],
                [
                    'factor_name'        => 'FE Colombia (Interconectado)',
                    'questionnaire_label'=> 'Energía eléctrica en bodegas y oficinas (kWh)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'kWh',
                    'is_required'        => false,
                    'display_order'      => 4,
                    'help_text'          => null,
                ],
                [
                    'factor_name'        => 'Aceite Lubricante',
                    'questionnaire_label'=> 'Aceite lubricante consumido en mantenimiento (Gal)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'Gal',
                    'is_required'        => false,
                    'display_order'      => 5,
                    'help_text'          => 'Aceite de motor consumido en cambios de la flota.',
                ],
            ],

            // ─── SECTOR: energia ────────────────────────────────────────────────────
            'energia' => [
                [
                    'factor_name'        => 'Gas Natural (Fijo)',
                    'questionnaire_label'=> 'Gas natural en generación o procesos (m³)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'm3',
                    'is_required'        => true,
                    'display_order'      => 1,
                    'help_text'          => null,
                ],
                [
                    'factor_name'        => 'Diesel / ACPM (Fijo)',
                    'questionnaire_label'=> 'Diesel en turbinas o calderas (Gal)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'Gal',
                    'is_required'        => false,
                    'display_order'      => 2,
                    'help_text'          => null,
                ],
                [
                    'factor_name'        => 'Carbón Mineral',
                    'questionnaire_label'=> 'Carbón mineral en plantas térmicas (Ton)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'Ton',
                    'is_required'        => false,
                    'display_order'      => 3,
                    'help_text'          => null,
                ],
                [
                    'factor_name'        => 'FE Colombia (Interconectado)',
                    'questionnaire_label'=> 'Electricidad de red consumida en operaciones (kWh)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'kWh',
                    'is_required'        => false,
                    'display_order'      => 4,
                    'help_text'          => null,
                ],
                [
                    'factor_name'        => 'R-410A (HFC)',
                    'questionnaire_label'=> 'Refrigerante R-410A en sistemas industriales (kg)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'kg',
                    'is_required'        => false,
                    'display_order'      => 5,
                    'help_text'          => null,
                ],
            ],

            // ─── SECTOR: industria ──────────────────────────────────────────────────
            'industria' => [
                [
                    'factor_name'        => 'Gas Natural (Fijo)',
                    'questionnaire_label'=> 'Gas natural en calderas, hornos o procesos (m³)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'm3',
                    'is_required'        => true,
                    'display_order'      => 1,
                    'help_text'          => null,
                ],
                [
                    'factor_name'        => 'Diesel / ACPM (Fijo)',
                    'questionnaire_label'=> 'Diesel en equipos fijos de producción (Gal)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'Gal',
                    'is_required'        => false,
                    'display_order'      => 2,
                    'help_text'          => null,
                ],
                [
                    'factor_name'        => 'FE Colombia (Interconectado)',
                    'questionnaire_label'=> 'Electricidad de red en planta de producción (kWh)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'kWh',
                    'is_required'        => true,
                    'display_order'      => 3,
                    'help_text'          => null,
                ],
                [
                    'factor_name'        => 'R-410A (HFC)',
                    'questionnaire_label'=> 'Refrigerante en sistemas de frío industrial (kg)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'kg',
                    'is_required'        => false,
                    'display_order'      => 4,
                    'help_text'          => null,
                ],
                [
                    'factor_name'        => 'Gasolina E10 (Mezcla comercial)',
                    'questionnaire_label'=> 'Gasolina en flota interna (Gal)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'Gal',
                    'is_required'        => false,
                    'display_order'      => 5,
                    'help_text'          => null,
                ],
                [
                    'factor_name'        => 'Residuos Sólidos en Vertedero',
                    'questionnaire_label'=> 'Residuos industriales enviados a vertedero (ton)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'Ton',
                    'is_required'        => false,
                    'display_order'      => 6,
                    'help_text'          => null,
                ],
                [
                    'factor_name'        => 'Agua Potable Consumida',
                    'questionnaire_label'=> 'Agua consumida en procesos industriales (m³)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'm3',
                    'is_required'        => false,
                    'display_order'      => 7,
                    'help_text'          => null,
                ],
            ],

            // ─── SECTOR: publico ────────────────────────────────────────────────────
            'publico' => [
                [
                    'factor_name'        => 'FE Colombia (Interconectado)',
                    'questionnaire_label'=> 'Electricidad de red en instalaciones públicas (kWh)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'kWh',
                    'is_required'        => true,
                    'display_order'      => 1,
                    'help_text'          => null,
                ],
                [
                    'factor_name'        => 'Gas Natural (Fijo)',
                    'questionnaire_label'=> 'Gas natural en instalaciones (m³)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'm3',
                    'is_required'        => false,
                    'display_order'      => 2,
                    'help_text'          => null,
                ],
                [
                    'factor_name'        => 'Gasolina E10 (Mezcla comercial)',
                    'questionnaire_label'=> 'Gasolina en flota oficial (Gal)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'Gal',
                    'is_required'        => false,
                    'display_order'      => 3,
                    'help_text'          => null,
                ],
                [
                    'factor_name'        => 'Agua Potable Consumida',
                    'questionnaire_label'=> 'Agua potable consumida (m³)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'm3',
                    'is_required'        => false,
                    'display_order'      => 4,
                    'help_text'          => null,
                ],
                [
                    'factor_name'        => 'Empleado Remoto',
                    'questionnaire_label'=> 'Días de teletrabajo del equipo (días-persona)',
                    'variable_name'      => 'consumption',
                    'input_unit_hint'    => 'día',
                    'is_required'        => false,
                    'display_order'      => 5,
                    'help_text'          => null,
                ],
            ],
        ];

        foreach ($rules as $sectorCode => $sectorRules) {
            foreach ($sectorRules as $rule) {
                $factorId = $factor($rule['factor_name']);
                if (!$factorId) {
                    $this->command->warn("Factor not found: {$rule['factor_name']} (sector: {$sectorCode})");
                    continue;
                }

                DB::table('sector_questionnaire_rules')->updateOrInsert(
                    ['sector_code' => $sectorCode, 'emission_factor_id' => $factorId],
                    [
                        'questionnaire_label' => $rule['questionnaire_label'],
                        'variable_name'       => $rule['variable_name'],
                        'input_unit_hint'     => $rule['input_unit_hint'],
                        'is_required'         => $rule['is_required'],
                        'display_order'       => $rule['display_order'],
                        'help_text'           => $rule['help_text'],
                        'updated_at'          => now(),
                        'created_at'          => now(),
                    ]
                );
            }
        }

        $this->command->info('✅ Sector questionnaire rules seeded for 6 sectors');
    }
}
