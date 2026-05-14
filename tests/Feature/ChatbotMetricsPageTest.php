<?php

use App\Filament\Pages\ChatbotMetrics;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Department;
use App\Models\KbArticle;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShieldPermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, ShieldPermissionSeeder::class]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
    $this->actingAs($this->admin);
});

it('renders the metrics page for super_admin with all the data slots', function () {
    Livewire::test(ChatbotMetrics::class)
        ->assertOk()
        ->assertSet('window', '30')
        ->assertSet('departmentId', null)
        ->assertSet('drilldownArticleId', null);
});

it('blocks access for non-admin users', function () {
    $regular = User::factory()->create();
    $regular->assignRole('usuario_final');
    $this->actingAs($regular);

    expect(ChatbotMetrics::canAccess())->toBeFalse();
});

it('changes the window via Livewire and resets drilldown', function () {
    Livewire::test(ChatbotMetrics::class)
        ->set('drilldownArticleId', 99)
        ->assertSet('drilldownArticleId', 99)
        ->set('window', '7')
        ->assertSet('window', '7')
        ->assertSet('drilldownArticleId', null);
});

it('opens drilldown when clicking on a KB article row', function () {
    $article = KbArticle::factory()->create(['title' => 'Cómo usar VPN']);

    Livewire::test(ChatbotMetrics::class)
        ->call('showDrilldown', $article->id)
        ->assertSet('drilldownArticleId', $article->id);
});

it('closeDrilldown clears the selection', function () {
    Livewire::test(ChatbotMetrics::class)
        ->set('drilldownArticleId', 42)
        ->call('closeDrilldown')
        ->assertSet('drilldownArticleId', null);
});

it('calculates a positive delta when current is greater than previous', function () {
    $page = new ChatbotMetrics;

    $delta = $page->delta(120.0, 100.0);

    expect($delta['absolute'])->toBe(20.0);
    expect($delta['pct'])->toBe(20.0);
    expect($delta['direction'])->toBe('up');
});

it('calculates a negative delta when current is less than previous', function () {
    $page = new ChatbotMetrics;

    $delta = $page->delta(80.0, 100.0);

    expect($delta['absolute'])->toBe(-20.0);
    expect($delta['pct'])->toBe(-20.0);
    expect($delta['direction'])->toBe('down');
});

it('returns null when previous is zero or null (no comparison possible)', function () {
    $page = new ChatbotMetrics;

    expect($page->delta(50.0, 0.0))->toBeNull();
    expect($page->delta(50.0, null))->toBeNull();
    expect($page->delta(null, 50.0))->toBeNull();
});

it('builds a createKbFromGapUrl with the question pre-filled', function () {
    $page = new ChatbotMetrics;

    $url = $page->createKbFromGapUrl('como configurar el wifi');

    expect($url)->toContain('/soporte/kb-articles/create');
    expect($url)->toContain('title=');
    // Laravel URL encoder usa %20 (no +) para espacios.
    expect($url)->toContain('Como%20configurar%20el%20wifi');
});

it('caps the prefilled title at 200 chars', function () {
    $page = new ChatbotMetrics;

    $long = str_repeat('a', 500);
    $url = $page->createKbFromGapUrl($long);

    // El URL-encoded title no debe pasar 200 chars del valor original.
    expect(strlen($long))->toBe(500);
    expect(strlen(urldecode(explode('title=', $url)[1] ?? '')))->toBeLessThanOrEqual(200);
});

it('returns chart data with the right number of buckets matching window', function () {
    Livewire::test(ChatbotMetrics::class)
        ->set('window', '7')
        ->assertViewHas('csatTrend', function (array $data) {
            return count($data['labels']) === 7
                && count($data['csat']) === 7
                && count($data['volume']) === 7;
        });
});

it('filters by department when departmentId is set', function () {
    $deptA = Department::factory()->create(['name' => 'Dept A']);
    $deptB = Department::factory()->create(['name' => 'Dept B']);

    $userA = User::factory()->create(['department_id' => $deptA->id]);
    $userB = User::factory()->create(['department_id' => $deptB->id]);

    $sessionA = ChatSession::create(['user_id' => $userA->id, 'status' => 'active', 'channel' => 'web']);
    $sessionB = ChatSession::create(['user_id' => $userB->id, 'status' => 'active', 'channel' => 'web']);

    ChatMessage::create(['chat_session_id' => $sessionA->id, 'role' => 'assistant', 'content' => 'A', 'source_kind' => 'kb_high']);
    ChatMessage::create(['chat_session_id' => $sessionB->id, 'role' => 'assistant', 'content' => 'B', 'source_kind' => 'kb_high']);

    // Sin filtro: ve los 2 mensajes
    Livewire::test(ChatbotMetrics::class)
        ->assertViewHas('summary', fn ($summary) => $summary['assistant_messages'] === 2);

    // Con filtro a Dept A: ve solo 1
    Livewire::test(ChatbotMetrics::class)
        ->set('departmentId', (string) $deptA->id)
        ->assertViewHas('summary', fn ($summary) => $summary['assistant_messages'] === 1);
});
