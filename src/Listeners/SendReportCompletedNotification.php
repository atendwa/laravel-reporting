<?php

declare(strict_types=1);

namespace Reporting\Listeners;

use Reporting\Events\ReportGenerated;
use Reporting\Notifications\ReportCompletedNotification;

final class SendReportCompletedNotification
{
    public function handle(ReportGenerated $reportGenerated): void
    {
        ReportCompletedNotification::send($reportGenerated->report, $reportGenerated->history);
    }
}
