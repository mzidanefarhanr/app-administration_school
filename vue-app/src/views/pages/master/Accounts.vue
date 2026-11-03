<script setup>
import { ref, onMounted } from 'vue';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import { FilterMatchMode, FilterOperator } from '@primevue/core/api';
import { useResourceManager } from '@/utils/useResourceManager';
import apiLogin from '@/apiLogin';
import { referenceExportFunction } from '@/utils/referenceLabels.mjs';

const exportReferenceCell = referenceExportFunction({ 'status_user.name': 'status-users' });

// ── Local refs (tidak ada di composable) ──────────────────────────────────
const dt = ref();
const filters = ref();
const length_rows_pick = ref(10);
const length_rows = ref([10, 25, 50, 100]);

// ── Role assignment (English UI) ──────────────────────────────────────────
const rolesDialog = ref(false);
const rolesLoading = ref(false);
const rolesSaving = ref(false);
const allRoles = ref([]);
const roleTargetUser = ref(null);
const selectedRoleIds = ref([]);
const rolesError = ref('');

const openRolesDialog = async (user) => {
    roleTargetUser.value = user;
    rolesError.value = '';
    selectedRoleIds.value = (user.roles || []).map((r) => r.id);
    rolesDialog.value = true;
    rolesLoading.value = true;
    try {
        const response = await apiLogin.get('/api/roles');
        allRoles.value = response.data.data;
    } catch (error) {
        rolesError.value = error.response?.data?.message || 'Failed to load roles.';
    } finally {
        rolesLoading.value = false;
    }
};

const saveRoles = async () => {
    if (!roleTargetUser.value) return;
    rolesSaving.value = true;
    rolesError.value = '';
    const userId = roleTargetUser.value.id;
    const currentIds = (roleTargetUser.value.roles || []).map((r) => r.id);
    const toAdd = selectedRoleIds.value.filter((id) => !currentIds.includes(id));
    const toRemove = currentIds.filter((id) => !selectedRoleIds.value.includes(id));
    try {
        for (const roleId of toAdd) {
            await apiLogin.post(`/api/users/${userId}/roles`, { role_id: roleId });
        }
        for (const roleId of toRemove) {
            await apiLogin.delete(`/api/users/${userId}/roles/${roleId}`);
        }
        rolesDialog.value = false;
        fetchDataUserRole();
    } catch (error) {
        rolesError.value = error.response?.data?.message || 'Failed to update roles.';
    } finally {
        rolesSaving.value = false;
    }
};

// ── Composable — semua state & method CRUD diambil dari sini ──────────────
const {
    manyDatas,
    manyDatas_cat2,
    manyDatas_3,
    singleData,
    previousData,
    selectedManyDatas,
    loading,
    submitted,
    entityLabel,
    entityLabelPlural,
    EditOrNewDialog,
    deleteDialog,
    deletesDialog,
    WaitingDialog,
    ErrorDialog,
    titleError,
    fieldError,
    filtered_Cat1,
    filtered_Cat2,
    isCat1Used,
    isCat2Used,
    isCat3Used,
    validFieldCount,
    fetchDataUserRole,
    openNew,
    hideDialog,
    editSingleData,
    saveSingleData,
    confirmDeleteSingleData,
    deleteSingleData,
    confirmDeleteSelected,
    deleteSelectedManyDatas,
    search_Cat1,
    search_Cat2
} = useResourceManager({
    endpoint: 'users',
    endpoints: ['type-users', 'status-users'],
    menuLabel: 'Account',
    menuLabelPlural: 'Accounts',
    fieldLabels: {
        name: 'Name',
        email: 'Email',
        username: 'Username',
        nik: 'NIK',
        'type_user.name': 'Type User',
        'status_user.name': 'Status User'
    },
    uniqueFields: ['email', 'username', 'nik'],
    uniqueMinLengths: [3, 3, 16],
    requiredFields: ['name'],
    fkFields: [
        { fk: 'type_user', fk_id: 'type_user_id' },
        { fk: 'status_user', fk_id: 'status_user_id' }
    ]
});

