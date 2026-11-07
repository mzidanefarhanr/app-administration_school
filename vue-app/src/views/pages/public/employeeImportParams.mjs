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

/**
 * Opsi mode impor dengan label terjemahan (vue-i18n). Tanpa `t`, memakai label Inggris bawaan.
 *
 * @param {((key: string) => string) | null} t
 */
export function getEmployeeImportModes(t = null) {
    if (!t) return EMPLOYEE_IMPORT_MODES;

    return EMPLOYEE_IMPORT_MODES.map((mode) => ({ ...mode, label: t(`employees.import.modes.${mode.value}`) }));
}

export const EMPLOYEE_IMPORT_MAX_BYTES = 5 * 1024 * 1024;

const MB = 1024 * 1024;

/**
 * @param {{ name?: string, size?: number } | null} file
 * @param {((key: string, params?: object) => string) | null} t fungsi terjemahan opsional (vue-i18n)
 * @returns {string | null} pesan kesalahan atau null bila file lolos pemeriksaan awal.
 */
export function validateEmployeeImportFile(file, t = null) {
    if (!file) return t ? t('employees.import.errors.chooseFile') : 'Choose a CSV or Excel file to import.';

    if (!/\.(csv|xlsx)$/i.test(file.name ?? '')) {
        return t ? t('employees.import.errors.fileFormat') : 'The file must be a CSV (UTF-8) or Excel (.xlsx) file.';
    }

    if (!file.size) return t ? t('employees.import.errors.fileEmpty') : 'The file is empty.';

    if (file.size > EMPLOYEE_IMPORT_MAX_BYTES) {
        return t ? t('employees.import.errors.fileTooLarge', { size: EMPLOYEE_IMPORT_MAX_BYTES / MB }) : `The file is larger than ${EMPLOYEE_IMPORT_MAX_BYTES / MB} MB.`;
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
