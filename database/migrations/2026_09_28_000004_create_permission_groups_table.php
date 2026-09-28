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
        Schema::create('permission_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 100)->unique();
            $table->string('name', 150);
            $table->string('description', 255)->nullable();
            $table->string('icon', 100)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::table('permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('permissions', 'permission_group_id')) {
                $table->foreignId('permission_group_id')
                    ->nullable()
                    ->after('uuid')
                    ->constrained('permission_groups')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('permissions', 'sort_order')) {
                $table->unsignedInteger('sort_order')
                    ->default(0)
                    ->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            if (Schema::hasColumn('permissions', 'permission_group_id')) {
                $table->dropForeign(['permission_group_id']);
                $table->dropColumn('permission_group_id');
            }

            if (Schema::hasColumn('permissions', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });

        Schema::dropIfExists('permission_groups');
    }
};
