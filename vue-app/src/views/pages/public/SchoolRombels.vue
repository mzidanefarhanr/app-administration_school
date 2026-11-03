<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import { authStore } from '@/stores/authStore';
import apiLogin from '@/apiLogin';

const toast = useToast();
const store = authStore();
const canManage = computed(() => store.can('students.manage'));

const rows = ref([]);
const years = ref([]);
const levels = ref([]);
const majors = ref([]);
const activeSchoolYearId = ref(null);
const activeYearName = ref(null);
const activeSemesterName = ref(null);

const loading = ref(true);
const listError = ref('');
const optionsError = ref('');
const search = ref('');
const teacherSearch = ref('');
const yearFilter = ref(null);
const levelFilter = ref(null);
const majorFilter = ref(null);
const rowsPerPage = ref(10);
const rowsPerPageOptions = [10, 25, 50, 100];

const classDialog = ref(false);
const classEditing = ref(false);
const classSaving = ref(false);
const classSubmitted = ref(false);
const classErrors = ref({});
const classMessage = ref('');
const classForm = ref({});
const editorHasHistory = ref(false);
const selectedTeacher = ref(null);
const legacyTeacherName = ref('');
const teacherSuggestions = ref([]);
const teacherLoading = ref(false);
const teacherOptionsError = ref('');
let teacherTimer = null;

const conflictDialog = ref(false);
const conflictMessage = ref('');
const conflictReloadAction = ref(null);
const reloadingConflict = ref(false);

const deleteDialog = ref(false);
const deleteTarget = ref(null);
const deleteSaving = ref(false);
const deleteError = ref('');

const mergeOptions = (base, extra) => {
    const map = new Map();
    [...base, ...extra].forEach((item) => {
        if (item?.id != null && !map.has(item.id)) map.set(item.id, item);
    });
    return [...map.values()].sort((a, b) => String(a.name ?? '').localeCompare(String(b.name ?? '')));
};

const levelOptions = computed(() =>
    mergeOptions(
        levels.value,
        rows.value
            .map((row) => row.school_level)
            .filter(Boolean)
            .map((level) => ({ id: level.id, name: level.name }))
    )
);

const majorOptions = computed(() =>
    mergeOptions(
        majors.value,
        rows.value
            .map((row) => row.student_major)
            .filter(Boolean)
            .map((major) => ({ id: major.id, name: major.name }))
    )
);

const yearOptions = computed(() =>
    mergeOptions(
        years.value,
        rows.value
            .map((row) => row.school_year)
            .filter(Boolean)
            .map((year) => ({
                id: year.id,
                name: year.name,
                is_locked: year.is_locked
            }))
    )
);

const unlockedYears = computed(() => yearOptions.value.filter((year) => !year.is_locked));

const filteredRows = computed(() => {
    const query = search.value.trim().toLowerCase();
    const teacherQuery = teacherSearch.value.trim().toLowerCase();

    return rows.value.filter((row) => {
        if (levelFilter.value && row.school_level_id !== levelFilter.value) return false;
        if (majorFilter.value && row.student_major_id !== majorFilter.value) return false;

        const teacherName = (row.class_teach_employee?.name ?? row.class_teach?.name ?? '').toLowerCase();
        if (teacherQuery && !teacherName.includes(teacherQuery)) return false;

        if (!query) return true;
        const haystack = [row.name, row.school_year?.name, row.school_level?.name, row.student_major?.name, row.class_teach_employee?.name, row.class_teach?.name].filter(Boolean).join(' ').toLowerCase();

        return haystack.includes(query);
    });
});

const activePeriodLabel = computed(() => {
    if (!activeSchoolYearId.value) return null;
    const name = yearOptions.value.find((year) => year.id === activeSchoolYearId.value)?.name ?? activeYearName.value ?? 'Active year';
    return activeSemesterName.value ? `${name} · ${activeSemesterName.value}` : name;
});

const selectedYear = computed(() => yearOptions.value.find((year) => year.id === classForm.value.school_year_id) ?? null);
const selectedYearLocked = computed(() => selectedYear.value?.is_locked === true);
const canSubmitClass = computed(() => !classSaving.value && !selectedYearLocked.value);

const notify = (severity, detail, summary = 'Classes') => {
    toast.add({ severity, summary, detail, life: 5000 });
};

