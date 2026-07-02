<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditObservation>
 */
class AuditObservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => \App\Models\Company::factory(),
            'period_id' => \App\Models\Period::factory(),
            'user_id' => \App\Models\User::factory(),
            'body' => $this->faker->sentence(),
            'verdict' => null,
        ];
    }
}
