<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AzureAuthController extends Controller
{
    /**
     * Redirect to Microsoft Azure AD login page.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('microsoft')->stateless()->redirect();
    }

    /**
     * Handle the callback from Azure AD after authentication.
     */
    public function callback(): RedirectResponse
    {
        $azureUser = Socialite::driver('microsoft')->stateless()->user();

        $email = $azureUser->getEmail();

        // CRÍTICO: validar que el tenant esté explícitamente configurado
        // (NO 'common') y que el correo pertenezca al dominio corporativo.
        // Sin esto, cualquier cuenta personal de Microsoft podría entrar.
        $tenantId = config('services.azure.tenant_id');

        if (blank($tenantId) || $tenantId === 'common') {
            abort(403, 'Azure AD SSO no está configurado para un tenant específico.');
        }

        $allowedDomains = collect(
            explode(',', (string) config('services.azure.allowed_domains', 'confipetrol.com'))
        )->map(fn ($d) => trim(mb_strtolower($d)))->filter()->all();

        $emailDomain = mb_strtolower((string) Str::after((string) $email, '@'));

        if (! $email || ! in_array($emailDomain, $allowedDomains, true)) {
            abort(403, 'Tu cuenta de Microsoft no pertenece a un dominio corporativo autorizado.');
        }

        // Buscar primero por azure_id, luego por email (para vincular cuentas existentes)
        $user = User::where('azure_id', $azureUser->getId())
            ->orWhere('email', $email)
            ->first();

        if ($user) {
            $user->update([
                'azure_id' => $azureUser->getId(),
                'name' => $azureUser->getName(),
                'avatar_url' => $this->resolveAvatar($azureUser->getAvatar()),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
        } else {
            $user = User::create([
                'azure_id' => $azureUser->getId(),
                'name' => $azureUser->getName(),
                'email' => $email,
                'avatar_url' => $this->resolveAvatar($azureUser->getAvatar()),
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
            ]);
        }

        // Sync department from Azure profile (if available in token)
        $this->syncDepartment($user, $azureUser);

        // Sync role from Azure AD groups
        $this->syncRole($user, $azureUser);

        // Track login
        $user->updateQuietly([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);

        Auth::login($user, remember: true);

        return redirect()->intended($this->resolveRedirectUrl($user));
    }

    /**
     * Azure devuelve el avatar como data:image/jpeg;base64,... (demasiado largo para la columna).
     * Solo guardamos si es una URL real; si es base64 lo descartamos.
     */
    protected function resolveAvatar(?string $avatar): ?string
    {
        if (blank($avatar) || str_starts_with($avatar, 'data:')) {
            return null;
        }

        return mb_substr($avatar, 0, 500);
    }

    /**
     * Sync user department from Azure AD profile claims.
     */
    protected function syncDepartment(User $user, mixed $azureUser): void
    {
        $deptName = $azureUser->user['department'] ?? null;

        if (blank($deptName)) {
            return;
        }

        $department = Department::where('name', $deptName)
            ->orWhere('slug', Str::slug($deptName))
            ->first();

        if ($department) {
            $user->updateQuietly(['department_id' => $department->id]);
        }
    }

    /**
     * Sync Spatie role from Azure AD group membership.
     * Uses config/azure-roles.php mapping.
     */
    protected function syncRole(User $user, mixed $azureUser): void
    {
        $groups = $azureUser->user['groups'] ?? [];
        $mapping = config('azure-roles', []);
        $defaultRole = $mapping['_default'] ?? 'usuario_final';

        $assignedRole = $defaultRole;

        foreach ($mapping as $groupId => $roleName) {
            if ($groupId === '_default') {
                continue;
            }

            if (in_array($groupId, $groups, true)) {
                $assignedRole = $roleName;

                break; // First match wins (ordered by priority in config)
            }
        }

        if (! $user->hasRole($assignedRole)) {
            $user->syncRoles([$assignedRole]);
        }
    }

    /**
     * Determine where to redirect based on the user's role.
     */
    protected function resolveRedirectUrl(User $user): string
    {
        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return '/admin';
        }

        if ($user->hasAnyRole(['supervisor_soporte', 'agente_soporte', 'tecnico_campo', 'editor_kb'])) {
            return '/soporte';
        }

        return '/portal/tickets';
    }
}
