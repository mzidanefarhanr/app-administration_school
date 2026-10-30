import test from 'node:test';
import assert from 'node:assert/strict';
import { EMPLOYEE_IMPORT_MAX_BYTES, buildEmployeeImportFormData, validateEmployeeImportFile } from '../src/views/pages/public/employeeImportParams.mjs';

test('rejects a missing file, a non-csv extension, an empty file, and an oversized file', () => {
    assert.match(validateEmployeeImportFile(null), /Choose a CSV file/);
    assert.match(validateEmployeeImportFile({ name: 'employees.xlsx', size: 10 }), /must be a CSV/);
    assert.match(validateEmployeeImportFile({ name: 'employees.csv', size: 0 }), /empty/);
    assert.match(validateEmployeeImportFile({ name: 'employees.csv', size: EMPLOYEE_IMPORT_MAX_BYTES + 1 }), /5 MB/);
});

test('accepts a csv within the size limit regardless of extension casing', () => {
    assert.equal(validateEmployeeImportFile({ name: 'EMPLOYEES.CSV', size: 1024 }), null);
});

test('form data keeps the chosen mode and falls back to insert', () => {
    const file = new File(['Name\nSynthetic'], 'employees.csv', { type: 'text/csv' });

    const update = buildEmployeeImportFormData(file, 'update');
    assert.equal(update.get('mode'), 'update');
    assert.equal(update.get('file').name, 'employees.csv');

    const fallback = buildEmployeeImportFormData(file, 'anything-else');
    assert.equal(fallback.get('mode'), 'insert');
});
