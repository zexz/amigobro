<?php

use AssistantRouter\Router\RouterInterface;

uses()->group('assistant-router');

it('routes payload to order_create', function () {
    /** @var RouterInterface $router */
    $router = app(RouterInterface::class);

    $r = $router->classify('любая фраза', ['payload' => 'ORDER_CREATE:ABC']);

    expect($r->intent->value)->toBe('order_create');
    expect($r->confidence)->toBeGreaterThan(0.9);
});

it('detects delivery info by keyword', function () {
    /** @var RouterInterface $router */
    $router = app(RouterInterface::class);

    $r = $router->classify('Сколько стоит доставка в Кишинёв?');

    expect($r->intent->value)->toBe('delivery_info');
});