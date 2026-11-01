<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import apiLogin from '@/apiLogin';
import { authStore } from '@/stores/authStore';

const store = authStore();
const toast = useToast();

const canManage = computed(() => store.can('letters.manage'));
const canApprove = computed(() => store.can('letters.approve'));
const canSign = computed(() => store.can('letters.sign'));
const currentUserId = computed(() => store.identity?.id ?? null);

const workflowLabels = {
    draft: 'Draft',
    review: 'In Review',
    approved: 'Approved',
    issued: 'Issued',
    cancelled: 'Cancelled'
};

const workflowSeverity = {
    draft: 'secondary',
    review: 'warn',
    approved: 'info',
    issued: 'success',
    cancelled: 'danger'
};

const statusFilterOptions = [
    { label: 'All workflow statuses', value: 'all' },
    { label: 'Draft', value: 'draft' },
    { label: 'In Review', value: 'review' },
    { label: 'Approved', value: 'approved' },
    { label: 'Issued', value: 'issued' },
    { label: 'Cancelled', value: 'cancelled' }
];

const fieldLabels = {
    received_date: 'Recorded date',
    letter_date: 'Letter date',
    sender: 'Sender',
    recipient: 'Recipient',
    subject: 'Subject',
    category: 'Category',
    notes: 'Notes'
};

const eventLabels = {
    created: 'Created',
    updated: 'Updated',
    submitted: 'Submitted for approval',
    approved: 'Approved',
    rejected: 'Rejected',
    issued: 'Issued and signed',
    cancelled: 'Cancelled',
    attachment_added: 'Attachment added',
    attachment_removed: 'Attachment removed'
};

const eventIcons = {
    created: 'pi pi-plus-circle',
    updated: 'pi pi-pencil',
    submitted: 'pi pi-send',
    approved: 'pi pi-check-circle',
    rejected: 'pi pi-times-circle',
    issued: 'pi pi-verified',
    cancelled: 'pi pi-ban',
    attachment_added: 'pi pi-paperclip',
    attachment_removed: 'pi pi-trash'
};

const options = ref(null);
const optionsError = ref('');
const items = ref([]);
const meta = ref({ current_page: 1, per_page: 20, total: 0, last_page: 1 });
const listLoading = ref(false);
const listError = ref('');
const page = ref(1);
const filters = reactive({ search: '', workflowStatus: 'all', letterFrom: null, letterTo: null, perPage: 20 });
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
const perPageLabel = computed(() => `Rows: ${filters.perPage}`);
const approvalNoteMax = computed(() => options.value?.approval_notes_max_length ?? 500);
const cancelMin = computed(() => options.value?.cancel_reason_min_length ?? 10);
const cancelMax = computed(() => options.value?.cancel_reason_max_length ?? 255);
const numberingExample = computed(() => options.value?.numbering?.example ?? '');

