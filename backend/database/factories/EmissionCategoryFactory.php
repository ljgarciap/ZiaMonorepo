<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmissionCategory>
 */
class EmissionCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Test Category',
            'scope_id' => function () {
                return \App\Models\Scope::firstOrCreate(
                    ['name' => 'Alcance 1'],
                    ['number' => 1, 'description' => 'Test Scope']
                )->id;
            },
            'description' => 'Test Description',
        ];
    }
}
