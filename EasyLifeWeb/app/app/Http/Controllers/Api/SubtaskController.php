<?php

namespace App\Http\Controllers\Api;

use App\Models\Subtask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SubtaskController extends ResourceController
{
    protected string $model = Subtask::class;
    protected string $orderBy = 'position';

    /** ?task_id=... */
    protected function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('task_id')) {
            $query->where('task_id', $request->task_id);
        }
    }
}
