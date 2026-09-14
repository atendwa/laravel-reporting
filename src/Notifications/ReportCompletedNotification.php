<?php

declare(strict_types=1);

namespace Reporting\Notifications;

use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Reporting\Models\Report;
use Reporting\Models\ReportHistory;

final class ReportCompletedNotification
{
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

        $notification = FilamentNotification::make()
            ->title('Report Generated')
            ->body('Report: ' . $report->name . ' has been generated successfully.')
            ->success()
            ->actions(self::buildDownloadActions($reportHistory));

        $notification->sendToDatabase($user);
        $notification->broadcast($user);
    }

    /**
     * @return array<int, Action>
     */
    private static function buildDownloadActions(ReportHistory $reportHistory): array
    {
        $actions = [];

        if (filled($reportHistory->pdf_file_path)) {
            $actions[] = Action::make('download_pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-document')
                ->button()
                ->color('danger')
                ->url(route('reporting.download.pdf', $reportHistory))
                ->openUrlInNewTab();
        }

        if (filled($reportHistory->csv_file_path)) {
            $actions[] = Action::make('download_csv')
                ->label('Download CSV')
                ->icon('heroicon-o-table-cells')
                ->button()
                ->color('success')
                ->url(route('reporting.download.csv', $reportHistory))
                ->openUrlInNewTab();
        }

        return $actions;
    }
}
