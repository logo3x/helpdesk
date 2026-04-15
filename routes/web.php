<?php

use App\Http\Controllers\Auth\AzureAuthController;
use App\Livewire\Portal\Chatbot;
use App\Livewire\Portal\CreateTicket;
use App\Livewire\Portal\MyTickets;
use App\Livewire\Portal\ViewTicket;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// ── Azure AD SSO ─────────────────────────────────────────────
Route::get('auth/azure', [AzureAuthController::class, 'redirect'])->name('auth.azure');
Route::get('auth/azure/callback', [AzureAuthController::class, 'callback'])->name('auth.azure.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

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
