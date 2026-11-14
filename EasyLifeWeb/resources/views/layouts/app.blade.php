<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') · {{ config('app.name', 'EasyLife') }}</title>
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body>
@auth
    <div class="admin-wrapper">
        {{-- ============ MENU LATERAL ============ --}}
        <aside class="sidebar">
            <a href="{{ route('admin.dashboard') }}" class="brand">
                <span class="logo"><i class="bi bi-check2-circle"></i></span> EasyLife
            </a>

            <div class="nav-section">General</div>
            <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a class="nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}">
                <i class="bi bi-bar-chart-line"></i> Reportes
            </a>

            <div class="nav-section">Administración</div>
            <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                <i class="bi bi-people"></i> Usuarios
            </a>
            <a class="nav-link {{ request()->routeIs('admin.event-types.*') ? 'active' : '' }}" href="{{ route('admin.event-types.index') }}">
                <i class="bi bi-tags"></i> Tipos de evento
            </a>
            <a class="nav-link {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}" href="{{ route('admin.announcements.index') }}">
                <i class="bi bi-megaphone"></i> Avisos
            </a>

            <div class="nav-section">Información</div>
            <a class="nav-link" href="{{ route('privacy') }}" target="_blank">
                <i class="bi bi-shield-lock"></i> Política de privacidad
            </a>
        </aside>

        {{-- ============ CONTENIDO ============ --}}
        <div class="main-content">
            <header class="topbar">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-outline-secondary d-lg-none" data-toggle-sidebar><i class="bi bi-list"></i></button>
                    <h1 class="h5 mb-0 fw-bold">@yield('title', 'Panel')</h1>
                </div>
                <div class="dropdown">
                    <a class="d-flex align-items-center gap-2 text-decoration-none text-dark dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <span class="rounded-circle bg-primary text-white d-grid" style="width:34px;height:34px;place-items:center">
                            {{ strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}
                        </span>
                        <span class="d-none d-sm-inline">{{ Auth::user()->name }}</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('admin.users.edit', Auth::user()) }}"><i class="bi bi-person-gear me-2"></i>Mi cuenta</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </header>

            <main class="page-body">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
@else
    @yield('content')
@endauth

@stack('scripts')
</body>
</html>
