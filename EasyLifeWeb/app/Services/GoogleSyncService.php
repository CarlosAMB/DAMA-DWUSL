<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sincronizacion bidireccional EasyLife <-> Google.
 *  - Eventos  <-> Google Calendar (calendario principal)
 *  - Tareas   <-> Google Tasks (lista por defecto)
 *
 * Orden de cada ejecucion:
 *  1. PULL: se traen los cambios hechos en Google desde la ultima sincronizacion.
 *  2. PUSH: se envian a Google los registros de EasyLife que cambiaron desde la ultima sincronizacion
 *     (sin reenviar los que se acaban de traer).
 * Conflictos: gana el cambio mas reciente (fecha "updated" de Google vs client_updated_at local).
 * Se compara el contenido antes de guardar para no generar cambios en ciclo.
 */
class GoogleSyncService
{
    private const EVENTS_URL = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';
    private const TASKS_URL = 'https://tasks.googleapis.com/tasks/v1/lists/@default/tasks';
    private const MAX_PAGES = 10;

    /** Cada cuanto se sincroniza automaticamente con Google al llamar /sync (si no hubo cambios locales). */
    public const AUTO_INTERVAL_SECONDS = 120;

    private array $stats;
    private array $pulledIds;

    public function __construct(private GoogleClient $google)
    {
    }

    public function isConnected(User $user): bool
    {
        return GoogleClient::configured() && filled($user->google_refresh_token);
    }

    /** Decide si /sync debe sincronizar tambien con Google en esta llamada. */
    public function shouldAutoSync(User $user, bool $hadLocalChanges): bool
    {
        if (! $this->isConnected($user)) {
            return false;
        }

        return $hadLocalChanges
            || ! $user->google_synced_at
            || $user->google_synced_at->lt(now()->subSeconds(self::AUTO_INTERVAL_SECONDS));
    }

    public function sync(User $user): array
    {
        $since = $user->google_synced_at;
        $this->stats = ['events_pulled' => 0, 'events_pushed' => 0, 'tasks_pulled' => 0, 'tasks_pushed' => 0, 'errors' => 0];
        $this->pulledIds = ['events' => [], 'tasks' => []];

        $this->pullEvents($user, $since);
        $this->pushEvents($user, $since);
        $this->pullTasks($user, $since);
        $this->pushTasks($user, $since);

        $user->forceFill(['google_synced_at' => now()])->save();

        return $this->stats;
    }

    // =====================================================================
    // EVENTOS <-> GOOGLE CALENDAR
    // =====================================================================

    private function pullEvents(User $user, ?CarbonInterface $since): void
    {
        $params = ['singleEvents' => 'true', 'maxResults' => 250];
        if ($since) {
            // Un minuto de margen por diferencias de reloj; los duplicados se descartan comparando contenido
            $params += ['updatedMin' => $since->copy()->subMinute()->toRfc3339String(), 'showDeleted' => 'true'];
        } else {
            // Primera vez: del ultimo mes al proximo anio
            $params += ['timeMin' => now()->subDays(30)->toRfc3339String(), 'timeMax' => now()->addYear()->toRfc3339String()];
        }

        foreach ($this->paginate($user, self::EVENTS_URL, $params) as $item) {
            $local = $this->findLocal(Event::class, 'google_event_id', $user, $item['id'], $item['extendedProperties']['private']['easylife_id'] ?? null);

            if (($item['status'] ?? null) === 'cancelled') {
                if ($local && ! $local->trashed()) {
                    $local->client_updated_at = now();
                    $local->save();
                    $local->delete();
                    $this->stats['events_pulled']++;
                }

                continue;
            }

            $data = $this->eventFromGoogle($user, $item);
            if (! $data || $this->isLocalNewer($local, $item) || ($local && $this->sameEvent($local, $data))) {
                continue;
            }

            $event = $local ?? new Event(['id' => (string) Str::uuid()]);
            $event->fill($data);
            $event->user_id = $user->id;
            $event->google_event_id = $item['id'];
            $event->client_updated_at = Carbon::parse($item['updated'] ?? now())->utc();
            $event->save();

            $this->pulledIds['events'][] = $event->id;
            $this->stats['events_pulled']++;
        }
    }

