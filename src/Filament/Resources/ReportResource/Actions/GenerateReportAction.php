<?php

declare(strict_types=1);

namespace Reporting\Filament\Resources\ReportResource\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Support\Exceptions\Cancel;
use Reporting\Contracts\TenantAwareInterface;
use Reporting\Jobs\GenerateReportJob;
use Reporting\Models\Report;
use Reporting\Services\GeneratorDiscovery;
use Reporting\Utilities\CooldownChecker;

final class GenerateReportAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->action(fn (Report $report, array $data): mixed => $this->handleGeneration($report, $data))
            ->schema(fn (Report $report): array => $this->buildModifierForm($report))
            ->before(fn (Report $report) => $this->checkCooldown($report))
            ->modalSubmitActionLabel('Generate')
            ->modalHeading('Generate Report')
            ->requiresConfirmation(condition: false)
            ->icon('heroicon-o-play')
            ->label('Generate')
            ->color('primary');
    }

    public static function getDefaultName(): ?string
    {
        return 'generate';
    }

    /**
     * Build form fields from generator modifiers.
     *
     * @return array<int, Component>
     */
    private function buildModifierForm(Report $report): array
    {
        $generatorDiscovery = new GeneratorDiscovery;

        if (! $generatorDiscovery->isValidGenerator($report->generator_class)) {
            return [];
        }

        $generator = $generatorDiscovery->resolve($report->generator_class);
        $modifiers = $generator->getModifiers();

        return collect($modifiers)
            ->map(fn (array $modifier): DatePicker|Select|TextInput|null => $this->buildField($modifier))
            ->filter()
            ->toArray();
    }

    /**
     * Build a Filament form field from a modifier definition.
     *
     * @param  array<string, mixed>  $modifier
     */
    private function buildField(array $modifier): DatePicker|Select|TextInput|null
    {
        $type = $modifier['type'] ?? 'text';

        return match ($type) {
            'date_range', 'date' => DatePicker::make($modifier['name'])
                ->label($modifier['label'] ?? $modifier['name'])
                ->required($modifier['required'] ?? false)
                ->default($modifier['default'] ?? null),

            'select' => Select::make($modifier['name'])
                ->label($modifier['label'] ?? $modifier['name'])
                ->options($modifier['options'] ?? [])
                ->required($modifier['required'] ?? false)
                ->default($modifier['default'] ?? null),

            'multiselect' => Select::make($modifier['name'])
                ->label($modifier['label'] ?? $modifier['name'])
                ->options($modifier['options'] ?? [])
                ->multiple()
                ->required($modifier['required'] ?? false)
                ->default($modifier['default'] ?? null),

            'number' => TextInput::make($modifier['name'])
                ->label($modifier['label'] ?? $modifier['name'])
                ->numeric()
                ->required($modifier['required'] ?? false)
                ->default($modifier['default'] ?? null),

            default => TextInput::make($modifier['name'])
                ->label($modifier['label'] ?? $modifier['name'])
                ->required($modifier['required'] ?? false)
                ->default($modifier['default'] ?? null),
        };
    }

    /**
     * @throws Cancel
     */
    private function checkCooldown(Report $report): void
    {
        if (! $report->is_heavy) {
            return;
        }

        $cooldownChecker = new CooldownChecker;

        if (! $cooldownChecker->isOnCooldown($report)) {
            return;
        }

        if (auth()->user()?->can('force_generate_report')) {
            return;
        }

        $recentHistory = $cooldownChecker->findRecentHistory($report);
        $completedAt = $recentHistory?->completed_at?->diffForHumans() ?? 'recently';

        Notification::make()
            ->title('Report On Cooldown')
            ->body(sprintf(
                'This report was last generated %s. Please use the cached result or try again later.',
                $completedAt
            ))
            ->warning()
            ->send();

        $this->cancel();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleGeneration(Report $report, array $data): mixed
    {
        $generator = (new GeneratorDiscovery)->resolve($report->generator_class);

        if ($generator instanceof TenantAwareInterface) {
            $data = $this->normalizeTenantModifiers($report, $data);
        }

        GenerateReportJob::dispatch($report, $data, auth()->id());

        Notification::make()
            ->title('Report Queued')
            ->body(sprintf('Report "%s" has been queued for generation.', $report->name))
            ->success()
            ->send();

        return null;
    }

    /**
     * Resolve empty tenant_ids before dispatch so the queued job always
     * receives an explicit list (or null for unrestricted access).
     *
     *  - User selected specific teams → keep as-is.
     *  - User left blank + has cross-tenant access → null (no whereIn applied).
     *  - User left blank + no cross-tenant access → user's own team IDs.
     *
     * @param  array<string, mixed>  $data
     *
     * @return array<string, mixed>
     */
    private function normalizeTenantModifiers(Report $report, array $data): array
    {
        $selectedTenantIds = $data['tenant_ids'] ?? null;

        if (! empty($selectedTenantIds)) {
            return $data;
        }

        if ($this->userCanAccessAllTenants($report)) {
            $data['tenant_ids'] = null;
        } else {
            $data['tenant_ids'] = auth()->user()
                ?->teams()
                ->where('is_default', false)
                ->pluck('id')
                ->toArray() ?? [];
        }

        return $data;
    }

    /**
     * Check whether the current user's role grants cross-tenant access
     * (`can_access_all_tenants = true`) for the given report.
     */
    private function userCanAccessAllTenants(Report $report): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        $userRoleIds = $user->roles->pluck('id');

        return $report->roles()
            ->whereIn('roles.id', $userRoleIds)
            ->wherePivot('can_access_all_tenants', true)
            ->exists();
    }
}
