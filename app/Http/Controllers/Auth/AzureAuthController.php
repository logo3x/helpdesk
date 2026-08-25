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
     * Cierra la sesión local y redirige al logout de Microsoft para
     * limpiar también la sesión SSO. Sin esto, Microsoft recuerda la
     * cuenta y auto-loguea al volver a /auth/azure.
     */
    public function logout(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        $tenantId = config('services.azure.tenant_id');
        $postLogoutUri = urlencode(url('/login'));

        return redirect("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/logout?post_logout_redirect_uri={$postLogoutUri}");
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

        // Matching en 3 pasadas — la primera que encuentre gana.
        //
        //  1. azure_id (oid de Azure, id inmutable) — usuario ya conocido.
        //  2. email exacto — típico de stub precargado por RRHH.
        //  3. identification (cédula) — para el caso en que el email
        //     precargado no coincide con el corporativo real de Azure.
        //     Es el claim `employeeId` o similar que el tenant expone
        //     en el token; si no viene, este paso se salta.
        $azureIdentification = $this->extractIdentification($azureUser);

        $user = User::where('azure_id', $azureUser->getId())->first();

        if (! $user) {
            $user = User::where('email', $email)->first();
        }

        if (! $user && $azureIdentification) {
            $user = User::where('identification', $azureIdentification)
                ->where(function ($q) {
                    // Solo aceptamos matchear por cédula un stub Azure
                    // pendiente. Si un usuario ya activo tiene esa cédula,
                    // NO lo pisamos — sería confuso y potencialmente inseguro.
                    $q->where('is_azure_pending', true)
                        ->orWhereNull('azure_id');
                })
                ->first();
        }

        $wasAzurePending = (bool) ($user?->is_azure_pending ?? false);

        if ($user) {
            $updates = [
                'azure_id' => $azureUser->getId(),
                'name' => $user->name ?: $azureUser->getName(),
                'avatar_url' => $this->resolveAvatar($azureUser->getAvatar()),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ];

            // El email siempre lo alineamos al que viene de Azure — es
            // la fuente de verdad para el login. Si el stub se precargó
            // con un email distinto (ej: escrito a mano por RRHH y con
            // typo), lo corregimos en el primer login exitoso.
            if ($user->email !== $email) {
                $updates['email'] = $email;
            }

            // Si el stub no tenía cédula pero Azure la trae, la seteamos
            // — sin pisar la que ya venía de la precarga.
            if (blank($user->identification) && ! blank($azureIdentification)) {
                $updates['identification'] = $azureIdentification;
            }

            // Si estaba como Azure pending, este es su primer login
            // exitoso. Lo activamos y guardamos el timestamp.
            if ($wasAzurePending) {
                $updates['is_azure_pending'] = false;
                $updates['azure_first_login_at'] = now();
            }

            $user->update($updates);
        } else {
            $user = User::create(array_filter([
                'azure_id' => $azureUser->getId(),
                'name' => $azureUser->getName(),
                'email' => $email,
                'identification' => $azureIdentification,
                'avatar_url' => $this->resolveAvatar($azureUser->getAvatar()),
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
                'is_azure_pending' => false,
                'azure_first_login_at' => now(),
            ], fn ($v) => $v !== null));
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
     * Intenta extraer la cédula del usuario desde los claims del token
     * de Azure. Confipetrol expone la cédula típicamente en:
     *   - user['employeeId']            (estándar Entra ID)
     *   - user['extension_*_cedula']    (extensión custom del tenant)
     *   - user['onPremisesImmutableId'] (fallback si viene sincronizada)
     *
     * Cualquier valor se normaliza a solo dígitos para poder matchear
     * con users.identification (que también guardamos como dígitos).
     * Devuelve null si el token no la expone — es lo esperable si
     * todavía no se configuró el claim en el tenant.
     */
    protected function extractIdentification(mixed $azureUser): ?string
    {
        $userClaims = $azureUser->user ?? [];

        $candidates = [
            $userClaims['employeeId'] ?? null,
            $userClaims['onPremisesImmutableId'] ?? null,
        ];

        // Extensiones dinámicas del tenant — ej: `extension_xxx_cedula`.
        foreach ($userClaims as $key => $value) {
            if (is_string($key) && str_contains(mb_strtolower($key), 'cedula')) {
                $candidates[] = $value;
            }
        }

        foreach ($candidates as $candidate) {
            if (blank($candidate)) {
                continue;
            }

            $onlyDigits = preg_replace('/\D/', '', (string) $candidate);
            if ($onlyDigits !== '' && mb_strlen($onlyDigits) >= 6) {
                return $onlyDigits;
            }
        }

        return null;
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
     *
     * REGLA (decidida 2026-08-24 tras bug de agentes perdiendo su rol):
     * El SSO NO administra roles de usuarios existentes. Los roles se
     * asignan/modifican desde el panel admin y son la fuente de verdad.
     * Azure solo autentica.
     *
     * Solo asignamos un rol default cuando el usuario NO tiene NINGÚN
     * rol asignado — típicamente porque acaba de ser creado en este
     * mismo callback (primera vez que se autentica alguien nuevo).
     *
     * Bug previo: syncRoles([...]) borraba los roles existentes en
     * cada login. Agentes que no estaban en el grupo Azure mapeado
     * quedaban degradados a usuario_final.
     *
     * Uses config/azure-roles.php mapping only for NEW users.
     */
    protected function syncRole(User $user, mixed $azureUser): void
    {
        // Si ya tiene algún rol, respetamos lo que hay en el panel.
        if ($user->roles()->exists()) {
            return;
        }

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

        $user->assignRole($assignedRole);
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

        return '/portal/chatbot';
    }
}
