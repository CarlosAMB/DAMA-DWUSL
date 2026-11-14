<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use Syncable;

    protected $fillable = [
        'id', 'user_id', 'event_type_id', 'title', 'description', 'location',
        'start_at', 'end_at', 'all_day', 'google_event_id', 'client_updated_at',
    ];

    protected $syncFields = ['event_type_id', 'title', 'description', 'location', 'start_at', 'end_at', 'all_day'];

    public static function rules(int $userId): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'event_type_id' => ['nullable', 'integer', 'exists:event_types,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:150'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'all_day' => ['boolean'],
        ];
    }

    protected function casts(): array
    {
        return [
            'description' => 'encrypted',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'all_day' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function type()
    {
        return $this->belongsTo(EventType::class, 'event_type_id');
    }

    /** Tareas asignadas al evento. */
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function reminders()
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }
}