const validationErrors = (error) => {
    const data = error.response?.data ?? {};
    if (data.errors && typeof data.errors === 'object') return data.errors;
    return Object.fromEntries(Object.entries(data).filter(([, value]) => Array.isArray(value)));
};

const errorMessage = (error, fallback) => error.response?.data?.message ?? fallback;

const fieldError = (errors, field) => errors?.[field]?.[0] ?? '';

const loadReference = async () => {
    const results = await Promise.allSettled([apiLogin.get('/api/school-years'), apiLogin.get('/api/school-levels'), apiLogin.get('/api/student-majors'), apiLogin.get('/api/academic/period')]);

    const [yearsResult, levelsResult, majorsResult, periodResult] = results;

    if (yearsResult.status === 'fulfilled') {
        years.value = yearsResult.value.data?.data ?? [];
    }
    if (levelsResult.status === 'fulfilled') {
        levels.value = levelsResult.value.data?.data ?? [];
    }
    if (majorsResult.status === 'fulfilled') {
        majors.value = majorsResult.value.data?.data ?? [];
    }
    if (periodResult.status === 'fulfilled') {
        const active = periodResult.value.data?.data?.active;
        activeSchoolYearId.value = active?.school_year?.id ?? null;
        activeYearName.value = active?.school_year?.name ?? null;
        activeSemesterName.value = active?.semester?.name ?? null;
    }

    const failed = results.filter((result) => result.status === 'rejected').length;
    optionsError.value = failed > 0 ? 'Some reference options could not be loaded. Existing values remain visible; ask an administrator to check your permissions.' : '';

    yearFilter.value = activeSchoolYearId.value && years.value.some((year) => year.id === activeSchoolYearId.value) ? activeSchoolYearId.value : null;
};

const fetchClasses = async () => {
    loading.value = true;
    listError.value = '';

    try {
        const params = {};
        if (yearFilter.value) params.school_year_id = yearFilter.value;
        const response = await apiLogin.get('/api/school-rombels', { params });
        rows.value = response.data?.data ?? [];
    } catch (error) {
        rows.value = [];
        listError.value = errorMessage(error, 'Failed to load classes. Please try again.');
    } finally {
        loading.value = false;
    }
};

const onYearFilterChange = () => {
    fetchClasses();
};

const resetFilters = () => {
    search.value = '';
    teacherSearch.value = '';
    levelFilter.value = null;
    majorFilter.value = null;
    yearFilter.value = activeSchoolYearId.value ?? null;
    fetchClasses();
};

const onPage = (event) => {
    rowsPerPage.value = event.rows;
};

const rowActionBlockReason = (row) => {
    if (!row.school_year) return 'The academic year of this class is not available.';
    if (row.school_year.is_locked) return 'This school year is locked. Unlock it before changing classes.';
    return '';
};

const deleteBlockReason = (row) => {
    const yearReason = rowActionBlockReason(row);
    if (yearReason) return yearReason;
    if ((row.memberships_count ?? 0) > 0) {
        return 'This class has membership history and cannot be deleted.';
    }
    return '';
};

const applyRowToForm = (row) => {
    classForm.value = {
        id: row.id,
        name: row.name ?? '',
        school_year_id: row.school_year_id,
        school_level_id: row.school_level_id,
        student_major_id: row.student_major_id ?? null,
        class_teach_employee_id: row.class_teach_employee_id ?? null,
        expected_version: row.version
    };
    editorHasHistory.value = (row.memberships_count ?? 0) > 0;
    selectedTeacher.value = row.class_teach_employee ?? null;
    legacyTeacherName.value = row.class_teach_employee ? '' : (row.class_teach?.name ?? '');
};

const openCreate = () => {
    if (unlockedYears.value.length === 0) {
        notify('warn', 'No unlocked school year is available. Unlock a year before creating a class.');
        return;
    }

    const preferredYearId =
        yearFilter.value && unlockedYears.value.some((year) => year.id === yearFilter.value)
            ? yearFilter.value
            : activeSchoolYearId.value && unlockedYears.value.some((year) => year.id === activeSchoolYearId.value)
              ? activeSchoolYearId.value
              : unlockedYears.value[0].id;

    classEditing.value = false;
    classSubmitted.value = false;
    classErrors.value = {};
    classMessage.value = '';
    editorHasHistory.value = false;
    selectedTeacher.value = null;
    legacyTeacherName.value = '';
    teacherSuggestions.value = [];
    teacherOptionsError.value = '';
    classForm.value = {
        id: null,
        name: '',
        school_year_id: preferredYearId,
        school_level_id: null,
        student_major_id: null,
        class_teach_employee_id: null,
        expected_version: null
    };
    classDialog.value = true;
};

