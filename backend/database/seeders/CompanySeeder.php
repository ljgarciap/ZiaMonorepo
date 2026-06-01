<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\CompanySector;
use App\Models\Period;
use App\Models\User;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        // Get sectors
        $construction = CompanySector::where('name', 'Construcción')->first();
        $technology = CompanySector::where('name', 'Tecnología')->first();
        $energy = CompanySector::where('name', 'Energía')->first();

        // Company 1: Bucarretes S.A.S. (Real data from Excel)
        $bucarretes = Company::firstOrCreate(
            ['nit' => '900123456-1'],
            [
                'name' => 'Bucarretes S.A.S.',
                'company_sector_id' => $construction->id,
                'address' => 'Calle 123 #45-67, Bogotá D.C., Colombia',
                'phone' => '+57 (1) 234-5678',
                'email' => 'contacto@bucarretes.com',
                'logo_url' => 'https://ui-avatars.com/api/?name=Bucarretes&background=1a237e&color=fff&size=200'
            ]
        );

        // Periods for Bucarretes: 2021-2024
        foreach ([2021, 2022, 2023, 2024] as $year) {
            Period::firstOrCreate(
                ['company_id' => $bucarretes->id, 'year' => $year],
                ['status' => $year === 2024 ? 'active' : 'closed']
            );
        }

        // Company 2: EcoTech Solutions S.A.S. (Real-based data)
        $ecotech = Company::firstOrCreate(
            ['nit' => '900654321-2'],
            [
                'name' => 'EcoTech Solutions S.A.S.',
                'company_sector_id' => $technology->id,
                'address' => 'Carrera 7 #80-45, Edificio Tech Plaza, Bogotá D.C., Colombia',
                'phone' => '+57 (1) 345-6789',
                'email' => 'info@ecotech.com.co',
                'logo_url' => 'https://ui-avatars.com/api/?name=EcoTech&background=00897b&color=fff&size=200'
            ]
        );

        // Periods for EcoTech: 2023-2024
        foreach ([2023, 2024] as $year) {
            Period::firstOrCreate(
                ['company_id' => $ecotech->id, 'year' => $year],
                ['status' => $year === 2024 ? 'active' : 'closed']
            );
        }

        // Company 3: Industrias Verdes Ltda. (Fictional complete data)
        $verdes = Company::firstOrCreate(
            ['nit' => '900987654-3'],
            [
                'name' => 'Industrias Verdes Ltda.',
                'company_sector_id' => $energy->id,
                'address' => 'Zona Industrial Km 5, Vía Medellín-Rionegro, Antioquia, Colombia',
                'phone' => '+57 (4) 456-7890',
                'email' => 'contacto@industriasverdes.com.co',
                'logo_url' => 'https://ui-avatars.com/api/?name=Industrias+Verdes&background=4caf50&color=fff&size=200'
            ]
        );

        // Periods for Industrias Verdes: 2020-2024 (5 years)
        foreach ([2020, 2021, 2022, 2023, 2024] as $year) {
            Period::firstOrCreate(
                ['company_id' => $verdes->id, 'year' => $year],
                ['status' => $year === 2024 ? 'active' : 'closed']
            );
        }

        // Assign 'admin@zia.com' to Bucarretes as 'user' to test Context Switching (Global Admin vs Company User)
        $adminUser = User::where('email', 'admin@zia.com')->first();
        if ($adminUser) {
            $bucarretes->users()->syncWithoutDetaching([
                $adminUser->id => ['role' => 'user', 'is_active' => true]
            ]);
        }

        // Assign 'user@zia.com' to EcoTech for testing standard user flow
        $stdUser = User::where('email', 'user@zia.com')->first();
        if ($stdUser) {
            $ecotech->users()->syncWithoutDetaching([
                $stdUser->id => ['role' => 'user', 'is_active' => true]
            ]);
        }

        $this->command->info('✅ Companies and periods created successfully');
    }
}
