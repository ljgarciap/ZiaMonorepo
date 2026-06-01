<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmissionCategory;

class EmissionCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hierarchy = [
            1 => [ // Alcance 1
                'Fuentes Móviles' => [
                    'Fuentes Móviles - Combustibles',
                    'Fuentes Móviles - Gases',
                    'Fuentes Móviles - Extintores',
                    'Fuentes Móviles - Lubricantes',
                ],
                'Emisiones Fugitivas' => [
                    'Emisiones Fugitivas - Refrigerantes',
                ],
                'Fuentes Fijas' => [
                    'Fuentes Fijas - Combustibles Sólidos',
                    'Fuentes Fijas - Combustibles Líquidos',
                    'Fuentes Fijas - Combustibles Gaseosos',
                ],
            ],
            2 => [ // Alcance 2
                'Energía Adquirida' => [
                    'Electricidad - Red',
                ],
            ],
            3 => [ // Alcance 3
                'Otras Fuentes Indirectas' => [
                    'Viajes Aéreos',
                    'Trabajo Remoto',
                ],
            ],
        ];

        foreach ($hierarchy as $scopeId => $parents) {
            foreach ($parents as $parentName => $subcategories) {
                // Create or find parent
                $parent = EmissionCategory::updateOrCreate(
                    ['name' => $parentName, 'scope_id' => $scopeId],
                    ['description' => "Categoría principal de $parentName"]
                );

                foreach ($subcategories as $subName) {
                    // Create or find subcategory exactly as named originally
                    EmissionCategory::updateOrCreate(
                        ['name' => $subName],
                        ['parent_id' => $parent->id, 'scope_id' => $scopeId]
                    );
                }
            }
        }

        $this->command->info('✅ Hierarchical emission categories created successfully');
    }
}
