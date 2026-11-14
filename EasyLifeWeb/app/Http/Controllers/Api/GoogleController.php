<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleClient;
use App\Services\GoogleSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Integracion con Google Calendar / Google Tasks desde la app movil. */
class GoogleController extends Controller
{
    /**
     * GET /api/v1/google/auth-url?return_url=exp://...
     * Devuelve la URL de Google que la app abre en el navegador.
     */
    public function authUrl(Request $request, GoogleClient $google): JsonResponse
    {
        abort_unless(GoogleClient::configured(), 503, 'La integración con Google no está configurada en el servidor.');

        $data = $request->validate(['return_url' => ['required', 'string', 'max:500']]);
        abort_unless(GoogleClient::isAllowedReturnUrl($data['return_url']), 422, 'La dirección de regreso no es válida.');

        return response()->json(['url' => $google->authUrl($request->user(), $data['return_url'])]);
    }

    /** POST /api/v1/google/sync  - sincronizacion manual */
    public function sync(Request $request, GoogleSyncService $sync): JsonResponse
    {
        $user = $request->user();
        abort_unless($sync->isConnected($user), 409, 'Tu cuenta de Google no está conectada.');

        try {
            $stats = $sync->sync($user);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => GoogleSyncService::errorMessage($e),
                'user' => ProfileController::present($user->fresh()),
            ], 502);
        }

        return response()->json(['stats' => $stats, 'user' => ProfileController::present($user->fresh())]);
    }

    /** DELETE /api/v1/google  - desconectar (revoca el acceso en Google) */
    public function disconnect(Request $request, GoogleClient $google): JsonResponse
    {
        $google->disconnect($request->user());

        return response()->json([
            'message' => 'Cuenta de Google desconectada.',
            'user' => ProfileController::present($request->user()->fresh()),
        ]);
    }
}
