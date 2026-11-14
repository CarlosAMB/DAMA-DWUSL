@extends('layouts.app')

@section('title', 'Dashboard')

@php
    $cards = [
        ['label' => 'Usuarios registrados', 'value' => $stats['users'], 'icon' => 'people', 'color' => 'primary'],
        ['label' => 'Activos (30 días)', 'value' => $stats['active_users'], 'icon' => 'person-check', 'color' => 'success'],
        ['label' => 'Nuevos este mes', 'value' => $stats['new_users'], 'icon' => 'person-plus', 'color' => 'info'],
        ['label' => 'Eventos creados', 'value' => $stats['events'], 'icon' => 'calendar-event', 'color' => 'warning'],
        ['label' => 'Tareas creadas', 'value' => $stats['tasks'], 'icon' => 'list-check', 'color' => 'primary'],
        ['label' => 'Proyectos', 'value' => $stats['projects'], 'icon' => 'kanban', 'color' => 'secondary'],
        ['label' => 'Tasa de finalización', 'value' => $stats['completion'].'%', 'icon' => 'graph-up-arrow', 'color' => 'success'],
        ['label' => 'Tareas vencidas', 'value' => $stats['overdue'], 'icon' => 'exclamation-octagon', 'color' => 'danger'],
    ];
@endphp

@section('content')
<div class="row g-3 mb-4">
    @foreach ($cards as $c)
        <div class="col-6 col-xl-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="stat-icon bg-{{ $c['color'] }}-subtle text-{{ $c['color'] }}"><i class="bi bi-{{ $c['icon'] }}"></i></div>
                <div>
                    <div class="stat-value">{{ $c['value'] }}</div>
                    <div class="stat-label">{{ $c['label'] }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">Nuevos usuarios (últimos 6 meses)</div>
            <div class="card-body"><canvas id="chartRegistrations" height="110"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Tareas por estado</div>
            <div class="card-body"><canvas id="chartStatus"></canvas></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Eventos por tipo</div>
            <div class="card-body"><canvas id="chartTypes" height="160"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                Usuarios recientes
                <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-primary">Ver todos</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Nombre</th><th>Correo</th><th>Registro</th></tr></thead>
                    <tbody>
                    @forelse ($recentUsers as $u)
                        <tr>
                            <td><a href="{{ route('admin.users.show', $u) }}">{{ $u->name }}</a></td>
                            <td class="text-secondary">{{ $u->email }}</td>
                            <td class="text-secondary">{{ $u->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-secondary py-4">Sin usuarios todavía</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script type="module">
    const reg = @json($registrations);
    new Chart(document.getElementById('chartRegistrations'), {
        type: 'line',
        data: { labels: reg.labels, datasets: [{ label: 'Usuarios', data: reg.data, borderColor: '#4F46E5', backgroundColor: 'rgba(79,70,229,.12)', fill: true, tension: .35 }] },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });

    const status = @json($tasksByStatus);
    new Chart(document.getElementById('chartStatus'), {
        type: 'doughnut',
        data: { labels: ['Pendiente', 'En progreso', 'Completada'], datasets: [{ data: Object.values(status), backgroundColor: ['#F59E0B', '#0EA5E9', '#10B981'] }] },
        options: { plugins: { legend: { position: 'bottom' } } }
    });

    const types = @json($eventsByType);
    new Chart(document.getElementById('chartTypes'), {
        type: 'bar',
        data: { labels: types.map(t => t.name), datasets: [{ data: types.map(t => t.events_count), backgroundColor: types.map(t => t.color) }] },
        options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
</script>
@endpush
