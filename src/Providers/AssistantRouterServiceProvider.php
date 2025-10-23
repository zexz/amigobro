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

        if (!$this->app->bound('llm.classifier')) {
            $this->app->singleton('llm.classifier', function () {
                return new class {
                    public function classify(array $messages): array {
                        return ['intent' => 'unknown', 'confidence' => 0.5, 'slots_needed' => []];
                    }
                };
            });
        }

        $this->app->bind(RouterInterface::class, function ($app) {
            return $app->make(Router::class);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/router.php' => config_path('assistant-router.php'),
        ], 'assistant-router-config');
    }
}
