<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Select from 'primevue/select';
import { useToast } from 'primevue/usetoast';
import { authStore } from '@/stores/authStore';
import apiLogin from '@/apiLogin';
import EmployeeFormDialog from './EmployeeFormDialog.vue';
import EmployeeDetailDialog from './EmployeeDetailDialog.vue';
import EmployeeArchiveDialog from './EmployeeArchiveDialog.vue';
import EmployeeAccountLinkDialog from './EmployeeAccountLinkDialog.vue';
import EmployeeImportDialog from './EmployeeImportDialog.vue';
import EmployeeExportDialog from './EmployeeExportDialog.vue';
import EmployeeBatchArchiveDialog from './EmployeeBatchArchiveDialog.vue';
import { buildEmployeeListParams, resolveEmployeeTypeId, firstValidationMessage, EMPLOYEE_PER_PAGE_OPTIONS } from './employeeDirectoryParams.mjs';

const props = defineProps({
    employeeTypeName: { type: String, required: true }
});

const { t } = useI18n();
const toast = useToast();
const route = useRoute();
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
const perPage = ref(10);
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
    maritalStatuses: []
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

const generatingAccountId = ref(null);
const generatedAccountDialog = ref(false);
const generatedAccountData = ref(null);
const copiedPassword = ref(false);

const deleteAccountDialog = ref(false);
const targetEmployeeForDeleteAccount = ref(null);
const deletingAccount = ref(false);

const selectedEmployees = ref([]);
const batchArchiveVisible = ref(false);
const restoringBatch = ref(false);

const openBatchArchive = () => {
    if (!selectedEmployees.value?.length) return;
    batchArchiveVisible.value = true;
};

const onBatchArchived = (count) => {
    notify('success', `${count} data ${entityName.value.toLowerCase()} berhasil diarsipkan.`);
    selectedEmployees.value = [];
    fetchList();
};

const confirmBatchRestore = async () => {
    if (restoringBatch.value || !selectedEmployees.value?.length) return;
    restoringBatch.value = true;
    try {
        const items = selectedEmployees.value.map((e) => ({
            id: e.id,
            version: e.version
        }));

        await apiLogin.post('/api/employees/batch-restore', { items });
        notify('success', `${items.length} data ${entityName.value.toLowerCase()} berhasil dipulihkan.`);
        selectedEmployees.value = [];
        fetchList();
    } catch (error) {
        notify('error', firstValidationMessage(error.response) ?? 'Gagal memulihkan data.');
    } finally {
        restoringBatch.value = false;
    }
};

const isTeacherPage = computed(() => props.employeeTypeName === 'Pendidik');
const pageTitle = computed(() => (isTeacherPage.value ? t('employees.directory.titleTeachers') : t('employees.directory.titleEmployees')));
const entityName = computed(() => (isTeacherPage.value ? t('employees.entity.teacher') : t('employees.entity.employee')));
const pageReportTemplate = computed(() => t('employees.directory.pageReport', { first: '{first}', last: '{last}', totalRecords: '{totalRecords}' }));
const noReferenceMessage = () => t('employees.directory.errors.noReference', { title: pageTitle.value });

const loadReference = async () => {
    const endpoints = {
        employeeTypes: '/api/employee-types',
        employeeStatuses: '/api/employee-statuses',
        employments: '/api/employments',
        subjects: '/api/subjects',
        genders: '/api/genders',
        religions: '/api/religions',
        districts: '/api/districts',
        maritalStatuses: '/api/marital-statuses'
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
            onlyArchived: onlyArchived.value
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
        listError.value = firstValidationMessage(error.response) ?? t('employees.directory.errors.loadList');
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
    selectedEmployees.value = [];
    onlyArchived.value = !onlyArchived.value;
    page.value = 1;
    fetchList();
};

const resetFilters = () => {
    selectedEmployees.value = [];
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
        summary: summary ?? (severity === 'success' ? t('employees.directory.toast.success') : t('employees.directory.toast.notice')),
        detail,
        life: 4000
    });
};

