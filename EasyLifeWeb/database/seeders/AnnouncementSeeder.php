<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        if (Announcement::exists()) {
            return;
        }

        Announcement::create([
            'created_by' => User::where('role', 'admin')->value('id'),
            'title' => '¡Bienvenido a EasyLife!',
            'body' => 'Organiza tus eventos, tareas y proyectos. Ahora también puedes trabajar sin conexión.',
            'level' => 'success',
            'published_at' => now(),
        ]);
    }
}
