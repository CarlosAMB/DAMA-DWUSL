<?php

namespace App\Http\Controllers\Api;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends ResourceController
{
    protected string $model = Task::class;
    protected array $dateFields = ['due_date', 'completed_at'];
    protected array $with = ['tags:id', 'subtasks'];
    protected string $orderBy = 'due_date';

    /** Filtros: ?status=pendiente&priority=alta&project_id=...&tag_id=...&event_id=... */
    protected function applyFilters(Builder $query, Request $request): void
    {
        foreach (['status', 'priority', 'project_id', 'event_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('tag_id')) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $request->tag_id));
        }
    }

    protected function prepare(array $data): array
    {
        $data = parent::prepare($data);

        // Mantiene coherente la fecha de finalizacion con el estado
        if (($data['status'] ?? null) === 'completada' && empty($data['completed_at'])) {
            $data['completed_at'] = now();
        } elseif (isset($data['status']) && $data['status'] !== 'completada') {
            $data['completed_at'] = null;
        }

        return $data;
    }

    protected function afterSave(Model $model, array $data): void
    {
        if (array_key_exists('tag_ids', $data)) {
            $model->tags()->sync($data['tag_ids'] ?? []);
        }
    }

    protected function transform(Model $model): array
    {
        $array = $model->toArray();
        $array['tag_ids'] = $model->tags->pluck('id')->values();
        unset($array['tags']);

        return $array;
    }

    /** PATCH /tasks/{id}/complete  -> alterna completada / pendiente */
    public function toggleComplete(Request $request, string $id): JsonResponse
    {
        $task = $this->findOwned($request, $id);
        $done = $task->status !== 'completada';
        $task->status = $done ? 'completada' : 'pendiente';
        $task->completed_at = $done ? now() : null;
        $task->client_updated_at = now();
        $task->save();

        return response()->json(['data' => $this->transform($task->fresh($this->with))]);
    }
}
