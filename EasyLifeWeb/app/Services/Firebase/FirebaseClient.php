<?php

namespace App\Services\Firebase;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Acceso a Firebase desde Laravel sin SDK (solo HTTP + JWT):
 *  - verifyIdToken(): valida el ID token que entrega Firebase Authentication en el telefono
 *    (firma RS256 con las llaves publicas de Google, audiencia = id del proyecto, vigencia).
 *  - http(): cliente autenticado con la cuenta de servicio para Firestore y Cloud Messaging.
 */
class FirebaseClient
{
    public const JWKS_URL = 'https://www.googleapis.com/service_accounts/v1/jwk/securetoken@system.gserviceaccount.com';

    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const SCOPES = 'https://www.googleapis.com/auth/datastore https://www.googleapis.com/auth/firebase.messaging';

    private ?array $credentials = null;

    private bool $credentialsLoaded = false;

    public function projectId(): ?string
    {
        return config('services.firebase.project_id') ?: ($this->credentials()['project_id'] ?? null);
    }

    /** Hay proyecto configurado: se pueden verificar inicios de sesion de Firebase. */
    public function authConfigured(): bool
    {
        return (bool) $this->projectId();
    }

    /** Hay cuenta de servicio: se puede escribir en Firestore y enviar push. */
    public function serverConfigured(): bool
    {
        $c = $this->credentials();

        return $this->authConfigured() && ! empty($c['client_email']) && ! empty($c['private_key']);
    }

    /** Lee la cuenta de servicio: ruta al archivo, JSON directo o JSON en base64. */
    public function credentials(): ?array
    {
        if ($this->credentialsLoaded) {
            return $this->credentials;
        }
        $this->credentialsLoaded = true;

        $value = trim((string) config('services.firebase.credentials'));
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '{')) {
            $json = $value;
        } elseif (is_file($value) || is_file(base_path($value))) {
            $json = file_get_contents(is_file($value) ? $value : base_path($value));
        } else {
            $json = base64_decode($value, true) ?: null;
        }

        $data = $json ? json_decode($json, true) : null;

        return $this->credentials = is_array($data) ? $data : null;
    }

    /**
     * Valida un ID token de Firebase Authentication.
     *
     * @return array claims (sub = uid, email, email_verified, auth_time...)
     *
     * @throws FirebaseAuthException
     */
    public function verifyIdToken(string $idToken): array
    {
        $projectId = $this->projectId();
        if (! $projectId) {
            throw new FirebaseAuthException('Firebase no está configurado en el servidor.');
        }

        try {
            $claims = $this->decode($idToken, false);
        } catch (\Throwable) {
            try {
                $claims = $this->decode($idToken, true); // las llaves de Google rotan: se reintenta con llaves nuevas
            } catch (\Throwable) {
                throw new FirebaseAuthException('El token de Firebase no es válido o expiró.');
            }
        }

        if (($claims['aud'] ?? null) !== $projectId
            || ($claims['iss'] ?? null) !== 'https://securetoken.google.com/'.$projectId
            || empty($claims['sub'])
            || ($claims['auth_time'] ?? PHP_INT_MAX) > time() + 60) {
            throw new FirebaseAuthException('El token de Firebase no pertenece a este proyecto.');
        }

        return $claims;
    }

    private function decode(string $idToken, bool $refreshKeys): array
    {
        if ($refreshKeys) {
            Cache::forget('firebase.jwks');
        }
        $jwks = Cache::remember('firebase.jwks', 3600, fn () => Http::timeout(10)->get(self::JWKS_URL)->throw()->json());

        JWT::$leeway = 60;

        return (array) JWT::decode($idToken, JWK::parseKeySet($jwks, 'RS256'));
    }

    /** Token OAuth de la cuenta de servicio (dura 1 hora; se guarda en cache 50 min). */
    public function accessToken(): string
    {
        return Cache::remember('firebase.access_token.'.$this->projectId(), 3000, function () {
            $c = $this->credentials();
            $now = time();
            $assertion = JWT::encode([
                'iss' => $c['client_email'],
                'scope' => self::SCOPES,
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
            ], $c['private_key'], 'RS256');

            return Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ])->throw()->json('access_token');
        });
    }

    public function http(): PendingRequest
    {
        return Http::withToken($this->accessToken())->acceptJson()->timeout(20);
    }
}
