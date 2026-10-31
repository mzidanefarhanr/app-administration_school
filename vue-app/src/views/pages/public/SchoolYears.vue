<script setup>
import { ref, computed, onMounted } from 'vue';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import { authStore } from '@/stores/authStore';
import apiLogin from '@/apiLogin';

const toast = useToast();
const store = authStore();
const canManage = computed(() => store.can('master.manage'));

const years = ref([]);
const semesters = ref([]);
const savedPeriod = ref(null);
const periodUnavailable = ref('');
const loading = ref(true);
const loadError = ref('');

const search = ref('');
const rowsPerPage = ref(10);
const rowsPerPageOptions = [10, 25, 50, 100];

const draftYearId = ref(null);
const draftSemesterId = ref(null);
const periodSaving = ref(false);
const periodErrors = ref({});
const periodMessage = ref('');

const activeYear = computed(() => savedPeriod.value?.active?.school_year ?? null);
const activeSemester = computed(() => savedPeriod.value?.active?.semester ?? null);
const periodDirty = computed(() => {
    const currentYearId = activeYear.value?.id ?? 0;
    const currentSemesterId = activeSemester.value?.id ?? 0;
    return Number(draftYearId.value ?? 0) !== Number(currentYearId)
        || Number(draftSemesterId.value ?? 0) !== Number(currentSemesterId);
});
const canSavePeriod = computed(() => canManage.value
    && draftYearId.value
    && draftSemesterId.value
    && periodDirty.value
    && !periodSaving.value);

const filteredYears = computed(() => {
    const query = search.value.trim().toLowerCase();
    const rows = [...years.value].sort((a, b) => b.id - a.id);
    if (!query) return rows;
    return rows.filter((year) => (year.name ?? '').toLowerCase().includes(query));
});

const onPage = (event) => {
    rowsPerPage.value = event.rows;
};

const yearDialog = ref(false);
const yearEditing = ref(false);
const yearSaving = ref(false);
const yearSubmitted = ref(false);
const yearErrors = ref({});
const yearForm = ref({ id: null, name: '', start_date: null, end_date: null });

const lockDialog = ref(false);
const lockMode = ref('lock');
const lockTarget = ref(null);
const lockReason = ref('');
const lockSaving = ref(false);
const lockErrors = ref({});

const deleteDialog = ref(false);
const deleteTarget = ref(null);
const deleteSaving = ref(false);
const deleteError = ref('');

const notify = (severity, detail, summary = 'School Years') => {
    toast.add({ severity, summary, detail, life: 5000 });
};

const validationErrors = (error) => {
    const data = error.response?.data ?? {};
    if (data.errors && typeof data.errors === 'object') return data.errors;
    const mapped = Object.fromEntries(
        Object.entries(data).filter(([, value]) => Array.isArray(value)),
    );
    return mapped;
};

const errorMessage = (error, fallback) => error.response?.data?.message ?? fallback;

const fieldError = (errors, field) => errors?.[field]?.[0] ?? '';

