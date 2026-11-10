<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import { FilterMatchMode, FilterOperator } from '@primevue/core/api';
import { useResourceManager } from '@/utils/useResourceManager';
import { referenceLabel, referenceExportFunction } from '@/utils/referenceLabels.mjs';

const exportReferenceCell = referenceExportFunction({ 'education_level.name': 'education-levels', status_education: 'school-status' });

const { t } = useI18n();

// ── Local refs (tidak ada di composable) ──────────────────────────────────
const dt = ref();
const filters = ref();
const length_rows_pick = ref(10);
const length_rows = ref([10, 25, 50, 100]);

// ── Composable — semua state & method CRUD diambil dari sini ──────────────
const {
    manyDatas,
    manyDatas_2,
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
    isCat4Used,
    validFieldCount,
    status_educations,
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
    endpoint: 'education-schools',
    endpoints: ['education-levels', 'districts'],
    menuLabelKey: 'master.educationSchools.entity',
    menuLabelPluralKey: 'master.educationSchools.plural',
    fieldLabels: {
        name: 'Name',
        npsn: 'NPSN',
        'education_level.name': 'Education Level',
        status_education: 'School Status',
        address: 'address',
        'district.name': 'District'
    },
    uniqueFields: ['name', 'npsn'],
    uniqueMinLengths: [3, 8],
    fkFields: [
        { fk: 'education_level', fk_id: 'education_level_id' },
        { fk: 'district', fk_id: 'district_id' }
    ]
});

// ── Filter ────────────────────────────────────────────────────────────────
const clearFilter = () => initFilters();

const initFilters = () => {
    filters.value = {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
        name: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        npsn: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        'education_level.name': { value: null, matchMode: FilterMatchMode.IN },
        status_education: { value: null, matchMode: FilterMatchMode.STARTS_WITH },
        address: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        'district.name': { value: null, matchMode: FilterMatchMode.STARTS_WITH }
    };
};

initFilters();

