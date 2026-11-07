<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import apiLogin from '@/apiLogin';
import { authStore } from '@/stores/authStore';

const store = authStore();
const toast = useToast();
const { t } = useI18n();

const canManage = computed(() => store.can('letters.manage'));
const canFilterRecipients = computed(() => store.can('letters.manage') && store.can('employees.view'));

const dispositionLabels = computed(() => ({
    pending: t('letters.dispositions.statuses.pending'),
    in_progress: t('letters.dispositions.statuses.in_progress'),
    completed: t('letters.dispositions.statuses.completed'),
    cancelled: t('letters.dispositions.statuses.cancelled')
}));

const dispositionSeverity = {
    pending: 'warn',
    in_progress: 'info',
    completed: 'success',
    cancelled: 'danger'
};

const statusOptions = computed(() => [
    { label: t('letters.dispositions.statuses.open'), value: 'open' },
    { label: t('letters.dispositions.statuses.pending'), value: 'pending' },
    { label: t('letters.dispositions.statuses.in_progress'), value: 'in_progress' },
    { label: t('letters.dispositions.statuses.completed'), value: 'completed' },
    { label: t('letters.dispositions.statuses.cancelled'), value: 'cancelled' },
    { label: t('letters.dispositions.statuses.all'), value: 'all' }
]);

const options = ref(null);
const items = ref([]);
const meta = ref({ current_page: 1, per_page: 20, total: 0, last_page: 1 });
const listLoading = ref(false);
const listError = ref('');
const page = ref(1);
const filters = reactive({ search: '', status: 'open', overdue: false, recipient: null, perPage: 20 });
const recipientOptions = ref([]);
let searchTimer = null;
let listRequestId = 0;

const perPageOptions = computed(() => options.value?.per_page_options ?? [10, 20, 50, 100]);
const followUpMin = computed(() => options.value?.follow_up_min_length ?? 10);
const followUpMax = computed(() => options.value?.follow_up_max_length ?? 2000);
const cancelMin = computed(() => options.value?.cancel_reason_min_length ?? 10);
const cancelMax = computed(() => options.value?.cancel_reason_max_length ?? 255);
const noteMax = computed(() => options.value?.note_max_length ?? 2000);
const perPageLabel = computed(() => t('letters.common.perPageLabel', { count: filters.perPage }));

const notify = (severity, detail) => {
    toast.add({ severity, summary: 'Letter Dispositions', detail, life: 5000 });
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

const loadOptions = async () => {
    try {
        const response = await apiLogin.get('/api/letters/dispositions/options');
        options.value = response.data?.data ?? null;
        filters.perPage = options.value?.default_per_page ?? filters.perPage;
    } catch {
        options.value = null;
    }
};

const loadRecipientOptions = async () => {
    if (!canFilterRecipients.value) return;
    try {
        const response = await apiLogin.get('/api/letters/dispositions/recipients', { params: { per_page: 100 } });
        recipientOptions.value = response.data?.data?.items ?? [];
    } catch {
        recipientOptions.value = [];
    }
};

const loadDispositions = async () => {
    const requestId = ++listRequestId;
    listLoading.value = true;
    listError.value = '';

    try {
        const params = { page: page.value, per_page: filters.perPage, status: filters.status };
        if (filters.search.trim()) params.search = filters.search.trim();
        if (filters.overdue) params.overdue = 'true';
        if (filters.recipient) params.recipient_employee_id = filters.recipient;

        const response = await apiLogin.get('/api/letters/dispositions', { params });
        if (requestId !== listRequestId) return;

        const data = response.data?.data ?? {};
        items.value = data.items ?? [];
        meta.value = data.meta ?? { current_page: 1, per_page: filters.perPage, total: 0, last_page: 1 };
        if (meta.value.per_page) filters.perPage = meta.value.per_page;

        if (items.value.length === 0 && page.value > 1 && page.value > meta.value.last_page) {
            page.value = Math.max(1, meta.value.last_page);
            return loadDispositions();
        }
    } catch (error) {
        if (requestId !== listRequestId) return;
        items.value = [];
        listError.value = errorMessage(error, 'Failed to load dispositions. Please try again.');
    } finally {
        if (requestId === listRequestId) listLoading.value = false;
    }
};

const onSearchInput = () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        page.value = 1;
        loadDispositions();
    }, 300);
};

const onFilterChange = () => {
    page.value = 1;
    loadDispositions();
};

