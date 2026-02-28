<?php

namespace mhrshuvo\JourneyLog\Tests;

use mhrshuvo\JourneyLog\JourneyLogServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            JourneyLogServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('journeylog.use_folders', true);
        $app['config']->set('journeylog.storage_path', 'journeys');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('session.driver', 'array');
    }
}
