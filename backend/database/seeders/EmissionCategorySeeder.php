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
        // Scope 3 category numbers per GHG Protocol Corporate Value Chain Standard (15 categories).
        // Subcategories carry the number of their parent GHG Protocol category.
        $scope3Numbers = [
            'Viajes Aéreos'   => 6,  // Cat. 6 — Business Travel
            'Trabajo Remoto'  => 7,  // Cat. 7 — Employee Commuting (home-working subset)
            'Consumo de Agua' => 5,  // Cat. 5 — Waste Generated in Operations (water treatment)
            'Residuos Sólidos' => 5, // Cat. 5 — Waste Generated in Operations
        ];

        $hierarchy = [
            1 => [ // Alcance 1
                'Fuentes Móviles' => [
                    'Fuentes Móviles - Combustibles',
                    'Fuentes Móviles - Gases',
                    'Fuentes Móviles - Lubricantes',
                ],
                'Emisiones Fugitivas' => [
                    // Split by source type: buildings A/C vs. refrigerated fleets
                    'Emisiones Fugitivas - Refrigerantes Fijas',
                    'Emisiones Fugitivas - Refrigerantes Móviles',
                    'Emisiones Fugitivas - Extintores',
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
                'Agua y Residuos' => [
                    'Consumo de Agua',
                    'Residuos Sólidos',
                ],
            ],
        ];

        foreach ($hierarchy as $scopeId => $parents) {
            foreach ($parents as $parentName => $subcategories) {
                $parent = EmissionCategory::updateOrCreate(
                    ['name' => $parentName, 'scope_id' => $scopeId],
                    ['description' => "Categoría principal de $parentName"]
                );

                foreach ($subcategories as $subName) {
                    EmissionCategory::updateOrCreate(
                        ['name' => $subName],
                        [
                            'parent_id'              => $parent->id,
                            'scope_id'               => $scopeId,
                            'scope3_category_number' => $scope3Numbers[$subName] ?? null,
                        ]
                    );
                }
            }
        }

        $this->command->info('✅ Hierarchical emission categories created successfully');
    }
}
