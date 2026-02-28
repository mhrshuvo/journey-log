<?php

namespace mhrshuvo\JourneyLog;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class JourneyLogServiceProvider extends ServiceProvider
{
    private array $envKeys = [
        'JOURNEY_LOG_STORAGE_PATH' => 'journeys',
        'JOURNEY_LOG_HEADER' => 'X-Journey-ID',
        'JOURNEY_LOG_SESSION_KEY' => 'journey_id',
        'JOURNEY_LOG_SUMMARY_ENABLED' => 'false',
        'JOURNEY_LOG_SUMMARY_ROUTE_PREFIX' => 'journey-log',
        'JOURNEY_LOG_AUTO_CLEANUP_ENABLED' => 'true',
        'JOURNEY_LOG_CLEANUP_RETENTION_HOURS' => '1',
    ];

    public function boot(Router $router)
    {
        $router->aliasMiddleware('journey-log', \mhrshuvo\JourneyLog\Middleware\LogCustomerJourney::class);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'journeylog');

        if (config('journeylog.summary_enabled')) {
            $this->registerSummaryRoutes();
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/journeylog.php' => config_path('journeylog.php'),
            ], 'journeylog-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/journeylog'),
            ], 'journeylog-views');

            $this->commands([
                \mhrshuvo\JourneyLog\Console\Commands\CleanupJourneyLogs::class,
            ]);

            $this->appendEnvKeys();
        }

        $this->registerScheduledTasks();
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/journeylog.php', 'journeylog');
    }

    private function registerSummaryRoutes(): void
    {
        $prefix = config('journeylog.summary_route_prefix', 'journey-log');
        $middleware = config('journeylog.summary_middleware', ['web']);

        Route::prefix($prefix)
            ->middleware($middleware)
            ->group(__DIR__.'/../routes/web.php');
    }

    private function registerScheduledTasks(): void
    {
        $this->app->booted(function () {
            if (config('journeylog.auto_cleanup_enabled', true)) {
                $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
                $schedule->command('journey-log:cleanup')->hourly();
            }
        });
    }

    private function appendEnvKeys(): void
    {
        $this->addKeysToFile(base_path('.env.example'));
        $this->addKeysToFile(base_path('.env'));
    }

    private function addKeysToFile(string $filePath): void
    {
        if (! file_exists($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);
        $keysToAdd = [];

        foreach ($this->envKeys as $key => $default) {
            if (! str_contains($content, $key.'=')) {
                $keysToAdd[] = "{$key}={$default}";
            }
        }

        if (empty($keysToAdd)) {
            return;
        }

        $block = "\n# JourneyLog\n".implode("\n", $keysToAdd)."\n";
        file_put_contents($filePath, $content.$block);
    }
}
