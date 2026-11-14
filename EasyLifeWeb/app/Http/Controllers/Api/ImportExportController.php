<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Services\IcsService;
use App\Support\Dates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Integracion con otras aplicaciones mediante archivos:
 *  - ICS: Google Calendar, Outlook, Apple Calendar
 *  - CSV: hojas de calculo, Todoist, Microsoft To Do (via Excel)
 *  - JSON: respaldo completo de EasyLife
 */
class ImportExportController extends Controller
{
    public const CSV_HEADERS = ['titulo', 'descripcion', 'prioridad', 'estado', 'fecha_limite', 'proyecto', 'etiquetas'];

    public function __construct(private IcsService $ics) {}

    /** GET /api/v1/export/{ics|csv|json} */
    public function export(Request $request, string $format)
    {
        $user = $request->user();
        $events = $user->events()->with('type')->orderBy('start_at')->get();
        $tasks = $user->tasks()->with(['project', 'tags'])->orderBy('due_date')->get();
        $stamp = now()->format('Ymd_His');

        return match ($format) {
            'ics' => response($this->ics->generate($events, $tasks), 200, [
                'Content-Type' => 'text/calendar; charset=utf-8',
                'Content-Disposition' => "attachment; filename=easylife_{$stamp}.ics",
            ]),
            'csv' => response($this->tasksToCsv($tasks, $user->timezone), 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=easylife_tareas_{$stamp}.csv",
            ]),
            'json' => response()->json([
                'app' => 'EasyLife', 'version' => 1, 'exported_at' => now(),
                'projects' => $user->projects()->get(),
                'tags' => $user->tags()->get(),
                'events' => $events,
                'tasks' => $tasks->map(fn ($t) => $t->toArray() + ['tag_names' => $t->tags->pluck('name')]),
            ]),
        };
    }

    /** POST /api/v1/import  (multipart: file) */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'], // 5 MB
        ]);

        $file = $request->file('file');
        $content = file_get_contents($file->getRealPath());
        $ext = strtolower($file->getClientOriginalExtension());
        $user = $request->user();

        $count = DB::transaction(function () use ($ext, $content, $user) {
            return match ($ext) {
                'ics' => $this->importIcs($user, $content),
                'csv' => $this->importCsv($user, $content),
                'json' => $this->importJson($user, $content),
                default => abort(422, 'Formato no soportado. Usa .ics, .csv o .json'),
            };
        });

        return response()->json(['message' => 'Importación completada', 'imported' => $count]);
    }

    private function importIcs($user, string $content): array
    {
        $data = $this->ics->parse($content, $user->timezone);
        $events = 0;
        $tasks = 0;

        foreach ($data['events'] as $e) {
            if (empty($e['title']) || empty($e['start_at'])) {
                continue;
            }
            $user->events()->create([
                'title' => $e['title'], 'description' => $e['description'] ?? null, 'location' => $e['location'] ?? null,
                'start_at' => $e['start_at'], 'end_at' => $e['end_at'] ?? null, 'all_day' => $e['all_day'] ?? false,
                'client_updated_at' => now(),
            ]);
            $events++;
        }
        foreach ($data['tasks'] as $t) {
            if (empty($t['title'])) {
                continue;
            }
            $user->tasks()->create([
                'title' => $t['title'], 'description' => $t['description'] ?? null, 'due_date' => $t['due_date'] ?? null,
                'priority' => $t['priority'] ?? 'media', 'status' => $t['status'] ?? 'pendiente',
                'completed_at' => ($t['status'] ?? null) === 'completada' ? now() : null,
                'client_updated_at' => now(),
            ]);
            $tasks++;
        }

        return ['events' => $events, 'tasks' => $tasks];
    }

    private function importCsv($user, string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // quitar BOM de Excel
        $rows = array_map('str_getcsv', preg_split("/\r\n|\n|\r/", trim($content)));
        $headers = array_map(fn ($h) => Str::slug(trim($h), '_'), array_shift($rows) ?? []);
        $tasks = 0;

        foreach ($rows as $row) {
            if (count(array_filter($row)) === 0) {
                continue;
            }
            $r = array_combine($headers, array_pad(array_slice($row, 0, count($headers)), count($headers), null));
            $title = trim($r['titulo'] ?? $r['title'] ?? $r['content'] ?? '');
            if ($title === '') {
                continue;
            }

            $projectId = null;
            if (! empty($r['proyecto'])) {
                $projectId = Project::firstOrCreate(
                    ['user_id' => $user->id, 'name' => trim($r['proyecto'])],
                    ['client_updated_at' => now()]
                )->id;
            }

            $priority = Str::lower(trim($r['prioridad'] ?? $r['priority'] ?? 'media'));
            $status = Str::lower(trim($r['estado'] ?? 'pendiente'));
            $task = $user->tasks()->create([
                'title' => mb_substr($title, 0, 150),
                'description' => $r['descripcion'] ?? $r['description'] ?? null,
                'priority' => in_array($priority, Task::PRIORITIES) ? $priority : 'media',
                'status' => in_array($status, Task::STATUSES) ? $status : 'pendiente',
                'due_date' => ! empty($r['fecha_limite'] ?? $r['due_date'] ?? null) ? rescue(fn () => \Carbon\Carbon::parse($r['fecha_limite'] ?? $r['due_date'], $user->timezone)->utc(), null, false) : null,
                'project_id' => $projectId,
                'client_updated_at' => now(),
            ]);

            if (! empty($r['etiquetas'])) {
                $tagIds = collect(explode('|', $r['etiquetas']))->map('trim')->filter()->map(
                    fn ($name) => Tag::firstOrCreate(['user_id' => $user->id, 'name' => mb_substr($name, 0, 40)], ['client_updated_at' => now()])->id
                );
                $task->tags()->sync($tagIds);
            }
            $tasks++;
        }

        return ['events' => 0, 'tasks' => $tasks];
    }

    private function importJson($user, string $content): array
    {
        $data = json_decode($content, true);
        abort_unless(is_array($data), 422, 'El archivo JSON no es válido');
        $events = 0;
        $tasks = 0;

        foreach ($data['events'] ?? [] as $e) {
            if (empty($e['title']) || empty($e['start_at'])) {
                continue;
            }
            $user->events()->create([
                'title' => mb_substr($e['title'], 0, 150), 'description' => $e['description'] ?? null,
                'location' => $e['location'] ?? null, 'start_at' => Dates::toUtc($e['start_at']),
                'end_at' => Dates::toUtc($e['end_at'] ?? null), 'all_day' => (bool) ($e['all_day'] ?? false),
                'event_type_id' => $e['event_type_id'] ?? null, 'client_updated_at' => now(),
            ]);
            $events++;
        }
        foreach ($data['tasks'] ?? [] as $t) {
            if (empty($t['title'])) {
                continue;
            }
            $task = $user->tasks()->create([
                'title' => mb_substr($t['title'], 0, 150), 'description' => $t['description'] ?? null,
                'priority' => in_array($t['priority'] ?? '', Task::PRIORITIES) ? $t['priority'] : 'media',
                'status' => in_array($t['status'] ?? '', Task::STATUSES) ? $t['status'] : 'pendiente',
                'due_date' => Dates::toUtc($t['due_date'] ?? null), 'client_updated_at' => now(),
            ]);
            if (! empty($t['tag_names'])) {
                $task->tags()->sync(collect($t['tag_names'])->map(
                    fn ($name) => Tag::firstOrCreate(['user_id' => $user->id, 'name' => mb_substr($name, 0, 40)], ['client_updated_at' => now()])->id
                ));
            }
            $tasks++;
        }

        return ['events' => $events, 'tasks' => $tasks];
    }

    private function tasksToCsv($tasks, string $timezone): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel muestre bien los acentos
        fputcsv($out, self::CSV_HEADERS);
        foreach ($tasks as $t) {
            fputcsv($out, [
                $t->title, $t->description, $t->priority, $t->status,
                $t->due_date?->copy()->setTimezone($timezone)->format('Y-m-d H:i'),
                $t->project?->name, $t->tags->pluck('name')->implode('|'),
            ]);
        }
        rewind($out);

        return stream_get_contents($out);
    }
}
