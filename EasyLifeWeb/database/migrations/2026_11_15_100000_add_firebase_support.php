<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integracion con Firebase:
 *  - users.firebase_uid: cuenta de Firebase Authentication vinculada
 *  - users.data_key: llave AES-256 propia de cada usuario (cifrada con APP_KEY) para los campos
 *    sensibles que se guardan en Firestore y en el SQLite del telefono
 *  - device_tokens: tokens FCM de los telefonos para enviar notificaciones push
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('firebase_uid', 128)->nullable()->unique()->after('email');
            $table->text('data_key')->nullable()->after('google_synced_at');
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 512)->unique();
            $table->string('platform', 20)->default('android');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['firebase_uid']);
            $table->dropColumn(['firebase_uid', 'data_key']);
        });
    }
};
