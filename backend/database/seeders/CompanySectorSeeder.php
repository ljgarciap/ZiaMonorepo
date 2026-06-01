<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CompanySector;

class CompanySectorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sectors = [
            ['name' => 'Tecnología', 'description' => 'Desarrollo de software, servicios de TI y electrónica.'],
            ['name' => 'Energía', 'description' => 'Producción de petróleo, gas y energía renovable.'],
            ['name' => 'Construcción', 'description' => 'Desarrollo de infraestructura y construcción.'],
            ['name' => 'Industrial', 'description' => 'Manufactura y procesamiento industrial.'],
            ['name' => 'Agricultura', 'description' => 'Producción de cultivos y gestión ganadera.']
        ];

        foreach ($sectors as $sector) {
            CompanySector::updateOrCreate(
                ['name' => $sector['name']],
                ['description' => $sector['description']]
            );
        }
    }
}
