<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Comportamiento comun de las tablas que se sincronizan con la app movil.
 *  - El id es un UUID (el movil lo genera aunque no tenga conexion).
 *  - Las eliminaciones son logicas para poder informarlas a otros dispositivos.
 */
trait Syncable
{
    use HasUuids, SoftDeletes;

    public function initializeSyncable(): void
    {
        $this->mergeCasts([
            'client_updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ]);
    }

    /** Registros del usuario indicado. */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /** Registros modificados en el servidor despues de una fecha (incluye eliminados). */
    public function scopeChangedSince(Builder $query, $since): Builder
    {
        $query->withTrashed();

        return $since ? $query->where('updated_at', '>', $since) : $query;
    }

    /**
     * Campos que el movil puede enviar al sincronizar.
     * Cada modelo define la propiedad $syncFields.
     */
    public static function syncFields(): array
    {
        return (new static)->syncFields ?? [];
    }
}
