<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventType;
use App\Models\Project;
use App\Models\Reminder;
use App\Models\Subtask;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Services\Firebase\FirestoreMirror;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Historial de demostracion: genera actividad repartida desde finales de septiembre hasta hoy,
 * para que el panel (dashboard y reportes) y la app de la cuenta demo se vean con uso real.
 *
 *   php artisan app:demo-history           crea el historial (si ya existe, no hace nada)
 *   php artisan app:demo-history --borrar  elimina lo generado (usuarios simulados y datos de la demo)
 *
 * - Usuarios simulados: los 5 de la lista (esos correos no se usan para enviar nada).
 * - Los registros generados tienen un id que empieza con "dededede-" para poder borrarlos.
 * - created_at va en el pasado (lo usan los reportes); updated_at queda en "ahora" para que
 *   los telefonos que ya sincronizaron tambien reciban estos datos.
 */
class DemoHistory extends Command
{
    protected $signature = 'app:demo-history {--borrar : Elimina el historial generado}';

    protected $description = 'Genera (o borra) actividad de demostración desde septiembre hasta hoy';

    private const PREFIX = 'dededede-';

    private const TZ = 'America/El_Salvador';

    private const START = '2026-09-25';

    /** Usuarios simulados: correo => [nombre, fecha de registro, perfil] */
    private const PEOPLE = [
        'joaquin.gonz@gmail.com' => ['Joaquín González', '2026-09-29', 'trabajo'],
        'fernandajuarez288@gmail.com' => ['Fernanda Juárez', '2026-10-06', 'universidad'],
        'm.gersonmar22@gmail.com' => ['Gerson Martínez', '2026-10-15', 'freelance'],
        'sebasmbuenavista@gmail.com' => ['Sebastián Buenavista', '2026-10-27', 'familia'],
        'chamba12mendez@gmail.com' => ['Carla Méndez', '2026-11-05', 'negocio'],
    ];

    private const PROJECTS = [
        'demo' => [['Portafolio personal', '#0EA5E9'], ['Curso de inglés B2', '#F59E0B']],
        'trabajo' => [['Presentación trimestral', '#2563EB'], ['Mudanza de apartamento', '#F97316']],
        'universidad' => [['Investigación de Biología', '#10B981'], ['Viaje a Suchitoto', '#EC4899']],
        'freelance' => [['App de inventario (cliente)', '#6366F1'], ['Rutina de gimnasio', '#EF4444']],
        'familia' => [['Boda de mi hermana', '#D946EF'], ['Certificación AWS', '#F59E0B']],
        'negocio' => [['Emprendimiento de repostería', '#F97316'], ['Remodelación de cocina', '#84CC16']],
    ];

    private const TASKS = [
        'demo' => ['Repasar guía de Diseño de Apps', 'Practicar listening 30 min', 'Actualizar CV', 'Subir proyecto a GitHub', 'Leer capítulo de React Native', 'Hacer ejercicios de SQL', 'Diseñar logo del portafolio', 'Grabar video de presentación', 'Estudiar para el parcial', 'Comprar cuaderno', 'Pagar internet', 'Escribir resumen de la clase', 'Revisar correo de la universidad', 'Configurar Firebase', 'Preparar exposición en grupo'],
        'trabajo' => ['Enviar informe semanal', 'Revisar presupuesto', 'Llamar a la empresa de mudanzas', 'Empacar libros', 'Preparar diapositivas', 'Reunión 1 a 1 con el equipo', 'Responder correos pendientes', 'Cotizar muebles', 'Cambiar dirección en el banco', 'Actualizar indicadores'],
        'universidad' => ['Leer artículo científico', 'Tomar muestras en laboratorio', 'Redactar marco teórico', 'Reservar hostal', 'Comprar boletos de bus', 'Estudiar para examen de Química', 'Entregar tarea de Estadística', 'Revisar bibliografía', 'Preparar presentación de avance'],
        'freelance' => ['Definir requerimientos con el cliente', 'Diseñar base de datos', 'Programar módulo de productos', 'Enviar factura', 'Ir al gimnasio (piernas)', 'Ir al gimnasio (espalda)', 'Probar la app en Android', 'Corregir errores reportados', 'Planificar comidas de la semana'],
        'familia' => ['Confirmar invitados', 'Cotizar fotógrafo', 'Ensayar discurso', 'Estudiar módulo de EC2', 'Hacer examen de práctica', 'Comprar traje', 'Pagar salón', 'Repasar IAM y S3', 'Llevar el carro al taller'],
        'negocio' => ['Comprar ingredientes', 'Publicar promoción en Instagram', 'Entregar pedido de pasteles', 'Cotizar azulejos', 'Llamar al fontanero', 'Calcular costos por producto', 'Diseñar menú', 'Pagar a proveedor', 'Elegir pintura para la cocina'],
    ];

