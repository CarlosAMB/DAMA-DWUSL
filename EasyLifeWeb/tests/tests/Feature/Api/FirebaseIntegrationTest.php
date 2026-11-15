<?php

namespace Tests\Feature\Api;

use App\Models\DeviceToken;
use App\Models\Event;
use App\Models\User;
use App\Support\DataCipher;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Firebase: Authentication (ID tokens), espejo cifrado en Firestore y push con FCM.
 * Las llamadas a Google se simulan; los tokens se firman con una llave RSA generada en la prueba.
 */
class FirebaseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const PROJECT = 'easylife-test';

    private string $privateKey;

    /** @var array<int, Request> */
    private array $requests = [];

    private array $fcmResponse = [200, ['name' => 'projects/x/messages/1']];

    private array $listedDocuments = [];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        // Llave RSA solo para pruebas (simula la cuenta de servicio y las llaves de Firebase Auth)
        $pem = file_get_contents(base_path('tests/fixtures/firebase-test-key.pem'));
        $key = openssl_pkey_get_private($pem);
        $this->privateKey = $pem;
        $rsa = openssl_pkey_get_details($key)['rsa'];
        $b64url = fn ($v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        $jwks = ['keys' => [['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => 'test-kid', 'n' => $b64url($rsa['n']), 'e' => $b64url($rsa['e'])]]];

        config([
            'services.firebase.project_id' => self::PROJECT,
            'services.firebase.collection' => 'users', // independiente del .env local (dev_users)
            'services.firebase.credentials' => json_encode([
                'project_id' => self::PROJECT,
                'client_email' => 'laravel@easylife-test.iam.gserviceaccount.com',
                'private_key' => $this->privateKey,
            ]),
        ]);

        Http::fake(function (Request $request) use ($jwks) {
            $this->requests[] = $request;
            $url = $request->url();

            return match (true) {
                str_contains($url, 'securetoken@system.gserviceaccount.com') => Http::response($jwks),
                str_contains($url, 'oauth2.googleapis.com/token') => Http::response(['access_token' => 'sa-token', 'expires_in' => 3600]),
                str_contains($url, 'fcm.googleapis.com') => Http::response($this->fcmResponse[1], $this->fcmResponse[0]),
                $request->method() === 'GET' && str_contains($url, 'firestore.googleapis.com') => Http::response(['documents' => $this->listedDocuments]),
                default => Http::response([]),
            };
        });
    }

    private function idToken(string $uid, string $email, array $extra = []): string
    {
        return JWT::encode(array_merge([
            'iss' => 'https://securetoken.google.com/'.self::PROJECT,
            'aud' => self::PROJECT,
            'sub' => $uid,
            'email' => $email,
            'email_verified' => false,
            'auth_time' => time(),
            'iat' => time(),
            'exp' => time() + 3600,
        ], $extra), $this->privateKey, 'RS256', 'test-kid');
    }

    /** Peticiones :commit enviadas a Firestore. */
    private function commits(): array
    {
        return collect($this->requests)
            ->filter(fn (Request $r) => str_ends_with($r->url(), ':commit'))
            ->flatMap(fn (Request $r) => $r->data()['writes'])
            ->all();
    }

    public function test_register_with_firebase_creates_account_and_returns_data_key(): void
    {
        $response = $this->postJson('/api/v1/auth/firebase', [
            'id_token' => $this->idToken('uid-nuevo', 'Nuevo@Correo.com'),
            'name' => 'Usuario Nuevo',
            'accept_privacy' => true,
        ])->assertCreated()->assertJsonPath('user.email', 'nuevo@correo.com')->assertJsonPath('user.firebase_uid', 'uid-nuevo');

        $this->assertSame(32, strlen(base64_decode($response->json('data_key'))));
        $this->assertNotEmpty($response->json('token'));
        // Luego inicia sesion con el mismo uid
        $this->postJson('/api/v1/auth/firebase', ['id_token' => $this->idToken('uid-nuevo', 'nuevo@correo.com')])
            ->assertOk()->assertJsonPath('data_key', $response->json('data_key'));
    }

    public function test_rejects_invalid_tokens_and_unknown_accounts(): void
    {
        // Token de otro proyecto
        $this->postJson('/api/v1/auth/firebase', ['id_token' => $this->idToken('u1', 'a@b.com', ['aud' => 'otro', 'iss' => 'https://securetoken.google.com/otro']), 'name' => 'X', 'accept_privacy' => true])
            ->assertStatus(422);
        // Token expirado
        $this->postJson('/api/v1/auth/firebase', ['id_token' => $this->idToken('u1', 'a@b.com', ['exp' => time() - 3600]), 'name' => 'X', 'accept_privacy' => true])
            ->assertStatus(422);
        // Firma falsa
        $this->postJson('/api/v1/auth/firebase', ['id_token' => 'eyJhbGciOiJSUzI1NiJ9.e30.firma'])->assertStatus(422);
        // Inicio de sesion sin cuenta en Laravel y sin datos de registro
        $this->postJson('/api/v1/auth/firebase', ['id_token' => $this->idToken('u1', 'a@b.com')])->assertStatus(422);
        // Registro sin aceptar privacidad
        $this->postJson('/api/v1/auth/firebase', ['id_token' => $this->idToken('u1', 'a@b.com'), 'name' => 'X'])
            ->assertStatus(422)->assertJsonValidationErrors('accept_privacy');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_account_is_linked_only_with_its_password_and_data_is_mirrored_encrypted(): void
    {
        $user = User::factory()->create(['email' => 'demo@easylife.test', 'password' => 'Demo123*']);
        $event = Event::create([
            'id' => (string) Str::uuid(), 'user_id' => $user->id, 'title' => 'Cita médica', 'description' => 'Llevar exámenes',
            'start_at' => '2026-11-20 15:00:00', 'client_updated_at' => now(),
        ]);
        $token = $this->idToken('uid-demo', 'demo@easylife.test');

        // Alguien crea una cuenta de Firebase con ese correo pero no conoce la contraseña
        $this->postJson('/api/v1/auth/firebase', ['id_token' => $token])->assertStatus(422);
        $this->postJson('/api/v1/auth/firebase', ['id_token' => $token, 'password' => 'otra'])->assertStatus(422);
        $this->assertNull($user->fresh()->firebase_uid);

        $this->postJson('/api/v1/auth/firebase', ['id_token' => $token, 'password' => 'Demo123*'])->assertOk();
        $this->assertSame('uid-demo', $user->fresh()->firebase_uid);

        // Sus datos se subieron a Firestore con el titulo y la descripcion cifrados
        $doc = collect($this->commits())->firstWhere('update.name', 'projects/'.self::PROJECT.'/databases/(default)/documents/users/uid-demo/events/'.$event->id)['update'];
        $title = $doc['fields']['title']['stringValue'];
        $this->assertStringStartsWith('ev1:', $title);
        $this->assertSame('Cita médica', DataCipher::decrypt($title, $user->fresh()->dataKey()));
        $this->assertSame('Llevar exámenes', DataCipher::decrypt($doc['fields']['description']['stringValue'], $user->fresh()->dataKey()));
        $this->assertArrayNotHasKey('user_id', $doc['fields']);
        $this->assertSame('2026-11-20T15:00:00.000000Z', $doc['fields']['start_at']['stringValue']);
    }

    public function test_sync_changes_are_mirrored_to_firestore(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'uid-1']);
        Sanctum::actingAs($user);
        $id = (string) Str::uuid();

        $this->postJson('/api/v1/sync', ['last_synced_at' => null, 'changes' => ['events' => [[
            'id' => $id, 'title' => 'Examen', 'start_at' => '2026-11-21T14:00:00Z', 'end_at' => null, 'all_day' => false,
            'client_updated_at' => now()->toISOString(), 'deleted_at' => null,
        ]]]])->assertOk();

        $doc = collect($this->commits())->firstWhere('update.name', 'projects/'.self::PROJECT.'/databases/(default)/documents/users/uid-1/events/'.$id)['update'];
        $this->assertSame('Examen', DataCipher::decrypt($doc['fields']['title']['stringValue'], $user->fresh()->dataKey()));
        $this->assertSame(['nullValue' => null], $doc['fields']['deleted_at']);

        // Al eliminarlo tambien se informa (borrado logico)
        $this->requests = [];
        $this->postJson('/api/v1/sync', ['last_synced_at' => null, 'changes' => ['events' => [[
            'id' => $id, 'client_updated_at' => now()->addMinute()->toISOString(), 'deleted_at' => now()->toISOString(),
        ]]]])->assertOk();
        $doc = collect($this->commits())->last()['update'];
        $this->assertArrayHasKey('stringValue', $doc['fields']['deleted_at']);
    }

    public function test_users_without_firebase_are_not_mirrored(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/sync', ['last_synced_at' => null, 'changes' => ['tags' => [[
            'id' => (string) Str::uuid(), 'name' => 'Casa', 'color' => '#ff0000', 'client_updated_at' => now()->toISOString(),
        ]]]])->assertOk();

        $this->assertSame([], $this->commits());
    }

    public function test_devices_are_registered_and_receive_announcement_push(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/devices', ['token' => 'fcm-token-1', 'platform' => 'android'])->assertOk();
        $this->postJson('/api/v1/devices', ['token' => 'fcm-token-1'])->assertOk(); // no se duplica
        $this->assertSame(1, DeviceToken::count());

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Mantenimiento', 'body' => 'El sábado no habrá servicio', 'level' => 'warning', 'is_active' => 1, 'send_push' => 1,
        ])->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, '1 dispositivo'));

        $sent = collect($this->requests)->first(fn (Request $r) => str_contains($r->url(), 'fcm.googleapis.com/v1/projects/'.self::PROJECT.'/messages:send'));
        $this->assertSame('fcm-token-1', $sent['message']['token']);
        $this->assertSame('Mantenimiento', $sent['message']['notification']['title']);
        $this->assertSame('Bearer sa-token', $sent->header('Authorization')[0]);

        // Si FCM indica que el token ya no existe (app desinstalada), se elimina
        $this->fcmResponse = [404, ['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]]];
        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Otro', 'body' => 'Aviso', 'level' => 'info', 'is_active' => 1, 'send_push' => 1,
        ])->assertRedirect();
        $this->assertSame(0, DeviceToken::count());
    }

    public function test_delete_account_with_recent_firebase_token_removes_firestore_data(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'uid-borrar']);
        $docName = 'projects/'.self::PROJECT.'/databases/(default)/documents/users/uid-borrar/events/abc';
        $this->listedDocuments = [['name' => $docName]];
        Sanctum::actingAs($user);

        // Token de otra cuenta o de un inicio de sesion antiguo: se rechaza
        $this->deleteJson('/api/v1/profile', ['id_token' => $this->idToken('otro', 'x@y.com')])->assertStatus(422);
        $this->deleteJson('/api/v1/profile', ['id_token' => $this->idToken('uid-borrar', $user->email, ['auth_time' => time() - 3600])])->assertStatus(422);

        $this->deleteJson('/api/v1/profile', ['id_token' => $this->idToken('uid-borrar', $user->email)])->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertContains(['delete' => $docName], $this->commits());
    }
}
