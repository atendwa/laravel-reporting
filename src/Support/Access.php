<?php

declare(strict_types=1);

namespace Reporting\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Reporting\Models\Report;

final class Access
{
    public static function isAdministrator(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return method_exists($user, 'hasRole') && (bool) $user->hasRole('super_admin');
    }

    /**
     * @param  class-string  $model
     */
    public static function allows(string $ability, string $model): bool
    {
        return self::isAdministrator() || (auth()->check() && Gate::allows($ability, $model) && self::hasReports());
    }

    public static function hasReports(): bool
    {
        $user = auth()->user();

        if ($user === null || ! method_exists($user, 'roles')) {
            return false;
        }

        $roles = $user->roles()->pluck('roles.id');

        return Report::query()->withoutGlobalScopes()->whereHas('roles', fn (Builder $query) => $query->whereIn('roles.id', $roles))->exists();
    }
}
