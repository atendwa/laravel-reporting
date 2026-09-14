<?php

declare(strict_types=1);

namespace Reporting\Policies;

use BetaFilament\Concerns\UsesFilamentPolicySetup;
use Reporting\Filament\Resources\ReportHistoryResource;

class ReportHistoryPolicy
{
    use UsesFilamentPolicySetup;

    protected string $resource = ReportHistoryResource::class;
}
