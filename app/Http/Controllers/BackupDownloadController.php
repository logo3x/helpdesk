<?php

namespace App\Http\Controllers;

use App\Services\BackupManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga de un zip de respaldo (link del correo o botón de la página).
 *
 * Además de la firma temporal (middleware "signed"), exige sesión con
 * rol admin o super_admin: un correo reenviado no basta para bajar el
 * respaldo. El nombre se valida contra los archivos reales del disco de
 * respaldos, así que no hay path traversal posible.
 */
class BackupDownloadController extends Controller
{
    public function __invoke(Request $request, BackupManager $backups, string $folder, string $file): StreamedResponse
    {
        abort_unless(
            $request->user()?->hasAnyRole(BackupManager::ADMIN_ROLES),
            403,
            'Solo los administradores pueden descargar respaldos.'
        );

        $path = $backups->resolvePath($folder, $file);

        abort_if($path === null, 404, 'El respaldo no existe o ya fue eliminado por la política de retención.');

        activity(BackupManager::LOG_NAME)
            ->causedBy($request->user())
            ->event('downloaded')
            ->withProperties(['path' => $path, 'ip' => $request->ip()])
            ->log('Respaldo descargado: '.$path);

        set_time_limit(0);

        return $backups->disk()->download($path, $file, [
            'Content-Type' => 'application/zip',
        ]);
    }
}
