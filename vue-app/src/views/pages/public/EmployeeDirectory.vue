<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import { authStore } from '@/stores/authStore';
import apiLogin from '@/apiLogin';
import EmployeeFormDialog from './EmployeeFormDialog.vue';
import EmployeeDetailDialog from './EmployeeDetailDialog.vue';
import EmployeeArchiveDialog from './EmployeeArchiveDialog.vue';
import EmployeeAccountLinkDialog from './EmployeeAccountLinkDialog.vue';
import EmployeeImportDialog from './EmployeeImportDialog.vue';
import EmployeeExportDialog from './EmployeeExportDialog.vue';
import {
    buildEmployeeListParams,
    resolveEmployeeTypeId,
    firstValidationMessage,
    EMPLOYEE_PER_PAGE_OPTIONS,
} from './employeeDirectoryParams.mjs';

const props = defineProps({
    employeeTypeName: { type: String, required: true },
});

const toast = useToast();
const store = authStore();

const canManage = computed(() => store.can('employees.manage'));
const canExport = computed(() => store.can('employees.export'));
const canImport = computed(() => store.can('employees.import'));

const rows = ref([]);
const meta = ref({ current_page: 1, per_page: 20, total: 0, last_page: 1 });
const loading = ref(false);
const listError = ref('');
const search = ref('');
const statusFilter = ref(null);
const employmentFilter = ref(null);
const subjectFilter = ref(null);
const sortField = ref('name');
const sortOrder = ref(1);
const page = ref(1);
const perPage = ref(20);
const onlyArchived = ref(false);
const requestId = ref(0);
const debounceTimer = ref(null);

const options = ref({
    employeeTypes: [],
    employeeStatuses: [],
    employments: [],
    subjects: [],
    genders: [],
    religions: [],
    districts: [],
    maritalStatuses: [],
});

const fixedTypeId = ref(null);

const formVisible = ref(false);
const detailVisible = ref(false);
const archiveVisible = ref(false);
const accountVisible = ref(false);
const importVisible = ref(false);
const exportVisible = ref(false);
const selectedEmployee = ref(null);
const selectedDetail = ref(null);

const pageTitle = computed(() => (props.employeeTypeName === 'Pendidik' ? 'Teachers' : 'Employees'));
const isTeacherPage = computed(() => props.employeeTypeName === 'Pendidik');

const loadReference = async () => {
    const endpoints = {
        employeeTypes: '/api/employee-types',
        employeeStatuses: '/api/employee-statuses',
        employments: '/api/employments',
        subjects: '/api/subjects',
        genders: '/api/genders',
        religions: '/api/religions',
        districts: '/api/districts',
        maritalStatuses: '/api/marital-statuses',
    };

    const entries = Object.entries(endpoints);
    const results = await Promise.allSettled(entries.map(([, url]) => apiLogin.get(url)));

    results.forEach((result, index) => {
        if (result.status === 'fulfilled') {
            options.value[entries[index][0]] = result.value.data?.data ?? [];
        }
    });

    fixedTypeId.value = resolveEmployeeTypeId(options.value.employeeTypes, props.employeeTypeName);
};

const fetchList = async () => {
    if (fixedTypeId.value === null) return;

    const currentRequest = ++requestId.value;
    loading.value = true;
    listError.value = '';

    try {
        const params = buildEmployeeListParams({
            search: search.value,
            employeeTypeId: fixedTypeId.value,
            employeeStatusId: statusFilter.value,
            employmentId: employmentFilter.value,
            subjectId: isTeacherPage.value ? subjectFilter.value : null,
            page: page.value,
            perPage: perPage.value,
            sort: sortField.value,
            direction: sortOrder.value === -1 ? 'desc' : 'asc',
            onlyArchived: onlyArchived.value,
        });

        const response = await apiLogin.get('/api/employees', { params });
        if (currentRequest !== requestId.value) return;

        rows.value = response.data?.data ?? [];
        meta.value = response.data?.meta ?? { current_page: 1, per_page: perPage.value, total: 0, last_page: 1 };

        if (rows.value.length === 0 && page.value > 1 && page.value > meta.value.last_page) {
            page.value = Math.max(1, meta.value.last_page);
            return fetchList();
        }
    } catch (error) {
        if (currentRequest !== requestId.value) return;
        rows.value = [];
        listError.value = firstValidationMessage(error.response) ?? 'Failed to load employees. Please try again.';
    } finally {
        if (currentRequest === requestId.value) loading.value = false;
    }
};

