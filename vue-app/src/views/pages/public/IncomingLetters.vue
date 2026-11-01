<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import apiLogin from '@/apiLogin';
import { authStore } from '@/stores/authStore';

const store = authStore();
const toast = useToast();

const canManage = computed(() => store.can('letters.manage'));
const canAssign = computed(() => store.can('letters.manage') && store.can('employees.view'));

const dispositionLabels = {
    pending: 'Pending',
    in_progress: 'In Progress',
    completed: 'Completed',
    cancelled: 'Cancelled'
};

const dispositionSeverity = {
    pending: 'warn',
    in_progress: 'info',
    completed: 'success',
    cancelled: 'danger'
};

const statusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Void', value: 'void' },
    { label: 'All statuses', value: 'all' }
];

const fieldLabels = {
    received_date: 'Received date',
    letter_date: 'Letter date',
    source_number: 'Source number',
    sender: 'Sender',
    subject: 'Subject',
    category: 'Category',
    notes: 'Notes'
};

const eventLabels = {
    created: 'Created',
    updated: 'Updated',
    voided: 'Voided',
    attachment_added: 'Attachment added',
    attachment_removed: 'Attachment removed',
    disposition_created: 'Disposition created',
    disposition_updated: 'Disposition updated',
    disposition_completed: 'Disposition completed',
    disposition_cancelled: 'Disposition cancelled'
};

const eventIcons = {
    created: 'pi pi-plus-circle',
    updated: 'pi pi-pencil',
    voided: 'pi pi-ban',
    attachment_added: 'pi pi-paperclip',
    attachment_removed: 'pi pi-trash',
    disposition_created: 'pi pi-user-plus',
    disposition_updated: 'pi pi-pencil',
    disposition_completed: 'pi pi-check-circle',
    disposition_cancelled: 'pi pi-ban'
};

const options = ref(null);
const optionsLoading = ref(false);
const optionsError = ref('');

const items = ref([]);
const meta = ref({ current_page: 1, per_page: 20, total: 0, last_page: 1 });
const listLoading = ref(false);
const listError = ref('');
const page = ref(1);
const perPage = ref(20);
const filters = reactive({ search: '', category: null, status: 'active', receivedFrom: null, receivedTo: null });
let searchTimer = null;
let listRequestId = 0;
let lastDateFilterKey = '|';

const categoryOptions = computed(() => (options.value?.categories ?? []).map((category) => ({ label: category, value: category })));
const perPageOptions = computed(() => options.value?.per_page_options ?? [10, 20, 50, 100]);
const maxFiles = computed(() => options.value?.max_files_per_letter ?? 5);
const maxFileKb = computed(() => options.value?.max_file_kb ?? 10240);
const allowedExtensions = computed(() => options.value?.allowed_extensions ?? ['pdf', 'jpg', 'jpeg', 'png']);
const acceptAttr = computed(() => allowedExtensions.value.map((extension) => `.${extension}`).join(','));
const maxFileLabel = computed(() => `${Math.floor(maxFileKb.value / 1024)} MB`);
const perPageLabel = computed(() => `Rows: ${perPage.value}`);

const notify = (severity, detail, summary = 'Incoming Letters') => {
    toast.add({ severity, summary, detail, life: 5000 });
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
    const [yearPart, month, day] = String(value).slice(0, 10).split('-').map(Number);
    if (!yearPart || !month || !day) return null;
    return new Date(yearPart, month - 1, day);
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

const loadOptions = async () => {
    optionsLoading.value = true;
    optionsError.value = '';

    try {
        const response = await apiLogin.get('/api/letters/incoming/options');
        options.value = response.data?.data ?? null;
        perPage.value = options.value?.default_per_page ?? perPage.value;
    } catch (error) {
        options.value = null;
        optionsError.value = errorMessage(error, 'Failed to load letter options. Default values are used.');
    } finally {
        optionsLoading.value = false;
    }
};

const loadLetters = async () => {
    const requestId = ++listRequestId;
    listLoading.value = true;
    listError.value = '';

    try {
        const params = {
            page: page.value,
            per_page: perPage.value,
            status: filters.status
        };

        if (filters.search.trim()) params.search = filters.search.trim();
        if (filters.category) params.category = filters.category;

        const from = dateToIso(filters.receivedFrom);
        const to = dateToIso(filters.receivedTo);
        if (from) params.received_from = from;
        if (to) params.received_to = to;

        const response = await apiLogin.get('/api/letters/incoming', { params });
        if (requestId !== listRequestId) return;

        const data = response.data?.data ?? {};
        items.value = data.items ?? [];
        meta.value = data.meta ?? { current_page: 1, per_page: perPage.value, total: 0, last_page: 1 };

        if (meta.value.per_page) perPage.value = meta.value.per_page;

        if (items.value.length === 0 && page.value > 1 && page.value > meta.value.last_page) {
            page.value = Math.max(1, meta.value.last_page);
            return loadLetters();
        }
    } catch (error) {
        if (requestId !== listRequestId) return;
        items.value = [];
        listError.value = errorMessage(error, 'Failed to load incoming letters. Please try again.');
    } finally {
        if (requestId === listRequestId) listLoading.value = false;
    }
};

const onSearchInput = () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        page.value = 1;
        loadLetters();
    }, 300);
};

const onFilterChange = () => {
    page.value = 1;
    loadLetters();
};

const onDateFilterChange = () => {
    const key = `${dateToIso(filters.receivedFrom) ?? ''}|${dateToIso(filters.receivedTo) ?? ''}`;
    if (key === lastDateFilterKey) return;
    lastDateFilterKey = key;
    onFilterChange();
};

