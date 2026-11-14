<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\GoogleController;
use App\Http\Controllers\Api\ImportExportController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\SubtaskController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API de EasyLife (consumida por la app movil React Native)
| Todas las rutas tienen el prefijo /api/v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ---------- Autenticacion (publico, con limite de intentos) ----------
    Route::middleware('throttle:login')->group(function () {
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/firebase', [AuthController::class, 'firebase']); // Firebase Authentication
    });

    Route::get('ping', fn () => response()->json(['ok' => true, 'time' => now()->toISOString()]));

    // ---------- Rutas protegidas con token Sanctum ----------
    Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        // Perfil y privacidad
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('profile/password', [ProfileController::class, 'password']);
        Route::get('profile/export', [ProfileController::class, 'export']);
        Route::delete('profile', [ProfileController::class, 'destroy']);

        // Catalogos administrados desde la web
        Route::get('event-types', [CatalogController::class, 'eventTypes']);
        Route::get('announcements', [CatalogController::class, 'announcements']);

        // Recursos del usuario
        Route::apiResource('events', EventController::class);
        Route::apiResource('tasks', TaskController::class);
        Route::patch('tasks/{task}/complete', [TaskController::class, 'toggleComplete']);
        Route::apiResource('subtasks', SubtaskController::class);
        Route::apiResource('projects', ProjectController::class);
        Route::apiResource('tags', TagController::class);
        Route::apiResource('reminders', ReminderController::class);

        // Calendario (dia / semana / mes / anio)
        Route::get('calendar', CalendarController::class.'@index');

        // Sincronizacion offline
        Route::post('sync', SyncController::class);

        // Importacion / exportacion (ICS, CSV, JSON)
        Route::get('export/{format}', [ImportExportController::class, 'export'])->whereIn('format', ['ics', 'csv', 'json']);
        Route::post('import', [ImportExportController::class, 'import']);

        // Google Calendar + Google Tasks
        Route::get('google/auth-url', [GoogleController::class, 'authUrl']);
        Route::post('google/sync', [GoogleController::class, 'sync']);
        Route::delete('google', [GoogleController::class, 'disconnect']);

        // Notificaciones push (Firebase Cloud Messaging)
        Route::post('devices', [DeviceController::class, 'store']);
        Route::delete('devices', [DeviceController::class, 'destroy']);
    });
});
