<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carbon_emissions', function (Blueprint $table) {
            $table->string('validation_status', 20)->default('pending')->after('notes');
            $table->text('validation_notes')->nullable()->after('validation_status');
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete()->after('validation_notes');
            $table->timestamp('validated_at')->nullable()->after('validated_by');
        });
    }

    public function down(): void
    {
        Schema::table('carbon_emissions', function (Blueprint $table) {
            $table->dropForeign(['validated_by']);
            $table->dropColumn(['validation_status', 'validation_notes', 'validated_by', 'validated_at']);
        });
    }
};