const dateToInput = (value) => {
    if (!value) return null;
    const [year, month, day] = value.split('-').map(Number);
    if (!year || !month || !day) return null;
    return new Date(year, month - 1, day);
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

const loadYears = async () => {
    const response = await apiLogin.get('/api/school-years');
    years.value = response.data?.data ?? [];
};

const loadPeriod = async () => {
    try {
        const response = await apiLogin.get('/api/academic/period');
        savedPeriod.value = response.data?.data ?? null;
        semesters.value = savedPeriod.value?.semesters ?? [];
        draftYearId.value = savedPeriod.value?.active?.school_year?.id ?? null;
        draftSemesterId.value = savedPeriod.value?.active?.semester?.id ?? null;
        periodUnavailable.value = '';
        periodErrors.value = {};
        periodMessage.value = '';
    } catch (error) {
        savedPeriod.value = null;
        periodUnavailable.value = error.response?.status === 403
            ? 'The active academic period is not available for your role.'
            : errorMessage(error, 'Failed to load the active academic period.');
    }
};

const loadAll = async () => {
    loading.value = true;
    loadError.value = '';
    try {
        await loadYears();
    } catch (error) {
        years.value = [];
        loadError.value = errorMessage(error, 'Failed to load school years. Please try again.');
    } finally {
        loading.value = false;
    }
    await loadPeriod();
};

const savePeriod = async () => {
    periodErrors.value = {};
    periodMessage.value = '';

    if (!draftYearId.value || !draftSemesterId.value) {
        periodErrors.value = {
            active_school_year_id: draftYearId.value ? [] : ['Active school year is required.'],
            active_semester_id: draftSemesterId.value ? [] : ['Active semester is required.'],
        };
        return;
    }

    periodSaving.value = true;
    try {
        const response = await apiLogin.put('/api/academic/period', {
            active_school_year_id: draftYearId.value,
            active_semester_id: draftSemesterId.value,
        });
        savedPeriod.value = response.data?.data ?? savedPeriod.value;
        semesters.value = savedPeriod.value?.semesters ?? semesters.value;
        draftYearId.value = savedPeriod.value?.active?.school_year?.id ?? draftYearId.value;
        draftSemesterId.value = savedPeriod.value?.active?.semester?.id ?? draftSemesterId.value;
        notify('success', response.data?.message ?? 'Academic period updated.');
        await loadYears();
    } catch (error) {
        periodErrors.value = validationErrors(error);
        periodMessage.value = errorMessage(error, '');
        if (Object.keys(periodErrors.value).length === 0 && !periodMessage.value) {
            notify('error', 'Failed to update the academic period. Please try again.');
        }
    } finally {
        periodSaving.value = false;
    }
};

const openCreateYear = () => {
    yearEditing.value = false;
    yearSubmitted.value = false;
    yearErrors.value = {};
    yearForm.value = { id: null, name: '', start_date: null, end_date: null };
    yearDialog.value = true;
};

const openEditYear = (year) => {
    yearEditing.value = true;
    yearSubmitted.value = false;
    yearErrors.value = {};
    yearForm.value = {
        id: year.id,
        name: year.name ?? '',
        start_date: dateToInput(year.start_date),
        end_date: dateToInput(year.end_date),
    };
    yearDialog.value = true;
};

const submitYear = async () => {
    if (yearSaving.value) return;
    yearSubmitted.value = true;
    yearErrors.value = {};

    const name = (yearForm.value.name ?? '').trim();
    const startDate = dateToIso(yearForm.value.start_date);
    const endDate = dateToIso(yearForm.value.end_date);

    if (!name) {
        yearErrors.value = { name: ['Name is required.'] };
        return;
    }
    if (startDate && endDate && startDate >= endDate) {
        yearErrors.value = { end_date: ['The start date must be earlier than the end date.'] };
        return;
    }

    yearSaving.value = true;
    try {
        const payload = { name, start_date: startDate, end_date: endDate };
        const response = yearEditing.value
            ? await apiLogin.put(`/api/school-years/${yearForm.value.id}`, payload)
            : await apiLogin.post('/api/school-years', payload);

        notify('success', response.data?.message ?? 'School year saved.');
        yearDialog.value = false;
        await loadYears();
        if (!yearEditing.value) await loadPeriod();
    } catch (error) {
        yearErrors.value = validationErrors(error);
        if (Object.keys(yearErrors.value).length === 0) {
            notify('error', errorMessage(error, 'Failed to save the school year.'));
        }
    } finally {
        yearSaving.value = false;
    }
};

const openLockDialog = (year) => {
    lockMode.value = year.is_locked ? 'unlock' : 'lock';
    lockTarget.value = year;
    lockReason.value = '';
    lockErrors.value = {};
    lockDialog.value = true;
};

const submitLock = async () => {
    if (lockSaving.value) return;
    lockErrors.value = {};

    const reason = lockReason.value.trim();
    if (!reason) {
        lockErrors.value = { reason: [`${lockMode.value === 'lock' ? 'Lock' : 'Unlock'} reason is required.`] };
        return;
    }

    lockSaving.value = true;
    try {
        const response = await apiLogin.post(`/api/school-years/${lockTarget.value.id}/${lockMode.value}`, { reason });
        notify(
            'success',
            response.data?.message ?? (lockMode.value === 'lock' ? 'School year locked.' : 'School year unlocked.'),
        );
        lockDialog.value = false;
        await loadAll();
    } catch (error) {
        lockErrors.value = validationErrors(error);
        if (Object.keys(lockErrors.value).length === 0) {
            notify('error', errorMessage(error, 'Failed to change the school year lock.'));
        }
    } finally {
        lockSaving.value = false;
    }
};

const deleteBlockReason = (year) => {
    if (year.is_active) return 'The active school year cannot be deleted. Set another active period first.';
    if (year.is_locked) return 'A locked school year cannot be deleted. Unlock it first.';
    if ((year.school_rombels_count ?? 0) > 0) {
        return `This year is referenced by ${year.school_rombels_count} class(es) and cannot be deleted.`;
    }
    return '';
};

const openDeleteDialog = (year) => {
    deleteTarget.value = year;
    deleteError.value = '';
    deleteDialog.value = true;
};

const confirmDelete = async () => {
    if (deleteSaving.value) return;
    deleteSaving.value = true;
    deleteError.value = '';
    try {
        const response = await apiLogin.delete(`/api/school-years/${deleteTarget.value.id}`, {
            data: { delete: 'true' },
        });
        notify('success', response.data?.message ?? 'School year deleted.');
        deleteDialog.value = false;
        await loadYears();
        await loadPeriod();
    } catch (error) {
        deleteError.value = errorMessage(error, 'Failed to delete the school year.');
    } finally {
        deleteSaving.value = false;
    }
};

onMounted(async () => {
    await store.fetchIdentity();
    await loadAll();
});
</script>

<template>
    <Fluid>
        <div class="bg-surface-0 dark:bg-surface-950 px-6 py-8 md:px-12 lg:px-20">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <div class="font-bold text-3xl text-surface-900 dark:text-surface-0 mb-2">School Years</div>
                    <p class="text-surface-500 dark:text-surface-400">
                        Academic periods, start and end dates, and per-year locks.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Tag v-if="activeYear" severity="success"
                        :value="`Active: ${activeYear.name}${activeSemester ? ' · ' + activeSemester.name : ''}`" />
                    <Tag v-else value="No active period" severity="warn" />
                    <Tag v-if="activeYear?.is_locked" value="Active year locked" severity="danger" />
                </div>
            </div>

            <div class="card mt-6 flex flex-col gap-4">
                <div>
                    <div class="font-semibold text-xl text-surface-900 dark:text-surface-0">Academic Period</div>
                    <p class="text-sm text-surface-500 dark:text-surface-400">
                        The active period is the default school year for students and classes. Locked years cannot be set as active.
                    </p>
                </div>

                <div v-if="periodUnavailable" class="text-orange-600 dark:text-orange-400 text-sm">
                    {{ periodUnavailable }}
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                    <div class="flex flex-col gap-2">
                        <label for="activeSchoolYear" class="font-medium">Active School Year</label>
                        <Select id="activeSchoolYear" v-model="draftYearId" :options="years" optionLabel="name"
                            optionValue="id" placeholder="Select school year" :disabled="!canManage" class="w-full" />
                        <small v-if="fieldError(periodErrors, 'active_school_year_id')" class="text-red-500">
                            {{ fieldError(periodErrors, 'active_school_year_id') }}
                        </small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="activeSemester" class="font-medium">Active Semester</label>
                        <Select id="activeSemester" v-model="draftSemesterId" :options="semesters" optionLabel="name"
                            optionValue="id" placeholder="Select semester" :disabled="!canManage" class="w-full" />
                        <small v-if="fieldError(periodErrors, 'active_semester_id')" class="text-red-500">
                            {{ fieldError(periodErrors, 'active_semester_id') }}
                        </small>
                    </div>
                    <div v-if="canManage" class="flex flex-col gap-2 md:pt-7">
                        <Button label="Save Active Period" icon="pi pi-check" :loading="periodSaving"
                            :disabled="!canSavePeriod" @click="savePeriod" />
                    </div>
                </div>

                <small v-if="periodMessage" class="text-red-500">{{ periodMessage }}</small>
                <small v-if="!canManage && activeYear" class="text-surface-500">
                    Only users with master data management permission can change the active period.
                </small>
            </div>

            <div class="card mt-6 flex flex-col gap-4">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <Button v-if="canManage" label="New School Year" icon="pi pi-plus" @click="openCreateYear" />
                        <span class="text-sm text-surface-500">Total school years: {{ years.length }}</span>
                    </div>
                    <IconField class="w-full md:w-72">
                        <InputIcon><i class="pi pi-search" /></InputIcon>
                        <InputText v-model="search" placeholder="Search school year" class="w-full" />
                    </IconField>
                </div>

                <DataTable :value="filteredYears" paginator showGridlines :rows="rowsPerPage"
                    :rowsPerPageOptions="rowsPerPageOptions" dataKey="id"
                    :loading="loading" @page="onPage"
                    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                    currentPageReportTemplate="Showing {first} to {last} of {totalRecords}">
                    <template #empty>
                        <div class="flex flex-col items-center gap-3 py-6">
                            <span v-if="loadError" class="text-red-500">{{ loadError }}</span>
                            <span v-else>No school years found.</span>
                            <Button v-if="loadError" icon="pi pi-refresh" label="Retry" @click="loadAll" />
                        </div>
                    </template>
                    <template #loading>Loading school years. Please wait.</template>
                    <Column header="No" style="width: 3rem">
                        <template #body="{ index }">{{ index + 1 }}</template>
                    </Column>
                    <Column field="name" header="Name" sortable style="min-width: 12rem" />
                    <Column field="start_date" header="Start Date" sortable style="min-width: 9rem">
                        <template #body="{ data }">{{ data.start_date || '-' }}</template>
                    </Column>
                    <Column field="end_date" header="End Date" sortable style="min-width: 9rem">
                        <template #body="{ data }">{{ data.end_date || '-' }}</template>
                    </Column>
                    <Column header="Active" style="min-width: 7rem">
                        <template #body="{ data }">
                            <Tag v-if="data.is_active" value="Active" severity="success" />
                            <span v-else class="text-surface-400">-</span>
                        </template>
                    </Column>
                    <Column header="Locked" style="min-width: 12rem">
                        <template #body="{ data }">
                            <div class="flex flex-col gap-1">
                                <Tag v-if="data.is_locked" value="Locked" severity="danger" />
                                <span v-else class="text-surface-400">Unlocked</span>
                                <small v-if="data.is_locked && data.lock_reason" class="text-surface-500">
                                    {{ data.lock_reason }}
                                </small>
                            </div>
                        </template>
                    </Column>
                    <Column header="Classes" style="min-width: 6rem">
                        <template #body="{ data }">{{ data.school_rombels_count ?? 0 }}</template>
                    </Column>
                    <Column v-if="canManage" header="Action" :exportable="false" style="min-width: 10rem">
                        <template #body="{ data }">
                            <Button icon="pi pi-pencil" variant="outlined" rounded class="mr-2"
                                v-tooltip.top="'Edit'" @click="openEditYear(data)" />
                            <Button :icon="data.is_locked ? 'pi pi-lock-open' : 'pi pi-lock'" variant="outlined"
                                rounded class="mr-2" :severity="data.is_locked ? 'success' : 'warn'"
                                :disabled="!data.is_locked && data.is_active"
                                v-tooltip.top="data.is_locked ? 'Unlock' : data.is_active ? 'The active year cannot be locked' : 'Lock'"
                                @click="openLockDialog(data)" />
                            <Button icon="pi pi-trash" variant="outlined" rounded severity="danger"
                                :disabled="!!deleteBlockReason(data)"
                                v-tooltip.top="deleteBlockReason(data) || 'Delete'"
                                @click="openDeleteDialog(data)" />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </Fluid>

    <Dialog v-model:visible="yearDialog" modal :style="{ width: '480px', maxWidth: '95vw' }"
        :header="yearEditing ? 'Edit School Year' : 'New School Year'" :draggable="false">
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <label for="yearName" class="font-medium">Name <span class="text-red-500">*</span></label>
                <InputText id="yearName" v-model="yearForm.name" maxlength="100" autofocus fluid
                    :invalid="yearSubmitted && !yearForm.name?.trim()" />
                <small v-if="yearSubmitted && !yearForm.name?.trim()" class="text-red-500">Name is required.</small>
                <small v-else-if="fieldError(yearErrors, 'name')" class="text-red-500">
                    {{ fieldError(yearErrors, 'name') }}
                </small>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label for="yearStart" class="font-medium">Start Date</label>
                    <DatePicker id="yearStart" v-model="yearForm.start_date" dateFormat="yy-mm-dd" showIcon fluid />
                    <small v-if="fieldError(yearErrors, 'start_date')" class="text-red-500">
                        {{ fieldError(yearErrors, 'start_date') }}
                    </small>
                </div>
                <div class="flex flex-col gap-2">
                    <label for="yearEnd" class="font-medium">End Date</label>
                    <DatePicker id="yearEnd" v-model="yearForm.end_date" dateFormat="yy-mm-dd" showIcon fluid />
                    <small v-if="fieldError(yearErrors, 'end_date')" class="text-red-500">
                        {{ fieldError(yearErrors, 'end_date') }}
                    </small>
                </div>
            </div>
            <small class="text-surface-500">Dates use the YYYY-MM-DD format. The start date must be earlier than the end date.</small>
        </div>
        <template #footer>
            <Button label="Cancel" icon="pi pi-times" text severity="secondary" :disabled="yearSaving"
                @click="yearDialog = false" />
            <Button :label="yearEditing ? 'Save Changes' : 'Create'" icon="pi pi-check" :loading="yearSaving"
                :disabled="yearSaving" @click="submitYear" />
        </template>
    </Dialog>

    <Dialog v-model:visible="lockDialog" modal :style="{ width: '480px', maxWidth: '95vw' }"
        :header="lockMode === 'lock' ? 'Lock School Year' : 'Unlock School Year'" :draggable="false">
        <div class="flex flex-col gap-4">
            <p class="text-sm">
                <template v-if="lockMode === 'lock'">
                    Locking <strong>{{ lockTarget?.name }}</strong> prevents class changes and student placement for this year.
                </template>
                <template v-else>
                    Unlocking <strong>{{ lockTarget?.name }}</strong> allows class changes and student placement again.
                </template>
            </p>
            <div class="flex flex-col gap-2">
                <label for="lockReason" class="font-medium">
                    {{ lockMode === 'lock' ? 'Lock' : 'Unlock' }} Reason <span class="text-red-500">*</span>
                </label>
                <Textarea id="lockReason" v-model="lockReason" rows="3" autoResize maxlength="255"
                    placeholder="Explain why this academic year is being locked or unlocked" />
                <small v-if="fieldError(lockErrors, 'reason')" class="text-red-500">
                    {{ fieldError(lockErrors, 'reason') }}
                </small>
            </div>
        </div>
        <template #footer>
            <Button label="Cancel" icon="pi pi-times" text severity="secondary" :disabled="lockSaving"
                @click="lockDialog = false" />
            <Button :label="lockMode === 'lock' ? 'Lock Year' : 'Unlock Year'"
                :icon="lockMode === 'lock' ? 'pi pi-lock' : 'pi pi-lock-open'"
                :severity="lockMode === 'lock' ? 'warn' : 'success'" :loading="lockSaving" :disabled="lockSaving"
                @click="submitLock" />
        </template>
    </Dialog>

    <Dialog v-model:visible="deleteDialog" modal :style="{ width: '460px', maxWidth: '95vw' }" header="Delete School Year"
        :draggable="false">
        <div class="flex items-start gap-4">
            <i class="pi pi-exclamation-triangle !text-3xl text-red-500" />
            <div>
                <p>Are you sure you want to delete <strong>{{ deleteTarget?.name }}</strong>?</p>
                <p class="text-sm text-surface-500 mt-2">
                    A school year can only be deleted when it is not active, not locked, and has no classes.
                </p>
                <p v-if="deleteError" class="text-red-500 text-sm mt-2">{{ deleteError }}</p>
            </div>
        </div>
        <template #footer>
            <Button label="Cancel" icon="pi pi-times" text severity="secondary" :disabled="deleteSaving"
                @click="deleteDialog = false" />
            <Button label="Delete" icon="pi pi-trash" severity="danger" :loading="deleteSaving" :disabled="deleteSaving"
                @click="confirmDelete" />
        </template>
    </Dialog>
</template>
