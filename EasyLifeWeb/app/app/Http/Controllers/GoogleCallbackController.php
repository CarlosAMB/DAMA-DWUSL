<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GoogleClient;
use Illuminate\Http\Request;

/**
 * GET /google/callback
 * Google redirige aqui despues de que el usuario autoriza (o cancela).
 * Se guardan los tokens y se regresa a la app movil con ?status=ok|cancelled|error
 */
class GoogleCallbackController extends Controller
{
    public function __invoke(Request $request, GoogleClient $google)
    {
        $state = $google->decodeState($request->query('state'));
        $user = $state ? User::where('is_active', true)->find($state['uid']) : null;

        if (! $user) {
            return response()->view('google.result', [
                'ok' => false,
                'message' => 'El enlace de autorización no es válido o ya venció. Vuelve a intentarlo desde la app.',
            ], 400);
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return $this->backToApp($state['return'], 'cancelled');
        }

        try {
            $google->connect($user, $request->query('code'));
        } catch (\Throwable $e) {
            report($e);

            return $this->backToApp($state['return'], 'error');
        }

        return $this->backToApp($state['return'], 'ok');
    }

    private function backToApp(string $returnUrl, string $status)
    {
        $url = $returnUrl.(str_contains($returnUrl, '?') ? '&' : '?').'status='.$status;

        // Pagina intermedia: redirige a la app y muestra un boton por si el navegador bloquea la redireccion
        return response()->view('google.result', ['ok' => $status === 'ok', 'returnUrl' => $url, 'message' => match ($status) {
            'ok' => '¡Listo! Tu cuenta de Google quedó conectada.',
            'cancelled' => 'Cancelaste la conexión con Google.',
            default => 'No se pudo conectar con Google. Inténtalo de nuevo.',
        }]);
    }
}
