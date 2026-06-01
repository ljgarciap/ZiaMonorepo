<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\Period;
use App\Models\EmissionFactor;
use App\Models\CarbonEmission;

class CarbonEmissionSeeder extends Seeder
{
    public function run(): void
    {
        // Get companies
        $bucarretes = Company::where('nit', '900123456-1')->first();
        $ecotech = Company::where('nit', '900654321-2')->first();
        $verdes = Company::where('nit', '900987654-3')->first();

        // Seed emissions for each company
        $this->seedBucarretes($bucarretes);
        $this->seedEcoTech($ecotech);
        $this->seedIndustriasVerdes($verdes);

        $this->command->info('✅ Carbon emissions seeded successfully');
    }

    private function seedBucarretes($company)
    {
        // Bucarretes: Construction company with real-based data (2021-2024)
        $periods = Period::where('company_id', $company->id)->get();

        foreach ($periods as $period) {
            $year = $period->year;
            $multiplier = 1 + (($year - 2021) * 0.1); // 10% growth per year

            // Scope 1: Mobile Sources
            $this->createEmission($period->id, 'Gasolina E10 (Mezcla comercial)', 1200 * $multiplier);
            $this->createEmission($period->id, 'Diesel B10', 2500 * $multiplier);

            // Scope 1: Fixed Sources
            $this->createEmission($period->id, 'Gas Natural (Fijo)', 3000 * $multiplier);

            // Scope 2: Electricity
            $this->createEmission($period->id, 'FE Colombia (Interconectado)', 25000 * $multiplier);

            // Scope 3: Only for recent years
            if ($year >= 2023) {
                $this->createEmission($period->id, 'Vuelo Nacional (km)', 5000 * $multiplier);
            }
        }
    }

    private function seedEcoTech($company)
    {
        // EcoTech: Technology company (2023-2024)
        $periods = Period::where('company_id', $company->id)->get();

        foreach ($periods as $period) {
            $year = $period->year;
            $multiplier = 1 + (($year - 2023) * 0.15); // 15% growth

            // Scope 1: Minimal (office operations)
            $this->createEmission($period->id, 'Gasolina E10 (Mezcla comercial)', 300 * $multiplier);
            $this->createEmission($period->id, 'Gas Natural (Fijo)', 500 * $multiplier);

            // Scope 2: High (data centers)
            $this->createEmission($period->id, 'FE Colombia (Interconectado)', 80000 * $multiplier);

            // Scope 3: Business travel and remote work
            $this->createEmission($period->id, 'Vuelo Nacional (km)', 12000 * $multiplier);
            $this->createEmission($period->id, 'Vuelo Internacional (km)', 25000 * $multiplier);
            $this->createEmission($period->id, 'Empleado Remoto (día)', 5000 * $multiplier); // 20 employees * 250 days
        }
    }

    private function seedIndustriasVerdes($company)
    {
        // Industrias Verdes: Energy/Industrial company (2020-2024) - ALL 3 SCOPES
        $periods = Period::where('company_id', $company->id)->get();

        foreach ($periods as $period) {
            $year = $period->year;
            $multiplier = 1 + (($year - 2020) * 0.08); // 8% growth per year

            // Scope 1: High industrial emissions
            $this->createEmission($period->id, 'Diesel B10', 5000 * $multiplier);
            $this->createEmission($period->id, 'Gas Natural (Fijo)', 15000 * $multiplier);
            $this->createEmission($period->id, 'Carbón Mineral', 50 * $multiplier);
            $this->createEmission($period->id, 'R-410A', 2 * $multiplier); // Refrigerant leaks

            // Scope 2: High electricity consumption
            $this->createEmission($period->id, 'FE Colombia (Interconectado)', 150000 * $multiplier);

            // Scope 3: Flights and remote work
            $this->createEmission($period->id, 'Vuelo Nacional (km)', 8000 * $multiplier);
            $this->createEmission($period->id, 'Vuelo Internacional (km)', 15000 * $multiplier);
            $this->createEmission($period->id, 'Empleado Remoto (día)', 2000 * $multiplier);
        }
    }

    private function createEmission($periodId, $factorName, $quantity)
    {
        $factor = EmissionFactor::where('name', $factorName)->first();
        
        if (!$factor) {
            $this->command->warn("⚠️  Factor not found: {$factorName}");
            return;
        }

        $calculatedCo2e = $quantity * floatval($factor->factor_total_co2e);

        CarbonEmission::create([
            'period_id' => $periodId,
            'emission_factor_id' => $factor->id,
            'quantity' => $quantity,
            'calculated_co2e' => $calculatedCo2e,
        ]);
    }
}
