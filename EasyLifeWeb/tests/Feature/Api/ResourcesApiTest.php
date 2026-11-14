<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResourcesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_crud_with_tags_and_subtasks(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $project = $this->postJson('/api/v1/projects', ['name' => 'Tesis'])->assertCreated()->json('data');
        $tag = $this->postJson('/api/v1/tags', ['name' => 'U'])->assertCreated()->json('data');

        $task = $this->postJson('/api/v1/tasks', [
            'title' => 'Capítulo 1', 'priority' => 'alta', 'project_id' => $project['id'],
            'due_date' => '2026-12-01T15:00:00-06:00', 'description' => 'Texto privado', 'tag_ids' => [$tag['id']],
        ])->assertCreated()->assertJsonPath('data.tag_ids.0', $tag['id'])->json('data');

        // La fecha se guarda en UTC
        $this->assertStringStartsWith('2026-12-01T21:00:00', $task['due_date']);
        // La descripcion se guarda encriptada en la BD
        $this->assertNotEquals('Texto privado', DB::table('tasks')->value('description'));

        $this->postJson('/api/v1/subtasks', ['task_id' => $task['id'], 'title' => 'Introducción'])->assertCreated();

        $this->patchJson("/api/v1/tasks/{$task['id']}/complete")->assertOk()->assertJsonPath('data.status', 'completada');
        $this->getJson("/api/v1/projects/{$project['id']}")->assertJsonPath('data.progress', 100);

        $this->getJson('/api/v1/tasks?priority=alta')->assertJsonCount(1, 'data');
        $this->deleteJson("/api/v1/tasks/{$task['id']}")->assertOk();
        $this->assertSoftDeleted('tasks', ['id' => $task['id']]);
    }

    public function test_user_cannot_access_records_of_another_user(): void
    {
        $owner = User::factory()->create();
        $project = Project::create(['user_id' => $owner->id, 'name' => 'Privado']);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/projects/{$project->id}")->assertNotFound();
        $this->postJson('/api/v1/tasks', ['title' => 'x', 'project_id' => $project->id])->assertStatus(422);
    }

    public function test_calendar_range(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/events', ['title' => 'Reunión', 'start_at' => '2026-11-20T15:00:00Z', 'end_at' => '2026-11-20T16:00:00Z'])->assertCreated();
        $this->postJson('/api/v1/events', ['title' => 'Fuera', 'start_at' => '2027-01-20T15:00:00Z'])->assertCreated();
        Task::create(['user_id' => $user->id, 'title' => 'Entrega', 'due_date' => '2026-11-21 12:00:00']);

        $this->getJson('/api/v1/calendar?from=2026-11-01T00:00:00Z&to=2026-11-30T23:59:59Z')
            ->assertOk()->assertJsonCount(1, 'events')->assertJsonCount(1, 'tasks');
    }
}
