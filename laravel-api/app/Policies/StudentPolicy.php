<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    /**
     * Determine whether the user can view any students.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAbility('students.view');
    }

    /**
     * Determine whether the user can view the student.
     */
    public function view(User $user, Student $student): bool
    {
        return $user->hasAbility('students.view');
    }

    /**
     * Determine whether the user can create students.
     */
    public function create(User $user): bool
    {
        return $user->hasAbility('students.manage');
    }

    /**
     * Determine whether the user can update the student.
     */
    public function update(User $user, Student $student): bool
    {
        return $user->hasAbility('students.manage');
    }

    /**
     * Determine whether the user can delete (archive) the student.
     */
    public function delete(User $user, Student $student): bool
    {
        return $user->hasAbility('students.manage');
    }

    /**
     * Determine whether the user can restore the student.
     */
    public function restore(User $user, Student $student): bool
    {
        return $user->hasAbility('students.manage');
    }

    /**
     * Determine whether the user can permanently delete the student.
     * Force delete is strictly prohibited per repository rules.
     */
    public function forceDelete(User $user, Student $student): bool
    {
        return false;
    }
}