const resetFilters = () => {
    selectedDispositions.value = [];
    filters.search = '';
    filters.status = 'open';
    filters.overdue = false;
    filters.recipient = null;
    page.value = 1;
    loadDispositions();
};

const onPage = (event) => {
    const nextPage = event.page + 1;
    const rowsChanged = event.rows !== filters.perPage;
    page.value = rowsChanged ? 1 : nextPage;
    if (rowsChanged) filters.perPage = event.rows;
    loadDispositions();
};

const editVisible = ref(false);
const editSaving = ref(false);
const editTarget = ref(null);
const editErrors = ref({});
const editError = ref('');
const editForm = reactive({ dueDate: null, note: '' });

const openEdit = (row) => {
    if (!canManage.value) return;
    editTarget.value = row;
    editForm.dueDate = isoToDate(row.due_date);
    editForm.note = row.note ?? '';
    editErrors.value = {};
    editError.value = '';
    editSaving.value = false;
    editVisible.value = true;
};

const submitEdit = async () => {
    if (editSaving.value || !editTarget.value) return;

    editErrors.value = {};
    editError.value = '';

    const errors = {};
    if (!dateToIso(editForm.dueDate)) errors.due_date = ['The due date is required.'];
    if (editForm.note.trim().length > noteMax.value) errors.note = [`The note may not exceed ${noteMax.value} characters.`];
    if (Object.keys(errors).length > 0) {
        editErrors.value = errors;
        return;
    }

    editSaving.value = true;
    try {
        const response = await apiLogin.put(`/api/letters/incoming/${editTarget.value.letter.id}/dispositions/${editTarget.value.id}`, {
            due_date: dateToIso(editForm.dueDate),
            note: editForm.note.trim() || null,
            expected_version: editTarget.value.version
        });
        editVisible.value = false;
        notify('success', response.data?.message ?? 'Disposition updated.');
        await loadDispositions();
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            editErrors.value = validationErrors(error);
            if (Object.keys(editErrors.value).length === 0) editError.value = errorMessage(error, 'The disposition could not be saved.');
        } else if (status === 409) {
            editVisible.value = false;
            openConflict(error);
        } else {
            editError.value = errorMessage(error, 'Failed to update the disposition.');
        }
    } finally {
        editSaving.value = false;
    }
};

const startSaving = ref(false);
const startTargetId = ref(null);

const startDisposition = async (row) => {
    if (!canManage.value || startSaving.value) return;

    startSaving.value = true;
    startTargetId.value = row.id;
    try {
        const response = await apiLogin.put(`/api/letters/incoming/${row.letter.id}/dispositions/${row.id}`, {
            status: 'in_progress',
            expected_version: row.version
        });
        notify('success', response.data?.message ?? 'Disposition started.');
        await loadDispositions();
    } catch (error) {
        if (error.response?.status === 409) {
            openConflict(error);
        } else {
            notify('error', errorMessage(error, 'Failed to start the disposition.'));
        }
    } finally {
        startSaving.value = false;
        startTargetId.value = null;
    }
};

const completeVisible = ref(false);
const completeSaving = ref(false);
const completeTarget = ref(null);
const completeErrors = ref({});
const completeError = ref('');
const completeNote = ref('');

const openComplete = (row) => {
    if (!canManage.value) return;
    completeTarget.value = row;
    completeNote.value = '';
    completeErrors.value = {};
    completeError.value = '';
    completeSaving.value = false;
    completeVisible.value = true;
};

const submitComplete = async () => {
    if (completeSaving.value || !completeTarget.value) return;

    completeErrors.value = {};
    completeError.value = '';

    const note = completeNote.value.trim();
    if (note.length < followUpMin.value || note.length > followUpMax.value) {
        completeErrors.value = {
            follow_up_note: [note.length < followUpMin.value ? `The follow-up note must be at least ${followUpMin.value} characters.` : `The follow-up note may not exceed ${followUpMax.value} characters.`]
        };
        return;
    }

    completeSaving.value = true;
    try {
        const response = await apiLogin.put(`/api/letters/incoming/${completeTarget.value.letter.id}/dispositions/${completeTarget.value.id}`, {
            status: 'completed',
            follow_up_note: note,
            expected_version: completeTarget.value.version
        });
        completeVisible.value = false;
        notify('success', response.data?.message ?? 'Disposition completed.');
        await loadDispositions();
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            completeErrors.value = validationErrors(error);
            if (Object.keys(completeErrors.value).length === 0) completeError.value = errorMessage(error, 'The disposition could not be completed.');
        } else if (status === 409) {
            completeVisible.value = false;
            openConflict(error);
        } else {
            completeError.value = errorMessage(error, 'Failed to complete the disposition.');
        }
    } finally {
        completeSaving.value = false;
    }
};

