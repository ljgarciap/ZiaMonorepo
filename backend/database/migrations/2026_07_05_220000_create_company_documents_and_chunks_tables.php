<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // pgvector habilitado para uso futuro (columna nativa `vector` + índice ANN
        // si el volumen de chunks lo justifica). Por ahora los embeddings se guardan
        // como JSON (columna `embedding`) para que la misma migración/tests corran
        // igual en sqlite (suite de PHPUnit) y en Postgres — la similitud de coseno
        // se calcula en PHP sobre el set ya acotado por company_id, no a nivel SQL.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        }

        Schema::create('company_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('file_path');
            $table->string('mime_type');
            $table->string('status')->default('pending'); // pending|processing|processed|failed
            $table->text('error_message')->nullable();
            $table->timestamps();
            // Sin softDeletes a propósito: ver nota en App\Models\CompanyDocument.
        });

        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('content');
            $table->json('embedding'); // array de floats (mistral-embed, 1024 dim)
            $table->timestamps();

            $table->index(['company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
        Schema::dropIfExists('company_documents');
    }
};
