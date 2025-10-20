<?php
namespace AssistantRouter\Router;

class RouteResult
{
    public function __construct(
        public readonly Intent $intent,
        public readonly float $confidence,
        public readonly array $slotsNeeded = [],
        public readonly ?string $reason = null,
        public readonly array $meta = [],
    ) {}
}
