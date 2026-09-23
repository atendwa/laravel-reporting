<?php

declare(strict_types=1);

namespace Support\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasRoles;

    protected $guarded = ['id'];

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
