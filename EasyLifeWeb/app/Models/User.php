<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** Preferencias por defecto de organizacion. */
    public const DEFAULT_PREFERENCES = [
        'theme' => 'light',              // light | dark
        'calendar_view' => 'month',      // day | week | month | year
        'default_reminder_minutes' => 15,
        'daily_summary' => true,
        'daily_summary_time' => '07:00',
        'week_starts_on' => 1,           // 0 domingo, 1 lunes
        'notifications_enabled' => true,
    ];

    /** Valores por defecto (iguales a los de la migracion). */
    protected $attributes = [
        'role' => 'user',
        'is_active' => true,
        'timezone' => 'America/El_Salvador',
    ];

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'role', 'is_active', 'timezone',
        'preferences', 'privacy_accepted_at', 'last_login_at',
        'google_access_token', 'google_refresh_token', 'google_token_expires_at', 'google_email', 'google_synced_at',
        'firebase_uid', 'data_key',
    ];

    protected $hidden = [
        'password', 'remember_token', 'google_access_token', 'google_refresh_token', 'data_key',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'preferences' => 'array',
            'privacy_accepted_at' => 'datetime',
            'last_login_at' => 'datetime',
            'google_access_token' => 'encrypted',
            'google_refresh_token' => 'encrypted',
            'google_token_expires_at' => 'datetime',
            'google_synced_at' => 'datetime',
            'data_key' => 'encrypted',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Preferencias del usuario combinadas con los valores por defecto. */
    public function getSettingsAttribute(): array
    {
        return array_merge(self::DEFAULT_PREFERENCES, $this->preferences ?? []);
    }

    /**
     * Llave AES-256 del usuario (bytes). Se crea la primera vez.
     * Cifra los campos sensibles en Firestore y en el SQLite del telefono.
     */
    public function dataKey(): string
    {
        if (! $this->data_key) {
            $this->forceFill(['data_key' => base64_encode(random_bytes(32))])->saveQuietly();
        }

        return base64_decode($this->data_key);
    }

    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function tags()
    {
        return $this->hasMany(Tag::class);
    }

    public function subtasks()
    {
        return $this->hasMany(Subtask::class);
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }
}
