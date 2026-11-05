@php
    /** @var \App\Models\Ticket $ticket */
    $ticket = $getRecord();
    $files = $ticket->getMedia('attachments');
    $iconFor = function (string $mime, string $name): string {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return match (true) {
            str_starts_with($mime, 'image/') => '🖼️',
            $ext === 'pdf' => '📕',
            in_array($ext, ['doc', 'docx'], true) => '📘',
            in_array($ext, ['xls', 'xlsx', 'csv'], true) => '📗',
            in_array($ext, ['zip', 'rar', '7z'], true) => '🗜️',
            default => '📎',
        };
    };
@endphp

{{-- Estilos en línea: los paneles no tienen tema Tailwind propio, así que
     no se pueden usar clases que Filament no incluya en su CSS. --}}
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0.75rem;">
    @foreach ($files as $file)
        @php $isImage = str_starts_with((string) $file->mime_type, 'image/'); @endphp
        <a href="{{ $file->getUrl() }}" target="_blank" rel="noopener"
           style="display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.75rem; border: 1px solid rgba(128,128,128,.25); border-radius: 0.75rem; text-decoration: none; color: inherit; background: rgba(128,128,128,.05);"
           onmouseover="this.style.borderColor='rgba(14,165,233,.6)'" onmouseout="this.style.borderColor='rgba(128,128,128,.25)'">
            @if ($isImage)
                <img src="{{ $file->getUrl() }}" alt="" loading="lazy"
                     style="width: 2.75rem; height: 2.75rem; object-fit: cover; border-radius: 0.5rem; flex-shrink: 0;">
            @else
                <span style="font-size: 1.75rem; line-height: 1; flex-shrink: 0;">{{ $iconFor((string) $file->mime_type, $file->file_name) }}</span>
            @endif

            <span style="min-width: 0; display: flex; flex-direction: column;">
                <span style="font-size: 0.8125rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $file->file_name }}">
                    {{ $file->file_name }}
                </span>
                <span style="font-size: 0.75rem; opacity: .65;">
                    {{ \Illuminate\Support\Number::fileSize($file->size) }} · Abrir ↗
                </span>
            </span>
        </a>
    @endforeach
</div>
