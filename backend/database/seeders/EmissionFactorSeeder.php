<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmissionCategory;
use App\Models\EmissionFactor;

class EmissionFactorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Helpers for lookups
        $unit = fn($symbol) => \App\Models\MeasurementUnit::where('symbol', $symbol)->first()?->id;
        $cat = fn($name) => \App\Models\EmissionCategory::where('name', $name)->first()?->id;
        $form = fn($name) => \DB::table('calculation_formulas')->where('name', 'like', "%$name%")->value('id');

        $stdFormulaId = $form('Combustión Estándar');
        $mobileFormulaId = $form('Combustión Móvil');

        // 2. Mobile Sources
        $mobileId = $cat('Fuentes Móviles - Combustibles');
        
        EmissionFactor::updateOrCreate(
            ['name' => 'Gasolina E10 (Mezcla comercial)', 'emission_category_id' => $mobileId],
            [
                'measurement_unit_id' => $unit('Gal'),
                'calculation_formula_id' => $mobileFormulaId,
                'factor_co2' => 7.618,
                'factor_ch4' => 0.0002627,
                'factor_n2o' => 0.0000255,
                // factor_total_co2e = CO2×1 + CH4×29.8 + N2O×273 (AR6, kgCO2e/Gal)
                'factor_total_co2e' => 7.63279,
                'uncertainty_lower' => 0.234,
                'uncertainty_upper' => 0.234,
                'uncertainty_distribution' => 'normal',
                'source_reference' => 'Calculo MVP Zia - Excel Definitiva (Row 16)'
            ]
        );

        EmissionFactor::updateOrCreate(
            ['name' => 'Diesel B10', 'emission_category_id' => $mobileId],
            [
                'measurement_unit_id' => $unit('Gal'),
                'calculation_formula_id' => $mobileFormulaId,
                'factor_co2' => 8.812,
                'factor_ch4' => 0.000028,
                'factor_n2o' => 0.00003,
                // factor_total_co2e = CO2×1 + CH4×29.8 + N2O×273 (AR6, kgCO2e/Gal)
                'factor_total_co2e' => 8.82102,
                'source_reference' => 'Estimado (Pending Excel Extraction)'
            ]
        );

        // 3. Fugitive Emissions (Refrigerants — Fixed Sources: building A/C, cold storage)
        // GWP from IPCC AR6 / GHG Protocol August 2024. R-410A, R-404A, R-123: AR6 blend values pending.
        // Mobile-source refrigerants (refrigerated fleets) go under 'Emisiones Fugitivas - Refrigerantes Móviles'.
        $fugitiveId = $cat('Emisiones Fugitivas - Refrigerantes Fijas');

        $refrigerants = [
            ['name' => 'R-410A (HFC)',      'factor_total_co2e' => 2088, 'source' => 'IPCC AR5 — AR6 blend value pending'],
            ['name' => 'R-22 (HCFC-22)',    'factor_total_co2e' => 1960, 'source' => 'IPCC AR6 / GHG Protocol Aug 2024'],
            ['name' => 'R-134a (HFC-134a)', 'factor_total_co2e' => 1526, 'source' => 'IPCC AR6 / GHG Protocol Aug 2024'],
            ['name' => 'R-404A (HFC)',       'factor_total_co2e' => 3922, 'source' => 'IPCC AR5 — AR6 blend value pending'],
            ['name' => 'R-32 (HFC-32)',      'factor_total_co2e' => 771,  'source' => 'IPCC AR6 / GHG Protocol Aug 2024'],
            ['name' => 'R-123 (HCFC-123)',   'factor_total_co2e' => 77,   'source' => 'IPCC AR5 — AR6 value pending'],
        ];

        foreach ($refrigerants as $r) {
            EmissionFactor::updateOrCreate(
                ['name' => $r['name'], 'emission_category_id' => $fugitiveId],
                [
                    'measurement_unit_id' => $unit('kg'),
                    'factor_co2' => 0,
                    'factor_ch4' => 0,
                    'factor_n2o' => 0,
                    'factor_total_co2e' => $r['factor_total_co2e'],
                    'source_reference' => $r['source'],
                ]
            );
        }

        // 4. Mobile Gaseous
        $mobileGaseousId = $cat('Fuentes Móviles - Gases');

        EmissionFactor::updateOrCreate(
            ['name' => 'Gas Natural Vehicular (GNV)', 'emission_category_id' => $mobileGaseousId],
            [
                'measurement_unit_id' => $unit('m3'),
                'factor_total_co2e' => 1.93,
                'source_reference' => 'Calculo MVP'
            ]
        );

        // 5. Extinguishers — classified as fugitive emissions per GHG Protocol, not mobile combustion
        $extinguishersId = $cat('Emisiones Fugitivas - Extintores');

        EmissionFactor::updateOrCreate(
            ['name' => 'CO2 (Extintor)', 'emission_category_id' => $extinguishersId],
            [
                'measurement_unit_id' => $unit('kg'),
                'factor_total_co2e' => 1.0,
                'source_reference' => 'Standard'
            ]
        );

        // 6. Lubricants
        $lubricantsId = $cat('Fuentes Móviles - Lubricantes');

        EmissionFactor::updateOrCreate(
            ['name' => 'Aceite Lubricante', 'emission_category_id' => $lubricantsId],
            [
                'measurement_unit_id' => $unit('Gal'),
                'factor_total_co2e' => 0.002,
                'source_reference' => 'MVP'
            ]
        );

        // 7. Fixed Solid
        $fixedSolidId = $cat('Fuentes Fijas - Combustibles Sólidos');

        EmissionFactor::updateOrCreate(
            ['name' => 'Carbón Mineral', 'emission_category_id' => $fixedSolidId],
            [
                'measurement_unit_id' => $unit('Ton'),
                'factor_total_co2e' => 2400.5,
                'source_reference' => 'IPCC'
            ]
        );

        // 8. Fixed Liquid
        $fixedLiquidId = $cat('Fuentes Fijas - Combustibles Líquidos');

        EmissionFactor::updateOrCreate(
            ['name' => 'Diesel / ACPM (Fijo)', 'emission_category_id' => $fixedLiquidId],
            [
                'measurement_unit_id' => $unit('Gal'),
                'factor_total_co2e' => 10.15,
                'source_reference' => 'UPME'
            ]
        );

        // 9. Fixed Gaseous
        $fixedGaseousId = $cat('Fuentes Fijas - Combustibles Gaseosos');

        EmissionFactor::updateOrCreate(
            ['name' => 'Gas Natural (Fijo)', 'emission_category_id' => $fixedGaseousId],
            [
                'measurement_unit_id' => $unit('m3'),
                'calculation_formula_id' => $stdFormulaId,
                'factor_total_co2e' => 1.933,
                'source_reference' => 'UPME'
            ]
        );

        // 10. Scope 2 - Electricity
        $electricityId = $cat('Electricidad - Red');

        EmissionFactor::updateOrCreate(
            ['name' => 'FE Colombia (Interconectado)', 'emission_category_id' => $electricityId],
            [
                'measurement_unit_id' => $unit('kWh'),
                'factor_co2' => 0.126,
                'factor_ch4' => 0,
                'factor_n2o' => 0,
                'factor_total_co2e' => 0.126,
                'source_reference' => 'XM / UPME'
            ]
        );

        // 11. Scope 3 - Viajes Aéreos
        $flightsId = $cat('Viajes Aéreos');

        EmissionFactor::updateOrCreate(
            ['name' => 'Vuelo Nacional (km)', 'emission_category_id' => $flightsId],
            [
                'measurement_unit_id' => $unit('km'),
                'factor_total_co2e' => 0.255,
                'source_reference' => 'DEFRA 2023 - Domestic flights'
            ]
        );

        EmissionFactor::updateOrCreate(
            ['name' => 'Vuelo Internacional (km)', 'emission_category_id' => $flightsId],
            [
                'measurement_unit_id' => $unit('km'),
                'factor_total_co2e' => 0.195,
                'source_reference' => 'DEFRA 2023 - International flights'
            ]
        );

        // 12. Scope 3 - Trabajo Remoto
        $remoteWorkId = $cat('Trabajo Remoto');

        EmissionFactor::updateOrCreate(
            ['name' => 'Empleado Remoto (día)', 'emission_category_id' => $remoteWorkId],
            [
                'measurement_unit_id' => $unit('día'),
                'factor_total_co2e' => 2.5,
                'source_reference' => 'EcoAct 2020 - Home working emissions'
            ]
        );

        // 13. Scope 3 - Agua y Residuos
        $waterCatId = $cat('Consumo de Agua');
        $wasteCatId = $cat('Residuos Sólidos');

        EmissionFactor::updateOrCreate(
            ['name' => 'Agua Potable Consumida (m3)', 'emission_category_id' => $waterCatId],
            [
                'measurement_unit_id' => $unit('m3'),
                // 0.35 kgCO2e/m³ (EcoAct 2020). Motor aplica /1000 → 0.00035 tCO2e/m³.
                'factor_total_co2e' => 0.35,
                'source_reference' => 'EcoAct 2020 - Water supply emissions (kgCO2e/m3)',
            ]
        );

        EmissionFactor::updateOrCreate(
            ['name' => 'Aguas Residuales Tratadas (m3)', 'emission_category_id' => $waterCatId],
            [
                'measurement_unit_id' => $unit('m3'),
                // 0.78 kgCO2e/m³ (EcoAct 2020). Motor aplica /1000 → 0.00078 tCO2e/m³.
                'factor_total_co2e' => 0.78,
                'source_reference' => 'EcoAct 2020 - Wastewater treatment emissions (kgCO2e/m3)',
            ]
        );

        EmissionFactor::updateOrCreate(
            ['name' => 'Residuos Sólidos en Vertedero (ton)', 'emission_category_id' => $wasteCatId],
            [
                'measurement_unit_id' => $unit('Ton'),
                // 140 kgCO2e/ton (IPCC 2006 Vol. 5, conservative). Motor aplica /1000 → 0.14 tCO2e/ton.
                'factor_total_co2e' => 140,
                'source_reference' => 'IPCC 2006 Vol. 5 - Solid waste disposal (conservative estimate)',
            ]
        );

        EmissionFactor::updateOrCreate(
            ['name' => 'Residuos Reciclables Gestionados (ton)', 'emission_category_id' => $wasteCatId],
            [
                'measurement_unit_id' => $unit('Ton'),
                'factor_total_co2e' => 0.021,
                'source_reference' => 'DEFRA 2023 - Material recycling (avoided emissions proxy)',
            ]
        );

        $this->command->info('✅ Emission factors created successfully with proper relationships');
    }
}
