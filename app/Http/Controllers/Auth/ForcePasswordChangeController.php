<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Flujo de cambio de password OBLIGATORIO en el primer login de las
 * cuentas locales precargadas (Sprint 4). El usuario llega aquí por
 * el middleware `force.password.change` cuando `password_must_change=1`.
 */
class ForcePasswordChangeController extends Controller
{
    /**
     * Muestra el formulario de cambio.
     */
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        // Si no tiene que cambiar, no debería estar aquí.
        if (! $user || ! $user->password_must_change) {
            return redirect('/');
        }

        return view('pages::auth.first-password-change');
    }

    /**
     * Procesa el cambio.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->password_must_change, 403);

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.current_password' => 'La contraseña actual no es correcta.',
        ]);

        // Que no repita la misma que trae por default (primeros 8 de cédula).
        if (Hash::check((string) $request->input('password'), $user->password)) {
            return back()->withErrors([
                'password' => 'La nueva contraseña debe ser distinta a la actual.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make((string) $request->input('password')),
            'password_must_change' => false,
        ])->save();

        // Post-cambio: al panel que corresponde por rol. Reutilizamos
        // la misma lógica de redirect que AzureAuthController.
        $target = match (true) {
            $user->hasAnyRole(['super_admin', 'admin']) => '/admin',
            $user->hasAnyRole(['supervisor_soporte', 'agente_soporte', 'tecnico_campo', 'editor_kb']) => '/soporte',
            default => '/portal/chatbot',
        };

        return redirect($target)->with('status', 'Contraseña actualizada. Recordá usarla en tus próximos inicios de sesión.');
    }
}