    private const EVENTS = [
        'Reunión' => ['Reunión de equipo', 'Reunión con cliente', 'Reunión de seguimiento'],
        'Cita' => ['Cita con el dentista', 'Cita médica', 'Cita en el banco'],
        'Cumpleaños' => ['Cumpleaños de mamá', 'Cumpleaños de Andrea', 'Cumpleaños de papá'],
        'Estudio' => ['Examen parcial', 'Sesión de estudio en grupo', 'Clase de inglés'],
        'Trabajo' => ['Entrega de reporte', 'Capacitación', 'Presentación de avances'],
        'Personal' => ['Cine con amigos', 'Almuerzo familiar', 'Partido de fútbol'],
        'Salud' => ['Clase de yoga', 'Chequeo general', 'Correr en el parque'],
        'Otro' => ['Trámite en la alcaldía', 'Mantenimiento del carro', 'Feria de emprendimiento'],
    ];

    private const PLACES = ['Laboratorio 3', 'Oficina central', 'Metrocentro', 'Clínica Santa Fe', 'Biblioteca ITCA', 'Casa', 'Parque Cuscatlán', 'Café del centro', null, null];

    private int $seq = 0;

    public function handle(FirestoreMirror $mirror): int
    {
        if ($this->option('borrar')) {
            return $this->wipe($mirror);
        }

        if (Task::withTrashed()->where('id', 'like', self::PREFIX.'%')->exists()) {
            $this->info('El historial de demostración ya existe: no se vuelve a crear.');

            return self::SUCCESS;
        }

        $demo = User::where('email', 'demo@easylife.test')->first();
        if (! $demo) {
            $this->error('No existe la cuenta demo@easylife.test (ejecuta primero php artisan db:seed).');

            return self::FAILURE;
        }

        mt_srand(2026);
        $types = EventType::where('is_active', true)->get()->keyBy('name');
        $today = Carbon::now(self::TZ)->startOfDay();

        $demo->forceFill(['created_at' => Carbon::parse(self::START.' 09:15', self::TZ)->utc(), 'last_login_at' => now()])->saveQuietly();
        $totals = $this->generate($demo, 'demo', Carbon::parse(self::START, self::TZ), $today, 34, 16, $types);

        foreach (self::PEOPLE as $email => [$name, $since, $profile]) {
            $start = Carbon::parse($since, self::TZ);
            $user = User::firstOrNew(['email' => $email]);
            $user->forceFill([
                'name' => $name,
                'password' => Str::random(40), // no se usa: son cuentas simuladas
                'role' => 'user',
                'is_active' => true,
                'preferences' => User::DEFAULT_PREFERENCES,
                'privacy_accepted_at' => $start->copy()->setTime(mt_rand(8, 21), mt_rand(0, 59))->utc(),
                'created_at' => $start->copy()->setTime(mt_rand(8, 21), mt_rand(0, 59))->utc(),
                'last_login_at' => now()->subDays(mt_rand(0, 6))->subHours(mt_rand(0, 12)),
            ])->save();

            $days = max(1, $start->diffInDays($today));
            $sub = $this->generate($user, $profile, $start, $today, (int) min(26, 8 + $days * 0.4), (int) min(12, 4 + $days * 0.15), $types);
            foreach ($sub as $k => $v) {
                $totals[$k] += $v;
            }
        }

        $mirror->flush(); // sube a Firestore lo de la cuenta demo (la unica vinculada a Firebase)
        $this->info("Historial creado: 5 usuarios simulados, {$totals['projects']} proyectos, {$totals['tasks']} tareas, {$totals['events']} eventos.");

        return self::SUCCESS;
    }

