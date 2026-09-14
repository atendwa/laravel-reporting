<?php

declare(strict_types=1);

namespace Reporting\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Reporting\Consts\ReportSource;
use Reporting\Models\Report;
use Reporting\Services\ReportExecutor;
use Throwable;

final class GenerateReportJob implements //    ShouldBeUnique,
    ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 120, 240];

    /**
     * @param  array<string, mixed>  $modifiers
     */
    public function __construct(
        public readonly Report $report,
        public readonly array $modifiers = [],
        public readonly ?int $userId = null,
        public readonly string $source = ReportSource::SOURCE_MANUAL,
    ) {
        $queue = (string) config('reporting.queue', 'default');
        //        $this->onQueue($queue);
        //        when(filled($queue), fn () => $this->onQueue($queue));
    }

    /**
     * @throws Throwable
     */
    public function handle(ReportExecutor $reportExecutor): void
    {
        $reportExecutor->execute($this->report, $this->modifiers, $this->userId, $this->source);
    }

    //    public function uniqueId(): string
    //    {
    //        return (string) $this->report->id;
    //    }
}
