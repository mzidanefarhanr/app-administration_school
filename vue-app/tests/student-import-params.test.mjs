import test from 'node:test';
import assert from 'node:assert/strict';
import { STUDENT_IMPORT_MAX_BYTES, buildStudentImportFormData, validateStudentImportFile } from '../src/views/pages/public/studentImportParams.mjs';

test('rejects a missing file, a non-csv extension, an empty file, and an oversized file', () => {
    assert.match(validateStudentImportFile(null), /Choose a CSV file/);
    assert.match(validateStudentImportFile({ name: 'students.xlsx', size: 10 }), /must be a CSV/);
    assert.match(validateStudentImportFile({ name: 'students.csv', size: 0 }), /empty/);
    assert.match(validateStudentImportFile({ name: 'students.csv', size: STUDENT_IMPORT_MAX_BYTES + 1 }), /5 MB/);
});

test('accepts a csv within the size limit regardless of extension casing', () => {
    assert.equal(validateStudentImportFile({ name: 'STUDENTS.CSV', size: 1024 }), null);
});

test('form data keeps the chosen mode and falls back to insert', () => {
    const file = new File(['Name\nSynthetic'], 'students.csv', { type: 'text/csv' });

    const update = buildStudentImportFormData(file, 'update');
    assert.equal(update.get('mode'), 'update');
    assert.equal(update.get('file').name, 'students.csv');

    const fallback = buildStudentImportFormData(file, 'anything-else');
    assert.equal(fallback.get('mode'), 'insert');
});
