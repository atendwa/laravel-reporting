<?php

declare(strict_types=1);

namespace Reporting\Policies;

use BetaFilament\Concerns\UsesFilamentPolicySetup;
use Reporting\Filament\Resources\ReportResource;

class ReportPolicy
{
    use UsesFilamentPolicySetup;

    protected string $resource = ReportResource::class;
}
