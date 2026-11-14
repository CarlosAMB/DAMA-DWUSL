@extends('layouts.app')

@section('title', 'Avisos')

@php($levelClass = ['info' => 'info', 'warning' => 'warning', 'success' => 'success'])

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Mensajes que verán los usuarios en la pantalla de inicio de la app</span>
        <a href="{{ route('admin.announcements.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo aviso</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Aviso</th><th>Nivel</th><th>Publicación</th><th>Expira</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            @forelse ($announcements as $a)
                <tr>
                    <td style="max-width: 380px">
                        <div class="fw-semibold">{{ $a->title }}</div>
                        <div class="small text-secondary text-truncate">{{ $a->body }}</div>
                    </td>
                    <td><span class="badge bg-{{ $levelClass[$a->level] }}">{{ ucfirst($a->level) }}</span></td>
                    <td class="small">{{ $a->published_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="small">{{ $a->expires_at?->format('d/m/Y H:i') ?? 'Nunca' }}</td>
                    <td>
                        @php($visible = $a->is_active && (! $a->published_at || $a->published_at->isPast()) && (! $a->expires_at || $a->expires_at->isFuture()))
                        <span class="badge {{ $visible ? 'bg-success' : 'bg-secondary' }}">{{ $visible ? 'Visible' : 'No visible' }}</span>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.announcements.edit', $a) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <form action="{{ route('admin.announcements.destroy', $a) }}" method="POST" class="d-inline" data-confirm="¿Eliminar este aviso?">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-secondary py-5">No hay avisos</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($announcements->hasPages())
        <div class="card-footer bg-white">{{ $announcements->links() }}</div>
    @endif
</div>
@endsection
