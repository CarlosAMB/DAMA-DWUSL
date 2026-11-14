<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Firebase\FirebaseAuthException;
use App\Services\Firebase\FirebaseClient;
use App\Services\Firebase\FirestoreMirror;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /** Datos publicos del usuario que se envian a la app. */
    public static function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'timezone' => $user->timezone,
            'preferences' => $user->settings,
            'google_connected' => ! empty($user->google_refresh_token),
            'google_email' => $user->google_email,
            'google_synced_at' => $user->google_synced_at,
            'firebase_uid' => $user->firebase_uid,
            'created_at' => $user->created_at,
        ];
    }

    /** PUT /api/v1/profile  - datos personales y preferencias de organizacion */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'email', 'max:150', Rule::unique('users')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'timezone' => ['sometimes', 'timezone'],
            'preferences' => ['sometimes', 'array'],
            'preferences.theme' => ['in:light,dark'],
            'preferences.calendar_view' => ['in:day,week,month,year'],
            'preferences.default_reminder_minutes' => ['integer', 'min:0', 'max:10080'],
            'preferences.daily_summary' => ['boolean'],
            'preferences.daily_summary_time' => ['date_format:H:i'],
            'preferences.week_starts_on' => ['integer', 'in:0,1'],
            'preferences.notifications_enabled' => ['boolean'],
        ]);

        if (isset($data['preferences'])) {
            $allowed = array_intersect_key($data['preferences'], User::DEFAULT_PREFERENCES);
            $data['preferences'] = array_merge($user->settings, $allowed);
        }

        $user->update($data);

        return response()->json(['user' => self::present($user->fresh())]);
    }

    /** PUT /api/v1/profile/password */
    public function password(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'La contraseña actual no es correcta.']);
        }

        $user->update(['password' => $data['password']]);
        // Cierra las sesiones de otros dispositivos
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Contraseña actualizada']);
    }

    /** GET /api/v1/profile/export - derecho de acceso: descarga todos mis datos */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'exported_at' => now(),
            'user' => self::present($user),
            'projects' => $user->projects()->get(),
            'tags' => $user->tags()->get(),
            'events' => $user->events()->get(),
            'tasks' => $user->tasks()->with('tags:id')->get(),
            'subtasks' => $user->subtasks()->get(),
            'reminders' => $user->reminders()->get(),
        ]);
    }

    /** DELETE /api/v1/profile - derecho al olvido: elimina la cuenta y todos sus datos */
    public function destroy(Request $request, FirebaseClient $firebase, FirestoreMirror $mirror): JsonResponse
    {
        // Confirmacion: la contraseña de Laravel o, para cuentas de Firebase, un ID token recien emitido
        // (la app vuelve a autenticar al usuario con su contraseña antes de pedirlo).
        $request->validate([
            'password' => ['required_without:id_token', 'nullable', 'string'],
            'id_token' => ['required_without:password', 'nullable', 'string', 'max:4096'],
        ]);
        $user = $request->user();

        if ($request->filled('id_token')) {
            try {
                $claims = $firebase->verifyIdToken($request->id_token);
            } catch (FirebaseAuthException) {
                $claims = [];
            }
            if (($claims['sub'] ?? null) !== $user->firebase_uid || ($claims['auth_time'] ?? 0) < time() - 300) {
                throw ValidationException::withMessages(['password' => 'No se pudo confirmar tu identidad. Ingresa tu contraseña de nuevo.']);
            }
        } elseif (! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['password' => 'La contraseña no es correcta.']);
        }

        $firebaseUid = $user->firebase_uid;
        $user->tokens()->delete();
        $user->delete(); // las tablas relacionadas usan cascadeOnDelete

        // Tambien se borra su copia en Firestore (la cuenta de Firebase Auth la elimina la app)
        if ($firebaseUid) {
            app()->terminating(function () use ($mirror, $firebaseUid) {
                try {
                    $mirror->deleteUser($firebaseUid);
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        }

        return response()->json(['message' => 'Cuenta eliminada']);
    }
}
