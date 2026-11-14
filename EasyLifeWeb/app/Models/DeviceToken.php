<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Token de Firebase Cloud Messaging de un telefono (para notificaciones push). */
class DeviceToken extends Model
{
    protected $fillable = ['user_id', 'token', 'platform', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
