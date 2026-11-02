<?php

namespace App\Policies;

use App\Models\ArchiveDocument;
use App\Models\User;

class ArchiveDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAbility('archives.view');
    }

    public function view(User $user, ArchiveDocument $archiveDocument): bool
    {
        return $user->hasAbility('archives.view')
            && $this->domainAllows($user, $archiveDocument->subject_type);
    }

    public function create(User $user): bool
    {
        return $user->hasAbility('archives.manage');
    }

    public function update(User $user, ArchiveDocument $archiveDocument): bool
    {
        return $user->hasAbility('archives.manage')
            && $this->domainAllows($user, $archiveDocument->subject_type);
    }

    public function addVersion(User $user, ArchiveDocument $archiveDocument): bool
    {
        return $user->hasAbility('archives.manage')
            && $this->domainAllows($user, $archiveDocument->subject_type);
    }

    public function download(User $user, ArchiveDocument $archiveDocument): bool
    {
        return $this->view($user, $archiveDocument);
    }

    public function delete(User $user, ArchiveDocument $archiveDocument): bool
    {
        return false;
    }

    public function restore(User $user, ArchiveDocument $archiveDocument): bool
    {
        return false;
    }

    public function forceDelete(User $user, ArchiveDocument $archiveDocument): bool
    {
        return false;
    }

    private function domainAllows(User $user, ?string $subjectType): bool
    {
        if ($subjectType === null) {
            return true;
        }

        $ability = config('archives.subject_types.'.$subjectType.'.ability');

        return is_string($ability) && $user->hasAbility($ability);
    }
}
