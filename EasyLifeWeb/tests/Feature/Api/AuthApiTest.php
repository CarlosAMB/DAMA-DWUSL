<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_token_and_requires_privacy(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ana', 'email' => 'ana@test.com', 'password' => 'Secreta123', 'password_confirmation' => 'Secreta123',
        ])->assertStatus(422)->assertJsonValidationErrors('accept_privacy');

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ana', 'email' => 'ana@test.com', 'password' => 'Secreta123',
            'password_confirmation' => 'Secreta123', 'accept_privacy' => true,
        ])->assertCreated()->assertJsonStructure(['token', 'user' => ['id', 'email', 'preferences']]);

        $this->assertNotEquals('Secreta123', User::first()->password); // guardada con hash
    }

    public function test_login_and_inactive_user_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'Secreta123']);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'mala'])->assertStatus(422);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Secreta123'])
            ->assertOk()->assertJsonStructure(['token']);

        $user->update(['is_active' => false]);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Secreta123'])->assertStatus(422);
    }

    public function test_protected_routes_need_token(): void
    {
        $this->getJson('/api/v1/tasks')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/tasks')->assertOk();
    }

    public function test_update_preferences(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/v1/profile', ['preferences' => ['theme' => 'dark', 'calendar_view' => 'week']])
            ->assertOk()
            ->assertJsonPath('user.preferences.theme', 'dark')
            ->assertJsonPath('user.preferences.default_reminder_minutes', 15);
    }
}
