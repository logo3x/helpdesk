<div wire:poll.30s>
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="ghost" size="sm" class="relative" icon="bell">
            @if ($unreadCount > 0)
                <span class="absolute -top-0.5 -right-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white">
                    {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                </span>
            @endif
        </flux:button>

        <flux:menu class="!w-96 !max-w-[calc(100vw-1rem)] !p-0">
            <div class="flex items-center justify-between border-b border-zinc-200 px-3 py-2 dark:border-zinc-700">
                <flux:heading size="sm">Notificaciones</flux:heading>
                @if ($unreadCount > 0)
                    <flux:button
                        size="xs"
                        variant="ghost"
                        wire:click="markAllAsRead"
                    >
                        Marcar todas como leídas
                    </flux:button>
                @endif
            </div>

            <div class="max-h-[28rem] overflow-y-auto">
                @forelse ($recent as $notif)
                    @php
                        $data = $notif->data ?? [];
                        $title = $data['title'] ?? 'Notificación';
                        $body = $data['body'] ?? null;
                        $icon = $data['icon'] ?? 'bell';
                        $iconColor = $data['iconColor'] ?? 'zinc';
                        $isUnread = $notif->read_at === null;
                        // Mapear heroicon-o-foo / heroicon-m-foo / heroicon-foo a "foo"
                        $iconName = preg_replace('/^heroicon-(o|m|s)-/', '', (string) $icon);
                    @endphp

                    <button
                        type="button"
                        wire:click="markAsReadAndGo('{{ $notif->id }}')"
                        class="flex w-full gap-3 border-b border-zinc-100 px-3 py-3 text-start transition hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800 {{ $isUnread ? 'bg-sky-50/50 dark:bg-sky-900/10' : '' }}"
                    >
                        <div class="mt-0.5 shrink-0">
                            <flux:icon
                                :name="$iconName"
                                variant="outline"
                                class="size-5 {{ match($iconColor) {
                                    'success' => 'text-green-600 dark:text-green-400',
                                    'info' => 'text-sky-600 dark:text-sky-400',
                                    'warning' => 'text-amber-600 dark:text-amber-400',
                                    'danger' => 'text-red-600 dark:text-red-400',
                                    default => 'text-zinc-500',
                                } }}"
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <flux:heading size="sm" class="truncate {{ $isUnread ? '' : 'opacity-70' }}">
                                    {{ $title }}
                                </flux:heading>
                                @if ($isUnread)
                                    <span class="mt-1 inline-block size-2 shrink-0 rounded-full bg-sky-500"></span>
                                @endif
                            </div>
                            @if ($body)
                                <flux:text size="sm" class="mt-0.5 line-clamp-2 {{ $isUnread ? 'text-zinc-600 dark:text-zinc-300' : 'text-zinc-400' }}">
                                    {{ $body }}
                                </flux:text>
                            @endif
                            <flux:text size="xs" class="mt-1 text-zinc-400">
                                {{ $notif->created_at->diffForHumans() }}
                            </flux:text>
                        </div>
                    </button>
                @empty
                    <div class="px-3 py-8 text-center">
                        <flux:icon name="bell-slash" class="mx-auto mb-2 size-8 text-zinc-400" />
                        <flux:text size="sm" class="text-zinc-500">No tienes notificaciones</flux:text>
                    </div>
                @endforelse
            </div>
        </flux:menu>
    </flux:dropdown>
</div>
