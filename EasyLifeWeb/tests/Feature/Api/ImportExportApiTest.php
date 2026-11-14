<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImportExportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_ics_and_reimport(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Event::create(['user_id' => $user->id, 'title' => 'Cena, familia', 'start_at' => '2026-12-24 02:00:00', 'end_at' => '2026-12-24 04:00:00']);

        $ics = $this->get('/api/v1/export/ics')->assertOk()->getContent();
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('SUMMARY:Cena\, familia', $ics);
        $this->assertStringContainsString('DTSTART:20261224T020000Z', $ics);

        $this->post('/api/v1/import', ['file' => UploadedFile::fake()->createWithContent('cal.ics', $ics)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('imported.events', 1);

        $this->assertEquals(2, $user->events()->where('title', 'Cena, familia')->count());
    }

    public function test_import_csv_creates_projects_and_tags(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $csv = "titulo,descripcion,prioridad,estado,fecha_limite,proyecto,etiquetas\n"
            ."Comprar leche,,alta,pendiente,2026-11-20 10:00,Casa,Super|Urgente\n"
            ."Leer libro,,baja,completada,,,\n";

        $this->post('/api/v1/import', ['file' => UploadedFile::fake()->createWithContent('t.csv', $csv)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('imported.tasks', 2);

        $this->assertEquals(1, $user->projects()->count());
        $this->assertEquals(2, $user->tags()->count());
        $this->get('/api/v1/export/csv')->assertOk()->assertSee('Comprar leche');
    }
}
