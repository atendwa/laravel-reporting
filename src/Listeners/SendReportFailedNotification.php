<?php

declare(strict_types=1);

namespace Reporting\Listeners;

use Reporting\Events\ReportFailed;
use Reporting\Notifications\ReportFailedNotification;

final class SendReportFailedNotification
{
    public function handle(ReportFailed $reportFailed): void
    {
        ReportFailedNotification::send($reportFailed->report, $reportFailed->history);
    }
}
