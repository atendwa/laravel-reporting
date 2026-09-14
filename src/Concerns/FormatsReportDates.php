<?php

declare(strict_types=1);

namespace Reporting\Concerns;

use Illuminate\Support\Carbon;

trait FormatsReportDates
{
    private function formatDate(mixed $value): string
    {
        if (blank($value)) {
            return '—';
        }

        return Carbon::parse((string) $value)->toFormattedDateString();
    }
}
