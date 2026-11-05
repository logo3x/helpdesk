<?php

namespace App\Filament\Pages;

use App\Enums\BackupType;
use App\Jobs\RunBackupJob;
use App\Services\BackupManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

/**
 * Respaldos del Helpdesk (reemplaza la página del plugin
 * shuvroroy/filament-spatie-laravel-backup vía usingPage()).
 *
 * - Tarjetas de estado: último respaldo de BD y completo (fecha, tamaño,
 *   si está sano), próxima ejecución y espacio usado.
 * - Tabla de zips del disco de respaldos con tipo, descarga (link firmado
 *   de 30 minutos que también exige sesión de admin) y eliminación.
 * - "Respaldar BD ahora" / "Respaldo completo ahora": despachan
 *   RunBackupJob a la cola para no depender del timeout de IIS.
 *
 * Solo super_admin y admin.
 */
class Backups extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $navigationLabel = 'Respaldos';

    protected static ?string $title = 'Respaldos';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 100;

    protected static ?string $slug = 'respaldos';

    protected string $view = 'filament.pages.backups';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(BackupManager::ADMIN_ROLES) ?? false;
    }

    /**
     * Filament solo revisa canAccess() al montar la página. Se repite en
     * cada petición de Livewire para que un usuario al que le quitaron el
     * rol (o un snapshot reutilizado) no pueda seguir llamando a las
     * acciones de respaldar, descargar o eliminar.
     */
    public function boot(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getSubheading(): ?string
    {
        return 'Copias de la base de datos y de los archivos subidos, generadas en la madrugada y enviadas a los administradores.';
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $backups = app(BackupManager::class);

        $cards = collect(BackupType::cases())->map(function (BackupType $type) use ($backups): array {
            $latest = $backups->latest($type);
            $lastRun = $backups->lastRun($type);

            return [
                'type' => $type,
                'latest' => $latest,
                'healthy' => $backups->isHealthy($type),
                'lastFailed' => $lastRun?->event === 'failed',
                'lastError' => $lastRun?->event === 'failed' ? $lastRun->getProperty('error') : null,
                'lastFailedAt' => $lastRun?->event === 'failed' ? $lastRun->created_at : null,
                'nextRun' => $type->nextRunDate(),
                'schedule' => $type->scheduleDescription(),
                'count' => $backups->backups($type)->count(),
            ];
        });

        $schedule = config('backup.helpdesk.schedule');

        return [
            'cards' => $cards,
            'totalSize' => $backups->totalSize(),
            'freeSpace' => $backups->freeSpace(),
            'isRunning' => $backups->isRunning(),
            'dumpWarning' => $backups->dumpBinaryWarning(),
            'hasPassword' => filled(config('backup.backup.password')),
            'help' => [
                'database' => 'Se genera '.mb_strtolower(BackupType::Database->scheduleDescription()).'. Se guardan los de los últimos '
                    .config('backup.cleanup.default_strategy.keep_all_backups_for_days').' días.',
                'full' => 'Se genera '.mb_strtolower(BackupType::Full->scheduleDescription()).' con los archivos subidos y la BD. Se guarda uno por semana durante '
                    .(config('backup.full.cleanup.default_strategy.keep_weekly_backups_for_weeks') + 1).' semanas.',
                'email' => 'Tras el respaldo semanal, los administradores reciben el zip de BD adjunto (si pesa menos de '
                    .config('backup.helpdesk.attachment_max_mb').' MB) y un link de descarga del completo válido por '
                    .config('backup.helpdesk.download_link_days').' días.'
                    .(config('backup.helpdesk.daily_email') === 'always' ? ' El diario de BD también se envía cada día.' : ' Del diario de BD solo llega correo si falla.'),
                'maintenance' => "Limpieza según la retención a las {$schedule['cleanup_time']} y revisión de salud a las {$schedule['monitor_time']}.",
                'space' => 'Suma de todos los zips guardados en el disco de respaldos.',
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (array $filters): Collection => $this->tableRecords($filters))
            ->columns([
                TextColumn::make('type_label')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (array $record): string => $record['type']?->color() ?? 'gray')
                    ->icon(fn (array $record): string => $record['type']?->icon() ?? 'heroicon-o-clock'),
                TextColumn::make('date')
                    ->label('Fecha')
                    ->dateTime('d/m/Y h:i a')
                    ->description(fn (array $record): string => $record['date']->diffForHumans()),
                TextColumn::make('size')
                    ->label('Tamaño')
                    ->formatStateUsing(fn (int $state): string => Number::fileSize($state, precision: 1)),
                TextColumn::make('file')
                    ->label('Archivo')
                    ->fontFamily('mono')
                    ->size('xs')
                    ->color('gray')
                    ->description(fn (array $record): string => $record['folder']),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        BackupType::Database->value => BackupType::Database->label(),
                        BackupType::Full->value => BackupType::Full->label(),
                        'anterior' => 'Anterior',
                    ]),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Descargar')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (array $record): string => app(BackupManager::class)->downloadUrl($record['path'], now()->addMinutes(30)))
                    ->openUrlInNewTab(),
                Action::make('delete')
                    ->label('Eliminar')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar respaldo')
                    ->modalDescription(fn (array $record): string => "Se borrará {$record['file']} del disco. Esta acción no se puede deshacer.")
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->action(function (array $record): void {
                        $deleted = app(BackupManager::class)->delete($record['path'], auth()->user());

                        Notification::make()
                            ->title($deleted ? 'Respaldo eliminado' : 'No se pudo eliminar el respaldo')
                            ->status($deleted ? 'success' : 'danger')
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Aún no hay respaldos')
            ->emptyStateDescription('Usa "Respaldar BD ahora" o espera a la ejecución automática de la madrugada.')
            ->emptyStateIcon(Heroicon::OutlinedCircleStack)
            ->paginated([10, 25, 50]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<string, array<string, mixed>>
     */
    protected function tableRecords(array $filters): Collection
    {
        $type = $filters['type']['value'] ?? null;

        return app(BackupManager::class)
            ->backups()
            ->when(filled($type), fn (Collection $backups): Collection => $backups->filter(
                fn (array $backup): bool => ($backup['type']?->value ?? 'anterior') === $type
            ))
            ->map(fn (array $backup): array => $backup + ['type_label' => $backup['type']?->label() ?? 'Anterior'])
            ->keyBy('key');
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->runBackupAction('backupDatabaseNow', BackupType::Database)
                ->label('Respaldar BD ahora')
                ->icon(Heroicon::OutlinedCircleStack)
                ->modalDescription('Genera un respaldo de la base de datos. Suele tardar menos de un minuto; te avisaremos en la campanita cuando termine.'),
            $this->runBackupAction('backupFullNow', BackupType::Full)
                ->label('Respaldo completo ahora')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('gray')
                ->modalDescription('Genera un respaldo de los archivos subidos y la base de datos y lo envía a los administradores. Puede tardar varios minutos; te avisaremos en la campanita cuando termine.'),
        ];
    }

    protected function runBackupAction(string $name, BackupType $type): Action
    {
        return Action::make($name)
            ->requiresConfirmation()
            ->modalHeading($type === BackupType::Full ? 'Respaldo completo' : 'Respaldo de la base de datos')
            ->modalSubmitActionLabel('Iniciar respaldo')
            ->disabled(fn (): bool => app(BackupManager::class)->isRunning())
            ->action(function () use ($type): void {
                $job = RunBackupJob::dispatch($type, auth()->id());

                // Sin worker (QUEUE_CONNECTION=sync) se ejecuta al terminar
                // la respuesta para que la página no quede esperando.
                if (config('queue.default') === 'sync') {
                    $job->afterResponse();
                }

                Notification::make()
                    ->title('Respaldo en marcha')
                    ->body('Puede tardar unos minutos. Te avisaremos en la campanita cuando termine; recarga la página para verlo en la tabla.')
                    ->info()
                    ->send();
            });
    }
}
