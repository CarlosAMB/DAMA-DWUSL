<?php

namespace App\Http\Controllers\Api;

use App\Models\Reminder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReminderController extends ResourceController
{
    protected string $model = Reminder::class;
    protected array $dateFields = ['remind_at'];
    protected string $orderBy = 'remind_at';

    /** ?upcoming=1 solo los recordatorios futuros */
    protected function applyFilters(Builder $query, Request $request): void
    {
        if ($request->boolean('upcoming')) {
            $query->where('remind_at', '>=', now());
        }
    }
}
