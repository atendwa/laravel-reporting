<?php

declare(strict_types=1);

namespace Reporting\Consts;

use Reporting\Models\Report;
use Reporting\Models\ReportHistory;
use Reporting\Models\ReportingSchedule;
use Reporting\Models\ReportSchedule;

final class Reporting
{
    public const NAME = 'reporting';

    public const DISPLAY_NAME = 'Reporting';

    public const TEST_GROUP = 'reporting-plugin';

    public const MODELS = [
        ReportSchedule::class,
        ReportHistory::class,
        ReportingSchedule::class,
        Report::class,
    ];

    public static function commandName(string $command): string
    {
        return self::NAME . ':' . $command;
    }
}
