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
        $scope3Numbers = [
            // Existing
            'Viajes Aéreos'                      => 6,
            'Trabajo Remoto'                     => 7,
            'Consumo de Agua'                    => 5,
            'Residuos Sólidos'                   => 5,
            // Sprint 9 — new categories
            'Compras - Servicios Profesionales'  => 1,
            'Compras - Tecnología y Equipos'     => 1,
            'Compras - Materiales y Suministros' => 1,
            'Compras - Alimentos y Bebidas'      => 1,
            'Compras - Papel y Materiales'       => 1,
            'Carga - Terrestre'                  => 4,
            'Carga - Aéreo'                      => 4,
            'Carga - Marítimo'                   => 4,
            'Commuting - Vehículo Propio'        => 7,
            'Commuting - Bus Urbano'             => 7,
            'Commuting - Metro y Tren'           => 7,
            'Commuting - Motocicleta'            => 7,
        ];

        $hierarchy = [
            1 => [ // Alcance 1
                'Fuentes Móviles' => [
                    'Fuentes Móviles - Combustibles',
                    'Fuentes Móviles - Gases',
                    'Fuentes Móviles - Lubricantes',
                ],
                'Emisiones Fugitivas' => [
                    'Emisiones Fugitivas - Refrigerantes Fijas',
                    'Emisiones Fugitivas - Refrigerantes Móviles',
                    'Emisiones Fugitivas - Extintores',
                    'Emisiones Fugitivas - Gases Industriales', // SF6, HFC suppressants
                ],
                'Fuentes Fijas' => [
                    'Fuentes Fijas - Combustibles Sólidos',
                    'Fuentes Fijas - Combustibles Líquidos',
                    'Fuentes Fijas - Combustibles Gaseosos',
                ],
                'Emisiones de Proceso' => [   // GHG from industrial/agricultural processes
                    'Procesos - Fermentación Entérica',
                    'Procesos - Gestión de Estiércol',
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
                'Bienes y Servicios Adquiridos' => [  // Cat. 1 — spend-based method
                    'Compras - Servicios Profesionales',
                    'Compras - Tecnología y Equipos',
                    'Compras - Materiales y Suministros',
                    'Compras - Alimentos y Bebidas',
                    'Compras - Papel y Materiales',
                ],
                'Transporte de Carga' => [            // Cat. 4 — upstream T&D
                    'Carga - Terrestre',
                    'Carga - Aéreo',
                    'Carga - Marítimo',
                ],
                'Transporte de Empleados' => [        // Cat. 7 — employee commuting
                    'Commuting - Vehículo Propio',
                    'Commuting - Bus Urbano',
                    'Commuting - Metro y Tren',
                    'Commuting - Motocicleta',
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
