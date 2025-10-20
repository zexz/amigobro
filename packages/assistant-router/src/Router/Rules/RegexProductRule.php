<?php
namespace AssistantRouter\Router\Rules;

use AssistantRouter\Router\{Intent, RouteResult};

class RegexProductRule implements RuleInterface
{
    public function match(string $text, array $ctx = []): ?RouteResult
    {
        $t = mb_strtolower($text);
        if (preg_match('/(ищу|хочу|подобрать|посоветуйте).*(кроссовк|кеды|ботинк|nike|adidas|sneaker)/u', $t)) {
            $slots = [];
            if (preg_match('/(\d{2})(?:\s?размер|)/u', $t, $m)) $slots['size'] = (int)$m[1];
            if (preg_match('/(черн(ый|ые)|бел(ый|ые)|син(ий|ие)|красн(ый|ые))/u', $t, $m)) $slots['color'] = $m[1];
            return new RouteResult(Intent::PRODUCT_SEARCH, 0.8, [], 'regex_product:'.json_encode($slots, JSON_UNESCAPED_UNICODE));
        }
        return null;
    }
}
