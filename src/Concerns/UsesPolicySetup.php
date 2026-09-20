<?php

declare(strict_types=1);

namespace Reporting\Concerns;

use Illuminate\Database\Eloquent\Model;

trait UsesPolicySetup
{
    public function viewAny(Model $user): bool
    {
        return $user->can($this->permission('ViewAny'));
    }

    public function view(Model $user, Model $model): bool
    {
        return $user->can($this->permission('View')) && filled($model->getKey());
    }

    public function create(Model $user): bool
    {
        return $user->can($this->permission('Create'));
    }

    public function update(Model $user, Model $model): bool
    {
        return $user->can($this->permission('Update'));
    }

    public function delete(Model $user, Model $model): bool
    {
        return $user->can($this->permission('Delete'));
    }

    public function deleteAny(Model $user): bool
    {
        return $user->can($this->permission('DeleteAny'));
    }

    public function forceDelete(Model $user, Model $model): bool
    {
        return $user->can($this->permission('ForceDelete'));
    }

    public function forceDeleteAny(Model $user): bool
    {
        return $user->can($this->permission('ForceDeleteAny'));
    }

    public function restore(Model $user, Model $model): bool
    {
        return $user->can($this->permission('Restore'));
    }

    public function restoreAny(Model $user): bool
    {
        return $user->can($this->permission('RestoreAny'));
    }

    public function replicate(Model $user, Model $model): bool
    {
        return $user->can($this->permission('Replicate'));
    }

    public function custom(Model $user, string $ability): bool
    {
        return $user->can($this->permission($ability));
    }

    protected function permission(string $prefix): string
    {
        $resource = str_replace('Resource', '', class_basename($this->resource));

        return $prefix . config('filament-shield.permissions.separator', ':') . $resource;
    }
}
