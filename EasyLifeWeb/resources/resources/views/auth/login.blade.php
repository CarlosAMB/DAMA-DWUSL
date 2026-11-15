@extends('layouts.app')

@section('title', 'Iniciar sesión')

@section('content')
<div class="auth-bg d-flex align-items-center justify-content-center p-3">
    <div class="card shadow-lg border-0" style="max-width: 420px; width: 100%;">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="d-inline-grid rounded-4 text-white mb-3" style="width:60px;height:60px;place-items:center;background:linear-gradient(135deg,#818CF8,#4F46E5);font-size:1.8rem">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <h1 class="h4 fw-bold mb-1">EasyLife</h1>
                <p class="text-secondary mb-0">Panel administrativo</p>
            </div>

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">Correo electrónico</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                    <label class="form-check-label" for="remember">Recordarme</label>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Ingresar
                </button>

                @if (Route::has('password.request'))
                    <div class="text-center mt-3">
                        <a class="small" href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection
