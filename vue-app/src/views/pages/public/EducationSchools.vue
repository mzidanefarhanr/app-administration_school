<script setup>
import { ref, onMounted } from 'vue';
import Button from 'primevue/button';
import { FilterMatchMode, FilterOperator } from '@primevue/core/api';
import { useResourceManager } from '@/utils/useResourceManager';

// ── Local refs (tidak ada di composable) ──────────────────────────────────
const dt              = ref();
const filters         = ref();
const length_rows_pick = ref(10);
const length_rows      = ref([10, 25, 50, 100]);

// ── Composable — semua state & method CRUD diambil dari sini ──────────────
const {
    manyDatas,
    manyDatas_cat2,
    manyDatas_cat3,
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
    search_Cat2,
} = useResourceManager({
    endpoint:        'education-schools',
    endpoints:       [
        'education-levels',
        'districts',
    ],
    menuLabel:       'Education School',
    menuLabelPlural: 'Education Schools',
    fieldLabels: {
        name:                   'Name',
        npsn:                   'NPSN',
        'education_level.name':  'Education Level',
        status_education:       'Status Education',
        address:                'address',
        'district.name':        'District',
    },
    uniqueFields:     ['name', 'npsn'],
    uniqueMinLengths: [3, 8],
    fkFields: [
        { fk: 'education_level',   fk_id: 'education_level_id'   },
        { fk: 'district', fk_id: 'district_id'  },
    ],
});

// ── Filter ────────────────────────────────────────────────────────────────
const clearFilter = () => initFilters();

