<?php

use Illuminate\Container\Container;
use Illuminate\Config\Repository as ConfigRepository;
use AssistantRouter\Router\Router;
use AssistantRouter\Router\Intent;
use AssistantRouter\Router\Classifiers\LlmClassifier;

function ensureConfigBound(): void {
    $app = Container::getInstance();
    if (!$app) {
        $app = new Container();
        Container::setInstance($app);
    }
    if (! $app->bound('config')) {
        $app->instance('config', new ConfigRepository([]));
    }

    $app['config']->set('assistant-router', [
        'llm' => [
            'provider'    => 'openai',
            'model'       => 'gpt-5-mini',
            'timeout'     => 1.5,
            'temperature' => 0.0,
        ],
        'thresholds' => [
            'rule_strong' => 0.80,
            'llm_use'     => 0.70,
            'fallback'    => 0.60,
        ],
    ]);
}

it('routes payload to order_create', function () {
    ensureConfigBound();

    $mockClient = new class {
        public function classify(array $messages): array {
            return ['intent' => 'unknown', 'confidence' => 0.5, 'slots_needed' => []];
        }
    };

    $router = new Router(new LlmClassifier($mockClient));

    $r = $router->classify('любая фраза', ['payload' => 'ORDER_CREATE:ABC']);

    expect($r->intent)->toBe(Intent::ORDER_CREATE);
    expect($r->confidence)->toBeGreaterThan(0.9);
});

it('detects delivery info by keyword', function () {
    ensureConfigBound();

    $mockClient = new class {
        public function classify(array $messages): array {
            return ['intent' => 'unknown', 'confidence' => 0.5, 'slots_needed' => []];
        }
    };

    $router = new Router(new LlmClassifier($mockClient));

    $r = $router->classify('Сколько стоит доставка в Кишинёв?');

    expect($r->intent)->toBe(Intent::DELIVERY_INFO);
});
