<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/** Reportes de uso de la plataforma por rango de fechas (datos agregados). */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.reports.index', $this->build($request));
    }

    /** Descarga el reporte en CSV (se abre en Excel). */
    public function export(Request $request)
    {
        $r = $this->build($request);
        $rows = [
            ['Reporte EasyLife', $r['from']->format('Y-m-d').' a '.$r['to']->format('Y-m-d')],
            [],
            ['Indicador', 'Valor'],
            ['Nuevos usuarios', $r['summary']['new_users']],
            ['Eventos creados', $r['summary']['events']],
            ['Tareas creadas', $r['summary']['tasks']],
            ['Tareas completadas', $r['summary']['completed']],
            ['Tasa de finalización (%)', $r['summary']['completion']],
            [],
            ['Eventos por tipo', 'Cantidad'],
            ...$r['eventsByType']->map(fn ($t) => [$t->name, $t->total])->all(),
            [],
            ['Tareas por prioridad', 'Cantidad'],
            ...collect($r['tasksByPriority'])->map(fn ($v, $k) => [ucfirst($k), $v])->values()->all(),
            [],
            ['Usuarios más activos (anónimo)', 'Tareas creadas'],
            ...$r['topUsers']->map(fn ($u) => ['Usuario #'.$u->id, $u->tasks_count])->all(),
        ];

        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);

        return response(stream_get_contents($out), 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename=reporte_easylife_'.now()->format('Ymd').'.csv',
        ]);
    }

    /** Descarga el reporte en PDF (mPDF): mismos datos y rango que la pantalla. */
    public function pdf(Request $request)
    {
        $r = $this->build($request);

        // Tareas creadas por semana (en el PDF se agrupan por semana para que quepan en una pagina)
        $weekly = Task::whereBetween('created_at', [$r['from'], $r['to']])->get(['created_at'])
            ->groupBy(fn ($t) => $t->created_at->copy()->startOfWeek()->format('Y-m-d'))
            ->map->count()->sortKeys();
        $weeks = collect();
        for ($w = $r['from']->copy()->startOfWeek(); $w->lte($r['to']); $w->addWeek()) {
            $weeks->push(['label' => $w->format('d/m').' – '.$w->copy()->endOfWeek()->format('d/m'), 'total' => $weekly[$w->format('Y-m-d')] ?? 0]);
        }

        $html = view('admin.reports.pdf', $r + [
            'weeks' => $weeks,
            'generatedBy' => $request->user()->name,
            'generatedAt' => now(),
        ])->render();

        $tmp = storage_path('app/mpdf');
        if (! is_dir($tmp)) {
            mkdir($tmp, 0775, true);
        }

        $mpdf = new Mpdf([
            'format' => 'Letter',
            'margin_left' => 15, 'margin_right' => 15, 'margin_top' => 15, 'margin_bottom' => 18,
            'default_font' => 'dejavusans',
            'tempDir' => $tmp,
        ]);
        $mpdf->SetTitle('Reporte EasyLife '.$r['from']->format('d/m/Y').' - '.$r['to']->format('d/m/Y'));
        $mpdf->SetAuthor('EasyLife');
        $mpdf->SetFooter('EasyLife · Reporte de uso||Página {PAGENO} de {nbpg}');
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('', Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename=reporte_easylife_'.now()->format('Ymd').'.pdf',
        ]);
    }

    private function build(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : now()->endOfDay();

        $tasks = Task::whereBetween('created_at', [$from, $to]);
        $taskCount = (clone $tasks)->count();
        $completed = (clone $tasks)->where('status', 'completada')->count();

        $summary = [
            'new_users' => User::where('role', 'user')->whereBetween('created_at', [$from, $to])->count(),
            'events' => Event::whereBetween('created_at', [$from, $to])->count(),
            'tasks' => $taskCount,
            'completed' => $completed,
            'completion' => $taskCount ? round($completed * 100 / $taskCount) : 0,
        ];

        $eventsByType = EventType::query()
            ->leftJoin('events', fn ($j) => $j->on('events.event_type_id', '=', 'event_types.id')
                ->whereBetween('events.created_at', [$from, $to])->whereNull('events.deleted_at'))
            ->groupBy('event_types.id', 'event_types.name', 'event_types.color')
            ->selectRaw('event_types.name, event_types.color, COUNT(events.id) as total')
            ->orderByDesc('total')->get();

        $tasksByPriority = collect(Task::PRIORITIES)->mapWithKeys(fn ($p) => [$p => (clone $tasks)->where('priority', $p)->count()]);

        // Actividad diaria (tareas creadas por dia)
        $days = collect();
        for ($d = $from->copy(); $d->lte($to) && $days->count() < 92; $d->addDay()) {
            $days->push($d->copy());
        }
        $daily = [
            'labels' => $days->map(fn ($d) => $d->format('d/m')),
            'data' => $days->map(fn ($d) => Task::whereBetween('created_at', [$d->copy()->startOfDay(), $d->copy()->endOfDay()])->count()),
        ];

        // Ranking anonimo (se muestra el ID, no el nombre) para respetar la privacidad
        $topUsers = User::where('role', 'user')
            ->withCount(['tasks' => fn ($q) => $q->whereBetween('created_at', [$from, $to])])
            ->orderByDesc('tasks_count')->take(5)->get(['id']);

        return compact('from', 'to', 'summary', 'eventsByType', 'tasksByPriority', 'daily', 'topUsers');
    }
}
