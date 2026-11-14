<?php

namespace App\Http\Controllers\Api;

use App\Models\Project;
use Illuminate\Database\Eloquent\Model;

class ProjectController extends ResourceController
{
    protected string $model = Project::class;
    protected string $orderBy = 'name';

    protected function transform(Model $model): array
    {
        return $model->toArray() + [
            'progress' => $model->progress,
            'tasks_count' => $model->tasks()->count(),
        ];
    }
}
