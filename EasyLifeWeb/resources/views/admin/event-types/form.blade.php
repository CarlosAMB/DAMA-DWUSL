@extends('layouts.app')

@section('title', $type->exists ? 'Editar tipo de evento' : 'Nuevo tipo de evento')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">{{ $type->exists ? 'Editar: '.$type->name : 'Nuevo tipo de evento' }}</div>
            <div class="card-body">
                <form method="POST" action="{{ $type->exists ? route('admin.event-types.update', $type) : route('admin.event-types.store') }}">
                    @csrf
                    @if ($type->exists) @method('PUT') @endif

                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input name="name" value="{{ old('name', $type->name) }}" class="form-control @error('name') is-invalid @enderror" required maxlength="60">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-5">
                            <label class="form-label">Color</label>
                            <input type="color" name="color" value="{{ old('color', $type->color) }}" class="form-control form-control-color w-100 @error('color') is-invalid @enderror">
                            @error('color')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-7">
                            <label class="form-label">Ícono en la app</label>
                            <select name="icon" class="form-select @error('icon') is-invalid @enderror">
                                @foreach ($icons as $icon)
                                    <option value="{{ $icon }}" @selected(old('icon', $type->icon) === $icon)>{{ $icon }}</option>
                                @endforeach
                            </select>
                            @error('icon')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Nombres de MaterialCommunityIcons.</div>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="is_active" value="1" @checked(old('is_active', $type->is_active))>
                        <label class="form-check-label" for="is_active">Disponible en la app</label>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('admin.event-types.index') }}" class="btn btn-light">Cancelar</a>
                        <button class="btn btn-primary"><i class="bi bi-save me-1"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
