<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import apiLogin from '@/apiLogin';
import { authStore } from '@/stores/authStore';

const store = authStore();
const toast = useToast();

const validityLabels = {
    none: 'No Expiry',
    upcoming: 'Upcoming',
    valid: 'Valid',
    expiring_soon: 'Expiring Soon',
    expired: 'Expired'
};

const validitySeverity = {
    none: 'secondary',
    upcoming: 'info',
    valid: 'success',
    expiring_soon: 'warn',
    expired: 'danger'
};

const options = ref(null);
const optionsError = ref('');
const canManage = computed(() => options.value?.can?.manage ?? store.can('archives.manage'));
const categories = computed(() => options.value?.categories ?? []);
const subjectTypes = computed(() => options.value?.subject_types ?? []);
const perPageOptions = computed(() => options.value?.per_page_options ?? [10, 20, 50, 100]);
const searchMax = computed(() => options.value?.search_max_length ?? 100);
const expiringDays = computed(() => options.value?.expiring_soon_days ?? 30);
const titleMin = computed(() => options.value?.title?.min ?? 3);
const titleMax = computed(() => options.value?.title?.max ?? 200);
const documentNumberMax = computed(() => options.value?.document_number?.max ?? 100);
const descriptionMax = computed(() => options.value?.description?.max ?? 1000);
const noteMax = computed(() => options.value?.version_note?.max ?? 500);
const maxFileKb = computed(() => options.value?.file?.max_file_kb ?? 10240);
const allowedExtensions = computed(() => options.value?.file?.allowed_extensions ?? ['pdf', 'jpg', 'jpeg', 'png']);
const acceptAttr = computed(() => allowedExtensions.value.map((extension) => `.${extension}`).join(','));
const maxFileLabel = computed(() => (maxFileKb.value >= 1024 ? `${Math.round(maxFileKb.value / 1024)} MB` : `${maxFileKb.value} KB`));
const fileRuleLabel = computed(() => `${allowedExtensions.value.map((extension) => extension.toUpperCase()).join(', ')}. Maximum ${maxFileLabel.value} per file.`);
const perPageLabel = computed(() => `Rows: ${filters.perPage}`);
const totalLabel = computed(() => `${meta.value.total} ${meta.value.total === 1 ? 'document' : 'documents'}`);

const categoryFilterOptions = computed(() => [{ label: 'All categories', value: '' }, ...categories.value]);
const subjectTypeFilterOptions = computed(() => [{ label: 'All subject types', value: '' }, ...subjectTypes.value]);
const validityFilterOptions = computed(() => [{ label: 'All validity statuses', value: '' }, ...(options.value?.validity_statuses ?? [])]);
const createSubjectTypeOptions = computed(() => [{ label: 'No subject link', value: '' }, ...subjectTypes.value]);

