<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\EventType;
use Illuminate\Http\JsonResponse;

/** Datos administrados desde el panel web que la app solo consulta. */
class CatalogController extends Controller
{
    /** GET /api/v1/event-types */
    public function eventTypes(): JsonResponse
    {
        return response()->json(['data' => EventType::where('is_active', true)->orderBy('name')->get()]);
    }

    /** GET /api/v1/announcements */
    public function announcements(): JsonResponse
    {
        return response()->json(['data' => Announcement::visible()->latest('published_at')->get()]);
    }
}
