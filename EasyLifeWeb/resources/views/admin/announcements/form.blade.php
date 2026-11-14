@extends('layouts.app')

@section('title', $announcement->exists ? 'Editar aviso' : 'Nuevo aviso')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">{{ $announcement->exists ? 'Editar aviso' : 'Publicar aviso' }}</div>
            <div class="card-body">
                <form method="POST" action="{{ $announcement->exists ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}">
                    @csrf
                    @if ($announcement->exists) @method('PUT') @endif

                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input name="title" value="{{ old('title', $announcement->title) }}" class="form-control @error('title') is-invalid @enderror" required maxlength="120">
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mensaje</label>
                        <textarea name="body" rows="4" class="form-control @error('body') is-invalid @enderror" required maxlength="2000">{{ old('body', $announcement->body) }}</textarea>
                        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Nivel</label>
                            <select name="level" class="form-select">
                                <option value="info" @selected(old('level', $announcement->level) === 'info')>Información</option>
                                <option value="success" @selected(old('level', $announcement->level) === 'success')>Éxito / novedad</option>
                                <option value="warning" @selected(old('level', $announcement->level) === 'warning')>Advertencia</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Publicar desde</label>
                            <input type="datetime-local" name="published_at" value="{{ old('published_at', $announcement->published_at?->format('Y-m-d\TH:i')) }}" class="form-control @error('published_at') is-invalid @enderror">
                            @error('published_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expira</label>
                            <input type="datetime-local" name="expires_at" value="{{ old('expires_at', $announcement->expires_at?->format('Y-m-d\TH:i')) }}" class="form-control @error('expires_at') is-invalid @enderror">
                            @error('expires_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="is_active" value="1" @checked(old('is_active', $announcement->is_active))>
                        <label class="form-check-label" for="is_active">Activo</label>
                    </div>
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" role="switch" name="send_push" id="send_push" value="1" @checked(old('send_push'))>
                        <label class="form-check-label" for="send_push">
                            Enviar también como notificación push <small class="text-muted">(Firebase Cloud Messaging, a todos los usuarios)</small>
                        </label>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('admin.announcements.index') }}" class="btn btn-light">Cancelar</a>
                        <button class="btn btn-primary"><i class="bi bi-send me-1"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
