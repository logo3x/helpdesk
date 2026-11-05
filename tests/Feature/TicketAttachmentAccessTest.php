<?php

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShieldPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    $this->seed([RoleSeeder::class, ShieldPermissionSeeder::class]);
    Notification::fake();
    Storage::fake('local');
    Storage::fake('public');

    $this->department = Department::factory()->create();

    $this->requester = User::factory()->create();
    $this->requester->assignRole('usuario_final');

    $this->agent = User::factory()->create(['department_id' => $this->department->id]);
    $this->agent->assignRole('agente_soporte');

    $this->ticket = Ticket::factory()->create([
        'requester_id' => $this->requester->id,
        'assigned_to_id' => $this->agent->id,
        'department_id' => $this->department->id,
    ]);
});

function attachPdf(Ticket|TicketComment $owner, string $name = 'informe.pdf'): Media
{
    return $owner->addMedia(UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj << >> endobj\ntrailer << >>\n%%EOF"))
        ->toMediaCollection('attachments');
}

it('guarda los adjuntos nuevos en el disco privado', function () {
    $media = attachPdf($this->ticket);

    expect($media->disk)->toBe('local');
    Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
    Storage::disk('public')->assertMissing($media->getPathRelativeToRoot());
});

it('el solicitante dueño ve el PDF en línea con cabeceras de seguridad', function () {
    $media = attachPdf($this->ticket);

    $response = $this->actingAs($this->requester)
        ->get(route('tickets.attachments.show', $media))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN');

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline')
        ->toContain('informe.pdf');
});

it('el agente asignado del departamento puede ver el adjunto', function () {
    $media = attachPdf($this->ticket);

    $this->actingAs($this->agent)
        ->get(route('tickets.attachments.show', $media))
        ->assertOk();
});

it('otro usuario final recibe 403', function () {
    $media = attachPdf($this->ticket);
    $stranger = User::factory()->create();
    $stranger->assignRole('usuario_final');

    $this->actingAs($stranger)
        ->get(route('tickets.attachments.show', $media))
        ->assertForbidden();

    $this->actingAs($stranger)
        ->get(route('tickets.attachments.download', $media))
        ->assertForbidden();
});

it('un agente de otro departamento recibe 403', function () {
    $media = attachPdf($this->ticket);
    $otherAgent = User::factory()->create(['department_id' => Department::factory()->create()->id]);
    $otherAgent->assignRole('agente_soporte');

    $this->actingAs($otherAgent)
        ->get(route('tickets.attachments.show', $media))
        ->assertForbidden();
});

it('sin sesión redirige al login', function () {
    $media = attachPdf($this->ticket);

    $this->get(route('tickets.attachments.show', $media))
        ->assertRedirect(route('login'));
});

it('las imágenes se muestran en línea dentro de un sandbox', function () {
    $media = $this->ticket->addMedia(UploadedFile::fake()->image('captura.png'))
        ->toMediaCollection('attachments');

    $response = $this->actingAs($this->requester)
        ->get(route('tickets.attachments.show', $media))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($response->headers->get('Content-Security-Policy'))->toContain('sandbox');
});

it('los tipos sin vista previa siempre se descargan', function () {
    $media = $this->ticket->addMedia(UploadedFile::fake()->createWithContent('respaldo.zip', "PK\x03\x04".str_repeat("\0", 64)))
        ->toMediaCollection('attachments');

    $response = $this->actingAs($this->requester)
        ->get(route('tickets.attachments.show', $media))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/octet-stream');

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment');
});

it('la ruta de descarga fuerza la descarga incluso de un PDF', function () {
    $media = attachPdf($this->ticket);

    $response = $this->actingAs($this->requester)
        ->get(route('tickets.attachments.download', $media))
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment');
});

it('sigue sirviendo los adjuntos antiguos del disco público', function () {
    $media = attachPdf($this->ticket);
    Storage::disk('public')->put($media->getPathRelativeToRoot(), Storage::disk('local')->get($media->getPathRelativeToRoot()));
    Storage::disk('local')->delete($media->getPathRelativeToRoot());
    $media->update(['disk' => 'public', 'conversions_disk' => 'public']);

    $this->actingAs($this->requester)
        ->get(route('tickets.attachments.show', $media->fresh()))
        ->assertOk();
});

it('los adjuntos de una nota interna no los ve el solicitante', function () {
    $note = TicketComment::create([
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->agent->id,
        'body' => 'Captura del log interno',
        'is_private' => true,
    ]);
    $media = attachPdf($note, 'log-interno.pdf');

    $this->actingAs($this->requester)
        ->get(route('tickets.attachments.show', $media))
        ->assertNotFound();

    $this->actingAs($this->agent)
        ->get(route('tickets.attachments.show', $media))
        ->assertOk();
});

it('no sirve archivos de otras colecciones o modelos', function () {
    $user = User::factory()->create();
    $media = Media::query()->create([
        'model_type' => User::class,
        'model_id' => $user->id,
        'uuid' => (string) str()->uuid(),
        'collection_name' => 'attachments',
        'name' => 'x',
        'file_name' => 'x.pdf',
        'mime_type' => 'application/pdf',
        'disk' => 'local',
        'conversions_disk' => 'local',
        'size' => 1,
        'manipulations' => [],
        'custom_properties' => [],
        'generated_conversions' => [],
        'responsive_images' => [],
    ]);

    $this->actingAs($this->requester)
        ->get(route('tickets.attachments.show', $media))
        ->assertNotFound();
});

it('rechaza HTML, SVG y ejecutables aunque el formulario los deje pasar', function (string $name, string $content) {
    expect(fn () => $this->ticket->addMedia(UploadedFile::fake()->createWithContent($name, $content))
        ->toMediaCollection('attachments'))
        ->toThrow(FileUnacceptableForCollection::class);
})->with([
    'html' => ['pagina.html', '<html><body><script>alert(1)</script></body></html>'],
    'svg' => ['dibujo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
    'js disfrazado de texto' => ['script.js', 'alert(document.cookie);'],
    'exe' => ['programa.exe', "MZ\x90\x00".str_repeat("\0", 64)],
]);

it('el comando mueve los adjuntos antiguos del disco público al privado', function () {
    $media = attachPdf($this->ticket);
    $path = $media->getPathRelativeToRoot();
    Storage::disk('public')->put($path, Storage::disk('local')->get($path));
    Storage::disk('local')->delete($path);
    $media->update(['disk' => 'public', 'conversions_disk' => 'public']);

    $this->artisan('tickets:move-attachments-private', ['--dry-run' => true])->assertSuccessful();
    expect($media->fresh()->disk)->toBe('public');

    $this->artisan('tickets:move-attachments-private')->assertSuccessful();

    expect($media->fresh()->disk)->toBe('local');
    Storage::disk('local')->assertExists($path);
    Storage::disk('public')->assertMissing($path);
});
