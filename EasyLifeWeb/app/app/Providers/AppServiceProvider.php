<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\Task;
use App\Services\Firebase\FirebaseClient;
use App\Services\Firebase\FirestoreMirror;
use App\Services\SyncService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FirebaseClient::class);
        $this->app->singleton(FirestoreMirror::class);
    }

    public function boot(): void
    {
        // Los recordatorios guardan "event" o "task" en lugar del nombre completo de la clase
        Relation::morphMap([
            'event' => Event::class,
            'task' => Task::class,
        ]);

        // Espejo en Firestore: cualquier cambio en los datos del usuario se sube al terminar la peticion
        foreach (SyncService::TABLES as $model) {
            foreach (['saved', 'deleted', 'restored'] as $event) {
                $model::$event(fn ($record) => app(FirestoreMirror::class)->queue($record));
            }
        }

        Paginator::useBootstrapFive();

        // Limita intentos de inicio de sesion por IP + correo (proteccion contra fuerza bruta)
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
