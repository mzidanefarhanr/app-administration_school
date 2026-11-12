<?php

namespace App\Policies;

use App\Models\Employment;
use App\Models\User;

class EmploymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAbility('master.view');
    }

    public function view(User $user, Employment $employment): bool
    {
        return $user->hasAbility('master.view');
    }

    public function create(User $user): bool
    {
        return $user->hasAbility('master.manage');
    }

    public function update(User $user, Employment $employment): bool
    {
        return $user->hasAbility('master.manage');
    }

    public function delete(User $user, Employment $employment): bool
    {
        return $user->hasAbility('master.manage');
    }

    public function restore(User $user, Employment $employment): bool
    {
        return false;
    }

    public function forceDelete(User $user, Employment $employment): bool
    {
        return false;
    }
}
