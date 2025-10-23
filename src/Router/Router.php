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
