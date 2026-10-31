<?php

namespace App\Policies;

use App\Models\SchoolRombel;
use App\Models\User;

class SchoolRombelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAbility('students.view');
    }

    public function view(User $user, SchoolRombel $schoolRombel): bool
    {
        return $user->hasAbility('students.view');
    }

    public function create(User $user): bool
    {
        return $user->hasAbility('students.manage');
    }

    public function update(User $user, SchoolRombel $schoolRombel): bool
    {
        return $user->hasAbility('students.manage');
    }

    public function delete(User $user, SchoolRombel $schoolRombel): bool
    {
        return $user->hasAbility('students.manage');
    }

    public function restore(User $user, SchoolRombel $schoolRombel): bool
    {
        return false;
    }

    public function forceDelete(User $user, SchoolRombel $schoolRombel): bool
    {
        return false;
    }
}
