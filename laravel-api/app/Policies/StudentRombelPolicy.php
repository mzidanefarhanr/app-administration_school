<?php

namespace App\Policies;

use App\Models\StudentRombel;
use App\Models\User;

class StudentRombelPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAbility('students.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, StudentRombel $studentRombel): bool
    {
        return $user->hasAbility('students.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAbility('students.manage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StudentRombel $studentRombel): bool
    {
        return $user->hasAbility('students.manage');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StudentRombel $studentRombel): bool
    {
        return $user->hasAbility('students.manage');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, StudentRombel $studentRombel): bool
    {
        return $user->hasAbility('students.manage');
    }

    /**
     * Determine whether the user can permanently delete the model.
     * Force delete is strictly prohibited per repository rules.
     */
    public function forceDelete(User $user, StudentRombel $studentRombel): bool
    {
        return false;
    }
}
