{{-- Plantilla del reporte en PDF (mPDF). Las barras se dibujan con tablas: mPDF no ejecuta JavaScript. --}}
@php
    $priorityColors = ['baja' => '#94A3B8', 'media' => '#0EA5E9', 'alta' => '#F59E0B', 'urgente' => '#EF4444'];
    $maxPriority = max(1, collect($tasksByPriority)->max());
    $maxType = max(1, $eventsByType->max('total'));
    $maxWeek = max(1, $weeks->max('total'));
    $maxUser = max(1, $topUsers->max('tasks_count'));
    // Celda de barra: ancho proporcional al valor
    $bar = function ($value, $max, $color) {
        $pct = $value > 0 ? max(2, round($value * 100 / $max)) : 0;
        return $pct
            ? '<table class="bar" cellspacing="0" cellpadding="0" style="width:'.$pct.'%"><tr><td style="background:'.$color.'">&nbsp;</td></tr></table>'
            : '<span class="muted">—</span>';
    };
@endphp
<html>
<head>
<style>
    body { font-family: dejavusans; font-size: 10pt; color: #1F2937; }
    .header { background: #1E1B4B; color: #ffffff; padding: 14px 18px; }
    .header .brand { font-size: 20pt; font-weight: bold; }
    .header .sub { font-size: 10pt; color: #C7D2FE; }
    h2 { font-size: 12pt; color: #1E1B4B; margin: 18px 0 6px 0; }
    .muted { color: #6B7280; }
    .kpis td { width: 25%; padding: 10px; background: #EEF0FF; text-align: center; }
    .kpi-value { font-size: 20pt; font-weight: bold; color: #4F46E5; }
    .kpi-label { font-size: 9pt; color: #374151; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th { background: #4F46E5; color: #ffffff; font-size: 9pt; text-align: left; padding: 5px 8px; }
    table.data td { padding: 5px 8px; border-bottom: 1px solid #E5E7EB; font-size: 9.5pt; vertical-align: middle; }
    table.data tr.zebra td { background: #F8F9FF; }
    table.bar { height: 11px; }
    table.bar td { height: 11px; font-size: 1pt; }
    .dot { font-size: 12pt; }
    .num { text-align: right; width: 60px; font-weight: bold; }
    .note { font-size: 8.5pt; color: #6B7280; margin-top: 14px; }
</style>
</head>
<body>

<div class="header">
    <span class="brand">EasyLife</span><br>
    <span class="sub">Reporte de uso de la plataforma · del {{ $from->format('d/m/Y') }} al {{ $to->format('d/m/Y') }}</span>
</div>
<p class="muted" style="font-size:8.5pt">Generado por {{ $generatedBy }} el {{ $generatedAt->format('d/m/Y H:i') }}</p>

<h2>Resumen del periodo</h2>
<table class="kpis" width="100%" cellspacing="6">
    <tr>
        <td><div class="kpi-value">{{ $summary['new_users'] }}</div><div class="kpi-label">Nuevos usuarios</div></td>
        <td><div class="kpi-value">{{ $summary['events'] }}</div><div class="kpi-label">Eventos creados</div></td>
        <td><div class="kpi-value">{{ $summary['tasks'] }}</div><div class="kpi-label">Tareas creadas</div></td>
        <td><div class="kpi-value" style="color:#E97132">{{ $summary['completion'] }}%</div><div class="kpi-label">Finalización ({{ $summary['completed'] }} completadas)</div></td>
    </tr>
</table>

<h2>Tareas creadas por semana</h2>
<table class="data">
    <tr><th style="width:120px">Semana</th><th>Cantidad</th><th class="num">Tareas</th></tr>
    @foreach ($weeks as $i => $w)
        <tr class="{{ $i % 2 ? 'zebra' : '' }}"><td>{{ $w['label'] }}</td><td>{!! $bar($w['total'], $maxWeek, '#4F46E5') !!}</td><td class="num">{{ $w['total'] }}</td></tr>
    @endforeach
</table>

<h2>Tareas por prioridad</h2>
<table class="data">
    <tr><th style="width:120px">Prioridad</th><th>Cantidad</th><th class="num">Tareas</th></tr>
    @foreach ($tasksByPriority as $priority => $total)
        <tr class="{{ $loop->index % 2 ? 'zebra' : '' }}">
            <td><span class="dot" style="color:{{ $priorityColors[$priority] ?? '#6B7280' }}">●</span> {{ ucfirst($priority) }}</td>
            <td>{!! $bar($total, $maxPriority, $priorityColors[$priority] ?? '#6B7280') !!}</td>
            <td class="num">{{ $total }}</td>
        </tr>
    @endforeach
</table>

<h2>Eventos por tipo</h2>
<table class="data">
    <tr><th style="width:120px">Tipo</th><th>Cantidad</th><th class="num">Eventos</th></tr>
    @foreach ($eventsByType as $type)
        <tr class="{{ $loop->index % 2 ? 'zebra' : '' }}">
            <td><span class="dot" style="color:{{ $type->color }}">●</span> {{ $type->name }}</td>
            <td>{!! $bar($type->total, $maxType, $type->color) !!}</td>
            <td class="num">{{ $type->total }}</td>
        </tr>
    @endforeach
</table>

<h2>Usuarios más activos <span class="muted" style="font-size:9pt">(anónimo)</span></h2>
<table class="data">
    <tr><th style="width:120px">Usuario</th><th>Tareas creadas</th><th class="num">Tareas</th></tr>
    @forelse ($topUsers as $u)
        <tr class="{{ $loop->index % 2 ? 'zebra' : '' }}"><td>Usuario #{{ $u->id }}</td><td>{!! $bar($u->tasks_count, $maxUser, '#E97132') !!}</td><td class="num">{{ $u->tasks_count }}</td></tr>
    @empty
        <tr><td colspan="3" class="muted">Sin actividad en el periodo.</td></tr>
    @endforelse
</table>

<p class="note">Por privacidad, este reporte solo contiene datos agregados: no incluye el contenido de las tareas ni de los eventos de los usuarios, y el ranking muestra un identificador en lugar del nombre.</p>

</body>
</html>