// ── Filter ────────────────────────────────────────────────────────────────
const clearFilter = () => initFilters();

const initFilters = () => {
    filters.value = {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
        name: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        email: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        username: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        nik: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        'type_user.name': { value: null, matchMode: FilterMatchMode.IN },
        'status_user.name': { value: null, matchMode: FilterMatchMode.IN }
    };
};

initFilters();

// ── getSeverity ───────────────────────────────────────────────────────────
const getSeverity = (status) => {
    switch (status) {
        case 'unqualified':
            return 'danger';
        case 'Negeri':
            return 'success';
        case 'Swasta':
            return 'warn';
        case 'Laki-Laki':
            return 'success';
        case 'Perempuan':
            return 'info';
        case 'Aktif':
            return 'success';
        case 'Lulus':
            return 'info';
        case 'Mutasi Keluar':
            return 'danger';
        case 'Drop Out':
            return 'danger';
        case 'Siswa Baru':
            return 'success';
        case 'Mutasi Masuk':
            return 'info';
        case 'renewal':
            return null;
    }
};

// ── Export CSV ────────────────────────────────────────────────────────────
const exportCSV = () => {
    if (dt.value) {
        dt.value.exportCSV();
    } else {
        console.error('DataTable reference is not available.');
    }
};

// ── Mount ─────────────────────────────────────────────────────────────────
onMounted(() => {
    fetchDataUserRole();
    if (ErrorDialog.value === true) {
        fetchDataUserRole();
    }
});
</script>

