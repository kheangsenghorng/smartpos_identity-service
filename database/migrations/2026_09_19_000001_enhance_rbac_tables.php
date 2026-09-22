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
        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'module')) {
                $table->string('module', 50)->default('system')->after('code')->index();
            }
            if (! Schema::hasColumn('roles', 'level')) {
                $table->unsignedInteger('level')->default(10)->after('module')->index();
            }
            if (! Schema::hasColumn('roles', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_system');
            }
        });

        Schema::table('permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('permissions', 'resource')) {
                $table->string('resource', 50)->nullable()->after('module')->index();
            }
            if (! Schema::hasColumn('permissions', 'action')) {
                $table->string('action', 50)->nullable()->after('resource')->index();
            }
            if (! Schema::hasColumn('permissions', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('description');
            }
        });

        Schema::table('user_roles', function (Blueprint $table) {
            if (! Schema::hasColumn('user_roles', 'uuid')) {
                $table->uuid('uuid')->nullable()->after('id')->unique();
            }
            if (! Schema::hasColumn('user_roles', 'business_uuid')) {
                $table->uuid('business_uuid')->nullable()->after('role_id')->index();
            }
            if (! Schema::hasColumn('user_roles', 'outlet_uuid')) {
                $table->uuid('outlet_uuid')->nullable()->after('business_uuid')->index();
            }
            if (! Schema::hasColumn('user_roles', 'assigned_by_uuid')) {
                $table->uuid('assigned_by_uuid')->nullable()->after('outlet_uuid');
            }
            if (! Schema::hasColumn('user_roles', 'starts_at')) {
                $table->dateTime('starts_at')->nullable()->after('assigned_by_uuid');
            }
            if (! Schema::hasColumn('user_roles', 'expires_at')) {
                $table->dateTime('expires_at')->nullable()->after('starts_at');
            }
            if (! Schema::hasColumn('user_roles', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('expires_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropColumn([
                'uuid',
                'business_uuid',
                'outlet_uuid',
                'assigned_by_uuid',
                'starts_at',
                'expires_at',
                'is_active',
            ]);
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn([
                'resource',
                'action',
                'is_active',
            ]);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn([
                'module',
                'level',
                'is_active',
            ]);
        });
    }
};
