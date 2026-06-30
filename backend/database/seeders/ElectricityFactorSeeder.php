<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ElectricityFactor;

class ElectricityFactorSeeder extends Seeder
{
    // FECOC (Factor de Emisión de CO2 de la red eléctrica colombiana)
    // Source: XM / UPME — historical emission factors for the Colombian grid.
    // Values in kgCO2e/kWh (location-based, national interconnected system).
    // TODO: add pre-2021 historical values from the UPME FECOC series.
    public function run(): void
    {
        $factors = [
            ['year' => 2024, 'region_code' => 'CO', 'value_kgco2e' => 0.1083, 'source' => 'XM / UPME FECOC 2024'],
            ['year' => 2023, 'region_code' => 'CO', 'value_kgco2e' => 0.1260, 'source' => 'XM / UPME FECOC 2023'],
            ['year' => 2022, 'region_code' => 'CO', 'value_kgco2e' => 0.1260, 'source' => 'XM / UPME FECOC 2022'],
            ['year' => 2021, 'region_code' => 'CO', 'value_kgco2e' => 0.1260, 'source' => 'XM / UPME FECOC 2021'],
            ['year' => 2020, 'region_code' => 'CO', 'value_kgco2e' => 0.1260, 'source' => 'XM / UPME FECOC 2020'],
            ['year' => 2019, 'region_code' => 'CO', 'value_kgco2e' => 0.1320, 'source' => 'XM / UPME FECOC 2019'],
        ];

        foreach ($factors as $row) {
            ElectricityFactor::updateOrCreate(
                ['year' => $row['year'], 'region_code' => $row['region_code']],
                ['value_kgco2e' => $row['value_kgco2e'], 'source' => $row['source']]
            );
        }

        $this->command->info('✅ Electricity factors (FECOC Colombia 2019-2024) created');
    }
}