const onPerPageChange = () => {
    page.value = 1;
    loadLetters();
};

const resetFilters = () => {
    filters.search = '';
    filters.category = null;
    filters.status = 'active';
    filters.receivedFrom = null;
    filters.receivedTo = null;
    lastDateFilterKey = '|';
    page.value = 1;
    loadLetters();
};

const onPage = (event) => {
    const nextPage = event.page + 1;
    const rowsChanged = event.rows !== perPage.value;
    page.value = rowsChanged ? 1 : nextPage;
    if (rowsChanged) perPage.value = event.rows;
    loadLetters();
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

const formVisible = ref(false);
const formMode = ref('create');
const formSaving = ref(false);
const formErrors = ref({});
const formError = ref('');
const form = reactive({
    id: null,
    reference: null,
    receivedDate: null,
    letterDate: null,
    sourceNumber: '',
    sender: '',
    subject: '',
    category: null,
    notes: '',
    version: null
});

const openCreate = () => {
    const today = isoToDate(todayIso());
    formMode.value = 'create';
    form.id = null;
    form.reference = null;
    form.receivedDate = today;
    form.letterDate = today;
    form.sourceNumber = '';
    form.sender = '';
    form.subject = '';
    form.category = null;
    form.notes = '';
    form.version = null;
    formErrors.value = {};
    formError.value = '';
    formSaving.value = false;
    formVisible.value = true;
};

const startEdit = (source) => {
    if (!canManage.value || source.status !== 'active') return;

    formMode.value = 'edit';
    form.id = source.id;
    form.reference = source.reference;
    form.receivedDate = isoToDate(source.received_date);
    form.letterDate = isoToDate(source.letter_date);
    form.sourceNumber = source.source_number ?? '';
    form.sender = source.sender ?? '';
    form.subject = source.subject ?? '';
    form.category = source.category ?? null;
    form.notes = source.notes ?? '';
    form.version = source.version;
    formErrors.value = {};
    formError.value = '';
    formSaving.value = false;
    detailVisible.value = false;
    formVisible.value = true;
};

const validateForm = () => {
    const errors = {};
    const received = dateToIso(form.receivedDate);
    const letter = dateToIso(form.letterDate);
    const sender = form.sender.trim();
    const subject = form.subject.trim();

    if (!received) errors.received_date = ['The received date is required.'];
    else if (received > todayIso()) errors.received_date = ['The received date may not be in the future.'];

    if (!letter) errors.letter_date = ['The letter date is required.'];
    else if (received && letter > received) errors.letter_date = ['The letter date may not be after the received date.'];

    if (!sender) errors.sender = ['The sender is required.'];
    else if (sender.length > 150) errors.sender = ['The sender may not exceed 150 characters.'];

    if (!subject) errors.subject = ['The subject is required.'];
    else if (subject.length > 200) errors.subject = ['The subject may not exceed 200 characters.'];

    if (!form.category) errors.category = ['The category is required.'];
    if (form.sourceNumber.trim().length > 100) errors.source_number = ['The source number may not exceed 100 characters.'];
    if (form.notes.trim().length > 2000) errors.notes = ['The notes may not exceed 2000 characters.'];

    return errors;
};

const submitForm = async () => {
    if (formSaving.value) return;

    formErrors.value = {};
    formError.value = '';

    const errors = validateForm();
    if (Object.keys(errors).length > 0) {
        formErrors.value = errors;
        return;
    }

    const payload = {
        received_date: dateToIso(form.receivedDate),
        letter_date: dateToIso(form.letterDate),
        source_number: form.sourceNumber.trim() || null,
        sender: form.sender.trim(),
        subject: form.subject.trim(),
        category: form.category,
        notes: form.notes.trim() || null
    };

    const editing = formMode.value === 'edit';
    if (editing) payload.expected_version = form.version;

    formSaving.value = true;
    try {
        const response = editing ? await apiLogin.put(`/api/letters/incoming/${form.id}`, payload) : await apiLogin.post('/api/letters/incoming', payload);

        formVisible.value = false;
        notify('success', response.data?.message ?? 'Letter saved.');
        await loadLetters();
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            formErrors.value = validationErrors(error);
            if (Object.keys(formErrors.value).length === 0) {
                formError.value = errorMessage(error, 'The letter could not be saved.');
            }
        } else if (status === 409) {
            formVisible.value = false;
            openConflict(error, form.id);
        } else {
            notify('error', errorMessage(error, 'Failed to save the letter.'));
        }
    } finally {
        formSaving.value = false;
    }
};

const openDetail = async (row) => {
    detailVisible.value = true;
    detailLoading.value = true;
    detailError.value = '';
    uploadError.value = '';
    selectedFile.value = null;
    if (fileInput.value) fileInput.value.value = '';

    const requestId = ++detailRequestId;
    try {
        const response = await apiLogin.get(`/api/letters/incoming/${row.id}`);
        if (requestId !== detailRequestId) return;
        detail.value = response.data?.data ?? null;
    } catch (error) {
        if (requestId !== detailRequestId) return;
        detail.value = null;
        detailError.value = error.response?.status === 404 ? 'This letter no longer exists. Refresh the list.' : errorMessage(error, 'Failed to load the letter.');
    } finally {
        if (requestId === detailRequestId) detailLoading.value = false;
    }
};

const voidVisible = ref(false);
const voidSaving = ref(false);
const voidTarget = ref(null);
const voidReason = ref('');
const voidErrors = ref({});
const voidError = ref('');

