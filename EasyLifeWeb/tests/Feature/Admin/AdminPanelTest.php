<?php

namespace Tests\Feature\Admin;

use App\Models\Announcement;
use App\Models\EventType;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@easylife.test')->first();
    }

    public function test_all_admin_pages_render(): void
    {
        $this->actingAs($this->admin());
        $demo = User::where('email', 'demo@easylife.test')->first();

        foreach ([
            '/admin', '/admin/users', '/admin/users/create', "/admin/users/{$demo->id}", "/admin/users/{$demo->id}/edit",
            '/admin/event-types', '/admin/event-types/create', '/admin/announcements', '/admin/announcements/create',
            '/admin/reports', '/admin/reports/export', '/privacidad',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_regular_user_cannot_access_panel(): void
    {
        $user = User::where('email', 'demo@easylife.test')->first();

        $this->post('/login', ['email' => $user->email, 'password' => 'Demo123*'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_login(): void
    {
        $this->post('/login', ['email' => 'admin@easylife.test', 'password' => 'Admin123*'])->assertRedirect('/admin');
    }

    public function test_crud_catalog_and_toggle_user(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/event-types', ['name' => 'Viaje', 'color' => '#112233', 'icon' => 'airplane', 'is_active' => 1])
            ->assertRedirect('/admin/event-types');
        $this->assertDatabaseHas('event_types', ['name' => 'Viaje']);

        $this->post('/admin/announcements', ['title' => 'Mantenimiento', 'body' => 'El sábado', 'level' => 'warning', 'is_active' => 1])
            ->assertRedirect('/admin/announcements');
        $this->assertEquals(2, Announcement::count());

        $demo = User::where('email', 'demo@easylife.test')->first();
        $demo->createToken('movil');
        $this->patch("/admin/users/{$demo->id}/toggle")->assertRedirect();
        $this->assertFalse($demo->fresh()->is_active);
        $this->assertEquals(0, $demo->tokens()->count()); // se cierran sus sesiones

        // Un tipo con eventos no se elimina, se desactiva
        $type = EventType::where('name', 'Reunión')->first();
        $this->delete("/admin/event-types/{$type->id}");
        $this->assertFalse($type->fresh()->is_active);
    }
}
