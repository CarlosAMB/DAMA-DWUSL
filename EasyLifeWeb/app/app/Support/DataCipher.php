<?php

namespace App\Support;

/**
 * Cifrado de campos sensibles con la llave propia de cada usuario (User::dataKey()).
 * Se usa para lo que se guarda en Firestore; la app movil usa el mismo formato
 * (src/utils/crypto.js) para descifrarlo y para cifrar su base SQLite.
 *
 * Formato: "ev1:" + base64(iv) + ":" + base64(texto cifrado) + ":" + base64(hmac)
 *  - AES-256-CBC con IV aleatorio
 *  - HMAC-SHA256 sobre iv + texto cifrado (detecta manipulaciones)
 *  - De la llave maestra se derivan dos llaves distintas: una para cifrar y otra para el HMAC
 */
class DataCipher
{
    public const PREFIX = 'ev1:';

    public static function encrypt(?string $plain, string $key): ?string
    {
        if ($plain === null || $plain === '') {
            return $plain;
        }
        [$encKey, $macKey] = self::keys($key);
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);
        $mac = hash_hmac('sha256', $iv.$cipher, $macKey, true);

        return self::PREFIX.base64_encode($iv).':'.base64_encode($cipher).':'.base64_encode($mac);
    }

    public static function decrypt(?string $value, string $key): ?string
    {
        if ($value === null || ! str_starts_with($value, self::PREFIX)) {
            return $value;
        }
        [$iv, $cipher, $mac] = array_map('base64_decode', explode(':', substr($value, strlen(self::PREFIX)), 3));
        [$encKey, $macKey] = self::keys($key);

        if (! hash_equals(hash_hmac('sha256', $iv.$cipher, $macKey, true), $mac)) {
            throw new \RuntimeException('El dato cifrado fue modificado.');
        }

        return openssl_decrypt($cipher, 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);
    }

    private static function keys(string $key): array
    {
        return [hash_hmac('sha256', 'enc', $key, true), hash_hmac('sha256', 'mac', $key, true)];
    }
}
