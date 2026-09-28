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
        Schema::create('permission_resources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('permission_group_id')
                ->constrained('permission_groups')
                ->cascadeOnDelete();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('permission_resources')
                ->nullOnDelete();

            $table->string('code', 150)->unique();
            $table->string('name', 150);
            $table->string('description', 255)->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->boolean('is_active')
                ->default(true)
                ->index();

            $table->timestamps();

            $table->index(['permission_group_id', 'sort_order']);
        });

        Schema::table('permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('permissions', 'permission_resource_id')) {
                $table->foreignId('permission_resource_id')
                    ->nullable()
                    ->after('permission_group_id')
                    ->constrained('permission_resources')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            if (Schema::hasColumn('permissions', 'permission_resource_id')) {
                $table->dropForeign(['permission_resource_id']);
                $table->dropColumn('permission_resource_id');
            }
        });

        Schema::dropIfExists('permission_resources');
    }
};
