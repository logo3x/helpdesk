<?php

namespace App\Filament\Soporte\Resources\Assets\Pages;

use App\Filament\Resources\Assets\Pages\EditAsset as AdminEditAsset;
use App\Filament\Soporte\Resources\Assets\AssetResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;

/**
 * Edición del activo dentro del panel /soporte.
 *
 * Hereda la página de /admin (igual que AssetLifecycle) para que las
 * acciones de actas, la hoja de vida y sus textos sean los mismos en
 * los dos paneles. Solo cambian:
 *
 *   - el resource, para que las URLs apunten a /soporte;
 *   - eliminar queda para supervisor, admin y super_admin, y no se
 *     ofrecen forzar eliminación ni restaurar;
 *   - no hay navegación anterior/siguiente, porque la lista de soporte
 *     no guarda la lista filtrada en sesión.
 */
class EditAsset extends AdminEditAsset
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        $hidden = ['prevAsset', 'nextAsset', 'forceDelete', 'restore'];

        return collect(parent::getHeaderActions())
            ->reject(fn (Action $action): bool => in_array($action->getName(), $hidden, true))
            ->map(fn (Action $action): Action => $action instanceof DeleteAction
                ? $action->visible(fn (): bool => auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']) ?? false)
                : $action)
            ->values()
            ->all();
    }

    protected function getNavigatorPosition(): array
    {
        return [null, null, null, null];
    }
}
