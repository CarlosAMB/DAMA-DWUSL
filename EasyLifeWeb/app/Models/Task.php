<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Task extends Model
{
    use Syncable;

    public const PRIORITIES = ['baja', 'media', 'alta', 'urgente'];
    public const STATUSES = ['pendiente', 'en_progreso', 'completada'];

    protected $fillable = [
        'id', 'user_id', 'project_id', 'event_id', 'title', 'description', 'priority',
        'status', 'due_date', 'completed_at', 'google_task_id', 'client_updated_at',
    ];

    protected $syncFields = ['project_id', 'event_id', 'title', 'description', 'priority', 'status', 'due_date', 'completed_at'];

    public static function rules(int $userId): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'project_id' => ['nullable', 'uuid', Rule::exists('projects', 'id')->where('user_id', $userId)],
            'event_id' => ['nullable', 'uuid', Rule::exists('events', 'id')->where('user_id', $userId)],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['nullable', Rule::in(self::PRIORITIES)],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'due_date' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['uuid', Rule::exists('tags', 'id')->where('user_id', $userId)],
        ];
    }

    protected function casts(): array
    {
        return [
            'description' => 'encrypted',
            'due_date' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function subtasks()
    {
        return $this->hasMany(Subtask::class)->orderBy('position');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function reminders()
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }
}
