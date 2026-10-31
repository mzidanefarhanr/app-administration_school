<?php

namespace App\Services\DataQuality;

/**
 * Kunci temuan deterministik tanpa nilai pribadi (DEC-28).
 *
 * Versi record dan tahun ajaran ikut di-hash sehingga review lama tidak
 * menyembunyikan data yang sudah berubah atau tahun ajaran lain.
 */
final class IssueKey
{
    public static function make(
        string $domain,
        string $issueType,
        ?string $field,
        ?int $schoolYearId,
        string $subjectType,
        int $subjectId,
        int $subjectVersion,
        ?int $relatedSubjectId = null,
        ?int $relatedVersion = null
    ): string {
        return hash('sha256', implode('|', [
            $domain,
            $issueType,
            $field ?? '',
            $schoolYearId ?? '',
            $subjectType,
            $subjectId,
            $subjectVersion,
            $relatedSubjectId ?? '',
            $relatedVersion ?? '',
        ]));
    }
}
