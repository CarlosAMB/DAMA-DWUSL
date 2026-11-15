@extends('layouts.app')

@section('title', 'Reportes')

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-sm-4 col-md-3">
                <label class="form-label small">Desde</label>
                <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="form-control">
            </div>
            <div class="col-sm-4 col-md-3">
                <label class="form-label small">Hasta</label>
                <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="form-control">
            </div>
            <div class="col-sm-4 col-md-6 d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-arrow-repeat"></i> Generar</button>
                <a class="btn btn-outline-success" href="{{ route('admin.reports.export', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV
                </a>
                <a class="btn btn-outline-danger" href="{{ route('admin.reports.pdf', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}">
                    <i class="bi bi-file-earmark-pdf"></i> Exportar PDF
                </a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    @foreach ([
        ['Nuevos usuarios', $summary['new_users'], 'person-plus', 'info'],
        ['Eventos creados', $summary['events'], 'calendar-event', 'warning'],
        ['Tareas creadas', $summary['tasks'], 'list-check', 'primary'],
        ['Finalización', $summary['completion'].'%', 'graph-up-arrow', 'success'],
    ] as [$label, $value, $icon, $color])
        <div class="col-6 col-lg-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="stat-icon bg-{{ $color }}-subtle text-{{ $color }}"><i class="bi bi-{{ $icon }}"></i></div>
                <div><div class="stat-value">{{ $value }}</div><div class="stat-label">{{ $label }}</div></div>
            </div>
        </div>
    @endforeach
</div>

<div class="card mb-3">
    <div class="card-header">Tareas creadas por día</div>
    <div class="card-body"><canvas id="chartDaily" height="80"></canvas></div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Tareas por prioridad</div>
            <div class="card-body"><canvas id="chartPriority"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Eventos por tipo</div>
            <ul class="list-group list-group-flush">
                @foreach ($eventsByType as $t)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><span class="color-dot me-2" style="background: {{ $t->color }}"></span>{{ $t->name }}</span>
                        <span class="badge bg-light text-dark">{{ $t->total }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Usuarios más activos <small class="text-secondary fw-normal">(anónimo)</small></div>
            <ul class="list-group list-group-flush">
                @foreach ($topUsers as $i => $u)
                    <li class="list-group-item d-flex justify-content-between">
                        <span><span class="badge bg-primary me-2">{{ $i + 1 }}</span>Usuario #{{ $u->id }}</span>
                        <span class="text-secondary">{{ $u->tasks_count }} tareas</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script type="module">
    const daily = @json($daily);
    new Chart(document.getElementById('chartDaily'), {
        type: 'bar',
        data: { labels: daily.labels, datasets: [{ data: daily.data, backgroundColor: '#818CF8', borderRadius: 4 }] },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
    const prio = @json($tasksByPriority);
    new Chart(document.getElementById('chartPriority'), {
        type: 'pie',
        data: { labels: ['Baja', 'Media', 'Alta', 'Urgente'], datasets: [{ data: Object.values(prio), backgroundColor: ['#94A3B8', '#0EA5E9', '#F59E0B', '#EF4444'] }] },
        options: { plugins: { legend: { position: 'bottom' } } }
    });
</script>
@endpush
