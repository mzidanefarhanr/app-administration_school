<?php

namespace App\Support;

use App\Models\Student;
use App\Models\StudentRombel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Definisi filter bersama untuk daftar siswa dan ekspor (DEC-22).
 *
 * - Siswa terarsip (soft delete) tidak pernah ikut daftar/rekap/ekspor.
 * - Identitas primer keanggotaan adalah `student_id`; baris legacy tanpa
 *   `student_id` hanya cocok bila `student_nik` menunjuk satu siswa (NIK unik).
 * - Keanggotaan "aktif" pada tahun terpilih: belum dihapus, belum diarsipkan,
 *   status Aktif/null, dan belum memiliki tanggal keluar.
 */
class StudentDirectory
{
    public const PLACEMENTS = ['all', 'placed', 'unplaced'];

    public const SORTS = ['id', 'name', 'nis', 'nisn'];

    public const PER_PAGE = [10, 20, 50, 100];

    /**
     * Subquery EXISTS keanggotaan rombel aktif pada tahun ajaran terpilih.
     */
    public static function activeMembershipExists(int $yearId, ?int $rombelId = null): \Closure
    {
        return function ($query) use ($yearId, $rombelId) {
            $query->selectRaw('1')
                ->from('student_rombels as sr')
                ->join('school_rombels as rb', 'rb.id', '=', 'sr.school_rombel')
                ->where('rb.school_year_id', $yearId)
                ->whereNull('sr.deleted_at')
                ->whereNull('sr.archived_at')
                ->where(function ($q) {
                    $q->where('sr.student_status_id', 1)->orWhereNull('sr.student_status_id');
                })
                ->whereNull('sr.latest_student_exit_date');

            if ($rombelId) {
                $query->where('sr.school_rombel', $rombelId);
            }

            $query->where(function ($q) {
                $q->whereColumn('sr.student_id', 'students.id')
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('sr.student_id')
                            ->whereNotNull('students.nik')
                            ->whereColumn('sr.student_nik', 'students.nik');
                    });
            });
        };
    }

    /**
     * Query dasar: siswa tidak terarsip + pencarian + filter rombel (aktif tahun terpilih).
     *
     * @param  array<string, mixed>  $filters
     */
    public static function base(array $filters): Builder
    {
        $query = Student::query()
            ->whereNull('students.deleted_at')
            ->select([
                'students.id',
                'students.version',
                'students.name',
                'students.nis',
                'students.nisn',
                'students.nik',
                'students.gender_id',
            ]);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('students.name', 'like', "%{$search}%")
                    ->orWhere('students.nis', 'like', "%{$search}%")
                    ->orWhere('students.nisn', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['rombel'])) {
            $query->whereExists(
                self::activeMembershipExists((int) $filters['school_year_id'], (int) $filters['rombel'])
            );
        }

        return $query;
    }

    /**
     * Terapkan filter placement ke query dasar.
     */
    public static function place(Builder $query, int $yearId, string $placement): Builder
    {
        if ($placement === 'placed') {
            $query->whereExists(self::activeMembershipExists($yearId));
        } elseif ($placement === 'unplaced') {
            $query->whereNotExists(self::activeMembershipExists($yearId));
        }

        return $query;
    }

    /**
     * Keanggotaan aktif tahun terpilih untuk sekumpulan siswa (termasuk legacy NIK).
     * Satu siswa = satu keanggotaan; baris `student_id` diprioritaskan.
     *
     * @param  Collection<int, Student>  $students
     * @return array<int, StudentRombel> keyed by student id
     */
    public static function membershipsFor(Collection $students, int $yearId): array
    {
        if ($students->isEmpty()) {
            return [];
        }

        $ids = $students->pluck('id')->filter()->all();
        $niks = $students->pluck('nik')->filter()->all();

        $rows = StudentRombel::query()
            ->with(['schoolRombel', 'studentEntry', 'studentStatus'])
            ->whereNull('student_rombels.deleted_at')
            ->whereNull('student_rombels.archived_at')
            ->where(function ($q) {
                $q->where('student_rombels.student_status_id', 1)
                    ->orWhereNull('student_rombels.student_status_id');
            })
            ->whereNull('student_rombels.latest_student_exit_date')
            ->whereHas('schoolRombel', fn ($q) => $q->where('school_year_id', $yearId))
            ->where(function ($q) use ($ids, $niks) {
                $q->whereIn('student_rombels.student_id', $ids)
                    ->orWhere(function ($legacy) use ($niks) {
                        $legacy->whereNull('student_rombels.student_id')
                            ->whereIn('student_rombels.student_nik', $niks);
                    });
            })
            ->get();

        $byNik = $students->whereNotNull('nik')->keyBy('nik');
        $map = [];

        foreach ($rows as $row) {
            $studentId = $row->student_id;
            if (! $studentId && $row->student_nik && isset($byNik[$row->student_nik])) {
                $studentId = $byNik[$row->student_nik]->id;
            }
            if ($studentId && ! isset($map[$studentId])) {
                $map[$studentId] = $row;
            }
        }

        return $map;
    }
}
