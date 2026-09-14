<?php

declare(strict_types=1);

namespace Reporting\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Reporting\Models\Report;
use Reporting\Models\ReportHistory;
use Throwable;

final class ReportFailed
{
    use Dispatchable;

    public function __construct(
        public readonly Report $report,
        public readonly ReportHistory $history,
        public readonly Throwable $exception,
    ) {}
}
