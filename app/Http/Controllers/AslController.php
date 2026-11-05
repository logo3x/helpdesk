<?php

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Models\SlaConfig;
use App\Services\SlaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AslController extends Controller
{
    public function show(): View|RedirectResponse
    {
        $user = Auth::user();

        if ($user && $user->asl_accepted_at !== null) {
            return redirect()->intended('/');
        }

        return view('asl.accept', [
            'slaTimes' => $this->slaTimesForAgreement(),
        ]);
    }

    /**
     * Plazos vigentes del SLA por prioridad, leídos de sla_configs para que
     * el acuerdo no quede desactualizado al cambiar la configuración. Si los
     * departamentos difieren, se toma el plazo más común de cada prioridad.
     *
     * @return array<string, array{label: string, first_response: string, resolution: string}>
     */
    protected function slaTimesForAgreement(): array
    {
        $configsByPriority = SlaConfig::query()
            ->where('is_active', true)
            ->get(['priority', 'first_response_minutes', 'resolution_minutes'])
            ->groupBy(fn (SlaConfig $config): string => $config->priority->value);

        $times = [];

        foreach ([TicketPriority::Critica, TicketPriority::Alta, TicketPriority::Media, TicketPriority::Baja, TicketPriority::Planificada] as $priority) {
            $configs = $configsByPriority->get($priority->value);

            if ($configs === null || $configs->isEmpty()) {
                continue;
            }

            $mostCommon = $configs
                ->countBy(fn (SlaConfig $config): string => $config->first_response_minutes.'|'.$config->resolution_minutes)
                ->sortDesc()
                ->keys()
                ->first();

            [$firstResponse, $resolution] = array_map('intval', explode('|', $mostCommon));

            $times[$priority->value] = [
                'label' => $priority->getLabel(),
                'first_response' => SlaService::formatMinutes($firstResponse),
                'resolution' => SlaService::formatMinutes($resolution),
            ];
        }

        return $times;
    }

    public function accept(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        $isNew = $user->asl_accepted_at === null;

        if ($isNew) {
            $user->forceFill(['asl_accepted_at' => now()])->save();
        }

        // Si es la primera vez que acepta, lleva al perfil para completar datos laborales.
        // Si ya había aceptado antes (acceso directo), respeta intended().
        if ($isNew) {
            return redirect()->route('profile.edit')
                ->with('asl_just_accepted', true);
        }

        return redirect()->intended('/');
    }
}
