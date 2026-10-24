<?php

use App\Filament\Resources\Assets\Pages\EditAsset;
use App\Models\Asset;

function invokeNavigator(EditAsset $page): array
{
    $m = new ReflectionMethod($page, 'getNavigatorPosition');
    $m->setAccessible(true);

    return $m->invoke($page);
}

it('calcula prev/next cuando el activo está en la lista cacheada', function () {
    $a = Asset::factory()->create();
    $b = Asset::factory()->create();
    $c = Asset::factory()->create();

    session()->put('assets.navigator', [$a->id, $b->id, $c->id]);

    $page = new EditAsset;
    $page->record = $b;

    [$prev, $next, $pos, $total] = invokeNavigator($page);

    expect($prev)->toBe($a->id);
    expect($next)->toBe($c->id);
    expect($pos)->toBe(2);
    expect($total)->toBe(3);
});

it('devuelve null para prev cuando es el primero', function () {
    $a = Asset::factory()->create();
    $b = Asset::factory()->create();

    session()->put('assets.navigator', [$a->id, $b->id]);

    $page = new EditAsset;
    $page->record = $a;

    [$prev, $next] = invokeNavigator($page);

    expect($prev)->toBeNull();
    expect($next)->toBe($b->id);
});

it('devuelve null para next cuando es el último', function () {
    $a = Asset::factory()->create();
    $b = Asset::factory()->create();

    session()->put('assets.navigator', [$a->id, $b->id]);

    $page = new EditAsset;
    $page->record = $b;

    [$prev, $next] = invokeNavigator($page);

    expect($prev)->toBe($a->id);
    expect($next)->toBeNull();
});

it('devuelve todos nulls cuando no hay sesión cacheada', function () {
    session()->forget('assets.navigator');

    $asset = Asset::factory()->create();
    $page = new EditAsset;
    $page->record = $asset;

    [$prev, $next, $pos, $total] = invokeNavigator($page);

    expect($prev)->toBeNull();
    expect($next)->toBeNull();
    expect($pos)->toBeNull();
    expect($total)->toBeNull();
});

it('devuelve nulls cuando el record no está en la lista cacheada', function () {
    $a = Asset::factory()->create();
    $b = Asset::factory()->create();
    $otro = Asset::factory()->create();

    session()->put('assets.navigator', [$a->id, $b->id]);

    $page = new EditAsset;
    $page->record = $otro;

    [$prev, $next, $pos, $total] = invokeNavigator($page);

    expect($prev)->toBeNull();
    expect($next)->toBeNull();
    expect($pos)->toBeNull();
    expect($total)->toBeNull();
});
