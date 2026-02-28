<?php

namespace mhrshuvo\JourneyLog\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class JourneyLogSummaryController extends Controller
{
    public function index(Request $request)
    {
        $baseDir = $this->baseDir();

        if (! File::isDirectory($baseDir)) {
            return view('journeylog::index', ['journeys' => collect(), 'folder' => null, 'folders' => []]);
        }

        $folders = $this->listFolders($baseDir);
        $folder = $request->query('folder');
        $search = $request->query('search');

        if ($folder) {
            $journeys = $this->listJourneys($baseDir.'/'.trim($folder, '/'), $folder);
        } else {
            // Collect from root + all subfolders
            $journeys = $this->listJourneys($baseDir, null);
            foreach ($folders as $f) {
                $journeys = $journeys->merge($this->listJourneys($baseDir.'/'.$f, $f));
            }
            $journeys = $journeys->sortByDesc('last_datetime')->values();
        }

        // Filter by search term if provided
        if ($search) {
            $journeys = $journeys->filter(function ($journey) use ($search) {
                return stripos($journey['journey_id'], $search) !== false;
            });
        }

        return view('journeylog::index', [
            'journeys' => $journeys,
            'folder' => $folder,
            'folders' => $folders,
            'search' => $search,
        ]);
    }

    public function show(string $journeyId, Request $request)
    {
        $baseDir = $this->baseDir();
        $folder = $request->query('folder');

        $searchDir = $folder ? $baseDir.'/'.trim($folder, '/') : $baseDir;
        $filePath = $searchDir.'/journey-'.$journeyId.'.json';

        if (! File::exists($filePath)) {
            // Try to find it recursively
            $found = $this->findJourneyFile($baseDir, $journeyId);
            if (! $found) {
                abort(404, 'Journey log not found.');
            }
            $filePath = $found;
        }

        $entries = json_decode(File::get($filePath), true) ?? [];

        return view('journeylog::show', [
            'journeyId' => $journeyId,
            'entries' => $entries,
            'folder' => $folder,
        ]);
    }

    public function delete(string $journeyId, Request $request)
    {
        $baseDir = $this->baseDir();
        $folder = $request->query('folder');

        $searchDir = $folder ? $baseDir.'/'.trim($folder, '/') : $baseDir;
        $filePath = $searchDir.'/journey-'.$journeyId.'.json';

        if (! File::exists($filePath)) {
            // Try to find it recursively
            $found = $this->findJourneyFile($baseDir, $journeyId);
            if (! $found) {
                return response()->json(['success' => false, 'message' => 'Journey log not found.'], 404);
            }
            $filePath = $found;
        }

        if (File::delete($filePath)) {
            return response()->json(['success' => true, 'message' => 'Journey log deleted successfully.']);
        }

        return response()->json(['success' => false, 'message' => 'Failed to delete journey log.'], 500);
    }

    public function deleteFolder(string $folderName, Request $request)
    {
        $baseDir = $this->baseDir();
        $folderPath = $baseDir.'/'.trim($folderName, '/');

        if (! File::isDirectory($folderPath)) {
            return response()->json(['success' => false, 'message' => 'Folder not found.'], 404);
        }

        try {
            // Count files before deletion for response message
            $files = File::glob($folderPath.'/journey-*.json');
            $fileCount = count($files);

            // Delete the entire folder and its contents
            if (File::deleteDirectory($folderPath)) {
                $message = "Folder '{$folderName}' and {$fileCount} journey log(s) deleted successfully.";

                return response()->json(['success' => true, 'message' => $message]);
            } else {
                return response()->json(['success' => false, 'message' => 'Failed to delete folder.'], 500);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error occurred while deleting folder: '.$e->getMessage()], 500);
        }
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'journey_ids' => 'required|array',
            'journey_ids.*' => 'required|string',
        ]);

        $baseDir = $this->baseDir();
        $journeyIds = $request->input('journey_ids');
        $deleted = 0;
        $failed = 0;

        foreach ($journeyIds as $journeyData) {
            $parts = explode('|', $journeyData, 2);
            $journeyId = $parts[0];
            $folder = isset($parts[1]) && $parts[1] !== '' ? $parts[1] : null;

            $searchDir = $folder ? $baseDir.'/'.trim($folder, '/') : $baseDir;
            $filePath = $searchDir.'/journey-'.$journeyId.'.json';

            if (! File::exists($filePath)) {
                $found = $this->findJourneyFile($baseDir, $journeyId);
                if ($found) {
                    $filePath = $found;
                }
            }

            if (File::exists($filePath) && File::delete($filePath)) {
                $deleted++;
            } else {
                $failed++;
            }
        }

        $message = "Deleted {$deleted} journey log(s).";
        if ($failed > 0) {
            $message .= " Failed to delete {$failed} journey log(s).";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'deleted' => $deleted,
            'failed' => $failed,
        ]);
    }

    public function download(string $journeyId, Request $request)
    {
        $baseDir = $this->baseDir();
        $folder = $request->query('folder');

        $searchDir = $folder ? $baseDir.'/'.trim($folder, '/') : $baseDir;
        $filePath = $searchDir.'/journey-'.$journeyId.'.json';

        if (! File::exists($filePath)) {
            // Try to find it recursively
            $found = $this->findJourneyFile($baseDir, $journeyId);
            if (! $found) {
                abort(404, 'Journey log not found.');
            }
            $filePath = $found;
        }

        $fileName = 'journey-'.$journeyId.'.json';
        if ($folder) {
            $fileName = $folder.'_'.$fileName;
        }

        return response()->download($filePath, $fileName, [
            'Content-Type' => 'application/json',
        ]);
    }

    private function baseDir(): string
    {
        $storagePath = config('journeylog.storage_path', 'journeys');

        return storage_path('logs/'.$storagePath);
    }

    private function listFolders(string $baseDir): array
    {
        $folders = [];

        foreach (File::directories($baseDir) as $dir) {
            $folders[] = basename($dir);
        }

        sort($folders);

        return $folders;
    }

    private function listJourneys(string $directory, ?string $folder): Collection
    {
        if (! File::isDirectory($directory)) {
            return collect();
        }

        $files = File::glob($directory.'/journey-*.json');

        return collect($files)
            ->map(function (string $file) use ($folder) {
                $entries = json_decode(File::get($file), true) ?? [];

                preg_match('/journey-(.+)\.json$/', basename($file), $m);
                $journeyId = $m[1] ?? basename($file);

                $firstDatetime = $entries[0]['datetime'] ?? null;
                $lastDatetime = end($entries)['datetime'] ?? null;

                return [
                    'journey_id' => $journeyId,
                    'folder' => $folder,
                    'entry_count' => count($entries),
                    'first_datetime' => $firstDatetime,
                    'last_datetime' => $lastDatetime,
                    'file_size' => File::size($file),
                ];
            })
            ->sortByDesc('last_datetime')
            ->values();
    }

    private function findJourneyFile(string $baseDir, string $journeyId): ?string
    {
        $pattern = $baseDir.'/*/journey-'.$journeyId.'.json';
        $files = File::glob($pattern);

        // Also check root level
        $rootFile = $baseDir.'/journey-'.$journeyId.'.json';
        if (File::exists($rootFile)) {
            return $rootFile;
        }

        return $files[0] ?? null;
    }
}
