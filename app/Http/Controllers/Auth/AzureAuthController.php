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
        return Socialite::driver('microsoft')
            ->scopes(['User.Read', 'GroupMember.Read.All'])
            ->redirect();
    }

    /**
     * Handle the callback from Azure AD after authentication.
     */
    public function callback(): RedirectResponse
    {
        $azureUser = Socialite::driver('microsoft')->user();

        $user = User::updateOrCreate(
            ['azure_id' => $azureUser->getId()],
            [
                'name' => $azureUser->getName(),
                'email' => $azureUser->getEmail(),
                'avatar_url' => $azureUser->getAvatar(),
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
            ],
        );

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
