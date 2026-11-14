<?php

namespace Database\Seeders;

use App\Models\EventType;
use Illuminate\Database\Seeder;

class EventTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Reunión', 'color' => '#3B82F6', 'icon' => 'account-group'],
            ['name' => 'Cita', 'color' => '#8B5CF6', 'icon' => 'calendar-account'],
            ['name' => 'Cumpleaños', 'color' => '#EC4899', 'icon' => 'cake-variant'],
            ['name' => 'Trabajo', 'color' => '#F59E0B', 'icon' => 'briefcase'],
            ['name' => 'Estudio', 'color' => '#10B981', 'icon' => 'school'],
            ['name' => 'Personal', 'color' => '#06B6D4', 'icon' => 'account'],
            ['name' => 'Salud', 'color' => '#EF4444', 'icon' => 'heart-pulse'],
            ['name' => 'Otro', 'color' => '#6B7280', 'icon' => 'dots-horizontal'],
        ];

        foreach ($types as $type) {
            EventType::updateOrCreate(['name' => $type['name']], $type);
        }
    }
}
