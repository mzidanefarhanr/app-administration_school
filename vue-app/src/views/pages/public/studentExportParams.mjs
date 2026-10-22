/**
 * Parameter ekspor siswa (DEC-22).
 *
 * `placement` SELALU dikirim, termasuk `all`. Ini mencegah CSV berbeda dari
 * daftar All hanya karena backend memakai default `placed` saat parameter absen.
 */
export function buildStudentExportParams({ schoolYearId, placement, search, rombelId } = {}) {
    const selectedPlacement = placement || 'all';

    return {
        school_year_id: schoolYearId,
        placement: selectedPlacement,
        search: search && search.trim() ? search.trim() : undefined,
        rombel: selectedPlacement !== 'unplaced' && rombelId ? rombelId : undefined,
    };
}
