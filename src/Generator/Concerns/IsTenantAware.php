<?php

declare(strict_types=1);

namespace Reporting\Generator\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Reporting\Models\Report;
use RuntimeException;

/**
 * Provides tenant-scoping behaviour for report generators.
 *
 * Usage in a generator:
 *
 *   final class MyReportGenerator implements GeneratorInterface, TenantAwareInterface
 *   {
 *       use IsTenantAware;
 *
 *       public function getModifiers(): array
 *       {
 *           return [
 *               $this->buildTenantModifier(),   // ← prepend tenant selector
 *               // ... other modifiers
 *           ];
 *       }
 *
 *       public function generate(array $modifiers): Collection
 *       {
 *           $query = MyModel::query()
 *               ->withoutGlobalScopes()
 *               ->where('is_cancelled', false);
 *
 *           $this->applyTenantScope($query, $modifiers);   // ← apply team_id filter
 *
 *           // Scope other modifiers to user's teams too:
 *           $options = $this->scopeOptionsToUserTeams(
 *               SomeRelatedModel::query()->withoutGlobalScopes()
 *           )->pluck('name', 'id')->toArray();
 *
 *           return $query->get()->map(...);
 *       }
 *   }
 *
 * The column used for filtering defaults to 'team_id'. Override getTenantColumn()
 * if your model uses a different column name.
 */
trait IsTenantAware
{
    /**
     * Signals to the reporting system that this generator is tenant-aware.
     */
    public function isTenantAware(): bool
    {
        return true;
    }

    /**
     * Build the modifier definition for the tenant multiselect form field.
     *
     * Include the result in your getModifiers() array — typically as the first
     * element so the tenant selector appears at the top of the form:
     *
     *   return [$this->buildTenantModifier(), ...otherModifiers];
     *
     * Options are automatically scoped to the current user's teams, unless the
     * user's role has `can_access_all_tenants = true` for this report, in which
     * case all teams are offered.
     *
     * @return array<string, mixed>
     */
    public function buildTenantModifier(): array
    {
        return [
            'name' => 'tenant_ids',
            'label' => 'Teams',
            'type' => 'multiselect',
            'required' => false,
            'default' => null,
            'validation' => 'nullable|array',
            'options' => $this->buildTenantOptions(),
        ];
    }

    /**
     * Apply tenant scoping to a query builder.
     *
     * Call this inside generate() after ->withoutGlobalScopes() to manually
     * enforce tenant boundaries without relying on the global TeamScope.
     *
     * Behaviour:
     *   - `tenant_ids` is null or []  → no restriction (cross-tenant / all tenants).
     *   - `tenant_ids` is [...]       → whereIn('team_id', $tenantIds).
     *
     * @param  Builder<Model>  $builder
     * @param  array<string, mixed>  $modifiers
     *
     * @return Builder<Model>
     */
    public function applyTenantScope(Builder $builder, array $modifiers): Builder
    {
        $tenantIds = $modifiers['tenant_ids'] ?? null;

        if (empty($tenantIds)) {
            return $builder;
        }

        return $builder->whereIn($this->getTenantColumn(), $tenantIds);
    }

    /**
     * The database column that identifies the owning team / tenant.
     * Override in your generator if the column name differs.
     */
    protected function getTenantColumn(): string
    {
        return 'team_id';
    }

    /**
     * Scope an options query to the current user's teams.
     *
     * Use this in getModifiers() when building option lists for select fields
     * so that users only see records belonging to their accessible teams:
     *
     *   'options' => $this->scopeOptionsToUserTeams(
     *       Outlet::query()->withoutGlobalScopes()
     *   )->pluck('name', 'id')->toArray(),
     *
     * @param  Builder<Model>  $builder
     *
     * @return Builder<Model>
     */
    protected function scopeOptionsToUserTeams(Builder $builder): Builder
    {
        $teamIds = $this->resolveUserTeamIds();

        if (empty($teamIds)) {
            return $builder;
        }

        return $builder->whereIn($this->getTenantColumn(), $teamIds);
    }

    /**
     * Build the options array for the tenant multiselect field.
     *
     * If the user's role grants `can_access_all_tenants` for this report,
     * all non-default teams are offered. Otherwise, only the user's own teams.
     *
     * @return array<int|string, string>
     */
    private function buildTenantOptions(): array
    {
        if ($this->currentUserCanAccessAllTenants()) {
            $teamModel = $this->teamModelClass();

            return $teamModel::query()
                ->where('is_default', false)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        }

        return $this->resolveUserTeams()
            ->sortBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Determine whether the current user's role grants cross-tenant access
     * (`can_access_all_tenants = true`) for this specific report.
     *
     * Super admins always have unrestricted access.
     */
    private function currentUserCanAccessAllTenants(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        $report = Report::query()
            ->where('generator_class', static::class)
            ->first();

        if ($report === null) {
            return false;
        }

        $userRoleIds = $user->roles->pluck('id');

        return $report->roles()
            ->whereIn('roles.id', $userRoleIds)
            ->wherePivot('can_access_all_tenants', true)
            ->exists();
    }

    /**
     * Get the IDs of the current user's accessible (non-default) teams.
     *
     * @return array<int>
     */
    private function resolveUserTeamIds(): array
    {
        return $this->resolveUserTeams()->pluck('id')->toArray();
    }

    /**
     * Get the current user's accessible (non-default) teams as a Collection.
     *
     * @return Collection<int, Model>
     */
    private function resolveUserTeams(): Collection
    {
        $user = auth()->user();

        if ($user === null) {
            return collect();
        }

        return $user->teams()
            ->where('is_default', false)
            ->get();
    }

    /**
     * @return class-string<Model>
     */
    private function teamModelClass(): string
    {
        $teamModel = config('reporting.team_model');

        if (! is_string($teamModel) || $teamModel === '') {
            throw new RuntimeException(
                'The "reporting.team_model" config value must be set to use IsTenantAware.'
            );
        }

        return $teamModel;
    }
}
