Набросок кода для подключения в свой проект и работы с ним.

# src/Providers/AssistantRouterServiceProvider.php
<?php
namespace AssistantRouter\Providers;

use Illuminate\Support\ServiceProvider;
use AssistantRouter\Router\RouterInterface;
use AssistantRouter\Router\Router;

class AssistantRouterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/router.php', 'assistant-router');
        $this->app->bind(RouterInterface::class, Router::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/router.php' => config_path('assistant-router.php'),
        ], 'assistant-router-config');
    }
}

# src/Router/Intent.php
<?php
namespace AssistantRouter\Router;

enum Intent: string {
    case PRODUCT_SEARCH = 'product_search';
    case AVAILABILITY   = 'availability_check';
    case ORDER_CREATE   = 'order_create';
    case ORDER_STATUS   = 'order_status';
    case DELIVERY_INFO  = 'delivery_info';
    case PAYMENT_INFO   = 'payment_info';
    case RETURNS_INFO   = 'returns_info';
    case FAQ            = 'faq';
    case HUMAN_HANDOFF  = 'human_handoff';
    case SMALLTALK      = 'smalltalk';
    case UNKNOWN        = 'unknown';
}

# src/Router/RouteResult.php
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

# src/Router/RouterInterface.php
<?php
namespace AssistantRouter\Router;

interface RouterInterface
{
    public function classify(string $text, array $context = []): RouteResult;
}

# src/Router/Rules/RuleInterface.php
<?php
namespace AssistantRouter\Router\Rules;

use AssistantRouter\Router\RouteResult;

interface RuleInterface
{
    /** Return RouteResult or null if not matched */
    public function match(string $text, array $ctx = []): ?RouteResult;
}

# src/Router/Rules/PayloadRule.php
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

# src/Router/Rules/KeywordRule.php
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

# src/Router/Rules/RegexProductRule.php
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

# src/Router/Classifiers/LlmClassifier.php
<?php
namespace AssistantRouter\Router\Classifiers;

use AssistantRouter\Router\{Intent, RouteResult};

/**
 * Minimal wrapper over an LLM provider. Inject your own client via container binding `llm.classifier`.
 */
class LlmClassifier
{
    public function __construct(private $client = null)
    {
        $this->client = $this->client ?: app('llm.classifier'); // bind in your app
    }

    public function classify(string $text, array $ctx = []): RouteResult
    {
        $intents = array_map(fn($c) => $c->value, Intent::cases());
        $prompt = [
            'role' => 'system',
            'content' => "Ты маршрутизатор чата магазина. Верни JSON: {intent, confidence, slots_needed[]}.".
                " Intent одно из: ".json_encode($intents, JSON_UNESCAPED_UNICODE).".\n".
                "Контекст: ".json_encode($ctx, JSON_UNESCAPED_UNICODE)."."
        ];
        $user = ['role' => 'user', 'content' => $text];

        // Expected response: [ 'intent' => 'product_search', 'confidence' => 0.73, 'slots_needed' => ['size'] ]
        $resp = $this->client->classify([$prompt, $user]);

        $intent = Intent::tryFrom($resp['intent'] ?? '') ?? Intent::UNKNOWN;
        $conf   = (float)($resp['confidence'] ?? 0.5);
        $slots  = $resp['slots_needed'] ?? [];

        return new RouteResult($intent, $conf, $slots, 'llm', ['raw' => $resp]);
    }
}

# src/Router/Router.php
<?php
namespace AssistantRouter\Router;

use AssistantRouter\Router\Rules\{RuleInterface, PayloadRule, KeywordRule, RegexProductRule};
use AssistantRouter\Router\Classifiers\LlmClassifier;

class Router implements RouterInterface
{
    /** @var RuleInterface[] */
    private array $rules;

    public function __construct(private LlmClassifier $llm)
    {
        $this->rules = [
            new PayloadRule(),
            new KeywordRule(),
            new RegexProductRule(),
        ];
    }

    public function classify(string $text, array $context = []): RouteResult
    {
        $ruleStrong = (float)config('assistant-router.thresholds.rule_strong', 0.80);
        $llmUse     = (float)config('assistant-router.thresholds.llm_use', 0.70);
        $fallback   = (float)config('assistant-router.thresholds.fallback', 0.60);

        $weak = null;
        foreach ($this->rules as $rule) {
            if ($r = $rule->match($text, $context)) {
                if ($r->confidence >= $ruleStrong) return $r;
                $weak = $r; // keep weak result
                break;
            }
        }

        $llm = $this->llm->classify($text, $context);

        if ($weak) {
            if ($llm->intent === $weak->intent) {
                return ($llm->confidence > $weak->confidence) ? $llm : $weak;
            }
            if (($context['payload'] ?? null) !== null) return $weak; // payload priority
            return ($llm->confidence >= $llmUse) ? $llm : $weak;
        }

        return $llm->confidence >= $fallback ? $llm : new RouteResult(Intent::UNKNOWN, 0.5, [], 'fallback');
    }
}

