<?php

declare(strict_types=1);

namespace Reporting\Policies;

use BetaFilament\Concerns\UsesFilamentPolicySetup;
use Reporting\Filament\Resources\ReportingScheduleResource;

class ReportingSchedulePolicy
{
    use UsesFilamentPolicySetup;

    protected string $resource = ReportingScheduleResource::class;
}
