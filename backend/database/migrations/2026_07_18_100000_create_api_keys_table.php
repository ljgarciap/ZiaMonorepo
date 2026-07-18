<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name'); // etiqueta libre, ej. "Integración equipo IoT"
            // Prefijo visible (8 chars) para que el admin identifique la key en
            // la UI sin poder reconstruir la key completa a partir de él.
            $table->string('key_prefix', 8);
            // Solo se guarda el hash — la key en texto plano se muestra una
            // única vez al crearla y nunca se puede recuperar después (mismo
            // patrón que tokens de Sanctum/GitHub/Stripe).
            $table->string('key_hash')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
