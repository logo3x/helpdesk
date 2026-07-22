<?php

use App\Http\Controllers\AslController;
use App\Http\Controllers\Assets\AssetLifecyclePdfController;
use App\Http\Controllers\Auth\AzureAuthController;
use App\Http\Controllers\InventoryAgentController;
use App\Http\Middleware\EnsureAslAccepted;
use App\Livewire\Portal\Chatbot;
use App\Livewire\Portal\CreateTicket;
use App\Livewire\Portal\Dashboard as PortalDashboard;
use App\Livewire\Portal\KbIndex;
use App\Livewire\Portal\KbShow;
use App\Livewire\Portal\MaintenanceSurveyResponse;
use App\Livewire\Portal\MyAssets;
use App\Livewire\Portal\MyTickets;
use App\Livewire\Portal\SurveyResponse;
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

        return redirect('/portal/chatbot');
    }

    return view('welcome');
})->name('home');

// ── Azure AD SSO ─────────────────────────────────────────────
Route::get('auth/azure', [AzureAuthController::class, 'redirect'])->name('auth.azure');
Route::get('auth/azure/callback', [AzureAuthController::class, 'callback'])->name('auth.azure.callback');
Route::get('auth/azure/logout', [AzureAuthController::class, 'logout'])->name('auth.azure.logout')->middleware('auth');

// ── Instalador del agente de inventario ───────────────────────
// Endpoint sin auth para que IT lo invoque desde cada PC con un
// one-liner. La seguridad la da el TOKEN Sanctum (que se pasa por
// query string al instalador y NO se loguea). El controller
// rehúsa tokens con formato inválido.
Route::get('agent/install', [InventoryAgentController::class, 'install'])->name('agent.install');
Route::get('agent/uninstall', [InventoryAgentController::class, 'uninstall'])->name('agent.uninstall');
Route::get('agent/script', [InventoryAgentController::class, 'script'])->name('agent.script');

// Aceptación del Acuerdo de Servicio (ASL). El middleware
// EnsureAslAccepted redirige aquí a los usuarios autenticados con
// asl_accepted_at = null. Estas rutas NO usan el middleware (para
// evitar loop) pero sí requieren sesión iniciada.
Route::middleware('auth')->group(function () {
    Route::get('asl/accept', [AslController::class, 'show'])->name('asl.show');
    Route::post('asl/accept', [AslController::class, 'accept'])->name('asl.accept');
});

// Hoja de vida PDF — ruta simple para evitar restricciones IIS en rutas Filament
Route::get('assets/{asset}/edit/pdf', AssetLifecyclePdfController::class)
    ->middleware(['auth', 'verified'])
    ->name('assets.lifecycle.pdf');

Route::middleware(['auth', 'verified', EnsureAslAccepted::class])->group(function () {
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

        return redirect('/portal/chatbot');
    })->name('dashboard');

    // ── Portal de usuario ────────────────────────────────────────
    Route::prefix('portal')->name('portal.')->group(function () {
        // Dashboard de bienvenida con saludo, stats personales,
        // accesos rápidos y últimos tickets. Es el landing por
        // defecto del rol usuario_final tras login.
        Route::get('/', PortalDashboard::class)->name('home');
        Route::get('tickets', MyTickets::class)->name('tickets.index');
        Route::get('tickets/create', CreateTicket::class)->name('tickets.create');
        Route::get('tickets/{ticket}', ViewTicket::class)->name('tickets.show');
        Route::get('chatbot', Chatbot::class)->name('chatbot');

        // Mis activos asignados (inventario en custodia del usuario).
        Route::get('assets', MyAssets::class)->name('assets.index');

        // Encuesta de satisfacción de tickets
        Route::get('survey/{token}', SurveyResponse::class)->name('survey');

        // Encuesta de mantenimiento de activos
        Route::get('maintenance-survey/{token}', MaintenanceSurveyResponse::class)->name('maintenance-survey');

        // Centro de ayuda (KB pública para usuarios finales). Solo
        // muestra artículos con status='published' (filtrado en el
        // componente vía scopePublished()).
        Route::get('kb', KbIndex::class)->name('kb.index');
        Route::get('kb/{slug}', KbShow::class)->name('kb.show');
    });
});

require __DIR__.'/settings.php';
