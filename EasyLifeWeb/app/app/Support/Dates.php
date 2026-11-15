<?php

namespace App\Support;

use Carbon\Carbon;

class Dates
{
    /**
     * Convierte cualquier fecha ISO-8601 recibida del movil a UTC
     * (la base de datos guarda todas las fechas en UTC).
     */
    public static function toUtc($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->utc();
    }
}
