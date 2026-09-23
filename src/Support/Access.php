<?php

declare(strict_types=1);

namespace Reporting\Support;

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
}
