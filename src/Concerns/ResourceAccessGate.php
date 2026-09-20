<?php

declare(strict_types=1);

namespace Reporting\Concerns;

trait ResourceAccessGate
{
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
