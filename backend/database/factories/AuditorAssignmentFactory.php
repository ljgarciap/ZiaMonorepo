<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditorAssignment>
 */
class AuditorAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(['role' => 'auditor']),
            'company_id' => \App\Models\Company::factory(),
            'period_id' => \App\Models\Period::factory(),
            'granted_by' => \App\Models\User::factory(['role' => 'superadmin']),
            'expires_at' => null,
        ];
    }
}
