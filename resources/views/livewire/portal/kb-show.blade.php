<div>
    <div class="mb-4">
        <flux:button :href="route('portal.kb.index')" wire:navigate variant="ghost" icon="arrow-left" size="sm">
            Volver al centro de ayuda
        </flux:button>
    </div>

    <article class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <header class="mb-6 border-b border-zinc-200 pb-4 dark:border-zinc-700">
            <div class="mb-3 flex flex-wrap items-center gap-1.5">
                @if ($article->department)
                    <flux:badge size="sm" color="zinc">{{ $article->department->name }}</flux:badge>
                @endif
                @if ($article->category)
                    <flux:badge size="sm" color="sky">{{ $article->category->name }}</flux:badge>
                @endif
            </div>

            <flux:heading size="xl">{{ $article->title }}</flux:heading>

            <div class="mt-2 flex flex-wrap items-center gap-3 text-zinc-500">
                @if ($article->author)
                    <flux:text size="sm">Por {{ $article->author->name }}</flux:text>
                @endif
                @if ($article->published_at)
                    <flux:text size="sm">· Publicado {{ $article->published_at->translatedFormat('d \d\e F \d\e Y') }}</flux:text>
                @endif
                <flux:text size="sm">·
                    <flux:icon name="eye" variant="micro" class="inline" />
                    {{ $article->views_count }} vistas
                </flux:text>
            </div>
        </header>

        <div class="kb-content prose prose-zinc max-w-none dark:prose-invert">
            {!! str($article->body)->markdown()->toHtmlString() !!}
        </div>

        {{-- Feedback --}}
        <div class="mt-8 border-t border-zinc-200 pt-6 dark:border-zinc-700">
            <flux:heading size="sm">¿Te resultó útil este artículo?</flux:heading>

            <div class="mt-3 flex items-center gap-2">
                <flux:button
                    size="sm"
                    icon="hand-thumb-up"
                    :variant="$userVote === true ? 'primary' : 'outline'"
                    wire:click="vote(true)"
                    wire:loading.attr="disabled"
                >
                    Sí, me ayudó
                    @if ($article->helpful_count > 0)
                        <span class="ms-1 text-xs opacity-70">({{ $article->helpful_count }})</span>
                    @endif
                </flux:button>

                <flux:button
                    size="sm"
                    icon="hand-thumb-down"
                    :variant="$userVote === false ? 'danger' : 'outline'"
                    wire:click="vote(false)"
                    wire:loading.attr="disabled"
                >
                    No, no me ayudó
                    @if ($article->not_helpful_count > 0)
                        <span class="ms-1 text-xs opacity-70">({{ $article->not_helpful_count }})</span>
                    @endif
                </flux:button>
            </div>

            @if ($userVote !== null)
                <flux:text size="sm" class="mt-3 text-zinc-500">
                    Gracias por tu feedback. Puedes cambiar tu voto en cualquier momento.
                </flux:text>
            @endif

            <div class="mt-6 rounded-md bg-zinc-50 p-4 dark:bg-zinc-800">
                <flux:text size="sm">
                    ¿Esto no resuelve tu problema?
                </flux:text>
                <flux:button
                    :href="route('portal.tickets.create')"
                    wire:navigate
                    size="sm"
                    variant="primary"
                    icon="plus"
                    class="mt-2"
                >
                    Crear un ticket
                </flux:button>
            </div>
        </div>
    </article>

    {{-- Estilos del Markdown del KB --}}
    <style>
        .kb-content h1, .kb-content h2, .kb-content h3 {
            font-weight: 600;
        }
        .kb-content h1 { font-size: 1.5rem; margin-top: 1.5rem; margin-bottom: 0.75rem; }
        .kb-content h2 { font-size: 1.25rem; margin-top: 1.25rem; margin-bottom: 0.5rem; }
        .kb-content h3 { font-size: 1.1rem; margin-top: 1rem; margin-bottom: 0.5rem; }
        .kb-content p { margin: 0.75rem 0; line-height: 1.6; }
        .kb-content ul, .kb-content ol { margin: 0.75rem 0; padding-left: 1.5rem; }
        .kb-content ul { list-style: disc; }
        .kb-content ol { list-style: decimal; }
        .kb-content li { margin: 0.25rem 0; }
        .kb-content code {
            background: rgba(127, 127, 127, 0.15);
            padding: 0.1rem 0.35rem;
            border-radius: 0.25rem;
            font-size: 0.875em;
        }
        .kb-content pre {
            background: rgba(127, 127, 127, 0.1);
            padding: 0.75rem;
            border-radius: 0.5rem;
            overflow-x: auto;
            margin: 0.75rem 0;
        }
        .kb-content pre code { background: transparent; padding: 0; }
        .kb-content blockquote {
            border-left: 3px solid rgba(127, 127, 127, 0.4);
            padding-left: 1rem;
            color: rgba(127, 127, 127, 1);
            margin: 0.75rem 0;
        }
        .kb-content a {
            color: #0ea5e9;
            text-decoration: underline;
        }
        .kb-content a:hover { color: #0284c7; }
        .kb-content strong { font-weight: 600; }
    </style>
</div>
