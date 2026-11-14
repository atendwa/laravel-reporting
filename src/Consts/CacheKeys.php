<?php

declare(strict_types=1);

namespace Reporting\Consts;

final class CacheKeys
{
    public const string GENERATORS = Reporting::NAME . '.generators';

    public const string SCHEDULED_REPORTS = Reporting::NAME . '.scheduled_reports';
}