    private function generate(User $user, string $profile, Carbon $start, Carbon $today, int $taskCount, int $eventCount, $types): array
    {
        // Hora aleatoria del dia, nunca en el futuro
        $at = function (Carbon $day) {
            $t = $day->copy()->setTime(mt_rand(7, 21), mt_rand(0, 59))->utc();

            return $t->isFuture() ? now()->subMinutes(mt_rand(5, 240)) : $t;
        };
        $randomDay = function () use ($start, $today) {
            $span = max(1, $start->diffInDays($today));

            return $start->copy()->addDays(mt_rand(0, $span));
        };

        // Etiquetas
        $tagIds = [];
        foreach ([['Urgente', '#EF4444'], ['Personal', '#10B981'], ['Pendiente de pago', '#F59E0B']] as [$name, $color]) {
            $tag = new Tag(['name' => $name, 'color' => $color]);
            $tag->forceFill(['id' => $this->id(), 'user_id' => $user->id, 'client_updated_at' => now(), 'created_at' => $at($start)])->save();
            $tagIds[] = $tag->id;
        }

        // Proyectos
        $projects = [];
        foreach (self::PROJECTS[$profile] as $i => [$name, $color]) {
            $created = $start->copy()->addDays($i * 3);
            $project = new Project(['name' => $name, 'color' => $color, 'status' => 'activo', 'due_date' => $today->copy()->addDays(mt_rand(10, 45))->toDateString()]);
            $project->forceFill(['id' => $this->id(), 'user_id' => $user->id, 'client_updated_at' => now(), 'created_at' => $at($created)])->save();
            $projects[] = $project;
        }

        // Eventos (pasados y algunos proximos)
        $eventIds = [];
        for ($i = 0; $i < $eventCount; $i++) {
            $typeName = array_rand(self::EVENTS);
            $title = self::EVENTS[$typeName][array_rand(self::EVENTS[$typeName])];
            $day = $i < $eventCount - 3 ? $randomDay() : $today->copy()->addDays(mt_rand(1, 20));
            $allDay = $typeName === 'Cumpleaños';
            $startAt = $allDay ? $day->copy()->startOfDay() : $day->copy()->setTime(mt_rand(8, 18), [0, 30][mt_rand(0, 1)]);
            $created = $startAt->copy()->subDays(mt_rand(1, 6));
            if ($created->lt($start)) {
                $created = $start->copy();
            }
            $event = new Event([
                'event_type_id' => $types[$typeName]->id ?? null,
                'title' => $title,
                'location' => $allDay ? null : self::PLACES[array_rand(self::PLACES)],
                'start_at' => $startAt->copy()->utc(),
                'end_at' => $allDay ? $startAt->copy()->endOfDay()->utc() : $startAt->copy()->addMinutes([60, 90, 120][mt_rand(0, 2)])->utc(),
                'all_day' => $allDay,
            ]);
            $event->forceFill(['id' => $this->id(), 'user_id' => $user->id, 'client_updated_at' => now(), 'created_at' => $at($created)])->save();
            $eventIds[] = $event->id;

            if ($startAt->isFuture() && ! $allDay) {
                $reminder = new Reminder(['remindable_type' => 'event', 'remindable_id' => $event->id, 'minutes_before' => 30, 'remind_at' => $startAt->copy()->subMinutes(30)->utc()]);
                $reminder->forceFill(['id' => $this->id(), 'user_id' => $user->id, 'client_updated_at' => now(), 'created_at' => $at($created)])->save();
            }
        }

        // Tareas: las viejas casi todas completadas, las recientes pendientes
        $titles = self::TASKS[$profile];
        for ($i = 0; $i < $taskCount; $i++) {
            $createdDay = $randomDay();
            $created = $at($createdDay);
            $due = $createdDay->copy()->addDays(mt_rand(1, 10))->setTime([9, 12, 17, 20][mt_rand(0, 3)], 0);
            $old = $due->lt($today->copy()->subDays(1));
            $done = $old ? mt_rand(1, 100) <= 95 : mt_rand(1, 100) <= 15;
            $status = $done ? 'completada' : ($old ? 'pendiente' : (mt_rand(1, 100) <= 30 ? 'en_progreso' : 'pendiente'));
            $completedAt = null;
            if ($done) {
                $completedAt = $created->copy()->addHours(mt_rand(2, max(3, (int) $created->diffInHours($due->copy()->utc(), true) + 6)));
                if ($completedAt->isFuture()) {
                    $completedAt = now()->subHours(mt_rand(1, 6));
                }
            }
            $priority = ['baja', 'media', 'media', 'alta', 'alta', 'urgente'][mt_rand(0, 5)];

            $task = new Task([
                'project_id' => mt_rand(1, 100) <= 55 ? $projects[array_rand($projects)]->id : null,
                'event_id' => mt_rand(1, 100) <= 15 && $eventIds ? $eventIds[array_rand($eventIds)] : null,
                'title' => $titles[array_rand($titles)],
                'priority' => $priority,
                'status' => $status,
                'due_date' => $due->copy()->utc(),
                'completed_at' => $completedAt,
            ]);
            $task->forceFill(['id' => $this->id(), 'user_id' => $user->id, 'client_updated_at' => now(), 'created_at' => $created])->save();
            $task->tags()->sync(collect($tagIds)->random(mt_rand(0, 2))->all());

            if (mt_rand(1, 100) <= 30) {
                foreach (['Investigar', 'Hacer borrador', 'Revisar y entregar'] as $pos => $step) {
                    $sub = new Subtask(['task_id' => $task->id, 'title' => $step, 'is_done' => $done || mt_rand(0, 1) === 1, 'position' => $pos]);
                    $sub->forceFill(['id' => $this->id(), 'user_id' => $user->id, 'client_updated_at' => now(), 'created_at' => $created])->save();
                }
            }
        }

        return ['projects' => count($projects), 'tasks' => $taskCount, 'events' => $eventCount];
    }

    private function wipe(FirestoreMirror $mirror): int
    {
        $count = 0;
        foreach ([Reminder::class, Subtask::class, Task::class, Event::class, Project::class, Tag::class] as $class) {
            // Borrado logico: asi la eliminacion tambien llega a los telefonos al sincronizar
            foreach ($class::where('id', 'like', self::PREFIX.'%')->get() as $model) {
                $model->client_updated_at = now();
                $model->save();
                $model->delete();
                $count++;
            }
        }
        $users = User::whereIn('email', array_keys(self::PEOPLE))->get();
        foreach ($users as $user) {
            $user->delete(); // sus datos se borran en cascada
        }
        $mirror->flush();
        $this->info("Historial eliminado: {$count} registros de la cuenta demo y {$users->count()} usuarios simulados.");

        return self::SUCCESS;
    }

    /** UUID valido con prefijo fijo para identificar lo generado por este comando. */
    private function id(): string
    {
        $this->seq++;

        return sprintf('%s%04x-4%03x-8%03x-%012x', self::PREFIX, $this->seq, mt_rand(0, 0xFFF), mt_rand(0, 0xFFF), mt_rand(0, 0xFFFFFFFFFFFF));
    }
}
