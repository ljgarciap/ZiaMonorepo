<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CompanyDocument>
 */
class CompanyDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id'  => \App\Models\Company::factory(),
            'uploaded_by' => \App\Models\User::factory(),
            'title'       => $this->faker->word() . '.pdf',
            'file_path'   => 'company_documents/' . $this->faker->uuid() . '.pdf',
            'mime_type'   => 'application/pdf',
            'status'      => 'processed',
        ];
    }
}
