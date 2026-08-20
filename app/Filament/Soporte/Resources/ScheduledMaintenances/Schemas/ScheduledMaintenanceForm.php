<?php

namespace App\Filament\Soporte\Resources\ScheduledMaintenances\Schemas;

use App\Enums\MaintenanceFrequency;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\Department;
use App\Models\Project;
use App\Models\ScheduledMaintenance;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * Formulario del módulo Mantenimientos Programados.
 *
 * Al CREAR ofrece dos modos:
 *   - individual: 1 activo, búsqueda rica por TAG/serial/custodio/etc.
 *   - masivo:    filtros por gerencia/campo/zona/proyecto/custodio
 *                → preview → selección múltiple. Crea N mantenimientos
 *                con misma fecha, agente y frecuencia. Notifica una sola
 *                vez al agente ("N mantenimientos asignados").
 *
 * Al EDITAR solo se ve el modo individual (por diseño: un mtto es un
 * registro atómico) con status, avance, observations y motivo.
 *
 * Solo activos tipo desktop/laptop/all_in_one/server aparecen en los
 * selectores.
 */
class ScheduledMaintenanceForm
{
    /** Tipos de activo que aceptan programación de mantenimiento. */
    public const ELIGIBLE_TYPES = ['desktop', 'laptop', 'all_in_one', 'server'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ── SELECTOR DE MODO (solo en Create) ─────────────────
                Radio::make('creation_mode')
                    ->label('¿Qué querés programar?')
                    ->options([
                        'individual' => '📄 Un solo activo',
                        'bulk' => '📦 Varios activos (programación masiva)',
                    ])
                    ->descriptions([
                        'individual' => 'Programa un mantenimiento sobre un activo específico.',
                        'bulk' => 'Filtrá por gerencia, campo, zona, proyecto o custodio y seleccioná varios activos a la vez. Ideal para jornadas de mtto de una sede.',
                    ])
                    ->default('individual')
                    ->required()
                    ->live()
                    ->dehydrated(false)
                    ->columnSpanFull()
                    ->visible(fn (string $operation) => $operation === 'create'),

                // ── MODO INDIVIDUAL ───────────────────────────────────
                Section::make('Activo a mantener')
                    ->icon('heroicon-o-computer-desktop')
                    ->columnSpanFull()
                    ->visible(fn (Get $get, string $operation) => $operation === 'edit' || ($get('creation_mode') ?? 'individual') === 'individual')
                    ->schema([
                        Select::make('asset_id')
                            ->label('Activo')
                            ->relationship(
                                name: 'asset',
                                titleAttribute: 'asset_tag',
                                modifyQueryUsing: fn (Builder $query) => $query
                                    ->whereIn('type', self::ELIGIBLE_TYPES)
                                    ->with(['user:id,name,identification', 'project:id,code,name', 'department:id,name'])
                                    ->orderBy('asset_tag'),
                            )
                            // Búsqueda rica: por TAG, serial, SAP, hostname,
                            // nombre y cédula de custodio (como columna
                            // desnormalizada custodian_name/custodian_id_number),
                            // nombre y código de proyecto, campo, zona y
                            // gerencia.
                            ->searchable([
                                'asset_tag', 'hostname', 'serial_number', 'sap_code',
                                'custodian_name', 'custodian_id_number', 'custodian_position',
                                'field', 'location_zone', 'management_area',
                            ])
                            ->getOptionLabelFromRecordUsing(fn (Asset $r) => self::renderAssetOptionHtml($r))
                            ->allowHtml()
                            ->preload()
                            ->required(fn (Get $get, string $operation) => $operation === 'edit' || ($get('creation_mode') ?? 'individual') === 'individual')
                            ->disabled(fn (string $operation) => $operation === 'edit')
                            ->dehydrated()
                            ->helperText('Buscá por TAG, serial, código SAP, hostname, nombre o cédula del custodio, proyecto, campo, zona o gerencia. Solo activos de tipo computador, portátil, all-in-one y servidor.'),
                    ]),

                // ── MODO MASIVO: FILTROS ──────────────────────────────
                Section::make('Filtros de búsqueda')
                    ->icon('heroicon-o-funnel')
                    ->description('Aplicá uno o más filtros. El listado se actualiza a medida que seleccionás. Todos los filtros son opcionales.')
                    ->columns(3)
                    ->columnSpanFull()
                    ->visible(fn (Get $get, string $operation) => $operation === 'create' && ($get('creation_mode') ?? '') === 'bulk')
                    ->schema([
                        Select::make('bulk_filter_type')
                            ->label('Tipo de activo')
                            ->options([
                                'desktop' => 'Computador de escritorio',
                                'laptop' => 'Portátil',
                                'all_in_one' => 'Todo en uno (all-in-one)',
                                'server' => 'Servidor',
                            ])
                            ->multiple()
                            ->preload()
                            ->native(false)
                            ->live()
                            ->dehydrated(false)
                            ->placeholder('Todos los elegibles'),

                        Select::make('bulk_filter_management_area')
                            ->label('Gerencia')
                            ->options(fn () => Asset::query()
                                ->whereIn('type', self::ELIGIBLE_TYPES)
                                ->whereNotNull('management_area')
                                ->distinct()
                                ->orderBy('management_area')
                                ->pluck('management_area', 'management_area')
                                ->all())
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->dehydrated(false),

                        Select::make('bulk_filter_field')
                            ->label('Campo')
                            ->options(fn () => Asset::query()
                                ->whereIn('type', self::ELIGIBLE_TYPES)
                                ->whereNotNull('field')
                                ->distinct()
                                ->orderBy('field')
                                ->pluck('field', 'field')
                                ->all())
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->dehydrated(false),

                        Select::make('bulk_filter_location_zone')
                            ->label('Zona')
                            ->options(fn () => Asset::query()
                                ->whereIn('type', self::ELIGIBLE_TYPES)
                                ->whereNotNull('location_zone')
                                ->distinct()
                                ->orderBy('location_zone')
                                ->pluck('location_zone', 'location_zone')
                                ->all())
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->dehydrated(false),

                        Select::make('bulk_filter_project_id')
                            ->label('Proyecto')
                            ->options(fn () => Project::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn ($p) => [$p->id => "{$p->code} · {$p->name}"])
                                ->all())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->live()
                            ->dehydrated(false),

                        Select::make('bulk_filter_department_id')
                            ->label('Departamento')
                            ->options(fn () => Department::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->live()
                            ->dehydrated(false),
                    ]),

                // ── MODO MASIVO: SELECCIÓN MÚLTIPLE ───────────────────
                Section::make('Activos a incluir')
                    ->icon('heroicon-o-queue-list')
                    ->columnSpanFull()
                    ->visible(fn (Get $get, string $operation) => $operation === 'create' && ($get('creation_mode') ?? '') === 'bulk')
                    ->schema([
                        // Contador arriba del select. Usamos hiddenLabel()
                        // para no mostrar el nombre "camelCase" del componente.
                        Placeholder::make('bulk_asset_count')
                            ->hiddenLabel()
                            ->content(function (Get $get) {
                                $selected = count($get('bulk_asset_ids') ?? []);
                                $matching = self::bulkAssetsMatchingCount($get);

                                if ($selected === 0) {
                                    return new HtmlString(sprintf(
                                        '<div class="text-sm text-gray-500 dark:text-gray-400">'
                                        .'Hay <b>%d</b> activo(s) que matchean los filtros actuales. Empezá a escribir o hacé click en el campo de abajo para verlos y seleccionar.'
                                        .'</div>',
                                        $matching,
                                    ));
                                }

                                return new HtmlString(sprintf(
                                    '<div class="text-sm font-medium text-warning-600 dark:text-warning-400">'
                                    .'Vas a crear <b>%d</b> mantenimiento(s) — uno por cada activo — con la misma fecha, agente y frecuencia.'
                                    .' <span class="text-gray-500 dark:text-gray-400 font-normal">(%d activos matchean los filtros en total)</span>'
                                    .'</div>',
                                    $selected, $matching,
                                ));
                            }),

                        // Select con búsqueda server-side. Cada tecla dispara
                        // getSearchResultsUsing() que sí lee los filtros
                        // actuales del form vía Get. Esto evita el issue de
                        // options() estático que Filament cachea al inicio.
                        Select::make('bulk_asset_ids')
                            ->label('Activos')
                            ->multiple()
                            ->searchable()
                            ->required(fn (Get $get) => ($get('creation_mode') ?? '') === 'bulk')
                            ->dehydrated(false)
                            ->live()
                            ->allowHtml()
                            ->getSearchResultsUsing(fn (string $search, Get $get) => self::bulkAssetSearchResults($search, $get))
                            ->getOptionLabelsUsing(fn (array $values) => self::bulkAssetLabelsFor($values))
                            ->helperText('Escribí para filtrar (TAG, hostname, custodio, etc.) o hacé click y aparecerán los activos que matchean los filtros. Podés seleccionar varios.'),
                    ]),

                // ── PROGRAMACIÓN (fecha, agente, frecuencia) ──────────
                Section::make('Programación')
                    ->icon('heroicon-o-calendar')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('agent_id')
                            ->label('Agente asignado')
                            ->relationship(
                                name: 'agent',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->whereHas('roles', fn ($q) => $q->whereIn('name', [
                                    'super_admin', 'admin', 'supervisor_soporte', 'agente_soporte', 'tecnico_campo',
                                ]))->orderBy('name'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (User $r) => $r->name.($r->email ? " · {$r->email}" : ''))
                            ->searchable(['name', 'email'])
                            ->preload()
                            ->required()
                            ->helperText('Recibirá una notificación en la campanita.'),

                        DatePicker::make('scheduled_at')
                            ->label('Fecha del mantenimiento')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->minDate(fn (string $operation) => $operation === 'create' ? now()->startOfDay() : null),

                        Select::make('frequency')
                            ->label('Frecuencia')
                            ->options([
                                MaintenanceFrequency::Cuatrimestral->value => MaintenanceFrequency::Cuatrimestral->label(),
                                MaintenanceFrequency::Anual->value => MaintenanceFrequency::Anual->label(),
                            ])
                            ->required()
                            ->native(false)
                            ->helperText('Al marcar cumplido/no cumplido, se agenda automáticamente el siguiente ciclo.'),
                    ]),

