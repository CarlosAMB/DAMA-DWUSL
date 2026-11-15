<?php

use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventTypeController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\GoogleCallbackController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Panel administrativo de EasyLife (solo usuarios con rol admin)
| Los usuarios finales se registran desde la app movil.
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => redirect()->route('admin.dashboard'));
Route::view('/privacidad', 'privacy')->name('privacy');

// Regreso de la autorizacion de Google (Calendar + Tasks) para la app movil
Route::get('/google/callback', GoogleCallbackController::class)
    ->middleware('throttle:20,1')->name('google.callback');

Auth::routes(['register' => false, 'verify' => false]);

Route::middleware(['auth', 'active', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    // :::::::::: USUARIOS ::::::::::
    Route::resource('users', UserController::class);
    Route::patch('users/{user}/toggle', [UserController::class, 'toggleActive'])->name('users.toggle');

    // :::::::::: CATALOGO: TIPOS DE EVENTO ::::::::::
    Route::resource('event-types', EventTypeController::class)->except('show')
        ->parameters(['event-types' => 'eventType']);

    // :::::::::: AVISOS PARA LA APP ::::::::::
    Route::resource('announcements', AnnouncementController::class)->except('show');

    // :::::::::: REPORTES ::::::::::
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('reports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
});
