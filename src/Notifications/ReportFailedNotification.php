<?php

declare(strict_types=1);

namespace Reporting\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Reporting\Models\Report;
use Reporting\Models\ReportHistory;

final class ReportFailedNotification
{
    /**
     * Send a report failed notification to the triggering user.
     */
    public static function send(Report $report, ReportHistory $reportHistory): void
    {
        if ($reportHistory->triggered_by_user_id === null) {
            return;
        }

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $userModel */
        $userModel = config('auth.providers.users.model');

        $user = $userModel::query()->select(['id', 'name'])->find($reportHistory->triggered_by_user_id);

        if (blank($user)) {
            return;
        }

        $errorMsg = $reportHistory->error_message ? ': ' . $reportHistory->error_message : '.';

        $notification = FilamentNotification::make()
            ->body('Report:' . $report->name . ' failed' . $errorMsg)
            ->title('Report Generation Failed')
            ->danger();

        $notification->sendToDatabase($user);
        $notification->broadcast($user);
    }
}
