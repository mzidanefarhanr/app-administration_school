import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const mappingUrl = new URL('../../laravel-api/resources/reference-labels.json', import.meta.url);
const labels = JSON.parse(await readFile(mappingUrl, 'utf8'));
const source = await readFile(new URL('../src/utils/referenceLabels.mjs', import.meta.url), 'utf8');
// Vite accepts plain JSON imports; native Node requires an import attribute.
// Load the actual helper and shared JSON, adapting only that import for Node.
const nativeSource = source.replace(
    "import referenceLabels from '../../../laravel-api/resources/reference-labels.json';",
    `import referenceLabels from ${JSON.stringify(mappingUrl.href)} with { type: 'json' };`
);
assert.notEqual(nativeSource, source, 'The helper must import the shared mapping JSON');
const {
    referenceLabel,
    displayReferenceOptions,
    referenceDomainForEndpoint,
    enrichReferenceResponse,
    referenceExportFunction
} = await import(`data:text/javascript;base64,${Buffer.from(nativeSource).toString('base64')}`);

const knownLabels = {
    'employee-types': ['Pendidik', 'Teacher'],
    'employee-statuses': ['Pegawai Negeri Sipil', 'Civil Servant'],
    employments: ['Kepala Sekolah', 'Principal'],
    subjects: ['Matematika', 'Mathematics'],
    genders: ['Perempuan', 'Female'],
    religions: ['Kristen', 'Protestant Christianity'],
    'marital-statuses': ['Menikah', 'Married'],
    'education-levels': ['Strata II', "Master's Degree"],
    professions: ['Petani', 'Farmer'],
    'family-statuses': ['Yatim', 'Father Deceased'],
    'student-statuses': ['Aktif', 'Active'],
    'student-entries': ['Siswa Baru', 'New Student'],
    'student-majors': ['Bahasa', 'Languages'],
    'phase-statuses': ['2026 Tahap 1', '2026 Phase 1'],
    'status-users': ['Tidak Aktif', 'Inactive'],
    semesters: ['Ganjil', 'First Semester'],
    'school-status': ['Negeri', 'Public']
};

test('all shared domains provide the expected English display labels', () => {
    for (const [domain, [canonical, english]] of Object.entries(knownLabels)) {
        assert.equal(referenceLabel(domain, canonical), english, domain);
        for (const [raw, display] of Object.entries(labels[domain])) {
            assert.equal(referenceLabel(domain, raw), display, `${domain}: ${raw}`);
        }
    }
});

test('unknown, proper and custom names are preserved, with no fuzzy matching', () => {
    assert.equal(referenceLabel('subjects', 'Matematika Club'), 'Matematika Club');
    assert.equal(referenceLabel('professions', 'Custom Profession'), 'Custom Profession');
    assert.equal(referenceLabel('genders', 'perempuan'), 'perempuan');
    assert.equal(referenceLabel('unknown', 'Perempuan'), 'Perempuan');
    assert.equal(referenceLabel('genders', 'constructor'), 'constructor');
    assert.equal(referenceLabel('constructor', 'name'), 'name');
    assert.equal(referenceLabel('genders', null), null);
    assert.equal(referenceLabel('genders', undefined), undefined);
    assert.equal(referenceLabel('genders', 42), 42);
});

test('display options retain raw names, IDs, metadata and the original array', () => {
    const rows = [Object.freeze({ id: 17, name: 'Pendidik', count: 8 }), { id: 18, name: 'Custom Team' }];
    const result = displayReferenceOptions('employee-types', rows);
    assert.deepEqual(result, [
        { id: 17, name: 'Pendidik', count: 8, display_name: 'Teacher' },
        { id: 18, name: 'Custom Team', display_name: 'Custom Team' }
    ]);
    assert.equal(rows[0].display_name, undefined);
    assert.notEqual(result, rows);
    assert.deepEqual(displayReferenceOptions('genders', [null, 'Perempuan']), [null, 'Perempuan']);
    assert.equal(displayReferenceOptions('genders', null), null);
});

