<?php

declare(strict_types=1);

namespace Reporting\Policies;

use Reporting\Concerns\UsesPolicySetup;
use Reporting\Filament\Resources\ReportingScheduleResource;

class ReportingSchedulePolicy
{
    use UsesPolicySetup;

    protected string $resource = ReportingScheduleResource::class;
}
