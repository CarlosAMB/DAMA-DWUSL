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
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** POST /api/v1/auth/register */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'accept_privacy' => ['accepted'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], [
            'accept_privacy.accepted' => 'Debes aceptar la política de privacidad.',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => $data['password'], // el cast "hashed" la encripta con bcrypt
            'role' => 'user',
            'preferences' => User::DEFAULT_PREFERENCES,
            'privacy_accepted_at' => now(),
            'last_login_at' => now(),
        ]);

        return response()->json($this->tokenResponse($user, $data['device_name'] ?? 'movil'), 201);
    }

    /** POST /api/v1/auth/login */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', strtolower($data['email']))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Correo o contraseña incorrectos.']);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'Tu cuenta está desactivada. Contacta al administrador.']);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json($this->tokenResponse($user, $data['device_name'] ?? 'movil'));
    }

    /** POST /api/v1/auth/logout  (revoca solo el token actual) */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada']);
    }

    /** GET /api/v1/auth/me */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => ProfileController::present($request->user()),
            'data_key' => base64_encode($request->user()->dataKey()),
        ]);
    }

    /**
     * POST /api/v1/auth/firebase
     * El telefono inicia sesion (o se registra) con Firebase Authentication y envia aqui su ID token.
     * Laravel valida el token y entrega su propio token Sanctum para usar la API.
     *
     *  - Si el uid ya esta vinculado: inicia sesion.
     *  - Si existe una cuenta con ese correo (creada antes de Firebase): se vincula solo si el correo
     *    esta verificado en Firebase o si se envia la contraseña actual de la cuenta (evita que alguien
     *    cree una cuenta de Firebase con un correo ajeno y se apropie de los datos).
     *  - Si no existe: se crea la cuenta (requiere nombre y aceptar la politica de privacidad).
     */
    public function firebase(Request $request, FirebaseClient $firebase, FirestoreMirror $mirror): JsonResponse
    {
        $data = $request->validate([
            'id_token' => ['required', 'string', 'max:4096'],
            'name' => ['nullable', 'string', 'max:100'],
            'accept_privacy' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'max:200'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $claims = $firebase->verifyIdToken($data['id_token']);
        } catch (FirebaseAuthException $e) {
            throw ValidationException::withMessages(['email' => $e->getMessage()]);
        }

        $uid = $claims['sub'];
        $email = strtolower((string) ($claims['email'] ?? ''));
        if ($email === '') {
            throw ValidationException::withMessages(['email' => 'La cuenta de Firebase no tiene correo electrónico.']);
        }

        $created = false;
        $linked = false;
        $user = User::where('firebase_uid', $uid)->first();

        if (! $user) {
            $user = User::where('email', $email)->first();

            if ($user) {
                $emailVerified = ($claims['email_verified'] ?? false) === true;
                $ownsAccount = isset($data['password']) && Hash::check($data['password'], $user->password);
                if ($user->firebase_uid || (! $emailVerified && ! $ownsAccount)) {
                    throw ValidationException::withMessages(['email' => 'Correo o contraseña incorrectos.']);
                }
                $user->forceFill(['firebase_uid' => $uid])->save();
                $linked = true;
            } else {
                if (empty($data['name'])) {
                    throw ValidationException::withMessages(['email' => 'Correo o contraseña incorrectos.']);
                }
                if (empty($data['accept_privacy'])) {
                    throw ValidationException::withMessages(['accept_privacy' => 'Debes aceptar la política de privacidad.']);
                }
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $email,
                    'firebase_uid' => $uid,
                    // La contraseña real la administra Firebase; aqui queda una aleatoria (bcrypt) que nadie conoce
                    'password' => Str::random(40),
                    'role' => 'user',
                    'preferences' => User::DEFAULT_PREFERENCES,
                    'privacy_accepted_at' => now(),
                ]);
                $created = true;
            }
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'Tu cuenta está desactivada. Contacta al administrador.']);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        // Al vincular una cuenta que ya tenia datos, se suben a Firestore (despues de responder)
        if ($linked) {
            app()->terminating(function () use ($mirror, $user) {
                try {
                    $mirror->mirrorUser($user);
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        }

        return response()->json($this->tokenResponse($user, $data['device_name'] ?? 'movil'), $created ? 201 : 200);
    }

    private function tokenResponse(User $user, string $device): array
    {
        $token = $user->createToken($device, ['*'], now()->addDays(30))->plainTextToken;

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => ProfileController::present($user),
            // Llave para cifrar los campos sensibles en el SQLite del telefono y leer los de Firestore
            'data_key' => base64_encode($user->dataKey()),
        ];
    }
}