const initFilters = () => {
    filters.value = {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
        'name': { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        'npsn': { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        'education_level.name': { value: null, matchMode: FilterMatchMode.IN },
        'status_education': { value: null, matchMode: FilterMatchMode.STARTS_WITH },
        'address': { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        'district.name': { value: null, matchMode: FilterMatchMode.STARTS_WITH },
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
}

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
    manyDatas_cat2.value = [
            "Negeri",
            "Swasta"
        ];
});
</script>

<template>
    <Fluid>
        <div class="bg-surface-0 dark:bg-surface-950 px-6 py-8 md:px-12 lg:px-20">
            <ul class="list-none p-0 m-0 flex items-center font-medium mb-5">
                <li>
                    <a class="text-surface-500 dark:text-surface-300 no-underline leading-normal cursor-pointer">Dashboard</a>
                </li>
                <li class="px-2">
                    <i class="pi pi-angle-right text-surface-500 dark:text-surface-300 !text-sm !leading-normal" />
                </li>
                <li>
                    <span class="text-surface-900 dark:text-surface-0 leading-normal">{{ entityLabelPlural }}</span>
                </li>
            </ul>

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
                :globalFilterFields="['name']"
                paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                currentPageReportTemplate="Showing {first} to {last} of {totalRecords}"
                >
                    <template #header>
                        <div class="mb-3 flex flex-col md:flex-row gap-4">
                            <div class="flex flex-wrap gap-2 w-full">
                                Total {{ entityLabelPlural }}: {{ manyDatas ? manyDatas.length : "Loading" }}
                            </div>
                        </div>
                        <div class="flex flex-col md:flex-row gap-2 items-end">
                            <div class="flex flex-wrap gap-2">
                                <Select v-model="length_rows_pick" :options="length_rows" placeholder="Select">
                                </Select>
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
                    <Column field="npsn" header="NPSN" exportHeader="NPSN" sortable filterField="npsn" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            {{ data.npsn }}
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" placeholder="Search by NPSN" />
                        </template>
                    </Column>
                    <Column field="education_level.name" header="Education Level" filterField="education_level.name" exportHeader="Education Level" :showFilterMatchModes="false" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            <Tag :value="data.education_level.name" :severity="getSeverity(data.education_level.name)" />
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
                    <Column field="status_education" header="Status Education" filterField="status_education" exportHeader="Status Education" :showFilterMatchModes="false" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            <Tag :value="data.status_education" :severity="getSeverity(data.status_education)" />
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <Select v-model="filterModel.value" @change="filterCallback()" :options="status_educations" placeholder="Select One" style="min-width: 12rem" :showClear="true">
                                <template #option="slotProps">
                                    <Tag :value="slotProps.option" :severity="getSeverity(slotProps.option)" />
                                </template>
                            </Select>
                        </template>
                    </Column>
                    <Column field="address" header="Addrress" exportHeader="Addrress" sortable filterField="address" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            {{ data.address }}
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" placeholder="Search by Addrress" />
                        </template>
                    </Column>
                    <Column field="district.name" header="District" exportHeader="District" sortable filterField="district.name" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            {{ data.district.name }}
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" placeholder="Search by District" />
                        </template>
                    </Column>

                    <Column header="Action" :exportable="false" style="min-width: 8rem">
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
    <Dialog v-model:visible="EditOrNewDialog" :style="{ width: '450px' }" :header="entityLabel+' Details'" :modal="true">
        <div class="flex flex-col gap-6">
            <div>
                <label for="name" class="block font-bold mb-3">Name</label>
                <InputText id="name" v-model="singleData.name" required="true" autofocus :invalid="submitted && !singleData.name" fluid />
                <small v-if="submitted && !singleData.name" class="text-red-500">Name is required.</small>
                <small v-else-if="singleData.name && singleData.name.trim().length > 0 && singleData.name.trim().length <= 3" class="text-yellow-500">Name must be at least 4 characters.</small>
                <small v-else-if="singleData.name && singleData.name === previousData.name" class="text-blue-500">Current Name (no changes).</small>
                <small v-else-if="isCat1Used" class="text-red-500">This name is already taken.</small>
                <small v-else-if="singleData.name && singleData.name.trim().length > 3" class="text-green-500">Name is available!</small>

                <label for="npsn" class="block font-bold mb-3 mt-3">NPSN</label>
                <InputText id="npsn" v-model="singleData.npsn" required="true" autofocus :invalid="submitted && !singleData.npsn" fluid />
                <small v-if="submitted && !singleData.npsn" class="text-red-500">NPSN is required.</small>
                <small v-else-if="singleData.npsn && singleData.npsn.trim().length > 0 && singleData.npsn.trim().length <= 7" class="text-yellow-500">NPSN must be at least 8 numbers.</small>
                <small v-else-if="singleData.npsn && singleData.npsn === previousData.npsn" class="text-blue-500">NPSN is used.</small>
                <small v-else-if="isCat4Used" class="text-red-500">Current NPSN (no changes).</small>
                <small v-else-if="singleData.npsn && singleData.npsn.trim().length > 7" class="text-green-500">NPSN is available! </small>

                <label for="education_level" class="block font-bold mb-3 mt-3">Education Level</label>
                <AutoComplete id="education_level" v-model="singleData.education_level" :suggestions="filtered_Cat1" required="true" optionLabel="name" forceSelection placeholder="Search for Education Level" dropdown display="chip" @complete="search_Cat1" fluid />
                <small v-if="submitted && !singleData.education_level" class="text-red-500">Education Level is required.</small>

                <label for="status_education" class="block font-bold mb-3">Status Education</label>
                <Select id="status_education" v-model="singleData.status_education" :options="status_educations" placeholder="Select a Status Education" :showClear="true" fluid></Select>
                <small v-if="submitted && !singleData.status_education" class="text-red-500">Status Education is required.</small>

                <label for="address" class="block font-bold mb-3">Addrress</label>
                <Textarea id="address" v-model="singleData.address" required="true" autofocus fluid />

                <label for="district" class="block font-bold mb-3 mt-3">District</label>
                <AutoComplete id="district" v-model="singleData.district" :suggestions="filtered_Cat2" required="true" optionLabel="name" forceSelection placeholder="Search for District" @complete="search_Cat2" fluid />
                <small v-if="submitted && !singleData.district" class="text-red-500">District is required.</small>

            </div>
        </div>

        <template #footer>
            <Button label="Cancel" icon="pi pi-times" text @click="hideDialog" />
            <Button v-if="!isCat1Used && validFieldCount > 0" label="Save" icon="pi pi-check" @click="saveSingleData" />
        </template>
    </Dialog>
    <!-- Loading Dialog -->
    <Dialog v-model:visible="WaitingDialog" modal header="Processing Request">
        <div class="text-center justify-content-center align-items-center">
            <i class="pi pi-spin pi-spinner" style="font-size: 2rem; color: green"></i>
            <p class="text-success mt-3" style="font-size: 1.1rem; font-weight: 500;">
            Please wait, your data is being processed...
        </p>
        </div>
    </Dialog>
    <!-- Error Dialog -->
    <Dialog v-model:visible="ErrorDialog" modal :header="titleError" style=" width: 25rem; color: red;">
        <div style="color: black;">
            {{ fieldError }}
        </div>
    </Dialog>

</template>
