<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Project extends Model
{
    use Syncable;

    public const STATUSES = ['activo', 'pausado', 'completado', 'archivado'];

    protected $fillable = ['id', 'user_id', 'name', 'description', 'color', 'status', 'due_date', 'client_updated_at'];

    protected $syncFields = ['name', 'description', 'color', 'status', 'due_date'];

    /** Reglas de validacion (se usan en la API REST y en la sincronizacion). */
    public static function rules(int $userId): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'due_date' => ['nullable', 'date'],
        ];
    }

    protected function casts(): array
    {
        return [
            'description' => 'encrypted',
            'due_date' => 'date:Y-m-d',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    /** Porcentaje de tareas completadas (0 - 100). */
    public function getProgressAttribute(): int
    {
        $total = $this->tasks()->count();
        if ($total === 0) {
            return 0;
        }

        return (int) round($this->tasks()->where('status', 'completada')->count() * 100 / $total);
    }
}
