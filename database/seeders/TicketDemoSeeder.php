<?php

namespace Database\Seeders;

use App\Enums\TicketImpact;
use App\Enums\TicketUrgency;
use App\Models\Category;
use App\Models\Department;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TicketDemoSeeder extends Seeder
{
    /**
     * Creates a handful of demo users (one per role relevant to the soporte
     * panel) and seeds a few tickets in different states so the admin and
     * soporte panels show realistic data out of the box.
     */
    public function run(): void
    {
        $service = app(TicketService::class);

        // --- Demo accounts -------------------------------------------------
        $agent = $this->createDemoUser(
            email: 'agente@confipetrol.local',
            name: 'Agente Soporte',
            role: 'agente_soporte',
            departmentSlug: 'ti',
        );

        $supervisor = $this->createDemoUser(
            email: 'supervisor@confipetrol.local',
            name: 'Supervisor Soporte',
            role: 'supervisor_soporte',
            departmentSlug: 'ti',
        );

        $requester = $this->createDemoUser(
            email: 'usuario@confipetrol.local',
            name: 'Usuario Final',
            role: 'usuario_final',
            departmentSlug: 'operaciones',
        );

        // Agente + supervisor de RRHH (para probar scope por depto)
        $this->createDemoUser(
            email: 'agente.rrhh@confipetrol.local',
            name: 'Agente RRHH',
            role: 'agente_soporte',
            departmentSlug: 'rrhh',
        );

        $this->createDemoUser(
            email: 'supervisor.rrhh@confipetrol.local',
            name: 'Supervisor RRHH',
            role: 'supervisor_soporte',
            departmentSlug: 'rrhh',
        );

        // --- Sample tickets -----------------------------------------------
        $catHardware = Category::where('slug', 'ti-hardware')->first();
        $catCorreo = Category::where('slug', 'ti-correo-y-teams')->first();
        $catNomina = Category::where('slug', 'rrhh-nomina')->first();

        $service->create($requester, [
            'subject' => 'Pantalla no enciende',
            'description' => 'Al llegar a la oficina el monitor principal no da señal. El equipo sí prende.',
            'impact' => TicketImpact::Medio,
            'urgency' => TicketUrgency::Media,
            'category_id' => $catHardware?->id,
            'department_id' => $catHardware?->department_id,
        ]);

        $t2 = $service->create($requester, [
            'subject' => 'Correo no recibe mensajes externos',
            'description' => 'Desde esta mañana no llegan correos desde dominios externos. Los internos sí.',
            'impact' => TicketImpact::Alto,
            'urgency' => TicketUrgency::Alta,
            'category_id' => $catCorreo?->id,
            'department_id' => $catCorreo?->department_id,
        ]);
        $service->assign($t2, $agent);
        $service->markFirstResponse($t2);
        TicketComment::create([
            'ticket_id' => $t2->id,
            'user_id' => $agent->id,
            'body' => 'Estoy revisando los logs del servidor de correo. Te aviso en breve.',
            'is_private' => false,
        ]);
        TicketComment::create([
            'ticket_id' => $t2->id,
            'user_id' => $supervisor->id,
            'body' => 'Nota interna: verificar si coincide con el mantenimiento de Exchange programado.',
            'is_private' => true,
        ]);

        $t3 = $service->create($requester, [
            'subject' => 'Reporte de horas de nómina no genera',
            'description' => 'El reporte mensual no se genera al dar clic. Muestra error 500.',
            'impact' => TicketImpact::Medio,
            'urgency' => TicketUrgency::Baja,
            'category_id' => $catNomina?->id,
            'department_id' => $catNomina?->department_id,
        ]);
        $service->assign($t3, $supervisor);
        $service->markFirstResponse($t3);
        $service->resolve($t3);
    }

    protected function createDemoUser(string $email, string $name, string $role, string $departmentSlug): User
    {
        $department = Department::where('slug', $departmentSlug)->first();

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'department_id' => $department?->id,
            ],
        );

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        return $user;
    }
}
