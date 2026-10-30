/**
 * Parameter wizard impor siswa (Fase 05a, DEC-25).
 *
 * Batas di sini hanya untuk umpan balik awal di browser; backend tetap
 * menegakkan batas ukuran, jumlah baris, dan mode.
 */
export const STUDENT_IMPORT_MODES = [
    { label: 'Insert new students', value: 'insert' },
    { label: 'Update existing students', value: 'update' }
];

export const STUDENT_IMPORT_MAX_BYTES = 5 * 1024 * 1024;

const MB = 1024 * 1024;

/**
 * @param {{ name?: string, size?: number } | null} file
 * @returns {string | null} pesan kesalahan atau null bila file lolos pemeriksaan awal.
 */
export function validateStudentImportFile(file) {
    if (!file) return 'Choose a CSV file to import.';

    if (!/\.csv$/i.test(file.name ?? '')) {
        return 'The file must be a CSV (UTF-8). Other formats are not supported yet.';
    }

    if (!file.size) return 'The file is empty.';

    if (file.size > STUDENT_IMPORT_MAX_BYTES) {
        return `The file is larger than ${STUDENT_IMPORT_MAX_BYTES / MB} MB.`;
    }

    return null;
}

/**
 * Build the multipart body for POST /api/imports/students.
 *
 * @param {File} file
 * @param {string} mode
 * @returns {FormData}
 */
export function buildStudentImportFormData(file, mode) {
    const form = new FormData();
    form.append('file', file);
    form.append('mode', mode === 'update' ? 'update' : 'insert');

    return form;
}
