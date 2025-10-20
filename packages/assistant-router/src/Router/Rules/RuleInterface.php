<?php
namespace AssistantRouter\Router\Rules;

use AssistantRouter\Router\RouteResult;

interface RuleInterface
{
    /** Return RouteResult or null if not matched */
    public function match(string $text, array $ctx = []): ?RouteResult;
}
