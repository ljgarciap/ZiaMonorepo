<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('auditor_assignments', function (Blueprint $table) {
            $table->id();
            // Spec 1.2.3: "Alcance [del Auditor externo]: Lectura de datos de una empresa
            // y un periodo específico, habilitado por el Superadmin con duración limitada."
            // company_user.expires_at (P1) sigue controlando si el auditor puede *entrar* al
            // contexto de la empresa; esta tabla controla A QUÉ PERIODO exacto tiene acceso.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'period_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditor_assignments');
    }
};
