<?php

use App\Http\Controllers\Auth\AzureAuthController;
use App\Livewire\Portal\Chatbot;
use App\Livewire\Portal\CreateTicket;
use App\Livewire\Portal\MyTickets;
use App\Livewire\Portal\ViewTicket;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// Si el usuario ya está autenticado (ej. al volver de un login o al
// impersonar), redirige directamente al panel que corresponda por rol
// en vez de mostrar la landing pública.
Route::get('/', function () {
    if (auth()->check()) {
        /** @var User $user */
        $user = auth()->user();

        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return redirect('/admin');
        }

        if ($user->hasAnyRole(['supervisor_soporte', 'agente_soporte', 'tecnico_campo', 'editor_kb'])) {
            return redirect('/soporte');
        }

        return redirect('/portal/tickets');
    }

    return view('welcome');
})->name('home');

// ── Azure AD SSO ─────────────────────────────────────────────
Route::get('auth/azure', [AzureAuthController::class, 'redirect'])->name('auth.azure');
Route::get('auth/azure/callback', [AzureAuthController::class, 'callback'])->name('auth.azure.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    // /dashboard es el destino por defecto de Fortify tras login; aquí
    // lo redirigimos al panel que corresponde al rol del usuario para
    // que nadie aterrice en una pantalla genérica.
    Route::get('dashboard', function () {
        /** @var User $user */
        $user = auth()->user();

        if ($user?->hasAnyRole(['super_admin', 'admin'])) {
            return redirect('/admin');
        }

        if ($user?->hasAnyRole(['supervisor_soporte', 'agente_soporte', 'tecnico_campo', 'editor_kb'])) {
            return redirect('/soporte');
        }

        return redirect('/portal/tickets');
    })->name('dashboard');

    // ── Portal de usuario ────────────────────────────────────────
    Route::prefix('portal')->name('portal.')->group(function () {
        Route::redirect('/', '/portal/tickets')->name('home');
        Route::get('tickets', MyTickets::class)->name('tickets.index');
        Route::get('tickets/create', CreateTicket::class)->name('tickets.create');
        Route::get('tickets/{ticket}', ViewTicket::class)->name('tickets.show');
        Route::get('chatbot', Chatbot::class)->name('chatbot');
    });
});

require __DIR__.'/settings.php';