    private function pushEvents(User $user, ?CarbonInterface $since): void
    {
        foreach ($this->pendingLocal(Event::class, 'google_event_id', $user, $since, $this->pulledIds['events']) as $event) {
            $this->guard(function () use ($user, $event) {
                if ($event->trashed()) {
                    $this->deleteRemote($user, self::EVENTS_URL.'/'.$event->google_event_id);
                    $this->setRemoteId('events', $event->id, 'google_event_id', null);

                    return;
                }

                $body = $this->eventToGoogle($user, $event);
                $id = $this->upsertRemote($user, self::EVENTS_URL, $event->google_event_id, $body);
                $this->setRemoteId('events', $event->id, 'google_event_id', $id);
                $this->stats['events_pushed']++;
            });
        }
    }

    private function eventFromGoogle(User $user, array $item): ?array
    {
        $tz = $this->timezone($user);
        $start = $item['start'] ?? [];
        $end = $item['end'] ?? [];

        if (isset($start['date'])) {
            // Todo el dia: en Google la fecha final es exclusiva (el dia siguiente)
            $startAt = Carbon::parse($start['date'], $tz)->startOfDay();
            $endAt = Carbon::parse($end['date'] ?? $start['date'], $tz)->subDay()->setTime(23, 59);
            if ($endAt->lt($startAt)) {
                $endAt = $startAt->copy()->setTime(23, 59);
            }
            $allDay = true;
        } elseif (isset($start['dateTime'])) {
            $startAt = Carbon::parse($start['dateTime']);
            $endAt = isset($end['dateTime']) ? Carbon::parse($end['dateTime']) : null;
            $allDay = false;
        } else {
            return null;
        }

        return [
            'title' => Str::limit(trim($item['summary'] ?? '') ?: '(Sin título)', 150, ''),
            'description' => $this->plainText($item['description'] ?? null, 5000),
            'location' => $this->plainText($item['location'] ?? null, 150),
            'start_at' => $startAt->utc(),
            'end_at' => $endAt?->utc(),
            'all_day' => $allDay,
        ];
    }

    private function eventToGoogle(User $user, Event $event): array
    {
        $tz = $this->timezone($user);
        if ($event->all_day) {
            $start = ['date' => $event->start_at->copy()->tz($tz)->toDateString()];
            $end = ['date' => ($event->end_at ?? $event->start_at)->copy()->tz($tz)->addDay()->toDateString()];
        } else {
            $start = ['dateTime' => $event->start_at->copy()->utc()->toRfc3339String()];
            $end = ['dateTime' => ($event->end_at ?? $event->start_at->copy()->addHour())->copy()->utc()->toRfc3339String()];
        }

        return [
            'summary' => $event->title,
            'description' => $event->description,
            'location' => $event->location,
            'start' => $start,
            'end' => $end,
            // Permite reconocer el evento aunque se pierda el google_event_id
            'extendedProperties' => ['private' => ['easylife_id' => $event->id]],
        ];
    }

    private function sameEvent(Event $local, array $data): bool
    {
        return $local->title === $data['title']
            && (string) $local->description === (string) $data['description']
            && (string) $local->location === (string) $data['location']
            && (bool) $local->all_day === $data['all_day']
            && $local->start_at?->timestamp === $data['start_at']->timestamp
            && $local->end_at?->timestamp === $data['end_at']?->timestamp;
    }

    // =====================================================================
    // TAREAS <-> GOOGLE TASKS
    // =====================================================================