test('only exact catalog endpoints scope top-level names', () => {
    for (const domain of Object.keys(knownLabels)) {
        assert.equal(referenceDomainForEndpoint(`/api/${domain}?page=2`), domain);
        assert.equal(referenceDomainForEndpoint(`https://api.example.test/api/${domain}/12/`), domain);
    }
    assert.equal(referenceDomainForEndpoint('/api/family-status'), 'family-statuses');
    for (const endpoint of ['/api/users', '/api/students', '/api/employees', '/api/education-schools', '/api/school-rombels', '/api/roles', '/api/subjects/export', '/api/subjects/12/users', '/api/constructor', '/api/unknown', '/subjects', '/other/api/subjects']) {
        assert.equal(referenceDomainForEndpoint(endpoint), undefined, endpoint);
    }
});

test('catalog arrays, detail rows and data wrappers retain canonical name', () => {
    const row = { id: 7, name: 'Matematika' };
    const result = enrichReferenceResponse({ success: true, data: { data: [row] }, meta: { name: 'Matematika' } }, '/api/subjects');
    assert.deepEqual(result.data.data[0], { ...row, display_name: 'Mathematics' });
    assert.deepEqual(result.meta, { name: 'Matematika' });
    assert.deepEqual(enrichReferenceResponse(row, '/api/subjects/7'), { ...row, display_name: 'Mathematics' });
    assert.deepEqual(enrichReferenceResponse([row], '/api/subjects'), [{ ...row, display_name: 'Mathematics' }]);
    assert.equal(row.display_name, undefined);
});

test('people, places, schools, classes, roles and generic user fields are never renamed', () => {
    const payload = {
        name: 'Pendidik', username: 'Perempuan', address: 'Negeri',
        data: [{ name: 'Perempuan', account: { name: 'Matematika' }, user: { name: 'Guru' }, student: { name: 'Bahasa' }, employee: { name: 'Pendidik' }, person: { name: 'Negeri' }, district: { name: 'Swasta' }, school: { name: 'Negeri' }, education_school: { name: 'Negeri' }, class_teach: { name: 'Guru' }, rombel: { name: 'Bahasa' }, roles: [{ name: 'Guru', slug: 'guru' }] }],
        recap: { by_rombel: [{ name: 'Bahasa', label: 'Perempuan', total: 2 }] }
    };
    for (const endpoint of ['/api/users', '/api/students', '/api/employees', '/api/school-rombels', '/api/education-schools', '/check-auth', '']) {
        assert.deepEqual(enrichReferenceResponse(payload, endpoint), payload, endpoint);
    }
});

test('known snake-case and camel-case relations enrich recursively across arbitrary wrappers', () => {
    const references = {
        employee_type: ['employee-types', 'Pendidik'], employeeType: ['employee-types', 'Pendidik'],
        employee_status: ['employee-statuses', 'Pegawai Negeri Sipil'], employeeStatus: ['employee-statuses', 'Pegawai Negeri Sipil'],
        employment: ['employments', 'Kepala Sekolah'], subject: ['subjects', 'Matematika'],
        gender: ['genders', 'Perempuan'], religion: ['religions', 'Kristen'],
        marital_status: ['marital-statuses', 'Menikah'], maritalStatus: ['marital-statuses', 'Menikah'],
        occupation: ['professions', 'Petani'], profession: ['professions', 'Petani'],
        education_level: ['education-levels', 'Strata II'], educationLevel: ['education-levels', 'Strata II'],
        student_status: ['student-statuses', 'Aktif'], studentStatus: ['student-statuses', 'Aktif'],
        student_entry: ['student-entries', 'Siswa Baru'], studentEntry: ['student-entries', 'Siswa Baru'],
        student_major: ['student-majors', 'Bahasa'], studentMajor: ['student-majors', 'Bahasa'],
        family_status: ['family-statuses', 'Yatim'], familyStatus: ['family-statuses', 'Yatim'],
        phase_status: ['phase-statuses', '2026 Tahap 1'], phaseStatus: ['phase-statuses', '2026 Tahap 1'],
        status_user: ['status-users', 'Tidak Aktif'], statusUser: ['status-users', 'Tidak Aktif'],
        semester: ['semesters', 'Ganjil']
    };
    const row = { name: 'Perempuan', ...Object.fromEntries(Object.entries(references).map(([key, [, name]]) => [key, { id: 9, name }])) };
    const result = enrichReferenceResponse({ data: [{ history: { data: [row] } }] }, '/api/employees');
    const enriched = result.data[0].history.data[0];
    assert.equal(enriched.name, 'Perempuan');
    assert.equal(enriched.display_name, undefined);
    for (const [key, [domain, name]] of Object.entries(references)) {
        assert.deepEqual(enriched[key], { id: 9, name, display_name: referenceLabel(domain, name) }, key);
    }
    assert.equal(row.gender.display_name, undefined);
});