const cancelVisible = ref(false);
const cancelSaving = ref(false);
const cancelTarget = ref(null);
const cancelErrors = ref({});
const cancelError = ref('');
const cancelReason = ref('');

const selectedDispositions = ref([]);
const batchCancelVisible = ref(false);
const batchCancelReason = ref('');
const batchCancelErrors = ref({});
const batchCancelError = ref('');
const isSubmittingBatchCancel = ref(false);

const cancellableSelected = computed(() => (selectedDispositions.value || []).filter((item) => ['pending', 'in_progress'].includes(item.status)));

const openBatchCancel = () => {
    if (cancellableSelected.value.length === 0) return;
    batchCancelReason.value = '';
    batchCancelErrors.value = {};
    batchCancelError.value = '';
    batchCancelVisible.value = true;
};

const submitBatchCancel = async () => {
    if (isSubmittingBatchCancel.value || cancellableSelected.value.length === 0) return;

    const reason = batchCancelReason.value.trim();
    if (reason.length < cancelMin.value || reason.length > cancelMax.value) {
        batchCancelErrors.value = {
            reason: [reason.length < cancelMin.value ? `The reason must be at least ${cancelMin.value} characters.` : `The reason may not exceed ${cancelMax.value} characters.`]
        };
        return;
    }

    isSubmittingBatchCancel.value = true;
    batchCancelErrors.value = {};
    batchCancelError.value = '';

    try {
        let count = 0;
        for (const item of cancellableSelected.value) {
            await apiLogin.post(`/api/letters/incoming/${item.letter.id}/dispositions/${item.id}/cancel`, {
                reason,
                expected_version: item.version
            });
            count++;
        }
        batchCancelVisible.value = false;
        notify('success', `${count} disposisi berhasil dibatalkan.`);
        selectedDispositions.value = [];
        await loadDispositions();
    } catch (error) {
        const status = error.response?.status;
        if (status === 409) {
            batchCancelVisible.value = false;
            openConflict(error);
        } else if (status === 422) {
            batchCancelErrors.value = validationErrors(error);
            if (Object.keys(batchCancelErrors.value).length === 0) {
                batchCancelError.value = errorMessage(error, 'Disposisi tidak dapat dibatalkan.');
            }
        } else {
            batchCancelError.value = errorMessage(error, 'Gagal membatalkan disposisi terpilih.');
        }
        await loadDispositions();
    } finally {
        isSubmittingBatchCancel.value = false;
    }
};

const openCancel = (row) => {
    if (!canManage.value) return;
    cancelTarget.value = row;
    cancelReason.value = '';
    cancelErrors.value = {};
    cancelError.value = '';
    cancelSaving.value = false;
    cancelVisible.value = true;
};

const submitCancel = async () => {
    if (cancelSaving.value || !cancelTarget.value) return;

    cancelErrors.value = {};
    cancelError.value = '';

    const reason = cancelReason.value.trim();
    if (reason.length < cancelMin.value || reason.length > cancelMax.value) {
        cancelErrors.value = {
            reason: [reason.length < cancelMin.value ? `The reason must be at least ${cancelMin.value} characters.` : `The reason may not exceed ${cancelMax.value} characters.`]
        };
        return;
    }

    cancelSaving.value = true;
    try {
        const response = await apiLogin.post(`/api/letters/incoming/${cancelTarget.value.letter.id}/dispositions/${cancelTarget.value.id}/cancel`, {
            reason,
            expected_version: cancelTarget.value.version
        });
        cancelVisible.value = false;
        notify('success', response.data?.message ?? 'Disposition cancelled.');
        await loadDispositions();
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            cancelErrors.value = validationErrors(error);
            if (Object.keys(cancelErrors.value).length === 0) cancelError.value = errorMessage(error, 'The disposition could not be cancelled.');
        } else if (status === 409) {
            cancelVisible.value = false;
            openConflict(error);
        } else {
            cancelError.value = errorMessage(error, 'Failed to cancel the disposition.');
        }
    } finally {
        cancelSaving.value = false;
    }
};

