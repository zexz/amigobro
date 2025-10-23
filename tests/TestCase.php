<?php

namespace AssistantRouter\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use AssistantRouter\Providers\AssistantRouterServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [AssistantRouterServiceProvider::class];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('assistant-router.thresholds.rule_strong', 0.80);
    }
}
