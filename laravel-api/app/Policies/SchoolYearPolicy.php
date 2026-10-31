<?php

namespace App\Policies;

use App\Models\SchoolYear;
use App\Models\User;

class SchoolYearPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAbility('master.view');
    }

    public function view(User $user, SchoolYear $schoolYear): bool
    {
        return $user->hasAbility('master.view');
    }

    public function create(User $user): bool
    {
        return $user->hasAbility('master.manage');
    }

    public function update(User $user, SchoolYear $schoolYear): bool
    {
        return $user->hasAbility('master.manage');
    }

    public function delete(User $user, SchoolYear $schoolYear): bool
    {
        return $user->hasAbility('master.manage');
    }

    public function restore(User $user, SchoolYear $schoolYear): bool
    {
        return false;
    }

    public function lock(User $user, SchoolYear $schoolYear): bool
    {
        return $user->hasAbility('master.manage');
    }

    public function unlock(User $user, SchoolYear $schoolYear): bool
    {
        return $user->hasAbility('master.manage');
    }

    public function forceDelete(User $user, SchoolYear $schoolYear): bool
    {
        return false;
    }
}