    private function pullTasks(User $user, ?CarbonInterface $since): void
    {
        $params = ['maxResults' => 100, 'showCompleted' => 'true', 'showHidden' => 'true'];
        if ($since) {
            $params += ['updatedMin' => $since->copy()->subMinute()->toRfc3339String(), 'showDeleted' => 'true'];
        }

        foreach ($this->paginate($user, self::TASKS_URL, $params) as $item) {
            $local = $this->findLocal(Task::class, 'google_task_id', $user, $item['id']);

            if (! empty($item['deleted'])) {
                if ($local && ! $local->trashed()) {
                    $local->client_updated_at = now();
                    $local->save();
                    $local->delete();
                    $this->stats['tasks_pulled']++;
                }

                continue;
            }

            $data = $this->taskFromGoogle($user, $item, $local);
            if (! $data || $this->isLocalNewer($local, $item) || ($local && $this->sameTask($user, $local, $data))) {
                continue;
            }

            $task = $local ?? new Task(['id' => (string) Str::uuid(), 'priority' => 'media']);
            $task->fill($data);
            $task->user_id = $user->id;
            $task->google_task_id = $item['id'];
            $task->client_updated_at = Carbon::parse($item['updated'] ?? now())->utc();
            $task->save();

            $this->pulledIds['tasks'][] = $task->id;
            $this->stats['tasks_pulled']++;
        }
    }

    private function pushTasks(User $user, ?CarbonInterface $since): void
    {
        foreach ($this->pendingLocal(Task::class, 'google_task_id', $user, $since, $this->pulledIds['tasks']) as $task) {
            $this->guard(function () use ($user, $task) {
                if ($task->trashed()) {
                    $this->deleteRemote($user, self::TASKS_URL.'/'.$task->google_task_id);
                    $this->setRemoteId('tasks', $task->id, 'google_task_id', null);

                    return;
                }

                $id = $this->upsertRemote($user, self::TASKS_URL, $task->google_task_id, $this->taskToGoogle($user, $task));
                $this->setRemoteId('tasks', $task->id, 'google_task_id', $id);
                $this->stats['tasks_pushed']++;
            });
        }
    }

    private function taskFromGoogle(User $user, array $item, ?Task $local): ?array
    {
        $title = trim($item['title'] ?? '');
        if ($title === '') {
            return null; // Google Tasks permite tareas sin titulo; EasyLife no
        }

        $tz = $this->timezone($user);
        $dueDate = null;
        if (! empty($item['due'])) {
            // Google Tasks solo guarda la fecha (sin hora): se conserva la hora local si el dia no cambio
            $day = substr($item['due'], 0, 10);
            $dueDate = $local?->due_date && $local->due_date->copy()->tz($tz)->toDateString() === $day
                ? $local->due_date->copy()
                : Carbon::parse($day, $tz)->setTime(23, 59)->utc();
        }

        $completed = ($item['status'] ?? '') === 'completed';
        $status = $completed ? 'completada' : (($local && $local->status !== 'completada') ? $local->status : 'pendiente');

        return [
            'title' => Str::limit($title, 150, ''),
            'description' => $this->plainText($item['notes'] ?? null, 5000),
            'due_date' => $dueDate,
            'status' => $status,
            'completed_at' => $completed ? Carbon::parse($item['completed'] ?? now())->utc() : null,
        ];
    }

    private function taskToGoogle(User $user, Task $task): array
    {
        $completed = $task->status === 'completada';

        return [
            'title' => $task->title,
            'notes' => $task->description,
            // Google Tasks ignora la hora: se envia solo el dia (en la zona horaria del usuario)
            'due' => $task->due_date ? $task->due_date->copy()->tz($this->timezone($user))->toDateString().'T00:00:00.000Z' : null,
            'status' => $completed ? 'completed' : 'needsAction',
            'completed' => $completed ? ($task->completed_at ?? now())->copy()->utc()->toRfc3339String() : null,
        ];
    }

    private function sameTask(User $user, Task $local, array $data): bool
    {
        $tz = $this->timezone($user);

        return $local->title === $data['title']
            && (string) $local->description === (string) $data['description']
            && ($local->status === 'completada') === ($data['status'] === 'completada')
            && $local->due_date?->copy()->tz($tz)->toDateString() === $data['due_date']?->copy()->tz($tz)->toDateString();
    }

    // =====================================================================
    // AYUDANTES
    // =====================================================================

