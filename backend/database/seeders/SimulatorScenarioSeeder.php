<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SimulatorScenario;

class SimulatorScenarioSeeder extends Seeder
{
    // FECOC 2024 Colombia grid factor and reference tariff from ECONOVA census (R5-DEL-001)
    private const FECOC_KG_CO2E_PER_KWH = 0.214;
    private const TARIFF_COP_PER_KWH    = 800;

    public function run(): void
    {
        SimulatorScenario::truncate();

        $scenarios = [
            [
                'code'        => 'HVAC_SCHEDULE',
                'name'        => 'Ajuste de Horario HVAC',
                'description' => 'Reducir 1 hora diaria de operación del AC (07:30-18:00 → 08:00-17:30). '
                               . 'Aplica sobre los 5 splits R-410A del edificio ECONOVA (20.44 kW totales). '
                               . 'Fuente: R5-DEL-001 — Censo de Cargas ECONOVA.',
                'category'    => 'hvac',
                'scope'       => 2,
                // 20.44 kW × 1 h/día × 5 días × 52 semanas
                'reduction_kwh_year'      => 5314.0,
                'emission_factor_kgco2e_kwh' => self::FECOC_KG_CO2E_PER_KWH,
                'tariff_cop_kwh'          => self::TARIFF_COP_PER_KWH,
                'annual_co2e_tco2e'       => round(5314.0 * self::FECOC_KG_CO2E_PER_KWH / 1000, 4), // 1.1372
                'annual_savings_cop'      => intval(5314.0 * self::TARIFF_COP_PER_KWH),              // 4,251,200
            ],
            [
                'code'        => 'HVAC_SETPOINT',
                'name'        => 'Setpoint HVAC 22°C → 24°C',
                'description' => 'Subir 2°C el setpoint de climatización. Según modelado térmico DesignBuilder '
                               . '(ASHRAE 90.1 — zona Bucaramanga), cada 2°C equivale a 6% menos de consumo de chiller. '
                               . 'Fuente: R5-R2-INS-001 — Especificación Bioclimática ECONOVA.',
                'category'    => 'hvac',
                'scope'       => 2,
                // 20.44 kW × 6% × 2,730 h/año (10.5h/día × 5 días × 52 semanas)
                'reduction_kwh_year'      => 3347.0,
                'emission_factor_kgco2e_kwh' => self::FECOC_KG_CO2E_PER_KWH,
                'tariff_cop_kwh'          => self::TARIFF_COP_PER_KWH,
                'annual_co2e_tco2e'       => round(3347.0 * self::FECOC_KG_CO2E_PER_KWH / 1000, 4), // 0.7162
                'annual_savings_cop'      => intval(3347.0 * self::TARIFF_COP_PER_KWH),              // 2,677,600
            ],
            [
                'code'        => 'LIGHTING_SENSORS',
                'name'        => 'Sensores de Ocupación en Iluminación',
                'description' => 'Instalar sensores de presencia en las 3 plantas (208 luminarias LED, 4.416 kW). '
                               . 'Reducción estimada del 30% del tiempo de uso por zonas desocupadas. '
                               . 'Fuente: R5-DEL-001 — Inventario de Iluminación ECONOVA.',
                'category'    => 'lighting',
                'scope'       => 2,
                // 4.416 kW × 30% × 2,730 h/año
                'reduction_kwh_year'      => 3617.0,
                'emission_factor_kgco2e_kwh' => self::FECOC_KG_CO2E_PER_KWH,
                'tariff_cop_kwh'          => self::TARIFF_COP_PER_KWH,
                'annual_co2e_tco2e'       => round(3617.0 * self::FECOC_KG_CO2E_PER_KWH / 1000, 4), // 0.7740
                'annual_savings_cop'      => intval(3617.0 * self::TARIFF_COP_PER_KWH),              // 2,893,600
            ],
            [
                'code'        => 'REFRIGERANT_MAINTENANCE',
                'name'        => 'Mantenimiento Preventivo Refrigerante R-410A',
                'description' => 'Reducir tasa de fuga de R-410A del 15% anual al 2% mediante mantenimiento preventivo. '
                               . 'El edificio tiene 16.8 kg de carga total (GWP R-410A = 2,088). '
                               . 'Es el escenario de mayor impacto en Alcance 1. '
                               . 'Fuente: R5-DEL-001 — Inventario HVAC ECONOVA.',
                'category'    => 'refrigerant',
                'scope'       => 1,
                // Sin mantenimiento: 16.8 × 15% = 2.52 kg/año | Con mant.: 16.8 × 2% = 0.336 kg/año
                'reduction_kg_year'  => 2.184, // 2.52 - 0.336
                'gwp'                => 2088,
                'annual_co2e_tco2e'  => round(2.184 * 2088 / 1000, 4), // 4.5603
                'annual_savings_cop' => 0, // Ahorro indirecto (menor compra de refrigerante)
            ],
            [
                'code'        => 'PUMP_UPGRADE',
                'name'        => 'Upgrade Bomba Hidroneumática IE2 → IE3',
                'description' => 'Reemplazar las 2 bombas de agua (IE2, 84%) por motores IE3 (91%). '
                               . 'Operan 24/7 en modo prestostático. Ganancia neta: 7% de eficiencia. '
                               . 'Fuente: R5-DEL-001 — Inventario de Motores ECONOVA.',
                'category'    => 'motor',
                'scope'       => 2,
                // 4.48 kW × 7% × 8,760 h/año
                'reduction_kwh_year'      => 2750.0,
                'emission_factor_kgco2e_kwh' => self::FECOC_KG_CO2E_PER_KWH,
                'tariff_cop_kwh'          => self::TARIFF_COP_PER_KWH,
                'annual_co2e_tco2e'       => round(2750.0 * self::FECOC_KG_CO2E_PER_KWH / 1000, 4), // 0.5885
                'annual_savings_cop'      => intval(2750.0 * self::TARIFF_COP_PER_KWH),              // 2,200,000
            ],
        ];

        foreach ($scenarios as $data) {
            SimulatorScenario::create($data);
        }
    }
}
