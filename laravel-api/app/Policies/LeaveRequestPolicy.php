<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyAbility(['leaves.view', 'leaves.request', 'leaves.manage']);
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($user->hasAnyAbility(['leaves.view', 'leaves.manage'])) {
            return true;
        }

        if (! $user->hasAbility('leaves.request')) {
            return false;
        }

        if ((int) $leaveRequest->requested_by === $user->id) {
            return true;
        }

        return $leaveRequest->subject_type === LeaveRequest::SUBJECT_EMPLOYEE
            && $leaveRequest->employee !== null
            && $leaveRequest->employee->user_id !== null
            && (int) $leaveRequest->employee->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyAbility(['leaves.manage', 'leaves.request']);
    }

    public function update(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->modifies($user, $leaveRequest);
    }

    public function submit(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->modifies($user, $leaveRequest);
    }

    public function cancel(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->modifies($user, $leaveRequest);
    }

    public function uploadAttachment(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->modifies($user, $leaveRequest);
    }

    public function deleteAttachment(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->modifies($user, $leaveRequest);
    }

    public function delete(User $user, LeaveRequest $leaveRequest): bool
    {
        return false;
    }

    public function restore(User $user, LeaveRequest $leaveRequest): bool
    {
        return false;
    }

    public function forceDelete(User $user, LeaveRequest $leaveRequest): bool
    {
        return false;
    }

    private function modifies(User $user, LeaveRequest $leaveRequest): bool
    {
        return (int) $leaveRequest->requested_by === $user->id
            || $user->hasAbility('leaves.manage');
    }
}