const openEdit = (row) => {
    if (rowActionBlockReason(row)) return;

    classEditing.value = true;
    classSubmitted.value = false;
    classErrors.value = {};
    classMessage.value = '';
    teacherSuggestions.value = [];
    teacherOptionsError.value = '';
    applyRowToForm(row);
    classDialog.value = true;
};

const searchTeachers = (event) => {
    const query = (event?.query ?? '').trim();
    if (teacherTimer) clearTimeout(teacherTimer);

    teacherTimer = setTimeout(async () => {
        teacherLoading.value = true;
        teacherOptionsError.value = '';
        try {
            const params = query ? { search: query } : {};
            const response = await apiLogin.get('/api/employees/teacher-options', { params });
            teacherSuggestions.value = response.data?.data ?? [];
        } catch (error) {
            teacherSuggestions.value = [];
            teacherOptionsError.value = error.response?.status === 403 ? 'You do not have permission to view the teacher list. You can still save the class without a homeroom teacher.' : 'Failed to load homeroom teacher options.';
        } finally {
            teacherLoading.value = false;
        }
    }, 250);
};

watch(selectedTeacher, (value) => {
    if (value && typeof value === 'object') {
        classForm.value.class_teach_employee_id = value.id;
    } else if (!value) {
        classForm.value.class_teach_employee_id = null;
    }
});

const clearTeacher = () => {
    selectedTeacher.value = null;
    classForm.value.class_teach_employee_id = null;
};

const submitClass = async () => {
    if (classSaving.value) return;
    classSubmitted.value = true;
    classErrors.value = {};
    classMessage.value = '';

    const name = (classForm.value.name ?? '').trim();
    if (!name) classErrors.value.name = ['Name is required.'];
    if (!classForm.value.school_year_id) classErrors.value.school_year_id = ['School year is required.'];
    if (!classForm.value.school_level_id) classErrors.value.school_level_id = ['School level is required.'];
    if (Object.keys(classErrors.value).length > 0) return;

    classSaving.value = true;
    try {
        const payload = {
            name,
            school_year_id: classForm.value.school_year_id,
            school_level_id: classForm.value.school_level_id,
            student_major_id: classForm.value.student_major_id || null,
            class_teach_employee_id: classForm.value.class_teach_employee_id || null
        };

        let response;
        if (classEditing.value) {
            payload.expected_version = classForm.value.expected_version;
            response = await apiLogin.put(`/api/school-rombels/${classForm.value.id}`, payload);
        } else {
            response = await apiLogin.post('/api/school-rombels', payload);
        }

        notify('success', response.data?.message ?? 'Class saved.');
        classDialog.value = false;
        await fetchClasses();
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            classErrors.value = validationErrors(error);
            classMessage.value = errorMessage(error, '');
        } else if (status === 409) {
            conflictMessage.value = errorMessage(error, 'Conflict: this class was modified by another operator.');
            conflictReloadAction.value = reloadLatestClass;
            classDialog.value = false;
            conflictDialog.value = true;
        } else {
            notify('error', errorMessage(error, 'Failed to save the class.'));
        }
    } finally {
        classSaving.value = false;
    }
};

const reloadLatestClass = async () => {
    if (!classEditing.value || !classForm.value.id) {
        await fetchClasses();
        return;
    }

    const response = await apiLogin.get(`/api/school-rombels/${classForm.value.id}`);
    const row = response.data?.data;
    if (!row) throw new Error('Class not found.');
    applyRowToForm(row);
    classEditing.value = true;
    classSubmitted.value = false;
    classErrors.value = {};
    classMessage.value = '';
    classDialog.value = true;
    await fetchClasses();
};

const reloadAfterConflict = async () => {
    if (reloadingConflict.value) return;
    reloadingConflict.value = true;
    try {
        if (conflictReloadAction.value) await conflictReloadAction.value();
        conflictDialog.value = false;
    } catch (error) {
        if (error.response?.status === 404 || error.message === 'Class not found.') {
            classDialog.value = false;
            conflictDialog.value = false;
            await fetchClasses();
            notify('warn', 'The class was deleted by another user. The list has been refreshed.');
        } else {
            notify('error', errorMessage(error, 'Failed to reload the latest class data.'));
        }
    } finally {
        reloadingConflict.value = false;
    }
};

const openDelete = (row) => {
    if (deleteBlockReason(row)) return;
    deleteTarget.value = row;
    deleteError.value = '';
    deleteDialog.value = true;
};

const confirmDelete = async () => {
    if (deleteSaving.value) return;
    deleteSaving.value = true;
    deleteError.value = '';

    try {
        const response = await apiLogin.delete(`/api/school-rombels/${deleteTarget.value.id}`, {
            data: { expected_version: deleteTarget.value.version }
        });
        notify('success', response.data?.message ?? 'Class deleted.');
        deleteDialog.value = false;
        await fetchClasses();
    } catch (error) {
        const status = error.response?.status;
        if (status === 409) {
            deleteDialog.value = false;
            conflictMessage.value = errorMessage(error, 'Conflict: this class was modified by another operator.');
            conflictReloadAction.value = fetchClasses;
            conflictDialog.value = true;
        } else {
            deleteError.value = errorMessage(error, 'Failed to delete the class.');
        }
    } finally {
        deleteSaving.value = false;
    }
};

onMounted(async () => {
    await store.fetchIdentity();
    await loadReference();
    await fetchClasses();
});
</script>

