<?php

use App\Livewire\Portal\CreateTicket;
use App\Livewire\Portal\KbShow;
use App\Livewire\Portal\ViewTicket;
use App\Models\KbArticle;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Notification::fake();
    Storage::fake('local');
    Storage::fake('public');

    $this->requester = User::factory()->create();
    $this->requester->assignRole('usuario_final');
});

function portalPdf(string $name = 'informe-falla.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj << >> endobj\ntrailer << >>\n%%EOF");
}

it('rechaza adjuntos HTML, SVG y JavaScript al crear el ticket', function (string $name, string $content) {
    Livewire::actingAs($this->requester)
        ->test(CreateTicket::class)
        ->set('subject', 'No abre el correo')
        ->set('description', 'Desde esta mañana no abre Outlook.')
        ->set('attachments', [UploadedFile::fake()->createWithContent($name, $content)])
        ->call('save')
        ->assertHasErrors('attachments.0');

    expect(Ticket::count())->toBe(0);
})->with([
    'html' => ['pagina.html', '<html><body><script>alert(1)</script></body></html>'],
    'svg' => ['dibujo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
    'js' => ['script.js', 'alert(document.cookie);'],
]);

it('acepta un PDF y lo guarda en el disco privado', function () {
    Livewire::actingAs($this->requester)
        ->test(CreateTicket::class)
        ->set('subject', 'No abre el correo')
        ->set('description', 'Desde esta mañana no abre Outlook.')
        ->set('attachments', [portalPdf()])
        ->call('save')
        ->assertHasNoErrors();

    $media = Ticket::sole()->getFirstMedia('attachments');

    expect($media->file_name)->toBe('informe-falla.pdf')
        ->and($media->disk)->toBe('local');
});

it('la vista del ticket muestra el visor de adjuntos con la ruta autenticada', function () {
    $ticket = Ticket::factory()->create(['requester_id' => $this->requester->id]);
    $media = $ticket->addMedia(portalPdf())->toMediaCollection('attachments');

    Livewire::actingAs($this->requester)
        ->test(ViewTicket::class, ['ticket' => $ticket])
        ->assertSee('Adjuntos (1)')
        ->assertSee('informe-falla.pdf')
        ->assertSee('Abrir en pestaña nueva')
        ->assertSee('Descargar')
        ->assertSeeHtml('ticket-attachments')
        ->assertDontSeeHtml('/storage/');

    expect(route('tickets.attachments.show', $media))->toContain('/ticket-attachments/'.$media->id);
});

it('la conversación alinea tus mensajes y muestra adjuntos de los comentarios', function () {
    $ticket = Ticket::factory()->create(['requester_id' => $this->requester->id]);
    $agent = User::factory()->create(['name' => 'Agente Uno']);
    $reply = TicketComment::create(['ticket_id' => $ticket->id, 'user_id' => $agent->id, 'body' => 'Te envío la guía', 'is_private' => false]);
    $reply->addMedia(portalPdf('guia-outlook.pdf'))->toMediaCollection('attachments');
    TicketComment::create(['ticket_id' => $ticket->id, 'user_id' => $this->requester->id, 'body' => 'Gracias', 'is_private' => false]);
    TicketComment::create(['ticket_id' => $ticket->id, 'user_id' => $agent->id, 'body' => 'Asignado a Agente Uno', 'is_private' => false, 'is_system_event' => true, 'event_type' => 'assigned']);

    Livewire::actingAs($this->requester)
        ->test(ViewTicket::class, ['ticket' => $ticket])
        ->assertSee('Agente Uno')
        ->assertSee('guia-outlook.pdf')
        ->assertSeeHtml('flex-row-reverse')
        ->assertSee('3 mensajes');
});

it('escapa el HTML de la descripción y de los comentarios', function () {
    $ticket = Ticket::factory()->create([
        'requester_id' => $this->requester->id,
        'description' => "Hola <script>alert('xss')</script> **urgente**",
    ]);
    TicketComment::create(['ticket_id' => $ticket->id, 'user_id' => $this->requester->id, 'body' => '<img src=x onerror=alert(1)>', 'is_private' => false]);

    Livewire::actingAs($this->requester)
        ->test(ViewTicket::class, ['ticket' => $ticket])
        ->assertSeeHtml('<strong>urgente</strong>')
        ->assertDontSeeHtml("<script>alert('xss')</script>")
        ->assertDontSeeHtml('<img src=x onerror=alert(1)>');
});

it('destaca el resumen de un ticket escalado desde el asistente', function () {
    $ticket = Ticket::factory()->create([
        'requester_id' => $this->requester->id,
        'description' => "# Ticket escalado desde el asistente virtual\n\n"
            ."**Resumen del usuario:** No puedo entrar a SAP\n\n---\n\n"
            ."## Transcripción de la conversación\n\n**Ana** · _10:00_\n\nno me abre sap",
    ]);

    Livewire::actingAs($this->requester)
        ->test(ViewTicket::class, ['ticket' => $ticket])
        ->assertSee('Desde el asistente virtual')
        ->assertSee('Problema reportado')
        ->assertSee('No puedo entrar a SAP')
        ->assertSee('Ver conversación con el asistente');
});

it('el artículo de la base de conocimiento escapa el HTML incrustado', function () {
    $article = KbArticle::factory()->published()->create([
        'body' => "## Pasos\n\n<script>alert('kb')</script>\n\n[enlace](javascript:alert(1))",
    ]);

    Livewire::actingAs($this->requester)
        ->test(KbShow::class, ['slug' => $article->slug])
        ->assertSeeHtml('<h2>Pasos</h2>')
        ->assertDontSeeHtml("<script>alert('kb')</script>")
        ->assertDontSeeHtml('javascript:alert(1)');
});
