<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\EventType;
use App\Services\GoogleSyncService;
use App\Services\SyncService;
use App\Support\Dates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    /**
     * POST /api/v1/sync
     * Body: { "last_synced_at": "2026-11-14T10:00:00Z" | null,
     *         "changes": { "projects": [...], "tags": [...], "events": [...], "tasks": [...], "subtasks": [...], "reminders": [...] } }
     */
    public function __invoke(Request $request, SyncService $sync, GoogleSyncService $google): JsonResponse
    {
        $request->validate([
            'last_synced_at' => ['nullable', 'date'],
            'changes' => ['nullable', 'array'],
        ]);

        // Se toma la hora ANTES de procesar para no perder cambios concurrentes
        $serverTime = now();
        $user = $request->user();
        $since = Dates::toUtc($request->input('last_synced_at'));

        $pushResult = $sync->push($user, $request->input('changes', []));

        // Si el usuario conecto Google, se sincroniza tambien con Calendar/Tasks antes de responder,
        // asi lo que llego de Google viaja al movil en esta misma respuesta.
        $googleResult = null;
        if ($google->shouldAutoSync($user, $pushResult['applied'] > 0)) {
            try {
                $googleResult = ['ok' => true, 'stats' => $google->sync($user)];
            } catch (\Throwable $e) {
                report($e);
                $googleResult = ['ok' => false, 'message' => GoogleSyncService::errorMessage($e)];
            }
        }

        return response()->json([
            'server_time' => $serverTime->toISOString(),
            'push' => $pushResult,
            'google' => $googleResult,
            'changes' => $sync->pull($user, $since),
            'event_types' => EventType::orderBy('name')->get(),
            'announcements' => Announcement::visible()->latest('published_at')->get(),
            'user' => ProfileController::present($user->fresh()),
        ]);
    }
}
