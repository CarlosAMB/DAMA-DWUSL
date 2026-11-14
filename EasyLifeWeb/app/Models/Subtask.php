<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Subtask extends Model
{
    use Syncable;

    protected $fillable = ['id', 'user_id', 'task_id', 'title', 'is_done', 'position', 'client_updated_at'];

    protected $syncFields = ['task_id', 'title', 'is_done', 'position'];

    public static function rules(int $userId): array
    {
        return [
            'task_id' => ['required', 'uuid', Rule::exists('tasks', 'id')->where('user_id', $userId)],
            'title' => ['required', 'string', 'max:150'],
            'is_done' => ['boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function casts(): array
    {
        return ['is_done' => 'boolean', 'position' => 'integer'];
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