const conflictVisible = ref(false);
const conflictMessage = ref('');
const reloadingConflict = ref(false);

const openConflict = (error) => {
    conflictMessage.value = errorMessage(error, 'This disposition was changed by another request.');
    conflictVisible.value = true;
};

const reloadLatest = async () => {
    if (reloadingConflict.value) return;
    reloadingConflict.value = true;
    try {
        await loadDispositions();
        conflictVisible.value = false;
        notify('warn', 'The latest dispositions have been loaded. Review the row before trying again.');
    } catch (error) {
        notify('error', errorMessage(error, 'Failed to reload the latest dispositions.'));
    } finally {
        reloadingConflict.value = false;
    }
};

onMounted(async () => {
    await store.fetchIdentity();
    await Promise.all([loadOptions(), loadRecipientOptions()]);
    await loadDispositions();
});
</script>

<template>
    <Fluid>
        <div class="bg-surface-0 dark:bg-surface-950 px-4 py-6 sm:px-6 sm:py-8 md:px-12 lg:px-20">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <div class="font-bold text-2xl sm:text-3xl text-surface-900 dark:text-surface-0 mb-2">{{ t('letters.dispositions.title') }}</div>
                    <p class="text-surface-500 dark:text-surface-400">{{ t('letters.dispositions.subtitle') }}</p>
                </div>
                <Tag :value="t('letters.dispositions.rows', { count: meta.total })" severity="secondary" />
            </div>

            <div class="card mt-6 flex flex-col gap-4">
                <div class="flex flex-col xl:flex-row xl:items-center gap-3">
                    <IconField class="w-full xl:max-w-sm">
                        <InputIcon><i class="pi pi-search" /></InputIcon>
                        <InputText v-model="filters.search" :placeholder="t('letters.dispositions.searchPlaceholder')" class="w-full" maxlength="100" :aria-label="t('letters.dispositions.searchPlaceholder')" @input="onSearchInput" />
                    </IconField>
                    <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value" class="w-full xl:w-44" :aria-label="t('letters.dispositions.status')" @change="onFilterChange" />
                    <Select
                        v-if="canFilterRecipients"
                        v-model="filters.recipient"
                        :options="recipientOptions"
                        optionLabel="name"
                        optionValue="id"
                        :placeholder="t('letters.dispositions.allRecipients')"
                        showClear
                        filter
                        class="w-full xl:w-56"
                        :aria-label="t('letters.dispositions.recipient')"
                        @change="onFilterChange"
                    />
                    <div class="flex items-center gap-2">
                        <Checkbox v-model="filters.overdue" binary inputId="dispositionOverdue" @change="onFilterChange" />
                        <label for="dispositionOverdue" class="text-sm">{{ t('letters.dispositions.overdueOnly') }}</label>
                    </div>
                    <div class="flex items-center gap-2 xl:ml-auto">
                        <Select v-model="filters.perPage" :options="perPageOptions" class="w-28" :aria-label="perPageLabel" @change="onFilterChange" />
                        <Button :label="t('letters.common.clear')" icon="pi pi-filter-slash" severity="secondary" variant="outlined" @click="resetFilters" />
                    </div>
                </div>

                                <div v-if="selectedDispositions.length > 0" class="flex flex-wrap items-center justify-between gap-3 p-3 bg-primary-50 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800 rounded-lg">
                    <div class="flex items-center gap-2">
                        <i class="pi pi-check-circle text-primary text-lg"></i>
                        <span class="font-medium text-sm text-surface-900 dark:text-surface-100">{{ selectedDispositions.length }} disposisi dipilih</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-if="canManage && cancellableSelected.length > 0"
                            :label="`Batalkan Terpilih (${cancellableSelected.length})`"
                            icon="pi pi-ban"
                            severity="danger"
                            size="small"
                            @click="openBatchCancel"
                        />
                        <Button
                            label="Batal Pilih"
                            icon="pi pi-times"
                            severity="secondary"
                            variant="text"
                            size="small"
                            @click="selectedDispositions = []"
                        />
                    </div>
                </div>

                <DataTable
                    v-model:selection="selectedDispositions"
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
                    tableStyle="min-width: 68rem"
                    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                    :currentPageReportTemplate="t('letters.dispositions.reportTemplate')"
                    @page="onPage"
                >
                    <template #loading>{{ t('letters.dispositions.messages.loading') }}</template>
                    <template #empty>
                        <div class="flex flex-col items-center gap-3 py-6">
                            <span v-if="listError" class="text-red-500">{{ listError }}</span>
                            <span v-else>{{ t('letters.dispositions.messages.empty') }}</span>
                            <Button v-if="listError" icon="pi pi-refresh" :label="t('letters.common.retry')" @click="loadDispositions" />
                        </div>
                    </template>
                    <Column :header="t('letters.dispositions.columns.letter')" style="min-width: 18rem">
                        <template #body="{ data }">
                            <div class="font-medium">{{ data.letter.reference }}</div>
                            <div class="text-sm truncate" :title="data.letter.subject">{{ data.letter.subject }}</div>
                            <div class="text-xs text-surface-500">{{ data.letter.source_number || t('letters.dispositions.noSourceNumber') }} · {{ data.letter.sender }}</div>
                        </template>
                    </Column>
                    <Column :header="t('letters.dispositions.columns.recipient')" style="min-width: 12rem">
                        <template #body="{ data }">
                            <div class="font-medium">{{ data.recipient?.name ?? '-' }}</div>
                            <div v-if="data.recipient?.employee_type" class="text-xs text-surface-500">{{ data.recipient.employee_type }}</div>
                        </template>
                    </Column>
                    <Column :header="t('letters.dispositions.columns.dueDate')" style="min-width: 8rem">
                        <template #body="{ data }">
                            <div>{{ formatDate(data.due_date) }}</div>
                            <Tag v-if="data.overdue" :value="t('letters.dispositions.overdue')" severity="danger" class="mt-1" />
                        </template>
                    </Column>
                    <Column :header="t('letters.dispositions.columns.status')" style="min-width: 8rem">
                        <template #body="{ data }">
                            <Tag :value="dispositionLabels[data.status] ?? data.status" :severity="dispositionSeverity[data.status] ?? 'secondary'" />
                            <div class="text-xs text-surface-500 mt-1">{{ t('letters.dispositions.version', { version: data.version }) }}</div>
                        </template>
                    </Column>
                    <Column :header="t('letters.dispositions.columns.note')" style="min-width: 14rem">
                        <template #body="{ data }">
                            <span class="block truncate" :title="data.note ?? ''">{{ data.note || '-' }}</span>
                        </template>
                    </Column>
                    <Column :header="t('letters.dispositions.columns.outcome')" style="min-width: 16rem">
                        <template #body="{ data }">
                            <div v-if="data.follow_up_note" class="text-sm whitespace-pre-line break-words">{{ data.follow_up_note }}</div>
                            <div v-else-if="data.cancel_reason" class="text-sm whitespace-pre-line break-words">{{ data.cancel_reason }}</div>
                            <span v-else class="text-surface-500">-</span>
                            <div v-if="data.completed_by" class="text-xs text-surface-500 mt-1">{{ data.completed_by.name }} · {{ formatDateTime(data.completed_at) }}</div>
                            <div v-if="data.cancelled_by" class="text-xs text-surface-500 mt-1">{{ data.cancelled_by.name }} · {{ formatDateTime(data.cancelled_at) }}</div>
                        </template>
                    </Column>
                    <Column :header="t('letters.dispositions.columns.actions')" style="min-width: 11rem">
                        <template #body="{ data }">
                            <div v-if="canManage && (data.status === 'pending' || data.status === 'in_progress')" class="flex flex-wrap items-center gap-2">
                                <Button icon="pi pi-pencil" :aria-label="t('letters.dispositions.actions.editTooltip')" size="small" variant="outlined" rounded v-tooltip.top="t('letters.common.edit')" @click="openEdit(data)" />
                                <Button
                                    v-if="data.status === 'pending'"
                                    icon="pi pi-play"
                                    :aria-label="t('letters.dispositions.actions.startTooltip')"
                                    size="small"
                                    severity="info"
                                    variant="outlined"
                                    rounded
                                    :loading="startSaving && startTargetId === data.id"
                                    v-tooltip.top="t('letters.dispositions.actions.start')"
                                    @click="startDisposition(data)"
                                />
                                <Button
                                    v-if="data.status === 'in_progress'"
                                    icon="pi pi-check"
                                    :aria-label="t('letters.dispositions.actions.completeTooltip')"
                                    size="small"
                                    severity="success"
                                    variant="outlined"
                                    rounded
                                    v-tooltip.top="t('letters.dispositions.actions.complete')"
                                    @click="openComplete(data)"
                                />
                                <Button icon="pi pi-ban" :aria-label="t('letters.dispositions.actions.cancelTooltip')" size="small" severity="danger" variant="outlined" rounded v-tooltip.top="t('letters.common.cancel')" @click="openCancel(data)" />
                            </div>
                            <span v-else class="text-sm text-surface-500">{{ t('letters.dispositions.readOnly') }}</span>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </Fluid>

    <Dialog v-model:visible="editVisible" modal :draggable="false" :style="{ width: 'min(94vw, 34rem)' }" :header="t('letters.dispositions.dialogs.editTitle')">
        <div class="flex flex-col gap-4">
            <div v-if="editTarget" class="text-sm text-surface-500">
                <span class="font-medium text-surface-900 dark:text-surface-0">{{ editTarget.letter.reference }}</span>
                — {{ editTarget.recipient?.name }}
            </div>
            <div class="flex flex-col gap-2">
                <label for="editDispositionDueDate" class="font-medium">{{ t('letters.dispositions.fields.dueDate') }} <span class="text-red-500">*</span></label>
                <DatePicker inputId="editDispositionDueDate" v-model="editForm.dueDate" dateFormat="yy-mm-dd" showIcon manualInput fluid />
                <small v-if="fieldError(editErrors, 'due_date')" class="text-red-500">{{ fieldError(editErrors, 'due_date') }}</small>
            </div>
            <div class="flex flex-col gap-2">
                <label for="editDispositionNote" class="font-medium">{{ t('letters.dispositions.columns.note') }}</label>
                <Textarea id="editDispositionNote" v-model="editForm.note" rows="3" :maxlength="noteMax" autoResize />
                <small class="text-surface-500">{{ editForm.note.length }}/{{ noteMax }}</small>
                <small v-if="fieldError(editErrors, 'note')" class="text-red-500">{{ fieldError(editErrors, 'note') }}</small>
                <small v-if="editError" class="text-red-500">{{ editError }}</small>
            </div>
        </div>
        <template #footer>
            <Button :label="t('letters.common.cancel')" severity="secondary" variant="text" :disabled="editSaving" @click="editVisible = false" />
            <Button :label="t('letters.common.saveChanges')" icon="pi pi-check" :loading="editSaving" @click="submitEdit" />
        </template>
    </Dialog>

    <Dialog v-model:visible="completeVisible" modal :draggable="false" :style="{ width: 'min(94vw, 34rem)' }" :header="t('letters.dispositions.dialogs.completeTitle')">
        <div class="flex flex-col gap-4">
            <div v-if="completeTarget" class="text-sm text-surface-500">
                <span class="font-medium text-surface-900 dark:text-surface-0">{{ completeTarget.letter.reference }}</span>
                — {{ completeTarget.recipient?.name }}
            </div>
            <Message severity="info" :closable="false">{{ t('letters.dispositions.dialogs.completeNotice') }}</Message>
            <div class="flex flex-col gap-2">
                <label for="completeFollowUp" class="font-medium">{{ t('letters.dispositions.fields.note') }} <span class="text-red-500">*</span></label>
                <Textarea id="completeFollowUp" v-model="completeNote" rows="3" :maxlength="followUpMax" autoResize :placeholder="t('letters.dispositions.fields.outcomePlaceholder')" />
                <small class="text-surface-500">{{ t('letters.dispositions.minChars', { min: followUpMin, current: completeNote.trim().length, max: followUpMax }) }}</small>
                <small v-if="fieldError(completeErrors, 'follow_up_note')" class="text-red-500">{{ fieldError(completeErrors, 'follow_up_note') }}</small>
                <small v-if="completeError" class="text-red-500">{{ completeError }}</small>
            </div>
        </div>
        <template #footer>
            <Button :label="t('letters.common.cancel')" severity="secondary" variant="text" :disabled="completeSaving" @click="completeVisible = false" />
            <Button :label="t('letters.dispositions.actions.complete')" icon="pi pi-check" severity="success" :loading="completeSaving" @click="submitComplete" />
        </template>
    </Dialog>

    <Dialog v-model:visible="cancelVisible" modal :draggable="false" :style="{ width: 'min(94vw, 34rem)' }" :header="t('letters.dispositions.dialogs.cancelTitle')">
        <div class="flex flex-col gap-4">
            <div v-if="cancelTarget" class="text-sm text-surface-500">
                <span class="font-medium text-surface-900 dark:text-surface-0">{{ cancelTarget.letter.reference }}</span>
                — {{ cancelTarget.recipient?.name }}
            </div>
            <div class="flex flex-col gap-2">
                <label for="cancelDispositionReason" class="font-medium">{{ t('letters.dispositions.fields.cancelReason') }} <span class="text-red-500">*</span></label>
                <Textarea id="cancelDispositionReason" v-model="cancelReason" rows="3" :maxlength="cancelMax" autoResize :placeholder="t('letters.dispositions.fields.cancelPlaceholder')" />
                <small class="text-surface-500">{{ t('letters.dispositions.minChars', { min: cancelMin, current: cancelReason.trim().length, max: cancelMax }) }}</small>
                <small v-if="fieldError(cancelErrors, 'reason')" class="text-red-500">{{ fieldError(cancelErrors, 'reason') }}</small>
                <small v-if="cancelError" class="text-red-500">{{ cancelError }}</small>
            </div>
        </div>
        <template #footer>
            <Button :label="t('letters.common.cancel')" severity="secondary" variant="text" :disabled="cancelSaving" @click="cancelVisible = false" />
            <Button :label="t('letters.dispositions.actions.cancelDisposition')" icon="pi pi-ban" severity="danger" :loading="cancelSaving" @click="submitCancel" />
        </template>
    </Dialog>

    <Dialog v-model:visible="conflictVisible" modal :draggable="false" :style="{ width: 'min(94vw, 34rem)' }" :header="t('letters.common.conflictTitle')">
        <div class="flex flex-col gap-3">
            <Message severity="warn" :closable="false">{{ conflictMessage }}</Message>
            <p class="text-sm text-surface-500">{{ t('letters.common.conflictSubtitle') }}</p>
        </div>
        <template #footer>
            <Button :label="t('letters.common.close')" severity="secondary" variant="text" @click="conflictVisible = false" />
            <Button :label="t('letters.common.reloadLatest')" icon="pi pi-refresh" :loading="reloadingConflict" @click="reloadLatest" />
        </template>
    </Dialog>
    <Dialog v-model:visible="batchCancelVisible" modal :draggable="false" :style="{ width: 'min(94vw, 36rem)' }" header="Batalkan Disposisi Terpilih">
        <div class="flex flex-col gap-4">
            <Message v-if="batchCancelError" severity="error" :closable="false">{{ batchCancelError }}</Message>
            <div class="text-sm text-surface-600 dark:text-surface-300">
                Anda akan membatalkan <strong>{{ cancellableSelected.length }}</strong> disposisi terpilih:
                <ul class="list-disc pl-5 mt-2 max-h-36 overflow-y-auto space-y-1 text-xs">
                    <li v-for="item in cancellableSelected" :key="item.id">
                        <span class="font-medium">{{ item.recipient?.name }}</span>: {{ item.letter?.reference }} ({{ item.letter?.subject }})
                    </li>
                </ul>
            </div>
            <Message severity="warn" :closable="false">{{ t('letters.dispositions.dialogs.cancelWarning') }}</Message>
            <div class="flex flex-col gap-2">
                <label for="batchCancelReason" class="font-medium">{{ t('letters.dispositions.fields.cancellationReason') }} <span class="text-red-500">*</span></label>
                <Textarea id="batchCancelReason" v-model="batchCancelReason" rows="3" :maxlength="cancelMax" autoResize :placeholder="t('letters.dispositions.fields.cancellationReasonPlaceholder')" />
                <small class="text-surface-500">{{ batchCancelReason.length }}/{{ cancelMax }} ({{ t('letters.dispositions.fields.cancellationReasonMin', { min: cancelMin }) }})</small>
                <small v-if="fieldError(batchCancelErrors, 'reason')" class="text-red-500">{{ fieldError(batchCancelErrors, 'reason') }}</small>
            </div>
        </div>
        <template #footer>
            <Button :label="t('letters.common.cancel')" severity="secondary" variant="text" :disabled="isSubmittingBatchCancel" @click="batchCancelVisible = false" />
            <Button :label="`Batalkan (${cancellableSelected.length})`" icon="pi pi-ban" severity="danger" :loading="isSubmittingBatchCancel" @click="submitBatchCancel" />
        </template>
    </Dialog>
</template>
