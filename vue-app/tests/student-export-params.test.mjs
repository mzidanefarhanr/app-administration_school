import test from 'node:test';
import assert from 'node:assert/strict';
import { buildStudentExportParams } from '../src/views/pages/public/studentExportParams.mjs';

test('export always sends the selected placement, including all', () => {
    const params = buildStudentExportParams({ schoolYearId: 7, placement: 'all', search: '', rombelId: 3 });

    assert.equal(params.placement, 'all');
    assert.equal(params.school_year_id, 7);
    assert.equal(params.rombel, 3);
    assert.equal(params.search, undefined);
});

test('export falls back to all when placement is missing', () => {
    const params = buildStudentExportParams({ schoolYearId: 7 });

    assert.equal(params.placement, 'all');
    assert.equal(params.school_year_id, 7);
});

test('export keeps placed/unplaced and drops rombel for unplaced', () => {
    assert.equal(buildStudentExportParams({ schoolYearId: 7, placement: 'placed', rombelId: 3 }).rombel, 3);
    assert.equal(buildStudentExportParams({ schoolYearId: 7, placement: 'unplaced', rombelId: 3 }).rombel, undefined);
});
