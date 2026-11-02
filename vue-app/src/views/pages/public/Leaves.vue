<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import apiLogin from '@/apiLogin';
import { authStore } from '@/stores/authStore';

const store = authStore();
const toast = useToast();

const canView = computed(() => store.can('leaves.view'));
const canRequest = computed(() => store.can('leaves.request'));
const canManage = computed(() => store.can('leaves.manage'));

const statusLabels = {
    draft: 'Draft',
    submitted: 'Submitted',
    approved: 'Approved',
    rejected: 'Rejected',
    cancelled: 'Cancelled'
};

const statusSeverity = {
    draft: 'secondary',
    submitted: 'info',
    approved: 'success',
    rejected: 'danger',
    cancelled: 'warn'
};

const decisionLabels = {
    submitted: 'Submitted',
    approved: 'Approved',
    rejected: 'Rejected',
    cancelled: 'Cancelled'
};

const decisionSeverity = {
    submitted: 'info',
    approved: 'success',
    rejected: 'danger',
    cancelled: 'warn'
};

const decisionIcons = {
    submitted: 'pi pi-send',
    approved: 'pi pi-check-circle',
    rejected: 'pi pi-times-circle',
    cancelled: 'pi pi-ban'
};

const options = ref(null);
const optionsError = ref('');
const activeTab = ref('requests');
const items = ref([]);
const meta = ref({ current_page: 1, per_page: 20, total: 0, last_page: 1 });
const listLoading = ref(false);
const listError = ref('');
const page = ref(1);

const filters = reactive({
    search: '',
    status: 'all',
    type: 'all',
    subjectType: 'all',
    dateFrom: null,
    dateTo: null,
    perPage: 20
});

let searchTimer = null;
let listRequestId = 0;
let lastDateFilterKey = '|';

const selfEmployeeOption = computed(() => {
    const employee = options.value?.self_employee;
    if (!employee) return null;

    return { id: employee.id, label: employeeSubjectLabel(employee) };
});

const activeSchoolYear = computed(() => options.value?.active_school_year ?? null);
const activeSchoolYearLabel = computed(() => {
    const year = activeSchoolYear.value;
    if (!year) return '';
    return `${year.name} (${formatDate(year.start_date)} – ${formatDate(year.end_date)})`;
});

const subjectTypeOptions = computed(() => options.value?.subject_types ?? []);
const statusFilterOptions = computed(() => [{ label: 'All statuses', value: 'all' }, ...(options.value?.statuses ?? []).map((status) => ({ label: status.label, value: status.value }))]);
const perPageOptions = computed(() => options.value?.per_page_options ?? [10, 20, 50, 100]);
const maxFiles = computed(() => options.value?.attachment?.max_files_per_request ?? 3);
const maxFileKb = computed(() => options.value?.attachment?.max_file_kb ?? 5120);
const allowedExtensions = computed(() => options.value?.attachment?.allowed_extensions ?? ['pdf', 'jpg', 'jpeg', 'png']);
const acceptAttr = computed(() => allowedExtensions.value.map((extension) => `.${extension}`).join(','));
const maxFileLabel = computed(() => `${Math.floor(maxFileKb.value / 1024)} MB`);
const reasonMin = computed(() => options.value?.reason?.min ?? 5);
const reasonMax = computed(() => options.value?.reason?.max ?? 1000);
const cancelMin = computed(() => options.value?.cancel_reason?.min ?? 10);
const cancelMax = computed(() => options.value?.cancel_reason?.max ?? 255);
const optionsReady = computed(() => options.value !== null);

const typeFilterOptions = computed(() => {
    const source = filters.subjectType === 'all' ? Object.values(options.value?.types ?? {}).flat() : (options.value?.types?.[filters.subjectType] ?? []);
    const seen = new Set();
    const unique = [];

    for (const type of source) {
        if (seen.has(type.code)) continue;
        seen.add(type.code);
        unique.push({ label: type.label, value: type.code });
    }

    return [{ label: 'All types', value: 'all' }, ...unique];
});

const totalLabel = computed(() => `${meta.value.total} ${meta.value.total === 1 ? 'request' : 'requests'}`);
const emptyMessage = computed(() => (activeTab.value === 'mine' ? 'You have no self-service leave requests matching the current filters.' : 'No leave requests match the current filters.'));

const notify = (severity, detail) => {
    toast.add({ severity, summary: 'Leaves', detail, life: 5000 });
};

const errorMessage = (error, fallback) => error.response?.data?.message ?? fallback;

const validationErrors = (error) => {
    const data = error.response?.data ?? {};
    return data.errors && typeof data.errors === 'object' ? data.errors : {};
};

const fieldError = (errors, field) => {
    const value = errors?.[field];
    if (Array.isArray(value)) return value[0] ?? '';
    return typeof value === 'string' ? value : '';
};

const errorList = (errors) =>
    Object.values(errors ?? {})
        .flat()
        .filter((message) => typeof message === 'string' && message !== '');

const todayIso = () => {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${now.getFullYear()}-${month}-${day}`;
};

const dateToIso = (value) => {
    if (!value) return null;
    if (typeof value === 'string') return value.slice(0, 10) || null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${date.getFullYear()}-${month}-${day}`;
};

const isoToDate = (value) => {
    if (!value) return null;
    const [year, month, day] = String(value).slice(0, 10).split('-').map(Number);
    if (!year || !month || !day) return null;
    return new Date(year, month - 1, day);
};

