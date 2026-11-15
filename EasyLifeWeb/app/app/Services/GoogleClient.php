<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente minimo de Google (OAuth 2.0 + llamadas a las APIs) usando el cliente HTTP de Laravel.
 * Se evita la libreria oficial google/apiclient porque es muy pesada para lo que se necesita.
 *
 * Flujo de conexion:
 *  1. La app pide la URL de autorizacion (authUrl) con un "state" cifrado que identifica al usuario.
 *  2. El usuario autoriza en Google, que redirige a /google/callback con un "code".
 *  3. El servidor cambia el code por tokens (exchangeCode) y los guarda cifrados en la BD.
 *  4. Antes de cada llamada se renueva el access token si esta por vencer (refresh token).
 */
class GoogleClient
{
    public const SCOPES = [
        'openid',
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/calendar.events',
        'https://www.googleapis.com/auth/tasks',
    ];

    /** Esquemas permitidos para volver a la app (evita redirecciones abiertas). */
    public const ALLOWED_RETURN_SCHEMES = ['exp', 'exps', 'easylife'];

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';
    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    public static function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public static function isAllowedReturnUrl(?string $url): bool
    {
        $scheme = $url ? parse_url($url, PHP_URL_SCHEME) : null;

        return $scheme && in_array(strtolower($scheme), self::ALLOWED_RETURN_SCHEMES, true);
    }

    /** URL de Google a la que se envia al usuario para que autorice EasyLife. */
    public function authUrl(User $user, string $returnUrl): string
    {
        $state = Crypt::encryptString(json_encode([
            'uid' => $user->id,
            'return' => $returnUrl,
            'exp' => now()->addMinutes(10)->timestamp,
        ]));

        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline', // para recibir refresh token
            'prompt' => 'consent',      // garantiza el refresh token aunque ya haya autorizado antes
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /** @return array{uid:int, return:string}|null  null si el state es invalido o vencio */
    public function decodeState(?string $state): ?array
    {
        try {
            $data = json_decode(Crypt::decryptString((string) $state), true);
        } catch (\Throwable) {
            return null;
        }
        if (! is_array($data) || ($data['exp'] ?? 0) < now()->timestamp || ! self::isAllowedReturnUrl($data['return'] ?? null)) {
            return null;
        }

        return $data;
    }

    /** Cambia el "code" por tokens y los guarda en el usuario. */
    public function connect(User $user, string $code): void
    {
        $tokens = Http::asForm()->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect'),
            'grant_type' => 'authorization_code',
        ])->throw()->json();

        if (empty($tokens['refresh_token']) && empty($user->google_refresh_token)) {
            throw new RuntimeException('Google no envió el token de actualización. Quita el acceso de EasyLife en tu cuenta de Google e intenta de nuevo.');
        }

        $email = Http::withToken($tokens['access_token'])->get(self::USERINFO_URL)->json('email');

        $user->forceFill([
            'google_access_token' => $tokens['access_token'],
            'google_refresh_token' => $tokens['refresh_token'] ?? $user->google_refresh_token,
            'google_token_expires_at' => now()->addSeconds(($tokens['expires_in'] ?? 3600) - 60),
            'google_email' => $email,
            'google_synced_at' => null, // la siguiente sincronizacion sera completa
        ])->save();
    }

    public function disconnect(User $user): void
    {
        if ($user->google_refresh_token) {
            // Si falla (token ya revocado, sin internet) igual se borra localmente
            rescue(fn () => Http::asForm()->timeout(10)->post(self::REVOKE_URL, ['token' => $user->google_refresh_token]), report: false);
        }

        $user->forceFill([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
            'google_email' => null,
            'google_synced_at' => null,
        ])->save();
    }

    /** Peticion autenticada a una API de Google (renueva el token si hace falta). */
    public function request(User $user): PendingRequest
    {
        if (! $user->google_refresh_token) {
            throw new GoogleAuthException('Tu cuenta de Google no está conectada.');
        }
        if (! $user->google_access_token || ! $user->google_token_expires_at || $user->google_token_expires_at->isPast()) {
            $this->refreshToken($user);
        }

        return Http::withToken($user->google_access_token)->acceptJson()->timeout(20);
    }

    private function refreshToken(User $user): void
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $user->google_refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->json('error') === 'invalid_grant') {
            // El usuario quito el acceso o el token vencio (en modo prueba duran 7 dias)
            $this->disconnect($user);
            throw new GoogleAuthException('El acceso a Google venció o fue revocado. Vuelve a conectar tu cuenta.');
        }
        $response->throw();

        $user->forceFill([
            'google_access_token' => $response->json('access_token'),
            'google_token_expires_at' => now()->addSeconds($response->json('expires_in', 3600) - 60),
        ])->save();
    }

    /** true si la respuesta indica que el recurso ya no existe en Google. */
    public static function gone(Response $response): bool
    {
        return in_array($response->status(), [404, 410], true);
    }
}
