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

    /**
     * Preview inline memakai izin yang sama dengan download (DEC-38 D2).
     */
    public function preview(User $user, ArchiveDocument $archiveDocument): bool
    {
        return $this->download($user, $archiveDocument);
    }

    /**
     * Riwayat akses dibaca pemegang audit.view selain archives.view + domain
     * (DEC-38 D3); tidak ada ability baru.
     */
    public function accessLogs(User $user, ArchiveDocument $archiveDocument): bool
    {
        return $user->hasAbility((string) config('archives.access_log.ability'))
            && $this->view($user, $archiveDocument);
    }

    /**
     * Arsip/restore memakai archives.manage + ability domain (DEC-38 D5);
     * delete/forceDelete tetap tidak pernah diizinkan.
     */
    public function archive(User $user, ArchiveDocument $archiveDocument): bool
    {
        return $this->update($user, $archiveDocument);
    }

    public function unarchive(User $user, ArchiveDocument $archiveDocument): bool
    {
        return $this->update($user, $archiveDocument);
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