const openVoid = (source) => {
    if (!canManage.value || source.status !== 'active') return;

    voidTarget.value = {
        id: source.id,
        reference: source.reference,
        subject: source.subject,
        version: source.version
    };
    voidReason.value = '';
    voidErrors.value = {};
    voidError.value = '';
    voidSaving.value = false;
    detailVisible.value = false;
    voidVisible.value = true;
};

const submitVoid = async () => {
    if (voidSaving.value || !voidTarget.value) return;

    voidErrors.value = {};
    voidError.value = '';

    const reason = voidReason.value.trim();
    if (reason.length < 10 || reason.length > 255) {
        voidErrors.value = {
            reason: [reason.length < 10 ? 'The reason must be at least 10 characters.' : 'The reason may not exceed 255 characters.']
        };
        return;
    }

    voidSaving.value = true;
    try {
        const response = await apiLogin.post(`/api/letters/incoming/${voidTarget.value.id}/void`, {
            reason,
            expected_version: voidTarget.value.version
        });
        voidVisible.value = false;
        notify('success', response.data?.message ?? 'Letter voided.');
        await loadLetters();
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            voidErrors.value = validationErrors(error);
            if (Object.keys(voidErrors.value).length === 0) {
                voidError.value = errorMessage(error, 'The letter could not be voided.');
            }
        } else if (status === 409) {
            voidVisible.value = false;
            openConflict(error, voidTarget.value.id);
        } else {
            notify('error', errorMessage(error, 'Failed to void the letter.'));
        }
    } finally {
        voidSaving.value = false;
    }
};

const conflictVisible = ref(false);
const conflictMessage = ref('');
const conflictLetterId = ref(null);
const reloadingConflict = ref(false);

const openConflict = (error, letterId) => {
    conflictMessage.value = errorMessage(error, 'This letter was changed by another request.');
    conflictLetterId.value = letterId ?? null;
    conflictVisible.value = true;
};

const reloadLatest = async () => {
    if (reloadingConflict.value) return;
    reloadingConflict.value = true;

    const targetId = conflictLetterId.value;
    try {
        if (targetId) {
            const response = await apiLogin.get(`/api/letters/incoming/${targetId}`);
            detail.value = response.data?.data ?? null;
            detailError.value = '';
            detailVisible.value = true;
            notify('warn', 'The latest version has been loaded. Review the letter before trying again.');
        }
        conflictVisible.value = false;
        await loadLetters();
    } catch (error) {
        if (error.response?.status === 404) {
            conflictVisible.value = false;
            detailVisible.value = false;
            formVisible.value = false;
            voidVisible.value = false;
            await loadLetters();
            notify('warn', 'The letter no longer exists. The list has been refreshed.');
        } else {
            notify('error', errorMessage(error, 'Failed to reload the latest letter.'));
        }
    } finally {
        reloadingConflict.value = false;
    }
};

const onFileSelected = (event) => {
    selectedFile.value = event.target.files?.[0] ?? null;
    uploadError.value = '';
};