test('parent references map without translating parent names or custom education records', () => {
    const data = { father_name: 'Petani', mother_name: 'Perempuan', guardian_name: 'Guru', education: { name: 'Strata II' } };
    for (const parent of ['father', 'mother', 'guardian', 'parent']) {
        data[`${parent}_profession`] = { id: 1, name: 'Petani' };
        data[`${parent}_education_level`] = { id: 2, name: 'Strata II' };
    }
    const result = enrichReferenceResponse({ data }, '/api/students/3').data;
    for (const parent of ['father', 'mother', 'guardian', 'parent']) {
        assert.equal(result[`${parent}_profession`].display_name, 'Farmer');
        assert.equal(result[`${parent}_education_level`].display_name, "Master's Degree");
    }
    assert.deepEqual(result.education, data.education);
    assert.equal(result.father_name, 'Petani');
});

test('scope does not leak from catalog rows into related users or metadata', () => {
    const payload = { data: [{ name: 'Petani', users: [{ name: 'Petani' }], metadata: { name: 'Petani' } }], message: 'Petani' };
    const result = enrichReferenceResponse(payload, '/api/professions');
    assert.equal(result.data[0].display_name, 'Farmer');
    assert.deepEqual(result.data[0].users, payload.data[0].users);
    assert.deepEqual(result.data[0].metadata, payload.data[0].metadata);
    assert.equal(result.message, 'Petani');
});

test('scoped relation arrays and recap labels enrich without changing counts or labels', () => {
    const payload = { data: { employee_types: { data: [{ id: 1, name: 'Pendidik' }] }, studentMajors: [{ name: 'Bahasa' }] }, recap: { by_gender: [{ label: 'Perempuan', total: 3 }], by_employee_type: [{ name: 'Pendidik', count: 2 }], other: [{ label: 'Perempuan' }] } };
    const result = enrichReferenceResponse(payload, '/api/students');
    assert.equal(result.data.employee_types.data[0].display_name, 'Teacher');
    assert.equal(result.data.studentMajors[0].display_name, 'Languages');
    assert.deepEqual(result.recap.by_gender[0], { label: 'Perempuan', total: 3, display_label: 'Female' });
    assert.deepEqual(result.recap.by_employee_type[0], { name: 'Pendidik', count: 2, display_name: 'Teacher' });
    assert.deepEqual(result.recap.other, payload.recap.other);
});

test('scalar directory refs and school statuses gain separate display fields', () => {
    const payload = { data: [{ name: 'Perempuan', gender: 'Perempuan', status_education: 'Swasta', employment: 'Kepala Sekolah' }] };
    assert.deepEqual(enrichReferenceResponse(payload, '/api/students').data[0], { ...payload.data[0], gender_display_name: 'Female', status_education_display_name: 'Private', employment_display_name: 'Principal' });
    assert.equal(payload.data[0].gender, 'Perempuan');
});

test('enrichment is idempotent and recomputes stale display labels after canonical edits', () => {
    const payload = { data: [{ name: 'Perempuan', gender: 'Perempuan', employee_type: { name: 'Pendidik' } }], recap: { by_gender: [{ label: 'Perempuan', total: 1 }] } };
    const once = enrichReferenceResponse(payload, '/api/employees');
    assert.deepEqual(enrichReferenceResponse(once, '/api/employees'), once);
    once.data[0].gender = 'Laki-Laki';
    once.data[0].employee_type.name = 'Tenaga Kependidikan';
    const updated = enrichReferenceResponse(once, '/api/employees');
    assert.equal(updated.data[0].gender_display_name, 'Male');
    assert.equal(updated.data[0].employee_type.display_name, 'Non-teaching Employee');
    assert.deepEqual(enrichReferenceResponse(updated, '/api/employees'), updated);
});

