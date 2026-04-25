<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff']);
    }

    public function view(User $user, User $model): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('staff')) {
            if ($model->hasAnyRole(['admin', 'staff'])) {
                return $user->id === $model->id;
            }

            return (int) $model->created_by === (int) $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff']);
    }

    public function update(User $user, User $model): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('staff')) {
            if ($model->hasAnyRole(['admin', 'staff'])) {
                return false;
            }

            return (int) $model->created_by === (int) $user->id;
        }

        return false;
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return ! $model->hasRole('admin') || $model->id !== $user->id;
        }

        if ($user->hasRole('staff')) {
            if ($model->hasAnyRole(['admin', 'staff'])) {
                return false;
            }

            return (int) $model->created_by === (int) $user->id;
        }

        return false;
    }

    /**
     * Lock or unlock another account. Self-locking is forbidden.
     */
    public function lock(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if ($model->hasRole('admin')) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('staff')) {
            if ($model->hasAnyRole(['admin', 'staff'])) {
                return false;
            }

            return (int) $model->created_by === (int) $user->id;
        }

        return false;
    }

    /**
     * Reveal the encrypted plain-text password to admins/staff
     * for the same set of users they are allowed to update.
     */
    public function viewPassword(User $user, User $model): bool
    {
        return $this->update($user, $model);
    }

    public function restore(User $user, User $model): bool
    {
        return $this->delete($user, $model);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasRole('admin');
    }
}