const formatDate = (value) => {
    if (!value) return '-';
    const raw = String(value).slice(0, 10);
    const parsed = new Date(`${raw}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) return raw;
    return parsed.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatDateTime = (value) => {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return String(value);
    return parsed.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const formatSize = (value) => {
    const bytes = Number(value);
    if (!Number.isFinite(bytes) || bytes < 0) return '-';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const inclusiveDays = (start, end) => {
    if (!start || !end) return null;
    const startDate = new Date(`${start}T00:00:00`);
    const endDate = new Date(`${end}T00:00:00`);
    if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) return null;
    return Math.round((endDate.getTime() - startDate.getTime()) / 86400000) + 1;
};

const subjectTypeLabel = (value) => options.value?.subject_types?.find((type) => type.value === value)?.label ?? (value === 'student' ? 'Student' : 'Employee');

const studentSubjectLabel = (subject) => {
    if (!subject) return '';
    const details = [subject.nis ? `NIS ${subject.nis}` : null, subject.class].filter(Boolean).join(' · ');
    return details ? `${subject.name} — ${details}` : subject.name;
};

const employeeSubjectLabel = (subject) => {
    if (!subject) return '';
    const details = [subject.nuptk ? `NUPTK ${subject.nuptk}` : null, subject.employee_type].filter(Boolean).join(' · ');
    return details ? `${subject.name} — ${details}` : subject.name;
};

const subjectLabel = (subjectType, subject) => (subjectType === 'student' ? studentSubjectLabel(subject) : employeeSubjectLabel(subject));

const subjectMeta = (row) => {
    const subject = row?.subject;
    if (!subject) return '';

    if (row.subject_type === 'student') {
        return [subject.nis ? `NIS ${subject.nis}` : null, subject.class, subject.school_year].filter(Boolean).join(' · ');
    }

    return [subject.nuptk ? `NUPTK ${subject.nuptk}` : null, subject.employee_type].filter(Boolean).join(' · ');
};

const statusLabel = (value) => statusLabels[value] ?? value ?? '-';
const statusTagSeverity = (value) => statusSeverity[value] ?? 'secondary';
const decisionLabel = (value) => decisionLabels[value] ?? value ?? '-';
const decisionTagSeverity = (value) => decisionSeverity[value] ?? 'secondary';
const decisionIcon = (value) => decisionIcons[value] ?? 'pi pi-history';

const loadOptions = async () => {
    optionsError.value = '';
    try {
        const response = await apiLogin.get('/api/leaves/options');
        options.value = response.data?.data ?? null;
        filters.perPage = options.value?.default_per_page ?? filters.perPage;
    } catch (error) {
        options.value = null;
        optionsError.value = errorMessage(error, 'Failed to load leave options. Some actions may be unavailable.');
    }
};

const listEndpoint = () => (activeTab.value === 'mine' ? '/api/leaves/mine' : '/api/leaves');

const loadList = async () => {
    if ((activeTab.value === 'requests' && !canView.value) || (activeTab.value === 'mine' && !canRequest.value)) {
        items.value = [];
        return;
    }

    const requestId = ++listRequestId;
    listLoading.value = true;
    listError.value = '';

    try {
        const params = { page: page.value, per_page: filters.perPage, status: filters.status };
        if (filters.search.trim()) params.search = filters.search.trim();
        if (filters.type !== 'all') params.type = filters.type;
        if (activeTab.value === 'requests' && filters.subjectType !== 'all') params.subject_type = filters.subjectType;

        const from = dateToIso(filters.dateFrom);
        const to = dateToIso(filters.dateTo);
        if (from) params.date_from = from;
        if (to) params.date_to = to;

        const response = await apiLogin.get(listEndpoint(), { params });
        if (requestId !== listRequestId) return;

        const data = response.data?.data ?? {};
        items.value = data.items ?? [];
        meta.value = data.meta ?? { current_page: 1, per_page: filters.perPage, total: 0, last_page: 1 };
        if (meta.value.per_page) filters.perPage = meta.value.per_page;

        if (items.value.length === 0 && page.value > 1 && page.value > meta.value.last_page) {
            page.value = Math.max(1, meta.value.last_page);
            return loadList();
        }
    } catch (error) {
        if (requestId !== listRequestId) return;
        items.value = [];
        listError.value = errorMessage(error, 'Failed to load leave requests. Please try again.');
    } finally {
        if (requestId === listRequestId) listLoading.value = false;
    }
};

const onSearchInput = () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        page.value = 1;
        loadList();
    }, 300);
};

const onFilterChange = () => {
    page.value = 1;
    loadList();
};

const onSubjectFilterChange = () => {
    if (!typeFilterOptions.value.some((option) => option.value === filters.type)) {
        filters.type = 'all';
    }
    onFilterChange();
};

const onDateFilterChange = () => {
    const key = `${dateToIso(filters.dateFrom) ?? ''}|${dateToIso(filters.dateTo) ?? ''}`;
    if (key === lastDateFilterKey) return;
    lastDateFilterKey = key;
    onFilterChange();
};

const resetFilters = () => {
    filters.search = '';
    filters.status = 'all';
    filters.type = 'all';
    filters.subjectType = 'all';
    filters.dateFrom = null;
    filters.dateTo = null;
    lastDateFilterKey = '|';
    page.value = 1;
    loadList();
};

const onTabChange = (value) => {
    if (value === activeTab.value) return;
    activeTab.value = value;
    resetFilters();
};

const onPage = (event) => {
    const rowsChanged = event.rows !== filters.perPage;
    page.value = rowsChanged ? 1 : event.page + 1;
    if (rowsChanged) filters.perPage = event.rows;
    loadList();
};

const formVisible = ref(false);
const formMode = ref('create');
const formSelfOnly = ref(false);
const formSaving = ref(false);
const formErrors = ref({});
const formError = ref('');
const form = reactive({
    id: null,
    version: null,
    subjectType: 'employee',
    subject: null,
    type: null,
    startDate: null,
    endDate: null,
    reason: ''
});

const subjectSuggestions = ref([]);
const subjectLoading = ref(false);
const subjectError = ref('');
let subjectRequestId = 0;
let subjectSearchTimer = null;

const formTypeOptions = computed(() => (options.value?.types?.[form.subjectType] ?? []).map((type) => ({ label: type.label, value: type.code, max_days: type.max_days })));

const formSelectedType = computed(() => (options.value?.types?.[form.subjectType] ?? []).find((type) => type.code === form.type) ?? null);

const formSubjectError = computed(() => fieldError(formErrors.value, form.subjectType === 'student' ? 'student_id' : 'employee_id'));

const firstTypeFor = (subjectType) => (options.value?.types?.[subjectType] ?? [])[0]?.code ?? null;

const defaultDatesFor = (subjectType) => {
    if (subjectType === 'student' && activeSchoolYear.value) {
        const today = todayIso();
        const year = activeSchoolYear.value;
        const date = today < year.start_date ? year.start_date : today > year.end_date ? year.end_date : today;
        return { start: isoToDate(date), end: isoToDate(date) };
    }

    const today = isoToDate(todayIso());
    return { start: today, end: today };
};

const formSubjectEndpoint = () => (form.subjectType === 'student' ? '/api/leaves/subjects/students' : '/api/leaves/subjects/employees');

const mapSubjectSuggestion = (item) => ({
    id: item.id,
    label: subjectLabel(form.subjectType, item),
    raw: item
});

const fetchSubjects = async (query = '') => {
    if (!canManage.value) return;

    const requestId = ++subjectRequestId;
    subjectLoading.value = true;
    subjectError.value = '';

    try {
        const response = await apiLogin.get(formSubjectEndpoint(), {
            params: { limit: 20, search: query.trim() || undefined }
        });
        if (requestId !== subjectRequestId) return;
        const current = form.subject?.id ? form.subject : null;
        const results = (response.data?.data?.items ?? []).map(mapSubjectSuggestion).filter((option) => !current || option.id !== current.id);
        subjectSuggestions.value = current ? [current, ...results] : results;
    } catch (error) {
        if (requestId !== subjectRequestId) return;
        subjectSuggestions.value = form.subject?.id ? [form.subject] : [];
        subjectError.value = errorMessage(error, 'Failed to load subject options.');
    } finally {
        if (requestId === subjectRequestId) subjectLoading.value = false;
    }
};

const searchSubjects = (event) => {
    const query = event?.query ?? '';
    if (subjectSearchTimer) clearTimeout(subjectSearchTimer);
    subjectSearchTimer = setTimeout(() => fetchSubjects(query), 250);
};

const onSubjectTypeChange = () => {
    if (formSelfOnly.value) return;

    form.subject = null;
    form.type = firstTypeFor(form.subjectType);
    subjectSuggestions.value = [];
    subjectError.value = '';

    if (formVisible.value && canManage.value) fetchSubjects('');
};

const resetFormErrors = () => {
    formErrors.value = {};
    formError.value = '';
};

const openCreate = (selfOnly = false) => {
    if (!optionsReady.value) {
        notify('error', 'Leave options are not available. Reload the page or retry.');
        return;
    }

    const subjectType = selfOnly ? 'employee' : canManage.value ? (activeSchoolYear.value ? 'student' : 'employee') : 'employee';
    const dates = defaultDatesFor(subjectType);

    formMode.value = 'create';
    formSelfOnly.value = selfOnly;
    form.id = null;
    form.version = null;
    form.subjectType = subjectType;
    form.subject = selfOnly ? selfEmployeeOption.value : null;
    form.type = firstTypeFor(subjectType);
    form.startDate = dates.start;
    form.endDate = dates.end;
    form.reason = '';
    subjectSuggestions.value = form.subject ? [form.subject] : [];
    subjectError.value = '';
    resetFormErrors();
    formSaving.value = false;
    detailVisible.value = false;
    formVisible.value = true;

    if (canManage.value && !selfOnly) fetchSubjects('');
};

const startEdit = async (source) => {
    if (!source?.id) return;
    if (!optionsReady.value) {
        notify('error', 'Leave options are not available. Reload the page or retry.');
        return;
    }

    try {
        const response = await apiLogin.get(`/api/leaves/${source.id}`);
        const data = response.data?.data;
        if (!data?.can?.edit) {
            notify('warn', 'This leave request can no longer be edited.');
            await loadList();
            return;
        }

        formMode.value = 'edit';
        formSelfOnly.value = false;
        form.id = data.id;
        form.version = data.version;
        form.subjectType = data.subject_type;
        form.subject = data.subject ? { id: data.subject.id, label: subjectLabel(data.subject_type, data.subject), raw: data.subject } : null;
        form.type = data.type;
        form.startDate = isoToDate(data.start_date);
        form.endDate = isoToDate(data.end_date);
        form.reason = data.reason ?? '';
        subjectSuggestions.value = form.subject ? [form.subject] : [];
        subjectError.value = '';
        resetFormErrors();
        formSaving.value = false;
        detailVisible.value = false;
        formVisible.value = true;

        if (canManage.value) fetchSubjects('');
    } catch (error) {
        if (error.response?.status === 404) {
            notify('warn', 'This leave request no longer exists. The list has been refreshed.');
            await loadList();
        } else {
            notify('error', errorMessage(error, 'Failed to load the leave request.'));
        }
    }
};

const validateForm = () => {
    const errors = {};
    const start = dateToIso(form.startDate);
    const end = dateToIso(form.endDate);
    const reason = form.reason.trim();

    if (!form.subjectType) errors.subject_type = ['Select whether this leave is for a student or an employee.'];
    if (!form.subject?.id) {
        errors[form.subjectType === 'student' ? 'student_id' : 'employee_id'] = [form.subjectType === 'student' ? 'Select a student for this leave request.' : 'Select an employee for this leave request.'];
    }
    if (!form.type) errors.type = ['Select a leave type.'];

    if (!start) errors.start_date = ['The start date is required.'];
    if (!end) errors.end_date = ['The end date is required.'];
    else if (start && end < start) errors.end_date = ['The end date may not be before the start date.'];
    else if (start) {
        const days = inclusiveDays(start, end);
        const definition = formSelectedType.value;

        if (definition && days > definition.max_days) {
            errors.end_date = [`${definition.label} allows at most ${definition.max_days} days.`];
        }

        if (form.subjectType === 'student') {
            const year = activeSchoolYear.value;
            if (!year) {
                errors.school_year = ['No active school year is configured. Set it in School Years before recording student leave.'];
            } else if (start < year.start_date || end > year.end_date) {
                errors.start_date = [`Student leave dates must fall within ${activeSchoolYearLabel.value}.`];
            }
        }
    }

    if (!reason) errors.reason = ['A reason is required.'];
    else if (reason.length < reasonMin.value) errors.reason = [`The reason must be at least ${reasonMin.value} characters.`];
    else if (reason.length > reasonMax.value) errors.reason = [`The reason may not exceed ${reasonMax.value} characters.`];

    return errors;
};

const submitForm = async () => {
    if (formSaving.value) return;

    resetFormErrors();
    const errors = validateForm();
    if (Object.keys(errors).length > 0) {
        formErrors.value = errors;
        return;
    }

    const payload = {
        subject_type: form.subjectType,
        student_id: form.subjectType === 'student' ? form.subject?.id : null,
        employee_id: form.subjectType === 'employee' ? form.subject?.id : null,
        type: form.type,
        start_date: dateToIso(form.startDate),
        end_date: dateToIso(form.endDate),
        reason: form.reason.trim()
    };

    const editing = formMode.value === 'edit';
    if (editing) payload.expected_version = form.version;

    formSaving.value = true;
    try {
        const response = editing ? await apiLogin.put(`/api/leaves/${form.id}`, payload) : await apiLogin.post('/api/leaves', payload);
        const data = response.data?.data ?? null;

        formVisible.value = false;
        notify('success', response.data?.message ?? 'Leave request saved.');
        await loadList();

        if (data) {
            detail.value = data;
            detailVisible.value = true;
        }
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            formErrors.value = validationErrors(error);
            if (Object.keys(formErrors.value).length === 0) formError.value = errorMessage(error, 'The leave request could not be saved.');
        } else if (status === 409) {
            formVisible.value = false;
            openConflict(error, form.id);
        } else {
            formError.value = errorMessage(error, 'Failed to save the leave request.');
        }
    } finally {
        formSaving.value = false;
    }
};

const detailVisible = ref(false);
const detailLoading = ref(false);
const detailError = ref('');
const detail = ref(null);
let detailRequestId = 0;

const fileInput = ref(null);
const selectedFile = ref(null);
const uploading = ref(false);
const uploadError = ref('');

const resetAttachmentInput = () => {
    selectedFile.value = null;
    uploadError.value = '';
    if (fileInput.value) fileInput.value.value = '';
};

const openDetail = async (row) => {
    if (!row?.id) return;

    detailVisible.value = true;
    detailLoading.value = true;
    detailError.value = '';
    detail.value = null;
    resetAttachmentInput();

    const requestId = ++detailRequestId;
    try {
        const response = await apiLogin.get(`/api/leaves/${row.id}`);
        if (requestId !== detailRequestId) return;
        detail.value = response.data?.data ?? null;
    } catch (error) {
        if (requestId !== detailRequestId) return;
        detail.value = null;
        detailError.value = error.response?.status === 404 ? 'This leave request no longer exists. Refresh the list.' : errorMessage(error, 'Failed to load the leave request.');
    } finally {
        if (requestId === detailRequestId) detailLoading.value = false;
    }
};

const submitVisible = ref(false);
const submitTarget = ref(null);
const submitSaving = ref(false);
const submitErrors = ref({});
const submitError = ref('');

const submitErrorList = computed(() => errorList(submitErrors.value));

const openSubmit = (source) => {
    if (!source) return;
    submitTarget.value = {
        id: source.id,
        version: source.version,
        subject: subjectLabel(source.subject_type, source.subject),
        start_date: source.start_date,
        end_date: source.end_date
    };
    submitErrors.value = {};
    submitError.value = '';
    submitSaving.value = false;
    detailVisible.value = false;
    submitVisible.value = true;
};

const confirmSubmit = async () => {
    if (submitSaving.value || !submitTarget.value) return;

    submitSaving.value = true;
    submitErrors.value = {};
    submitError.value = '';

    try {
        const response = await apiLogin.post(`/api/leaves/${submitTarget.value.id}/submit`, {
            expected_version: submitTarget.value.version
        });
        const data = response.data?.data ?? null;

        submitVisible.value = false;
        notify('success', response.data?.message ?? 'Leave request submitted.');
        await loadList();

        if (data) {
            detail.value = data;
            detailVisible.value = true;
        }
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            submitErrors.value = validationErrors(error);
            if (Object.keys(submitErrors.value).length === 0) submitError.value = errorMessage(error, 'The leave request could not be submitted.');
        } else if (status === 409) {
            submitVisible.value = false;
            openConflict(error, submitTarget.value.id);
        } else {
            submitError.value = errorMessage(error, 'The leave request could not be submitted.');
        }
    } finally {
        submitSaving.value = false;
    }
};

const cancelVisible = ref(false);
const cancelTarget = ref(null);
const cancelSaving = ref(false);
const cancelReason = ref('');
const cancelErrors = ref({});
const cancelError = ref('');

const cancelRequiresReason = computed(() => cancelTarget.value?.status === 'submitted');
const cancelErrorList = computed(() => errorList(cancelErrors.value));

const openCancel = (source) => {
    if (!source) return;
    cancelTarget.value = {
        id: source.id,
        version: source.version,
        status: source.status,
        subject: subjectLabel(source.subject_type, source.subject),
        start_date: source.start_date,
        end_date: source.end_date
    };
    cancelReason.value = '';
    cancelErrors.value = {};
    cancelError.value = '';
    cancelSaving.value = false;
    detailVisible.value = false;
    cancelVisible.value = true;
};

const confirmCancel = async () => {
    if (cancelSaving.value || !cancelTarget.value) return;

    const reason = cancelReason.value.trim();
    cancelErrors.value = {};
    cancelError.value = '';

    if (cancelRequiresReason.value && reason.length < cancelMin.value) {
        cancelErrors.value = { reason: [`A cancellation reason of at least ${cancelMin.value} characters is required for submitted requests.`] };
        return;
    }

    if (reason !== '' && (reason.length < cancelMin.value || reason.length > cancelMax.value)) {
        cancelErrors.value = { reason: [`The cancellation reason must be between ${cancelMin.value} and ${cancelMax.value} characters.`] };
        return;
    }

    const payload = { expected_version: cancelTarget.value.version };
    if (reason !== '') payload.reason = reason;

    cancelSaving.value = true;
    try {
        const response = await apiLogin.post(`/api/leaves/${cancelTarget.value.id}/cancel`, payload);
        const data = response.data?.data ?? null;

        cancelVisible.value = false;
        notify('success', response.data?.message ?? 'Leave request cancelled.');
        await loadList();

        if (data) {
            detail.value = data;
            detailVisible.value = true;
        }
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            cancelErrors.value = validationErrors(error);
            if (Object.keys(cancelErrors.value).length === 0) cancelError.value = errorMessage(error, 'The leave request could not be cancelled.');
        } else if (status === 409) {
            cancelVisible.value = false;
            openConflict(error, cancelTarget.value.id);
        } else {
            cancelError.value = errorMessage(error, 'The leave request could not be cancelled.');
        }
    } finally {
        cancelSaving.value = false;
    }
};

const onFileSelected = (event) => {
    selectedFile.value = event.target.files?.[0] ?? null;
    uploadError.value = '';
};

const uploadAttachment = async () => {
    if (!detail.value || !selectedFile.value || uploading.value) return;

    const file = selectedFile.value;
    const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

    uploadError.value = '';

    if (!allowedExtensions.value.includes(extension)) {
        uploadError.value = `Only ${allowedExtensions.value.join(', ').toUpperCase()} files are allowed.`;
        return;
    }

    if (file.size > maxFileKb.value * 1024) {
        uploadError.value = `The file may not exceed ${maxFileLabel.value}.`;
        return;
    }

    if ((detail.value.attachments?.length ?? 0) >= maxFiles.value) {
        uploadError.value = `A leave request can have at most ${maxFiles.value} attachments.`;
        return;
    }

    const body = new FormData();
    body.append('file', file);

    uploading.value = true;
    try {
        const response = await apiLogin.post(`/api/leaves/${detail.value.id}/attachments`, body);
        detail.value = response.data?.data ?? detail.value;
        resetAttachmentInput();
        notify('success', response.data?.message ?? 'Attachment uploaded.');
        await loadList();
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            uploadError.value = fieldError(validationErrors(error), 'file') || errorMessage(error, 'The file could not be uploaded.');
        } else if (status === 409) {
            detailVisible.value = false;
            openConflict(error, detail.value.id);
        } else {
            uploadError.value = errorMessage(error, 'Failed to upload the attachment.');
        }
    } finally {
        uploading.value = false;
    }
};

const downloadAttachment = async (attachment) => {
    if (!detail.value) return;

    try {
        const response = await apiLogin.get(`/api/leaves/${detail.value.id}/attachments/${attachment.id}/download`, {
            responseType: 'blob'
        });
        const blob = new Blob([response.data], { type: attachment.mime_type || 'application/octet-stream' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = attachment.original_name || 'attachment';
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    } catch {
        notify('error', 'Failed to download the attachment.');
    }
};

const removeVisible = ref(false);
const removeSaving = ref(false);
const removeTarget = ref(null);
const removeError = ref('');

const openRemoveAttachment = (attachment) => {
    if (!detail.value?.can?.delete_attachment) return;

    removeTarget.value = { leaveId: detail.value.id, attachment };
    removeError.value = '';
    removeSaving.value = false;
    removeVisible.value = true;
};

const confirmRemoveAttachment = async () => {
    if (removeSaving.value || !removeTarget.value) return;

    const { leaveId, attachment } = removeTarget.value;
    removeSaving.value = true;
    removeError.value = '';

    try {
        const response = await apiLogin.delete(`/api/leaves/${leaveId}/attachments/${attachment.id}`);
        detail.value = response.data?.data ?? detail.value;
        removeVisible.value = false;
        notify('success', response.data?.message ?? 'Attachment removed.');
        await loadList();
    } catch (error) {
        if (error.response?.status === 409) {
            removeVisible.value = false;
            detailVisible.value = false;
            openConflict(error, leaveId);
        } else {
            removeError.value = errorMessage(error, 'Failed to remove the attachment.');
        }
    } finally {
        removeSaving.value = false;
    }
};

const conflictVisible = ref(false);
const conflictMessage = ref('');
const conflictTargetId = ref(null);
const reloadingConflict = ref(false);

const openConflict = (error, targetId) => {
    conflictMessage.value = errorMessage(error, 'This leave request was changed by another request.');
    conflictTargetId.value = targetId ?? null;
    conflictVisible.value = true;
};

const reloadLatest = async () => {
    if (reloadingConflict.value) return;
    reloadingConflict.value = true;

    const targetId = conflictTargetId.value;
    try {
        if (targetId) {
            const response = await apiLogin.get(`/api/leaves/${targetId}`);
            detail.value = response.data?.data ?? null;
            detailError.value = '';
            detailVisible.value = true;
            notify('warn', 'The latest version has been loaded. Review the request before trying again.');
        }
        conflictVisible.value = false;
        await loadList();
    } catch (error) {
        if (error.response?.status === 404) {
            conflictVisible.value = false;
            detailVisible.value = false;
            formVisible.value = false;
            submitVisible.value = false;
            cancelVisible.value = false;
            await loadList();
            notify('warn', 'The leave request no longer exists. The list has been refreshed.');
        } else {
            notify('error', errorMessage(error, 'Failed to reload the latest leave request.'));
        }
    } finally {
        reloadingConflict.value = false;
    }
};

onMounted(async () => {
    await store.fetchIdentity();
    await loadOptions();
    activeTab.value = canView.value ? 'requests' : 'mine';

    if (canView.value || canRequest.value) await loadList();
});
</script>

<template>
    <Fluid>
        <div class="bg-surface-0 dark:bg-surface-950 px-4 py-6 sm:px-6 sm:py-8 md:px-12 lg:px-20">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <div class="font-bold text-2xl sm:text-3xl text-surface-900 dark:text-surface-0 mb-2">Leaves</div>
                    <p class="text-surface-500 dark:text-surface-400">Submit student and employee leave requests, attach supporting files, and track their status.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Tag :value="totalLabel" severity="secondary" />
                    <Button v-if="activeTab === 'requests' && canManage" label="New Request" icon="pi pi-plus" :disabled="!optionsReady" @click="openCreate(false)" />
                    <Button v-if="activeTab === 'mine' && canRequest && selfEmployeeOption" label="New Self Request" icon="pi pi-plus" :disabled="!optionsReady" @click="openCreate(true)" />
                </div>
            </div>

            <div v-if="optionsError" class="mt-4 flex flex-col sm:flex-row sm:items-center gap-3 rounded border border-orange-300 dark:border-orange-700 bg-orange-50 dark:bg-orange-950 px-4 py-3 text-sm text-orange-700 dark:text-orange-300">
                <span class="flex-1">{{ optionsError }}</span>
                <Button label="Retry" icon="pi pi-refresh" size="small" severity="secondary" variant="outlined" @click="loadOptions" />
            </div>

            <Message v-if="!canView && !canRequest" severity="error" :closable="false" class="mt-4"> You do not have access to leave requests. </Message>

            <template v-else>
                <Tabs :value="activeTab" class="mt-6" @update:value="onTabChange">
                    <TabList>
                        <Tab v-if="canView" value="requests">Requests</Tab>
                        <Tab v-if="canRequest" value="mine">My Leaves</Tab>
                    </TabList>
                </Tabs>

                <div class="text-sm text-surface-500 dark:text-surface-400 mt-2">
                    {{ activeTab === 'requests' ? 'All leave requests visible to your role.' : 'Leave requests linked to your employee account.' }}
                </div>

                <Message v-if="activeTab === 'mine' && canRequest && !selfEmployeeOption" severity="warn" :closable="false" class="mt-4">
                    Your account is not linked to an employee record. Contact an administrator to link it before requesting leave.
                </Message>

                <div class="card mt-4 flex flex-col gap-4">
                    <div class="flex flex-col xl:flex-row xl:items-center xl:flex-wrap gap-3">
                        <IconField class="w-full xl:max-w-sm">
                            <InputIcon><i class="pi pi-search" /></InputIcon>
                            <InputText v-model="filters.search" placeholder="Search name, NIS, NISN, or NUPTK" class="w-full" maxlength="100" aria-label="Search leave requests" @input="onSearchInput" />
                        </IconField>
                        <Select v-model="filters.status" :options="statusFilterOptions" optionLabel="label" optionValue="value" class="w-full xl:w-44" aria-label="Leave status" @change="onFilterChange" />
                        <Select
                            v-if="activeTab === 'requests'"
                            v-model="filters.subjectType"
                            :options="[{ label: 'All subject types', value: 'all' }, ...subjectTypeOptions]"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full xl:w-44"
                            aria-label="Subject type"
                            @change="onSubjectFilterChange"
                        />
                        <Select v-model="filters.type" :options="typeFilterOptions" optionLabel="label" optionValue="value" class="w-full xl:w-48" aria-label="Leave type" @change="onFilterChange" />
                        <DatePicker
                            v-model="filters.dateFrom"
                            placeholder="From date"
                            dateFormat="yy-mm-dd"
                            showIcon
                            manualInput
                            fluid
                            class="w-full xl:w-40"
                            aria-label="Leave date from"
                            @date-select="onDateFilterChange"
                            @blur="onDateFilterChange"
                        />
                        <DatePicker v-model="filters.dateTo" placeholder="To date" dateFormat="yy-mm-dd" showIcon manualInput fluid class="w-full xl:w-40" aria-label="Leave date to" @date-select="onDateFilterChange" @blur="onDateFilterChange" />
                        <div class="flex items-center gap-2 xl:ml-auto">
                            <Select v-model="filters.perPage" :options="perPageOptions" class="w-28" :aria-label="`Rows: ${filters.perPage}`" @change="onFilterChange" />
                            <Button label="Clear" icon="pi pi-filter-slash" severity="secondary" variant="outlined" @click="resetFilters" />
                        </div>
                    </div>

                    <DataTable
                        :value="items"
                        :lazy="true"
                        paginator
                        showGridlines
                        scrollable
                        :rows="filters.perPage"
                        :totalRecords="meta.total"
                        :first="(meta.current_page - 1) * meta.per_page"
                        :rowsPerPageOptions="perPageOptions"
                        dataKey="id"
                        :loading="listLoading"
                        tableStyle="min-width: 76rem"
                        paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                        currentPageReportTemplate="Showing {first} to {last} of {totalRecords} requests"
                        @page="onPage"
                    >
                        <template #loading>Loading leave requests. Please wait.</template>
                        <template #empty>
                            <div class="flex flex-col items-center gap-3 py-6">
                                <span v-if="listError" class="text-red-500">{{ listError }}</span>
                                <span v-else>{{ emptyMessage }}</span>
                                <Button v-if="listError" icon="pi pi-refresh" label="Retry" @click="loadList" />
                            </div>
                        </template>
                        <Column header="Subject" style="min-width: 16rem">
                            <template #body="{ data }">
                                <div class="font-medium truncate" :title="data.subject?.name ?? ''">{{ data.subject?.name ?? 'Unknown subject' }}</div>
                                <div class="text-xs text-surface-500 truncate" :title="subjectMeta(data)">{{ subjectMeta(data) || '-' }}</div>
                            </template>
                        </Column>
                        <Column header="Type" style="min-width: 11rem">
                            <template #body="{ data }">
                                <div class="font-medium">{{ data.type_label ?? data.type }}</div>
                                <div class="text-xs text-surface-500">{{ subjectTypeLabel(data.subject_type) }}</div>
                            </template>
                        </Column>
                        <Column header="Dates" style="min-width: 14rem">
                            <template #body="{ data }">
                                <div>{{ formatDate(data.start_date) }} – {{ formatDate(data.end_date) }}</div>
                                <div class="text-xs text-surface-500">{{ data.days }} {{ data.days === 1 ? 'day' : 'days' }}</div>
                            </template>
                        </Column>
                        <Column header="Status" style="min-width: 9rem">
                            <template #body="{ data }">
                                <Tag :value="statusLabel(data.status)" :severity="statusTagSeverity(data.status)" />
                                <div class="text-xs text-surface-500 mt-1">Version {{ data.version }}</div>
                            </template>
                        </Column>
                        <Column header="Requested by" style="min-width: 12rem">
                            <template #body="{ data }">
                                <span class="block truncate" :title="data.requested_by?.name ?? ''">{{ data.requested_by?.name ?? '-' }}</span>
                            </template>
                        </Column>
                        <Column header="Files" style="min-width: 5rem">
                            <template #body="{ data }">{{ data.attachments_count ?? 0 }}</template>
                        </Column>
                        <Column header="Actions" style="min-width: 10rem">
                            <template #body="{ data }">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Button icon="pi pi-eye" aria-label="View leave request" variant="outlined" rounded v-tooltip.top="'View'" @click="openDetail(data)" />
                                    <Button v-if="data.can?.edit" icon="pi pi-pencil" aria-label="Edit draft leave request" variant="outlined" rounded v-tooltip.top="'Edit'" @click="startEdit(data)" />
                                    <Button v-if="data.can?.submit" icon="pi pi-send" aria-label="Submit draft leave request" severity="info" variant="outlined" rounded v-tooltip.top="'Submit'" @click="openSubmit(data)" />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </template>
        </div>
    </Fluid>

    <Dialog v-model:visible="formVisible" modal :draggable="false" :style="{ width: 'min(94vw, 44rem)' }" :header="formMode === 'create' ? 'New Leave Request' : 'Edit Leave Request'">
        <div class="flex flex-col gap-4">
            <Message v-if="formError" severity="error" :closable="false">{{ formError }}</Message>

            <div class="flex flex-col gap-2">
                <label for="leaveSubjectType" class="font-medium">Subject Type <span class="text-red-500">*</span></label>
                <Select v-if="canManage" inputId="leaveSubjectType" v-model="form.subjectType" :options="subjectTypeOptions" optionLabel="label" optionValue="value" :disabled="formSelfOnly" fluid @change="onSubjectTypeChange" />
                <InputText v-else id="leaveSubjectType" :modelValue="subjectTypeLabel(form.subjectType)" disabled />
                <small v-if="fieldError(formErrors, 'subject_type')" class="text-red-500">{{ fieldError(formErrors, 'subject_type') }}</small>
            </div>

            <div class="flex flex-col gap-2">
                <label for="leaveSubject" class="font-medium"> {{ form.subjectType === 'student' ? 'Student' : 'Employee' }} <span class="text-red-500">*</span> </label>
                <AutoComplete
                    v-if="canManage"
                    inputId="leaveSubject"
                    v-model="form.subject"
                    :suggestions="subjectSuggestions"
                    optionLabel="label"
                    :loading="subjectLoading"
                    :minLength="0"
                    :disabled="formSelfOnly"
                    dropdown
                    forceSelection
                    placeholder="Type to search"
                    fluid
                    @complete="searchSubjects"
                />
                <InputText v-else id="leaveSubject" :modelValue="form.subject?.label ?? 'No linked employee record'" disabled />
                <small v-if="!canManage" class="text-surface-500">Self-service requests always use your linked employee record.</small>
                <small v-if="subjectError" class="text-red-500">{{ subjectError }}</small>
                <small v-if="formSubjectError" class="text-red-500">{{ formSubjectError }}</small>
            </div>

            <Message v-if="form.subjectType === 'student' && activeSchoolYear" severity="info" :closable="false"> Student leave dates must fall within {{ activeSchoolYearLabel }}. </Message>
            <Message v-if="form.subjectType === 'student' && !activeSchoolYear" severity="warn" :closable="false"> No active school year is configured. Set it in School Years before recording student leave. </Message>
            <small v-if="fieldError(formErrors, 'school_year')" class="text-red-500">{{ fieldError(formErrors, 'school_year') }}</small>

            <div class="flex flex-col gap-2">
                <label for="leaveType" class="font-medium">Leave Type <span class="text-red-500">*</span></label>
                <Select inputId="leaveType" v-model="form.type" :options="formTypeOptions" optionLabel="label" optionValue="value" placeholder="Select leave type" fluid />
                <small v-if="formSelectedType" class="text-surface-500">Maximum {{ formSelectedType.max_days }} days for this type.</small>
                <small v-if="fieldError(formErrors, 'type')" class="text-red-500">{{ fieldError(formErrors, 'type') }}</small>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label for="leaveStartDate" class="font-medium">Start Date <span class="text-red-500">*</span></label>
                    <DatePicker inputId="leaveStartDate" v-model="form.startDate" dateFormat="yy-mm-dd" showIcon manualInput fluid />
                    <small v-if="fieldError(formErrors, 'start_date')" class="text-red-500">{{ fieldError(formErrors, 'start_date') }}</small>
                </div>
                <div class="flex flex-col gap-2">
                    <label for="leaveEndDate" class="font-medium">End Date <span class="text-red-500">*</span></label>
                    <DatePicker inputId="leaveEndDate" v-model="form.endDate" dateFormat="yy-mm-dd" showIcon manualInput fluid />
                    <small v-if="fieldError(formErrors, 'end_date')" class="text-red-500">{{ fieldError(formErrors, 'end_date') }}</small>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label for="leaveReason" class="font-medium">Reason <span class="text-red-500">*</span></label>
                <Textarea id="leaveReason" v-model="form.reason" rows="4" :maxlength="reasonMax" autoResize placeholder="Explain the reason for this leave" />
                <small class="text-surface-500">{{ form.reason.trim().length }}/{{ reasonMax }} · at least {{ reasonMin }} characters</small>
                <small v-if="fieldError(formErrors, 'reason')" class="text-red-500">{{ fieldError(formErrors, 'reason') }}</small>
            </div>

            <Message severity="info" :closable="false"> Up to {{ maxFiles }} private attachments ({{ maxFileLabel }} each) can be added after the draft is saved. </Message>
        </div>

        <template #footer>
            <Button label="Cancel" severity="secondary" variant="text" :disabled="formSaving" @click="formVisible = false" />
            <Button :label="formMode === 'create' ? 'Create Draft' : 'Save Changes'" icon="pi pi-check" :loading="formSaving" @click="submitForm" />
        </template>
    </Dialog>

    <Dialog v-model:visible="detailVisible" modal :draggable="false" :style="{ width: 'min(94vw, 60rem)' }" header="Leave Request">
        <div v-if="detailLoading" class="flex justify-center py-10">
            <i class="pi pi-spin pi-spinner text-2xl" aria-hidden="true"></i>
        </div>
        <Message v-else-if="detailError" severity="error" :closable="false">{{ detailError }}</Message>
        <div v-else-if="detail" class="flex flex-col gap-5">
            <div class="flex flex-wrap items-center gap-2">
                <Tag :value="statusLabel(detail.status)" :severity="statusTagSeverity(detail.status)" />
                <Tag :value="subjectTypeLabel(detail.subject_type)" severity="secondary" />
                <Tag :value="detail.type_label ?? detail.type" severity="info" />
                <Tag :value="`Version ${detail.version}`" severity="contrast" />
                <div class="flex flex-wrap gap-2 sm:ml-auto">
                    <Button v-if="detail.can?.edit" label="Edit" icon="pi pi-pencil" size="small" variant="outlined" @click="startEdit(detail)" />
                    <Button v-if="detail.can?.submit" label="Submit" icon="pi pi-send" size="small" severity="info" variant="outlined" @click="openSubmit(detail)" />
                    <Button v-if="detail.can?.cancel" label="Cancel" icon="pi pi-ban" size="small" severity="danger" variant="outlined" @click="openCancel(detail)" />
                </div>
            </div>

            <Message v-if="detail.status === 'draft'" severity="info" :closable="false"> This draft can be edited, submitted, or cancelled. Attachments can be added before or after submission. </Message>
            <Message v-if="detail.status === 'submitted'" severity="info" :closable="false"> This request has been submitted. It can be cancelled with a reason, but it can no longer be edited. </Message>
            <Message v-if="detail.status === 'cancelled'" severity="warn" :closable="false"> Cancelled by {{ detail.cancelled_by?.name ?? 'a user' }} on {{ formatDateTime(detail.cancelled_at) }}. </Message>
            <Message v-if="detail.status === 'approved'" severity="success" :closable="false"> Approved by {{ detail.decided_by?.name ?? 'a user' }} on {{ formatDateTime(detail.decided_at) }}. </Message>
            <Message v-if="detail.status === 'rejected'" severity="error" :closable="false"> Rejected by {{ detail.decided_by?.name ?? 'a user' }} on {{ formatDateTime(detail.decided_at) }}. </Message>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div>
                    <div class="text-surface-500">{{ detail.subject_type === 'student' ? 'Student' : 'Employee' }}</div>
                    <div class="font-medium break-words">{{ detail.subject?.name ?? '-' }}</div>
                    <div class="text-xs text-surface-500 break-words">{{ subjectMeta(detail) || '-' }}</div>
                </div>
                <div>
                    <div class="text-surface-500">Type</div>
                    <div class="font-medium">{{ detail.type_label ?? detail.type }}</div>
                </div>
                <div>
                    <div class="text-surface-500">Start date</div>
                    <div class="font-medium">{{ formatDate(detail.start_date) }}</div>
                </div>
                <div>
                    <div class="text-surface-500">End date</div>
                    <div class="font-medium">{{ formatDate(detail.end_date) }} · {{ detail.days }} {{ detail.days === 1 ? 'day' : 'days' }}</div>
                </div>
                <div>
                    <div class="text-surface-500">Requested by</div>
                    <div class="font-medium">{{ detail.requested_by?.name ?? '-' }} · {{ formatDateTime(detail.created_at) }}</div>
                </div>
                <div v-if="detail.submitted_by">
                    <div class="text-surface-500">Submitted by</div>
                    <div class="font-medium">{{ detail.submitted_by.name ?? '-' }} · {{ formatDateTime(detail.submitted_at) }}</div>
                </div>
                <div v-if="detail.decided_by">
                    <div class="text-surface-500">Decided by</div>
                    <div class="font-medium">{{ detail.decided_by.name ?? '-' }} · {{ formatDateTime(detail.decided_at) }}</div>
                </div>
                <div v-if="detail.cancelled_by">
                    <div class="text-surface-500">Cancelled by</div>
                    <div class="font-medium">{{ detail.cancelled_by.name ?? '-' }} · {{ formatDateTime(detail.cancelled_at) }}</div>
                </div>
            </div>

            <div>
                <div class="text-sm text-surface-500 mb-1">Reason</div>
                <div class="text-sm whitespace-pre-line break-words">{{ detail.reason || '-' }}</div>
            </div>

            <div class="border border-surface-200 dark:border-surface-700 rounded p-4 flex flex-col gap-3">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="font-semibold">Attachments ({{ detail.attachments?.length ?? 0 }}/{{ maxFiles }})</div>
                    <div v-if="detail.can?.upload && (detail.attachments?.length ?? 0) < maxFiles" class="flex flex-col sm:flex-row sm:items-center gap-2">
                        <input ref="fileInput" type="file" :accept="acceptAttr" class="block w-full sm:w-64 text-sm" aria-label="Choose a leave attachment file" @change="onFileSelected" />
                        <Button label="Upload" icon="pi pi-upload" size="small" :loading="uploading" :disabled="!selectedFile" @click="uploadAttachment" />
                    </div>
                </div>
                <small class="text-surface-500"> PDF, JPG, JPEG, or PNG. Maximum {{ maxFileLabel }} per file and {{ maxFiles }} files per request. Attachments are private and downloaded through an authorized endpoint. </small>
                <small v-if="selectedFile" class="text-surface-500">Selected: {{ selectedFile.name }} ({{ formatSize(selectedFile.size) }})</small>
                <small v-if="detail.status !== 'draft' && detail.status !== 'submitted'" class="text-surface-500">Attachments can no longer be changed for this request.</small>
                <Message v-if="uploadError" severity="error" :closable="false">{{ uploadError }}</Message>

                <div v-if="!detail.attachments || detail.attachments.length === 0" class="text-sm text-surface-500">No attachments yet.</div>
                <ul v-else class="divide-y divide-surface-200 dark:divide-surface-700">
                    <li v-for="attachment in detail.attachments" :key="attachment.id" class="flex flex-col sm:flex-row sm:items-center gap-2 py-2">
                        <div class="flex items-center gap-2 min-w-0 flex-1">
                            <i :class="attachment.mime_type === 'application/pdf' ? 'pi pi-file-pdf' : 'pi pi-image'" aria-hidden="true"></i>
                            <div class="min-w-0">
                                <div class="truncate text-sm" :title="attachment.original_name">{{ attachment.original_name }}</div>
                                <div class="text-xs text-surface-500">{{ formatSize(attachment.size_bytes) }} · uploaded {{ formatDateTime(attachment.uploaded_at) }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button icon="pi pi-download" aria-label="Download attachment" size="small" variant="outlined" rounded v-tooltip.top="'Download'" @click="downloadAttachment(attachment)" />
                            <Button v-if="detail.can?.delete_attachment" icon="pi pi-trash" aria-label="Remove attachment" size="small" severity="danger" variant="outlined" rounded v-tooltip.top="'Remove'" @click="openRemoveAttachment(attachment)" />
                        </div>
                    </li>
                </ul>
            </div>

            <div class="border border-surface-200 dark:border-surface-700 rounded p-4">
                <div class="font-semibold mb-3">Decision History</div>
                <div v-if="!detail.decisions || detail.decisions.length === 0" class="text-sm text-surface-500">No decisions recorded yet.</div>
                <ol v-else class="flex flex-col gap-3">
                    <li v-for="decision in detail.decisions" :key="decision.id" class="flex gap-3 text-sm">
                        <i :class="decisionIcon(decision.action)" class="mt-1" aria-hidden="true"></i>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <Tag :value="decisionLabel(decision.action)" :severity="decisionTagSeverity(decision.action)" />
                                <span class="font-medium">{{ decision.actor?.name ?? 'Unknown user' }}</span>
                                <span class="text-xs text-surface-500">{{ formatDateTime(decision.created_at) }}</span>
                            </div>
                            <div class="text-xs text-surface-500 mt-1">{{ statusLabel(decision.from_status) }} → {{ statusLabel(decision.to_status) }}</div>
                            <div v-if="decision.reason" class="mt-1 whitespace-pre-line break-words">{{ decision.reason }}</div>
                        </div>
                    </li>
                </ol>
            </div>
        </div>
    </Dialog>

    <Dialog v-model:visible="submitVisible" modal :draggable="false" :style="{ width: 'min(94vw, 36rem)' }" header="Submit Leave Request">
        <div class="flex flex-col gap-4">
            <div v-if="submitTarget" class="text-sm text-surface-500">
                <span class="font-medium text-surface-900 dark:text-surface-0">{{ submitTarget.subject || 'Leave request' }}</span>
                — {{ formatDate(submitTarget.start_date) }} to {{ formatDate(submitTarget.end_date) }}
            </div>
            <Message severity="info" :closable="false"> Submitting validates the subject, dates, overlap, and required attachments. The request can no longer be edited after submission. </Message>
            <Message v-if="submitErrorList.length > 0" severity="error" :closable="false">
                <ul class="flex flex-col gap-1">
                    <li v-for="message in submitErrorList" :key="message">{{ message }}</li>
                </ul>
            </Message>
            <small v-if="submitError" class="text-red-500">{{ submitError }}</small>
        </div>
        <template #footer>
            <Button label="Close" severity="secondary" variant="text" :disabled="submitSaving" @click="submitVisible = false" />
            <Button label="Submit" icon="pi pi-send" severity="info" :loading="submitSaving" @click="confirmSubmit" />
        </template>
    </Dialog>

    <Dialog v-model:visible="cancelVisible" modal :draggable="false" :style="{ width: 'min(94vw, 38rem)' }" header="Cancel Leave Request">
        <div class="flex flex-col gap-4">
            <div v-if="cancelTarget" class="text-sm text-surface-500">
                <span class="font-medium text-surface-900 dark:text-surface-0">{{ cancelTarget.subject || 'Leave request' }}</span>
                — {{ formatDate(cancelTarget.start_date) }} to {{ formatDate(cancelTarget.end_date) }}
            </div>
            <Message severity="warn" :closable="false">
                {{ cancelRequiresReason ? 'Cancelling a submitted request requires a reason. The request becomes read-only.' : 'Cancelling this draft keeps the record and audit trail. This cannot be undone.' }}
            </Message>

            <div class="flex flex-col gap-2">
                <label for="leaveCancelReason" class="font-medium">
                    Cancellation Reason
                    <span v-if="cancelRequiresReason" class="text-red-500">*</span>
                </label>
                <Textarea id="leaveCancelReason" v-model="cancelReason" rows="3" :maxlength="cancelMax" autoResize placeholder="Explain why this request is cancelled" />
                <small class="text-surface-500">
                    {{ cancelRequiresReason ? `At least ${cancelMin} characters.` : `Optional. If provided, at least ${cancelMin} characters.` }}
                    {{ cancelReason.trim().length }}/{{ cancelMax }}
                </small>
                <small v-if="fieldError(cancelErrors, 'reason')" class="text-red-500">{{ fieldError(cancelErrors, 'reason') }}</small>
            </div>

            <Message v-if="cancelErrorList.length > 0" severity="error" :closable="false">
                <ul class="flex flex-col gap-1">
                    <li v-for="message in cancelErrorList" :key="message">{{ message }}</li>
                </ul>
            </Message>
            <small v-if="cancelError" class="text-red-500">{{ cancelError }}</small>
        </div>
        <template #footer>
            <Button label="Close" severity="secondary" variant="text" :disabled="cancelSaving" @click="cancelVisible = false" />
            <Button label="Cancel Request" icon="pi pi-ban" severity="danger" :loading="cancelSaving" @click="confirmCancel" />
        </template>
    </Dialog>

    <Dialog v-model:visible="removeVisible" modal :draggable="false" :style="{ width: 'min(94vw, 34rem)' }" header="Remove Attachment">
        <div class="flex flex-col gap-3">
            <p class="text-sm">
                Remove
                <span class="font-medium">{{ removeTarget?.attachment?.original_name }}</span>
                from this leave request? The file is retained in private storage for audit and will no longer be listed.
            </p>
            <Message v-if="removeError" severity="error" :closable="false">{{ removeError }}</Message>
        </div>
        <template #footer>
            <Button label="Cancel" severity="secondary" variant="text" :disabled="removeSaving" @click="removeVisible = false" />
            <Button label="Remove" icon="pi pi-trash" severity="danger" :loading="removeSaving" @click="confirmRemoveAttachment" />
        </template>
    </Dialog>

    <Dialog v-model:visible="conflictVisible" modal :draggable="false" :style="{ width: 'min(94vw, 34rem)' }" header="Conflict">
        <div class="flex flex-col gap-3">
            <Message severity="warn" :closable="false">{{ conflictMessage }}</Message>
            <p class="text-sm text-surface-500">Reload the latest request to see the current version before trying again.</p>
        </div>
        <template #footer>
            <Button label="Close" severity="secondary" variant="text" @click="conflictVisible = false" />
            <Button label="Reload Latest" icon="pi pi-refresh" :loading="reloadingConflict" @click="reloadLatest" />
        </template>
    </Dialog>
</template>
