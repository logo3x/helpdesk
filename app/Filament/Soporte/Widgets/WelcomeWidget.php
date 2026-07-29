<?php

namespace App\Filament\Soporte\Widgets;

use App\Models\Ticket;
use Filament\Widgets\Widget;

class WelcomeWidget extends Widget
{
    protected static string $view = 'filament.soporte.widgets.welcome-widget';

    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        $user = auth()->user();
        $hour = now()->hour;

        $greeting = match (true) {
            $hour < 12 => 'Buenos días',
            $hour < 18 => 'Buenas tardes',
            default => 'Buenas noches',
        };

        $firstName = explode(' ', (string) $user?->name)[0] ?? '';

        $myOpen = Ticket::query()
            ->where('assigned_to', $user?->id)
            ->whereNotIn('status', ['resuelto', 'cerrado'])
            ->whereNull('deleted_at')
            ->count();

        $roles = $user?->getRoleNames()->implode(', ') ?? '';
        $roleLabel = match (true) {
            str_contains($roles, 'super_admin') => 'Super Administrador',
            str_contains($roles, 'admin') => 'Administrador',
            str_contains($roles, 'supervisor') => 'Supervisor de Soporte',
            str_contains($roles, 'agente') => 'Agente de Soporte',
            default => 'Usuario',
        };

        return [
            'greeting' => $greeting,
            'firstName' => $firstName,
            'fullName' => $user?->name,
            'roleLabel' => $roleLabel,
            'myOpen' => $myOpen,
            'avatarUrl' => null,
        ];
    }
}
