<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventType;
use App\Models\Project;
use App\Models\Reminder;
use App\Models\Subtask;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Datos de ejemplo para el usuario demo. */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'demo@easylife.test')->first();
        if (! $user || $user->tasks()->exists()) {
            return;
        }

        $types = EventType::pluck('id', 'name');

        $tags = collect([
            ['name' => 'Universidad', 'color' => '#3B82F6'],
            ['name' => 'Casa', 'color' => '#F59E0B'],
            ['name' => 'Urgente', 'color' => '#EF4444'],
            ['name' => 'Ideas', 'color' => '#10B981'],
        ])->map(fn ($t) => Tag::create($t + ['user_id' => $user->id]));

        $proyectoU = Project::create([
            'user_id' => $user->id, 'name' => 'Proyecto final DAMA', 'color' => '#6366F1',
            'description' => 'App de organización personal con React Native y Laravel',
            'due_date' => now()->addWeeks(3)->toDateString(),
        ]);
        $proyectoCasa = Project::create([
            'user_id' => $user->id, 'name' => 'Remodelar habitación', 'color' => '#F97316',
        ]);

        $reunion = Event::create([
            'user_id' => $user->id, 'event_type_id' => $types['Reunión'], 'title' => 'Reunión de equipo',
            'location' => 'Laboratorio 3', 'start_at' => now()->addDay()->setTime(14, 0),
            'end_at' => now()->addDay()->setTime(15, 30),
        ]);
        Event::create([
            'user_id' => $user->id, 'event_type_id' => $types['Cumpleaños'], 'title' => 'Cumpleaños de mamá',
            'start_at' => now()->addDays(5)->startOfDay(), 'all_day' => true,
        ]);
        Event::create([
            'user_id' => $user->id, 'event_type_id' => $types['Cita'], 'title' => 'Cita con el dentista',
            'start_at' => now()->addDays(2)->setTime(9, 0), 'end_at' => now()->addDays(2)->setTime(10, 0),
        ]);

        $t1 = Task::create([
            'user_id' => $user->id, 'project_id' => $proyectoU->id, 'event_id' => $reunion->id,
            'title' => 'Preparar presentación de avances', 'priority' => 'alta',
            'due_date' => now()->addDay()->setTime(12, 0),
        ]);
        $t1->tags()->attach([$tags[0]->id, $tags[2]->id]);
        foreach (['Capturas de pantalla', 'Diagrama de base de datos', 'Demo en emulador'] as $i => $title) {
            Subtask::create(['user_id' => $user->id, 'task_id' => $t1->id, 'title' => $title, 'position' => $i, 'is_done' => $i === 0]);
        }

        $t2 = Task::create([
            'user_id' => $user->id, 'project_id' => $proyectoU->id, 'title' => 'Implementar sincronización offline',
            'priority' => 'urgente', 'status' => 'en_progreso', 'due_date' => now()->addDays(4),
        ]);
        $t2->tags()->attach($tags[0]->id);

        Task::create([
            'user_id' => $user->id, 'project_id' => $proyectoU->id, 'title' => 'Diseñar pantallas',
            'priority' => 'media', 'status' => 'completada', 'completed_at' => now()->subDay(),
        ]);

        $t4 = Task::create([
            'user_id' => $user->id, 'project_id' => $proyectoCasa->id, 'title' => 'Comprar pintura',
            'priority' => 'baja', 'due_date' => now()->addWeek(),
        ]);
        $t4->tags()->attach($tags[1]->id);

        Task::create(['user_id' => $user->id, 'title' => 'Pagar recibo de luz', 'priority' => 'alta', 'due_date' => now()->addDays(2)]);

        Reminder::create([
            'user_id' => $user->id, 'remindable_type' => 'event', 'remindable_id' => $reunion->id,
            'remind_at' => $reunion->start_at->copy()->subMinutes(30), 'minutes_before' => 30,
        ]);
        Reminder::create([
            'user_id' => $user->id, 'remindable_type' => 'task', 'remindable_id' => $t1->id,
            'remind_at' => $t1->due_date->copy()->subHour(), 'minutes_before' => 60,
        ]);
    }
}
