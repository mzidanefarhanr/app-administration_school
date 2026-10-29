import test from 'node:test';
import assert from 'node:assert/strict';
import {
    buildEmployeeListParams,
    buildEmployeeExportParams,
    resolveEmployeeTypeId,
    normalizeEmployeePayload,
    firstValidationMessage,
} from '../src/views/pages/public/employeeDirectoryParams.mjs';

test('list params keep server defaults and omit empty filters', () => {
    const params = buildEmployeeListParams({ search: '   ', onlyArchived: false });

    assert.deepEqual(params, { page: 1, per_page: 20, sort: 'name', direction: 'asc' });
});

test('list params include active filters and archive mode', () => {
    const params = buildEmployeeListParams({
        search: '  budi  ',
        employeeTypeId: 4,
        employeeStatusId: 2,
        employmentId: 7,
        subjectId: 3,
        page: 3,
        perPage: 50,
        sort: 'tmt_employee',
        direction: 'desc',
        onlyArchived: true,
    });

    assert.equal(params.search, 'budi');
    assert.equal(params.employee_type_id, 4);
    assert.equal(params.employee_status_id, 2);
    assert.equal(params.employment_id, 7);
    assert.equal(params.subject_id, 3);
    assert.equal(params.page, 3);
    assert.equal(params.per_page, 50);
    assert.equal(params.sort, 'tmt_employee');
    assert.equal(params.direction, 'desc');
    assert.equal(params.only_archived, 1);
});

test('list params fall back on unsupported paging and sorting values', () => {
    const params = buildEmployeeListParams({ page: 0, perPage: 7, sort: 'nik', direction: 'sideways' });

    assert.equal(params.page, 1);
    assert.equal(params.per_page, 20);
    assert.equal(params.sort, 'name');
    assert.equal(params.direction, 'asc');
});

test('export params mirror filters without paging, sorting, or archive mode', () => {
    const params = buildEmployeeExportParams({
        search: 'siti',
        employeeTypeId: 4,
        page: 2,
        perPage: 50,
        sort: 'id',
        direction: 'desc',
        onlyArchived: true,
    });

    assert.deepEqual(params, { search: 'siti', employee_type_id: 4 });
});

test('employee type id resolves by name for the fixed route type', () => {
    const options = [
        { id: 1, name: 'Tenaga Kependidikan' },
        { id: 2, name: 'Pendidik' },
    ];

    assert.equal(resolveEmployeeTypeId(options, 'pendidik'), 2);
    assert.equal(resolveEmployeeTypeId(options, ' Tenaga Kependidikan '), 1);
    assert.equal(resolveEmployeeTypeId(options, 'Unknown'), null);
    assert.equal(resolveEmployeeTypeId(options, ''), null);
});

test('empty strings are normalised to null before submit', () => {
    const payload = normalizeEmployeePayload({ name: 'Budi', nik: '', nuptk: '  ', subject_id: 3 });

    assert.equal(payload.nik, null);
    assert.equal(payload.nuptk, null);
    assert.equal(payload.subject_id, 3);
});

test('validation helper returns the first inline message or fallback', () => {
    assert.equal(firstValidationMessage({ data: { errors: { nik: ['The nik field is required.'] } } }), 'The nik field is required.');
    assert.equal(firstValidationMessage({ data: { message: 'Conflict' } }), 'Conflict');
    assert.equal(firstValidationMessage(undefined), null);
});
