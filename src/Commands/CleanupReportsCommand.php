<?php

declare(strict_types=1);

namespace Reporting\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use Reporting\Consts\Reporting;
use Reporting\Models\ReportHistory;

final class CleanupReportsCommand extends Command
{
    protected $signature = Reporting::NAME . ':prune {--days=90 : Number of days to retain}';

    protected $description = 'Delete old report histories and their files';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $result = spin(fn (): array => $this->performCleanup($days), 'Cleaning up old reports...');

        info(sprintf('Deleted %d report histories older than ', $result['count']) . $days . ' days.');
        info(sprintf('Freed %d bytes of storage.', $result['size']));

        return self::SUCCESS;
    }

    /**
     * @return array{count: int, size: int}
     */
    private function performCleanup(int $days): array
    {
        $cutoff = now()->subDays($days);
        $count = 0;
        $size = 0;
        $disk = (string) config('reporting.disk', 'local');

        ReportHistory::query()
            ->select(['id', 'csv_file_path', 'pdf_file_path'])
            ->where('created_at', '<', $cutoff)
            ->chunkById(100, function ($histories) use (&$count, &$size, $disk): void {
                $histories->each(function (ReportHistory $reportHistory) use (&$count, &$size, $disk): void {
                    $size += $this->deleteFiles($reportHistory, $disk);
                    $reportHistory->delete();
                    ++$count;
                });
            });

        return ['count' => $count, 'size' => $size];
    }

    private function deleteFiles(ReportHistory $reportHistory, string $disk): int
    {
        $freed = 0;

        if ($reportHistory->csv_file_path && Storage::disk($disk)->exists($reportHistory->csv_file_path)) {
            $freed += Storage::disk($disk)->size($reportHistory->csv_file_path);
            Storage::disk($disk)->delete($reportHistory->csv_file_path);
        }

        if ($reportHistory->pdf_file_path && Storage::disk($disk)->exists($reportHistory->pdf_file_path)) {
            $freed += Storage::disk($disk)->size($reportHistory->pdf_file_path);
            Storage::disk($disk)->delete($reportHistory->pdf_file_path);
        }

        return $freed;
    }
}
