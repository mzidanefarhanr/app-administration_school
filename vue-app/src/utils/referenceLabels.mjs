import referenceLabels from '../../../laravel-api/resources/reference-labels.json';

// Exact, domain-scoped matches only: custom names and canonical identifiers stay intact.
export function referenceLabel(domain, name) {
    const labels = Object.hasOwn(referenceLabels, domain) ? referenceLabels[domain] : undefined;
    return typeof name === 'string' && labels && Object.hasOwn(labels, name) ? labels[name] : name;
}

export function displayReferenceOptions(domain, options) {
    if (!Array.isArray(options)) return options;
    return options.map((option) =>
        isRecord(option) && typeof option.name === 'string'
            ? { ...option, display_name: referenceLabel(domain, option.name) }
            : option
    );
}

const domains = {
    'employee-types': ['employee_type', 'employeeType'],
    'employee-statuses': ['employee_status', 'employeeStatus'],
    employments: ['employment'],
    subjects: ['subject'],
    genders: ['gender'],
    religions: ['religion'],
    'marital-statuses': ['marital_status', 'maritalStatus'],
    'education-levels': [
        'education_level', 'educationLevel',
        'father_education_level', 'fatherEducationLevel',
        'mother_education_level', 'motherEducationLevel',
        'guardian_education_level', 'guardianEducationLevel',
        'parent_education_level', 'parentEducationLevel'
    ],
    professions: [
        'occupation', 'profession',
        'father_profession', 'fatherProfession', 'father_occupation', 'fatherOccupation',
        'mother_profession', 'motherProfession', 'mother_occupation', 'motherOccupation',
        'guardian_profession', 'guardianProfession', 'guardian_occupation', 'guardianOccupation',
        'parent_profession', 'parentProfession', 'parent_occupation', 'parentOccupation'
    ],
    'family-statuses': ['family_status', 'familyStatus'],
    'student-statuses': ['student_status', 'studentStatus'],
    'student-entries': ['student_entry', 'studentEntry'],
    'student-majors': ['student_major', 'studentMajor'],
    'phase-statuses': ['phase_status', 'phaseStatus'],
    'status-users': ['status_user', 'statusUser'],
    semesters: ['semester'],
    'school-status': ['school_status', 'schoolStatus', 'status_education']
};

const relationDomains = Object.create(null);
for (const [domain, aliases] of Object.entries(domains)) {
    relationDomains[domain] = domain;
    relationDomains[domain.replaceAll('-', '_')] = domain;
    relationDomains[domain.replace(/-([a-z])/g, (_, letter) => letter.toUpperCase())] = domain;
    relationDomains[`by_${domain.replaceAll('-', '_')}`] = domain;
    for (const alias of aliases) {
        relationDomains[alias] = domain;
        relationDomains[`by_${alias}`] = domain;
    }
}

// These endpoints contain reference rows. Schools, classes, people, roles and
// accounts are deliberately absent, even when their names match a catalog term.
const catalogEndpoints = { ...Object.fromEntries(Object.keys(domains).map((domain) => [domain, domain])), 'family-status': 'family-statuses' };

export function referenceDomainForEndpoint(endpoint = '') {
    if (typeof endpoint !== 'string') return undefined;
    const pathname = endpoint.replace(/^https?:\/\/[^/]+/i, '').split(/[?#]/, 1)[0];
    const match = pathname.match(/^\/?api\/([^/]+)(?:\/\d+)?\/?$/);
    return match && Object.hasOwn(catalogEndpoints, match[1]) ? catalogEndpoints[match[1]] : undefined;
}

function isRecord(value) {
    if (value === null || typeof value !== 'object') return false;
    const prototype = Object.getPrototypeOf(value);
    return prototype === Object.prototype || prototype === null;
}

function enrich(value, domain) {
    if (Array.isArray(value)) return value.map((item) => enrich(item, domain));
    if (!isRecord(value)) return value;

    const result = { ...value };

    for (const [key, child] of Object.entries(value)) {
        const childDomain = relationDomains[key];
        // Scope flows through response wrappers only, never into unrelated objects.
        const wrapperDomain = ['data', 'items', 'results'].includes(key) ? domain : undefined;
        result[key] = enrich(child, childDomain || wrapperDomain);
    }
    for (const [key, child] of Object.entries(value)) {
        if (relationDomains[key] && typeof child === 'string') {
            result[`${key}_display_name`] = referenceLabel(relationDomains[key], child);
        }
    }
    if (domain && typeof value.name === 'string') result.display_name = referenceLabel(domain, value.name);
    if (domain && typeof value.label === 'string') result.display_label = referenceLabel(domain, value.label);
    return result;
}

// Pure enrichment: raw name/label fields and the original payload are never modified.
// Strings, Blob downloads, Dates and other non-JSON values pass through unchanged.
export function enrichReferenceResponse(payload, endpoint = '') {
    return enrich(payload, referenceDomainForEndpoint(endpoint));
}

// PrimeVue CSV callbacks receive a cell, not a row. Translate only explicitly
// configured reference fields and retain PrimeVue's quote escaping for user data.
export function referenceExportFunction(fields = {}) {
    return ({ data, field }) => {
        const domain = Object.hasOwn(fields, field) ? fields[field] : undefined;
        return String(referenceLabel(domain, data) ?? '').replaceAll('"', '""');
    };
}
