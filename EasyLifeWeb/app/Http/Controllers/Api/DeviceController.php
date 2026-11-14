<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Registro de telefonos para notificaciones push (Firebase Cloud Messaging). */
class DeviceController extends Controller
{
    /** POST /api/v1/devices  { token, platform } */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', Rule::in(['android', 'ios'])],
        ]);

        // Si el telefono era de otra cuenta, el token pasa al usuario actual
        DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            ['user_id' => $request->user()->id, 'platform' => $data['platform'] ?? 'android', 'last_used_at' => now()]
        );

        return response()->json(['message' => 'Dispositivo registrado']);
    }

    /** DELETE /api/v1/devices  { token }  (al cerrar sesion) */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string', 'max:512']]);
        $request->user()->deviceTokens()->where('token', $request->token)->delete();

        return response()->json(['message' => 'Dispositivo eliminado']);
    }
}
