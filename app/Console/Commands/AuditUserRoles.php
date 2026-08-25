<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Audita qué usuarios probablemente perdieron su rol de agente/técnico/
 * supervisor por el bug del SSO que hacía syncRoles([]) borrando roles
 * existentes en cada login (fixed 2026-08-24).
 *
 * Heurística: si un usuario hoy es `usuario_final` pero tiene actividad
 * histórica típica de agente (tickets asignados, comentarios en tickets,
 * KB creada, mantenimientos como agente, etc.), es candidato para
 * reasignarle su rol real.
 *
 * NO reasigna automáticamente — solo lista para que el admin lo haga
 * desde el panel. La lista se puede exportar con --json.
 */
class AuditUserRoles extends Command
{
    protected $signature = 'users:audit-roles
                            {--json : Emitir salida JSON}
                            {--role=agente_soporte : Rol sospechoso a proponer (agente_soporte|supervisor_soporte|tecnico_campo)}';

    protected $description = 'Detecta usuarios que probablemente perdieron su rol operativo por el bug del SSO.';

    public function handle(): int
    {
        $proposedRole = (string) $this->option('role');

        // Candidatos: usuario_final PURO (sin ningún otro rol) con
        // actividad de agente. Excluye a los que ya son agente actualmente.
        $candidates = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'usuario_final'))
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', [
                'super_admin', 'admin', 'supervisor_soporte',
                'agente_soporte', 'tecnico_campo', 'editor_kb',
            ]))
            ->with('roles:id,name')
            ->get()
            ->map(function (User $u) {
                // Tickets asignados — solo un agente/técnico/supervisor
                // puede recibir esta asignación. Señal fuertísima.
                $assignedTickets = Ticket::query()->where('assigned_to_id', $u->id)->count();

                // Comentarios privados — is_private = notas internas
                // solo visibles por staff. Un usuario final nunca
                // podría haberlas escrito.
                $privateComments = DB::table('ticket_comments')
                    ->where('user_id', $u->id)
                    ->where('is_private', true)
                    ->count();

                // Comentarios totales al ticket (privados o públicos).
                // Menos fuerte que privados, pero indicativo.
                $allComments = DB::table('ticket_comments')
                    ->where('user_id', $u->id)
                    ->count();

                // Autor de artículos de KB — solo staff con rol editor
                // o superior escribe KB.
                $kbCount = DB::table('kb_articles')
                    ->where('author_id', $u->id)
                    ->count();

                // Programó o le asignaron mantenimientos.
                $maintenanceCount = DB::table('scheduled_maintenances')
                    ->where(function ($q) use ($u) {
                        $q->where('agent_id', $u->id)
                            ->orWhere('created_by_id', $u->id);
                    })
                    ->count();

                return [
                    'user' => $u,
                    'assigned_tickets' => $assignedTickets,
                    'private_comments' => $privateComments,
                    'all_comments' => $allComments,
                    'kb_articles' => $kbCount,
                    'maintenances' => $maintenanceCount,
                    'signal' => $assignedTickets + $privateComments + $kbCount + $maintenanceCount,
                ];
            })
            ->filter(fn (array $c) => $c['signal'] > 0)
            ->sortByDesc('signal')
            ->values();

        if ($this->option('json')) {
            $this->line($candidates->map(fn ($c) => [
                'id' => $c['user']->id,
                'name' => $c['user']->name,
                'email' => $c['user']->email,
                'identification' => $c['user']->identification,
                'department_id' => $c['user']->department_id,
                'signals' => [
                    'assigned_tickets' => $c['assigned_tickets'],
                    'private_comments' => $c['private_comments'],
                    'all_comments' => $c['all_comments'],
                    'kb_articles' => $c['kb_articles'],
                    'maintenances' => $c['maintenances'],
                ],
                'proposed_role' => $proposedRole,
            ])->toJson(JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        if ($candidates->isEmpty()) {
            $this->info('✓ No se detectaron usuarios sospechosos de haber perdido su rol.');

            return self::SUCCESS;
        }

        $this->warn(sprintf(
            '⚠ Se detectaron %d usuario(s) hoy como "usuario_final" pero con historial operativo.',
            $candidates->count(),
        ));
        $this->newLine();

        $this->table(
            ['ID', 'Nombre', 'Email', 'Tk asig.', 'Priv.coms', 'Todos coms', 'KB', 'Mttos', 'Total'],
            $candidates->map(fn (array $c) => [
                $c['user']->id,
                mb_strimwidth((string) $c['user']->name, 0, 28, '…'),
                mb_strimwidth((string) $c['user']->email, 0, 32, '…'),
                $c['assigned_tickets'],
                $c['private_comments'],
                $c['all_comments'],
                $c['kb_articles'],
                $c['maintenances'],
                $c['signal'],
            ])->all(),
        );

        $this->newLine();
        $this->line('Para reasignarles el rol correcto:');
        $this->line('  1. Entrá a /admin/users con cuenta super_admin.');
        $this->line('  2. Buscá cada uno de estos usuarios por email.');
        $this->line('  3. En Editar → Rol → seleccioná el rol correcto ('.$proposedRole.', supervisor_soporte, tecnico_campo, etc.).');
        $this->newLine();
        $this->comment('Nota: el bug ya está arreglado — el SSO ya NO borra roles al hacer login.');

        return self::SUCCESS;
    }
}
