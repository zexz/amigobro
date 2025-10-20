<?php
namespace AssistantRouter\Router\Rules;

use AssistantRouter\Router\{Intent, RouteResult};

class PayloadRule implements RuleInterface
{
    public function match(string $text, array $ctx = []): ?RouteResult
    {
        $payload = $ctx['payload'] ?? null; // Messenger postback/quick reply payload
        if (!$payload) return null;

        return match (true) {
            str_starts_with($payload, 'ORDER_CREATE') =>
                new RouteResult(Intent::ORDER_CREATE, 0.99, [], 'payload'),
            str_starts_with($payload, 'ASK_DELIVERY') =>
                new RouteResult(Intent::DELIVERY_INFO, 0.99, [], 'payload'),
            str_starts_with($payload, 'ASK_PAYMENT') =>
                new RouteResult(Intent::PAYMENT_INFO, 0.99, [], 'payload'),
            str_starts_with($payload, 'ASK_STATUS') =>
                new RouteResult(Intent::ORDER_STATUS, 0.99, [], 'payload'),
            str_starts_with($payload, 'HUMAN') =>
                new RouteResult(Intent::HUMAN_HANDOFF, 0.99, [], 'payload'),
            default => null,
        };
    }
}
