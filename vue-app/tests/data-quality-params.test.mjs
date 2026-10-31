import test from 'node:test';
import assert from 'node:assert/strict';
import {
    DATA_QUALITY_PER_PAGE_OPTIONS,
    ISSUE_STATUS_OPTIONS,
    ISSUE_TYPE_OPTIONS,
    buildIssueParams,
    buildOpenRecordPath,
    buildReviewPayload,
    buildStudentSummaryParams,
    defaultSchoolYearId,
    formatCompletenessPercent,
    issueFieldLabel,
    issueFieldOptions,
    issueStatusLabel,
    issueTypeLabel,
    resolveInitialTab,
    reviewReasonLabel,
    reviewReasonOptions
} from '../src/views/pages/public/dataQualityParams.mjs';

test('default school year follows the academic term and falls back safely', () => {
    const years = [
        { id: 3, name: '2025/2026' },
        { id: 4, name: '2026/2027' }
    ];

    assert.equal(defaultSchoolYearId(years, new Date('2026-10-31T00:00:00Z')), 4);
    assert.equal(defaultSchoolYearId(years, new Date('2026-03-01T00:00:00Z')), 3);
    assert.equal(defaultSchoolYearId([{ id: 9, name: '2030/2031' }], new Date('2026-10-31T00:00:00Z')), 9);
    assert.equal(defaultSchoolYearId([], new Date('2026-10-31T00:00:00Z')), null);
    assert.equal(defaultSchoolYearId(null), null);
});

test('initial tab uses the first permitted domain', () => {
    assert.equal(resolveInitialTab(true, true), 'students');
    assert.equal(resolveInitialTab(false, true), 'employees');
    assert.equal(resolveInitialTab(true, false), 'students');
    assert.equal(resolveInitialTab(false, false), null);
});

test('student summary params require a positive school year id', () => {
    assert.deepEqual(buildStudentSummaryParams(4), { school_year_id: 4 });
    assert.deepEqual(buildStudentSummaryParams('4'), { school_year_id: 4 });
    assert.deepEqual(buildStudentSummaryParams(null), {});
    assert.deepEqual(buildStudentSummaryParams(0), {});
});

test('issue params use server defaults and omit empty filters', () => {
    assert.deepEqual(buildIssueParams({ schoolYearId: 4 }), {
        school_year_id: 4,
        page: 1,
        per_page: 20,
        status: 'open'
    });

    assert.deepEqual(buildIssueParams({}), { page: 1, per_page: 20, status: 'open' });
});

test('issue params include active filters and supported paging', () => {
    const params = buildIssueParams({
        schoolYearId: 4,
        type: 'missing',
        field: 'nik',
        status: 'ignored',
        search: '  Alpha  ',
        page: 3,
        perPage: 50
    });

    assert.equal(params.school_year_id, 4);
    assert.equal(params.type, 'missing');
    assert.equal(params.field, 'nik');
    assert.equal(params.status, 'ignored');
    assert.equal(params.search, 'Alpha');
    assert.equal(params.page, 3);
    assert.equal(params.per_page, 50);
});

test('issue params fall back on unsupported paging and status values', () => {
    const params = buildIssueParams({ status: 'unknown', page: 0, perPage: 7 });

    assert.equal(params.status, 'open');
    assert.equal(params.page, 1);
    assert.equal(params.per_page, 20);
    assert.equal(DATA_QUALITY_PER_PAGE_OPTIONS.includes(params.per_page), true);
});

test('review payload mirrors issue identity and clears reasons unless ignored', () => {
    const issue = {
        type: 'missing',
        field: 'nik',
        subject: { id: 11, version: 3 },
        related: null,
        review: { version: 2 }
    };

    assert.deepEqual(buildReviewPayload(issue, { status: 'ignored', reasonCode: 'pending_document', schoolYearId: 4 }), {
        status: 'ignored',
        reason_code: 'pending_document',
        issue_type: 'missing',
        field: 'nik',
        subject_id: 11,
        subject_version: 3,
        related_subject_id: null,
        related_version: null,
        version: 2,
        school_year_id: 4
    });

    const acknowledged = buildReviewPayload(issue, { status: 'acknowledged', reasonCode: 'pending_document' });
    assert.equal(acknowledged.reason_code, null);
    assert.equal('school_year_id' in acknowledged, false);
});

test('review payload carries both records for duplicate issues', () => {
    const issue = {
        type: 'duplicate',
        field: 'name_birthdate',
        subject: { id: 11, version: 3 },
        related: { id: 12, version: 5 },
        review: { version: 0 }
    };

    const payload = buildReviewPayload(issue, { status: 'open', schoolYearId: 4 });

    assert.equal(payload.related_subject_id, 12);
    assert.equal(payload.related_version, 5);
    assert.equal(payload.version, 0);
});

test('review payload defaults a missing review version to zero', () => {
    const payload = buildReviewPayload({ type: 'invalid', field: 'nik', subject: { id: 1, version: 1 } }, { status: 'open' });

    assert.equal(payload.version, 0);
    assert.equal(payload.reason_code, null);
});

test('open record paths use the existing detail pages and type labels', () => {
    assert.equal(buildOpenRecordPath('students', { id: 7 }), '/public/students?openStudent=7');
    assert.equal(buildOpenRecordPath('employees', { id: 8, employee_type: 'Teacher' }), '/public/teachers?openEmployee=8');
    assert.equal(buildOpenRecordPath('employees', { id: 9, employee_type: 'Non-teaching Employee' }), '/public/employees?openEmployee=9');
    assert.equal(buildOpenRecordPath('employees', { id: 10, employee_type: 'Other' }), '/public/employees?openEmployee=10');
    assert.equal(buildOpenRecordPath('students', { id: null }), null);
});

test('review reasons hide verified distinct for non-duplicates', () => {
    assert.equal(
        reviewReasonOptions('missing').some((option) => option.value === 'verified_distinct'),
        false
    );
    assert.equal(
        reviewReasonOptions('duplicate').some((option) => option.value === 'verified_distinct'),
        true
    );
});

test('labels and percentages are presentation-safe', () => {
    assert.equal(issueTypeLabel('duplicate'), 'Duplicate Candidate');
    assert.equal(issueTypeLabel('blocked'), 'Blocked');
    assert.equal(issueStatusLabel('acknowledged'), 'Acknowledged');
    assert.equal(issueFieldLabel('tmt_employee'), 'Employment Start Date (TMT)');
    assert.equal(issueFieldLabel('unknown_field'), 'unknown_field');
    assert.equal(reviewReasonLabel('verified_distinct'), 'Verified as distinct');
    assert.equal(formatCompletenessPercent(91.6), '91.6%');
    assert.equal(formatCompletenessPercent(100), '100.0%');
    assert.equal(formatCompletenessPercent(null), '—');
    assert.equal(formatCompletenessPercent(undefined), '—');
});

test('field options follow the selected domain', () => {
    assert.equal(
        issueFieldOptions('students').some((option) => option.value === 'nuptk'),
        false
    );
    assert.equal(
        issueFieldOptions('employees').some((option) => option.value === 'nuptk'),
        true
    );
    assert.equal(ISSUE_TYPE_OPTIONS.length, 4);
    assert.equal(ISSUE_STATUS_OPTIONS.length, 4);
});
