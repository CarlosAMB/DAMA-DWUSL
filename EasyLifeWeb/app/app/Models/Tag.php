<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use Syncable;

    protected $fillable = ['id', 'user_id', 'name', 'color', 'client_updated_at'];

    protected $syncFields = ['name', 'color'];

    public static function rules(int $userId): array
    {
        return [
            'name' => ['required', 'string', 'max:40'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    public function tasks()
    {
        return $this->belongsToMany(Task::class);
    }
}
