<?php

namespace App\Http\Controllers\Api;

use App\Models\Event;
use App\Support\Dates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class EventController extends ResourceController
{
    protected string $model = Event::class;
    protected array $dateFields = ['start_at', 'end_at'];
    protected array $with = ['type'];
    protected string $orderBy = 'start_at';

    /** Filtros: ?from=2026-11-01&to=2026-11-30&event_type_id=1 */
    protected function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('from')) {
            $query->where(fn ($q) => $q->where('end_at', '>=', Dates::toUtc($request->from))
                ->orWhere(fn ($q2) => $q2->whereNull('end_at')->where('start_at', '>=', Dates::toUtc($request->from))));
        }
        if ($request->filled('to')) {
            $query->where('start_at', '<=', Dates::toUtc($request->to));
        }
        if ($request->filled('event_type_id')) {
            $query->where('event_type_id', $request->event_type_id);
        }
    }
}
