<?php

use Illuminate\Support\Facades\Context;

if (! function_exists('journey_log')) {
    function journey_log(?string $folder, string $message, array $data = [])
    {
        $config = config('journeylog');

        $sessionKey = $config['session_key'] ?? 'journey_id';
        $header = $config['header'] ?? 'X-Journey-ID';

        $journeyId = session($sessionKey)
            ?? request()->header($header)
            ?? Context::get('journey_id')
            ?? 'guest';

        $subPath = $folder
            ? trim($folder, '/').'/'
            : '';

        $baseDir = storage_path('logs/'.($config['storage_path'] ?? 'journeys'));
        $filePath = "{$baseDir}/{$subPath}journey-{$journeyId}.json";

        try {
            if (! file_exists(dirname($filePath))) {
                if (! mkdir(dirname($filePath), 0775, true) && ! is_dir(dirname($filePath))) {
                    // Fallback: try to log to Laravel's default log instead
                    \Log::warning('JourneyLog: Could not create directory', [
                        'path' => dirname($filePath),
                        'message' => $message,
                        'data' => $data,
                    ]);

                    return;
                }
                // Set proper permissions after creation
                chmod(dirname($filePath), 0775);
            }

            $entries = [];
            if (file_exists($filePath)) {
                $existing = json_decode(file_get_contents($filePath), true);
                if (is_array($existing)) {
                    $entries = $existing;
                }
            }

            $entries[] = [
                'message' => $message,
                'context' => $data,
                'level' => 200,
                'level_name' => 'INFO',
                'channel' => 'journey',
                'datetime' => now()->format(config('journeylog.datetime_format', 'Y-m-d\TH:i:sP')),
                'extra' => [],
            ];

            $jsonContent = json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if (file_put_contents($filePath, $jsonContent, LOCK_EX) === false) {
                // Fallback: try to log to Laravel's default log instead
                \Log::warning('JourneyLog: Could not write to journey log file', [
                    'path' => $filePath,
                    'message' => $message,
                    'data' => $data,
                ]);

                return;
            }
            // Set proper file permissions
            chmod($filePath, 0664);
        } catch (\Exception $e) {
            // Final fallback to Laravel log
            \Log::error('JourneyLog encountered an error', [
                'error' => $e->getMessage(),
                'message' => $message,
                'data' => $data,
            ]);
        }
    }
}
