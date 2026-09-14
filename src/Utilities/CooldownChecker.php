<?php

declare(strict_types=1);

namespace Reporting\Utilities;

use Reporting\Consts\ReportStatus;
use Reporting\Models\Report;
use Reporting\Models\ReportHistory;

final class CooldownChecker
{
    /**
     * Get the cooldown duration in minutes for a report.
     */
    public function getCooldownMinutes(Report $report): int
    {
        return $report->cooldown_minutes ?? (int) config('reporting.default_cooldown_minutes', 30);
    }

    /**
     * Check if a report is currently on cooldown.
     */
    public function isOnCooldown(Report $report): bool
    {
        return $this->findRecentHistory($report) instanceof ReportHistory;
    }

    /**
     * Find the most recent completed history within the cooldown window.
     */
    public function findRecentHistory(Report $report): ?ReportHistory
    {
        $cooldownMinutes = $this->getCooldownMinutes($report);

        /** @var ReportHistory|null */
        return $report->histories()
            ->where('completed_at', '>=', now()->subMinutes($cooldownMinutes))
            ->where('status', ReportStatus::STATUS_COMPLETED)
            ->latest('completed_at')
            ->first();
    }
}
