<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Integracion con Google Calendar / Tasks (las llamadas a Google se simulan con Http::fake). */
class GoogleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const RETURN_URL = 'exp://10.0.2.2:8081/--/google';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.google.client_id' => 'test-client.apps.googleusercontent.com',
            'services.google.client_secret' => 'test-secret',
            'services.google.redirect' => 'http://localhost:8000/google/callback',
        ]);
    }

    private function connectedUser(): User
    {
        return User::factory()->create([
            'google_access_token' => 'access-1',
            'google_refresh_token' => 'refresh-1',
            'google_token_expires_at' => now()->addHour(),
            'google_email' => 'demo@gmail.com',
        ]);
    }

    public function test_auth_url_requires_configuration_and_valid_return_url(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/google/auth-url?return_url=https://malicioso.com')->assertStatus(422);

        $url = $this->getJson('/api/v1/google/auth-url?return_url='.urlencode(self::RETURN_URL))
            ->assertOk()->json('url');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString(urlencode('https://www.googleapis.com/auth/calendar.events'), $url);

        config(['services.google.client_id' => null]);
        $this->getJson('/api/v1/google/auth-url?return_url='.urlencode(self::RETURN_URL))->assertStatus(503);
    }

    public function test_callback_stores_encrypted_tokens_and_returns_to_app(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.abc', 'refresh_token' => '1//refresh', 'expires_in' => 3599]),
            'openidconnect.googleapis.com/*' => Http::response(['email' => 'usuario@gmail.com']),
        ]);
        $state = Crypt::encryptString(json_encode(['uid' => $user->id, 'return' => self::RETURN_URL, 'exp' => now()->addMinutes(5)->timestamp]));

        $this->get('/google/callback?code=4/abc&state='.urlencode($state))
            ->assertOk()
            ->assertSee(self::RETURN_URL.'?status=ok', false);

        $user->refresh();
        $this->assertSame('1//refresh', $user->google_refresh_token);
        $this->assertSame('usuario@gmail.com', $user->google_email);
        // En la base de datos el token queda cifrado
        $this->assertNotSame('1//refresh', \DB::table('users')->where('id', $user->id)->value('google_refresh_token'));
    }

    public function test_callback_rejects_invalid_or_expired_state(): void
    {
        $user = User::factory()->create();
        $expired = Crypt::encryptString(json_encode(['uid' => $user->id, 'return' => self::RETURN_URL, 'exp' => now()->subMinute()->timestamp]));

        $this->get('/google/callback?code=x&state=basura')->assertStatus(400);
        $this->get('/google/callback?code=x&state='.urlencode($expired))->assertStatus(400);
        $this->assertNull($user->fresh()->google_refresh_token);
    }

    public function test_sync_pushes_local_items_and_pulls_google_events(): void
    {
        $user = $this->connectedUser();
        $local = Event::create([
            'id' => (string) Str::uuid(), 'user_id' => $user->id, 'title' => 'Examen DAMA',
            'start_at' => '2026-11-20 15:00:00', 'end_at' => '2026-11-20 17:00:00', 'client_updated_at' => now(),
        ]);
        $task = Task::create([
            'id' => (string) Str::uuid(), 'user_id' => $user->id, 'title' => 'Entregar proyecto',
            'due_date' => '2026-11-21 23:00:00', 'client_updated_at' => now(),
        ]);

        $sent = [];
        Http::fake(function (Request $request) use (&$sent) {
            $url = $request->url();
            if ($request->method() === 'GET' && str_contains($url, '/calendar/v3/')) {
                return Http::response(['items' => [[
                    'id' => 'g-evt-1', 'status' => 'confirmed', 'summary' => 'Cumpleaños de Ana',
                    'updated' => now()->toRfc3339String(), 'start' => ['date' => '2026-11-22'], 'end' => ['date' => '2026-11-23'],
                ]]]);
            }
            if ($request->method() === 'GET' && str_contains($url, 'tasks.googleapis.com')) {
                return Http::response(['items' => []]);
            }
            $sent[] = [$request->method(), $url, $request->data()];

            return Http::response(['id' => str_contains($url, 'tasks') ? 'g-task-1' : 'g-evt-local']);
        });

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/google/sync')->assertOk()
            ->assertJsonPath('stats.events_pulled', 1)
            ->assertJsonPath('stats.events_pushed', 1)
            ->assertJsonPath('stats.tasks_pushed', 1);

        // Local -> Google
        $this->assertSame('g-evt-local', $local->fresh()->google_event_id);
        $this->assertSame('g-task-1', $task->fresh()->google_task_id);
        $eventBody = collect($sent)->first(fn ($s) => str_contains($s[1], 'calendar'))[2];
        $this->assertSame('Examen DAMA', $eventBody['summary']);
        $this->assertSame($local->id, $eventBody['extendedProperties']['private']['easylife_id']);
        $taskBody = collect($sent)->first(fn ($s) => str_contains($s[1], 'tasks'))[2];
        $this->assertSame('2026-11-21T00:00:00.000Z', $taskBody['due']); // 17:00 en El Salvador

        // Google -> local (evento de todo el dia)
        $pulled = Event::where('google_event_id', 'g-evt-1')->firstOrFail();
        $this->assertTrue($pulled->all_day);
        $this->assertSame('Cumpleaños de Ana', $pulled->title);
        $this->assertNotNull($user->fresh()->google_synced_at);

        // Una segunda sincronizacion sin cambios no reenvia nada
        $this->travel(5)->minutes();
        $sent = [];
        $this->postJson('/api/v1/google/sync')->assertOk()
            ->assertJsonPath('stats.events_pushed', 0)
            ->assertJsonPath('stats.events_pulled', 0);
        $this->assertSame([], $sent);
    }

    public function test_offline_sync_also_syncs_google_and_returns_pulled_events(): void
    {
        $user = $this->connectedUser();
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/calendar/v3/') && $request->method() === 'GET') {
                return Http::response(['items' => [[
                    'id' => 'g-evt-9', 'summary' => 'Reunión', 'updated' => now()->toRfc3339String(),
                    'start' => ['dateTime' => '2026-11-25T14:00:00-06:00'], 'end' => ['dateTime' => '2026-11-25T15:00:00-06:00'],
                ]]]);
            }

            return Http::response(['items' => []]);
        });

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/sync', ['last_synced_at' => null])->assertOk()
            ->assertJsonPath('google.ok', true)
            ->assertJsonPath('changes.events.0.title', 'Reunión')
            ->assertJsonPath('changes.events.0.start_at', '2026-11-25T20:00:00.000000Z');
    }

    public function test_revoked_access_disconnects_the_account(): void
    {
        $user = $this->connectedUser();
        $user->forceFill(['google_token_expires_at' => now()->subMinute()])->save();
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant'], 400)]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/sync', ['last_synced_at' => null])->assertOk()
            ->assertJsonPath('google.ok', false)
            ->assertJsonPath('user.google_connected', false);
    }

    public function test_disconnect_revokes_and_clears_tokens(): void
    {
        $user = $this->connectedUser();
        Http::fake(['oauth2.googleapis.com/revoke' => Http::response([], 200)]);

        Sanctum::actingAs($user);
        $this->deleteJson('/api/v1/google')->assertOk()->assertJsonPath('user.google_connected', false);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'revoke'));
        $this->assertNull($user->fresh()->google_refresh_token);
    }
}