<template>
    <Fluid>
        <div class="bg-surface-0 dark:bg-surface-950 px-6 py-8 md:px-12 lg:px-20">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <div class="font-bold text-3xl text-surface-900 dark:text-surface-0 mb-2">Classes</div>
                    <p class="text-surface-500 dark:text-surface-400">Class groups per academic year with homeroom teachers from active teacher records.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Tag v-if="activePeriodLabel" severity="success" :value="`Active period: ${activePeriodLabel}`" />
                    <Tag v-else value="No active period" severity="warn" />
                </div>
            </div>

            <div class="card mt-6 flex flex-col gap-4">
                <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-3">
                    <div class="flex flex-wrap gap-2">
                        <Button v-if="canManage" label="New Class" icon="pi pi-plus" :disabled="unlockedYears.length === 0" @click="openCreate" />
                        <Button icon="pi pi-filter-slash" label="Clear" severity="secondary" variant="outlined" @click="resetFilters" />
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-sm text-surface-500">Total classes: {{ filteredRows.length }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-3">
                    <Select v-model="yearFilter" :options="yearOptions" optionLabel="name" optionValue="id" placeholder="All school years" showClear class="w-full" @change="onYearFilterChange" />
                    <Select v-model="levelFilter" :options="levelOptions" optionLabel="name" optionValue="id" placeholder="All levels" showClear class="w-full" />
                    <Select v-model="majorFilter" :options="majorOptions" optionLabel="name" optionValue="id" placeholder="All majors" showClear class="w-full" />
                    <InputText v-model="teacherSearch" placeholder="Homeroom teacher" class="w-full" />
                    <IconField class="w-full">
                        <InputIcon><i class="pi pi-search" /></InputIcon>
                        <InputText v-model="search" placeholder="Search class, year, level" class="w-full" />
                    </IconField>
                </div>

                <small v-if="optionsError" class="text-orange-600 dark:text-orange-400">{{ optionsError }}</small>

                <DataTable
                    :value="filteredRows"
                    paginator
                    showGridlines
                    :rows="rowsPerPage"
                    :rowsPerPageOptions="rowsPerPageOptions"
                    dataKey="id"
                    :loading="loading"
                    @page="onPage"
                    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                    currentPageReportTemplate="Showing {first} to {last} of {totalRecords}"
                >
                    <template #empty>
                        <div class="flex flex-col items-center gap-3 py-6">
                            <span v-if="listError" class="text-red-500">{{ listError }}</span>
                            <span v-else>No classes match the current filters.</span>
                            <Button v-if="listError" icon="pi pi-refresh" label="Retry" @click="fetchClasses" />
                        </div>
                    </template>
                    <template #loading>Loading classes. Please wait.</template>
                    <Column header="No" style="width: 3rem">
                        <template #body="{ index }">{{ index + 1 }}</template>
                    </Column>
                    <Column field="name" header="Name" sortable style="min-width: 10rem" />
                    <Column field="school_year.name" header="School Year" sortable style="min-width: 13rem">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2">
                                <span>{{ data.school_year?.name ?? '-' }}</span>
                                <Tag v-if="data.school_year?.is_locked" value="Locked" severity="danger" />
                            </div>
                        </template>
                    </Column>
                    <Column field="school_level.name" header="School Level" sortable style="min-width: 9rem">
                        <template #body="{ data }">
                            <Tag v-if="data.school_level" :value="data.school_level.name" severity="info" />
                            <span v-else>-</span>
                        </template>
                    </Column>
                    <Column field="student_major.name" header="Student Major" sortable style="min-width: 10rem">
                        <template #body="{ data }">{{ data.student_major?.name ?? '-' }}</template>
                    </Column>
                    <Column field="class_teach_employee.name" header="Homeroom Teacher" sortable style="min-width: 14rem">
                        <template #body="{ data }">
                            <span v-if="data.class_teach_employee">{{ data.class_teach_employee.name }}</span>
                            <span v-else-if="data.class_teach">
                                {{ data.class_teach.name }}
                                <Tag value="Legacy" severity="secondary" class="ml-1" />
                            </span>
                            <span v-else>-</span>
                        </template>
                    </Column>
                    <Column header="Students" style="min-width: 9rem">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span class="font-medium">{{ data.active_members_count ?? 0 }} active</span>
                                <small class="text-surface-500">{{ data.memberships_count ?? 0 }} total</small>
                            </div>
                        </template>
                    </Column>
                    <Column header="Version" style="min-width: 6rem">
                        <template #body="{ data }">v{{ data.version }}</template>
                    </Column>
                    <Column v-if="canManage" header="Action" :exportable="false" style="min-width: 9rem">
                        <template #body="{ data }">
                            <Button icon="pi pi-pencil" variant="outlined" rounded class="mr-2" :disabled="!!rowActionBlockReason(data)" v-tooltip.top="rowActionBlockReason(data) || 'Edit'" @click="openEdit(data)" />
                            <Button icon="pi pi-trash" variant="outlined" rounded severity="danger" :disabled="!!deleteBlockReason(data)" v-tooltip.top="deleteBlockReason(data) || 'Delete'" @click="openDelete(data)" />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </Fluid>

    <Dialog v-model:visible="classDialog" modal :style="{ width: '560px', maxWidth: '95vw' }" :header="classEditing ? 'Edit Class' : 'New Class'" :draggable="false">
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <label for="className" class="font-medium">Name <span class="text-red-500">*</span></label>
                <InputText id="className" v-model="classForm.name" maxlength="100" autofocus fluid :invalid="classSubmitted && !classForm.name?.trim()" />
                <small v-if="classSubmitted && !classForm.name?.trim()" class="text-red-500">Name is required.</small>
                <small v-else-if="fieldError(classErrors, 'name')" class="text-red-500">
                    {{ fieldError(classErrors, 'name') }}
                </small>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label for="classYear" class="font-medium">School Year <span class="text-red-500">*</span></label>
                    <Select id="classYear" v-model="classForm.school_year_id" :options="yearOptions" optionLabel="name" optionValue="id" placeholder="Select school year" :disabled="editorHasHistory" fluid />
                    <small v-if="fieldError(classErrors, 'school_year_id')" class="text-red-500">
                        {{ fieldError(classErrors, 'school_year_id') }}
                    </small>
                </div>
                <div class="flex flex-col gap-2">
                    <label for="classLevel" class="font-medium">School Level <span class="text-red-500">*</span></label>
                    <Select id="classLevel" v-model="classForm.school_level_id" :options="levelOptions" optionLabel="name" optionValue="id" placeholder="Select school level" :disabled="editorHasHistory" fluid />
                    <small v-if="fieldError(classErrors, 'school_level_id')" class="text-red-500">
                        {{ fieldError(classErrors, 'school_level_id') }}
                    </small>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label for="classMajor" class="font-medium">Student Major</label>
                <Select id="classMajor" v-model="classForm.student_major_id" :options="majorOptions" optionLabel="name" optionValue="id" placeholder="No student major" showClear :disabled="editorHasHistory" fluid />
                <small v-if="fieldError(classErrors, 'student_major_id')" class="text-red-500">
                    {{ fieldError(classErrors, 'student_major_id') }}
                </small>
            </div>

            <div class="flex flex-col gap-2">
                <label for="classTeacher" class="font-medium">Homeroom Teacher</label>
                <div class="flex items-center gap-2">
                    <AutoComplete
                        id="classTeacher"
                        v-model="selectedTeacher"
                        :suggestions="teacherSuggestions"
                        optionLabel="name"
                        forceSelection
                        dropdown
                        display="chip"
                        fluid
                        placeholder="Search an active teacher"
                        :loading="teacherLoading"
                        @complete="searchTeachers"
                    />
                    <Button v-if="selectedTeacher" icon="pi pi-times" text rounded severity="secondary" v-tooltip.top="'Clear homeroom teacher'" @click="clearTeacher" />
                </div>
                <small v-if="fieldError(classErrors, 'class_teach_employee_id')" class="text-red-500">
                    {{ fieldError(classErrors, 'class_teach_employee_id') }}
                </small>
                <small v-if="teacherOptionsError" class="text-orange-600 dark:text-orange-400">
                    {{ teacherOptionsError }}
                </small>
                <small v-if="legacyTeacherName" class="text-surface-500"> Legacy homeroom teacher: {{ legacyTeacherName }}. Select an active teacher to replace it. </small>
                <small class="text-surface-500">Only active teachers (Pendidik) are eligible.</small>
            </div>

            <div v-if="selectedYearLocked" class="text-orange-600 dark:text-orange-400 text-sm">The selected school year is locked. Unlock it before saving class changes.</div>

            <div v-if="editorHasHistory" class="text-surface-500 text-sm">Academic year, school level, and student major cannot be changed because this class already has membership history.</div>

            <div v-if="classMessage" class="text-red-500 text-sm">{{ classMessage }}</div>
        </div>
        <template #footer>
            <Button label="Cancel" icon="pi pi-times" text severity="secondary" :disabled="classSaving" @click="classDialog = false" />
            <Button :label="classEditing ? 'Save Changes' : 'Create'" icon="pi pi-check" :loading="classSaving" :disabled="!canSubmitClass" @click="submitClass" />
        </template>
    </Dialog>

    <Dialog v-model:visible="conflictDialog" modal :style="{ width: '520px', maxWidth: '95vw' }" header="Data Conflict Detected" :draggable="false">
        <div class="flex items-start gap-4">
            <i class="pi pi-exclamation-triangle !text-3xl text-orange-500" />
            <div>
                <p class="mb-2">{{ conflictMessage }}</p>
                <p class="text-surface-500 text-sm">Click "Reload Latest" to fetch the newest class data. Your current input will be replaced by the latest record.</p>
            </div>
        </div>
        <template #footer>
            <Button label="Close" icon="pi pi-times" text severity="secondary" :disabled="reloadingConflict" @click="conflictDialog = false" />
            <Button label="Reload Latest" icon="pi pi-refresh" :loading="reloadingConflict" :disabled="reloadingConflict" @click="reloadAfterConflict" />
        </template>
    </Dialog>

    <Dialog v-model:visible="deleteDialog" modal :style="{ width: '460px', maxWidth: '95vw' }" header="Delete Class" :draggable="false">
        <div class="flex items-start gap-4">
            <i class="pi pi-exclamation-triangle !text-3xl text-red-500" />
            <div>
                <p>
                    Are you sure you want to delete <strong>{{ deleteTarget?.name }}</strong
                    >?
                </p>
                <p class="text-sm text-surface-500 mt-2">This action is only allowed when the class has no membership history. The deletion is recorded in the audit log.</p>
                <p v-if="deleteError" class="text-red-500 text-sm mt-2">{{ deleteError }}</p>
            </div>
        </div>
        <template #footer>
            <Button label="Cancel" icon="pi pi-times" text severity="secondary" :disabled="deleteSaving" @click="deleteDialog = false" />
            <Button label="Delete" icon="pi pi-trash" severity="danger" :loading="deleteSaving" :disabled="deleteSaving" @click="confirmDelete" />
        </template>
    </Dialog>
</template>
