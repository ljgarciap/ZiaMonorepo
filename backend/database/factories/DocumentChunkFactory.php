<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentChunk>
 */
class DocumentChunkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_document_id' => \App\Models\CompanyDocument::factory(),
            'company_id'          => \App\Models\Company::factory(),
            'chunk_index'         => 0,
            'content'             => $this->faker->paragraph(),
            'embedding'           => array_fill(0, 1024, 0.0),
        ];
    }
}