const onSearchInput = () => {
    if (debounceTimer.value) clearTimeout(debounceTimer.value);
    debounceTimer.value = setTimeout(() => {
        page.value = 1;
        fetchList();
    }, 300);
};

const onFilterChange = () => {
    page.value = 1;
    fetchList();
};

const onPage = (event) => {
    page.value = event.page + 1;
    perPage.value = event.rows;
    fetchList();
};

const onSort = (event) => {
    sortField.value = event.sortField || 'name';
    sortOrder.value = event.sortOrder ?? 1;
    page.value = 1;
    fetchList();
};

const toggleArchived = () => {
    onlyArchived.value = !onlyArchived.value;
    page.value = 1;
    fetchList();
};

const resetFilters = () => {
    search.value = '';
    statusFilter.value = null;
    employmentFilter.value = null;
    subjectFilter.value = null;
    page.value = 1;
    fetchList();
};

const rowNumber = (index) => (meta.value.current_page - 1) * meta.value.per_page + index + 1;

const notify = (severity, detail, summary = null) => {
    toast.add({
        severity,
        summary: summary ?? (severity === 'success' ? 'Success' : 'Notice'),
        detail,
        life: 4000,
    });
};

const toId = (rowOrId) => (typeof rowOrId === 'object' ? rowOrId.id : rowOrId);

const fetchDetail = async (row) => {
    try {
        const response = await apiLogin.get(`/api/employees/${toId(row)}`);
        return response.data?.data ?? null;
    } catch (error) {
        notify('error', firstValidationMessage(error.response) ?? 'Failed to load employee detail.');
        return null;
    }
};

const openDetail = async (row) => {
    const detail = await fetchDetail(row);
    if (!detail) return;

    selectedDetail.value = detail;
    selectedEmployee.value = detail;
    detailVisible.value = true;
};

const openCreate = () => {
    selectedEmployee.value = null;
    formVisible.value = true;
};

const openEdit = async (row) => {
    const detail = await fetchDetail(row);
    if (!detail) return;

    selectedEmployee.value = detail;
    detailVisible.value = false;
    formVisible.value = true;
};

const openArchive = async (row) => {
    const detail = await fetchDetail(row);
    if (!detail) return;

    selectedEmployee.value = detail;
    detailVisible.value = false;
    archiveVisible.value = true;
};

const openAccount = async (row) => {
    const detail = await fetchDetail(row);
    if (!detail) return;

    selectedEmployee.value = detail;
    detailVisible.value = false;
    accountVisible.value = true;
};

const onSaved = () => {
    notify('success', `${isTeacherPage.value ? 'Teacher' : 'Employee'} saved.`);
    detailVisible.value = false;
    fetchList();
};

const onArchived = () => {
    notify('success', `${isTeacherPage.value ? 'Teacher' : 'Employee'} archived.`);
    fetchList();
};

const onAccountSaved = () => {
    notify('success', 'Account link updated.');
    detailVisible.value = false;
    fetchList();
};

const onConflict = (currentVersion) => {
    notify(
        'warn',
        `The record was changed by another user${currentVersion ? ` (version ${currentVersion})` : ''}. The latest data has been reloaded.`,
        'Conflict',
    );
    fetchList();
};

const restore = async (row) => {
    if (!window.confirm(`Restore ${row.name}?`)) return;

    try {
        await apiLogin.post(`/api/employees/${toId(row)}/restore`, { version: row.version });
        notify('success', `${isTeacherPage.value ? 'Teacher' : 'Employee'} restored.`);
        fetchList();
    } catch (error) {
        if (error.response?.status === 409) {
            onConflict(error.response?.data?.current_version ?? null);
        } else {
            notify('error', firstValidationMessage(error.response) ?? 'Failed to restore the employee.');
        }
    }
};

const exportFilters = computed(() => ({
    search: search.value,
    employeeTypeId: fixedTypeId.value,
    employeeStatusId: statusFilter.value,
    employmentId: employmentFilter.value,
    subjectId: isTeacherPage.value ? subjectFilter.value : null,
}));

