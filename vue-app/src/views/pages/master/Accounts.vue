<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import { FilterMatchMode, FilterOperator } from '@primevue/core/api';
import { useResourceManager } from '@/utils/useResourceManager';
import apiLogin from '@/apiLogin';
import { referenceExportFunction } from '@/utils/referenceLabels.mjs';

const exportReferenceCell = referenceExportFunction({ 'status_user.name': 'status-users' });

// ── Local refs (tidak ada di composable) ──────────────────────────────────
const { t } = useI18n();
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
    menuLabelKey: 'master.accounts.entity',
    menuLabelPluralKey: 'master.accounts.plural',
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
                        <Button :label="t('common.action.add')" icon="pi pi-plus" class="mr-2" @click="openNew" />
                        <Button :label="t('common.action.delete')" icon="pi pi-trash" severity="danger" variant="outlined" @click="confirmDeleteSelected" :disabled="!selectedManyDatas || !selectedManyDatas.length" />
                    </template>

                    <template #end>
                        <FileUpload mode="basic" accept="image/*" :maxFileSize="1000000" :label="t('master.common.import')" customUpload :chooseLabel="t('master.common.import')" class="mr-2" auto :chooseButtonProps="{ severity: 'secondary' }" />
                        <Button :label="t('master.common.export')" icon="pi pi-upload" severity="secondary" @click="exportCSV()" />
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
                    :currentPageReportTemplate="t('master.common.showing')"
                >
                    <template #header>
                        <div class="mb-3 flex flex-col md:flex-row gap-4">
                            <div class="flex flex-wrap gap-2 w-full">{{ t('master.dialog.totalCount', { entity: entityLabelPlural, count: manyDatas ? manyDatas.length : t('master.common.loading') }) }}</div>
                        </div>
                        <div class="flex flex-col md:flex-row gap-2 items-end">
                            <div class="flex flex-wrap gap-2">
                                <Select v-model="length_rows_pick" :options="length_rows" :placeholder="t('master.common.select')"> </Select>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button type="button" icon="pi pi-filter-slash" :label="t('master.common.clear')" variant="outlined" @click="clearFilter()" />
                            </div>
                            <div class="flex flex-wrap gap-2 w-full"></div>
                            <div class="flex flex-wrap gap-2 w-full"></div>
                            <div class="flex flex-wrap gap-2 w-full">
                                <IconField>
                                    <InputIcon>
                                        <i class="pi pi-search" />
                                    </InputIcon>
                                    <InputText v-model="filters['global'].value" :placeholder="t('master.common.search')" />
                                </IconField>
                            </div>
                        </div>
                    </template>
                    <template #empty> {{ t('master.dialog.empty', { entity: entityLabelPlural }) }} </template>
                    <template #loading> {{ t('master.dialog.loading', { entity: entityLabelPlural }) }} </template>
                    <Column selectionMode="multiple" style="width: 3rem" :exportable="false"></Column>
                    <Column :header="t('master.common.no')" sortable style="width: 3rem">
                        <template #body="slotProps">
                            {{ slotProps.index + 1 }}
                        </template>
                    </Column>
                    <Column field="name" :header="t('master.common.name')" exportHeader="Name" sortable filterField="name" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            {{ data.name }}
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" :placeholder="t('master.common.searchByName')" />
                        </template>
                    </Column>
                    <Column field="email" :header="t('master.accounts.email')" exportHeader="Email" sortable filterField="email" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            {{ data.email }}
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" :placeholder="t('master.common.search')" />
                        </template>
                    </Column>
                    <Column field="username" :header="t('master.accounts.username')" exportHeader="Username" sortable filterField="username" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            {{ data.username }}
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" :placeholder="t('master.common.search')" />
                        </template>
                    </Column>
                    <Column field="nik" :header="t('master.accounts.nik')" exportHeader="NIK" sortable filterField="nik" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            {{ data.nik }}
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" :placeholder="t('master.common.search')" />
                        </template>
                    </Column>
                    <Column field="type_user.name" :header="t('master.accounts.typeUser')" filterField="type_user.name" exportHeader="Type Users" :showFilterMatchModes="false" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            <Tag :value="data.type_user.name" :severity="getSeverity(data.type_user.name)" />
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <MultiSelect v-model="filterModel.value" @change="filterCallback()" :options="manyDatas_cat2" :placeholder="t('master.common.all')">
                                <template #option="slotProps">
                                    <div class="flex items-center gap-2">
                                        <Tag :value="slotProps.option" :severity="getSeverity(slotProps.option)" />
                                    </div>
                                </template>
                            </MultiSelect>
                        </template>
                    </Column>
                    <Column
                        field="status_user.name"
                        :header="t('master.accounts.accountStatus')"
                        filterField="status_user.name"
                        exportHeader="Account Status"
                        :showFilterMatchModes="false"
                        :filterMenuStyle="{ width: '14rem' }"
                        style="min-width: 12rem"
                    >
                        <template #body="{ data }">
                            <Tag :value="data.status_user?.display_name ?? data.status_user?.name" :severity="getSeverity(data.status_user.name)" />
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <MultiSelect v-model="filterModel.value" @change="filterCallback()" :options="manyDatas_3" optionLabel="display_name" optionValue="name" :placeholder="t('master.common.all')">
                                <template #option="slotProps">
                                    <div class="flex items-center gap-2">
                                        <Tag :value="slotProps.option.display_name ?? slotProps.option.name" :severity="getSeverity(slotProps.option.name)" />
                                    </div>
                                </template>
                            </MultiSelect>
                        </template>
                    </Column>

                    <Column :header="t('master.accounts.roles')" :exportable="false" style="min-width: 10rem">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-1">
                                <Tag v-for="role in data.roles || []" :key="role.id" :value="role.name" severity="info" />
                                <span v-if="!data.roles || !data.roles.length" class="text-surface-400">{{ t('master.accounts.noRoles') }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column :header="t('master.common.action')" :exportable="false" style="min-width: 10rem">
                        <template #body="slotProps">
                            <Button icon="pi pi-shield" variant="outlined" rounded class="mr-2" :title="t('master.accounts.assignRoles')" @click="openRolesDialog(slotProps.data)" />
                            <Button icon="pi pi-pencil" variant="outlined" rounded class="mr-2" @click="editSingleData(slotProps.data)" />
                            <Button icon="pi pi-trash" variant="outlined" rounded severity="danger" @click="confirmDeleteSingleData(slotProps.data)" />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </Fluid>

    <!-- Assign Roles Dialog (English) -->
    <Dialog v-model:visible="rolesDialog" :style="{ width: '32rem' }" :header="t('master.accounts.assignRoles')" :modal="true">
        <div class="flex flex-col gap-4">
            <div v-if="roleTargetUser" class="text-surface-700 dark:text-surface-200">
                {{ t('master.accounts.accountLabel', { name: roleTargetUser.name }) }}
            </div>
            <Message v-if="rolesError" severity="error" :closable="false">{{ rolesError }}</Message>
            <div v-if="rolesLoading">{{ t('master.accounts.loadingRoles') }}</div>
            <div v-else class="flex flex-col gap-2">
                <div v-for="role in allRoles" :key="role.id" class="flex items-center gap-2">
                    <Checkbox v-model="selectedRoleIds" :inputId="`role-${role.id}`" :value="role.id" />
                    <label :for="`role-${role.id}`" class="cursor-pointer">
                        {{ role.name }}
                    </label>
                </div>
                <div v-if="!allRoles.length" class="text-surface-400">{{ t('master.accounts.noRolesAvailable') }}</div>
            </div>
        </div>
        <template #footer>
            <Button :label="t('common.action.cancel')" icon="pi pi-times" text @click="rolesDialog = false" />
            <Button :label="t('common.action.save')" icon="pi pi-check" :loading="rolesSaving" @click="saveRoles" />
        </template>
    </Dialog>

    <!-- Confirm Delete Dialog -->
    <Dialog v-model:visible="deleteDialog" :style="{ width: '450px' }" :header="t('master.dialog.confirm')" :modal="true">
        <div class="flex items-center gap-4">
            <i class="pi pi-exclamation-triangle !text-3xl" />
            <span v-if="singleData"
                >Are you sure you want to delete <b>{{ singleData.name }}</b
                >?</span
            >
        </div>
        <template #footer>
            <Button :label="t('master.common.noOption')" icon="pi pi-times" text @click="deleteDialog = false" severity="secondary" variant="text" />
            <Button :label="t('master.common.yes')" icon="pi pi-check" @click="deleteSingleData" severity="danger" />
        </template>
    </Dialog>
    <!-- Confirm Delete Selected Dialog -->
    <Dialog v-model:visible="deletesDialog" :style="{ width: '450px' }" :header="t('master.dialog.confirm')" :modal="true">
        <div class="flex items-center gap-4">
            <i class="pi pi-exclamation-triangle !text-3xl" />
            <span v-if="selectedManyDatas">{{ t('master.dialog.confirmDeleteSelected', { count: selectedManyDatas.length, entity: entityLabelPlural }) }}</span>
        </div>
        <template #footer>
            <Button :label="t('master.common.noOption')" icon="pi pi-times" text @click="deletesDialog = false" severity="secondary" variant="text" />
            <Button :label="t('master.common.yes')" icon="pi pi-check" text @click="deleteSelectedManyDatas" severity="danger" />
        </template>
    </Dialog>
    <!-- Confirm Edit Dialog -->
    <Dialog v-model:visible="EditOrNewDialog" :style="{ width: '450px' }" :header="t('master.dialog.entityDetails', { entity: entityLabel })" :modal="true">
        <div class="flex flex-col gap-6">
            <div>
                <label for="name" class="block font-bold mb-3">{{ t('master.common.name') }}</label>
                <InputText id="name" v-model="singleData.name" required="true" autofocus :invalid="submitted && !singleData.name" fluid />
                <small v-if="submitted && !singleData.name" class="text-red-500">{{ t('master.validation.nameRequired') }}</small>
                <small v-else-if="singleData.name && singleData.name.trim().length > 0 && singleData.name.trim().length < 3" class="text-yellow-500">{{ t('master.validation.nameMinLength', { min: 3 }) }}</small>

                <label for="email" class="block font-bold mb-3 mt-3">{{ t('master.accounts.email') }}</label>
                <InputText id="email" v-model="singleData.email" required="true" autofocus :invalid="submitted && !singleData.email" fluid />
                <small v-if="submitted && !singleData.email" class="text-red-500">{{ t('master.validation.emailRequired') }}</small>
                <small v-else-if="singleData.email && singleData.email === previousData.email" class="text-blue-500">{{ t('master.validation.emailNoChanges') }}</small>
                <small v-else-if="isCat1Used" class="text-red-500">{{ t('master.validation.emailTaken') }}</small>
                <small v-else-if="singleData.email && singleData.email.trim().length > 3" class="text-green-500">{{ t('master.validation.emailAvailable') }} </small>

                <label for="username" class="block font-bold mb-3 mt-3">{{ t('master.accounts.username') }}</label>
                <InputText id="username" v-model="singleData.username" required="true" autofocus :invalid="submitted && !singleData.username" fluid />
                <small v-if="submitted && !singleData.username" class="text-red-500">{{ t('master.validation.usernameRequired') }}</small>
                <small v-else-if="singleData.username && singleData.username.trim().length > 0 && singleData.username.trim().length < 3" class="text-yellow-500">{{ t('master.validation.usernameMinLength', { min: 3 }) }}</small>
                <small v-else-if="singleData.username && singleData.username === previousData.username" class="text-blue-500">{{ t('master.validation.usernameNoChanges') }}</small>
                <small v-else-if="isCat2Used" class="text-red-500">{{ t('master.validation.usernameTaken') }}</small>
                <small v-else-if="singleData.username && singleData.username.trim().length >= 3" class="text-green-500">{{ t('master.validation.usernameAvailable') }} </small>

                <label for="nik" class="block font-bold mb-3 mt-3">{{ t('master.accounts.nik') }}</label>
                <InputText id="nik" v-model="singleData.nik" required="true" autofocus :invalid="submitted && !singleData.nik" fluid />
                <small v-if="submitted && !singleData.nik" class="text-red-500">{{ t('master.validation.nikRequired') }}</small>
                <small v-else-if="singleData.nik && singleData.nik.trim().length > 0 && singleData.nik.trim().length !== 16" class="text-yellow-500">{{ t('master.validation.nikExactDigits') }}</small>
                <small v-else-if="singleData.nik && singleData.nik === previousData.nik" class="text-blue-500">{{ t('master.validation.nikNoChanges') }}</small>
                <small v-else-if="isCat3Used" class="text-red-500">{{ t('master.validation.nikTaken') }}</small>
                <small v-else-if="singleData.nik && singleData.nik.trim().length === 16" class="text-green-500">{{ t('master.validation.nikValid') }}</small>

                <div v-if="Object.keys(previousData).length === 0">
                    <label for="password" class="block font-bold mb-3 mt-3">{{ t('master.accounts.password') }}</label>
                    <InputText id="password" type="password" v-model="singleData.password" required="true" autofocus :invalid="submitted && !singleData.password" fluid />
                    <small v-if="submitted && !singleData.password" class="text-red-500">{{ t('master.validation.passwordRequired') }}</small>
                    <small v-else-if="singleData.password && singleData.password.trim().length > 0 && singleData.password.trim().length < 12" class="text-yellow-500">{{ t('master.validation.passwordMinLength', { min: 12 }) }}</small>
                </div>
                <div v-else>
                    <label for="password_new" class="block font-bold mb-3 mt-3">{{ t('master.validation.passwordNew') }}</label>
                    <InputText id="password_new" type="password" v-model="singleData.password_new" autofocus :invalid="submitted && singleData.password_new && singleData.password_new.length < 12" fluid />
                    <small v-if="!singleData.password_new" class="text-yellow-500">{{ t('master.validation.passwordLeaveBlank') }}</small>
                    <small v-else-if="singleData.password_new && singleData.password_new.trim().length > 0 && singleData.password_new.trim().length < 12" class="text-yellow-500">{{ t('master.validation.passwordMinLength', { min: 12 }) }}</small>
                </div>

                <label for="type_user" class="block font-bold mb-3 mt-3">{{ t('master.accounts.typeUser') }}</label>
                <AutoComplete id="type_user" v-model="singleData.type_user" required="true" optionLabel="name" forceSelection :suggestions="filtered_Cat1" @complete="search_Cat1" fluid />
                <small v-if="submitted && !singleData.type_user" class="text-red-500">{{ t('master.validation.typeUserRequired') }}</small>

                <label for="status_user" class="block font-bold mb-3 mt-3">{{ t('master.accounts.accountStatus') }}</label>
                <AutoComplete id="status_user" v-model="singleData.status_user" required="true" optionLabel="display_name" forceSelection :suggestions="filtered_Cat2" @complete="search_Cat2" fluid />
                <small v-if="submitted && !singleData.status_user" class="text-red-500">{{ t('master.validation.statusUserRequired') }}</small>
            </div>
        </div>

        <template #footer>
            <Button :label="t('common.action.cancel')" icon="pi pi-times" text @click="hideDialog" />
            <Button v-if="!isCat1Used && !isCat2Used && !isCat3Used && validFieldCount > 0" :label="t('common.action.save')" icon="pi pi-check" @click="saveSingleData" />
        </template>
    </Dialog>
    <!-- Loading Dialog -->
    <Dialog v-model:visible="WaitingDialog" modal :header="t('master.dialog.processing')">
        <div class="text-center justify-content-center align-items-center">
            <i class="pi pi-spin pi-spinner" style="font-size: 2rem; color: green"></i>
            <p class="text-success mt-3" style="font-size: 1.1rem; font-weight: 500">{{ t('master.dialog.pleaseWait') }}</p>
        </div>
    </Dialog>
    <!-- Error Dialog -->
    <Dialog v-model:visible="ErrorDialog" modal :header="titleError" style="width: 25rem; color: red">
        <div style="color: black">
            {{ fieldError }}
        </div>
    </Dialog>
</template>
