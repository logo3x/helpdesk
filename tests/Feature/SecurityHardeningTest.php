<?php

use App\Enums\BackupType;
use App\Filament\Pages\Backups;
use App\Models\User;
use App\Services\BackupManager;
use App\Services\MarkdownRenderer;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShieldPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Hallazgos de la revisión de seguridad de adjuntos, respaldos y textos.
 */
beforeEach(function () {
    $this->seed([RoleSeeder::class, ShieldPermissionSeeder::class]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('MarkdownRenderer escapa HTML y descarta enlaces javascript:', function () {
    $html = (string) MarkdownRenderer::safe('<img src=x onerror="alert(1)"> **hola** [enlace](javascript:alert(1))');

    expect($html)->toContain('<strong>hola</strong>')
        ->toContain('&lt;img src=x')
        ->not->toContain('<img')
        ->not->toContain('href="javascript:');
});

it('ninguna vista Blade renderiza markdown con HTML crudo', function () {
    $unsafe = collect(File::allFiles(resource_path('views')))
        ->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.blade.php'))
        ->filter(fn (SplFileInfo $file): bool => str_contains(file_get_contents($file->getPathname()), '->markdown()'))
        ->map(fn (SplFileInfo $file): string => $file->getRelativePathname())
        ->values()
        ->all();

    expect($unsafe)->toBe([]);
});

it('la página Respaldos vuelve a revisar el rol en cada acción de Livewire', function () {
    Storage::fake('backups');
    Storage::disk('backups')->put('helpdesk-completo/completo-2026-11-01-02-30-00.zip', 'zip');
    $full = app(BackupManager::class)->latest(BackupType::Full);

    $this->actingAs($this->admin);
    $page = Livewire::test(Backups::class)->assertOk();

    $this->admin->removeRole('admin');

    $page->call('mountAction', 'delete', [], ['table' => true, 'recordKey' => $full['key']])
        ->assertForbidden();

    Storage::disk('backups')->assertExists('helpdesk-completo/completo-2026-11-01-02-30-00.zip');
});

it('los textos de ayuda de la página Respaldos se escapan en los atributos', function () {
    Storage::fake('backups');
    config(['backup.helpdesk.schedule.cleanup_time' => '"><script>alert(1)</script>']);

    $this->actingAs($this->admin);

    $html = Livewire::test(Backups::class)->assertOk()->html();

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});
