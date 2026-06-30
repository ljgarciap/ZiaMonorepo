<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CompanySector;
use App\Models\Subsector;

class SubsectorSeeder extends Seeder
{
    public function run(): void
    {
        $sector = fn(string $code) => CompanySector::where('code', $code)->value('id');

        $subsectors = [
            // Sector: servicios
            ['sector' => 'servicios', 'code' => 'comercio',    'name' => 'Comercio y Retail'],
            ['sector' => 'servicios', 'code' => 'educacion',   'name' => 'Educación'],
            ['sector' => 'servicios', 'code' => 'salud',       'name' => 'Salud y Bienestar'],
            ['sector' => 'servicios', 'code' => 'financiero',  'name' => 'Servicios Financieros'],
            ['sector' => 'servicios', 'code' => 'hoteleria',   'name' => 'Hotelería y Turismo'],
            ['sector' => 'servicios', 'code' => 'inmobiliario','name' => 'Inmobiliario y Gestión de Activos'],

            // Sector: industria
            ['sector' => 'industria', 'code' => 'manufactura',   'name' => 'Manufactura General'],
            ['sector' => 'industria', 'code' => 'agroindustria', 'name' => 'Agroindustria'],
            ['sector' => 'industria', 'code' => 'construccion',  'name' => 'Construcción'],
            ['sector' => 'industria', 'code' => 'mineria',       'name' => 'Minería'],

            // Sector: transporte
            ['sector' => 'transporte', 'code' => 'carga_terrestre', 'name' => 'Carga Terrestre'],
            ['sector' => 'transporte', 'code' => 'pasajeros',       'name' => 'Transporte de Pasajeros'],
            ['sector' => 'transporte', 'code' => 'aereo',           'name' => 'Transporte Aéreo'],

            // Sector: energia
            ['sector' => 'energia', 'code' => 'electrica',    'name' => 'Generación Eléctrica'],
            ['sector' => 'energia', 'code' => 'petroleo_gas', 'name' => 'Petróleo y Gas'],

            // Sector: tecnologia
            ['sector' => 'tecnologia', 'code' => 'software_tic',        'name' => 'Software y TIC'],
            ['sector' => 'tecnologia', 'code' => 'telecomunicaciones',   'name' => 'Telecomunicaciones'],
            ['sector' => 'tecnologia', 'code' => 'centros_datos',        'name' => 'Centros de Datos'],

            // Sector: publico
            ['sector' => 'publico', 'code' => 'administracion', 'name' => 'Administración Pública'],
            ['sector' => 'publico', 'code' => 'defensa',        'name' => 'Defensa y Seguridad'],
            ['sector' => 'publico', 'code' => 'social',         'name' => 'Servicios Sociales'],
        ];

        foreach ($subsectors as $row) {
            $sectorId = $sector($row['sector']);
            if (!$sectorId) {
                continue;
            }
            Subsector::updateOrCreate(
                ['code' => $row['code']],
                [
                    'company_sector_id' => $sectorId,
                    'name'              => $row['name'],
                ]
            );
        }

        $this->command->info('✅ Subsectors seeded (' . count($subsectors) . ' entries)');
    }
}
