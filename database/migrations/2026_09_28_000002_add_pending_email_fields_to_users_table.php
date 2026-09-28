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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('pending_email_expires_at')
                ->nullable()
                ->after('pending_email');
            $table->string('pending_email_token', 64)
                ->nullable()
                ->after('pending_email_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'pending_email_expires_at',
                'pending_email_token',
            ]);
        });
    }
};
