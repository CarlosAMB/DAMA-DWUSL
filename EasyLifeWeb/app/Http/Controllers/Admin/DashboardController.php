<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Dashboard con estadisticas AGREGADAS.
 * Por privacidad el administrador no ve el contenido de tareas o eventos.
 */
class DashboardController extends Controller
{
    public function __invoke()
    {
        $totalTasks = Task::count();
        $completed = Task::where('status', 'completada')->count();

        $stats = [
            'users' => User::where('role', 'user')->count(),
            'active_users' => User::where('role', 'user')->where('last_login_at', '>=', now()->subDays(30))->count(),
            'new_users' => User::where('role', 'user')->where('created_at', '>=', now()->startOfMonth())->count(),
            'events' => Event::count(),
            'tasks' => $totalTasks,
            'projects' => Project::count(),
            'completion' => $totalTasks ? round($completed * 100 / $totalTasks) : 0,
            'overdue' => Task::where('status', '!=', 'completada')->where('due_date', '<', now())->count(),
        ];

        // Registros de los ultimos 6 meses
        $months = collect(range(5, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $registrations = [
            'labels' => $months->map(fn (Carbon $m) => ucfirst($m->locale('es')->translatedFormat('M Y'))),
            'data' => $months->map(fn (Carbon $m) => User::where('role', 'user')
                ->whereBetween('created_at', [$m, $m->copy()->endOfMonth()])->count()),
        ];

        $tasksByStatus = collect(Task::STATUSES)->mapWithKeys(fn ($s) => [$s => Task::where('status', $s)->count()]);
        $tasksByPriority = collect(Task::PRIORITIES)->mapWithKeys(fn ($p) => [$p => Task::where('priority', $p)->count()]);
        $eventsByType = EventType::withCount('events')->orderByDesc('events_count')->get(['id', 'name', 'color']);

        $recentUsers = User::where('role', 'user')->latest()->take(5)->get();

        return view('admin.dashboard', compact(
            'stats', 'registrations', 'tasksByStatus', 'tasksByPriority', 'eventsByType', 'recentUsers'
        ));
    }
}
