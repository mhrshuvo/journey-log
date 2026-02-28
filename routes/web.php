<?php

use Illuminate\Support\Facades\Route;
use mhrshuvo\JourneyLog\Http\Controllers\JourneyLogSummaryController;

Route::get('/', [JourneyLogSummaryController::class, 'index'])->name('journeylog.index');
Route::get('/{journeyId}', [JourneyLogSummaryController::class, 'show'])->name('journeylog.show');
Route::delete('/delete/{journeyId}', [JourneyLogSummaryController::class, 'delete'])->name('journeylog.delete');
Route::delete('/delete-folder/{folderName}', [JourneyLogSummaryController::class, 'deleteFolder'])->name('journeylog.deleteFolder');
Route::delete('/bulk-delete', [JourneyLogSummaryController::class, 'bulkDelete'])->name('journeylog.bulk-delete');
Route::get('/download/{journeyId}', [JourneyLogSummaryController::class, 'download'])->name('journeylog.download');
