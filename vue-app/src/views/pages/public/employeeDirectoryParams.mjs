export const EMPLOYEE_PER_PAGE_OPTIONS = [10, 20, 50, 100];

export const EMPLOYEE_SORT_FIELDS = ['id', 'name', 'tmt_employee'];

const trim = (value) => (typeof value === 'string' ? value.trim() : '');

const assignFilter = (params, key, value) => {
    if (value === null || value === undefined || value === '') return;
    params[key] = value;
};

/**
 * Query parameters for GET /api/employees (DEC-24 D6).
 * The employee type is fixed per route, so it is only sent when provided.
 */
export function buildEmployeeListParams(filters = {}) {
    const params = {};

    const search = trim(filters.search);
    if (search !== '') params.search = search;

    assignFilter(params, 'employee_type_id', filters.employeeTypeId);
    assignFilter(params, 'employee_status_id', filters.employeeStatusId);
    assignFilter(params, 'employment_id', filters.employmentId);
    assignFilter(params, 'subject_id', filters.subjectId);

    params.page = filters.page && filters.page > 0 ? filters.page : 1;
    params.per_page = EMPLOYEE_PER_PAGE_OPTIONS.includes(filters.perPage) ? filters.perPage : 20;
    params.sort = EMPLOYEE_SORT_FIELDS.includes(filters.sort) ? filters.sort : 'name';
    params.direction = filters.direction === 'desc' ? 'desc' : 'asc';

    if (filters.onlyArchived) params.only_archived = 1;

    return params;
}

/**
 * Query parameters for GET /api/employees/export (DEC-24 D8, DEC-26 D1/D2).
 * Export mirrors the active filters but never pagination, sorting, or archive mode.
 * Selected columns keep registry order on the server; an empty list means default.
 */
export function buildEmployeeExportParams(filters = {}, columns = []) {
    const params = buildEmployeeListParams(filters);

    delete params.page;
    delete params.per_page;
    delete params.sort;
    delete params.direction;
    delete params.only_archived;

    const keys = Array.isArray(columns) ? [...new Set(columns.filter((key) => typeof key === 'string' && key.trim() !== '').map((key) => key.trim()))] : [];

    if (keys.length > 0) params.columns = keys.join(',');

    return params;
}

/**
 * Resolve the fixed employee type id for a directory route by its name.
 */
export function resolveEmployeeTypeId(options = [], name = '') {
    const wanted = trim(name).toLowerCase();
    if (wanted === '') return null;

    const match = options.find((option) => trim(option?.name).toLowerCase() === wanted);

    return match ? match.id : null;
}

/**
 * Convert empty form values to null so the API receives nullable fields.
 */
export function normalizeEmployeePayload(form = {}) {
    const payload = {};

    Object.entries(form).forEach(([key, value]) => {
        payload[key] = typeof value === 'string' && value.trim() === '' ? null : value;
    });

    return payload;
}

/**
 * Pick the first server validation message for inline display.
 */
export function firstValidationMessage(response) {
    const errors = response?.data?.errors;

    if (errors && typeof errors === 'object') {
        const first = Object.values(errors)[0];

        if (Array.isArray(first) && first.length > 0) return first[0];
        if (typeof first === 'string') return first;
    }

    return response?.data?.message ?? null;
}
