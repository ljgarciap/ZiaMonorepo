<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\EmissionFactor;

class CompanyFactorSeeder extends Seeder
{
    public function run()
    {
        $companies = Company::all();
        $factors = EmissionFactor::all();

        foreach ($companies as $company) {
            foreach ($factors as $factor) {
                // Associate all factors as enabled by default if not already associated
                if (!$company->factors()->where('emission_factor_id', $factor->id)->exists()) {
                    $company->factors()->attach($factor->id, ['is_enabled' => true]);
                }
            }
        }
    }
}
