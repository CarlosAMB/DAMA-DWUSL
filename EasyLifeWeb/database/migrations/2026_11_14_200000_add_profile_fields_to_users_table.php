<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos de perfil, rol, preferencias e integracion con Google.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user')->after('password'); // admin | user
            $table->boolean('is_active')->default(true)->after('role');
            $table->string('phone')->nullable()->after('email');
            $table->string('timezone')->default('America/El_Salvador')->after('is_active');
            $table->json('preferences')->nullable()->after('timezone');
            $table->timestamp('privacy_accepted_at')->nullable()->after('preferences');
            $table->timestamp('last_login_at')->nullable()->after('privacy_accepted_at');

            // Tokens de Google Calendar (se guardan encriptados)
            $table->text('google_access_token')->nullable();
            $table->text('google_refresh_token')->nullable();
            $table->timestamp('google_token_expires_at')->nullable();
            $table->string('google_email')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role', 'is_active', 'phone', 'timezone', 'preferences', 'privacy_accepted_at', 'last_login_at',
                'google_access_token', 'google_refresh_token', 'google_token_expires_at', 'google_email',
            ]);
        });
    }
};
