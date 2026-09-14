<?php

declare(strict_types=1);

namespace Reporting\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract for generators that need to scope their data and options
 * to the tenants (teams) that the requesting user has access to.
 *
 * Implement this interface and use the IsTenantAware trait to get the
 * full default implementation. Only override what your generator needs.
 */
interface TenantAwareInterface
{
    /**
     * Signals that this generator is tenant-aware.
     * The reporting system uses this to activate tenant-scoping behaviour.
     */
    public function isTenantAware(): bool;

    /**
     * Returns the modifier definition for the tenant multiselect field.
     * Include the result of this method in your getModifiers() array.
     *
     * @return array<string, mixed>
     */
    public function buildTenantModifier(): array;

    /**
     * Apply tenant scoping to an Eloquent query builder.
     *
     * - When `$modifiers['tenant_ids']` is null  → no restriction (cross-tenant access).
     * - When `$modifiers['tenant_ids']` is []    → no restriction (treat as unset).
     * - When `$modifiers['tenant_ids']` is [...] → whereIn('team_id', $tenantIds).
     *
     * Call this inside generate() immediately after ->withoutGlobalScopes() to
     * preserve the explicit control without relying on the global TeamScope.
     *
     * @param  Builder<Model>  $builder
     * @param  array<string, mixed>  $modifiers
     *
     * @return Builder<Model>
     */
    public function applyTenantScope(Builder $builder, array $modifiers): Builder;
}
