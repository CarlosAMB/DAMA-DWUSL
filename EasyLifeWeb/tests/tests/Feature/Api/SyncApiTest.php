<?php

namespace Tests\Feature\Api;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SyncApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_offline_changes_and_pull(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $projectId = (string) Str::uuid();
        $taskId = (string) Str::uuid();
        $tagId = (string) Str::uuid();

        $response = $this->postJson('/api/v1/sync', [
            'last_synced_at' => null,
            'changes' => [
                'projects' => [['id' => $projectId, 'name' => 'Offline', 'client_updated_at' => '2026-11-14T10:00:00Z']],
                'tags' => [['id' => $tagId, 'name' => 'Casa', 'client_updated_at' => '2026-11-14T10:00:00Z']],
                'tasks' => [[
                    'id' => $taskId, 'title' => 'Creada sin internet', 'project_id' => $projectId,
                    'tag_ids' => [$tagId], 'client_updated_at' => '2026-11-14T10:00:00Z',
                ]],
                'subtasks' => [['id' => (string) Str::uuid(), 'task_id' => $taskId, 'title' => 'Paso 1', 'client_updated_at' => '2026-11-14T10:00:00Z']],
                'reminders' => [[
                    'id' => (string) Str::uuid(), 'remindable_type' => 'task', 'remindable_id' => $taskId,
                    'remind_at' => '2026-11-15T10:00:00Z', 'client_updated_at' => '2026-11-14T10:00:00Z',
                ]],
            ],
        ])->assertOk();

        $response->assertJsonPath('push.applied', 5)
            ->assertJsonPath('push.errors', [])
            ->assertJsonCount(1, 'changes.tasks')
            ->assertJsonPath('changes.tasks.0.tag_ids.0', $tagId)
            ->assertJsonStructure(['server_time', 'event_types', 'announcements', 'user']);

        $this->assertDatabaseHas('tasks', ['id' => $taskId, 'user_id' => $user->id]);
    }

    public function test_last_write_wins_and_deletions(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $task = Task::create(['user_id' => $user->id, 'title' => 'Servidor', 'client_updated_at' => '2026-11-14 12:00:00']);

        // Cambio mas viejo que el del servidor: se ignora
        $this->postJson('/api/v1/sync', ['changes' => ['tasks' => [
            ['id' => $task->id, 'title' => 'Viejo', 'client_updated_at' => '2026-11-14T11:00:00Z'],
        ]]])->assertJsonPath('push.skipped', 1);
        $this->assertEquals('Servidor', $task->fresh()->title);

        // Cambio mas nuevo: se aplica
        $this->postJson('/api/v1/sync', ['changes' => ['tasks' => [
            ['id' => $task->id, 'title' => 'Nuevo', 'client_updated_at' => '2026-11-14T13:00:00Z'],
        ]]])->assertJsonPath('push.applied', 1);
        $this->assertEquals('Nuevo', $task->fresh()->title);

        // Eliminacion desde el movil y el pull la informa
        $since = now()->subMinute()->toISOString();
        $this->postJson('/api/v1/sync', ['last_synced_at' => $since, 'changes' => ['tasks' => [
            ['id' => $task->id, 'deleted_at' => '2026-11-14T14:00:00Z', 'client_updated_at' => '2026-11-14T14:00:00Z'],
        ]]])->assertJsonPath('changes.tasks.0.id', $task->id)
            ->assertJsonPath('changes.tasks.0.deleted_at', fn ($v) => $v !== null);
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_cannot_overwrite_records_of_another_user(): void
    {
        $other = User::factory()->create();
        $task = Task::create(['user_id' => $other->id, 'title' => 'Ajena']);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/sync', ['changes' => ['tasks' => [
            ['id' => $task->id, 'title' => 'Hackeada', 'client_updated_at' => '2030-01-01T00:00:00Z'],
        ]]])->assertJsonCount(1, 'push.errors');

        $this->assertEquals('Ajena', $task->fresh()->title);
    }
}