<template>
    <Fluid>
        <div class="bg-surface-0 dark:bg-surface-950 px-6 py-8 md:px-12 lg:px-20">
            <div class="flex items-start flex-col md:justify-between md:flex-row">
                <div>
                    <div class="font-bold text-3xl text-surface-900 dark:text-surface-0 mb-4">{{ entityLabelPlural }}</div>
                </div>
            </div>

            <div class="card flex flex-col gap-4">
                <Toolbar class="mb-3">
                    <template #start>
                        <Button label="New" icon="pi pi-plus" class="mr-2" @click="openNew" />
                        <Button label="Delete" icon="pi pi-trash" severity="danger" variant="outlined" @click="confirmDeleteSelected" :disabled="!selectedManyDatas || !selectedManyDatas.length" />
                    </template>

                    <template #end>
                        <FileUpload mode="basic" accept="image/*" :maxFileSize="1000000" label="Import" customUpload chooseLabel="Import" class="mr-2" auto :chooseButtonProps="{ severity: 'secondary' }" />
                        <Button label="Export" icon="pi pi-upload" severity="secondary" @click="exportCSV()" />
                    </template>
                </Toolbar>

                <DataTable
                    ref="dt"
                    v-model:selection="selectedManyDatas"
                    v-model:filters="filters"
                    :value="manyDatas"
                    paginator
                    showGridlines
                    :rows="length_rows_pick"
                    dataKey="id"
                    filterDisplay="menu"
                    :loading="loading"
                    :exportFunction="exportReferenceCell"
                    :globalFilterFields="['name', 'email', 'username', 'nik', 'status_user.display_name']"
                    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                    currentPageReportTemplate="Showing {first} to {last} of {totalRecords}"
                >
                    <template #header>
                        <div class="mb-3 flex flex-col md:flex-row gap-4">
                            <div class="flex flex-wrap gap-2 w-full">Total {{ entityLabelPlural }}: {{ manyDatas ? manyDatas.length : 'Loading' }}</div>
                        </div>
                        <div class="flex flex-col md:flex-row gap-2 items-end">
                            <div class="flex flex-wrap gap-2">
                                <Select v-model="length_rows_pick" :options="length_rows" placeholder="Select"> </Select>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button type="button" icon="pi pi-filter-slash" label="Clear" variant="outlined" @click="clearFilter()" />
                            </div>
                            <div class="flex flex-wrap gap-2 w-full"></div>
                            <div class="flex flex-wrap gap-2 w-full"></div>
                            <div class="flex flex-wrap gap-2 w-full">
                                <IconField>
                                    <InputIcon>
                                        <i class="pi pi-search" />
                                    </InputIcon>
                                    <InputText v-model="filters['global'].value" placeholder="Search" />
                                </IconField>
                            </div>
                        </div>
                    </template>
                    <template #empty> No {{ entityLabelPlural }} found. </template>
                    <template #loading> Loading {{ entityLabelPlural }} data. Please wait. </template>
                    <Column selectionMode="multiple" style="width: 3rem" :exportable="false"></Column>
                    <Column header="No" sortable style="width: 3rem">
                        <template #body="slotProps">
                            {{ slotProps.index + 1 }}
                        </template>
                    </Column>
                    <Column field="name" header="Name" exportHeader="Name" sortable filterField="name" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            {{ data.name }}
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" placeholder="Search by Name" />
                        </template>
                    </Column>
                    <Column field="email" header="Email" exportHeader="Email" sortable filterField="email" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            {{ data.email }}
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" placeholder="Search by EMAIL" />
                        </template>
                    </Column>
                    <Column field="username" header="Username" exportHeader="Username" sortable filterField="username" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            {{ data.username }}
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" placeholder="Search by Username" />
                        </template>
                    </Column>
                    <Column field="nik" header="NIK" exportHeader="NIK" sortable filterField="nik" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            {{ data.nik }}
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" placeholder="Search by NIK" />
                        </template>
                    </Column>
                    <Column field="type_user.name" header="Type Users" filterField="type_user.name" exportHeader="Type Users" :showFilterMatchModes="false" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            <Tag :value="data.type_user.name" :severity="getSeverity(data.type_user.name)" />
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <MultiSelect v-model="filterModel.value" @change="filterCallback()" :options="manyDatas_cat2" placeholder="Any">
                                <template #option="slotProps">
                                    <div class="flex items-center gap-2">
                                        <Tag :value="slotProps.option" :severity="getSeverity(slotProps.option)" />
                                    </div>
                                </template>
                            </MultiSelect>
                        </template>
                    </Column>
                    <Column field="status_user.name" header="Account Status" filterField="status_user.name" exportHeader="Account Status" :showFilterMatchModes="false" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            <Tag :value="data.status_user?.display_name ?? data.status_user?.name" :severity="getSeverity(data.status_user.name)" />
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <MultiSelect v-model="filterModel.value" @change="filterCallback()" :options="manyDatas_3" optionLabel="display_name" optionValue="name" placeholder="Any">
                                <template #option="slotProps">
                                    <div class="flex items-center gap-2">
                                        <Tag :value="slotProps.option.display_name ?? slotProps.option.name" :severity="getSeverity(slotProps.option.name)" />
                                    </div>
                                </template>
                            </MultiSelect>
                        </template>
                    </Column>

                    <Column header="Roles" :exportable="false" style="min-width: 10rem">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-1">
                                <Tag v-for="role in data.roles || []" :key="role.id" :value="role.name" severity="info" />
                                <span v-if="!data.roles || !data.roles.length" class="text-surface-400">No roles</span>
                            </div>
                        </template>
                    </Column>

                    <Column header="Action" :exportable="false" style="min-width: 10rem">
                        <template #body="slotProps">
                            <Button icon="pi pi-shield" variant="outlined" rounded class="mr-2" title="Assign Roles" @click="openRolesDialog(slotProps.data)" />
                            <Button icon="pi pi-pencil" variant="outlined" rounded class="mr-2" @click="editSingleData(slotProps.data)" />
                            <Button icon="pi pi-trash" variant="outlined" rounded severity="danger" @click="confirmDeleteSingleData(slotProps.data)" />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </Fluid>

    <!-- Assign Roles Dialog (English) -->
    <Dialog v-model:visible="rolesDialog" :style="{ width: '32rem' }" header="Assign Roles" :modal="true">
        <div class="flex flex-col gap-4">
            <div v-if="roleTargetUser" class="text-surface-700 dark:text-surface-200">
                Account: <span class="font-semibold">{{ roleTargetUser.name }}</span>
            </div>
            <Message v-if="rolesError" severity="error" :closable="false">{{ rolesError }}</Message>
            <div v-if="rolesLoading">Loading roles...</div>
            <div v-else class="flex flex-col gap-2">
                <div v-for="role in allRoles" :key="role.id" class="flex items-center gap-2">
                    <Checkbox v-model="selectedRoleIds" :inputId="`role-${role.id}`" :value="role.id" />
                    <label :for="`role-${role.id}`" class="cursor-pointer">
                        {{ role.name }}
                    </label>
                </div>
                <div v-if="!allRoles.length" class="text-surface-400">No roles available.</div>
            </div>
        </div>
        <template #footer>
            <Button label="Cancel" icon="pi pi-times" text @click="rolesDialog = false" />
            <Button label="Save" icon="pi pi-check" :loading="rolesSaving" @click="saveRoles" />
        </template>
    </Dialog>

    <!-- Confirm Delete Dialog -->
    <Dialog v-model:visible="deleteDialog" :style="{ width: '450px' }" header="Confirm" :modal="true">
        <div class="flex items-center gap-4">
            <i class="pi pi-exclamation-triangle !text-3xl" />
            <span v-if="singleData"
                >Are you sure you want to delete <b>{{ singleData.name }}</b
                >?</span
            >
        </div>
        <template #footer>
            <Button label="No" icon="pi pi-times" text @click="deleteDialog = false" severity="secondary" variant="text" />
            <Button label="Yes" icon="pi pi-check" @click="deleteSingleData" severity="danger" />
        </template>
    </Dialog>
    <!-- Confirm Delete Selected Dialog -->
    <Dialog v-model:visible="deletesDialog" :style="{ width: '450px' }" header="Confirm" :modal="true">
        <div class="flex items-center gap-4">
            <i class="pi pi-exclamation-triangle !text-3xl" />
            <span v-if="selectedManyDatas">Are you sure you want to delete {{ selectedManyDatas.length }} data selected {{ entityLabelPlural }}?</span>
        </div>
        <template #footer>
            <Button label="No" icon="pi pi-times" text @click="deletesDialog = false" severity="secondary" variant="text" />
            <Button label="Yes" icon="pi pi-check" text @click="deleteSelectedManyDatas" severity="danger" />
        </template>
    </Dialog>
    <!-- Confirm Edit Dialog -->
    <Dialog v-model:visible="EditOrNewDialog" :style="{ width: '450px' }" :header="entityLabel + ' Details'" :modal="true">
        <div class="flex flex-col gap-6">
            <div>
                <label for="name" class="block font-bold mb-3">Name</label>
                <InputText id="name" v-model="singleData.name" required="true" autofocus :invalid="submitted && !singleData.name" fluid />
                <small v-if="submitted && !singleData.name" class="text-red-500">Name is required.</small>
                <small v-else-if="singleData.name && singleData.name.trim().length > 0 && singleData.name.trim().length < 3" class="text-yellow-500">Name must be at least 3 characters.</small>

                <label for="email" class="block font-bold mb-3 mt-3">Email</label>
                <InputText id="email" v-model="singleData.email" required="true" autofocus :invalid="submitted && !singleData.email" fluid />
                <small v-if="submitted && !singleData.email" class="text-red-500">Email is required.</small>
                <small v-else-if="singleData.email && singleData.email === previousData.email" class="text-blue-500">Current Email (no changes).</small>
                <small v-else-if="isCat1Used" class="text-red-500">Email is already used.</small>
                <small v-else-if="singleData.email && singleData.email.trim().length > 3" class="text-green-500">Email is available! </small>

                <label for="username" class="block font-bold mb-3 mt-3">Username</label>
                <InputText id="username" v-model="singleData.username" required="true" autofocus :invalid="submitted && !singleData.username" fluid />
                <small v-if="submitted && !singleData.username" class="text-red-500">Username is required.</small>
                <small v-else-if="singleData.username && singleData.username.trim().length > 0 && singleData.username.trim().length < 3" class="text-yellow-500">Username must be at least 3 characters.</small>
                <small v-else-if="singleData.username && singleData.username === previousData.username" class="text-blue-500">Current Username (no changes).</small>
                <small v-else-if="isCat2Used" class="text-red-500">Username is already taken.</small>
                <small v-else-if="singleData.username && singleData.username.trim().length >= 3" class="text-green-500">Username is available! </small>

                <label for="nik" class="block font-bold mb-3 mt-3">NIK</label>
                <InputText id="nik" v-model="singleData.nik" required="true" autofocus :invalid="submitted && !singleData.nik" fluid />
                <small v-if="submitted && !singleData.nik" class="text-red-500">NIK is required.</small>
                <small v-else-if="singleData.nik && singleData.nik.trim().length > 0 && singleData.nik.trim().length !== 16" class="text-yellow-500">NIK must be exactly 16 digits.</small>
                <small v-else-if="singleData.nik && singleData.nik === previousData.nik" class="text-blue-500">Current NIK (no changes).</small>
                <small v-else-if="isCat3Used" class="text-red-500">NIK is already registered.</small>
                <small v-else-if="singleData.nik && singleData.nik.trim().length === 16" class="text-green-500">NIK is valid!</small>

                <div v-if="Object.keys(previousData).length === 0">
                    <label for="password" class="block font-bold mb-3 mt-3">Password</label>
                    <InputText id="password" type="password" v-model="singleData.password" required="true" autofocus :invalid="submitted && !singleData.password" fluid />
                    <small v-if="submitted && !singleData.password" class="text-red-500">Password is required.</small>
                    <small v-else-if="singleData.password && singleData.password.trim().length > 0 && singleData.password.trim().length < 12" class="text-yellow-500">Password must be at least 12 characters.</small>
                </div>
                <div v-else>
                    <label for="password_new" class="block font-bold mb-3 mt-3">New Password (optional)</label>
                    <InputText id="password_new" type="password" v-model="singleData.password_new" autofocus :invalid="submitted && singleData.password_new && singleData.password_new.length < 12" fluid />
                    <small v-if="!singleData.password_new" class="text-yellow-500">Leave blank to keep existing password.</small>
                    <small v-else-if="singleData.password_new && singleData.password_new.trim().length > 0 && singleData.password_new.trim().length < 12" class="text-yellow-500">Password must be at least 12 characters.</small>
                </div>

                <label for="type_user" class="block font-bold mb-3 mt-3">Type User</label>
                <AutoComplete id="type_user" v-model="singleData.type_user" required="true" optionLabel="name" forceSelection :suggestions="filtered_Cat1" @complete="search_Cat1" fluid />
                <small v-if="submitted && !singleData.type_user" class="text-red-500">Type User is required.</small>

                <label for="status_user" class="block font-bold mb-3 mt-3">Status User</label>
                <AutoComplete id="status_user" v-model="singleData.status_user" required="true" optionLabel="display_name" forceSelection :suggestions="filtered_Cat2" @complete="search_Cat2" fluid />
                <small v-if="submitted && !singleData.status_user" class="text-red-500">Status User is required.</small>
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" icon="pi pi-times" text @click="hideDialog" />
            <Button v-if="!isCat1Used && !isCat2Used && !isCat3Used && validFieldCount > 0" label="Save" icon="pi pi-check" @click="saveSingleData" />
        </template>
    </Dialog>
    <!-- Loading Dialog -->
    <Dialog v-model:visible="WaitingDialog" modal header="Processing Request">
        <div class="text-center justify-content-center align-items-center">
            <i class="pi pi-spin pi-spinner" style="font-size: 2rem; color: green"></i>
            <p class="text-success mt-3" style="font-size: 1.1rem; font-weight: 500">Please wait, your data is being processed...</p>
        </div>
    </Dialog>
    <!-- Error Dialog -->
    <Dialog v-model:visible="ErrorDialog" modal :header="titleError" style="width: 25rem; color: red">
        <div style="color: black">
            {{ fieldError }}
        </div>
    </Dialog>
</template>
