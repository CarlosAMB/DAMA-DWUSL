@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
<div class="card">
    <div class="card-header">
        <form class="row g-2 align-items-center" method="GET">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Buscar por nombre o correo">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <select name="role" class="form-select">
                    <option value="">Todos los roles</option>
                    <option value="user" @selected(request('role') === 'user')>Usuario</option>
                    <option value="admin" @selected(request('role') === 'admin')>Administrador</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="activo" @selected(request('status') === 'activo')>Activos</option>
                    <option value="inactivo" @selected(request('status') === 'inactivo')>Inactivos</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-outline-primary flex-fill"><i class="bi bi-funnel"></i> Filtrar</button>
                <a href="{{ route('admin.users.create') }}" class="btn btn-primary flex-fill"><i class="bi bi-plus-lg"></i> Nuevo</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Usuario</th><th>Rol</th><th>Estado</th><th class="text-center">Tareas</th>
                    <th class="text-center">Eventos</th><th>Último acceso</th><th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $user->name }}</div>
                        <div class="small text-secondary">{{ $user->email }}</div>
                    </td>
                    <td><span class="badge {{ $user->isAdmin() ? 'bg-danger' : 'bg-secondary' }}">{{ $user->isAdmin() ? 'Admin' : 'Usuario' }}</span></td>
                    <td>
                        @if ($user->is_active)
                            <span class="badge bg-success-subtle text-success"><i class="bi bi-circle-fill small"></i> Activo</span>
                        @else
                            <span class="badge bg-danger-subtle text-danger"><i class="bi bi-circle-fill small"></i> Inactivo</span>
                        @endif
                    </td>
                    <td class="text-center">{{ $user->tasks_count }}</td>
                    <td class="text-center">{{ $user->events_count }}</td>
                    <td class="text-secondary small">{{ $user->last_login_at?->diffForHumans() ?? 'Nunca' }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.users.show', $user) }}" class="btn btn-sm btn-outline-secondary" title="Ver"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                        @if ($user->id !== auth()->id())
                            <form action="{{ route('admin.users.toggle', $user) }}" method="POST" class="d-inline">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm {{ $user->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" title="{{ $user->is_active ? 'Desactivar' : 'Activar' }}">
                                    <i class="bi {{ $user->is_active ? 'bi-person-slash' : 'bi-person-check' }}"></i>
                                </button>
                            </form>
                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="d-inline" data-confirm="¿Eliminar a {{ $user->name }} y TODOS sus datos? Esta acción no se puede deshacer.">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-secondary py-5">No se encontraron usuarios</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($users->hasPages())
        <div class="card-footer bg-white">{{ $users->links() }}</div>
    @endif
</div>
@endsection
