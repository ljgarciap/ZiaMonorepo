<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IotDevice>
 */
class IotDeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'thingsboard_id' => $this->faker->uuid(),
            'name'           => $this->faker->words(2, true) . ' Sensor',
            'type'           => $this->faker->randomElement(['energy', 'water']),
            'location'       => $this->faker->city(),
            'unit'           => 'kWh',
        ];
    }
}
