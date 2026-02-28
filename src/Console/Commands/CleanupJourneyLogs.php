<?php

namespace mhrshuvo\JourneyLog\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class CleanupJourneyLogs extends Command
{
    protected $signature = 'journey-log:cleanup {--dry-run : Show what would be deleted without actually deleting} {--hours= : Override retention hours}';

    protected $description = 'Clean up journey log JSON files older than configured retention period and remove empty directories';

    public function handle(): int
    {
        $config = config('journeylog');
        $storagePath = storage_path('logs/'.($config['storage_path'] ?? 'journeys'));

        if (! is_dir($storagePath)) {
            $this->info("Journey log directory does not exist: {$storagePath}");

            return self::SUCCESS;
        }

        if (! is_readable($storagePath)) {
            $this->error("Cannot read journey log directory: {$storagePath}");
            $this->error('Check directory permissions for the web server user.');

            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');
        $retentionHours = $this->option('hours') ?? $config['cleanup_retention_hours'] ?? 1;
        $cutoffTime = now()->subHours($retentionHours);

        $deletedFiles = 0;
        $deletedFolders = 0;
        $errors = 0;

        $this->info("Cleaning up journey logs older than {$retentionHours} hour(s)...");

        // Clean up JSON files older than 1 hour
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($storagePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        $filesToDelete = [];
        $foldersToCheck = [];

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'json') {
                $fileModifiedTime = \Carbon\Carbon::createFromTimestamp($file->getMTime());

                if ($fileModifiedTime->lt($cutoffTime)) {
                    $filesToDelete[] = $file->getPathname();
                }
            } elseif ($file->isDir()) {
                $foldersToCheck[] = $file->getPathname();
            }
        }

        // Delete old JSON files
        foreach ($filesToDelete as $filePath) {
            if ($dryRun) {
                $this->line("Would delete file: {$filePath}");
                $deletedFiles++;
            } else {
                try {
                    if (File::delete($filePath)) {
                        $this->line("Deleted file: {$filePath}");
                        $deletedFiles++;
                    } else {
                        $this->error("Failed to delete file: {$filePath}");
                        $errors++;
                    }
                } catch (\Exception $e) {
                    $this->error("Error deleting file {$filePath}: ".$e->getMessage());
                    $errors++;
                }
            }
        }

        // Remove empty directories (excluding the root storage path)
        $foldersToCheck = array_filter($foldersToCheck, fn ($folder) => $folder !== $storagePath);

        // Sort folders by depth (deepest first) to ensure we check child folders before parent folders
        usort($foldersToCheck, fn ($a, $b) => substr_count($b, '/') - substr_count($a, '/'));

        foreach ($foldersToCheck as $folderPath) {
            if (is_dir($folderPath) && $this->isEmptyDirectory($folderPath)) {
                if ($dryRun) {
                    $this->line("Would delete empty folder: {$folderPath}");
                    $deletedFolders++;
                } else {
                    try {
                        if (rmdir($folderPath)) {
                            $this->line("Deleted empty folder: {$folderPath}");
                            $deletedFolders++;
                        } else {
                            $this->error("Failed to delete folder: {$folderPath}");
                            $errors++;
                        }
                    } catch (\Exception $e) {
                        $this->error("Error deleting folder {$folderPath}: ".$e->getMessage());
                        $errors++;
                    }
                }
            }
        }

        $action = $dryRun ? 'Would delete' : 'Deleted';
        $this->info("{$action} {$deletedFiles} JSON files and {$deletedFolders} empty folders");

        if ($errors > 0) {
            $this->error("Encountered {$errors} errors during cleanup");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function isEmptyDirectory(string $directory): bool
    {
        $handle = opendir($directory);
        while (($entry = readdir($handle)) !== false) {
            if ($entry !== '.' && $entry !== '..') {
                closedir($handle);

                return false;
            }
        }
        closedir($handle);

        return true;
    }
}