const uploadAttachment = async () => {
    if (!detail.value || !selectedFile.value || uploading.value) return;

    uploadError.value = '';
    const file = selectedFile.value;
    const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

    if (!allowedExtensions.value.includes(extension)) {
        uploadError.value = `Only ${allowedExtensions.value.join(', ').toUpperCase()} files are allowed.`;
        return;
    }

    if (file.size > maxFileKb.value * 1024) {
        uploadError.value = `The file may not exceed ${maxFileLabel.value}.`;
        return;
    }

    if ((detail.value.attachments?.length ?? 0) >= maxFiles.value) {
        uploadError.value = `A letter can have at most ${maxFiles.value} attachments.`;
        return;
    }

    const body = new FormData();
    body.append('file', file);

    uploading.value = true;
    try {
        const response = await apiLogin.post(`/api/letters/incoming/${detail.value.id}/attachments`, body);
        detail.value = response.data?.data ?? detail.value;
        selectedFile.value = null;
        if (fileInput.value) fileInput.value.value = '';
        notify('success', response.data?.message ?? 'Attachment uploaded.');
        await loadLetters();
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
        const response = await apiLogin.get(`/api/letters/incoming/${detail.value.id}/attachments/${attachment.id}/download`, {
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
    if (!detail.value || !canManage.value || detail.value.status !== 'active') return;

    removeTarget.value = { letterId: detail.value.id, attachment };
    removeError.value = '';
    removeSaving.value = false;
    removeVisible.value = true;
};

const confirmRemoveAttachment = async () => {
    if (removeSaving.value || !removeTarget.value) return;

    const { letterId, attachment } = removeTarget.value;
    removeSaving.value = true;
    removeError.value = '';

    try {
        const response = await apiLogin.delete(`/api/letters/incoming/${letterId}/attachments/${attachment.id}`);
        detail.value = response.data?.data ?? detail.value;
        removeVisible.value = false;
        notify('success', response.data?.message ?? 'Attachment removed.');
        await loadLetters();
    } catch (error) {
        if (error.response?.status === 409) {
            removeVisible.value = false;
            detailVisible.value = false;
            openConflict(error, letterId);
        } else {
            removeError.value = errorMessage(error, 'Failed to remove the attachment.');
        }
    } finally {
        removeSaving.value = false;
    }
};

const assignVisible = ref(false);
const assignSaving = ref(false);
const assignTarget = ref(null);
const assignErrors = ref({});
const assignError = ref('');
const recipientOptions = ref([]);
const recipientLoading = ref(false);
const assignForm = reactive({ recipients: [], dueDate: null, note: '' });
let recipientTimer = null;

const recipientFieldErrors = computed(() =>
    Object.entries(assignErrors.value)
        .filter(([key]) => String(key).startsWith('recipients.'))
        .map(([key, messages]) => ({
            key,
            message: Array.isArray(messages) ? messages[0] : String(messages)
        }))
);

const searchRecipients = async (query = '') => {
    if (!canAssign.value) return;

    recipientLoading.value = true;
    try {
        const params = { per_page: 100 };
        if (query.trim()) params.search = query.trim();
        const response = await apiLogin.get('/api/letters/dispositions/recipients', { params });
        recipientOptions.value = response.data?.data?.items ?? [];
    } catch (error) {
        recipientOptions.value = [];
        assignError.value = errorMessage(error, 'Failed to load active employees.');
    } finally {
        recipientLoading.value = false;
    }
};

const onRecipientFilter = (event) => {
    if (recipientTimer) clearTimeout(recipientTimer);
    const query = event?.value ?? '';
    recipientTimer = setTimeout(() => searchRecipients(query), 250);
};

const openAssign = (source) => {
    if (!canAssign.value || !source || source.status !== 'active') return;

    assignTarget.value = { id: source.id, reference: source.reference, subject: source.subject };
    assignForm.recipients = [];
    assignForm.dueDate = null;
    assignForm.note = '';
    assignErrors.value = {};
    assignError.value = '';
    assignSaving.value = false;
    recipientOptions.value = [];
    detailVisible.value = false;
    assignVisible.value = true;
    searchRecipients();
};

const submitAssign = async () => {
    if (assignSaving.value || !assignTarget.value) return;

    assignErrors.value = {};
    assignError.value = '';

    const errors = {};
    if (!Array.isArray(assignForm.recipients) || assignForm.recipients.length === 0) {
        errors.recipients = ['Select at least one recipient.'];
    }
    if (!dateToIso(assignForm.dueDate)) {
        errors.due_date = ['The due date is required.'];
    }
    if (assignForm.note.trim().length > 2000) {
        errors.note = ['The note may not exceed 2000 characters.'];
    }

    if (Object.keys(errors).length > 0) {
        assignErrors.value = errors;
        return;
    }

    assignSaving.value = true;
    try {
        const response = await apiLogin.post(`/api/letters/incoming/${assignTarget.value.id}/dispositions`, {
            recipients: assignForm.recipients.map((recipient) => Number(recipient)),
            due_date: dateToIso(assignForm.dueDate),
            note: assignForm.note.trim() || null
        });
        assignVisible.value = false;
        detail.value = response.data?.data ?? detail.value;
        detailVisible.value = true;
        notify('success', response.data?.message ?? 'Dispositions created.');
        await loadLetters();
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            assignErrors.value = validationErrors(error);
            if (Object.keys(assignErrors.value).length === 0) {
                assignError.value = errorMessage(error, 'The disposition could not be created.');
            }
        } else if (status === 409) {
            assignVisible.value = false;
            openConflict(error, assignTarget.value.id);
        } else {
            assignError.value = errorMessage(error, 'Failed to create the disposition.');
        }
    } finally {
        assignSaving.value = false;
    }
};

const changedFieldLabels = (fields) => {
    if (!Array.isArray(fields) || fields.length === 0) return '-';
    return fields.map((field) => fieldLabels[field] ?? field).join(', ');
};

const eventLabel = (event) => eventLabels[event] ?? event;
const eventIcon = (event) => eventIcons[event] ?? 'pi pi-history';

onMounted(async () => {
    await store.fetchIdentity();
    await loadOptions();
    await loadLetters();
});
</script>

<template>
    <Fluid>
        <div class="bg-surface-0 dark:bg-surface-950 px-4 py-6 sm:px-6 sm:py-8 md:px-12 lg:px-20">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <div class="font-bold text-2xl sm:text-3xl text-surface-900 dark:text-surface-0 mb-2">Incoming Letters</div>
                    <p class="text-surface-500 dark:text-surface-400">Agenda of incoming letters with private attachments, versioned edits, and void history.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Tag v-if="canManage" :value="`${meta.total} letters`" severity="secondary" />
                    <Button v-if="canManage" label="New Letter" icon="pi pi-plus" @click="openCreate" />
                </div>
            </div>

            <div v-if="optionsError" class="mt-4 flex flex-col sm:flex-row sm:items-center gap-3 rounded border border-orange-300 dark:border-orange-700 bg-orange-50 dark:bg-orange-950 px-4 py-3 text-sm text-orange-700 dark:text-orange-300">
                <span class="flex-1">{{ optionsError }}</span>
                <Button label="Retry" icon="pi pi-refresh" size="small" severity="secondary" variant="outlined" :loading="optionsLoading" @click="loadOptions" />
            </div>

            <div class="card mt-6 flex flex-col gap-4">
                <div class="flex flex-col xl:flex-row xl:items-center gap-3">
                    <IconField class="w-full xl:max-w-sm">
                        <InputIcon><i class="pi pi-search" /></InputIcon>
                        <InputText v-model="filters.search" placeholder="Search number, sender, or subject" class="w-full" maxlength="100" aria-label="Search letters" @input="onSearchInput" />
                    </IconField>
                    <Select v-model="filters.category" :options="categoryOptions" optionLabel="label" optionValue="value" placeholder="All categories" showClear filter class="w-full xl:w-48" aria-label="Category" @change="onFilterChange" />
                    <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value" class="w-full xl:w-40" aria-label="Status" @change="onFilterChange" />
                    <DatePicker
                        v-model="filters.receivedFrom"
                        placeholder="Received from"
                        dateFormat="yy-mm-dd"
                        showIcon
                        manualInput
                        fluid
                        class="w-full xl:w-44"
                        aria-label="Received from"
                        @date-select="onDateFilterChange"
                        @blur="onDateFilterChange"
                    />
                    <DatePicker v-model="filters.receivedTo" placeholder="Received to" dateFormat="yy-mm-dd" showIcon manualInput fluid class="w-full xl:w-44" aria-label="Received to" @date-select="onDateFilterChange" @blur="onDateFilterChange" />
                    <div class="flex items-center gap-2 xl:ml-auto">
                        <Select v-model="perPage" :options="perPageOptions" class="w-28" :aria-label="perPageLabel" @change="onPerPageChange" />
                        <Button label="Clear" icon="pi pi-filter-slash" severity="secondary" variant="outlined" @click="resetFilters" />
                    </div>
                </div>

                <DataTable
                    :value="items"
                    :lazy="true"
                    paginator
                    showGridlines
                    scrollable
                    :rows="perPage"
                    :totalRecords="meta.total"
                    :first="(meta.current_page - 1) * meta.per_page"
                    :rowsPerPageOptions="perPageOptions"
                    dataKey="id"
                    :loading="listLoading"
                    tableStyle="min-width: 66rem"
                    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                    currentPageReportTemplate="Showing {first} to {last} of {totalRecords} letters"
                    @page="onPage"
                >
                    <template #loading>Loading letters. Please wait.</template>
                    <template #empty>
                        <div class="flex flex-col items-center gap-3 py-6">
                            <span v-if="listError" class="text-red-500">{{ listError }}</span>
                            <span v-else>No incoming letters match the current filters.</span>
                            <Button v-if="listError" icon="pi pi-refresh" label="Retry" @click="loadLetters" />
                        </div>
                    </template>
                    <Column header="Agenda" style="min-width: 6rem">
                        <template #body="{ data }">
                            <span class="font-medium">{{ data.reference }}</span>
                        </template>
                    </Column>
                    <Column header="Received" style="min-width: 8rem">
                        <template #body="{ data }">{{ formatDate(data.received_date) }}</template>
                    </Column>
                    <Column header="Letter Date" style="min-width: 8rem">
                        <template #body="{ data }">{{ formatDate(data.letter_date) }}</template>
                    </Column>
                    <Column header="Source No." style="min-width: 9rem">
                        <template #body="{ data }">
                            <span class="block truncate" :title="data.source_number ?? ''">{{ data.source_number || '-' }}</span>
                        </template>
                    </Column>
                    <Column header="Sender" style="min-width: 11rem">
                        <template #body="{ data }">
                            <span class="block truncate" :title="data.sender">{{ data.sender }}</span>
                        </template>
                    </Column>
                    <Column header="Subject" style="min-width: 15rem">
                        <template #body="{ data }">
                            <span class="block truncate" :title="data.subject">{{ data.subject }}</span>
                        </template>
                    </Column>
                    <Column header="Category" style="min-width: 8rem">
                        <template #body="{ data }">{{ data.category }}</template>
                    </Column>
                    <Column header="Files" style="min-width: 5rem">
                        <template #body="{ data }">{{ data.attachments_count ?? 0 }}</template>
                    </Column>
                    <Column header="Dispositions" style="min-width: 8rem">
                        <template #body="{ data }">
                            <div class="flex flex-wrap items-center gap-1">
                                <Tag :value="`${data.dispositions_open_count ?? 0} open`" :severity="(data.dispositions_open_count ?? 0) > 0 ? 'info' : 'secondary'" />
                                <Tag v-if="(data.dispositions_overdue_count ?? 0) > 0" :value="`${data.dispositions_overdue_count} overdue`" severity="danger" />
                            </div>
                        </template>
                    </Column>
                    <Column header="Status" style="min-width: 6rem">
                        <template #body="{ data }">
                            <Tag :value="data.status === 'active' ? 'Active' : 'Void'" :severity="data.status === 'active' ? 'success' : 'danger'" />
                        </template>
                    </Column>
                    <Column header="Actions" style="min-width: 9rem">
                        <template #body="{ data }">
                            <div class="flex flex-wrap items-center gap-2">
                                <Button icon="pi pi-eye" aria-label="View letter" variant="outlined" rounded v-tooltip.top="'View'" @click="openDetail(data)" />
                                <Button v-if="canManage && data.status === 'active'" icon="pi pi-pencil" aria-label="Edit letter" variant="outlined" rounded v-tooltip.top="'Edit'" @click="startEdit(data)" />
                                <Button v-if="canManage && data.status === 'active'" icon="pi pi-ban" aria-label="Void letter" severity="danger" variant="outlined" rounded v-tooltip.top="'Void'" @click="openVoid(data)" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </Fluid>

    <Dialog v-model:visible="formVisible" modal :draggable="false" :style="{ width: 'min(94vw, 44rem)' }" :header="formMode === 'create' ? 'New Incoming Letter' : `Edit Letter ${form.reference ?? ''}`">
        <div class="flex flex-col gap-4">
            <Message v-if="formError" severity="error" :closable="false">{{ formError }}</Message>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label for="letterReceivedDate" class="font-medium">Received Date <span class="text-red-500">*</span></label>
                    <DatePicker inputId="letterReceivedDate" v-model="form.receivedDate" dateFormat="yy-mm-dd" showIcon manualInput fluid />
                    <small v-if="fieldError(formErrors, 'received_date')" class="text-red-500">{{ fieldError(formErrors, 'received_date') }}</small>
                </div>
                <div class="flex flex-col gap-2">
                    <label for="letterDate" class="font-medium">Letter Date <span class="text-red-500">*</span></label>
                    <DatePicker inputId="letterDate" v-model="form.letterDate" dateFormat="yy-mm-dd" showIcon manualInput fluid />
                    <small v-if="fieldError(formErrors, 'letter_date')" class="text-red-500">{{ fieldError(formErrors, 'letter_date') }}</small>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label for="letterSourceNumber" class="font-medium">Source Number</label>
                <InputText id="letterSourceNumber" v-model="form.sourceNumber" maxlength="100" placeholder="Optional" />
                <small class="text-surface-500">Optional and not unique: the sender's reference number may repeat.</small>
                <small v-if="fieldError(formErrors, 'source_number')" class="text-red-500">{{ fieldError(formErrors, 'source_number') }}</small>
            </div>

            <div class="flex flex-col gap-2">
                <label for="letterSender" class="font-medium">Sender <span class="text-red-500">*</span></label>
                <InputText id="letterSender" v-model="form.sender" maxlength="150" placeholder="Sender name or institution" />
                <small v-if="fieldError(formErrors, 'sender')" class="text-red-500">{{ fieldError(formErrors, 'sender') }}</small>
            </div>

            <div class="flex flex-col gap-2">
                <label for="letterSubject" class="font-medium">Subject <span class="text-red-500">*</span></label>
                <InputText id="letterSubject" v-model="form.subject" maxlength="200" placeholder="Letter subject" />
                <small v-if="fieldError(formErrors, 'subject')" class="text-red-500">{{ fieldError(formErrors, 'subject') }}</small>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label for="letterCategory" class="font-medium">Category <span class="text-red-500">*</span></label>
                    <Select inputId="letterCategory" v-model="form.category" :options="categoryOptions" optionLabel="label" optionValue="value" placeholder="Select category" filter fluid />
                    <small v-if="fieldError(formErrors, 'category')" class="text-red-500">{{ fieldError(formErrors, 'category') }}</small>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label for="letterNotes" class="font-medium">Notes</label>
                <Textarea id="letterNotes" v-model="form.notes" rows="3" maxlength="2000" autoResize placeholder="Optional internal notes" />
                <small class="text-surface-500">{{ form.notes.length }}/2000</small>
                <small v-if="fieldError(formErrors, 'notes')" class="text-red-500">{{ fieldError(formErrors, 'notes') }}</small>
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" severity="secondary" variant="text" :disabled="formSaving" @click="formVisible = false" />
            <Button :label="formMode === 'create' ? 'Create Letter' : 'Save Changes'" icon="pi pi-check" :loading="formSaving" @click="submitForm" />
        </template>
    </Dialog>

    <Dialog v-model:visible="voidVisible" modal :draggable="false" :style="{ width: 'min(94vw, 36rem)' }" header="Void Letter">
        <div class="flex flex-col gap-4">
            <Message severity="warn" :closable="false"> Voiding keeps the letter, its attachments, and its history. The letter becomes read-only and remains visible when the Void filter is selected. </Message>

            <div v-if="voidTarget" class="text-sm text-surface-500">
                <span class="font-medium text-surface-900 dark:text-surface-0">{{ voidTarget.reference }}</span>
                — {{ voidTarget.subject }}
            </div>

            <div class="flex flex-col gap-2">
                <label for="voidReason" class="font-medium">Reason <span class="text-red-500">*</span></label>
                <Textarea id="voidReason" v-model="voidReason" rows="3" maxlength="255" autoResize placeholder="Explain why the letter is voided" />
                <small class="text-surface-500">At least 10 characters. {{ voidReason.trim().length }}/255</small>
                <small v-if="fieldError(voidErrors, 'reason')" class="text-red-500">{{ fieldError(voidErrors, 'reason') }}</small>
                <small v-if="voidError" class="text-red-500">{{ voidError }}</small>
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" severity="secondary" variant="text" :disabled="voidSaving" @click="voidVisible = false" />
            <Button label="Void Letter" icon="pi pi-ban" severity="danger" :loading="voidSaving" @click="submitVoid" />
        </template>
    </Dialog>

    <Dialog v-model:visible="detailVisible" modal :draggable="false" :style="{ width: 'min(94vw, 60rem)' }" :header="detail ? `Letter ${detail.reference}` : 'Letter'">
        <div v-if="detailLoading" class="flex justify-center py-10">
            <i class="pi pi-spin pi-spinner text-2xl" aria-hidden="true"></i>
        </div>
        <Message v-else-if="detailError" severity="error" :closable="false">{{ detailError }}</Message>
        <div v-else-if="detail" class="flex flex-col gap-5">
            <div class="flex flex-wrap items-center gap-2">
                <Tag :value="detail.status === 'active' ? 'Active' : 'Void'" :severity="detail.status === 'active' ? 'success' : 'danger'" />
                <Tag :value="detail.category" severity="secondary" />
                <Tag :value="`Version ${detail.version}`" severity="contrast" />
                <span class="text-sm text-surface-500"> Recorded by {{ detail.created_by?.name ?? '-' }} · {{ formatDateTime(detail.created_at) }} </span>
                <div v-if="canManage && detail.status === 'active'" class="flex flex-wrap gap-2 sm:ml-auto">
                    <Button label="Edit" icon="pi pi-pencil" size="small" variant="outlined" @click="startEdit(detail)" />
                    <Button label="Void" icon="pi pi-ban" size="small" severity="danger" variant="outlined" @click="openVoid(detail)" />
                </div>
            </div>

            <Message v-if="detail.status === 'void'" severity="error" :closable="false"> Voided by {{ detail.voided_by?.name ?? 'a user' }} on {{ formatDateTime(detail.voided_at) }}. Reason: {{ detail.void_reason }} </Message>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div>
                    <div class="text-surface-500">Received date</div>
                    <div class="font-medium">{{ formatDate(detail.received_date) }}</div>
                </div>
                <div>
                    <div class="text-surface-500">Letter date</div>
                    <div class="font-medium">{{ formatDate(detail.letter_date) }}</div>
                </div>
                <div>
                    <div class="text-surface-500">Source number</div>
                    <div class="font-medium break-words">{{ detail.source_number || '-' }}</div>
                </div>
                <div>
                    <div class="text-surface-500">Category</div>
                    <div class="font-medium">{{ detail.category }}</div>
                </div>
                <div>
                    <div class="text-surface-500">Sender</div>
                    <div class="font-medium break-words">{{ detail.sender }}</div>
                </div>
                <div>
                    <div class="text-surface-500">Subject</div>
                    <div class="font-medium break-words">{{ detail.subject }}</div>
                </div>
            </div>

            <div v-if="detail.notes">
                <div class="text-sm text-surface-500 mb-1">Notes</div>
                <div class="text-sm whitespace-pre-line break-words">{{ detail.notes }}</div>
            </div>

            <div class="border border-surface-200 dark:border-surface-700 rounded p-4 flex flex-col gap-3">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <div class="font-semibold">
                            Dispositions ({{ detail.dispositions_open_count ?? 0 }} open<template v-if="(detail.dispositions_overdue_count ?? 0) > 0">, {{ detail.dispositions_overdue_count }} overdue</template>)
                        </div>
                        <small class="text-surface-500">Follow-up updates are managed on the Dispositions page.</small>
                    </div>
                    <Button v-if="canAssign && detail.status === 'active'" label="Assign" icon="pi pi-user-plus" size="small" variant="outlined" @click="openAssign(detail)" />
                </div>

                <div v-if="!detail.dispositions || detail.dispositions.length === 0" class="text-sm text-surface-500">No dispositions yet.</div>
                <ul v-else class="divide-y divide-surface-200 dark:divide-surface-700">
                    <li v-for="disposition in detail.dispositions" :key="disposition.id" class="py-3 text-sm flex flex-col gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ disposition.recipient?.name ?? '-' }}</span>
                            <span v-if="disposition.recipient?.employee_type" class="text-xs text-surface-500">{{ disposition.recipient.employee_type }}</span>
                            <Tag :value="dispositionLabels[disposition.status] ?? disposition.status" :severity="dispositionSeverity[disposition.status] ?? 'secondary'" />
                            <Tag v-if="disposition.overdue" value="Overdue" severity="danger" />
                            <span class="text-xs text-surface-500">Due {{ formatDate(disposition.due_date) }} · Version {{ disposition.version }}</span>
                        </div>
                        <div v-if="disposition.note" class="whitespace-pre-line break-words"><span class="text-surface-500">Note: </span>{{ disposition.note }}</div>
                        <div v-if="disposition.follow_up_note" class="whitespace-pre-line break-words"><span class="text-surface-500">Follow-up: </span>{{ disposition.follow_up_note }}</div>
                        <div v-if="disposition.cancel_reason" class="whitespace-pre-line break-words"><span class="text-surface-500">Cancel reason: </span>{{ disposition.cancel_reason }}</div>
                        <div v-if="disposition.completed_by" class="text-xs text-surface-500">Completed by {{ disposition.completed_by.name ?? '-' }} · {{ formatDateTime(disposition.completed_at) }}</div>
                        <div v-if="disposition.cancelled_by" class="text-xs text-surface-500">Cancelled by {{ disposition.cancelled_by.name ?? '-' }} · {{ formatDateTime(disposition.cancelled_at) }}</div>
                    </li>
                </ul>
            </div>

            <div class="border border-surface-200 dark:border-surface-700 rounded p-4 flex flex-col gap-3">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="font-semibold">Attachments ({{ detail.attachments.length }}/{{ maxFiles }})</div>
                    <div v-if="canManage && detail.status === 'active' && detail.attachments.length < maxFiles" class="flex flex-col sm:flex-row sm:items-center gap-2">
                        <input ref="fileInput" type="file" :accept="acceptAttr" class="block w-full sm:w-64 text-sm" aria-label="Choose an attachment file" @change="onFileSelected" />
                        <Button label="Upload" icon="pi pi-upload" size="small" :loading="uploading" :disabled="!selectedFile" @click="uploadAttachment" />
                    </div>
                </div>
                <small class="text-surface-500"> PDF, JPG, JPEG, or PNG. Maximum {{ maxFileLabel }} per file and {{ maxFiles }} files per letter. </small>
                <small v-if="selectedFile" class="text-surface-500">Selected: {{ selectedFile.name }} ({{ formatSize(selectedFile.size) }})</small>
                <Message v-if="uploadError" severity="error" :closable="false">{{ uploadError }}</Message>

                <div v-if="detail.attachments.length === 0" class="text-sm text-surface-500">No attachments yet.</div>
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
                            <Button
                                v-if="canManage && detail.status === 'active'"
                                icon="pi pi-trash"
                                aria-label="Remove attachment"
                                size="small"
                                severity="danger"
                                variant="outlined"
                                rounded
                                v-tooltip.top="'Remove'"
                                @click="openRemoveAttachment(attachment)"
                            />
                        </div>
                    </li>
                </ul>
            </div>

            <div class="border border-surface-200 dark:border-surface-700 rounded p-4">
                <div class="font-semibold mb-3">History</div>
                <div v-if="detail.events.length === 0" class="text-sm text-surface-500">No recorded events.</div>
                <ol v-else class="flex flex-col gap-3">
                    <li v-for="event in detail.events" :key="event.id" class="flex gap-3 text-sm">
                        <i :class="eventIcon(event.event)" class="mt-1" aria-hidden="true"></i>
                        <div class="min-w-0">
                            <div class="font-medium">{{ eventLabel(event.event) }}</div>
                            <div class="text-xs text-surface-500">{{ event.actor?.name ?? 'Unknown user' }} · {{ formatDateTime(event.created_at) }}</div>
                            <div v-if="event.event === 'updated' && event.metadata?.changed_fields" class="text-xs text-surface-500">Fields: {{ changedFieldLabels(event.metadata.changed_fields) }}</div>
                        </div>
                    </li>
                </ol>
            </div>
        </div>
    </Dialog>

    <Dialog v-model:visible="removeVisible" modal :draggable="false" :style="{ width: 'min(94vw, 34rem)' }" header="Remove Attachment">
        <div class="flex flex-col gap-3">
            <p class="text-sm">
                Remove
                <span class="font-medium">{{ removeTarget?.attachment?.original_name }}</span>
                from this letter? The file is retained in private storage for audit and will no longer be listed.
            </p>
            <Message v-if="removeError" severity="error" :closable="false">{{ removeError }}</Message>
        </div>
        <template #footer>
            <Button label="Cancel" severity="secondary" variant="text" :disabled="removeSaving" @click="removeVisible = false" />
            <Button label="Remove" icon="pi pi-trash" severity="danger" :loading="removeSaving" @click="confirmRemoveAttachment" />
        </template>
    </Dialog>

    <Dialog v-model:visible="assignVisible" modal :draggable="false" :style="{ width: 'min(94vw, 40rem)' }" :header="assignTarget ? `Assign Disposition ${assignTarget.reference}` : 'Assign Disposition'">
        <div class="flex flex-col gap-4">
            <div v-if="assignTarget" class="text-sm text-surface-500">
                <span class="font-medium text-surface-900 dark:text-surface-0">{{ assignTarget.reference }}</span>
                — {{ assignTarget.subject }}
            </div>

            <div class="flex flex-col gap-2">
                <label for="dispositionRecipients" class="font-medium">Recipients <span class="text-red-500">*</span></label>
                <MultiSelect
                    inputId="dispositionRecipients"
                    v-model="assignForm.recipients"
                    :options="recipientOptions"
                    optionLabel="name"
                    optionValue="id"
                    :loading="recipientLoading"
                    filter
                    display="chip"
                    placeholder="Select active employees"
                    emptyMessage="No active employees found."
                    aria-label="Disposition recipients"
                    @filter="onRecipientFilter"
                />
                <small class="text-surface-500">Only active employees are available. Each recipient receives one disposition row.</small>
                <small v-if="fieldError(assignErrors, 'recipients')" class="text-red-500">{{ fieldError(assignErrors, 'recipients') }}</small>
                <small v-for="error in recipientFieldErrors" :key="error.key" class="text-red-500">{{ error.message }}</small>
            </div>

            <div class="flex flex-col gap-2">
                <label for="dispositionDueDate" class="font-medium">Due Date <span class="text-red-500">*</span></label>
                <DatePicker inputId="dispositionDueDate" v-model="assignForm.dueDate" dateFormat="yy-mm-dd" showIcon manualInput fluid />
                <small v-if="fieldError(assignErrors, 'due_date')" class="text-red-500">{{ fieldError(assignErrors, 'due_date') }}</small>
            </div>

            <div class="flex flex-col gap-2">
                <label for="dispositionNote" class="font-medium">Note</label>
                <Textarea id="dispositionNote" v-model="assignForm.note" rows="3" maxlength="2000" autoResize placeholder="Optional instruction for the recipients" />
                <small class="text-surface-500">{{ assignForm.note.length }}/2000</small>
                <small v-if="fieldError(assignErrors, 'note')" class="text-red-500">{{ fieldError(assignErrors, 'note') }}</small>
                <small v-if="assignError" class="text-red-500">{{ assignError }}</small>
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" severity="secondary" variant="text" :disabled="assignSaving" @click="assignVisible = false" />
            <Button label="Assign" icon="pi pi-user-plus" :loading="assignSaving" @click="submitAssign" />
        </template>
    </Dialog>

    <Dialog v-model:visible="conflictVisible" modal :draggable="false" :style="{ width: 'min(94vw, 34rem)' }" header="Conflict">
        <div class="flex flex-col gap-3">
            <Message severity="warn" :closable="false">{{ conflictMessage }}</Message>
            <p class="text-sm text-surface-500">Reload the latest letter to see the current version before trying again.</p>
        </div>
        <template #footer>
            <Button label="Close" severity="secondary" variant="text" @click="conflictVisible = false" />
            <Button label="Reload Latest" icon="pi pi-refresh" :loading="reloadingConflict" @click="reloadLatest" />
        </template>
    </Dialog>
</template>
