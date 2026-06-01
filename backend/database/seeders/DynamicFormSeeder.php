<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DynamicFormSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Scopes
        $scopes = [
            1 => ['name' => 'Alcance 1', 'description' => 'Emisiones directas de fuentes propias o controladas.', 'documentation_text' => 'Se refiere a las emisiones de GEI directas generadas dentro de la organización...'],
            2 => ['name' => 'Alcance 2', 'description' => 'Emisiones indirectas por la generación de electricidad adquirida.', 'documentation_text' => 'Se refiere a las emisiones indirectas de GEI asociadas al consumo de electricidad...'],
            3 => ['name' => 'Alcance 3', 'description' => 'Otras emisiones indirectas en la cadena de valor.', 'documentation_text' => 'Incluye todas las demás emisiones indirectas que ocurren en la cadena de valor...'],
        ];

        foreach ($scopes as $id => $data) {
            \App\Models\Scope::updateOrCreate(['id' => $id], $data);
        }

        // 2. Seed Units
        $units = [
            ['name' => 'Galones', 'symbol' => 'Gal'],
            ['name' => 'Kilogramos', 'symbol' => 'kg'],
            ['name' => 'Metros Cúbicos', 'symbol' => 'm3'],
            ['name' => 'Kilowatt-hora', 'symbol' => 'kWh'],
            ['name' => 'Kilometros', 'symbol' => 'km'],
            ['name' => 'Toneladas', 'symbol' => 'Ton'],
            ['name' => 'Días', 'symbol' => 'día'],
            ['name' => 'Unidades', 'symbol' => 'Unid'],
        ];

        foreach ($units as $unit) {
            \App\Models\MeasurementUnit::firstOrCreate(['symbol' => $unit['symbol']], $unit);
        }

        // 3. Migrate Categories (Link to Scopes)
        $categories = \App\Models\EmissionCategory::all();
        foreach ($categories as $category) {
            // Map old 'scope' column (enum '1','2','3') to scope_id
            if ($category->scope) { // attributes access raw column 'scope' before migration dropped it? no it is still there
                // We haven't dropped the column yet, so we can access it.
                // But Eloquent might ignore it if it's not in fillable or hidden.
                // Let's use getAttribute('scope') to be safe.
                $scopeEnum = $category->getAttribute('scope');
                if ($scopeEnum) {
                    $category->scope_id = (int)$scopeEnum;
                    $category->save();
                }
            }

            // Attempt to create hierarchy
            if (str_contains($category->name, 'Fuentes Móviles - ')) {
                // Find or create parent "Fuentes Móviles"
                $parent = \App\Models\EmissionCategory::firstOrCreate(
                    ['name' => 'Fuentes Móviles', 'scope_id' => 1],
                    ['description' => 'Emisiones de fuentes móviles.']
                );
                $category->parent_id = $parent->id;
                $category->save();
            } elseif (str_contains($category->name, 'Fuentes Fijas - ')) {
                 // Find or create parent "Fuentes Fijas"
                 $parent = \App\Models\EmissionCategory::firstOrCreate(
                    ['name' => 'Fuentes Fijas', 'scope_id' => 1],
                    ['description' => 'Emisiones de fuentes fijas.']
                );
                $category->parent_id = $parent->id;
                $category->save();
            }
        }

        // 4. Migrate Factors (Link to Units)
        $factors = \App\Models\EmissionFactor::all();
        foreach ($factors as $factor) {
            $unitSymbol = $factor->getAttribute('unit'); // accessing the old string column
            if ($unitSymbol) {
                // Handle inconsistency if any (e.g. 'Gal' vs 'gal') - assume seeded data is clean as per EmissionFactorSeeder
                $unitObj = \App\Models\MeasurementUnit::where('symbol', $unitSymbol)->first();
                if ($unitObj) {
                    $factor->measurement_unit_id = $unitObj->id;
                    $factor->save();
                } else {
                     // Create if missing (fallback)
                     $newUnit = \App\Models\MeasurementUnit::create(['name' => $unitSymbol, 'symbol' => $unitSymbol]);
                     $factor->measurement_unit_id = $newUnit->id;
                     $factor->save();
                }
            }
        }
    }
}