const toId = (rowOrId) => (typeof rowOrId === 'object' ? rowOrId.id : rowOrId);

const fetchDetail = async (row) => {
    try {
        const response = await apiLogin.get(`/api/employees/${toId(row)}`);
        return response.data?.data ?? null;
    } catch (error) {
        notify('error', firstValidationMessage(error.response) ?? t('employees.directory.errors.loadDetail'));
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

// Tautan dari halaman Data Quality membuka dialog detail yang sudah ada.
const openEmployeeFromQuery = async () => {
    const raw = Array.isArray(route.query.openEmployee) ? route.query.openEmployee[0] : route.query.openEmployee;
    const id = Number(raw);
    if (!Number.isInteger(id) || id <= 0) return;

    await openDetail({ id });
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
    notify('success', t('employees.directory.toast.saved', { entity: entityName.value }));
    detailVisible.value = false;
    fetchList();
};

const onArchived = () => {
    notify('success', t('employees.directory.toast.archived', { entity: entityName.value }));
    fetchList();
};

const onAccountSaved = () => {
    notify('success', t('employees.directory.toast.accountUpdated'));
    detailVisible.value = false;
    fetchList();
};

const executeGenerateAccount = async (row) => {
    generatingAccountId.value = row.id;
    try {
        const response = await apiLogin.post(`/api/employees/${toId(row)}/generate-account`);
        generatedAccountData.value = response.data?.data;
        copiedPassword.value = false;
        generatedAccountDialog.value = true;
        detailVisible.value = false;
        notify('success', `Akun untuk ${row.name} berhasil dibuat.`);
        fetchList();
    } catch (error) {
        notify('error', error.response?.data?.message || 'Gagal membuat akun untuk pegawai.');
    } finally {
        generatingAccountId.value = null;
    }
};

const copyGeneratedPassword = async () => {
    if (!generatedAccountData.value?.initial_password) return;
    try {
        await navigator.clipboard.writeText(generatedAccountData.value.initial_password);
        copiedPassword.value = true;
        setTimeout(() => {
            copiedPassword.value = false;
        }, 2000);
    } catch (err) {
        console.error('Clipboard copy failed', err);
    }
};

const openDeleteAccountConfirm = (row) => {
    if (row.account?.is_admin) {
        notify('warn', 'Akun Administrator terlindungi dan tidak dapat dihapus melalui data pegawai.');
        return;
    }
    targetEmployeeForDeleteAccount.value = row;
    detailVisible.value = false;
    deleteAccountDialog.value = true;
};

const executeDeleteAccount = async () => {
    if (!targetEmployeeForDeleteAccount.value) return;
    deletingAccount.value = true;
    try {
        await apiLogin.delete(`/api/employees/${toId(targetEmployeeForDeleteAccount.value)}/delete-account`);
        notify('success', `Akun pengguna untuk ${targetEmployeeForDeleteAccount.value.name} berhasil dihapus.`);
        deleteAccountDialog.value = false;
        targetEmployeeForDeleteAccount.value = null;
        fetchList();
    } catch (error) {
        notify('error', error.response?.data?.message || 'Gagal menghapus akun pengguna.');
    } finally {
        deletingAccount.value = false;
    }
};

const onConflict = (currentVersion) => {
    notify('warn', t('employees.directory.toast.conflict', { version: currentVersion ? t('employees.directory.toast.conflictVersion', { version: currentVersion }) : '' }), t('employees.directory.toast.conflictTitle'));
    fetchList();
};

const restore = async (row) => {
    if (!window.confirm(t('employees.directory.confirmRestore', { name: row.name }))) return;

    try {
        await apiLogin.post(`/api/employees/${toId(row)}/restore`, { version: row.version });
        notify('success', t('employees.directory.toast.restored', { entity: entityName.value }));
        fetchList();
    } catch (error) {
        if (error.response?.status === 409) {
            onConflict(error.response?.data?.current_version ?? null);
        } else {
            notify('error', firstValidationMessage(error.response) ?? t('employees.directory.errors.restore'));
        }
    }
};

const exportFilters = computed(() => ({
    search: search.value,
    employeeTypeId: fixedTypeId.value,
    employeeStatusId: statusFilter.value,
    employmentId: employmentFilter.value,
    subjectId: isTeacherPage.value ? subjectFilter.value : null
}));

const openExport = () => {
    if (fixedTypeId.value === null) return;

    exportVisible.value = true;
};

const onImported = (batch) => {
    notify('success', t('employees.directory.toast.imported', { count: batch?.committed_rows ?? 0, entity: entityName.value.toLowerCase() }));
    onlyArchived.value = false;
    page.value = 1;
    fetchList();
};

const onExported = ({ columns }) => {
    notify('success', t('employees.directory.toast.exported', { count: columns }));
};

onMounted(async () => {
    await loadReference();
    if (fixedTypeId.value === null) {
        listError.value = noReferenceMessage();
        return;
    }
    await fetchList();
    await openEmployeeFromQuery();
});

// Routes share this component, so a direct switch between Teachers and
// Employees must re-resolve the fixed type instead of reusing stale rows.
watch(
    () => props.employeeTypeName,
    (name) => {
        onlyArchived.value = false;
        search.value = '';
        statusFilter.value = null;
        employmentFilter.value = null;
        subjectFilter.value = null;
        page.value = 1;
        fixedTypeId.value = resolveEmployeeTypeId(options.value.employeeTypes, name);
        listError.value = fixedTypeId.value === null ? noReferenceMessage() : '';

        if (fixedTypeId.value !== null) fetchList();
    }
);
</script>

<template>
    <div class="card flex flex-col gap-4 w-full">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h2 class="text-2xl font-semibold">{{ pageTitle }}</h2>
                <p class="text-sm text-surface-500">
                    {{ onlyArchived ? t('employees.directory.subtitleArchived') : isTeacherPage ? t('employees.directory.subtitleTeachers') : t('employees.directory.subtitleStaff') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button v-if="canExport" icon="pi pi-external-link" :label="t('employees.directory.actions.exportCsv')" :disabled="fixedTypeId === null" @click="openExport" />
                <Button v-if="canImport && !onlyArchived" icon="pi pi-upload" :label="t('employees.directory.actions.importCsv')" severity="secondary" variant="outlined" :disabled="fixedTypeId === null" @click="importVisible = true" />
                <Button v-if="canManage && !onlyArchived" icon="pi pi-plus" :label="isTeacherPage ? t('employees.directory.actions.newTeacher') : t('employees.directory.actions.newEmployee')" @click="openCreate" />
                <Button
                    v-if="canManage"
                    :icon="onlyArchived ? 'pi pi-users' : 'pi pi-inbox'"
                    :label="onlyArchived ? t('employees.directory.actions.activeRecords') : t('employees.directory.actions.archived')"
                    severity="secondary"
                    variant="outlined"
                    @click="toggleArchived"
                />
                <Button icon="pi pi-filter-slash" :label="t('employees.directory.actions.clear')" severity="secondary" variant="outlined" @click="resetFilters" />
            </div>
        </div>

        <div class="flex flex-col xl:flex-row gap-3 xl:items-center">
            <IconField class="w-full xl:w-72">
                <InputIcon><i class="pi pi-search" /></InputIcon>
                <InputText v-model="search" :placeholder="t('employees.directory.searchPlaceholder')" class="w-full" @input="onSearchInput" />
            </IconField>
            <Select v-model="statusFilter" :options="options.employeeStatuses" optionLabel="display_name" optionValue="id" :placeholder="t('employees.directory.filters.status')" showClear class="w-full xl:w-52" @change="onFilterChange" />
            <Select v-model="employmentFilter" :options="options.employments" optionLabel="display_name" optionValue="id" :placeholder="t('employees.directory.filters.employment')" showClear class="w-full xl:w-52" @change="onFilterChange" />
            <Select
                v-if="isTeacherPage"
                v-model="subjectFilter"
                :options="options.subjects"
                optionLabel="display_name"
                optionValue="id"
                :placeholder="t('employees.directory.filters.subject')"
                showClear
                filter
                class="w-full xl:w-52"
                @change="onFilterChange"
            />
        </div>

        <div v-if="selectedEmployees.length > 0" class="flex flex-wrap items-center justify-between gap-3 p-3 rounded-lg bg-primary-50 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800 transition-all">
            <div class="flex items-center gap-2 text-sm font-semibold text-primary-900 dark:text-primary-100">
                <i class="pi pi-check-square text-base text-primary-600 dark:text-primary-400" />
                <span>{{ selectedEmployees.length }} {{ entityName.toLowerCase() }} dipilih</span>
            </div>
            <div class="flex items-center gap-2">
                <Button
                    v-if="canManage && !onlyArchived"
                    icon="pi pi-box"
                    :label="`Arsipkan (${selectedEmployees.length})`"
                    severity="warn"
                    size="small"
                    @click="openBatchArchive"
                />
                <Button
                    v-if="canManage && onlyArchived"
                    icon="pi pi-undo"
                    :label="`Pulihkan (${selectedEmployees.length})`"
                    severity="success"
                    size="small"
                    :loading="restoringBatch"
                    @click="confirmBatchRestore"
                />
                <Button
                    icon="pi pi-times"
                    label="Batal Pilih"
                    severity="secondary"
                    variant="text"
                    size="small"
                    @click="selectedEmployees = []"
                />
            </div>
        </div>

        <DataTable
            v-model:selection="selectedEmployees"
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
            :currentPageReportTemplate="pageReportTemplate"
            @page="onPage"
            @sort="onSort"
        >
            <template #loading>{{ t('employees.directory.loading') }}</template>
            <template #empty>
                <div class="flex flex-col items-center gap-3 py-6">
                    <span v-if="listError" class="text-red-500">{{ listError }}</span>
                    <span v-else>{{ onlyArchived ? t('employees.directory.empty.archived') : t('employees.directory.empty.filtered') }}</span>
                    <Button v-if="listError" icon="pi pi-refresh" :label="t('common.action.retry')" @click="fetchList()" />
                </div>
            </template>
            <Column selectionMode="multiple" headerStyle="width: 3rem" :exportable="false" />
            <Column :header="t('employees.directory.columns.no')" style="width: 3rem">
                <template #body="{ index }">{{ rowNumber(index) }}</template>
            </Column>
            <Column field="name" :header="t('employees.directory.columns.name')" sortable style="min-width: 14rem" />
            <Column field="nuptk" :header="t('employees.directory.columns.nuptk')" style="min-width: 10rem">
                <template #body="{ data }">{{ data.nuptk || '-' }}</template>
            </Column>
            <Column :header="t('employees.directory.columns.status')" style="min-width: 10rem">
                <template #body="{ data }">
                    <Tag v-if="data.employee_status" :value="data.employee_status.display_name ?? data.employee_status.name" severity="info" />
                    <span v-else>-</span>
                </template>
            </Column>
            <Column :header="t('employees.directory.columns.employment')" style="min-width: 10rem">
                <template #body="{ data }">{{ data.employment?.display_name ?? data.employment?.name ?? '-' }}</template>
            </Column>
            <Column v-if="isTeacherPage" :header="t('employees.directory.columns.subject')" style="min-width: 10rem">
                <template #body="{ data }">{{ data.subject?.display_name ?? data.subject?.name ?? '-' }}</template>
            </Column>
            <Column field="tmt_employee" :header="t('employees.directory.columns.tmt')" sortable style="min-width: 8rem" />
            <Column :header="t('employees.directory.columns.account')" style="min-width: 9rem">
                <template #body="{ data }">
                    <Tag v-if="data.account?.is_admin" value="Admin" severity="info" v-tooltip.top="'Akun Administrator Sistem'" />
                    <Tag v-else-if="data.account?.linked" :value="data.account?.username ? `@${data.account.username}` : t('employees.directory.account.linked')" severity="success" />
                    <Tag v-else :value="t('employees.directory.account.notLinked')" severity="secondary" />
                </template>
            </Column>
            <Column :header="t('employees.directory.columns.action')" :exportable="false" style="min-width: 13rem">
                <template #body="{ data }">
                    <Button icon="pi pi-eye" variant="outlined" rounded class="mr-2" v-tooltip.top="t('employees.directory.tooltips.view')" @click="openDetail(data)" />
                    <template v-if="canManage">
                        <template v-if="!onlyArchived">
                            <Button icon="pi pi-pencil" variant="outlined" rounded class="mr-2" v-tooltip.top="t('common.action.edit')" @click="openEdit(data)" />

                            <!-- Generate Akun (jika belum punya akun) -->
                            <Button
                                v-if="!data.account?.linked"
                                icon="pi pi-user-plus"
                                variant="outlined"
                                rounded
                                severity="success"
                                class="mr-2"
                                v-tooltip.top="t('employees.directory.tooltips.generateAccount')"
                                :loading="generatingAccountId === data.id"
                                @click="executeGenerateAccount(data)"
                            />

                            <!-- Hapus Akun / Terkunci (jika sudah punya akun) -->
                            <Button
                                v-else-if="data.account?.is_admin"
                                icon="pi pi-lock"
                                variant="outlined"
                                rounded
                                severity="secondary"
                                class="mr-2 opacity-60 cursor-not-allowed"
                                v-tooltip.top="t('employees.directory.tooltips.adminAccountLocked')"
                                disabled
                            />
                            <Button
                                v-else
                                icon="pi pi-user-minus"
                                variant="outlined"
                                rounded
                                severity="danger"
                                class="mr-2"
                                v-tooltip.top="t('employees.directory.tooltips.deleteAccount')"
                                @click="openDeleteAccountConfirm(data)"
                            />

                            <Button icon="pi pi-inbox" variant="outlined" rounded severity="danger" v-tooltip.top="t('employees.directory.tooltips.archive')" @click="openArchive(data)" />
                        </template>
                        <Button v-else icon="pi pi-replay" variant="outlined" rounded severity="success" v-tooltip.top="t('employees.directory.tooltips.restore')" @click="restore(data)" />
                    </template>
                </template>
            </Column>
        </DataTable>

        <EmployeeFormDialog v-model:visible="formVisible" :employee="selectedEmployee" :fixed-type-id="fixedTypeId" :options="options" @saved="onSaved" />

        <EmployeeDetailDialog
            v-model:visible="detailVisible"
            :employee="selectedDetail"
            :can-manage="canManage"
            @edit="openEdit"
            @archive="openArchive"
            @restore="restore"
            @generate-account="executeGenerateAccount"
            @delete-account="openDeleteAccountConfirm"
        />

        <EmployeeArchiveDialog v-model:visible="archiveVisible" :employee="selectedEmployee" @archived="onArchived" @conflict="onConflict" />
        <EmployeeBatchArchiveDialog v-model:visible="batchArchiveVisible" :employees="selectedEmployees" :is-teacher="isTeacherPage" @archived="onBatchArchived" />

        <EmployeeAccountLinkDialog v-model:visible="accountVisible" :employee="selectedEmployee" @saved="onAccountSaved" @conflict="onConflict" />

        <EmployeeImportDialog v-model:visible="importVisible" :employee-type-name="props.employeeTypeName" @imported="onImported" />

        <EmployeeExportDialog v-model:visible="exportVisible" :filters="exportFilters" :entity-label="isTeacherPage ? 'Teachers' : 'Employees'" @downloaded="onExported" />

        <!-- Dialog Hasil Generate Akun -->
        <Dialog v-model:visible="generatedAccountDialog" modal header="Akun Pengguna Berhasil Dibuat" :style="{ width: '32rem' }">
            <div class="flex flex-col gap-4">
                <div class="p-3 bg-green-50 dark:bg-green-950/40 border border-green-200 dark:border-green-800 rounded text-sm text-green-800 dark:text-green-200">
                    Akun pengguna berhasil dibuat dan dihubungkan ke data pegawai. Silakan salin kredensial berikut untuk diberikan kepada pegawai yang bersangkutan:
                </div>

                <div class="space-y-3">
                    <div>
                        <span class="text-xs font-semibold text-surface-500 uppercase block mb-1">Nama</span>
                        <div class="p-2.5 bg-surface-50 dark:bg-surface-800 rounded border border-surface-200 dark:border-surface-700 text-sm font-medium">
                            {{ generatedAccountData?.name }}
                        </div>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-surface-500 uppercase block mb-1">Nama Pengguna (Username)</span>
                        <div class="p-2.5 bg-surface-50 dark:bg-surface-800 rounded border border-surface-200 dark:border-surface-700 text-sm font-mono font-semibold">
                            {{ generatedAccountData?.username }}
                        </div>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-surface-500 uppercase block mb-1">Email</span>
                        <div class="p-2.5 bg-surface-50 dark:bg-surface-800 rounded border border-surface-200 dark:border-surface-700 text-sm">
                            {{ generatedAccountData?.email }}
                        </div>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-surface-500 uppercase block mb-1">Kata Sandi Sementara</span>
                        <div class="flex gap-2">
                            <div class="p-2.5 bg-surface-50 dark:bg-surface-800 rounded border border-surface-200 dark:border-surface-700 text-sm font-mono font-bold flex-1 text-primary-600">
                                {{ generatedAccountData?.initial_password }}
                            </div>
                            <Button
                                :icon="copiedPassword ? 'pi pi-check' : 'pi pi-copy'"
                                :severity="copiedPassword ? 'success' : 'secondary'"
                                variant="outlined"
                                v-tooltip.top="copiedPassword ? 'Tersalin!' : 'Salin kata sandi'"
                                @click="copyGeneratedPassword"
                            />
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <Button label="Tutup" severity="primary" @click="generatedAccountDialog = false" />
            </template>
        </Dialog>

        <!-- Dialog Konfirmasi Hapus Akun -->
        <Dialog v-model:visible="deleteAccountDialog" modal header="Konfirmasi Hapus Akun Pengguna" :style="{ width: '28rem' }">
            <div class="flex items-start gap-3">
                <i class="pi pi-exclamation-triangle text-red-500 text-3xl mt-1"></i>
                <div>
                    <p class="text-sm text-surface-800 dark:text-surface-100 font-medium m-0">
                        Apakah Anda yakin ingin menghapus akun pengguna untuk pegawai <b>{{ targetEmployeeForDeleteAccount?.name }}</b>?
                    </p>
                    <p class="text-xs text-surface-500 m-0 mt-2">
                        Akun pengguna akan dihapus dan pegawai tidak lagi memiliki akses login. Data kepegawaian akan tetap ada tanpa akun.
                    </p>
                </div>
            </div>
            <template #footer>
                <Button label="Batal" severity="secondary" variant="outlined" :disabled="deletingAccount" @click="deleteAccountDialog = false" />
                <Button label="Hapus Akun" severity="danger" icon="pi pi-trash" :loading="deletingAccount" @click="executeDeleteAccount" />
            </template>
        </Dialog>
    </div>
</template>
