<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Dates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    /**
     * GET /api/v1/calendar?from=...&to=...
     * Devuelve eventos y tareas con fecha limite dentro del rango
     * (sirve para las vistas dia / semana / mes / anio).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from = Dates::toUtc($request->from);
        $to = Dates::toUtc($request->to);
        $user = $request->user();

        $events = $user->events()->with('type')
            ->where('start_at', '<=', $to)
            ->where(fn ($q) => $q->where('end_at', '>=', $from)->orWhere(fn ($q2) => $q2->whereNull('end_at')->where('start_at', '>=', $from)))
            ->orderBy('start_at')->get();

        $tasks = $user->tasks()->whereBetween('due_date', [$from, $to])->orderBy('due_date')->get();

        return response()->json(['events' => $events, 'tasks' => $tasks]);
    }
}
