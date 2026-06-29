<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CalculationFormula>
 */
class CalculationFormulaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'        => $this->faker->words(3, true),
            'expression'  => 'activity_data * factor_co2 / 1000',
            'variables'   => null,
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
