<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    use AuthenticatesUsers; // incluye limite de intentos (5 por minuto)

    protected $redirectTo = '/admin';

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    /** Solo los administradores activos pueden entrar al panel web. */
    protected function authenticated(Request $request, $user)
    {
        if (! $user->isAdmin() || ! $user->is_active) {
            $this->guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => $user->is_active
                    ? 'El panel es solo para administradores. Usa la app móvil EasyLife.'
                    : 'Tu cuenta está desactivada.',
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended($this->redirectPath());
    }
}
