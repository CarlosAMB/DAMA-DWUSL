@extends('layouts.app')

@section('title', 'Tipos de evento')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Catálogo usado por la app al crear eventos</span>
        <a href="{{ route('admin.event-types.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo tipo</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Nombre</th><th>Color</th><th>Ícono (app)</th><th class="text-center">Eventos</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            @forelse ($types as $type)
                <tr>
                    <td class="fw-semibold">{{ $type->name }}</td>
                    <td><span class="color-dot me-1" style="background: {{ $type->color }}"></span> <code>{{ $type->color }}</code></td>
                    <td><code>{{ $type->icon }}</code></td>
                    <td class="text-center">{{ $type->events_count }}</td>
                    <td><span class="badge {{ $type->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $type->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.event-types.edit', $type) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <form action="{{ route('admin.event-types.destroy', $type) }}" method="POST" class="d-inline" data-confirm="¿Eliminar el tipo {{ $type->name }}?">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-secondary py-5">No hay tipos de evento</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
