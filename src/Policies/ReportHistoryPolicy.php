<?php

declare(strict_types=1);

namespace Reporting\Policies;

use Reporting\Concerns\UsesPolicySetup;
use Reporting\Filament\Resources\ReportHistoryResource;

class ReportHistoryPolicy
{
    use UsesPolicySetup;

    protected string $resource = ReportHistoryResource::class;
}
