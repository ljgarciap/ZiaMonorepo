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
                'expression' => 'activity_data * factor_total_co2e',
                'description' => 'Cálculo básico multiplicando el dato de actividad por el factor total de CO2e.'
            ],
            [
                'name' => 'Combustión Móvil (Excel Z16)',
                'expression' => '(activity_data * factor_co2) + (activity_data * factor_ch4 * gwp_ch4) + (activity_data * factor_n2o * gwp_n2o)',
                'description' => 'Lógica detallada que suma las contribuciones de CO2, CH4 y N2O ajustadas por sus GWP.'
            ],
            [
                'name' => 'Fugas de Refrigerante',
                'expression' => 'activity_data * (factor_total_co2e / 1000)',
                'description' => 'Cálculo para fugas de gas refrigerante asumiendo que el dato de actividad está en gramos.'
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
