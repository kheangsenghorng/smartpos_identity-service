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
        Schema::create('role_assignable_roles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('grantor_role_id')
                ->constrained('roles')
                ->cascadeOnDelete();

            $table->foreignId('assignable_role_id')
                ->constrained('roles')
                ->cascadeOnDelete();

            $table->timestamp('created_at')->nullable();

            $table->unique([
                'grantor_role_id',
                'assignable_role_id',
            ], 'role_assignable_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_assignable_roles');
    }
};
