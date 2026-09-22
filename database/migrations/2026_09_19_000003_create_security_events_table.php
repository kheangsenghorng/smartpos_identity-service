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
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->uuid('user_uuid')
                ->nullable()
                ->index();

            $table->uuid('business_uuid')
                ->nullable()
                ->index();

            $table->string('event_type', 100)
                ->index();

            $table->string('severity', 20)
                ->default('info');

            $table->string('ip_address', 45)
                ->nullable();

            $table->text('user_agent')
                ->nullable();

            $table->json('metadata')
                ->nullable();

            $table->timestamp('created_at')
                ->nullable()
                ->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
