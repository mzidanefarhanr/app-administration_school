export const DATA_QUALITY_PER_PAGE_OPTIONS = [10, 20, 50, 100];

export const ISSUE_TYPE_OPTIONS = [
    { value: 'missing', label: 'Missing' },
    { value: 'invalid', label: 'Invalid' },
    { value: 'duplicate', label: 'Duplicate Candidate' },
    { value: 'blocked', label: 'Blocked' }
];

export const ISSUE_STATUS_OPTIONS = [
    { value: 'open', label: 'Open' },
    { value: 'acknowledged', label: 'Acknowledged' },
    { value: 'ignored', label: 'Ignored' },
    { value: 'all', label: 'All' }
];

export const REVIEW_REASON_OPTIONS = [
    { value: 'pending_document', label: 'Pending document' },
    { value: 'source_unavailable', label: 'Source unavailable' },
    { value: 'verified_distinct', label: 'Verified as distinct', duplicatesOnly: true },
    { value: 'not_applicable', label: 'Not applicable' }
];

const STUDENT_FIELD_OPTIONS = [
    { value: 'nik', label: 'NIK' },
    { value: 'nis', label: 'NIS' },
    { value: 'nisn', label: 'NISN' },
    { value: 'gender_id', label: 'Gender' },
    { value: 'birthplace', label: 'Birthplace' },
    { value: 'birthdate', label: 'Birthdate' },
    { value: 'religion_id', label: 'Religion' },
    { value: 'name_birthdate', label: 'Name and Birthdate' }
];

const EMPLOYEE_FIELD_OPTIONS = [
    { value: 'nik', label: 'NIK' },
    { value: 'employee_type_id', label: 'Employee Type' },
    { value: 'employee_status_id', label: 'Employee Status' },
    { value: 'employment_id', label: 'Employment' },
    { value: 'gender_id', label: 'Gender' },
    { value: 'religion_id', label: 'Religion' },
    { value: 'marital_status_id', label: 'Marital Status' },
    { value: 'district_id', label: 'District' },
    { value: 'tmt_employee', label: 'Employment Start Date (TMT)' },
    { value: 'nuptk', label: 'NUPTK' },
    { value: 'subject_id', label: 'Subject' },
    { value: 'name_birthdate', label: 'Name and Birthdate' }
];

const ISSUE_TYPE_LABELS = Object.fromEntries(ISSUE_TYPE_OPTIONS.map((option) => [option.value, option.label]));
const ISSUE_STATUS_LABELS = Object.fromEntries(ISSUE_STATUS_OPTIONS.map((option) => [option.value, option.label]));
const REVIEW_REASON_LABELS = Object.fromEntries(REVIEW_REASON_OPTIONS.map((option) => [option.value, option.label]));
const FIELD_LABELS = Object.fromEntries([...STUDENT_FIELD_OPTIONS, ...EMPLOYEE_FIELD_OPTIONS].map((option) => [option.value, option.label]));

const trim = (value) => (typeof value === 'string' ? value.trim() : '');

const positiveInteger = (value, fallback) => {
    const number = Number(value);

    return Number.isInteger(number) && number > 0 ? number : fallback;
};

export function issueFieldOptions(domain) {
    return domain === 'employees' ? EMPLOYEE_FIELD_OPTIONS : STUDENT_FIELD_OPTIONS;
}

export function issueFieldLabel(field) {
    return FIELD_LABELS[field] ?? field ?? '-';
}

export function issueTypeLabel(type) {
    return ISSUE_TYPE_LABELS[type] ?? type ?? '-';
}

export function issueStatusLabel(status) {
    return ISSUE_STATUS_LABELS[status] ?? status ?? '-';
}

export function reviewReasonLabel(reasonCode) {
    return REVIEW_REASON_LABELS[reasonCode] ?? reasonCode ?? '-';
}

export function reviewReasonOptions(issueType) {
    return REVIEW_REASON_OPTIONS.filter((option) => !option.duplicatesOnly || issueType === 'duplicate');
}

export function formatCompletenessPercent(value) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '—';

    return `${Number(value).toFixed(1)}%`;
}

/**
 * Pick the academic year that matches the current date, falling back to the
 * first available year so the page still works outside the current term.
 */
export function defaultSchoolYearId(years = [], today = new Date()) {
    if (!Array.isArray(years) || years.length === 0) return null;

    const year = today.getFullYear();
    const month = today.getMonth() + 1;
    const expectedName = month > 6 ? `${year}/${year + 1}` : `${year - 1}/${year}`;
    const match = years.find((item) => trim(item?.name) === expectedName);

    return match ? match.id : (years[0]?.id ?? null);
}

export function resolveInitialTab(canStudents, canEmployees) {
    if (canStudents) return 'students';
    if (canEmployees) return 'employees';

    return null;
}

export function buildStudentSummaryParams(schoolYearId) {
    const id = positiveInteger(schoolYearId, null);

    return id === null ? {} : { school_year_id: id };
}

export function buildIssueParams(filters = {}) {
    const params = {
        page: positiveInteger(filters.page, 1),
        per_page: DATA_QUALITY_PER_PAGE_OPTIONS.includes(filters.perPage) ? filters.perPage : 20,
        status: ISSUE_STATUS_OPTIONS.some((option) => option.value === filters.status) ? filters.status : 'open'
    };

    const schoolYearId = positiveInteger(filters.schoolYearId, null);
    if (schoolYearId !== null) params.school_year_id = schoolYearId;

    const type = trim(filters.type);
    if (type !== '') params.type = type;

    const field = trim(filters.field);
    if (field !== '') params.field = field;

    const search = trim(filters.search);
    if (search !== '') params.search = search;

    return params;
}

/**
 * Review payload for PUT /api/data-quality/{domain}/reviews/{issueKey}.
 * The issue key itself is computed by the server from these values.
 */
export function buildReviewPayload(issue, { status, reasonCode = null, schoolYearId = null } = {}) {
    const payload = {
        status,
        reason_code: status === 'ignored' ? reasonCode || null : null,
        issue_type: issue?.type ?? null,
        field: issue?.field ?? null,
        subject_id: issue?.subject?.id ?? null,
        subject_version: issue?.subject?.version ?? null,
        related_subject_id: issue?.related?.id ?? null,
        related_version: issue?.related?.version ?? null,
        version: issue?.review?.version ?? 0
    };

    const yearId = positiveInteger(schoolYearId, null);
    if (yearId !== null) payload.school_year_id = yearId;

    return payload;
}

/**
 * Deep link to the existing record page. Employees are split by type, so the
 * server-provided English type label decides Teachers versus Employees.
 */
export function buildOpenRecordPath(domain, subject) {
    const id = positiveInteger(subject?.id, null);
    if (id === null) return null;

    if (domain === 'students') return `/public/students?openStudent=${id}`;

    if (subject?.employee_type === 'Teacher') return `/public/teachers?openEmployee=${id}`;

    return `/public/employees?openEmployee=${id}`;
}