                // ── ESTADO Y EJECUCIÓN (solo Edit) ────────────────────
                Section::make('Estado y ejecución')
                    ->icon('heroicon-o-check-circle')
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn (string $operation) => $operation === 'edit')
                    ->schema([
                        ToggleButtons::make('status')
                            ->label('Estado')
                            ->options([
                                MaintenanceStatus::Pendiente->value => MaintenanceStatus::Pendiente->label(),
                                MaintenanceStatus::Cumplido->value => MaintenanceStatus::Cumplido->label(),
                                MaintenanceStatus::NoCumplido->value => MaintenanceStatus::NoCumplido->label(),
                            ])
                            ->colors([
                                MaintenanceStatus::Pendiente->value => 'gray',
                                MaintenanceStatus::Cumplido->value => 'success',
                                MaintenanceStatus::NoCumplido->value => 'danger',
                            ])
                            ->icons([
                                MaintenanceStatus::Pendiente->value => 'heroicon-o-clock',
                                MaintenanceStatus::Cumplido->value => 'heroicon-o-check-badge',
                                MaintenanceStatus::NoCumplido->value => 'heroicon-o-x-circle',
                            ])
                            ->inline()
                            ->required()
                            ->live(),

                        Select::make('progress_percent')
                            ->label('Porcentaje de avance')
                            ->options(collect(range(0, 100, 10))
                                ->mapWithKeys(fn ($v) => [$v => "{$v}%"])
                                ->all())
                            ->default(0)
                            ->required()
                            ->native(false),

                        Textarea::make('observations')
                            ->label('Observaciones')
                            ->rows(4)
                            ->maxLength(2000)
                            ->required(fn (Get $get, string $operation) => $operation === 'edit'
                                && auth()->user()?->hasAnyRole(['agente_soporte', 'tecnico_campo']))
                            ->helperText('Obligatorio al editar un mantenimiento asignado. Registra qué se hizo, componentes revisados, hallazgos.')
                            ->columnSpanFull(),

                        Textarea::make('not_completed_reason')
                            ->label('Motivo de no cumplimiento')
                            ->rows(3)
                            ->maxLength(1000)
                            ->visible(fn (Get $get) => $get('status') === MaintenanceStatus::NoCumplido->value)
                            ->required(fn (Get $get) => $get('status') === MaintenanceStatus::NoCumplido->value)
                            ->helperText('Ej: "Usuario no disponible", "Repuesto pendiente", "Equipo en préstamo".')
                            ->columnSpanFull(),
                    ]),

                Section::make('Trazabilidad')
                    ->icon('heroicon-o-clock')
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull()
                    ->visible(fn (string $operation) => $operation === 'edit')
                    ->schema([
                        Placeholder::make('created_by_display')
                            ->label('Programado por')
                            ->content(fn (?ScheduledMaintenance $record) => $record?->createdBy?->name ?? '—'),

                        Placeholder::make('completed_at_display')
                            ->label('Cerrado el')
                            ->content(fn (?ScheduledMaintenance $record) => $record?->completed_at?->translatedFormat('d M Y H:i') ?? 'Aún no cerrado'),

                        Placeholder::make('parent_display')
                            ->label('Ciclo anterior')
                            ->content(fn (?ScheduledMaintenance $record) => $record?->parent
                                ? '#'.$record->parent->id.' ('.$record->parent->scheduled_at->translatedFormat('d M Y').')'
                                : 'Este es el primer ciclo'),
                    ]),
            ]);
    }

    /**
     * Render HTML de 2 líneas para cada opción del Select de asset.
     * Línea 1: TAG + tipo + hostname. Línea 2: custodio + ubicación.
     */
    protected static function renderAssetOptionHtml(Asset $r): string
    {
        $tag = e($r->asset_tag ?: 'Sin TAG');
        $type = e(strtoupper($r->type ?? ''));
        $hostname = $r->hostname ? ' · '.e($r->hostname) : '';
        $serial = $r->serial_number ? ' · S/N '.e($r->serial_number) : '';

        $custodian = $r->user?->name
            ?? $r->custodian_name
            ?? 'Sin custodio';
        $cedula = $r->user?->identification
            ?? $r->custodian_id_number
            ?? null;
        $custodianLine = e($custodian).($cedula ? ' · CC '.e($cedula) : '');

        $locationParts = array_filter([
            $r->project?->code ? 'Proy '.e($r->project->code) : null,
            $r->management_area ? e($r->management_area) : null,
            $r->field ? 'Campo '.e($r->field) : null,
            $r->location_zone ? 'Zona '.e($r->location_zone) : null,
        ]);
        $locationLine = $locationParts !== [] ? implode(' · ', $locationParts) : 'Sin ubicación';

        return sprintf(
            '<div class="py-1"><div class="font-medium">%s · %s%s%s</div><div class="text-xs text-gray-500 dark:text-gray-400">%s · %s</div></div>',
            $tag, $type, $hostname, $serial,
            $custodianLine, $locationLine,
        );
    }

    /**
     * Query base con los filtros del modo masivo aplicados. Se usa
     * para el count total, para la búsqueda searchable y para los
     * labels de los ya seleccionados.
     */
    protected static function bulkAssetQuery(Get $get): Builder
    {
        $types = $get('bulk_filter_type') ?: self::ELIGIBLE_TYPES;
        $types = array_values(array_intersect($types, self::ELIGIBLE_TYPES));
        if ($types === []) {
            $types = self::ELIGIBLE_TYPES;
        }

        $query = Asset::query()
            ->whereIn('type', $types)
            ->with(['user:id,name,identification', 'project:id,code,name', 'department:id,name']);

        if ($v = $get('bulk_filter_management_area')) {
            $query->where('management_area', $v);
        }
        if ($v = $get('bulk_filter_field')) {
            $query->where('field', $v);
        }
        if ($v = $get('bulk_filter_location_zone')) {
            $query->where('location_zone', $v);
        }
        if ($v = $get('bulk_filter_project_id')) {
            $query->where('project_id', $v);
        }
        if ($v = $get('bulk_filter_department_id')) {
            $query->where('department_id', $v);
        }

        return $query;
    }

    /** Cuántos activos matchean los filtros actuales (para el contador). */
    protected static function bulkAssetsMatchingCount(Get $get): int
    {
        return self::bulkAssetQuery($get)->count();
    }

    /**
     * Búsqueda server-side de activos aplicando filtros + término
     * escrito por el usuario. Devuelve id => html. Cap 50 para
     * responsividad.
     *
     * @return array<int, string>
     */
    protected static function bulkAssetSearchResults(string $search, Get $get): array
    {
        $query = self::bulkAssetQuery($get);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('asset_tag', 'like', $like)
                    ->orWhere('hostname', 'like', $like)
                    ->orWhere('serial_number', 'like', $like)
                    ->orWhere('sap_code', 'like', $like)
                    ->orWhere('custodian_name', 'like', $like)
                    ->orWhere('custodian_id_number', 'like', $like);
            });
        }

        return $query->orderBy('asset_tag')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Asset $a) => [$a->id => self::renderAssetOptionHtml($a)])
            ->all();
    }

    /**
     * Labels HTML de los activos ya seleccionados (para que
     * multiple() los renderice al re-hidratar el form).
     *
     * @param  array<int, int|string>  $values
     * @return array<int, string>
     */
    protected static function bulkAssetLabelsFor(array $values): array
    {
        if ($values === []) {
            return [];
        }

        return Asset::query()
            ->whereIn('id', $values)
            ->with(['user:id,name,identification', 'project:id,code,name', 'department:id,name'])
            ->get()
            ->mapWithKeys(fn (Asset $a) => [$a->id => self::renderAssetOptionHtml($a)])
            ->all();
    }
}
