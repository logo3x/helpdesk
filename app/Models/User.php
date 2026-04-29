<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'azure_id', 'avatar_url', 'department_id', 'last_login_at', 'last_login_ip', 'identification', 'position', 'phone'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->hasAnyRole(['super_admin', 'admin']),
            'soporte' => $this->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte', 'agente_soporte', 'tecnico_campo', 'editor_kb']),
            default => false,
        };
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return HasMany<Ticket, $this> */
    public function requestedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    /** @return HasMany<Ticket, $this> */
    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Nombre mostrado en el menú de usuario de Filament.
     * Debe retornar SOLO el nombre porque ui-avatars usa este string
     * para generar las iniciales del círculo. Si aquí metiéramos
     * "(rol)" las iniciales salen feas ("S(" en lugar de "SS").
     *
     * El rol se muestra separado vía Panel::userMenuItems().
     */
    public function getFilamentName(): string
    {
        return $this->name;
    }

    /**
     * Define quién puede ACTIVAR la impersonación (gate global).
     * Los checks por-target se hacen en el visible() del botón.
     */
    public function canImpersonate(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']);
    }

    /**
     * Define si ESTE usuario puede ser impersonado.
     * Nadie puede impersonar a un super_admin/admin (principio de
     * menor privilegio).
     */
    public function canBeImpersonated(): bool
    {
        return ! $this->hasAnyRole(['super_admin', 'admin']);
    }

    /**
     * Reglas por-target para el botón Impersonate en el panel Soporte:
     * un supervisor_soporte solo puede impersonar a agentes de su
     * propio departamento (no a otros supervisores, ni a agentes de
     * otros deptos, ni a usuarios finales sin relación).
     */
    public function canImpersonateTarget(User $target): bool
    {
        if (! $this->canImpersonate()) {
            return false;
        }

        if (! $target->canBeImpersonated()) {
            return false;
        }

        // Admin y super_admin pueden impersonar a cualquiera (que pase
        // el gate del target).
        if ($this->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        // Supervisor: solo agentes de su mismo depto.
        if ($this->hasRole('supervisor_soporte')) {
            return $target->hasRole('agente_soporte')
                && $target->department_id !== null
                && $target->department_id === $this->department_id;
        }

        return false;
    }

    /**
     * Etiqueta legible del rol actual del usuario para mostrar en la UI.
     * Los nombres Spatie están en snake_case; aquí se normalizan.
     */
    public function roleLabel(): ?string
    {
        $role = $this->roles->first()?->name;

        if (! $role) {
            return null;
        }

        return match ($role) {
            'super_admin' => 'Super Admin',
            'admin' => 'Administrador',
            'supervisor_soporte' => 'Supervisor',
            'agente_soporte' => 'Agente',
            'tecnico_campo' => 'Técnico',
            'editor_kb' => 'Editor KB',
            'usuario_final' => 'Usuario',
            default => Str::of($role)->replace('_', ' ')->title()->toString(),
        };
    }
}