const openExport = () => {
    if (fixedTypeId.value === null) return;

    exportVisible.value = true;
};

const onImported = (batch) => {
    notify('success', `${batch?.committed_rows ?? 0} ${isTeacherPage.value ? 'teacher' : 'employee'}(s) imported.`);
    onlyArchived.value = false;
    page.value = 1;
    fetchList();
};

const onExported = ({ columns }) => {
    notify('success', `Export downloaded with ${columns} column(s).`);
};

onMounted(async () => {
    await loadReference();
    if (fixedTypeId.value === null) {
        listError.value = `${pageTitle.value} reference data is not available. Ask an administrator to seed the reference data.`;
        return;
    }
    fetchList();
});

// Routes share this component, so a direct switch between Teachers and
// Employees must re-resolve the fixed type instead of reusing stale rows.
watch(() => props.employeeTypeName, (name) => {
    onlyArchived.value = false;
    search.value = '';
    statusFilter.value = null;
    employmentFilter.value = null;
    subjectFilter.value = null;
    page.value = 1;
    fixedTypeId.value = resolveEmployeeTypeId(options.value.employeeTypes, name);
    listError.value = fixedTypeId.value === null
        ? `${pageTitle.value} reference data is not available. Ask an administrator to seed the reference data.`
        : '';

    if (fixedTypeId.value !== null) fetchList();
});
</script>

