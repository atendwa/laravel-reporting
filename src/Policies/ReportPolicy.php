<?php

declare(strict_types=1);

namespace Reporting\Policies;

use Reporting\Concerns\UsesPolicySetup;
use Reporting\Filament\Resources\ReportResource;

class ReportPolicy
{
    use UsesPolicySetup;

    protected string $resource = ReportResource::class;
}
