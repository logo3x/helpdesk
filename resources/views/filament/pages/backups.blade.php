<x-filament-panels::page>
    @php
        $helpIcon = fn (string $text) => svg('heroicon-o-question-mark-circle', 'inline-block size-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-help align-text-bottom', [
            'x-tooltip.raw' => e($text),
            'aria-label' => e($text),
        ])->toHtml();
    @endphp

    <div class="flex flex-col gap-6" @if ($isRunning) wire:poll.10s @endif>

        {{-- Avisos de configuración --}}
        @if ($isRunning)
            <div class="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200">
                <x-filament::loading-indicator class="mt-0.5 size-5 shrink-0" />
                <p>Hay un respaldo en curso. Esta página se actualiza sola cada 10 segundos.</p>
            </div>
        @endif

        @if ($dumpWarning)
            <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="mt-0.5 size-5 shrink-0" />
                <p>{{ $dumpWarning }}</p>
            </div>
        @endif

        @unless ($hasPassword)
            <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                <x-filament::icon icon="heroicon-o-lock-open" class="mt-0.5 size-5 shrink-0" />
                <p>Los zips no tienen contraseña y el de BD se envía por correo. Define <code class="font-mono">BACKUP_ARCHIVE_PASSWORD</code> en el .env para cifrarlos (se abren con 7-Zip o WinRAR).</p>
            </div>
        @endunless

        {{-- Tarjetas de estado --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($cards as $card)
                @php($type = $card['type'])
                <section @class([
                    'flex flex-col gap-4 rounded-xl bg-white p-5 shadow-sm ring-1 dark:bg-gray-900',
                    'ring-gray-950/5 dark:ring-white/10' => $card['healthy'],
                    'ring-danger-300 dark:ring-danger-500/40' => ! $card['healthy'],
                ])>
                    <header class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span @class([
                                'flex size-10 items-center justify-center rounded-lg',
                                'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400' => $type === \App\Enums\BackupType::Database,
                                'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' => $type === \App\Enums\BackupType::Full,
                            ])>
                                <x-filament::icon :icon="$type->icon()" class="size-5" />
                            </span>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ $type === \App\Enums\BackupType::Database ? 'Base de datos (diario)' : 'Completo (semanal)' }}
                                    {!! $helpIcon($type === \App\Enums\BackupType::Database ? $help['database'] : $help['full']) !!}
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $card['schedule'] }}</p>
                            </div>
                        </div>

                        <x-filament::badge :color="$card['healthy'] ? 'success' : 'danger'" :icon="$card['healthy'] ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-circle'">
                            {{ $card['healthy'] ? 'Sano' : 'Revisar' }}
                        </x-filament::badge>
                    </header>

                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Último respaldo</dt>
                            <dd class="font-medium text-gray-950 dark:text-white">
                                @if ($card['latest'])
                                    {{ $card['latest']['date']->format('d/m/Y h:i a') }}
                                    <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">{{ $card['latest']['date']->diffForHumans() }}</span>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">Sin respaldos</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Tamaño</dt>
                            <dd class="font-medium text-gray-950 dark:text-white">
                                {{ $card['latest'] ? \Illuminate\Support\Number::fileSize($card['latest']['size'], precision: 1) : '—' }}
                                <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">{{ $card['count'] }} {{ $card['count'] === 1 ? 'copia guardada' : 'copias guardadas' }}</span>
                            </dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Próxima ejecución</dt>
                            <dd class="font-medium text-gray-950 dark:text-white">
                                {{ ucfirst($card['nextRun']->translatedFormat('l d/m/Y \a \l\a\s h:i a')) }}
                                <span class="text-xs font-normal text-gray-500 dark:text-gray-400">({{ $card['nextRun']->diffForHumans() }})</span>
                            </dd>
                        </div>
                    </dl>

                    @if ($card['lastFailed'])
                        <div class="rounded-lg bg-danger-50 p-3 text-xs text-danger-700 dark:bg-danger-500/10 dark:text-danger-300">
                            <p class="font-semibold">Último intento fallido{{ $card['lastFailedAt'] ? ' · '.$card['lastFailedAt']->format('d/m/Y h:i a') : '' }}</p>
                            <p class="mt-1 break-words">{{ \Illuminate\Support\Str::limit((string) $card['lastError'], 300) }}</p>
                        </div>
                    @elseif (! $card['latest'])
                        <p class="text-xs text-gray-500 dark:text-gray-400">Aún no se ha generado ningún respaldo de este tipo.</p>
                    @elseif (! $card['healthy'])
                        <p class="text-xs text-danger-600 dark:text-danger-400">El último respaldo tiene más de {{ $type->maxAgeInDays() }} {{ $type->maxAgeInDays() === 1 ? 'día' : 'días' }}. Revisa que la tarea programada esté corriendo.</p>
                    @endif
                </section>
            @endforeach

            <section class="flex flex-col gap-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 md:col-span-2 xl:col-span-1 dark:bg-gray-900 dark:ring-white/10">
                <header class="flex items-center gap-3">
                    <span class="flex size-10 items-center justify-center rounded-lg bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300">
                        <x-filament::icon icon="heroicon-o-server-stack" class="size-5" />
                    </span>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                            Espacio usado {!! $helpIcon($help['space']) !!}
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Disco de respaldos</p>
                    </div>
                </header>

                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Respaldos</dt>
                        <dd class="text-lg font-semibold text-gray-950 dark:text-white">{{ \Illuminate\Support\Number::fileSize($totalSize, precision: 1) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Libre en la unidad</dt>
                        <dd class="text-lg font-semibold text-gray-950 dark:text-white">{{ $freeSpace !== null ? \Illuminate\Support\Number::fileSize($freeSpace, precision: 1) : '—' }}</dd>
                    </div>
                </dl>

                <ul class="space-y-2 text-xs text-gray-600 dark:text-gray-400">
                    <li class="flex gap-2">
                        <x-filament::icon icon="heroicon-m-envelope" class="mt-0.5 size-4 shrink-0 text-gray-400" />
                        <span>{{ $help['email'] }}</span>
                    </li>
                    <li class="flex gap-2">
                        <x-filament::icon icon="heroicon-m-arrow-path" class="mt-0.5 size-4 shrink-0 text-gray-400" />
                        <span>{{ $help['maintenance'] }}</span>
                    </li>
                </ul>
            </section>
        </div>

        {{-- Tabla de respaldos --}}
        <div>
            <h2 class="mb-3 text-base font-semibold text-gray-950 dark:text-white">
                Respaldos guardados
                {!! $helpIcon('“Anterior” son copias hechas antes de separar los respaldos en BD y Completo; se pueden descargar o eliminar a mano.') !!}
            </h2>

            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
