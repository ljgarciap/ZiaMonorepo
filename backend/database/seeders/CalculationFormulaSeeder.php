<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CalculationFormula;

class CalculationFormulaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $formulas = [
            [
                'name' => 'Combustión Estándar (Actividad * Factor)',
                'expression' => 'activity_data * factor_total_co2e / 1000',
                'description' => 'Cálculo básico: actividad × factor_total_co2e / 1000 → resultado en tCO2e.'
            ],
            [
                'name' => 'Combustión Móvil (Excel Z16)',
                'expression' => '((activity_data * factor_co2) + (activity_data * factor_ch4 * gwp_ch4) + (activity_data * factor_n2o * gwp_n2o) + (activity_data * factor_nf3 * gwp_nf3) + (activity_data * factor_sf6 * gwp_sf6)) / 1000',
                'description' => 'Suma CO2+CH4+N2O+NF3+SF6 ajustados por GWP / 1000 → resultado en tCO2e. Replica lógica Excel Z16.'
            ],
            [
                'name' => 'Fugas de Refrigerante',
                'expression' => 'activity_data * (factor_total_co2e / 1000)',
                'description' => 'Actividad en kg × GWP / 1000 → resultado en tCO2e. Usado para refrigerantes y extintores.'
            ]
        ];

        foreach ($formulas as $formula) {
            CalculationFormula::updateOrCreate(
                ['name' => $formula['name']],
                ['expression' => $formula['expression'], 'description' => $formula['description']]
            );
        }
    }
}
