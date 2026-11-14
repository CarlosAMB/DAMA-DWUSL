@extends('layouts.app')

@section('title', 'Detalle de usuario')

@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center p-4">
                <div class="rounded-circle bg-primary text-white d-inline-grid mb-3" style="width:72px;height:72px;place-items:center;font-size:1.8rem">
                    {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                </div>
                <h2 class="h5 fw-bold mb-0">{{ $user->name }}</h2>
                <div class="text-secondary mb-3">{{ $user->email }}</div>
                <span class="badge {{ $user->isAdmin() ? 'bg-danger' : 'bg-secondary' }}">{{ $user->isAdmin() ? 'Administrador' : 'Usuario' }}</span>
                <span class="badge {{ $user->is_active ? 'bg-success' : 'bg-dark' }}">{{ $user->is_active ? 'Activo' : 'Inactivo' }}</span>
            </div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between"><span class="text-secondary">Teléfono</span>{{ $user->phone ?? '—' }}</li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-secondary">Zona horaria</span>{{ $user->timezone }}</li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-secondary">Registrado</span>{{ $user->created_at->format('d/m/Y') }}</li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-secondary">Último acceso</span>{{ $user->last_login_at?->format('d/m/Y H:i') ?? 'Nunca' }}</li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-secondary">Privacidad aceptada</span>{{ $user->privacy_accepted_at?->format('d/m/Y') ?? 'No' }}</li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-secondary">Google Calendar</span>{{ $user->google_refresh_token ? 'Conectado' : 'No conectado' }}</li>
            </ul>
            <div class="card-body d-flex gap-2">
                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-primary flex-fill"><i class="bi bi-pencil"></i> Editar</a>
                <a href="{{ route('admin.users.index') }}" class="btn btn-light flex-fill">Volver</a>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="alert alert-info d-flex gap-2">
            <i class="bi bi-shield-lock fs-5"></i>
            <div>Por privacidad, el panel solo muestra <strong>métricas agregadas</strong>. El contenido de los eventos y tareas del usuario está encriptado y no es visible para el administrador.</div>
        </div>
        <div class="row g-3">
            @foreach ([
                ['Eventos', $metrics['events'], 'calendar-event', 'warning'],
                ['Tareas', $metrics['tasks'], 'list-check', 'primary'],
                ['Completadas', $metrics['completed'], 'check2-all', 'success'],
                ['Pendientes', $metrics['pending'], 'hourglass-split', 'info'],
                ['Vencidas', $metrics['overdue'], 'exclamation-octagon', 'danger'],
                ['Proyectos', $metrics['projects'], 'kanban', 'secondary'],
                ['Etiquetas', $metrics['tags'], 'tags', 'primary'],
                ['Dispositivos con sesión', $metrics['devices'], 'phone', 'dark'],
            ] as [$label, $value, $icon, $color])
                <div class="col-6 col-md-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon bg-{{ $color }}-subtle text-{{ $color }} mx-auto mb-2"><i class="bi bi-{{ $icon }}"></i></div>
                        <div class="stat-value">{{ $value }}</div>
                        <div class="stat-label">{{ $label }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
