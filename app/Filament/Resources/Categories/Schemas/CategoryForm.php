<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('department_id')
                    ->label('Departamento')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),

                Select::make('parent_id')
                    ->label('Categoría padre (opcional)')
                    ->helperText('Si eliges una, esta queda como sub-categoría. Solo se admite un nivel de jerarquía.')
                    ->placeholder('— ninguna (categoría raíz) —')
                    ->options(function (Get $get, ?Category $record) {
                        $departmentId = $get('department_id');

                        if (! $departmentId) {
                            return [];
                        }

                        return Category::query()
                            ->where('department_id', $departmentId)
                            ->whereNull('parent_id')
                            ->when($record?->id, fn ($q) => $q->where('id', '!=', $record->id))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->disabled(fn (?Category $record) => $record !== null && ! $record->canBecomeChild())
                    ->dehydrated()
                    ->searchable()
                    ->preload()
                    // SEG: valida backend que parent_id realmente pertenezca
                    // al mismo depto y no sea una hija. Evita que un atacante
                    // envíe un parent_id arbitrario por POST saltando la UI.
                    ->rules([
                        function (Get $get) {
                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                if (blank($value)) {
                                    return;
                                }
                                $departmentId = (int) $get('department_id');
                                $parent = Category::query()
                                    ->whereKey($value)
                                    ->first();
                                if (! $parent) {
                                    $fail('La categoría padre no existe.');

                                    return;
                                }
                                if ((int) $parent->department_id !== $departmentId) {
                                    $fail('La categoría padre debe pertenecer al mismo departamento.');
                                }
                                if ($parent->parent_id !== null) {
                                    $fail('No se pueden encadenar más de 2 niveles de categoría.');
                                }
                            };
                        },
                    ]),

                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(120)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, TextInput $component) {
                        if ($component->getRecord() !== null) {
                            return;
                        }

                        $component
                            ->getContainer()
                            ->getComponent(fn ($c) => $c instanceof TextInput && $c->getName() === 'slug')
                            ?->state(Str::slug((string) $state));
                    }),

                TextInput::make('slug')
                    ->required()
                    ->maxLength(150)
                    ->unique(Category::class, 'slug', ignoreRecord: true),

                Select::make('icon')
                    ->label('Icono')
                    ->options(self::iconOptions())
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->placeholder('— sin icono —')
                    ->helperText('Elige un icono visual. Se usa en el centro de ayuda y en el portal.')
                    ->getSearchResultsUsing(fn (string $search) => collect(self::iconOptions())
                        ->filter(fn ($label, $key) => str_contains(strtolower($key.$label), strtolower($search)))
                        ->all())
                    ->getOptionLabelUsing(fn (?string $value): ?string => $value ? (self::iconOptions()[$value] ?? $value) : null)
                    ->allowHtml(false)
                    // SEG: valida backend que el icono sea de la whitelist.
                    // El Select de Filament solo filtra en la UI; sin esto
                    // un atacante podría guardar cualquier string.
                    ->rules(['nullable', 'string', Rule::in(array_keys(self::iconOptions()))]),

                Textarea::make('description')
                    ->label('Descripción')
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),

                TextInput::make('sort_order')
                    ->label('Orden')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),

                Toggle::make('is_active')
                    ->label('Activa')
                    ->default(true),
            ]);
    }

    /**
     * Catálogo curado de iconos Heroicon disponibles para las categorías.
     * Mantener alfabéticamente por categoría temática para que el search sea útil.
     *
     * @return array<string, string>
     */
    protected static function iconOptions(): array
    {
        return [
            // Hardware y PCs
            'heroicon-o-computer-desktop' => 'Computadora de escritorio',
            'heroicon-o-cpu-chip' => 'Procesador / CPU',
            'heroicon-o-device-phone-mobile' => 'Teléfono móvil',
            'heroicon-o-device-tablet' => 'Tablet',
            'heroicon-o-printer' => 'Impresora',
            'heroicon-o-server' => 'Servidor',
            'heroicon-o-wifi' => 'Wifi',
            'heroicon-o-signal' => 'Red / Señal',

            // Software y datos
            'heroicon-o-cloud' => 'Nube / Cloud',
            'heroicon-o-code-bracket' => 'Código',
            'heroicon-o-circle-stack' => 'Base de datos',
            'heroicon-o-cog-6-tooth' => 'Configuración',
            'heroicon-o-wrench-screwdriver' => 'Herramientas / Mantenimiento',

            // Seguridad y accesos
            'heroicon-o-key' => 'Llave / Contraseña',
            'heroicon-o-lock-closed' => 'Candado cerrado',
            'heroicon-o-shield-check' => 'Seguridad',
            'heroicon-o-user-circle' => 'Usuario / Cuenta',
            'heroicon-o-user-group' => 'Grupo de usuarios',

            // Comunicación
            'heroicon-o-envelope' => 'Correo',
            'heroicon-o-chat-bubble-left-right' => 'Chat',
            'heroicon-o-phone' => 'Teléfono',
            'heroicon-o-bell' => 'Notificación',
            'heroicon-o-megaphone' => 'Anuncio',

            // Documentos
            'heroicon-o-document-text' => 'Documento',
            'heroicon-o-document-duplicate' => 'Duplicar documento',
            'heroicon-o-folder' => 'Carpeta',
            'heroicon-o-paper-clip' => 'Adjunto',
            'heroicon-o-book-open' => 'Libro / Manual',
            'heroicon-o-clipboard-document' => 'Portapapeles',

            // Flujos y tickets
            'heroicon-o-ticket' => 'Ticket',
            'heroicon-o-inbox' => 'Bandeja de entrada',
            'heroicon-o-check-circle' => 'Resuelto',
            'heroicon-o-exclamation-triangle' => 'Advertencia',
            'heroicon-o-exclamation-circle' => 'Alerta',
            'heroicon-o-clock' => 'Reloj / Tiempo',
            'heroicon-o-calendar' => 'Calendario',

            // Áreas de negocio
            'heroicon-o-briefcase' => 'Negocio / Empresa',
            'heroicon-o-building-office-2' => 'Edificio corporativo',
            'heroicon-o-truck' => 'Transporte / Logística',
            'heroicon-o-banknotes' => 'Finanzas / Pagos',
            'heroicon-o-shopping-cart' => 'Compras',
            'heroicon-o-academic-cap' => 'Formación',
            'heroicon-o-heart' => 'HSEQ / Salud',
            'heroicon-o-light-bulb' => 'Idea / Propuesta',

            // Operación y campo
            'heroicon-o-map' => 'Mapa',
            'heroicon-o-map-pin' => 'Ubicación',
            'heroicon-o-fire' => 'Emergencia',
            'heroicon-o-beaker' => 'Laboratorio',

            // General
            'heroicon-o-sparkles' => 'IA / Automático',
            'heroicon-o-tag' => 'Etiqueta',
            'heroicon-o-question-mark-circle' => 'Ayuda / FAQ',
            'heroicon-o-information-circle' => 'Información',
        ];
    }
}
