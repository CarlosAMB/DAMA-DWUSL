<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventTypeController extends Controller
{
    /** Iconos disponibles (MaterialCommunityIcons, los mismos que usa la app). */
    public const ICONS = [
        'calendar', 'account-group', 'calendar-account', 'cake-variant', 'briefcase', 'school', 'account',
        'heart-pulse', 'airplane', 'dumbbell', 'food', 'music', 'phone', 'video', 'cart', 'home', 'star', 'dots-horizontal',
    ];

    public function index()
    {
        $types = EventType::withCount('events')->orderBy('name')->get();

        return view('admin.event-types.index', compact('types'));
    }

    public function create()
    {
        return view('admin.event-types.form', ['type' => new EventType(['color' => '#3B82F6', 'icon' => 'calendar', 'is_active' => true]), 'icons' => self::ICONS]);
    }

    public function store(Request $request)
    {
        EventType::create($this->validated($request));

        return redirect()->route('admin.event-types.index')->with('success', 'Tipo de evento creado.');
    }

    public function edit(EventType $eventType)
    {
        return view('admin.event-types.form', ['type' => $eventType, 'icons' => self::ICONS]);
    }

    public function update(Request $request, EventType $eventType)
    {
        $eventType->update($this->validated($request, $eventType));

        return redirect()->route('admin.event-types.index')->with('success', 'Tipo de evento actualizado.');
    }

    public function destroy(EventType $eventType)
    {
        if ($eventType->events()->withTrashed()->exists()) {
            $eventType->update(['is_active' => false]);

            return back()->with('success', 'El tipo tiene eventos asociados: se desactivó en lugar de eliminarse.');
        }

        $eventType->delete();

        return back()->with('success', 'Tipo de evento eliminado.');
    }

    private function validated(Request $request, ?EventType $type = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('event_types')->ignore($type?->id)],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['required', Rule::in(self::ICONS)],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
