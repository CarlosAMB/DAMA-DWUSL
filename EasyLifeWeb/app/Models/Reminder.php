<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Reminder extends Model
{
    use Syncable;

    public const TYPES = ['event', 'task'];

    protected $fillable = ['id', 'user_id', 'remindable_type', 'remindable_id', 'remind_at', 'minutes_before', 'client_updated_at'];

    protected $syncFields = ['remindable_type', 'remindable_id', 'remind_at', 'minutes_before'];

    public static function rules(int $userId): array
    {
        return [
            'remindable_type' => ['required', Rule::in(self::TYPES)],
            'remindable_id' => ['required', 'uuid'],
            'remind_at' => ['required', 'date'],
            'minutes_before' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function casts(): array
    {
        return ['remind_at' => 'datetime', 'minutes_before' => 'integer'];
    }

    public function remindable()
    {
        return $this->morphTo();
    }
}
