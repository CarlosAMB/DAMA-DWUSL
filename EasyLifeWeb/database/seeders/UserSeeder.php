<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuarios de prueba (solo para desarrollo):
 *  - admin@easylife.test / Admin123*   -> panel administrativo
 *  - demo@easylife.test  / Demo123*    -> app movil
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@easylife.test'], [
            'name' => 'Administrador',
            'password' => 'Admin123*',
            'role' => 'admin',
            'privacy_accepted_at' => now(),
        ]);

        User::updateOrCreate(['email' => 'demo@easylife.test'], [
            'name' => 'Usuario Demo',
            'password' => 'Demo123*',
            'role' => 'user',
            'privacy_accepted_at' => now(),
            'preferences' => User::DEFAULT_PREFERENCES,
        ]);

        // La actividad de demostracion (usuarios simulados y datos con fechas pasadas)
        // se genera con: php artisan app:demo-history
    }
}
