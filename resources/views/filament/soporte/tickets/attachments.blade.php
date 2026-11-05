@php
    /** @var \App\Models\Ticket $ticket */
    $ticket = $getRecord();
@endphp

{{-- Compartido por /admin y /soporte. Tarjetas + visor modal; los archivos
     se sirven por la ruta autenticada, no por /storage. --}}
<x-attachment-viewer :files="$ticket->getMedia('attachments')" />
