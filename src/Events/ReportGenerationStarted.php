<?php

declare(strict_types=1);

namespace Reporting\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Reporting\Models\Report;
use Reporting\Models\ReportHistory;

final readonly class ReportGenerationStarted
{
    use Dispatchable;

    public function __construct(
        public Report $report,
        public ReportHistory $history,
    ) {}
}
