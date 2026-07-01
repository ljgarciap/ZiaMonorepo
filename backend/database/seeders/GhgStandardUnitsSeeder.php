<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GhgStandardUnitsSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['name' => 'Kilogramo',             'symbol' => 'kg',   'is_standard' => true, 'is_active' => true],
            ['name' => 'Tonelada',               'symbol' => 't',    'is_standard' => true, 'is_active' => true],
            ['name' => 'Galón',                  'symbol' => 'gal',  'is_standard' => true, 'is_active' => true],
            ['name' => 'Litro',                  'symbol' => 'L',    'is_standard' => true, 'is_active' => true],
            ['name' => 'Metro cúbico',           'symbol' => 'm3',   'is_standard' => true, 'is_active' => true],
            ['name' => 'Kilowatt-hora',          'symbol' => 'kWh',  'is_standard' => true, 'is_active' => true],
            ['name' => 'Megawatt-hora',          'symbol' => 'MWh',  'is_standard' => true, 'is_active' => true],
            ['name' => 'Megajulio',              'symbol' => 'MJ',   'is_standard' => true, 'is_active' => true],
            ['name' => 'Kilómetro',              'symbol' => 'km',   'is_standard' => true, 'is_active' => true],
            ['name' => 'Tonelada-kilómetro',     'symbol' => 't·km', 'is_standard' => true, 'is_active' => true],
        ];

        foreach ($units as $unit) {
            DB::table('measurement_units')->updateOrInsert(
                ['symbol' => $unit['symbol']],
                array_merge($unit, ['updated_at' => now(), 'created_at' => now()])
            );
        }
    }
}
