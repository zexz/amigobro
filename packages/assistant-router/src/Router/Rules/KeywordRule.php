<?php
namespace AssistantRouter\Router\Rules;

use AssistantRouter\Router\{Intent, RouteResult};

class KeywordRule implements RuleInterface
{
    private array $map = [
        'delivery_info'  => ['доставка','когда привезут','сколько едет','курьер','самовывоз'],
        'payment_info'   => ['оплата','карта','наложка','перевод','cash on delivery'],
        'order_status'   => ['статус заказа','где заказ','трек','номер заказа','tracking'],
        'human_handoff'  => ['оператор','менеджер','живой человек','перезвоните','позвоните'],
        'returns_info'   => ['возврат','обмен','гарантия','ремонт'],
    ];

    public function match(string $text, array $ctx = []): ?RouteResult
    {
        $t = mb_strtolower($text);
        foreach ($this->map as $intent => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($t, $kw)) {
                    return new RouteResult(Intent::from($intent), 0.85, [], "keyword:$kw");
                }
            }
        }
        return null;
    }
}
