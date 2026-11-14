<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Project;
use App\Models\Reminder;
use App\Models\Subtask;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Support\Dates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Sincronizacion bidireccional con la app movil (modo sin conexion).
 *
 * 1. PUSH: el movil envia los registros que cambio mientras estaba sin conexion.
 *    Conflictos: gana el cambio mas reciente segun "client_updated_at" (last-write-wins).
 * 2. PULL: el servidor devuelve todo lo que cambio desde "last_synced_at"
 *    (incluye eliminados para que el movil tambien los borre).
 */
class SyncService
{
    /** Orden importante: primero las tablas de las que dependen otras (llaves foraneas). */
    public const TABLES = [
        'projects' => Project::class,
        'tags' => Tag::class,
        'events' => Event::class,
        'tasks' => Task::class,
        'subtasks' => Subtask::class,
        'reminders' => Reminder::class,
    ];

    /** Campos fecha/hora de cada tabla (se convierten a UTC). */
    private const DATE_FIELDS = [
        'events' => ['start_at', 'end_at'],
        'tasks' => ['due_date', 'completed_at'],
        'reminders' => ['remind_at'],
    ];

    public function push(User $user, array $changes): array
    {
        $result = ['applied' => 0, 'skipped' => 0, 'errors' => []];

        foreach (self::TABLES as $table => $modelClass) {
            foreach ($changes[$table] ?? [] as $record) {
                try {
                    $status = DB::transaction(fn () => $this->applyRecord($user, $table, $modelClass, (array) $record));
                    $status === true ? $result['applied']++ : $result['skipped']++;
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $result['errors'][] = ['table' => $table, 'id' => $record['id'] ?? null, 'messages' => $e->errors()];
                } catch (\Throwable $e) {
                    report($e);
                    $result['errors'][] = ['table' => $table, 'id' => $record['id'] ?? null, 'messages' => ['general' => [$e->getMessage()]]];
                }
            }
        }

        return $result;
    }

    /** @return bool true si se aplico, false si se ignoro (el servidor tenia una version mas nueva) */
    private function applyRecord(User $user, string $table, string $modelClass, array $record): bool
    {
        Validator::make($record, [
            'id' => ['required', 'uuid'],
            'client_updated_at' => ['required', 'date'],
            'deleted_at' => ['nullable', 'date'],
        ])->validate();

        $incomingAt = Dates::toUtc($record['client_updated_at']);
        /** @var Model|null $existing */
        $existing = $modelClass::withTrashed()->find($record['id']);

        if ($existing && $existing->user_id !== $user->id) {
            abort(403, 'El registro pertenece a otro usuario');
        }
        if ($existing && $existing->client_updated_at && $existing->client_updated_at->gt($incomingAt)) {
            return false; // el servidor tiene un cambio mas reciente
        }

        // --- Eliminacion ---
        if (! empty($record['deleted_at'])) {
            if ($existing && ! $existing->trashed()) {
                $existing->client_updated_at = $incomingAt;
                $existing->save();
                $existing->delete();
            }

            return true;
        }

        // --- Alta / modificacion ---
        $data = Validator::make($record, $modelClass::rules($user->id))->validate();

        if ($table === 'reminders') {
            $this->assertRemindableOwned($user, $data['remindable_type'], $data['remindable_id']);
        }

        $fields = array_intersect_key($data, array_flip($modelClass::syncFields()));
        foreach (self::DATE_FIELDS[$table] ?? [] as $field) {
            if (array_key_exists($field, $fields)) {
                $fields[$field] = Dates::toUtc($fields[$field]);
            }
        }

        $model = $existing ?? new $modelClass;
        $model->fill($fields);
        $model->id = $record['id'];
        $model->user_id = $user->id;
        $model->client_updated_at = $incomingAt;
        if ($model->exists && $model->trashed()) {
            $model->deleted_at = null; // restaurar
        }
        $model->save();

        if ($table === 'tasks' && array_key_exists('tag_ids', $data)) {
            $model->tags()->sync($data['tag_ids'] ?? []);
        }

        return true;
    }

    private function assertRemindableOwned(User $user, string $type, string $id): void
    {
        $class = $type === 'event' ? Event::class : Task::class;
        if (! $class::withTrashed()->where('user_id', $user->id)->whereKey($id)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['remindable_id' => 'El elemento del recordatorio no existe.']);
        }
    }

    public function pull(User $user, $since): array
    {
        $changes = [];
        foreach (self::TABLES as $table => $modelClass) {
            $query = $modelClass::ownedBy($user->id);
            if ($since) {
                // >= para no perder cambios hechos en el mismo segundo (los duplicados no causan problema)
                $query->withTrashed()->where('updated_at', '>=', $since);
            }
            if ($table === 'tasks') {
                $query->with('tags:id');
            }

            $changes[$table] = $query->get()->map(fn ($m) => self::serialize($table, $m))->values();
        }

        return $changes;
    }

    /** Representacion de un registro tal como la recibe el movil (tambien se usa para el espejo en Firestore). */
    public static function serialize(string $table, Model $model): array
    {
        $array = $model->toArray();
        if ($table === 'tasks') {
            $array['tag_ids'] = $model->tags->pluck('id')->values();
            unset($array['tags']);
        }

        return $array;
    }
}
