<?php

declare(strict_types=1);

namespace Reporting\Observers;

use Illuminate\Support\Facades\Storage;
use Reporting\Models\ReportHistory;

final class ReportHistoryObserver
{
    public function deleting(ReportHistory $reportHistory): void
    {
        /** @var string $disk */
        $disk = config('reporting.disk', 'local');

        if (filled($reportHistory->csv_file_path) && Storage::disk($disk)->exists($reportHistory->csv_file_path)) {
            Storage::disk($disk)->delete($reportHistory->csv_file_path);
        }

        if (filled($reportHistory->pdf_file_path) && Storage::disk($disk)->exists($reportHistory->pdf_file_path)) {
            Storage::disk($disk)->delete($reportHistory->pdf_file_path);
        }
    }
}