const notify = (severity, detail) => {
    toast.add({ severity, summary: 'Archives', detail, life: 5000 });
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

const subjectOptionText = (option) => {
    if (!option) return '';
    const suffix = option.archived ? ' (archived)' : '';
    return option.secondary ? `${option.label} — ${option.secondary}${suffix}` : `${option.label}${suffix}`;
};

const subjectTypeLabel = (value) => subjectTypes.value.find((type) => type.value === value)?.label ?? (value ? value.charAt(0).toUpperCase() + value.slice(1) : '-');

// Daftar dan filter
const items = ref([]);
const meta = ref({ current_page: 1, per_page: 20, total: 0, last_page: 1 });
const listLoading = ref(false);
const listError = ref('');
const page = ref(1);
const filters = reactive({
    q: '',
    category: '',
    subjectType: '',
    subjectId: null,
    validity: '',
    dateFrom: null,
    dateTo: null,
    perPage: 20
});
const filterSubjects = ref([]);
const filterSubjectsLoading = ref(false);
let searchTimer = null;
let listRequestId = 0;
let lastDateFilterKey = '|';

const loadOptions = async () => {
    try {
        const response = await apiLogin.get('/api/archives/options');
        options.value = response.data?.data ?? null;
        optionsError.value = '';
        filters.perPage = options.value?.default_per_page ?? filters.perPage;
    } catch (error) {
        options.value = null;
        optionsError.value = errorMessage(error, 'Failed to load archive options.');
    }
};

const loadFilterSubjects = async () => {
    if (!filters.subjectType) {
        filterSubjects.value = [];
        return;
    }

    const type = filters.subjectType;
    filterSubjectsLoading.value = true;
    try {
        const response = await apiLogin.get(`/api/archives/subjects/${type}`, { params: { limit: 100 } });
        if (filters.subjectType !== type) return;
        filterSubjects.value = response.data?.data?.items ?? [];
    } catch {
        if (filters.subjectType === type) filterSubjects.value = [];
    } finally {
        if (filters.subjectType === type) filterSubjectsLoading.value = false;
    }
};

const loadList = async () => {
    const requestId = ++listRequestId;
    listLoading.value = true;
    listError.value = '';

    try {
        const params = { page: page.value, per_page: filters.perPage };
        if (filters.q.trim()) params.q = filters.q.trim();
        if (filters.category) params.category = filters.category;
        if (filters.subjectType) params.subject_type = filters.subjectType;
        if (filters.subjectId) params.subject_id = filters.subjectId;
        if (filters.validity) params.validity = filters.validity;
        const dateFrom = dateToIso(filters.dateFrom);
        const dateTo = dateToIso(filters.dateTo);
        if (dateFrom) params.date_from = dateFrom;
        if (dateTo) params.date_to = dateTo;

        const response = await apiLogin.get('/api/archives', { params });
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
        listError.value = errorMessage(error, 'Failed to load archive documents. Please try again.');
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

const onSubjectTypeFilterChange = () => {
    filters.subjectId = null;
    loadFilterSubjects();
    onFilterChange();
};

const onDateFilterChange = () => {
    const key = `${dateToIso(filters.dateFrom) ?? ''}|${dateToIso(filters.dateTo) ?? ''}`;
    if (key === lastDateFilterKey) return;
    lastDateFilterKey = key;
    page.value = 1;
    loadList();
};

const resetFilters = () => {
    filters.q = '';
    filters.category = '';
    filters.subjectType = '';
    filters.subjectId = null;
    filters.validity = '';
    filters.dateFrom = null;
    filters.dateTo = null;
    filterSubjects.value = [];
    lastDateFilterKey = '|';
    page.value = 1;
    loadList();
};

const onPage = (event) => {
    const nextPage = event.page + 1;
    const rowsChanged = event.rows !== filters.perPage;
    page.value = rowsChanged ? 1 : nextPage;
    if (rowsChanged) filters.perPage = event.rows;
    loadList();
};

// Form create
const createVisible = ref(false);
const createSaving = ref(false);
const createErrors = ref({});
const createError = ref('');
const createFile = ref(null);
const createFileInput = ref(null);
const createSubjectOptions = ref([]);
const createSubjectLoading = ref(false);
let subjectSearchTimer = null;
const createForm = reactive({
    category: '',
    title: '',
    documentNumber: '',
    documentDate: null,
    validFrom: null,
    validUntil: null,
    description: '',
    subjectType: '',
    subjectId: null
});

const resetCreateFileInput = () => {
    if (createFileInput.value) createFileInput.value.value = '';
};

const openCreate = () => {
    if (!canManage.value) return;
    createForm.category = '';
    createForm.title = '';
    createForm.documentNumber = '';
    createForm.documentDate = isoToDate(todayIso());
    createForm.validFrom = null;
    createForm.validUntil = null;
    createForm.description = '';
    createForm.subjectType = '';
    createForm.subjectId = null;
    createFile.value = null;
    createSubjectOptions.value = [];
    createErrors.value = {};
    createError.value = '';
    createSaving.value = false;
    resetCreateFileInput();
    createVisible.value = true;
};

const onCreateSubjectTypeChange = () => {
    createForm.subjectId = null;
    createSubjectOptions.value = [];
    createError.value = '';
    if (subjectSearchTimer) {
        clearTimeout(subjectSearchTimer);
        subjectSearchTimer = null;
    }
    if (createForm.subjectType) loadCreateSubjects('');
};

const loadCreateSubjects = async (term) => {
    const type = createForm.subjectType;
    if (!type) return;

    createSubjectLoading.value = true;
    try {
        const response = await apiLogin.get(`/api/archives/subjects/${type}`, {
            params: { q: term || undefined, limit: 20 }
        });
        if (createForm.subjectType !== type) return;
        createSubjectOptions.value = response.data?.data?.items ?? [];
    } catch (error) {
        if (createForm.subjectType !== type) return;
        createSubjectOptions.value = [];
        createError.value = errorMessage(error, 'Failed to load subject options.');
    } finally {
        if (createForm.subjectType === type) createSubjectLoading.value = false;
    }
};

const onCreateSubjectFilter = (event) => {
    if (subjectSearchTimer) clearTimeout(subjectSearchTimer);
    const term = (event?.value ?? '').toString();
    subjectSearchTimer = setTimeout(() => loadCreateSubjects(term), 300);
};

const onCreateFileSelected = (event) => {
    createFile.value = event.target.files?.[0] ?? null;
    createErrors.value = { ...createErrors.value, file: [] };
};

const fileValidationMessage = (file) => {
    if (!file) return 'Select a file.';
    const extension = file.name.split('.').pop()?.toLowerCase() ?? '';
    if (!allowedExtensions.value.includes(extension)) {
        return `Only ${allowedExtensions.value.map((value) => value.toUpperCase()).join(', ')} files are allowed.`;
    }
    if (file.size > maxFileKb.value * 1024) {
        return `The file may not be larger than ${maxFileLabel.value}.`;
    }
    return '';
};

const metadataValidationErrors = (form) => {
    const errors = {};
    if (!form.category) errors.category = ['Select an archive category.'];
    const title = form.title.trim();
    if (title.length < titleMin.value) {
        errors.title = [`The title must be at least ${titleMin.value} characters.`];
    } else if (title.length > titleMax.value) {
        errors.title = [`The title may not exceed ${titleMax.value} characters.`];
    }
    if (!dateToIso(form.documentDate)) errors.document_date = ['The document date is required.'];
    const validFrom = dateToIso(form.validFrom);
    const validUntil = dateToIso(form.validUntil);
    if ((validFrom === null) !== (validUntil === null)) {
        errors.valid_until = ['Provide both valid from and valid until, or neither.'];
    } else if (validFrom !== null && validUntil !== null && validUntil < validFrom) {
        errors.valid_until = ['The valid until date may not be before the valid from date.'];
    }
    if (form.description.trim().length > descriptionMax.value) {
        errors.description = [`The description may not exceed ${descriptionMax.value} characters.`];
    }
    return errors;
};

const submitCreate = async () => {
    if (createSaving.value) return;

    createErrors.value = {};
    createError.value = '';

    const errors = metadataValidationErrors(createForm);
    if (createForm.subjectType && !createForm.subjectId) {
        errors.subject_id = ['Select a subject to link to this archive document.'];
    }
    const fileError = fileValidationMessage(createFile.value);
    if (fileError) errors.file = [fileError];

    if (Object.keys(errors).length > 0) {
        createErrors.value = errors;
        return;
    }

    const body = new FormData();
    body.append('category', createForm.category);
    body.append('title', createForm.title.trim());
    body.append('document_date', dateToIso(createForm.documentDate));
    const documentNumber = createForm.documentNumber.trim();
    if (documentNumber) body.append('document_number', documentNumber);
    const validFrom = dateToIso(createForm.validFrom);
    const validUntil = dateToIso(createForm.validUntil);
    if (validFrom && validUntil) {
        body.append('valid_from', validFrom);
        body.append('valid_until', validUntil);
    }
    const description = createForm.description.trim();
    if (description) body.append('description', description);
    if (createForm.subjectType && createForm.subjectId) {
        body.append('subject_type', createForm.subjectType);
        body.append('subject_id', createForm.subjectId);
    }
    body.append('file', createFile.value);

    createSaving.value = true;
    try {
        const response = await apiLogin.post('/api/archives', body);
        createVisible.value = false;
        notify('success', response.data?.message ?? 'Archive document created.');
        page.value = 1;
        await loadList();
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            createErrors.value = validationErrors(error);
            if (Object.keys(createErrors.value).length === 0) createError.value = errorMessage(error, 'The archive document could not be saved.');
        } else {
            createError.value = errorMessage(error, 'Failed to create the archive document.');
        }
    } finally {
        createSaving.value = false;
    }
};

// Form edit
const editVisible = ref(false);
const editLoading = ref(false);
const editSaving = ref(false);
const editTarget = ref(null);
const editTargetId = ref(null);
const editErrors = ref({});
const editError = ref('');
const editForm = reactive({
    category: '',
    title: '',
    documentNumber: '',
    documentDate: null,
    validFrom: null,
    validUntil: null,
    description: ''
});

const fetchDocument = async (id) => {
    const response = await apiLogin.get(`/api/archives/${id}`);
    return response.data?.data ?? null;
};

const openEdit = async (row) => {
    if (!row?.can?.update || editLoading.value) return;

    editTargetId.value = row.id;
    editLoading.value = true;
    try {
        const data = await fetchDocument(row.id);
        if (!data) {
            notify('error', 'The archive document could not be loaded.');
            return;
        }
        editTarget.value = data;
        editForm.category = data.category;
        editForm.title = data.title ?? '';
        editForm.documentNumber = data.document_number ?? '';
        editForm.documentDate = isoToDate(data.document_date);
        editForm.validFrom = isoToDate(data.valid_from);
        editForm.validUntil = isoToDate(data.valid_until);
        editForm.description = data.description ?? '';
        editErrors.value = {};
        editError.value = '';
        editSaving.value = false;
        editVisible.value = true;
    } catch (error) {
        notify('error', errorMessage(error, 'Failed to load the archive document.'));
        if (error.response?.status === 404) await loadList();
    } finally {
        editLoading.value = false;
    }
};

const submitEdit = async () => {
    if (editSaving.value || !editTarget.value) return;

    editErrors.value = {};
    editError.value = '';

    const errors = metadataValidationErrors(editForm);
    if (Object.keys(errors).length > 0) {
        editErrors.value = errors;
        return;
    }

    const target = editTarget.value;
    editSaving.value = true;
    try {
        const response = await apiLogin.put(`/api/archives/${target.id}`, {
            category: editForm.category,
            title: editForm.title.trim(),
            document_number: editForm.documentNumber.trim() || null,
            document_date: dateToIso(editForm.documentDate),
            valid_from: dateToIso(editForm.validFrom),
            valid_until: dateToIso(editForm.validUntil),
            description: editForm.description.trim() || null,
            expected_version: target.version
        });
        editVisible.value = false;
        notify('success', response.data?.message ?? 'Archive document updated.');
        await loadList();
        if (detailVisible.value && detail.value?.id === target.id) {
            await Promise.all([loadDetail(target.id), loadVersions(target.id, versionsMeta.value.current_page)]);
        }
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            editErrors.value = validationErrors(error);
            if (Object.keys(editErrors.value).length === 0) editError.value = errorMessage(error, 'The archive document could not be saved.');
        } else if (status === 409) {
            editVisible.value = false;
            openConflict(error, target.id);
        } else {
            editError.value = errorMessage(error, 'Failed to update the archive document.');
        }
    } finally {
        editSaving.value = false;
    }
};

// Detail dan riwayat versi
const detailVisible = ref(false);
const detailLoading = ref(false);
const detailError = ref('');
const detail = ref(null);
const detailDocumentId = ref(null);
const versions = ref([]);
const versionsLoading = ref(false);
const versionsError = ref('');
const versionsMeta = ref({ current_page: 1, per_page: 10, total: 0, last_page: 1 });
const downloadingKey = ref(null);
let detailRequestId = 0;
let versionsRequestId = 0;

const loadDetail = async (id) => {
    const requestId = ++detailRequestId;
    detailLoading.value = true;
    detailError.value = '';
    try {
        const data = await fetchDocument(id);
        if (requestId !== detailRequestId || !detailVisible.value || detailDocumentId.value !== id) return;
        if (!data) {
            detailError.value = 'The archive document was not found.';
            return;
        }
        detail.value = data;
    } catch (error) {
        if (requestId !== detailRequestId) return;
        detailError.value = errorMessage(error, 'Failed to load the archive document.');
    } finally {
        if (requestId === detailRequestId) detailLoading.value = false;
    }
};

const loadVersions = async (id, targetPage = 1) => {
    const requestId = ++versionsRequestId;
    versionsLoading.value = true;
    versionsError.value = '';
    try {
        const response = await apiLogin.get(`/api/archives/${id}/versions`, {
            params: { page: targetPage, per_page: versionsMeta.value.per_page }
        });
        if (requestId !== versionsRequestId || !detailVisible.value || detailDocumentId.value !== id) return;
        const data = response.data?.data ?? {};
        versions.value = data.items ?? [];
        versionsMeta.value = data.meta ?? { current_page: 1, per_page: 10, total: 0, last_page: 1 };
    } catch (error) {
        if (requestId !== versionsRequestId) return;
        versionsError.value = errorMessage(error, 'Failed to load the version history.');
    } finally {
        if (requestId === versionsRequestId) versionsLoading.value = false;
    }
};

const openDetail = (row) => {
    if (!row?.can?.view) return;
    detailDocumentId.value = row.id;
    detail.value = row;
    detailError.value = '';
    detailVisible.value = true;
    versions.value = [];
    versionsError.value = '';
    versionsMeta.value = { current_page: 1, per_page: 10, total: 0, last_page: 1 };
    loadDetail(row.id);
    loadVersions(row.id, 1);
};

const onVersionsPage = (event) => {
    if (!detail.value) return;
    loadVersions(detail.value.id, event.page + 1);
};

const downloadVersion = async (documentId, versionNumber, fallbackName) => {
    const key = `${documentId}:${versionNumber}`;
    if (downloadingKey.value) return;

    downloadingKey.value = key;
    try {
        const response = await apiLogin.get(`/api/archives/${documentId}/versions/${versionNumber}/download`, {
            responseType: 'blob'
        });
        const mime = response.headers?.['content-type'] || 'application/octet-stream';
        const filename = response.headers?.['x-document-filename'] || fallbackName || `archive-${documentId}-v${versionNumber}`;
        const url = URL.createObjectURL(new Blob([response.data], { type: mime }));
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    } catch (error) {
        notify('error', error.response?.status === 404 ? 'The archive file was not found.' : 'Failed to download the archive file.');
    } finally {
        downloadingKey.value = null;
    }
};

// Form versi baru
const addVisible = ref(false);
const addSaving = ref(false);
const addTarget = ref(null);
const addErrors = ref({});
const addError = ref('');
const addNote = ref('');
const addFile = ref(null);
const addFileInput = ref(null);

const resetAddFileInput = () => {
    if (addFileInput.value) addFileInput.value.value = '';
};

const openAddVersion = (row) => {
    if (!row?.can?.add_version) return;
    addTarget.value = row;
    addNote.value = '';
    addFile.value = null;
    addErrors.value = {};
    addError.value = '';
    addSaving.value = false;
    resetAddFileInput();
    addVisible.value = true;
};

const onAddFileSelected = (event) => {
    addFile.value = event.target.files?.[0] ?? null;
    addErrors.value = { ...addErrors.value, file: [] };
};

const submitAddVersion = async () => {
    if (addSaving.value || !addTarget.value) return;

    addErrors.value = {};
    addError.value = '';

    const errors = {};
    const fileError = fileValidationMessage(addFile.value);
    if (fileError) errors.file = [fileError];
    if (addNote.value.trim().length > noteMax.value) {
        errors.note = [`The version note may not exceed ${noteMax.value} characters.`];
    }
    if (Object.keys(errors).length > 0) {
        addErrors.value = errors;
        return;
    }

    const target = addTarget.value;
    const body = new FormData();
    body.append('file', addFile.value);
    const note = addNote.value.trim();
    if (note) body.append('note', note);
    body.append('expected_version', target.version);

    addSaving.value = true;
    try {
        const response = await apiLogin.post(`/api/archives/${target.id}/versions`, body);
        addVisible.value = false;
        notify('success', response.data?.message ?? 'Archive version created.');
        await loadList();
        if (detailVisible.value && detail.value?.id === target.id) {
            await Promise.all([loadDetail(target.id), loadVersions(target.id, 1)]);
        }
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            addErrors.value = validationErrors(error);
            if (Object.keys(addErrors.value).length === 0) addError.value = errorMessage(error, 'The archive version could not be saved.');
        } else if (status === 409) {
            addVisible.value = false;
            openConflict(error, target.id);
        } else {
            addError.value = errorMessage(error, 'Failed to add the archive version.');
        }
    } finally {
        addSaving.value = false;
    }
};