<template>
    <div class="card flex flex-col gap-4 w-full">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h2 class="text-2xl font-semibold">{{ pageTitle }}</h2>
                <p class="text-sm text-surface-500">
                    {{ onlyArchived ? 'Archived records (managers only)' : isTeacherPage ? 'Teachers only' : 'Non-teaching staff only' }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button v-if="canExport" icon="pi pi-external-link" label="Export CSV" :disabled="fixedTypeId === null" @click="openExport" />
                <Button
                    v-if="canImport && !onlyArchived"
                    icon="pi pi-upload"
                    label="Import CSV"
                    severity="secondary"
                    variant="outlined"
                    :disabled="fixedTypeId === null"
                    @click="importVisible = true" />
                <Button v-if="canManage && !onlyArchived" icon="pi pi-plus" :label="isTeacherPage ? 'New Teacher' : 'New Employee'" @click="openCreate" />
                <Button
                    v-if="canManage"
                    :icon="onlyArchived ? 'pi pi-users' : 'pi pi-inbox'"
                    :label="onlyArchived ? 'Active Records' : 'Archived'"
                    severity="secondary"
                    variant="outlined"
                    @click="toggleArchived" />
                <Button icon="pi pi-filter-slash" label="Clear" severity="secondary" variant="outlined" @click="resetFilters" />
            </div>
        </div>

        <div class="flex flex-col xl:flex-row gap-3 xl:items-center">
            <IconField class="w-full xl:w-72">
                <InputIcon><i class="pi pi-search" /></InputIcon>
                <InputText v-model="search" placeholder="Search name or NUPTK" class="w-full" @input="onSearchInput" />
            </IconField>
            <Select
                v-model="statusFilter"
                :options="options.employeeStatuses"
                optionLabel="display_name"
                optionValue="id"
                placeholder="Employee Status"
                showClear
                class="w-full xl:w-52"
                @change="onFilterChange" />
            <Select
                v-model="employmentFilter"
                :options="options.employments"
                optionLabel="display_name"
                optionValue="id"
                placeholder="Employment"
                showClear
                class="w-full xl:w-52"
                @change="onFilterChange" />
            <Select
                v-if="isTeacherPage"
                v-model="subjectFilter"
                :options="options.subjects"
                optionLabel="display_name"
                optionValue="id"
                placeholder="Subject"
                showClear
                filter
                class="w-full xl:w-52"
                @change="onFilterChange" />
        </div>

        <DataTable
            :value="rows"
            :lazy="true"
            paginator
            showGridlines
            :rows="perPage"
            :totalRecords="meta.total"
            :first="(meta.current_page - 1) * meta.per_page"
            :rowsPerPageOptions="EMPLOYEE_PER_PAGE_OPTIONS"
            dataKey="id"
            :loading="loading"
            removableSort
            :sortField="sortField"
            :sortOrder="sortOrder"
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} records"
            @page="onPage"
            @sort="onSort">
            <template #loading>Loading employees. Please wait.</template>
            <template #empty>
                <div class="flex flex-col items-center gap-3 py-6">
                    <span v-if="listError" class="text-red-500">{{ listError }}</span>
                    <span v-else>{{ onlyArchived ? 'No archived records.' : 'No employees match the current filters.' }}</span>
                    <Button v-if="listError" icon="pi pi-refresh" label="Retry" @click="fetchList()" />
                </div>
            </template>
            <Column header="No" style="width: 3rem">
                <template #body="{ index }">{{ rowNumber(index) }}</template>
            </Column>
            <Column field="name" header="Name" sortable style="min-width: 14rem" />
            <Column field="nuptk" header="NUPTK" style="min-width: 10rem">
                <template #body="{ data }">{{ data.nuptk || '-' }}</template>
            </Column>
            <Column header="Status" style="min-width: 10rem">
                <template #body="{ data }">
                    <Tag v-if="data.employee_status" :value="data.employee_status.display_name ?? data.employee_status.name" severity="info" />
                    <span v-else>-</span>
                </template>
            </Column>
            <Column header="Employment" style="min-width: 10rem">
                <template #body="{ data }">{{ data.employment?.display_name ?? data.employment?.name ?? '-' }}</template>
            </Column>
            <Column v-if="isTeacherPage" header="Subject" style="min-width: 10rem">
                <template #body="{ data }">{{ data.subject?.display_name ?? data.subject?.name ?? '-' }}</template>
            </Column>
            <Column field="tmt_employee" header="Employment Start Date (TMT)" sortable style="min-width: 8rem" />
            <Column header="Account" style="min-width: 8rem">
                <template #body="{ data }">
                    <Tag :value="data.account?.linked ? 'Linked' : 'Not linked'" :severity="data.account?.linked ? 'success' : 'secondary'" />
                </template>
            </Column>
            <Column header="Action" :exportable="false" style="min-width: 12rem">
                <template #body="{ data }">
                    <Button icon="pi pi-eye" variant="outlined" rounded class="mr-2" v-tooltip.top="'View Detail'" @click="openDetail(data)" />
                    <template v-if="canManage">
                        <template v-if="!onlyArchived">
                            <Button icon="pi pi-pencil" variant="outlined" rounded class="mr-2" v-tooltip.top="'Edit'" @click="openEdit(data)" />
                            <Button
                                :icon="data.account?.linked ? 'pi pi-user-minus' : 'pi pi-user-plus'"
                                variant="outlined"
                                rounded
                                severity="info"
                                class="mr-2"
                                v-tooltip.top="data.account?.linked ? 'Unlink Account' : 'Link Account'"
                                @click="openAccount(data)" />
                            <Button icon="pi pi-inbox" variant="outlined" rounded severity="danger" v-tooltip.top="'Archive'" @click="openArchive(data)" />
                        </template>
                        <Button v-else icon="pi pi-replay" variant="outlined" rounded severity="success" v-tooltip.top="'Restore'" @click="restore(data)" />
                    </template>
                </template>
            </Column>
        </DataTable>
        <EmployeeFormDialog
            v-model:visible="formVisible"
            :employee="selectedEmployee"
            :fixed-type-id="fixedTypeId"
            :options="options"
            @saved="onSaved" />

        <EmployeeDetailDialog
            v-model:visible="detailVisible"
            :employee="selectedDetail"
            :can-manage="canManage"
            @edit="openEdit"
            @archive="openArchive"
            @restore="restore"
            @link="openAccount"
            @unlink="openAccount" />

        <EmployeeArchiveDialog
            v-model:visible="archiveVisible"
            :employee="selectedEmployee"
            @archived="onArchived"
            @conflict="onConflict" />

        <EmployeeAccountLinkDialog
            v-model:visible="accountVisible"
            :employee="selectedEmployee"
            @saved="onAccountSaved"
            @conflict="onConflict" />

        <EmployeeImportDialog v-model:visible="importVisible" @imported="onImported" />

        <EmployeeExportDialog v-model:visible="exportVisible" :filters="exportFilters" :entity-label="isTeacherPage ? 'Teachers' : 'Employees'" @downloaded="onExported" />
    </div>
</template>
