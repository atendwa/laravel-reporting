<?php

declare(strict_types=1);

namespace Reporting\Consts;

use Reporting\Models\Report;
use Reporting\Models\ReportHistory;
use Reporting\Models\ReportingSchedule;
use Reporting\Models\ReportSchedule;

final class Reporting
{
    public const string NAME = 'reporting';

    public const string DISPLAY_NAME = 'Reporting';

    public const string TEST_GROUP = 'reporting-plugin';

    public const array MODELS = [
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
