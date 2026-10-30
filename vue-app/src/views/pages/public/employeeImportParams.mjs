/**
 * Parameter wizard impor pegawai (Fase 05b, DEC-26).
 *
 * Batas di sini hanya untuk umpan balik awal di browser; backend tetap
 * menegakkan batas ukuran, jumlah baris, dan mode. Match update memakai NIK.
 */
export const EMPLOYEE_IMPORT_MODES = [
    { label: 'Insert new employees', value: 'insert' },
    { label: 'Update existing employees', value: 'update' }
];

export const EMPLOYEE_IMPORT_MAX_BYTES = 5 * 1024 * 1024;

const MB = 1024 * 1024;

/**
 * @param {{ name?: string, size?: number } | null} file
 * @returns {string | null} pesan kesalahan atau null bila file lolos pemeriksaan awal.
 */
export function validateEmployeeImportFile(file) {
    if (!file) return 'Choose a CSV file to import.';

    if (!/\.csv$/i.test(file.name ?? '')) {
        return 'The file must be a CSV (UTF-8). Other formats are not supported yet.';
    }

    if (!file.size) return 'The file is empty.';

    if (file.size > EMPLOYEE_IMPORT_MAX_BYTES) {
        return `The file is larger than ${EMPLOYEE_IMPORT_MAX_BYTES / MB} MB.`;
    }

    return null;
}

/**
 * Build the multipart body for POST /api/imports/employees.
 *
 * @param {File} file
 * @param {string} mode
 * @returns {FormData}
 */
export function buildEmployeeImportFormData(file, mode) {
    const form = new FormData();
    form.append('file', file);
    form.append('mode', mode === 'update' ? 'update' : 'insert');

    return form;
}
