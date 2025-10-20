<?php
namespace AssistantRouter\Router\Classifiers;

use AssistantRouter\Router\{Intent, RouteResult};

class LlmClassifier
{
    public function __construct(private $client = null)
    {
        $this->client = $this->client ?: app('llm.classifier');
    }

    public function classify(string $text, array $ctx = []): RouteResult
    {
        $intents = array_map(fn($c) => $c->value, Intent::cases());
        $prompt = [
            'role' => 'system',
            'content' => "Ты маршрутизатор чата магазина. Верни JSON: {intent, confidence, slots_needed[]}."
                ." Intent одно из: ".json_encode($intents, JSON_UNESCAPED_UNICODE).".\n"
                ."Контекст: ".json_encode($ctx, JSON_UNESCAPED_UNICODE)."."
        ];
        $user = ['role' => 'user', 'content' => $text];

        // Expected: ['intent'=>'product_search','confidence'=>0.73,'slots_needed'=>['size']]
        $resp = $this->client->classify([$prompt, $user]);

        $intent = Intent::tryFrom($resp['intent'] ?? '') ?? Intent::UNKNOWN;
        $conf   = (float)($resp['confidence'] ?? 0.5);
        $slots  = $resp['slots_needed'] ?? [];

        return new RouteResult($intent, $conf, $slots, 'llm', ['raw' => $resp]);
    }
}
