<?php

namespace App\Services\Firebase;

use App\Models\DeviceToken;
use Illuminate\Support\Collection;

/**
 * Notificaciones push con Firebase Cloud Messaging (API HTTP v1).
 * Los tokens los registra la app (POST /api/v1/devices) al iniciar sesion en un APK.
 */
class FcmService
{
    /** Canal de Android creado por la app (src/services/notifications.js). */
    private const ANDROID_CHANNEL = 'recordatorios';

    public function __construct(private FirebaseClient $firebase) {}

    public function enabled(): bool
    {
        return $this->firebase->serverConfigured();
    }

    /** Envia a todos los dispositivos de usuarios activos. */
    public function sendToAll(string $title, string $body, array $data = []): array
    {
        $tokens = DeviceToken::whereHas('user', fn ($q) => $q->where('is_active', true)->where('role', 'user'))->get();

        return $this->send($tokens, $title, $body, $data);
    }

    /** Envia a los dispositivos de los usuarios indicados. */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): array
    {
        return $this->send(DeviceToken::whereIn('user_id', $userIds)->get(), $title, $body, $data);
    }

    /** @return array{sent:int, failed:int} */
    private function send(Collection $tokens, string $title, string $body, array $data): array
    {
        $result = ['sent' => 0, 'failed' => 0];
        if (! $this->enabled() || $tokens->isEmpty()) {
            return $result;
        }

        $url = 'https://fcm.googleapis.com/v1/projects/'.$this->firebase->projectId().'/messages:send';
        $data = array_map('strval', $data);

        foreach ($tokens as $device) {
            try {
                $response = $this->firebase->http()->post($url, ['message' => [
                    'token' => $device->token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => (object) $data,
                    'android' => ['priority' => 'high', 'notification' => ['channel_id' => self::ANDROID_CHANNEL]],
                ]]);
            } catch (\Throwable $e) {
                report($e);
                $result['failed']++;

                continue;
            }

            if ($response->successful()) {
                $result['sent']++;
                $device->forceFill(['last_used_at' => now()])->save();

                continue;
            }

            $result['failed']++;
            // Token de una app desinstalada o invalido: se elimina para no volver a intentarlo
            $code = collect($response->json('error.details', []))->pluck('errorCode')->filter()->first();
            if ($response->status() === 404 || in_array($code, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                $device->delete();
            }
        }

        return $result;
    }
}
