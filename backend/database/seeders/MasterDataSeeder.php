<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Scope;
use App\Models\MeasurementUnit;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Scopes
        $scopes = [
            1 => [
                'name' => 'Alcance 1',
                'description' => 'Emisiones directas de fuentes propias o controladas.',
                'documentation_text' => 'Se refiere a las emisiones de GEI directas generadas dentro de la organización.'
            ],
            2 => [
                'name' => 'Alcance 2',
                'description' => 'Emisiones indirectas por consumo de energía.',
                'documentation_text' => 'Se refiere a las emisiones de GEI provenientes del consumo de electricidad, calor o vapor adquiridos.'
            ],
            3 => [
                'name' => 'Alcance 3',
                'description' => 'Otras emisiones indirectas en la cadena de valor.',
                'documentation_text' => 'Incluye todas las demás emisiones indirectas que ocurren en la cadena de valor de la empresa.'
            ],
        ];

        foreach ($scopes as $id => $data) {
            Scope::updateOrCreate(['id' => $id], $data);
        }

        // 2. Seed Units
        $units = [
            ['name' => 'Galones', 'symbol' => 'Gal'],
            ['name' => 'Kilogramos', 'symbol' => 'kg'],
            ['name' => 'Metros Cúbicos', 'symbol' => 'm3'],
            ['name' => 'Kilovatios-hora', 'symbol' => 'kWh'],
            ['name' => 'Kilómetros', 'symbol' => 'km'],
            ['name' => 'Toneladas', 'symbol' => 'Ton'],
            ['name' => 'Días', 'symbol' => 'día'],
        ];

        foreach ($units as $unit) {
            MeasurementUnit::firstOrCreate(['symbol' => $unit['symbol']], $unit);
        }

        $this->command->info('✅ Master Data (Scopes and Units) seeded successfully');
    }
}