const notify = (severity, detail) => {
    toast.add({ severity, summary: 'Outgoing Letters', detail, life: 5000 });
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

const loadOptions = async () => {
    optionsError.value = '';
    try {
        const response = await apiLogin.get('/api/letters/outgoing/options');
        options.value = response.data?.data ?? null;
        filters.perPage = options.value?.default_per_page ?? filters.perPage;
    } catch (error) {
        options.value = null;
        optionsError.value = errorMessage(error, 'Failed to load outgoing letter options. Default values are used.');
    }
};

const loadLetters = async () => {
    const requestId = ++listRequestId;
    listLoading.value = true;
    listError.value = '';

    try {
        const params = { page: page.value, per_page: filters.perPage, workflow_status: filters.workflowStatus };
        if (filters.search.trim()) params.search = filters.search.trim();

        const from = dateToIso(filters.letterFrom);
        const to = dateToIso(filters.letterTo);
        if (from) params.letter_from = from;
        if (to) params.letter_to = to;

        const response = await apiLogin.get('/api/letters/outgoing', { params });
        if (requestId !== listRequestId) return;

        const data = response.data?.data ?? {};
        items.value = data.items ?? [];
        meta.value = data.meta ?? { current_page: 1, per_page: filters.perPage, total: 0, last_page: 1 };
        if (meta.value.per_page) filters.perPage = meta.value.per_page;

        if (items.value.length === 0 && page.value > 1 && page.value > meta.value.last_page) {
            page.value = Math.max(1, meta.value.last_page);
            return loadLetters();
        }
    } catch (error) {
        if (requestId !== listRequestId) return;
        items.value = [];
        listError.value = errorMessage(error, 'Failed to load outgoing letters. Please try again.');
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
    const key = `${dateToIso(filters.letterFrom) ?? ''}|${dateToIso(filters.letterTo) ?? ''}`;
    if (key === lastDateFilterKey) return;
    lastDateFilterKey = key;
    onFilterChange();
};

const resetFilters = () => {
    filters.search = '';
    filters.workflowStatus = 'all';
    filters.letterFrom = null;
    filters.letterTo = null;
    lastDateFilterKey = '|';
    page.value = 1;
    loadLetters();
};

const onPage = (event) => {
    const nextPage = event.page + 1;
    const rowsChanged = event.rows !== filters.perPage;
    page.value = rowsChanged ? 1 : nextPage;
    if (rowsChanged) filters.perPage = event.rows;
    loadLetters();
};

const formVisible = ref(false);
const formMode = ref('create');
const formSaving = ref(false);
const formErrors = ref({});
const formError = ref('');
const form = reactive({
    id: null,
    reference: null,
    recordedDate: null,
    letterDate: null,
    sender: '',
    recipient: '',
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
    form.recordedDate = today;
    form.letterDate = today;
    form.sender = options.value?.default_sender ?? '';
    form.recipient = '';
    form.subject = '';
    form.category = null;
    form.notes = '';
    form.version = null;
    formErrors.value = {};
    formError.value = '';
    formSaving.value = false;
    detailVisible.value = false;
    formVisible.value = true;
};

const startEdit = (source) => {
    if (!canManage.value || source.workflow_status !== 'draft') return;

    formMode.value = 'edit';
    form.id = source.id;
    form.reference = source.reference;
    form.recordedDate = isoToDate(source.received_date);
    form.letterDate = isoToDate(source.letter_date);
    form.sender = source.sender ?? '';
    form.recipient = source.recipient ?? '';
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
    const recorded = dateToIso(form.recordedDate);
    const letter = dateToIso(form.letterDate);
    const sender = form.sender.trim();
    const recipient = form.recipient.trim();
    const subject = form.subject.trim();

    if (!recorded) errors.received_date = ['The recorded date is required.'];
    else if (recorded > todayIso()) errors.received_date = ['The recorded date may not be in the future.'];

    if (!letter) errors.letter_date = ['The letter date is required.'];
    else if (recorded && letter > recorded) errors.letter_date = ['The letter date may not be after the recorded date.'];

    if (!sender) errors.sender = ['The sender is required.'];
    else if (sender.length > 150) errors.sender = ['The sender may not exceed 150 characters.'];

    if (!recipient) errors.recipient = ['The recipient is required.'];
    else if (recipient.length > 150) errors.recipient = ['The recipient may not exceed 150 characters.'];

    if (!subject) errors.subject = ['The subject is required.'];
    else if (subject.length > 200) errors.subject = ['The subject may not exceed 200 characters.'];

    if (!form.category) errors.category = ['The category is required.'];
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
        received_date: dateToIso(form.recordedDate),
        letter_date: dateToIso(form.letterDate),
        sender: form.sender.trim(),
        recipient: form.recipient.trim(),
        subject: form.subject.trim(),
        category: form.category,
        notes: form.notes.trim() || null
    };

    const editing = formMode.value === 'edit';
    if (editing) payload.expected_version = form.version;

    formSaving.value = true;
    try {
        const response = editing ? await apiLogin.put(`/api/letters/outgoing/${form.id}`, payload) : await apiLogin.post('/api/letters/outgoing', payload);

        formVisible.value = false;
        notify('success', response.data?.message ?? 'Letter saved.');
        await loadLetters();
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            formErrors.value = validationErrors(error);
            if (Object.keys(formErrors.value).length === 0) formError.value = errorMessage(error, 'The letter could not be saved.');
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

const detailVisible = ref(false);
const detailLoading = ref(false);
const detailError = ref('');
const detail = ref(null);
let detailRequestId = 0;

const fileInput = ref(null);
const selectedFile = ref(null);
const uploading = ref(false);
const uploadError = ref('');

const openDetail = async (row) => {
    detailVisible.value = true;
    detailLoading.value = true;
    detailError.value = '';
    uploadError.value = '';
    selectedFile.value = null;
    if (fileInput.value) fileInput.value.value = '';

    const requestId = ++detailRequestId;
    try {
        const response = await apiLogin.get(`/api/letters/outgoing/${row.id}`);
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

const isCreator = (letter) => Number(letter?.created_by?.id) === Number(currentUserId.value);

const canSubmit = (letter) => canManage.value && letter?.workflow_status === 'draft';
const canEdit = (letter) => canManage.value && letter?.workflow_status === 'draft';
const canApproveLetter = (letter) => canApprove.value && letter?.workflow_status === 'review' && !isCreator(letter);
const canIssueLetter = (letter) => canSign.value && letter?.workflow_status === 'approved' && !isCreator(letter);
const canCancelLetter = (letter) => {
    if (!letter) return false;
    if (letter.workflow_status === 'draft' || letter.workflow_status === 'review') return canManage.value;
    if (letter.workflow_status === 'approved' || letter.workflow_status === 'issued') return canApprove.value;
    return false;
};

const actionVisible = ref(false);
const actionSaving = ref(false);
const actionKind = ref('submit');
const actionTarget = ref(null);
const actionText = ref('');
const actionErrors = ref({});
const actionError = ref('');

const actionConfig = computed(() => {
    const map = {
        submit: { title: 'Submit for Approval', button: 'Submit', icon: 'pi pi-send', severity: 'primary', requiresText: false },
        approve: { title: 'Approve Letter', button: 'Approve', icon: 'pi pi-check', severity: 'success', requiresText: false },
        reject: { title: 'Reject Letter', button: 'Reject', icon: 'pi pi-times', severity: 'warn', requiresText: true, field: 'reason', label: 'Rejection Reason' },
        issue: { title: 'Issue and Sign Letter', button: 'Issue', icon: 'pi pi-verified', severity: 'success', requiresText: false },
        cancel: { title: 'Cancel Letter', button: 'Cancel Letter', icon: 'pi pi-ban', severity: 'danger', requiresText: true, field: 'reason', label: 'Cancellation Reason' }
    };
    return map[actionKind.value] ?? map.submit;
});

const actionMessage = computed(() => {
    if (actionKind.value === 'submit') return 'Submitting locks edits and attachments until an approver rejects the letter.';
    if (actionKind.value === 'approve') return 'Approval records your name and time. The letter can then be issued by an authorized signer.';
    if (actionKind.value === 'reject') return 'Rejection returns the letter to draft so the creator can revise it.';
    if (actionKind.value === 'issue') {
        return numberingExample.value ? `Issuing assigns an official number using the configured format, for example ${numberingExample.value}. The number cannot be reused.` : 'Issuing assigns an official number. The number cannot be reused.';
    }
    return 'Cancelling keeps the letter, attachments, approval ledger, and history. The letter becomes read-only.';
});

const openAction = (kind, source) => {
    if (!source) return;
    actionKind.value = kind;
    actionTarget.value = { id: source.id, reference: source.reference, subject: source.subject, version: source.version, official_number: source.official_number };
    actionText.value = '';
    actionErrors.value = {};
    actionError.value = '';
    actionSaving.value = false;
    detailVisible.value = false;
    actionVisible.value = true;
};

const submitAction = async () => {
    if (actionSaving.value || !actionTarget.value) return;

    actionErrors.value = {};
    actionError.value = '';

    const config = actionConfig.value;
    const text = actionText.value.trim();

    if (config.requiresText) {
        const min = config.field === 'reason' ? cancelMin.value : 10;
        const max = config.field === 'reason' ? cancelMax.value : 255;
        if (text.length < min || text.length > max) {
            actionErrors.value = {
                [config.field]: [text.length < min ? `The ${config.field === 'reason' ? 'reason' : 'note'} must be at least ${min} characters.` : `The ${config.field} may not exceed ${max} characters.`]
            };
            return;
        }
    }

    if (actionKind.value === 'approve' && text.length > approvalNoteMax.value) {
        actionErrors.value = { notes: [`The approval note may not exceed ${approvalNoteMax.value} characters.`] };
        return;
    }

    const url = `/api/letters/outgoing/${actionTarget.value.id}/${actionKind.value}`;
    const payload = { expected_version: actionTarget.value.version };

    if (actionKind.value === 'approve') payload.notes = text || null;
    if (actionKind.value === 'reject' || actionKind.value === 'cancel') payload.reason = text;

    actionSaving.value = true;
    try {
        const response = await apiLogin.post(url, payload);
        actionVisible.value = false;
        detail.value = response.data?.data ?? null;
        detailVisible.value = true;
        notify('success', response.data?.message ?? 'Letter updated.');
        await loadLetters();
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            actionErrors.value = validationErrors(error);
            if (Object.keys(actionErrors.value).length === 0) actionError.value = errorMessage(error, 'The action could not be completed.');
        } else if (status === 409) {
            actionVisible.value = false;
            openConflict(error, actionTarget.value.id);
        } else {
            actionError.value = errorMessage(error, 'The action could not be completed.');
        }
    } finally {
        actionSaving.value = false;
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
            const response = await apiLogin.get(`/api/letters/outgoing/${targetId}`);
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
            actionVisible.value = false;
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
        const response = await apiLogin.post(`/api/letters/outgoing/${detail.value.id}/attachments`, body);
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
        const response = await apiLogin.get(`/api/letters/outgoing/${detail.value.id}/attachments/${attachment.id}/download`, {
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
    if (!detail.value || !canManage.value || detail.value.workflow_status !== 'draft') return;

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
        const response = await apiLogin.delete(`/api/letters/outgoing/${letterId}/attachments/${attachment.id}`);
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
                    <div class="font-bold text-2xl sm:text-3xl text-surface-900 dark:text-surface-0 mb-2">Outgoing Letters</div>
                    <p class="text-surface-500 dark:text-surface-400">Drafts, approval and signing workflow, atomic official numbering, and private attachments.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Tag :value="`${meta.total} letters`" severity="secondary" />
                    <Button v-if="canManage" label="New Letter" icon="pi pi-plus" @click="openCreate" />
                </div>
            </div>

            <div v-if="optionsError" class="mt-4 flex flex-col sm:flex-row sm:items-center gap-3 rounded border border-orange-300 dark:border-orange-700 bg-orange-50 dark:bg-orange-950 px-4 py-3 text-sm text-orange-700 dark:text-orange-300">
                <span class="flex-1">{{ optionsError }}</span>
                <Button label="Retry" icon="pi pi-refresh" size="small" severity="secondary" variant="outlined" @click="loadOptions" />
            </div>

            <div class="card mt-6 flex flex-col gap-4">
                <div class="flex flex-col xl:flex-row xl:items-center gap-3">
                    <IconField class="w-full xl:max-w-sm">
                        <InputIcon><i class="pi pi-search" /></InputIcon>
                        <InputText v-model="filters.search" placeholder="Search number, recipient, or subject" class="w-full" maxlength="100" aria-label="Search outgoing letters" @input="onSearchInput" />
                    </IconField>
                    <Select v-model="filters.workflowStatus" :options="statusFilterOptions" optionLabel="label" optionValue="value" class="w-full xl:w-48" aria-label="Workflow status" @change="onFilterChange" />
                    <DatePicker
                        v-model="filters.letterFrom"
                        placeholder="Letter date from"
                        dateFormat="yy-mm-dd"
                        showIcon
                        manualInput
                        fluid
                        class="w-full xl:w-44"
                        aria-label="Letter date from"
                        @date-select="onDateFilterChange"
                        @blur="onDateFilterChange"
                    />
                    <DatePicker
                        v-model="filters.letterTo"
                        placeholder="Letter date to"
                        dateFormat="yy-mm-dd"
                        showIcon
                        manualInput
                        fluid
                        class="w-full xl:w-44"
                        aria-label="Letter date to"
                        @date-select="onDateFilterChange"
                        @blur="onDateFilterChange"
                    />
                    <div class="flex items-center gap-2 xl:ml-auto">
                        <Select v-model="filters.perPage" :options="perPageOptions" class="w-28" :aria-label="perPageLabel" @change="onFilterChange" />
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
                    tableStyle="min-width: 74rem"
                    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                    currentPageReportTemplate="Showing {first} to {last} of {totalRecords} letters"
                    @page="onPage"
                >
                    <template #loading>Loading outgoing letters. Please wait.</template>
                    <template #empty>
                        <div class="flex flex-col items-center gap-3 py-6">
                            <span v-if="listError" class="text-red-500">{{ listError }}</span>
                            <span v-else>No outgoing letters match the current filters.</span>
                            <Button v-if="listError" icon="pi pi-refresh" label="Retry" @click="loadLetters" />
                        </div>
                    </template>
                    <Column header="Reference" style="min-width: 7rem">
                        <template #body="{ data }">
                            <span class="font-medium">{{ data.reference }}</span>
                        </template>
                    </Column>
                    <Column header="Recorded" style="min-width: 8rem">
                        <template #body="{ data }">{{ formatDate(data.received_date) }}</template>
                    </Column>
                    <Column header="Letter Date" style="min-width: 8rem">
                        <template #body="{ data }">{{ formatDate(data.letter_date) }}</template>
                    </Column>
                    <Column header="Recipient" style="min-width: 12rem">
                        <template #body="{ data }">
                            <span class="block truncate" :title="data.recipient ?? ''">{{ data.recipient || '-' }}</span>
                        </template>
                    </Column>
                    <Column header="Subject" style="min-width: 14rem">
                        <template #body="{ data }">
                            <span class="block truncate" :title="data.subject">{{ data.subject }}</span>
                        </template>
                    </Column>
                    <Column header="Official No." style="min-width: 10rem">
                        <template #body="{ data }">
                            <span class="block truncate" :title="data.official_number ?? ''">{{ data.official_number || '-' }}</span>
                        </template>
                    </Column>
                    <Column header="Workflow" style="min-width: 8rem">
                        <template #body="{ data }">
                            <Tag :value="workflowLabels[data.workflow_status] ?? (data.workflow_status || '-')" :severity="workflowSeverity[data.workflow_status] ?? 'secondary'" />
                            <div class="text-xs text-surface-500 mt-1">Version {{ data.version }}</div>
                        </template>
                    </Column>
                    <Column header="Files" style="min-width: 5rem">
                        <template #body="{ data }">{{ data.attachments_count ?? 0 }}</template>
                    </Column>
                    <Column header="Actions" style="min-width: 9rem">
                        <template #body="{ data }">
                            <div class="flex flex-wrap items-center gap-2">
                                <Button icon="pi pi-eye" aria-label="View letter" variant="outlined" rounded v-tooltip.top="'View'" @click="openDetail(data)" />
                                <Button v-if="canEdit(data)" icon="pi pi-pencil" aria-label="Edit draft" variant="outlined" rounded v-tooltip.top="'Edit'" @click="startEdit(data)" />
                                <Button v-if="canSubmit(data)" icon="pi pi-send" aria-label="Submit draft" severity="info" variant="outlined" rounded v-tooltip.top="'Submit'" @click="openAction('submit', data)" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </Fluid>

    <Dialog v-model:visible="formVisible" modal :draggable="false" :style="{ width: 'min(94vw, 46rem)' }" :header="formMode === 'create' ? 'New Outgoing Letter' : `Edit Draft ${form.reference ?? ''}`">
        <div class="flex flex-col gap-4">
            <Message v-if="formError" severity="error" :closable="false">{{ formError }}</Message>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label for="outgoingRecordedDate" class="font-medium">Recorded Date <span class="text-red-500">*</span></label>
                    <DatePicker inputId="outgoingRecordedDate" v-model="form.recordedDate" dateFormat="yy-mm-dd" showIcon manualInput fluid />
                    <small v-if="fieldError(formErrors, 'received_date')" class="text-red-500">{{ fieldError(formErrors, 'received_date') }}</small>
                </div>
                <div class="flex flex-col gap-2">
                    <label for="outgoingLetterDate" class="font-medium">Letter Date <span class="text-red-500">*</span></label>
                    <DatePicker inputId="outgoingLetterDate" v-model="form.letterDate" dateFormat="yy-mm-dd" showIcon manualInput fluid />
                    <small v-if="fieldError(formErrors, 'letter_date')" class="text-red-500">{{ fieldError(formErrors, 'letter_date') }}</small>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label for="outgoingSender" class="font-medium">Sender <span class="text-red-500">*</span></label>
                <InputText id="outgoingSender" v-model="form.sender" maxlength="150" placeholder="School or unit name" />
                <small v-if="fieldError(formErrors, 'sender')" class="text-red-500">{{ fieldError(formErrors, 'sender') }}</small>
            </div>

            <div class="flex flex-col gap-2">
                <label for="outgoingRecipient" class="font-medium">Recipient <span class="text-red-500">*</span></label>
                <InputText id="outgoingRecipient" v-model="form.recipient" maxlength="150" placeholder="Destination institution or person" />
                <small v-if="fieldError(formErrors, 'recipient')" class="text-red-500">{{ fieldError(formErrors, 'recipient') }}</small>
            </div>

            <div class="flex flex-col gap-2">
                <label for="outgoingSubject" class="font-medium">Subject <span class="text-red-500">*</span></label>
                <InputText id="outgoingSubject" v-model="form.subject" maxlength="200" placeholder="Letter subject" />
                <small v-if="fieldError(formErrors, 'subject')" class="text-red-500">{{ fieldError(formErrors, 'subject') }}</small>
            </div>

            <div class="flex flex-col gap-2">
                <label for="outgoingCategory" class="font-medium">Category <span class="text-red-500">*</span></label>
                <Select inputId="outgoingCategory" v-model="form.category" :options="categoryOptions" optionLabel="label" optionValue="value" placeholder="Select category" filter fluid />
                <small v-if="fieldError(formErrors, 'category')" class="text-red-500">{{ fieldError(formErrors, 'category') }}</small>
            </div>

            <div class="flex flex-col gap-2">
                <label for="outgoingNotes" class="font-medium">Notes</label>
                <Textarea id="outgoingNotes" v-model="form.notes" rows="3" maxlength="2000" autoResize placeholder="Optional internal notes" />
                <small class="text-surface-500">{{ form.notes.length }}/2000</small>
                <small v-if="fieldError(formErrors, 'notes')" class="text-red-500">{{ fieldError(formErrors, 'notes') }}</small>
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" severity="secondary" variant="text" :disabled="formSaving" @click="formVisible = false" />
            <Button :label="formMode === 'create' ? 'Create Draft' : 'Save Changes'" icon="pi pi-check" :loading="formSaving" @click="submitForm" />
        </template>
    </Dialog>

    <Dialog v-model:visible="detailVisible" modal :draggable="false" :style="{ width: 'min(94vw, 62rem)' }" :header="detail ? `Outgoing Letter ${detail.reference}` : 'Outgoing Letter'">
        <div v-if="detailLoading" class="flex justify-center py-10">
            <i class="pi pi-spin pi-spinner text-2xl" aria-hidden="true"></i>
        </div>
        <Message v-else-if="detailError" severity="error" :closable="false">{{ detailError }}</Message>
        <div v-else-if="detail" class="flex flex-col gap-5">
            <div class="flex flex-wrap items-center gap-2">
                <Tag :value="workflowLabels[detail.workflow_status] ?? detail.workflow_status" :severity="workflowSeverity[detail.workflow_status] ?? 'secondary'" />
                <Tag :value="detail.status === 'void' ? 'Void' : 'Active'" :severity="detail.status === 'void' ? 'danger' : 'success'" />
                <Tag :value="detail.category" severity="secondary" />
                <Tag :value="`Version ${detail.version}`" severity="contrast" />
                <Tag v-if="detail.official_number" :value="detail.official_number" severity="success" />
                <div class="flex flex-wrap gap-2 sm:ml-auto">
                    <Button v-if="canEdit(detail)" label="Edit" icon="pi pi-pencil" size="small" variant="outlined" @click="startEdit(detail)" />
                    <Button v-if="canSubmit(detail)" label="Submit" icon="pi pi-send" size="small" severity="info" variant="outlined" @click="openAction('submit', detail)" />
                    <Button v-if="canApproveLetter(detail)" label="Approve" icon="pi pi-check" size="small" severity="success" variant="outlined" @click="openAction('approve', detail)" />
                    <Button v-if="canApproveLetter(detail)" label="Reject" icon="pi pi-times" size="small" severity="warn" variant="outlined" @click="openAction('reject', detail)" />
                    <Button v-if="canIssueLetter(detail)" label="Issue" icon="pi pi-verified" size="small" severity="success" @click="openAction('issue', detail)" />
                    <Button v-if="canCancelLetter(detail)" label="Cancel" icon="pi pi-ban" size="small" severity="danger" variant="outlined" @click="openAction('cancel', detail)" />
                </div>
            </div>

            <Message v-if="detail.workflow_status === 'review' && isCreator(detail)" severity="info" :closable="false">You created this letter. A different user with approval ability must approve or reject it.</Message>
            <Message v-if="detail.workflow_status === 'approved' && isCreator(detail)" severity="info" :closable="false">You created this letter. A different user with signing ability must issue it.</Message>
            <Message v-if="detail.workflow_status === 'cancelled'" severity="error" :closable="false"> Cancelled by {{ detail.voided_by?.name ?? 'a user' }} on {{ formatDateTime(detail.voided_at) }}. Reason: {{ detail.void_reason }} </Message>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div>
                    <div class="text-surface-500">Recorded date</div>
                    <div class="font-medium">{{ formatDate(detail.received_date) }}</div>
                </div>
                <div>
                    <div class="text-surface-500">Letter date</div>
                    <div class="font-medium">{{ formatDate(detail.letter_date) }}</div>
                </div>
                <div>
                    <div class="text-surface-500">Sender</div>
                    <div class="font-medium break-words">{{ detail.sender }}</div>
                </div>
                <div>
                    <div class="text-surface-500">Recipient</div>
                    <div class="font-medium break-words">{{ detail.recipient || '-' }}</div>
                </div>
                <div>
                    <div class="text-surface-500">Official number</div>
                    <div class="font-medium break-words">{{ detail.official_number || 'Not issued yet' }}</div>
                </div>
                <div>
                    <div class="text-surface-500">Category</div>
                    <div class="font-medium">{{ detail.category }}</div>
                </div>
                <div>
                    <div class="text-surface-500">Subject</div>
                    <div class="font-medium break-words">{{ detail.subject }}</div>
                </div>
                <div>
                    <div class="text-surface-500">Recorded by</div>
                    <div class="font-medium">{{ detail.created_by?.name ?? '-' }} · {{ formatDateTime(detail.created_at) }}</div>
                </div>
                <div v-if="detail.approved_by">
                    <div class="text-surface-500">Approved by</div>
                    <div class="font-medium">{{ detail.approved_by.name ?? '-' }} · {{ formatDateTime(detail.approved_at) }}</div>
                </div>
                <div v-if="detail.issued_by">
                    <div class="text-surface-500">Issued by</div>
                    <div class="font-medium">{{ detail.issued_by.name ?? '-' }} · {{ formatDateTime(detail.issued_at) }}</div>
                </div>
            </div>

            <div v-if="detail.notes">
                <div class="text-sm text-surface-500 mb-1">Notes</div>
                <div class="text-sm whitespace-pre-line break-words">{{ detail.notes }}</div>
            </div>

            <div class="border border-surface-200 dark:border-surface-700 rounded p-4">
                <div class="font-semibold mb-3">Approval Ledger</div>
                <div v-if="!detail.approvals || detail.approvals.length === 0" class="text-sm text-surface-500">No approval actions recorded.</div>
                <ul v-else class="flex flex-col gap-3">
                    <li v-for="approval in detail.approvals" :key="approval.id" class="text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <Tag :value="approval.action === 'approved' ? 'Approved' : 'Rejected'" :severity="approval.action === 'approved' ? 'success' : 'warn'" />
                            <span class="font-medium">{{ approval.actor?.name ?? 'Unknown user' }}</span>
                            <span class="text-xs text-surface-500">{{ formatDateTime(approval.created_at) }}</span>
                        </div>
                        <div v-if="approval.notes" class="mt-1 whitespace-pre-line break-words">{{ approval.notes }}</div>
                    </li>
                </ul>
            </div>

            <div class="border border-surface-200 dark:border-surface-700 rounded p-4 flex flex-col gap-3">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="font-semibold">Attachments ({{ detail.attachments.length }}/{{ maxFiles }})</div>
                    <div v-if="canManage && detail.workflow_status === 'draft' && detail.attachments.length < maxFiles" class="flex flex-col sm:flex-row sm:items-center gap-2">
                        <input ref="fileInput" type="file" :accept="acceptAttr" class="block w-full sm:w-64 text-sm" aria-label="Choose an attachment file" @change="onFileSelected" />
                        <Button label="Upload" icon="pi pi-upload" size="small" :loading="uploading" :disabled="!selectedFile" @click="uploadAttachment" />
                    </div>
                </div>
                <small class="text-surface-500"> PDF, JPG, JPEG, or PNG. Maximum {{ maxFileLabel }} per file and {{ maxFiles }} files per letter. Attachments are editable only while the letter is a draft. </small>
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
                                v-if="canManage && detail.workflow_status === 'draft'"
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
                            <div v-if="event.event === 'issued' && event.metadata?.official_number" class="text-xs text-surface-500">Number: {{ event.metadata.official_number }}</div>
                        </div>
                    </li>
                </ol>
            </div>
        </div>
    </Dialog>

    <Dialog v-model:visible="actionVisible" modal :draggable="false" :style="{ width: 'min(94vw, 38rem)' }" :header="actionConfig.title">
        <div class="flex flex-col gap-4">
            <div v-if="actionTarget" class="text-sm text-surface-500">
                <span class="font-medium text-surface-900 dark:text-surface-0">{{ actionTarget.reference }}</span>
                — {{ actionTarget.subject }}
            </div>
            <Message severity="info" :closable="false">{{ actionMessage }}</Message>

            <div v-if="actionKind === 'approve'" class="flex flex-col gap-2">
                <label for="approvalNotes" class="font-medium">Approval Note</label>
                <Textarea id="approvalNotes" v-model="actionText" rows="3" :maxlength="approvalNoteMax" autoResize placeholder="Optional note for the approval ledger" />
                <small class="text-surface-500">Optional. {{ actionText.trim().length }}/{{ approvalNoteMax }}</small>
                <small v-if="fieldError(actionErrors, 'notes')" class="text-red-500">{{ fieldError(actionErrors, 'notes') }}</small>
            </div>

            <div v-if="actionConfig.requiresText" class="flex flex-col gap-2">
                <label for="actionReason" class="font-medium">{{ actionConfig.label }} <span class="text-red-500">*</span></label>
                <Textarea id="actionReason" v-model="actionText" rows="3" :maxlength="cancelMax" autoResize :placeholder="actionKind === 'reject' ? 'Explain why the letter is rejected' : 'Explain why the letter is cancelled'" />
                <small class="text-surface-500">At least {{ cancelMin }} characters. {{ actionText.trim().length }}/{{ cancelMax }}</small>
                <small v-if="fieldError(actionErrors, 'reason')" class="text-red-500">{{ fieldError(actionErrors, 'reason') }}</small>
            </div>

            <small v-if="actionError" class="text-red-500">{{ actionError }}</small>
        </div>
        <template #footer>
            <Button label="Close" severity="secondary" variant="text" :disabled="actionSaving" @click="actionVisible = false" />
            <Button :label="actionConfig.button" :icon="actionConfig.icon" :severity="actionConfig.severity" :loading="actionSaving" @click="submitAction" />
        </template>
    </Dialog>

    <Dialog v-model:visible="removeVisible" modal :draggable="false" :style="{ width: 'min(94vw, 34rem)' }" header="Remove Attachment">
        <div class="flex flex-col gap-3">
            <p class="text-sm">
                Remove
                <span class="font-medium">{{ removeTarget?.attachment?.original_name }}</span>
                from this draft? The file is retained in private storage for audit and will no longer be listed.
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
            <p class="text-sm text-surface-500">Reload the latest letter to see the current version before trying again.</p>
        </div>
        <template #footer>
            <Button label="Close" severity="secondary" variant="text" @click="conflictVisible = false" />
            <Button label="Reload Latest" icon="pi pi-refresh" :loading="reloadingConflict" @click="reloadLatest" />
        </template>
    </Dialog>
</template>
