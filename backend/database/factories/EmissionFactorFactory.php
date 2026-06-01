<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmissionFactor>
 */
class EmissionFactorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'emission_category_id' => \App\Models\EmissionCategory::factory(),
            'name' => 'Test Factor',
            'measurement_unit_id' => function () {
                return \App\Models\MeasurementUnit::firstOrCreate(
                    ['symbol' => 'kg'],
                    ['name' => 'Kilogramo']
                )->id;
            },
            'factor_co2' => 1.0,
            'factor_ch4' => 0.0,
            'factor_n2o' => 0.0,
            'factor_total_co2e' => 1.0,
        ];
    }
}
