<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Firebase\FcmService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Avisos que se muestran en la pantalla de inicio de la app movil. */
class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::with('author')->latest()->paginate(10);

        return view('admin.announcements.index', compact('announcements'));
    }

    public function create()
    {
        return view('admin.announcements.form', ['announcement' => new Announcement(['level' => 'info', 'is_active' => true])]);
    }

    public function store(Request $request, FcmService $fcm)
    {
        $announcement = Announcement::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('admin.announcements.index')->with('success', 'Aviso publicado.'.$this->push($request, $fcm, $announcement));
    }

    public function edit(Announcement $announcement)
    {
        return view('admin.announcements.form', compact('announcement'));
    }

    public function update(Request $request, Announcement $announcement, FcmService $fcm)
    {
        $announcement->update($this->validated($request));

        return redirect()->route('admin.announcements.index')->with('success', 'Aviso actualizado.'.$this->push($request, $fcm, $announcement));
    }

    /** Si se marco "Enviar notificacion push" y el aviso esta visible, se envia a todos los telefonos. */
    private function push(Request $request, FcmService $fcm, Announcement $announcement): string
    {
        if (! $request->boolean('send_push')) {
            return '';
        }
        if (! $fcm->enabled()) {
            return ' No se envió la notificación push: Firebase no está configurado en el servidor.';
        }
        if (! Announcement::visible()->whereKey($announcement->id)->exists()) {
            return ' No se envió la notificación push porque el aviso no está visible ahora.';
        }

        $r = $fcm->sendToAll($announcement->title, $announcement->body, ['type' => 'announcement', 'id' => $announcement->id]);

        return " Notificación push enviada a {$r['sent']} dispositivo(s)".($r['failed'] ? " ({$r['failed']} fallaron)." : '.');
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();

        return back()->with('success', 'Aviso eliminado.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
            'level' => ['required', Rule::in(Announcement::LEVELS)],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:published_at'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['published_at'] = $data['published_at'] ?? now();

        return $data;
    }
}
