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
        Schema::table('security_events', function (Blueprint $table) {
            $table->uuid('session_uuid')
                ->nullable()
                ->after('business_uuid')
                ->index();

            $table->uuid('device_uuid')
                ->nullable()
                ->after('session_uuid')
                ->index();

            $table->string('route', 255)
                ->nullable()
                ->after('user_agent');

            $table->string('http_method', 10)
                ->nullable()
                ->after('route');

            $table->text('description')
                ->nullable()
                ->after('http_method');

            $table->timestamp('occurred_at')
                ->nullable()
                ->after('metadata')
                ->index();

            $table->timestamp('updated_at')
                ->nullable()
                ->after('created_at');

            $table->index('severity');
            $table->index(['user_uuid', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('security_events', function (Blueprint $table) {
            $table->dropIndex(['severity']);
            $table->dropIndex(['user_uuid', 'event_type']);

            $table->dropColumn([
                'session_uuid',
                'device_uuid',
                'route',
                'http_method',
                'description',
                'occurred_at',
                'updated_at',
            ]);
        });
    }
};