# src/Router/RouteMap.php
<?php
namespace AssistantRouter\Router;

class RouteMap
{
    public static function handlerFor(Intent $intent): string
    {
        return match ($intent) {
            Intent::PRODUCT_SEARCH => 'App\\Application\\Handlers\\ProductSearchHandler',
            Intent::AVAILABILITY   => 'App\\Application\\Handlers\\AvailabilityHandler',
            Intent::ORDER_CREATE   => 'App\\Application\\Handlers\\OrderCreateHandler',
            Intent::DELIVERY_INFO  => 'App\\Application\\Handlers\\DeliveryInfoHandler',
            Intent::PAYMENT_INFO   => 'App\\Application\\Handlers\\PaymentInfoHandler',
            Intent::ORDER_STATUS   => 'App\\Application\\Handlers\\OrderStatusHandler',
            Intent::HUMAN_HANDOFF  => 'App\\Application\\Handlers\\HumanHandoffHandler',
            Intent::FAQ            => 'App\\Application\\Handlers\\FaqHandler',
            Intent::SMALLTALK      => 'App\\Application\\Handlers\\SmalltalkHandler',
            default                => 'App\\Application\\Handlers\\UnknownHandler',
        };
    }
}

# config/router.php (publishable -> config/assistant-router.php)
<?php
return [
    'llm' => [
        'provider'    => env('LLM_PROVIDER', 'openai'),
        'model'       => env('LLM_MODEL', 'gpt-5-mini'),
        'timeout'     => 1.5,
        'temperature' => 0.0,
    ],
    'thresholds' => [
        'rule_strong' => 0.80,
        'llm_use'     => 0.70,
        'fallback'    => 0.60,
    ],
];

# tests/Pest.php
<?php
uses()->group('assistant-router');

# tests/RouterTest.php
<?php
use AssistantRouter\Router\RouterInterface;

it('routes payload to order_create', function () {
    $router = app(RouterInterface::class);
    $r = $router->classify('любая фраза', ['payload' => 'ORDER_CREATE:ABC']);
    expect($r->intent->value)->toBe('order_create');
    expect($r->confidence)->toBeGreaterThan(0.9);
});

it('detects delivery info by keyword', function () {
    $router = app(RouterInterface::class);
    $r = $router->classify('Сколько стоит доставка в Кишинёв?');
    expect($r->intent->value)->toBe('delivery_info');
});

# README.md (excerpt)
# Assistant Router for Laravel

## Install (as path repo)
1. Place package in `packages/assistant-router`.
2. In app `composer.json` add:
```json
{
  "repositories": [
    {"type": "path", "url": "packages/assistant-router"}
  ],
  "require": {"amigo/assistant-router": "*@dev"}
}
```
3. `composer update`
4. Publish config: `php artisan vendor:publish --tag=assistant-router-config`
5. Bind your LLM client:
```php
// e.g., in AppServiceProvider
$this->app->singleton('llm.classifier', function(){
    return new class {
        public function classify(array $messages): array {
            // call your provider here; return ['intent'=>'smalltalk','confidence'=>0.6,'slots_needed'=>[]]
            return ['intent' => 'unknown', 'confidence' => 0.5, 'slots_needed' => []];
        }
    }; 
});
```

## Usage
```php
$router = app(\AssistantRouter\Router\RouterInterface::class);
$result = $router->classify($incomingText, $context); // RouteResult
$handlerClass = \AssistantRouter\Router\RouteMap::handlerFor($result->intent);
$handler = app($handlerClass);
$response = $handler->handle($message, $result);
```

# Example: FbWebhookController stub (in your app)
<?php
// app/Http/Controllers/FbWebhookController.php (example)
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use AssistantRouter\Router\RouterInterface;
use AssistantRouter\Router\RouteMap;

class FbWebhookController extends Controller
{
    public function incoming(Request $req)
    {
        $msg = $this->extract($req); // implement extract text/payload/psid
        $ctx = [
            'channel' => 'messenger',
            'payload' => $msg['payload'] ?? null,
            'locale'  => $msg['locale'] ?? 'ru',
            'session' => $this->loadSession($msg['psid'] ?? ''),
        ];

        /** @var RouterInterface $router */
        $router = app(RouterInterface::class);
        $route = $router->classify($msg['text'] ?? '', $ctx);

        $handlerClass = RouteMap::handlerFor($route->intent);
        $handler = app($handlerClass);
        $reply = $handler->handle((object)$msg, $route);

        return response()->json($reply);
    }
}