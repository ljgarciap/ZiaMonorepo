<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CarbonEmission>
 */
class CarbonEmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'period_id'           => \App\Models\Period::factory(),
            'emission_factor_id'  => \App\Models\EmissionFactor::factory(),
            'quantity'            => $this->faker->randomFloat(4, 10, 1000),
            'emissions_co2'       => $this->faker->randomFloat(8, 0, 10),
            'emissions_ch4'       => 0.0,
            'emissions_n2o'       => 0.0,
            'emissions_nf3'       => 0.0,
            'emissions_sf6'       => 0.0,
            'calculated_co2e'     => $this->faker->randomFloat(8, 0.01, 100),
            'uncertainty_result'  => $this->faker->randomFloat(6, 0, 10),
            'activity_data_total' => null,
            'activity_data_stdev' => null,
            'notes'               => null,
        ];
    }
}
