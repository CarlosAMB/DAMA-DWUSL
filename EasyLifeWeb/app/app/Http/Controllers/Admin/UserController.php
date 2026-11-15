<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /** Listado con busqueda y filtros. */
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q2) => $q2->whereLike('name', "%$s%")->orWhereLike('email', "%$s%")))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'activo'))
            ->withCount(['tasks', 'events'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        User::create($data + ['is_active' => true, 'preferences' => User::DEFAULT_PREFERENCES]);

        return redirect()->route('admin.users.index')->with('success', 'Usuario creado correctamente.');
    }

    /** Detalle: solo metricas agregadas (no el contenido privado del usuario). */
    public function show(User $user)
    {
        $metrics = [
            'events' => $user->events()->count(),
            'tasks' => $user->tasks()->count(),
            'completed' => $user->tasks()->where('status', 'completada')->count(),
            'pending' => $user->tasks()->where('status', '!=', 'completada')->count(),
            'overdue' => $user->tasks()->where('status', '!=', 'completada')->where('due_date', '<', now())->count(),
            'projects' => $user->projects()->count(),
            'tags' => $user->tags()->count(),
            'devices' => $user->tokens()->count(),
        ];

        return view('admin.users.show', compact('user', 'metrics'));
    }

    public function edit(User $user)
    {
        return view('admin.users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'password' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if ($user->id === $request->user()->id && $data['role'] !== 'admin') {
            return back()->withErrors(['role' => 'No puedes quitarte el rol de administrador a ti mismo.'])->withInput();
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('success', 'Usuario actualizado.');
    }

    /** Activa / desactiva la cuenta. Al desactivar se cierran sus sesiones en la app. */
    public function toggleActive(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        $user->update(['is_active' => ! $user->is_active]);
        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return back()->with('success', $user->is_active ? 'Usuario activado.' : 'Usuario desactivado y sesiones cerradas.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        $user->tokens()->delete();
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Usuario y todos sus datos eliminados.');
    }
}