// Konflik versi
const conflictVisible = ref(false);
const conflictMessage = ref('');
const conflictDocumentId = ref(null);
const reloadingConflict = ref(false);

const openConflict = (error, documentId) => {
    conflictMessage.value = errorMessage(error, 'This archive document was changed by another request.');
    conflictDocumentId.value = documentId ?? null;
    conflictVisible.value = true;
};

const reloadLatest = async () => {
    if (reloadingConflict.value) return;

    reloadingConflict.value = true;
    try {
        await loadList();
        if (detailVisible.value && detail.value?.id === conflictDocumentId.value) {
            await Promise.all([loadDetail(conflictDocumentId.value), loadVersions(conflictDocumentId.value, versionsMeta.value.current_page)]);
        }
        conflictVisible.value = false;
        notify('warn', 'The latest archive documents have been loaded. Review the row before trying again.');
    } finally {
        reloadingConflict.value = false;
    }
};

onMounted(async () => {
    await store.fetchIdentity();
    await loadOptions();
    await loadList();
});
</script>

<template>
    <Fluid>
        <div class="bg-surface-0 dark:bg-surface-950 px-4 py-6 sm:px-6 sm:py-8 md:px-12 lg:px-20">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <div class="font-bold text-2xl sm:text-3xl text-surface-900 dark:text-surface-0 mb-2">Archive Documents</div>
                    <p class="text-surface-500 dark:text-surface-400">Private document archive with metadata, validity tracking, subject links, and immutable file versions.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Tag :value="totalLabel" severity="secondary" />
                    <Button v-if="canManage" label="New Archive Document" icon="pi pi-plus" @click="openCreate" />
                </div>
            </div>

            <Message v-if="optionsError" severity="error" :closable="false" class="mt-4">{{ optionsError }}</Message>

            <div class="card mt-6 flex flex-col gap-4">
                <div class="flex flex-col xl:flex-row xl:flex-wrap xl:items-center gap-3">
                    <IconField class="w-full xl:max-w-sm">
                        <InputIcon><i class="pi pi-search" /></InputIcon>
                        <InputText v-model="filters.q" placeholder="Search title or document number" class="w-full" :maxlength="searchMax" aria-label="Search archive documents" @input="onSearchInput" />
                    </IconField>
                    <Select
                        v-model="filters.category"
                        :options="categoryFilterOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full xl:w-48"
                        aria-label="Archive category"
                        @change="onFilterChange"
                    />
                    <Select
                        v-model="filters.subjectType"
                        :options="subjectTypeFilterOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full xl:w-44"
                        aria-label="Subject type"
                        @change="onSubjectTypeFilterChange"
                    />
                    <Select
                        v-model="filters.subjectId"
                        :options="filterSubjects"
                        :optionLabel="subjectOptionText"
                        optionValue="id"
                        :placeholder="filters.subjectType ? 'All subjects' : 'Select subject type first'"
                        :disabled="!filters.subjectType"
                        :loading="filterSubjectsLoading"
                        showClear
                        filter
                        class="w-full xl:w-64"
                        aria-label="Subject"
                        @change="onFilterChange"
                    />
                    <Select
                        v-model="filters.validity"
                        :options="validityFilterOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full xl:w-48"
                        aria-label="Validity status"
                        @change="onFilterChange"
                    />
                    <DatePicker
                        v-model="filters.dateFrom"
                        placeholder="Document date from"
                        dateFormat="yy-mm-dd"
                        showIcon
                        manualInput
                        fluid
                        class="w-full xl:w-44"
                        aria-label="Document date from"
                        @date-select="onDateFilterChange"
                        @blur="onDateFilterChange"
                    />
                    <DatePicker
                        v-model="filters.dateTo"
                        placeholder="Document date to"
                        dateFormat="yy-mm-dd"
                        showIcon
                        manualInput
                        fluid
                        class="w-full xl:w-44"
                        aria-label="Document date to"
                        @date-select="onDateFilterChange"
                        @blur="onDateFilterChange"
                    />
                    <div class="flex flex-wrap items-center gap-2 xl:ml-auto">
                        <Select v-model="filters.perPage" :options="perPageOptions" class="w-28" :aria-label="perPageLabel" @change="onFilterChange" />
                        <Button icon="pi pi-refresh" label="Refresh" severity="secondary" variant="outlined" :loading="listLoading" aria-label="Refresh archive documents" @click="loadList" />
                        <Button icon="pi pi-filter-slash" label="Reset" severity="secondary" variant="text" aria-label="Reset filters" @click="resetFilters" />
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
                    tableStyle="min-width: 84rem"
                    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                    currentPageReportTemplate="Showing {first} to {last} of {totalRecords} documents"
                    @page="onPage"
                >
                    <template #loading>Loading archive documents. Please wait.</template>
                    <template #empty>
                        <div class="flex flex-col items-center gap-3 py-6">
                            <span v-if="listError" class="text-red-500">{{ listError }}</span>
                            <span v-else>No archive documents match the current filters.</span>
                            <Button v-if="listError" icon="pi pi-refresh" label="Retry" @click="loadList" />
                        </div>
                    </template>
                    <Column header="Title" style="min-width: 18rem">
                        <template #body="{ data }">
                            <button type="button" class="text-left font-medium text-primary hover:underline" :title="data.title" @click="openDetail(data)">{{ data.title }}</button>
                            <div class="text-xs text-surface-500 mt-1">Version {{ data.version }} · {{ data.current_version }} file version{{ data.current_version === 1 ? '' : 's' }}</div>
                        </template>
                    </Column>
                    <Column header="Category" style="min-width: 9rem">
                        <template #body="{ data }">
                            <Tag :value="data.category_label" severity="secondary" />
                        </template>
                    </Column>
                    <Column header="Subject" style="min-width: 14rem">
                        <template #body="{ data }">
                            <div v-if="data.subject">
                                <div class="font-medium truncate" :title="data.subject.label">{{ data.subject.label }}</div>
                                <div class="text-xs text-surface-500">{{ data.subject.secondary }}</div>
                                <Tag v-if="data.subject.archived" value="Archived" severity="secondary" class="mt-1" />
                            </div>
                            <span v-else class="text-surface-500">No subject link</span>
                        </template>
                    </Column>
                    <Column header="Number" style="min-width: 9rem">
                        <template #body="{ data }">
                            <span class="whitespace-nowrap">{{ data.document_number || '-' }}</span>
                        </template>
                    </Column>
                    <Column header="Document Date" style="min-width: 9rem">
                        <template #body="{ data }">
                            <span class="whitespace-nowrap">{{ formatDate(data.document_date) }}</span>
                        </template>
                    </Column>
                    <Column header="Validity" style="min-width: 10rem">
                        <template #body="{ data }">
                            <Tag :value="validityLabels[data.validity_status] ?? data.validity_status" :severity="validitySeverity[data.validity_status] ?? 'secondary'" />
                            <div class="text-xs text-surface-500 mt-1">
                                <template v-if="data.valid_until">Until {{ formatDate(data.valid_until) }}</template>
                                <template v-else>No expiry date</template>
                            </div>
                        </template>
                    </Column>
                    <Column header="Updated" style="min-width: 11rem">
                        <template #body="{ data }">
                            <span class="whitespace-nowrap">{{ formatDateTime(data.updated_at) }}</span>
                        </template>
                    </Column>
                    <Column header="Actions" style="min-width: 12rem">
                        <template #body="{ data }">
                            <div class="flex flex-wrap items-center gap-2">
                                <Button icon="pi pi-eye" aria-label="View archive document" size="small" variant="outlined" rounded v-tooltip.top="'View'" @click="openDetail(data)" />
                                <Button
                                    v-if="data.can?.update"
                                    icon="pi pi-pencil"
                                    aria-label="Edit archive metadata"
                                    size="small"
                                    variant="outlined"
                                    rounded
                                    :loading="editLoading && editTargetId === data.id"
                                    v-tooltip.top="'Edit'"
                                    @click="openEdit(data)"
                                />
                                <Button
                                    v-if="data.can?.add_version"
                                    icon="pi pi-upload"
                                    aria-label="Add archive version"
                                    size="small"
                                    variant="outlined"
                                    rounded
                                    v-tooltip.top="'Add Version'"
                                    @click="openAddVersion(data)"
                                />
                                <Button
                                    v-if="data.can?.download"
                                    icon="pi pi-download"
                                    aria-label="Download current archive file"
                                    size="small"
                                    variant="outlined"
                                    rounded
                                    :loading="downloadingKey === `${data.id}:${data.current_version}`"
                                    v-tooltip.top="'Download'"
                                    @click="downloadVersion(data.id, data.current_version)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </Fluid>

    <Dialog v-model:visible="createVisible" modal :draggable="false" :style="{ width: 'min(96vw, 56rem)' }" header="New Archive Document">
        <div class="flex flex-col gap-4 max-h-[70vh] overflow-y-auto pr-1">
            <Message v-if="createError" severity="error" :closable="false">{{ createError }}</Message>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label for="archiveCreateCategory" class="font-medium">Category <span class="text-red-500">*</span></label>
                    <Select inputId="archiveCreateCategory" v-model="createForm.category" :options="categories" optionLabel="label" optionValue="value" placeholder="Select category" filter fluid />
                    <small v-if="fieldError(createErrors, 'category')" class="text-red-500">{{ fieldError(createErrors, 'category') }}</small>
                </div>
                <div class="flex flex-col gap-2">
                    <label for="archiveCreateDocumentDate" class="font-medium">Document Date <span class="text-red-500">*</span></label>
                    <DatePicker inputId="archiveCreateDocumentDate" v-model="createForm.documentDate" dateFormat="yy-mm-dd" showIcon manualInput fluid />
                    <small v-if="fieldError(createErrors, 'document_date')" class="text-red-500">{{ fieldError(createErrors, 'document_date') }}</small>
                </div>
            </div>
            <div class="flex flex-col gap-2">
                <label for="archiveCreateTitle" class="font-medium">Title <span class="text-red-500">*</span></label>
                <InputText id="archiveCreateTitle" v-model="createForm.title" :maxlength="titleMax" placeholder="Describe the archived document" fluid />
                <small class="text-surface-500">At least {{ titleMin }} characters. {{ createForm.title.trim().length }}/{{ titleMax }}</small>
                <small v-if="fieldError(createErrors, 'title')" class="text-red-500">{{ fieldError(createErrors, 'title') }}</small>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label for="archiveCreateNumber" class="font-medium">Document Number</label>
                    <InputText id="archiveCreateNumber" v-model="createForm.documentNumber" :maxlength="documentNumberMax" placeholder="Optional document number" fluid />
                    <small v-if="fieldError(createErrors, 'document_number')" class="text-red-500">{{ fieldError(createErrors, 'document_number') }}</small>
                </div>
                <div class="flex flex-col gap-2">
                    <label for="archiveCreateSubjectType" class="font-medium">Subject Type</label>
                    <Select inputId="archiveCreateSubjectType" v-model="createForm.subjectType" :options="createSubjectTypeOptions" optionLabel="label" optionValue="value" fluid @change="onCreateSubjectTypeChange" />
                    <small v-if="fieldError(createErrors, 'subject_type')" class="text-red-500">{{ fieldError(createErrors, 'subject_type') }}</small>
                </div>
            </div>
            <div v-if="createForm.subjectType" class="flex flex-col gap-2">
                <label for="archiveCreateSubject" class="font-medium">Subject <span class="text-red-500">*</span></label>
                <Select
                    inputId="archiveCreateSubject"
                    v-model="createForm.subjectId"
                    :options="createSubjectOptions"
                    :optionLabel="subjectOptionText"
                    optionValue="id"
                    placeholder="Search and select a subject"
                    :loading="createSubjectLoading"
                    filter
                    fluid
                    aria-label="Linked subject"
                    @filter="onCreateSubjectFilter"
                />
                <small class="text-surface-500">Only {{ subjectTypeLabel(createForm.subjectType).toLowerCase() }} records you are allowed to view are listed. The subject link cannot be changed after creation.</small>
                <small v-if="fieldError(createErrors, 'subject_id')" class="text-red-500">{{ fieldError(createErrors, 'subject_id') }}</small>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label for="archiveCreateValidFrom" class="font-medium">Valid From</label>
                    <DatePicker inputId="archiveCreateValidFrom" v-model="createForm.validFrom" dateFormat="yy-mm-dd" showIcon manualInput fluid />
                    <small v-if="fieldError(createErrors, 'valid_from')" class="text-red-500">{{ fieldError(createErrors, 'valid_from') }}</small>
                </div>
                <div class="flex flex-col gap-2">
                    <label for="archiveCreateValidUntil" class="font-medium">Valid Until</label>
                    <DatePicker inputId="archiveCreateValidUntil" v-model="createForm.validUntil" dateFormat="yy-mm-dd" showIcon manualInput fluid />
                    <small class="text-surface-500">Validity is computed daily in WIB. Expiring soon within {{ expiringDays }} days.</small>
                    <small v-if="fieldError(createErrors, 'valid_until')" class="text-red-500">{{ fieldError(createErrors, 'valid_until') }}</small>
                </div>
            </div>
            <div class="flex flex-col gap-2">
                <label for="archiveCreateDescription" class="font-medium">Description</label>
                <Textarea id="archiveCreateDescription" v-model="createForm.description" rows="3" :maxlength="descriptionMax" autoResize placeholder="Optional notes about this archive document" />
                <small class="text-surface-500">{{ createForm.description.trim().length }}/{{ descriptionMax }}</small>
                <small v-if="fieldError(createErrors, 'description')" class="text-red-500">{{ fieldError(createErrors, 'description') }}</small>
            </div>
            <div class="flex flex-col gap-2">
                <label for="archiveCreateFile" class="font-medium">First File <span class="text-red-500">*</span></label>
                <input id="archiveCreateFile" ref="createFileInput" type="file" :accept="acceptAttr" class="block w-full text-sm" aria-label="Choose the first archive file" @change="onCreateFileSelected" />
                <small class="text-surface-500">{{ fileRuleLabel }} Files are stored privately and downloaded through an authorized endpoint.</small>
                <small v-if="createFile" class="text-surface-500">Selected: {{ createFile.name }} ({{ formatSize(createFile.size) }})</small>
                <small v-if="fieldError(createErrors, 'file')" class="text-red-500">{{ fieldError(createErrors, 'file') }}</small>
            </div>
        </div>
        <template #footer>
            <Button label="Cancel" severity="secondary" variant="text" :disabled="createSaving" @click="createVisible = false" />
            <Button label="Create Archive Document" icon="pi pi-check" :loading="createSaving" @click="submitCreate" />
        </template>
    </Dialog>

    <Dialog v-model:visible="editVisible" modal :draggable="false" :style="{ width: 'min(96vw, 52rem)' }" header="Edit Archive Metadata">
        <div class="flex flex-col gap-4 max-h-[70vh] overflow-y-auto pr-1">
            <Message v-if="editError" severity="error" :closable="false">{{ editError }}</Message>
            <div v-if="editTarget" class="text-sm text-surface-500">
                Editing <span class="font-medium text-surface-900 dark:text-surface-0">{{ editTarget.title }}</span> · metadata version {{ editTarget.version }} · file version {{ editTarget.current_version }}
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label for="archiveEditCategory" class="font-medium">Category <span class="text-red-500">*</span></label>
                    <Select inputId="archiveEditCategory" v-model="editForm.category" :options="categories" optionLabel="label" optionValue="value" placeholder="Select category" filter fluid />
                    <small v-if="fieldError(editErrors, 'category')" class="text-red-500">{{ fieldError(editErrors, 'category') }}</small>
                </div>
                <div class="flex flex-col gap-2">
                    <label for="archiveEditDocumentDate" class="font-medium">Document Date <span class="text-red-500">*</span></label>
                    <DatePicker inputId="archiveEditDocumentDate" v-model="editForm.documentDate" dateFormat="yy-mm-dd" showIcon manualInput fluid />
                    <small v-if="fieldError(editErrors, 'document_date')" class="text-red-500">{{ fieldError(editErrors, 'document_date') }}</small>
                </div>
            </div>
            <div class="flex flex-col gap-2">
                <label for="archiveEditTitle" class="font-medium">Title <span class="text-red-500">*</span></label>
                <InputText id="archiveEditTitle" v-model="editForm.title" :maxlength="titleMax" fluid />
                <small v-if="fieldError(editErrors, 'title')" class="text-red-500">{{ fieldError(editErrors, 'title') }}</small>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label for="archiveEditNumber" class="font-medium">Document Number</label>
                    <InputText id="archiveEditNumber" v-model="editForm.documentNumber" :maxlength="documentNumberMax" placeholder="Optional document number" fluid />
                    <small v-if="fieldError(editErrors, 'document_number')" class="text-red-500">{{ fieldError(editErrors, 'document_number') }}</small>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-medium">Subject</label>
                    <div class="rounded-border border border-surface-200 dark:border-surface-700 px-3 py-2 text-sm">
                        <template v-if="editTarget?.subject">
                            <div class="font-medium">{{ editTarget.subject.label }}</div>
                            <div class="text-xs text-surface-500">{{ editTarget.subject.secondary }}</div>
                        </template>
                        <span v-else class="text-surface-500">No subject link</span>
                    </div>
                    <small class="text-surface-500">The subject link cannot be changed after creation.</small>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label for="archiveEditValidFrom" class="font-medium">Valid From</label>
                    <DatePicker inputId="archiveEditValidFrom" v-model="editForm.validFrom" dateFormat="yy-mm-dd" showIcon manualInput fluid />
                    <small v-if="fieldError(editErrors, 'valid_from')" class="text-red-500">{{ fieldError(editErrors, 'valid_from') }}</small>
                </div>
                <div class="flex flex-col gap-2">
                    <label for="archiveEditValidUntil" class="font-medium">Valid Until</label>
                    <DatePicker inputId="archiveEditValidUntil" v-model="editForm.validUntil" dateFormat="yy-mm-dd" showIcon manualInput fluid />
                    <small v-if="fieldError(editErrors, 'valid_until')" class="text-red-500">{{ fieldError(editErrors, 'valid_until') }}</small>
                </div>
            </div>
            <div class="flex flex-col gap-2">
                <label for="archiveEditDescription" class="font-medium">Description</label>
                <Textarea id="archiveEditDescription" v-model="editForm.description" rows="3" :maxlength="descriptionMax" autoResize />
                <small class="text-surface-500">{{ editForm.description.trim().length }}/{{ descriptionMax }}</small>
                <small v-if="fieldError(editErrors, 'description')" class="text-red-500">{{ fieldError(editErrors, 'description') }}</small>
            </div>
            <Message severity="info" :closable="false">Existing files are immutable. Use Add Version to upload a new file; metadata updates increase the metadata version.</Message>
        </div>
        <template #footer>
            <Button label="Cancel" severity="secondary" variant="text" :disabled="editSaving" @click="editVisible = false" />
            <Button label="Save Changes" icon="pi pi-check" :loading="editSaving" @click="submitEdit" />
        </template>
    </Dialog>

    <Dialog v-model:visible="detailVisible" modal :draggable="false" :style="{ width: 'min(96vw, 64rem)' }" header="Archive Document">
        <div class="flex flex-col gap-5 max-h-[72vh] overflow-y-auto pr-1">
            <div v-if="detailLoading" class="py-8 text-center text-surface-500">Loading archive document...</div>
            <template v-else-if="detail">
                <Message v-if="detailError" severity="error" :closable="false">{{ detailError }}</Message>
                <div class="flex flex-col gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <Tag :value="detail.category_label" severity="secondary" />
                        <Tag :value="validityLabels[detail.validity_status] ?? detail.validity_status" :severity="validitySeverity[detail.validity_status] ?? 'secondary'" />
                        <span class="text-xs text-surface-500">Metadata version {{ detail.version }}</span>
                    </div>
                    <div class="font-bold text-xl sm:text-2xl">{{ detail.title }}</div>
                    <div class="text-sm text-surface-500">{{ detail.document_number || 'No document number' }} · Document date {{ formatDate(detail.document_date) }} · File version {{ detail.current_version }}</div>
                </div>
                <div v-if="detail.description" class="text-sm whitespace-pre-line break-words">{{ detail.description }}</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="rounded-border border border-surface-200 dark:border-surface-700 p-4">
                        <div class="font-semibold mb-2">Validity</div>
                        <div class="text-sm">Valid from: {{ formatDate(detail.valid_from) }}</div>
                        <div class="text-sm">Valid until: {{ formatDate(detail.valid_until) }}</div>
                        <div class="text-xs text-surface-500 mt-2">Computed in WIB. Expiring soon within {{ expiringDays }} days.</div>
                    </div>
                    <div class="rounded-border border border-surface-200 dark:border-surface-700 p-4">
                        <div class="font-semibold mb-2">Linked Subject</div>
                        <template v-if="detail.subject">
                            <div class="text-sm font-medium">{{ detail.subject.label }}</div>
                            <div class="text-xs text-surface-500">{{ detail.subject.secondary }} · {{ subjectTypeLabel(detail.subject.type) }}</div>
                            <Tag v-if="detail.subject.archived" value="Archived" severity="secondary" class="mt-2" />
                        </template>
                        <span v-else class="text-sm text-surface-500">No subject link</span>
                    </div>
                </div>
                <div class="rounded-border border border-surface-200 dark:border-surface-700 p-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-2">
                        <div class="font-semibold">Current File</div>
                        <Button
                            v-if="detail.latest_version && detail.can?.download"
                            icon="pi pi-download"
                            label="Download"
                            size="small"
                            severity="secondary"
                            variant="outlined"
                            :loading="downloadingKey === `${detail.id}:${detail.latest_version.version_number}`"
                            @click="downloadVersion(detail.id, detail.latest_version.version_number, detail.latest_version.original_name)"
                        />
                    </div>
                    <template v-if="detail.latest_version">
                        <div class="text-sm font-medium break-all">{{ detail.latest_version.original_name }}</div>
                        <div class="text-xs text-surface-500">
                            {{ detail.latest_version.mime_type }} · {{ formatSize(detail.latest_version.size_bytes) }} · uploaded by
                            {{ detail.latest_version.uploaded_by?.name ?? '-' }} · {{ formatDateTime(detail.latest_version.created_at) }}
                        </div>
                        <div v-if="detail.latest_version.note" class="text-sm mt-2 whitespace-pre-line break-words">{{ detail.latest_version.note }}</div>
                    </template>
                    <span v-else class="text-sm text-surface-500">No file recorded.</span>
                </div>
                <div class="text-xs text-surface-500">
                    Created by {{ detail.created_by?.name ?? '-' }} · {{ formatDateTime(detail.created_at) }}. Last updated by
                    {{ detail.updated_by?.name ?? '-' }} · {{ formatDateTime(detail.updated_at) }}.
                </div>
                <div class="flex flex-col gap-2">
                    <div class="font-semibold">Version History</div>
                    <span v-if="versionsError" class="text-red-500 text-sm">{{ versionsError }}</span>
                    <DataTable
                        :value="versions"
                        :lazy="true"
                        paginator
                        showGridlines
                        scrollable
                        :rows="versionsMeta.per_page"
                        :totalRecords="versionsMeta.total"
                        :first="(versionsMeta.current_page - 1) * versionsMeta.per_page"
                        dataKey="id"
                        :loading="versionsLoading"
                        tableStyle="min-width: 52rem"
                        paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
                        currentPageReportTemplate="Showing {first} to {last} of {totalRecords} versions"
                        @page="onVersionsPage"
                    >
                        <template #empty>
                            <span class="text-sm text-surface-500">No file versions recorded.</span>
                        </template>
                        <Column header="Version" style="min-width: 7rem">
                            <template #body="{ data }">
                                <span class="font-medium">v{{ data.version_number }}</span>
                                <Tag v-if="data.version_number === detail.current_version" value="Current" severity="success" class="ml-2" />
                            </template>
                        </Column>
                        <Column header="File" style="min-width: 16rem">
                            <template #body="{ data }">
                                <div class="text-sm truncate" :title="data.original_name">{{ data.original_name }}</div>
                                <div class="text-xs text-surface-500">{{ data.mime_type }} · {{ formatSize(data.size_bytes) }}</div>
                            </template>
                        </Column>
                        <Column header="Note" style="min-width: 14rem">
                            <template #body="{ data }">
                                <span class="text-sm block truncate" :title="data.note ?? ''">{{ data.note || '-' }}</span>
                            </template>
                        </Column>
                        <Column header="Uploaded By" style="min-width: 10rem">
                            <template #body="{ data }">
                                <div class="text-sm">{{ data.uploaded_by?.name ?? '-' }}</div>
                                <div class="text-xs text-surface-500">{{ formatDateTime(data.created_at) }}</div>
                            </template>
                        </Column>
                        <Column header="Actions" style="min-width: 6rem">
                            <template #body="{ data }">
                                <Button
                                    icon="pi pi-download"
                                    aria-label="Download archive version"
                                    size="small"
                                    variant="outlined"
                                    rounded
                                    :loading="downloadingKey === `${detail.id}:${data.version_number}`"
                                    v-tooltip.top="'Download'"
                                    @click="downloadVersion(detail.id, data.version_number, data.original_name)"
                                />
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </template>
            <div v-else class="flex flex-col items-center gap-3 py-8">
                <span class="text-red-500">{{ detailError || 'The archive document could not be loaded.' }}</span>
                <Button icon="pi pi-refresh" label="Retry" @click="detailError = ''; loadDetail(detail?.id)" />
            </div>
        </div>
        <template #footer>
            <Button label="Close" severity="secondary" variant="text" @click="detailVisible = false" />
            <Button v-if="detail?.can?.add_version" label="Add Version" icon="pi pi-upload" severity="secondary" variant="outlined" @click="openAddVersion(detail)" />
            <Button v-if="detail?.can?.update" label="Edit Metadata" icon="pi pi-pencil" @click="openEdit(detail)" />
        </template>
    </Dialog>

    <Dialog v-model:visible="addVisible" modal :draggable="false" :style="{ width: 'min(96vw, 44rem)' }" header="Add Archive Version">
        <div class="flex flex-col gap-4 max-h-[70vh] overflow-y-auto pr-1">
            <Message v-if="addError" severity="error" :closable="false">{{ addError }}</Message>
            <div v-if="addTarget" class="text-sm text-surface-500">
                <span class="font-medium text-surface-900 dark:text-surface-0">{{ addTarget.title }}</span>
                · current file version {{ addTarget.current_version }} · metadata version {{ addTarget.version }}
            </div>
            <Message severity="info" :closable="false">A new file version is added to the history. Existing versions and their metadata remain unchanged.</Message>
            <div class="flex flex-col gap-2">
                <label for="archiveAddFile" class="font-medium">File <span class="text-red-500">*</span></label>
                <input id="archiveAddFile" ref="addFileInput" type="file" :accept="acceptAttr" class="block w-full text-sm" aria-label="Choose a new archive file version" @change="onAddFileSelected" />
                <small class="text-surface-500">{{ fileRuleLabel }}</small>
                <small v-if="addFile" class="text-surface-500">Selected: {{ addFile.name }} ({{ formatSize(addFile.size) }})</small>
                <small v-if="fieldError(addErrors, 'file')" class="text-red-500">{{ fieldError(addErrors, 'file') }}</small>
            </div>
            <div class="flex flex-col gap-2">
                <label for="archiveAddNote" class="font-medium">Version Note</label>
                <Textarea id="archiveAddNote" v-model="addNote" rows="3" :maxlength="noteMax" autoResize placeholder="Optional note describing this version" />
                <small class="text-surface-500">{{ addNote.trim().length }}/{{ noteMax }}</small>
                <small v-if="fieldError(addErrors, 'note')" class="text-red-500">{{ fieldError(addErrors, 'note') }}</small>
                <small v-if="fieldError(addErrors, 'expected_version')" class="text-red-500">{{ fieldError(addErrors, 'expected_version') }}</small>
            </div>
        </div>
        <template #footer>
            <Button label="Cancel" severity="secondary" variant="text" :disabled="addSaving" @click="addVisible = false" />
            <Button label="Upload Version" icon="pi pi-upload" :loading="addSaving" @click="submitAddVersion" />
        </template>
    </Dialog>

    <Dialog v-model:visible="conflictVisible" modal :draggable="false" :style="{ width: 'min(94vw, 34rem)' }" header="Conflict">
        <div class="flex flex-col gap-3">
            <Message severity="warn" :closable="false">{{ conflictMessage }}</Message>
            <p class="text-sm text-surface-500">Reload the latest archive documents to see the current version before trying again.</p>
        </div>
        <template #footer>
            <Button label="Close" severity="secondary" variant="text" @click="conflictVisible = false" />
            <Button label="Reload Latest" icon="pi pi-refresh" :loading="reloadingConflict" @click="reloadLatest" />
        </template>
    </Dialog>
</template>