    /** Recorre todas las paginas de un listado de Google. */
    private function paginate(User $user, string $url, array $params): \Generator
    {
        $pageToken = null;
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $json = $this->google->request($user)
                ->get($url, $params + array_filter(['pageToken' => $pageToken]))
                ->throw()
                ->json();

            yield from $json['items'] ?? [];

            $pageToken = $json['nextPageToken'] ?? null;
            if (! $pageToken) {
                return;
            }
        }
    }

    private function findLocal(string $class, string $column, User $user, string $remoteId, ?string $localId = null)
    {
        $query = $class::withTrashed()->where('user_id', $user->id);

        return (clone $query)->where($column, $remoteId)->first()
            ?? ($localId && Str::isUuid($localId) ? (clone $query)->whereKey($localId)->first() : null);
    }

    /** Registros de EasyLife que hay que enviar a Google. */
    private function pendingLocal(string $class, string $column, User $user, ?CarbonInterface $since, array $exclude)
    {
        $query = $class::withTrashed()->where('user_id', $user->id)->whereNotIn('id', $exclude);

        if ($since) {
            $query->where('updated_at', '>', $since)
                ->where(fn ($q) => $q->whereNull('deleted_at')->orWhereNotNull($column));
        } else {
            $query->whereNull('deleted_at'); // primera vez: todo lo vigente
        }

        return $query->orderBy('updated_at')->lazy();
    }

    /** El cambio local es mas nuevo que el de Google: se conserva el local (se enviara en el push). */
    private function isLocalNewer($local, array $item): bool
    {
        if (! $local) {
            return false;
        }
        if ($local->trashed()) {
            return true; // eliminado en EasyLife: el push lo borra de Google
        }

        return $local->client_updated_at && isset($item['updated'])
            && $local->client_updated_at->gt(Carbon::parse($item['updated']));
    }

    /** Crea o actualiza en Google; devuelve el id remoto. */
    private function upsertRemote(User $user, string $url, ?string $remoteId, array $body): string
    {
        if ($remoteId) {
            $response = $this->google->request($user)->patch("$url/$remoteId", $body);
            if (! GoogleClient::gone($response)) {
                return $response->throw()->json('id');
            }
            // Lo borraron en Google: se vuelve a crear
        }

        return $this->google->request($user)->post($url, $body)->throw()->json('id');
    }

    private function deleteRemote(User $user, string $url): void
    {
        $response = $this->google->request($user)->delete($url);
        if (! GoogleClient::gone($response)) {
            $response->throw();
        }
    }

    /** Guarda el id de Google sin cambiar updated_at (para no reenviarlo al movil ni a Google). */
    private function setRemoteId(string $table, string $id, string $column, ?string $value): void
    {
        DB::table($table)->where('id', $id)->update([$column => $value]);
    }

    /** Un error en un registro no detiene la sincronizacion de los demas (salvo problemas de acceso). */
    private function guard(callable $fn): void
    {
        try {
            $fn();
        } catch (RequestException $e) {
            if (in_array($e->response->status(), [401, 403], true)) {
                throw $e;
            }
            report($e);
            $this->stats['errors']++;
        }
    }

    private function plainText(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $value))));

        return $text === '' ? null : Str::limit($text, $max, '');
    }

    private function timezone(User $user): string
    {
        return $user->timezone ?: config('app.timezone');
    }

    /** Mensaje entendible para el usuario a partir de un error de Google. */
    public static function errorMessage(\Throwable $e): string
    {
        if ($e instanceof GoogleAuthException) {
            return $e->getMessage();
        }
        if ($e instanceof RequestException) {
            $status = $e->response->status();
            if ($status === 403) {
                $reason = $e->response->json('error.message');

                return 'Google rechazó la solicitud. Verifica que las APIs de Calendar y Tasks estén habilitadas'
                    .($reason ? " ($reason)" : '.');
            }
            if ($status === 401) {
                return 'El acceso a Google no es válido. Vuelve a conectar tu cuenta.';
            }
        }
        if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
            return 'El servidor no pudo comunicarse con Google.';
        }

        return 'No se pudo sincronizar con Google.';
    }
}
