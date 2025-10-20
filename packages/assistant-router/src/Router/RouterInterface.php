<?php
namespace AssistantRouter\Router;

interface RouterInterface
{
    public function classify(string $text, array $context = []): RouteResult;
}
