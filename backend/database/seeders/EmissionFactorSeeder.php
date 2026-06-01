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
                'factor_total_co2e' => 7.63323,
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
                'factor_total_co2e' => 8.825,
                'source_reference' => 'Estimado (Pending Excel Extraction)'
            ]
        );

        // 3. Fugitive Emissions (Refrigerants)
        $fugitiveId = $cat('Emisiones Fugitivas - Refrigerantes');

        EmissionFactor::updateOrCreate(
            ['name' => 'R-410A', 'emission_category_id' => $fugitiveId],
            [
                'measurement_unit_id' => $unit('kg'),
                'factor_co2' => 0,
                'factor_ch4' => 0,
                'factor_n2o' => 0,
                'factor_total_co2e' => 2088,
                'source_reference' => 'IPCC AR4/AR5'
            ]
        );

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

        // 5. Extinguishers
        $extinguishersId = $cat('Fuentes Móviles - Extintores');

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

        $this->command->info('✅ Emission factors created successfully with proper relationships');
    }
}
