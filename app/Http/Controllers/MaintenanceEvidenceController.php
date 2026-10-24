<?php

namespace App\Http\Controllers;

use App\Models\ScheduledMaintenance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Descarga autenticada de evidencia de mantenimientos.
 *
 * Las evidencias viven en el disco "local" (storage/app/) que NO es
 * público. Esta ruta les da acceso previa autorización:
 *   - super_admin / admin → cualquier evidencia.
 *   - supervisor_soporte → solo evidencias de activos de su depto.
 *   - agente / técnico → solo las que subió él mismo (agent_id).
 *   - usuario final → solo si es el custodio del activo relacionado.
 */
class MaintenanceEvidenceController extends Controller
{
    public function download(Request $request, Media $media)
    {
        if ($media->collection_name !== 'evidence' || $media->model_type !== ScheduledMaintenance::class) {
            throw new NotFoundHttpException;
        }

        /** @var ScheduledMaintenance|null $maintenance */
        $maintenance = $media->model;

        if (! $maintenance) {
            throw new NotFoundHttpException;
        }

        if (! $this->userCanAccess($request->user(), $maintenance)) {
            abort(403, 'No tienes permiso para descargar esta evidencia.');
        }

        $disk = Storage::disk($media->disk);
        $relativePath = $media->id.'/'.$media->file_name;

        if (! $disk->exists($relativePath)) {
            throw new NotFoundHttpException;
        }

        return $disk->download($relativePath, $media->file_name);
    }

    protected function userCanAccess($user, ScheduledMaintenance $maintenance): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        $maintenance->loadMissing('asset:id,user_id,department_id');

        if ($user->hasRole('supervisor_soporte')) {
            return $maintenance->asset?->department_id === $user->department_id;
        }

        if ($user->hasAnyRole(['agente_soporte', 'tecnico_campo'])) {
            return (int) $maintenance->agent_id === (int) $user->id;
        }

        // Usuario final: solo si es el custodio del activo.
        return $maintenance->asset?->user_id === $user->id;
    }
}
