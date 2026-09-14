<?php

declare(strict_types=1);

namespace Reporting\Consts;

final class ReportSource
{
    public const SOURCE_SCHEDULED = 'scheduled';

    public const SOURCE_MANUAL = 'manual';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::SOURCE_SCHEDULED => 'Scheduled',
            self::SOURCE_MANUAL => 'Manual',
        ];
    }
}
