<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('company_group_members', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained('company_groups')->onDelete('cascade');
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->timestamp('joined_at')->useCurrent();
            $table->primary(['group_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_group_members');
        Schema::dropIfExists('company_groups');
    }
};