const getSeverity = (status) => {
    switch (status) {
        case 'unqualified':
            return 'danger';

        case 'Negeri':
            return 'success';

        case 'Swasta':
            return 'warn';

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
                    v-model:rows="length_rows_pick"
                    :rowsPerPageOptions="length_rows"
                    dataKey="id"
                    filterDisplay="menu"
                    :loading="loading"
                    :exportFunction="exportReferenceCell"
                    :globalFilterFields="['name', 'education_level.display_name', 'status_education_display_name']"
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
                    <Column field="npsn" :header="t('master.educationSchools.npsn')" exportHeader="NPSN" sortable filterField="npsn" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            {{ data.npsn }}
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" :placeholder="t('master.educationSchools.searchByNpsn')" />
                        </template>
                    </Column>
                    <Column
                        field="education_level.name"
                        :header="t('master.educationSchools.educationLevel')"
                        filterField="education_level.name"
                        exportHeader="Education Level"
                        :showFilterMatchModes="false"
                        :filterMenuStyle="{ width: '14rem' }"
                        style="min-width: 12rem"
                    >
                        <template #body="{ data }">
                            <Tag :value="data.education_level?.display_name ?? data.education_level?.name" :severity="getSeverity(data.education_level.name)" />
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <MultiSelect v-model="filterModel.value" @change="filterCallback()" :options="manyDatas_2" optionLabel="display_name" optionValue="name" :placeholder="t('master.educationSchools.any')">
                                <template #option="slotProps">
                                    <div class="flex items-center gap-2">
                                        <Tag :value="slotProps.option.display_name ?? slotProps.option.name" :severity="getSeverity(slotProps.option.name)" />
                                    </div>
                                </template>
                            </MultiSelect>
                        </template>
                    </Column>
                    <Column
                        field="status_education"
                        :header="t('master.educationSchools.schoolStatus')"
                        filterField="status_education"
                        exportHeader="School Status"
                        :showFilterMatchModes="false"
                        :filterMenuStyle="{ width: '14rem' }"
                        style="min-width: 12rem"
                    >
                        <template #body="{ data }">
                            <Tag :value="referenceLabel('school-status', data.status_education)" :severity="getSeverity(data.status_education)" />
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <Select v-model="filterModel.value" @change="filterCallback()" :options="status_educations" optionLabel="display_name" optionValue="name" :placeholder="t('master.common.select')" style="min-width: 12rem" :showClear="true">
                                <template #option="slotProps">
                                    <Tag :value="slotProps.option.display_name" :severity="getSeverity(slotProps.option.name)" />
                                </template>
                            </Select>
                        </template>
                    </Column>
                    <Column field="address" :header="t('master.educationSchools.address')" exportHeader="Address" sortable filterField="address" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            {{ data.address }}
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" :placeholder="t('master.educationSchools.searchByAddress')" />
                        </template>
                    </Column>
                    <Column field="district.name" :header="t('master.educationSchools.district')" exportHeader="District" sortable filterField="district.name" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            {{ data.district.name }}
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" :placeholder="t('master.educationSchools.searchByDistrict')" />
                        </template>
                    </Column>

                    <Column :header="t('master.common.action')" :exportable="false" style="min-width: 8rem">
                        <template #body="slotProps">
                            <Button icon="pi pi-pencil" variant="outlined" rounded class="mr-2" @click="editSingleData(slotProps.data)" />
                            <Button icon="pi pi-trash" variant="outlined" rounded severity="danger" @click="confirmDeleteSingleData(slotProps.data)" />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </Fluid>

    <!-- Confirm Delete Dialog -->
    <Dialog v-model:visible="deleteDialog" :style="{ width: '450px' }" :header="t('master.dialog.confirm')" :modal="true">
        <div class="flex items-center gap-4">
            <i class="pi pi-exclamation-triangle !text-3xl" />
            <span v-if="singleData">{{ t('master.dialog.confirmDelete', { name: singleData.name }) }}</span>
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
                <small v-else-if="singleData.name && singleData.name.trim().length > 0 && singleData.name.trim().length <= 3" class="text-yellow-500">{{ t('master.validation.nameMinLength', { min: 4 }) }}</small>
                <small v-else-if="singleData.name && singleData.name === previousData.name" class="text-blue-500">{{ t('master.validation.nameNoChanges') }}</small>
                <small v-else-if="isCat1Used" class="text-red-500">{{ t('master.validation.nameTaken') }}</small>
                <small v-else-if="singleData.name && singleData.name.trim().length > 3" class="text-green-500">{{ t('master.validation.nameAvailable') }}</small>

                <label for="npsn" class="block font-bold mb-3 mt-3">{{ t('master.educationSchools.npsn') }}</label>
                <InputText id="npsn" v-model="singleData.npsn" required="true" autofocus :invalid="submitted && !singleData.npsn" fluid />
                <small v-if="submitted && !singleData.npsn" class="text-red-500">{{ t('master.validation.npsnRequired') }}</small>
                <small v-else-if="singleData.npsn && singleData.npsn.trim().length > 0 && singleData.npsn.trim().length <= 7" class="text-yellow-500">{{ t('master.validation.npsnMinDigits') }}</small>
                <small v-else-if="singleData.npsn && singleData.npsn === previousData.npsn" class="text-blue-500">{{ t('master.validation.npsnTaken') }}</small>
                <small v-else-if="isCat4Used" class="text-red-500">{{ t('master.validation.npsnNoChanges') }}</small>
                <small v-else-if="singleData.npsn && singleData.npsn.trim().length > 7" class="text-green-500">{{ t('master.validation.npsnAvailable') }} </small>

                <label for="education_level" class="block font-bold mb-3 mt-3">{{ t('master.educationSchools.educationLevel') }}</label>
                <AutoComplete
                    id="education_level"
                    v-model="singleData.education_level"
                    :suggestions="filtered_Cat1"
                    required="true"
                    optionLabel="display_name"
                    forceSelection
                    :placeholder="t('master.educationSchools.searchEducationLevel')"
                    dropdown
                    display="chip"
                    @complete="search_Cat1"
                    fluid
                />
                <small v-if="submitted && !singleData.education_level" class="text-red-500">{{ t('master.validation.educationLevelRequired') }}</small>

                <label for="status_education" class="block font-bold mb-3">{{ t('master.educationSchools.schoolStatus') }}</label>
                <Select id="status_education" v-model="singleData.status_education" :options="status_educations" optionLabel="display_name" optionValue="name" :placeholder="t('master.educationSchools.selectStatus')" :showClear="true" fluid></Select>
                <small v-if="submitted && !singleData.status_education" class="text-red-500">{{ t('master.validation.schoolStatusRequired') }}</small>

                <label for="address" class="block font-bold mb-3">{{ t('master.educationSchools.address') }}</label>
                <Textarea id="address" v-model="singleData.address" required="true" autofocus fluid />

                <label for="district" class="block font-bold mb-3 mt-3">{{ t('master.educationSchools.district') }}</label>
                <AutoComplete id="district" v-model="singleData.district" :suggestions="filtered_Cat2" required="true" optionLabel="name" forceSelection :placeholder="t('master.educationSchools.searchDistrict')" @complete="search_Cat2" fluid />
                <small v-if="submitted && !singleData.district" class="text-red-500">{{ t('master.validation.districtRequired') }}</small>
            </div>
        </div>

        <template #footer>
            <Button :label="t('common.action.cancel')" icon="pi pi-times" text @click="hideDialog" />
            <Button v-if="!isCat1Used && validFieldCount > 0" :label="t('common.action.save')" icon="pi pi-check" @click="saveSingleData" />
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
