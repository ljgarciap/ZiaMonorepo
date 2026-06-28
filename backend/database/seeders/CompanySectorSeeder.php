<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CompanySector;

class CompanySectorSeeder extends Seeder
{
    public function run(): void
    {
        $sectors = [
            [
                'code' => 'industria',
                'name' => 'Industria',
                'description' => 'Manufactura, construcción, agroindustria y procesamiento industrial.',
            ],
            [
                'code' => 'transporte',
                'name' => 'Transporte y Logística',
                'description' => 'Transporte de carga/pasajeros, logística y almacenamiento.',
            ],
            [
                'code' => 'servicios',
                'name' => 'Comercio y Servicios',
                'description' => 'Retail, e-commerce, banca, hotelería, educación, edificios comerciales.',
            ],
            [
                'code' => 'energia',
                'name' => 'Energía y Recursos Naturales',
                'description' => 'Generación/distribución de energía, minería, agua, petróleo y gas.',
            ],
            [
                'code' => 'publico',
                'name' => 'Sector Público y Gobierno',
                'description' => 'Entidades públicas e infraestructura pública (agua, saneamiento).',
            ],
            [
                'code' => 'tecnologia',
                'name' => 'Tecnología y Comunicaciones',
                'description' => 'Software, centros de datos, telecomunicaciones e internet.',
            ],
        ];

        foreach ($sectors as $sector) {
            CompanySector::updateOrCreate(
                ['code' => $sector['code']],
                ['name' => $sector['name'], 'description' => $sector['description']]
            );
        }
    }
}