test('nulls and non-JSON downloads pass through untouched', () => {
    const blob = new Blob(['Perempuan']);
    const date = new Date('2026-10-01');
    for (const value of [null, undefined, 0, '', 'Perempuan', blob, date]) {
        assert.equal(enrichReferenceResponse(value, '/api/genders'), value);
    }
    assert.deepEqual(enrichReferenceResponse({ data: [null, { gender: null }] }, '/api/students'), { data: [null, { gender: null }] });
});

test('CSV callbacks translate only explicit reference fields and safely escape user values', () => {
    const exportCell = referenceExportFunction({ 'gender.name': 'genders', status_education: 'school-status' });
    assert.equal(exportCell({ field: 'gender.name', data: 'Perempuan' }), 'Female');
    assert.equal(exportCell({ field: 'status_education', data: 'Negeri' }), 'Public');
    assert.equal(exportCell({ field: 'name', data: 'Perempuan' }), 'Perempuan');
    assert.equal(exportCell({ field: 'address', data: 'A "quoted" address' }), 'A ""quoted"" address');
    assert.equal(exportCell({ field: 'username', data: 'Negeri' }), 'Negeri');
    assert.equal(exportCell({ field: 'constructor', data: 'Perempuan' }), 'Perempuan');
});

test('success interceptor enriches its response while preserving response metadata', async () => {
    const apiSource = await readFile(new URL('../src/apiLogin/index.js', import.meta.url), 'utf8');
    const match = apiSource.match(/interceptors\.response\.use\(\s*\(response\) => \{([\s\S]*?)\},\s*async \(error\)/);
    assert.ok(match, 'Expected a success response interceptor');
    const onSuccess = new Function('enrichReferenceResponse', 'response', match[1]);
    const response = { config: { url: '/api/genders' }, status: 200, data: { data: [{ id: 2, name: 'Perempuan' }] } };
    assert.equal(onSuccess(enrichReferenceResponse, response), response);
    assert.equal(response.status, 200);
    assert.equal(response.data.data[0].name, 'Perempuan');
    assert.equal(response.data.data[0].display_name, 'Female');
});

test('reference editors bind canonical names and school status submits canonical values', async () => {
    for (const view of ['EducationLevels', 'Profession', 'PhaseStatus', 'StudentMajors']) {
        const viewSource = await readFile(new URL(`../src/views/pages/public/${view}.vue`, import.meta.url), 'utf8');
        assert.match(viewSource, /v-model="singleData\.name"/);
        assert.doesNotMatch(viewSource, /v-model="singleData\.display_name"/);
        assert.match(viewSource, /:exportFunction="exportReferenceCell"/);
    }
    const schoolSource = await readFile(new URL('../src/views/pages/public/EducationSchools.vue', import.meta.url), 'utf8');
    assert.match(schoolSource, /v-model="singleData\.status_education"[^>]+optionLabel="display_name" optionValue="name"/);
    const manager = await readFile(new URL('../src/utils/useResourceManager.js', import.meta.url), 'utf8');
    assert.match(manager, /name: 'Negeri', display_name: 'Public'/);
    assert.match(manager, /name: 'Swasta', display_name: 'Private'/);
    assert.match(manager, /item\.display_name \?\? item\.name/);
});

test('successful account updates remove credential fields before caching the row', async () => {
    const source = await readFile(new URL('../src/utils/useResourceManager.js', import.meta.url), 'utf8');
    const block = source.match(/await apiLogin\.post\(`\/api\/\$\{endpoint\}\/\$\{singleData\.value\.id\}`[\s\S]*?manyDatas\.value\[idx\] = enrichReferenceResponse[^;]+;/);
    assert.ok(block);
    const execute = new Function('apiLogin', 'endpoint', 'singleData', 'manyDatas', 'idx', 'enrichReferenceResponse', `return (async () => { ${block[0]} })();`);
    const singleData = { value: { id: 1, name: 'Synthetic account', password: 'synthetic-old-secret', password_new: 'synthetic-new-secret' } };
    const manyDatas = { value: [] };
    let submitted;
    await execute({ post: async (_, payload) => { submitted = { ...payload }; } }, 'users', singleData, manyDatas, 0, enrichReferenceResponse);
    assert.equal(submitted.password_new, 'synthetic-new-secret');
    assert.equal(Object.hasOwn(manyDatas.value[0], 'password'), false);
    assert.equal(Object.hasOwn(manyDatas.value[0], 'password_new'), false);
    assert.equal(manyDatas.value[0].name, 'Synthetic account');
});
