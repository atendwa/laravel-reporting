<?php

declare(strict_types=1);

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Reporting\Models\ReportHistory;

Route::middleware(['web', 'auth'])
    ->get('reporting/preview/{reportHistory}', function (ReportHistory $reportHistory): ResponseFactory|Response {
        $disk = config('reporting.disk', 'local');

        throw_if(! is_string($disk), 'reporting.disk configuration must be a string.');

        $path = $reportHistory->pdf_file_path;

        if (blank($path) || ! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return response(Storage::disk($disk)->get($path), 200, [
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
            'Content-Type' => 'application/pdf',
        ]);
    })->name('reporting.preview');

Route::middleware(['web', 'auth'])
    ->get('reporting/download/{reportHistory}/pdf', function (ReportHistory $reportHistory): ResponseFactory|Response {
        $disk = config('reporting.disk', 'local');

        throw_if(! is_string($disk), 'reporting.disk configuration must be a string.');

        $path = $reportHistory->pdf_file_path;

        if (blank($path) || ! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return response(Storage::disk($disk)->get($path), 200, [
            'Content-Disposition' => 'attachment; filename="' . basename($path) . '"',
            'Content-Type' => 'application/pdf',
        ]);
    })->name('reporting.download.pdf');

Route::middleware(['web', 'auth'])
    ->get('reporting/download/{reportHistory}/csv', function (ReportHistory $reportHistory): ResponseFactory|Response {
        $disk = config('reporting.disk', 'local');

        throw_if(! is_string($disk), 'reporting.disk configuration must be a string.');

        $path = $reportHistory->csv_file_path;

        if (blank($path) || ! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return response(Storage::disk($disk)->get($path), 200, [
            'Content-Disposition' => 'attachment; filename="' . basename($path) . '"',
            'Content-Type' => 'text/csv',
        ]);
    })->name('reporting.download.csv');
