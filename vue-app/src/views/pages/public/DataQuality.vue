<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import { authStore } from '@/stores/authStore';
import apiLogin from '@/apiLogin';
import DataQualityReviewDialog from './DataQualityReviewDialog.vue';
import {
    DATA_QUALITY_PER_PAGE_OPTIONS,
    buildIssueParams,
    buildOpenRecordPath,
    buildStudentSummaryParams,
    defaultSchoolYearId,
    formatCompletenessPercent,
    issueFieldLabel,
    issueFieldOptions,
    issueStatusLabel,
    issueStatusOptions,
    issueTypeLabel,
    issueTypeOptions,
    resolveInitialTab,
    reviewReasonLabel
} from './dataQualityParams.mjs';

const { t, te, locale } = useI18n();
const store = authStore();
const router = useRouter();
const toast = useToast();

const canStudents = computed(() => store.can('students.view'));
const canEmployees = computed(() => store.can('employees.view'));
const canManageStudents = computed(() => store.can('students.manage'));
const canManageEmployees = computed(() => store.can('employees.manage'));

const activeTab = ref(resolveInitialTab(canStudents.value, canEmployees.value));
const schoolYears = ref([]);
const studentYearId = ref(null);
const yearsLoading = ref(false);
const yearsError = ref('');
const employeeLoaded = ref(false);

const createListState = () =>
    reactive({
        summary: null,
        summaryLoading: false,
        summaryError: '',
        rows: [],
        meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
        loading: false,
        error: '',
        type: null,
        field: null,
        status: 'open',
        search: '',
        page: 1,
        perPage: 20,
        requestId: 0,
        debounceTimer: null
    });

const studentList = createListState();
const employeeList = createListState();
const listStates = { students: studentList, employees: employeeList };

const issueTypeSelectOptions = computed(() => issueTypeOptions(t));
const issueStatusSelectOptions = computed(() => issueStatusOptions(t));
const studentFieldOptions = computed(() => issueFieldOptions('students', t));
const employeeFieldOptions = computed(() => issueFieldOptions('employees', t));
const pageReportTemplate = computed(() => t('dataQuality.issues.pageReport', { first: '{first}', last: '{last}', totalRecords: '{totalRecords}' }));

// Label isian ringkasan: terjemahkan bila kode dikenal, jika tidak pakai label dari server.
const summaryFieldLabel = (row) => (te(`dataQuality.field.${row.field}`) ? issueFieldLabel(row.field, t) : row.label);

const canManageDomain = (domain) => (domain === 'students' ? canManageStudents.value : canManageEmployees.value);

const issueSeverity = (type) =>
    ({
        missing: 'warn',
        invalid: 'danger',
        duplicate: 'info',
        blocked: 'contrast'
    })[type] ?? 'secondary';

const reviewSeverity = (status) =>
    ({
        open: 'warn',
        acknowledged: 'info',
        ignored: 'secondary'
    })[status] ?? 'secondary';

const placementSeverity = (placement) => (placement === 'placed' ? 'success' : 'warn');

const formatAsOf = (value) => {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';

    return `${date.toLocaleString(locale.value === 'id' ? 'id-ID' : 'en-GB', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Jakarta'
    })} (${t('dataQuality.timezone')})`;
};

const studentPopulation = computed(() => studentList.summary?.population ?? {});
const studentRecords = computed(() => studentList.summary?.records ?? {});
const employeePopulation = computed(() => employeeList.summary?.population ?? {});
const employeeRecords = computed(() => employeeList.summary?.records ?? {});

const studentFindings = computed(() =>
    (studentList.summary?.fields ?? []).reduce(
        (totals, field) => ({
            missing: totals.missing + (field.missing ?? 0),
            invalid: totals.invalid + (field.invalid ?? 0)
        }),
        { missing: 0, invalid: 0 }
    )
);

const employeeFindings = computed(() =>
    (employeeList.summary?.fields ?? []).reduce(
        (totals, field) => ({
            missing: totals.missing + (field.missing ?? 0),
            invalid: totals.invalid + (field.invalid ?? 0)
        }),
        { missing: 0, invalid: 0 }
    )
);

const studentBlocked = computed(() => studentList.summary?.fields?.find((field) => field.field === 'nik')?.blocked ?? { missing: 0, invalid: 0 });

const studentReviews = computed(() => studentList.summary?.reviews ?? { open: 0, acknowledged: 0, ignored: 0, unresolved: 0 });
const employeeReviews = computed(() => employeeList.summary?.reviews ?? { open: 0, acknowledged: 0, ignored: 0, unresolved: 0 });
const studentDuplicates = computed(() => studentList.summary?.duplicates ?? { candidate_pairs: 0, affected_subjects: 0 });
const employeeDuplicates = computed(() => employeeList.summary?.duplicates ?? { candidate_pairs: 0, affected_subjects: 0 });

const loadSchoolYears = async () => {
    yearsLoading.value = true;
    yearsError.value = '';

    try {
        const response = await apiLogin.get('/api/school-years');
        schoolYears.value = response.data.data ?? [];
        studentYearId.value = defaultSchoolYearId(schoolYears.value);

        if (studentYearId.value) {
            await Promise.all([loadStudentSummary(), loadIssues('students')]);
        }
    } catch (error) {
        yearsError.value = error.response?.data?.message ?? t('dataQuality.errors.loadYears');
    } finally {
        yearsLoading.value = false;
    }
};

const loadStudentSummary = async () => {
    if (!studentYearId.value) return;

    studentList.summaryLoading = true;
    studentList.summaryError = '';

    try {
        const response = await apiLogin.get('/api/data-quality/students/summary', {
            params: buildStudentSummaryParams(studentYearId.value)
        });
        studentList.summary = response.data.data ?? null;
    } catch (error) {
        studentList.summary = null;
        studentList.summaryError = error.response?.data?.message ?? t('dataQuality.errors.loadStudentSummary');
    } finally {
        studentList.summaryLoading = false;
    }
};

const loadEmployeeSummary = async () => {
    employeeList.summaryLoading = true;
    employeeList.summaryError = '';

    try {
        const response = await apiLogin.get('/api/data-quality/employees/summary');
        employeeList.summary = response.data.data ?? null;
    } catch (error) {
        employeeList.summary = null;
        employeeList.summaryError = error.response?.data?.message ?? t('dataQuality.errors.loadEmployeeSummary');
    } finally {
        employeeList.summaryLoading = false;
    }
};

const loadIssues = async (domain) => {
    const state = listStates[domain];
    const schoolYearId = domain === 'students' ? studentYearId.value : null;

    if (domain === 'students' && !schoolYearId) return;

    const requestId = ++state.requestId;
    state.loading = true;
    state.error = '';

    try {
        const params = buildIssueParams({
            schoolYearId,
            page: state.page,
            perPage: state.perPage,
            type: state.type,
            field: state.field,
            status: state.status,
            search: state.search
        });
        const response = await apiLogin.get(`/api/data-quality/${domain}/issues`, { params });

        if (requestId !== state.requestId) return;

        state.rows = response.data.data ?? [];
        state.meta = response.data.meta ?? { current_page: 1, per_page: state.perPage, total: 0, last_page: 1 };

        if (state.rows.length === 0 && state.page > 1 && state.page > state.meta.last_page) {
            state.page = Math.max(1, state.meta.last_page);
            return loadIssues(domain);
        }
    } catch (error) {
        if (requestId !== state.requestId) return;
        state.rows = [];
        state.error = error.response?.data?.message ?? t('dataQuality.errors.loadIssues');
    } finally {
        if (requestId === state.requestId) state.loading = false;
    }
};

const refreshAll = (domain) => {
    if (domain === 'students') {
        return Promise.all([loadStudentSummary(), loadIssues('students')]);
    }

    return Promise.all([loadEmployeeSummary(), loadIssues('employees')]);
};

const onSchoolYearChange = () => {
    studentList.page = 1;
    studentList.summary = null;
    studentList.rows = [];
    refreshAll('students');
};

const onFilterChange = (domain) => {
    const state = listStates[domain];
    state.page = 1;
    loadIssues(domain);
};

const onSearchInput = (domain) => {
    const state = listStates[domain];
    if (state.debounceTimer) clearTimeout(state.debounceTimer);
    state.debounceTimer = setTimeout(() => onFilterChange(domain), 300);
};

const onPage = (domain, event) => {
    const state = listStates[domain];
    state.page = event.page + 1;
    state.perPage = event.rows;
    loadIssues(domain);
};

const resetFilters = (domain) => {
    const state = listStates[domain];
    state.type = null;
    state.field = null;
    state.status = 'open';
    state.search = '';
    state.page = 1;
    loadIssues(domain);
};

const openRecord = (domain, subject) => {
    const path = buildOpenRecordPath(domain, subject);
    if (path) router.push(path);
};

const reviewDialogVisible = ref(false);
const reviewIssue = ref(null);
const reviewDomain = ref('students');
const reviewInitialStatus = ref('acknowledged');

const openReview = (issue, domain, status) => {
    reviewIssue.value = issue;
    reviewDomain.value = domain;
    reviewInitialStatus.value = status;
    reviewDialogVisible.value = true;
};

const onReviewed = (result) => {
    toast.add({
        severity: 'success',
        summary: t('dataQuality.toast.reviewSavedSummary'),
        detail: t('dataQuality.toast.reviewSavedDetail', { status: issueStatusLabel(result.status, t) }),
        life: 3000
    });
    refreshAll(reviewDomain.value);
};

const onReviewStale = (message) => {
    toast.add({
        severity: 'warn',
        summary: t('dataQuality.toast.reviewStaleSummary'),
        detail: message,
        life: 5000
    });
    refreshAll(reviewDomain.value);
};

watch(activeTab, (tab) => {
    if (tab === 'employees' && canEmployees.value && !employeeLoaded.value) {
        employeeLoaded.value = true;
        refreshAll('employees');
    }
});

onMounted(async () => {
    if (store.identity === null) await store.fetchIdentity();

    if (!activeTab.value) activeTab.value = resolveInitialTab(canStudents.value, canEmployees.value);

    if (activeTab.value === 'students' && canStudents.value) {
        await loadSchoolYears();
    }

    if (activeTab.value === 'employees' && canEmployees.value) {
        employeeLoaded.value = true;
        await refreshAll('employees');
    }
});
</script>

<template>
    <Fluid>
        <div class="bg-surface-0 dark:bg-surface-900 px-6 py-8 md:px-12 lg:px-20">
            <div class="flex flex-col gap-1 mb-6">
                <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-0">{{ t('dataQuality.title') }}</h1>
                <p class="text-sm text-surface-600 dark:text-surface-300">{{ t('dataQuality.subtitle') }}</p>
            </div>

            <Tabs v-model:value="activeTab">
                <TabList>
                    <Tab v-if="canStudents" value="students">{{ t('dataQuality.tabs.students') }}</Tab>
                    <Tab v-if="canEmployees" value="employees">{{ t('dataQuality.tabs.employees') }}</Tab>
                </TabList>

                <TabPanels>
                    <TabPanel v-if="canStudents" value="students">
                        <div class="flex flex-col gap-4 pt-2">
                            <div class="flex flex-col lg:flex-row lg:items-end gap-3">
                                <div class="flex flex-col gap-2">
                                    <label for="dq-student-year" class="text-sm font-medium">{{ t('dataQuality.schoolYear.label') }}</label>
                                    <Select
                                        id="dq-student-year"
                                        v-model="studentYearId"
                                        :options="schoolYears"
                                        optionLabel="name"
                                        optionValue="id"
                                        :placeholder="t('dataQuality.schoolYear.placeholder')"
                                        class="w-full md:w-64"
                                        :loading="yearsLoading"
                                        @change="onSchoolYearChange"
                                    />
                                </div>
                                <Button :label="t('common.action.refresh')" icon="pi pi-refresh" severity="secondary" variant="outlined" :disabled="!studentYearId" @click="refreshAll('students')" />
                                <span v-if="studentList.summary?.as_of" class="text-xs text-surface-500 lg:ml-auto">{{ t('dataQuality.asOf', { time: formatAsOf(studentList.summary.as_of) }) }}</span>
                            </div>

                            <div v-if="yearsError" class="flex flex-wrap items-center gap-2">
                                <Message severity="error" :closable="false">{{ yearsError }}</Message>
                                <Button :label="t('common.action.retry')" icon="pi pi-refresh" size="small" text @click="loadSchoolYears" />
                            </div>

                            <Message v-else-if="!yearsLoading && schoolYears.length === 0" severity="info" :closable="false">{{ t('dataQuality.schoolYear.empty') }}</Message>

                            <div v-if="studentList.summaryError" class="flex flex-wrap items-center gap-2">
                                <Message severity="error" :closable="false">{{ studentList.summaryError }}</Message>
                                <Button :label="t('common.action.retry')" icon="pi pi-refresh" size="small" text @click="loadStudentSummary" />
                            </div>

                            <p v-else-if="studentList.summaryLoading" class="text-sm text-surface-500">{{ t('dataQuality.loadingSummary') }}</p>

                            <template v-else-if="studentList.summary">
                                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-3">
                                    <div class="border border-surface-200 dark:border-surface-700 rounded-border p-4">
                                        <div class="text-sm text-surface-500">{{ t('dataQuality.summary.placedStudents') }}</div>
                                        <div class="text-3xl font-semibold">{{ studentPopulation.placed ?? 0 }}</div>
                                        <div class="grid grid-cols-2 gap-1 text-xs text-surface-500 mt-2">
                                            <span>{{ t('dataQuality.summary.active', { count: studentPopulation.active_students ?? 0 }) }}</span>
                                            <span>{{ t('dataQuality.summary.unplaced', { count: studentPopulation.unplaced ?? 0 }) }}</span>
                                            <span>{{ t('dataQuality.summary.notEvaluated', { count: studentPopulation.not_evaluated ?? 0 }) }}</span>
                                            <span>{{ t('dataQuality.summary.archivedExcluded', { count: studentPopulation.archived_excluded ?? 0 }) }}</span>
                                        </div>
                                    </div>
                                    <div class="border border-surface-200 dark:border-surface-700 rounded-border p-4">
                                        <div class="text-sm text-surface-500">{{ t('dataQuality.summary.completeRecords') }}</div>
                                        <div class="text-3xl font-semibold">{{ studentRecords.complete ?? 0 }}</div>
                                        <div class="text-xs text-surface-500 mt-2">
                                            {{ t('dataQuality.summary.incomplete', { count: studentRecords.incomplete ?? 0 }) }}<br />
                                            {{ t('dataQuality.summary.complete', { percent: formatCompletenessPercent(studentRecords.complete_percent) }) }}
                                        </div>
                                    </div>
                                    <div class="border border-surface-200 dark:border-surface-700 rounded-border p-4">
                                        <div class="text-sm text-surface-500">{{ t('dataQuality.summary.fieldFindings') }}</div>
                                        <div class="text-3xl font-semibold">{{ studentFindings.missing + studentFindings.invalid }}</div>
                                        <div class="text-xs text-surface-500 mt-2">
                                            {{ t('dataQuality.summary.missing', { count: studentFindings.missing }) }}<br />
                                            {{ t('dataQuality.summary.invalid', { count: studentFindings.invalid }) }}<br />
                                            {{ t('dataQuality.summary.blockedNik', { missing: studentBlocked.missing, invalid: studentBlocked.invalid }) }}
                                        </div>
                                    </div>
                                    <div class="border border-surface-200 dark:border-surface-700 rounded-border p-4">
                                        <div class="text-sm text-surface-500">{{ t('dataQuality.summary.duplicateCandidates') }}</div>
                                        <div class="text-3xl font-semibold">{{ studentDuplicates.candidate_pairs ?? 0 }}</div>
                                        <div class="text-xs text-surface-500 mt-2">
                                            {{ t('dataQuality.summary.affectedRecords', { count: studentDuplicates.affected_subjects ?? 0 }) }}<br />
                                            {{ t('dataQuality.summary.manualReviewOnly') }}
                                        </div>
                                    </div>
                                    <div class="border border-surface-200 dark:border-surface-700 rounded-border p-4">
                                        <div class="text-sm text-surface-500">{{ t('dataQuality.summary.reviewWorkflow') }}</div>
                                        <div class="text-3xl font-semibold">{{ studentReviews.unresolved ?? 0 }}</div>
                                        <div class="text-xs text-surface-500 mt-2">
                                            {{ t('dataQuality.summary.open', { count: studentReviews.open ?? 0 }) }}<br />
                                            {{ t('dataQuality.summary.acknowledged', { count: studentReviews.acknowledged ?? 0 }) }}<br />
                                            {{ t('dataQuality.summary.ignored', { count: studentReviews.ignored ?? 0 }) }}
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <h3 class="text-lg font-semibold">{{ t('dataQuality.completeness.title') }}</h3>
                                    <DataTable :value="studentList.summary.fields" dataKey="field" size="small" showGridlines scrollable>
                                        <Column :header="t('dataQuality.columns.field')" style="min-width: 10rem">
                                            <template #body="{ data }">{{ summaryFieldLabel(data) }}</template>
                                        </Column>
                                        <Column field="denominator" :header="t('dataQuality.columns.denominator')" style="min-width: 8rem" />
                                        <Column field="missing" :header="t('dataQuality.columns.missing')" style="min-width: 7rem" />
                                        <Column field="invalid" :header="t('dataQuality.columns.invalid')" style="min-width: 7rem" />
                                        <Column field="complete" :header="t('dataQuality.columns.complete')" style="min-width: 7rem" />
                                        <Column :header="t('dataQuality.columns.completeness')" style="min-width: 8rem">
                                            <template #body="{ data }">
                                                {{ formatCompletenessPercent(data.completeness_percent) }}
                                            </template>
                                        </Column>
                                    </DataTable>
                                    <p v-if="studentBlocked.missing || studentBlocked.invalid" class="text-xs text-surface-500">
                                        {{ t('dataQuality.completeness.studentsBlockedNote', { missing: studentBlocked.missing, invalid: studentBlocked.invalid }) }}
                                    </p>
                                </div>

                                <div class="flex flex-col gap-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <h3 class="text-lg font-semibold">{{ t('dataQuality.issues.title') }}</h3>
                                        <Tag :value="t('dataQuality.issues.matching', { count: studentList.meta.total })" severity="secondary" />
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <Select
                                            v-model="studentList.type"
                                            :options="issueTypeSelectOptions"
                                            optionLabel="label"
                                            optionValue="value"
                                            :placeholder="t('dataQuality.issues.allTypes')"
                                            showClear
                                            class="w-full sm:w-44"
                                            @change="onFilterChange('students')"
                                        />
                                        <Select
                                            v-model="studentList.field"
                                            :options="studentFieldOptions"
                                            optionLabel="label"
                                            optionValue="value"
                                            :placeholder="t('dataQuality.issues.allFields')"
                                            showClear
                                            filter
                                            class="w-full sm:w-52"
                                            @change="onFilterChange('students')"
                                        />
                                        <Select v-model="studentList.status" :options="issueStatusSelectOptions" optionLabel="label" optionValue="value" class="w-full sm:w-44" @change="onFilterChange('students')" />
                                        <InputText v-model="studentList.search" :placeholder="t('dataQuality.issues.searchStudents')" class="w-full sm:w-56" @input="onSearchInput('students')" />
                                        <Button :label="t('dataQuality.issues.reset')" icon="pi pi-filter-slash" severity="secondary" variant="outlined" @click="resetFilters('students')" />
                                    </div>

                                    <DataTable
                                        :value="studentList.rows"
                                        :lazy="true"
                                        paginator
                                        showGridlines
                                        scrollable
                                        :rows="studentList.perPage"
                                        :totalRecords="studentList.meta.total"
                                        :first="(studentList.meta.current_page - 1) * studentList.meta.per_page"
                                        :rowsPerPageOptions="DATA_QUALITY_PER_PAGE_OPTIONS"
                                        dataKey="issue_key"
                                        :loading="studentList.loading"
                                        paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                                        :currentPageReportTemplate="pageReportTemplate"
                                        @page="onPage('students', $event)"
                                    >
                                        <template #loading>{{ t('dataQuality.issues.loading') }}</template>
                                        <template #empty>
                                            <div class="flex flex-col items-center gap-3 py-6">
                                                <span v-if="studentList.error" class="text-red-500">{{ studentList.error }}</span>
                                                <span v-else>{{ t('dataQuality.issues.empty') }}</span>
                                                <Button v-if="studentList.error" icon="pi pi-refresh" :label="t('common.action.retry')" @click="loadIssues('students')" />
                                            </div>
                                        </template>
                                        <Column :header="t('dataQuality.columns.no')" style="width: 3rem">
                                            <template #body="{ index }">
                                                {{ (studentList.meta.current_page - 1) * studentList.meta.per_page + index + 1 }}
                                            </template>
                                        </Column>
                                        <Column :header="t('dataQuality.columns.record')" style="min-width: 15rem">
                                            <template #body="{ data }">
                                                <div class="font-medium">{{ data.subject.name }}</div>
                                                <div class="text-xs text-surface-500 flex flex-wrap items-center gap-1">
                                                    <span>{{ data.subject.nis || '-' }} / {{ data.subject.nisn || '-' }}</span>
                                                    <Tag :value="data.subject.placement === 'placed' ? t('dataQuality.placement.placed') : t('dataQuality.placement.unplaced')" :severity="placementSeverity(data.subject.placement)" />
                                                </div>
                                            </template>
                                        </Column>
                                        <Column :header="t('dataQuality.columns.issue')" style="min-width: 18rem">
                                            <template #body="{ data }">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <Tag :value="issueTypeLabel(data.type, t)" :severity="issueSeverity(data.type)" />
                                                    <span class="font-medium">{{ issueFieldLabel(data.field, t) }}</span>
                                                </div>
                                                <div class="text-xs text-surface-500 mt-1">{{ data.message }}</div>
                                            </template>
                                        </Column>
                                        <Column :header="t('dataQuality.columns.related')" style="min-width: 12rem">
                                            <template #body="{ data }">
                                                <template v-if="data.related">
                                                    <div class="flex items-center gap-2">
                                                        <span>{{ data.related.name }}</span>
                                                        <Button icon="pi pi-external-link" text rounded size="small" v-tooltip.top="t('dataQuality.tooltip.openRelated')" @click="openRecord('students', data.related)" />
                                                    </div>
                                                </template>
                                                <span v-else>-</span>
                                            </template>
                                        </Column>
                                        <Column :header="t('dataQuality.columns.review')" style="min-width: 12rem">
                                            <template #body="{ data }">
                                                <Tag :value="issueStatusLabel(data.review?.status ?? 'open', t)" :severity="reviewSeverity(data.review?.status ?? 'open')" />
                                                <div v-if="data.review?.reason_code" class="text-xs text-surface-500 mt-1">
                                                    {{ reviewReasonLabel(data.review.reason_code, t) }}
                                                </div>
                                                <div v-if="data.review?.version" class="text-xs text-surface-500">{{ t('dataQuality.issues.reviewVersion', { version: data.review.version }) }}</div>
                                            </template>
                                        </Column>
                                        <Column :header="t('dataQuality.columns.action')" :exportable="false" style="min-width: 13rem">
                                            <template #body="{ data }">
                                                <Button icon="pi pi-external-link" variant="outlined" rounded v-tooltip.top="t('dataQuality.tooltip.openRecord')" @click="openRecord('students', data.subject)" />
                                                <template v-if="canManageDomain('students')">
                                                    <Button
                                                        v-if="data.review?.status !== 'acknowledged'"
                                                        icon="pi pi-check"
                                                        variant="outlined"
                                                        rounded
                                                        severity="success"
                                                        class="ml-2"
                                                        v-tooltip.top="t('dataQuality.tooltip.acknowledge')"
                                                        @click="openReview(data, 'students', 'acknowledged')"
                                                    />
                                                    <Button
                                                        v-if="data.review?.status !== 'ignored'"
                                                        icon="pi pi-eye-slash"
                                                        variant="outlined"
                                                        rounded
                                                        severity="secondary"
                                                        class="ml-2"
                                                        v-tooltip.top="t('dataQuality.tooltip.ignore')"
                                                        @click="openReview(data, 'students', 'ignored')"
                                                    />
                                                    <Button
                                                        v-if="data.review?.status !== 'open'"
                                                        icon="pi pi-replay"
                                                        variant="outlined"
                                                        rounded
                                                        severity="info"
                                                        class="ml-2"
                                                        v-tooltip.top="t('dataQuality.tooltip.reopen')"
                                                        @click="openReview(data, 'students', 'open')"
                                                    />
                                                </template>
                                            </template>
                                        </Column>
                                    </DataTable>
                                </div>
                            </template>
                        </div>
                    </TabPanel>

                    <TabPanel v-if="canEmployees" value="employees">
                        <div class="flex flex-col gap-4 pt-2">
                            <div class="flex flex-col lg:flex-row lg:items-end gap-3">
                                <div class="flex flex-col gap-1">
                                    <span class="text-sm font-medium">{{ t('dataQuality.population.label') }}</span>
                                    <span class="text-sm text-surface-500">{{ t('dataQuality.population.employeesHint') }}</span>
                                </div>
                                <Button :label="t('common.action.refresh')" icon="pi pi-refresh" severity="secondary" variant="outlined" class="lg:ml-auto" @click="refreshAll('employees')" />
                                <span v-if="employeeList.summary?.as_of" class="text-xs text-surface-500">{{ t('dataQuality.asOf', { time: formatAsOf(employeeList.summary.as_of) }) }}</span>
                            </div>

                            <div v-if="employeeList.summaryError" class="flex flex-wrap items-center gap-2">
                                <Message severity="error" :closable="false">{{ employeeList.summaryError }}</Message>
                                <Button :label="t('common.action.retry')" icon="pi pi-refresh" size="small" text @click="loadEmployeeSummary" />
                            </div>

                            <p v-else-if="employeeList.summaryLoading" class="text-sm text-surface-500">{{ t('dataQuality.loadingSummary') }}</p>

                            <template v-else-if="employeeList.summary">
                                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-3">
                                    <div class="border border-surface-200 dark:border-surface-700 rounded-border p-4">
                                        <div class="text-sm text-surface-500">{{ t('dataQuality.summary.activeEmployees') }}</div>
                                        <div class="text-3xl font-semibold">{{ employeePopulation.active ?? 0 }}</div>
                                        <div class="grid grid-cols-2 gap-1 text-xs text-surface-500 mt-2">
                                            <span>{{ t('dataQuality.summary.separated', { count: employeePopulation.separated ?? 0 }) }}</span>
                                            <span>{{ t('dataQuality.summary.archivedExcluded', { count: employeePopulation.archived_excluded ?? 0 }) }}</span>
                                        </div>
                                    </div>
                                    <div class="border border-surface-200 dark:border-surface-700 rounded-border p-4">
                                        <div class="text-sm text-surface-500">{{ t('dataQuality.summary.completeRecords') }}</div>
                                        <div class="text-3xl font-semibold">{{ employeeRecords.complete ?? 0 }}</div>
                                        <div class="text-xs text-surface-500 mt-2">
                                            {{ t('dataQuality.summary.incomplete', { count: employeeRecords.incomplete ?? 0 }) }}<br />
                                            {{ t('dataQuality.summary.complete', { percent: formatCompletenessPercent(employeeRecords.complete_percent) }) }}
                                        </div>
                                    </div>
                                    <div class="border border-surface-200 dark:border-surface-700 rounded-border p-4">
                                        <div class="text-sm text-surface-500">{{ t('dataQuality.summary.fieldFindings') }}</div>
                                        <div class="text-3xl font-semibold">{{ employeeFindings.missing + employeeFindings.invalid }}</div>
                                        <div class="text-xs text-surface-500 mt-2">
                                            {{ t('dataQuality.summary.missing', { count: employeeFindings.missing }) }}<br />
                                            {{ t('dataQuality.summary.invalid', { count: employeeFindings.invalid }) }}<br />
                                            {{ t('dataQuality.summary.subjectRule') }}
                                        </div>
                                    </div>
                                    <div class="border border-surface-200 dark:border-surface-700 rounded-border p-4">
                                        <div class="text-sm text-surface-500">{{ t('dataQuality.summary.duplicateCandidates') }}</div>
                                        <div class="text-3xl font-semibold">{{ employeeDuplicates.candidate_pairs ?? 0 }}</div>
                                        <div class="text-xs text-surface-500 mt-2">
                                            {{ t('dataQuality.summary.affectedRecords', { count: employeeDuplicates.affected_subjects ?? 0 }) }}<br />
                                            {{ t('dataQuality.summary.manualReviewOnly') }}
                                        </div>
                                    </div>
                                    <div class="border border-surface-200 dark:border-surface-700 rounded-border p-4">
                                        <div class="text-sm text-surface-500">{{ t('dataQuality.summary.reviewWorkflow') }}</div>
                                        <div class="text-3xl font-semibold">{{ employeeReviews.unresolved ?? 0 }}</div>
                                        <div class="text-xs text-surface-500 mt-2">
                                            {{ t('dataQuality.summary.open', { count: employeeReviews.open ?? 0 }) }}<br />
                                            {{ t('dataQuality.summary.acknowledged', { count: employeeReviews.acknowledged ?? 0 }) }}<br />
                                            {{ t('dataQuality.summary.ignored', { count: employeeReviews.ignored ?? 0 }) }}
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <h3 class="text-lg font-semibold">{{ t('dataQuality.completeness.title') }}</h3>
                                    <DataTable :value="employeeList.summary.fields" dataKey="field" size="small" showGridlines scrollable>
                                        <Column :header="t('dataQuality.columns.field')" style="min-width: 12rem">
                                            <template #body="{ data }">{{ summaryFieldLabel(data) }}</template>
                                        </Column>
                                        <Column field="denominator" :header="t('dataQuality.columns.denominator')" style="min-width: 8rem" />
                                        <Column field="missing" :header="t('dataQuality.columns.missing')" style="min-width: 7rem" />
                                        <Column field="invalid" :header="t('dataQuality.columns.invalid')" style="min-width: 7rem" />
                                        <Column field="complete" :header="t('dataQuality.columns.complete')" style="min-width: 7rem" />
                                        <Column :header="t('dataQuality.columns.completeness')" style="min-width: 8rem">
                                            <template #body="{ data }">
                                                {{ formatCompletenessPercent(data.completeness_percent) }}
                                            </template>
                                        </Column>
                                    </DataTable>
                                    <p class="text-xs text-surface-500">{{ t('dataQuality.completeness.employeesNote') }}</p>
                                </div>

                                <div class="flex flex-col gap-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <h3 class="text-lg font-semibold">{{ t('dataQuality.issues.title') }}</h3>
                                        <Tag :value="t('dataQuality.issues.matching', { count: employeeList.meta.total })" severity="secondary" />
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <Select
                                            v-model="employeeList.type"
                                            :options="issueTypeSelectOptions"
                                            optionLabel="label"
                                            optionValue="value"
                                            :placeholder="t('dataQuality.issues.allTypes')"
                                            showClear
                                            class="w-full sm:w-44"
                                            @change="onFilterChange('employees')"
                                        />
                                        <Select
                                            v-model="employeeList.field"
                                            :options="employeeFieldOptions"
                                            optionLabel="label"
                                            optionValue="value"
                                            :placeholder="t('dataQuality.issues.allFields')"
                                            showClear
                                            filter
                                            class="w-full sm:w-52"
                                            @change="onFilterChange('employees')"
                                        />
                                        <Select v-model="employeeList.status" :options="issueStatusSelectOptions" optionLabel="label" optionValue="value" class="w-full sm:w-44" @change="onFilterChange('employees')" />
                                        <InputText v-model="employeeList.search" :placeholder="t('dataQuality.issues.searchEmployees')" class="w-full sm:w-56" @input="onSearchInput('employees')" />
                                        <Button :label="t('dataQuality.issues.reset')" icon="pi pi-filter-slash" severity="secondary" variant="outlined" @click="resetFilters('employees')" />
                                    </div>

                                    <DataTable
                                        :value="employeeList.rows"
                                        :lazy="true"
                                        paginator
                                        showGridlines
                                        scrollable
                                        :rows="employeeList.perPage"
                                        :totalRecords="employeeList.meta.total"
                                        :first="(employeeList.meta.current_page - 1) * employeeList.meta.per_page"
                                        :rowsPerPageOptions="DATA_QUALITY_PER_PAGE_OPTIONS"
                                        dataKey="issue_key"
                                        :loading="employeeList.loading"
                                        paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                                        :currentPageReportTemplate="pageReportTemplate"
                                        @page="onPage('employees', $event)"
                                    >
                                        <template #loading>{{ t('dataQuality.issues.loading') }}</template>
                                        <template #empty>
                                            <div class="flex flex-col items-center gap-3 py-6">
                                                <span v-if="employeeList.error" class="text-red-500">{{ employeeList.error }}</span>
                                                <span v-else>{{ t('dataQuality.issues.empty') }}</span>
                                                <Button v-if="employeeList.error" icon="pi pi-refresh" :label="t('common.action.retry')" @click="loadIssues('employees')" />
                                            </div>
                                        </template>
                                        <Column :header="t('dataQuality.columns.no')" style="width: 3rem">
                                            <template #body="{ index }">
                                                {{ (employeeList.meta.current_page - 1) * employeeList.meta.per_page + index + 1 }}
                                            </template>
                                        </Column>
                                        <Column :header="t('dataQuality.columns.record')" style="min-width: 15rem">
                                            <template #body="{ data }">
                                                <div class="font-medium">{{ data.subject.name }}</div>
                                                <div class="text-xs text-surface-500 flex flex-wrap items-center gap-1">
                                                    <span>{{ data.subject.nuptk || '-' }}</span>
                                                    <Tag v-if="data.subject.employee_type" :value="data.subject.employee_type" severity="info" />
                                                </div>
                                            </template>
                                        </Column>
                                        <Column :header="t('dataQuality.columns.issue')" style="min-width: 18rem">
                                            <template #body="{ data }">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <Tag :value="issueTypeLabel(data.type, t)" :severity="issueSeverity(data.type)" />
                                                    <span class="font-medium">{{ issueFieldLabel(data.field, t) }}</span>
                                                </div>
                                                <div class="text-xs text-surface-500 mt-1">{{ data.message }}</div>
                                            </template>
                                        </Column>
                                        <Column :header="t('dataQuality.columns.related')" style="min-width: 12rem">
                                            <template #body="{ data }">
                                                <template v-if="data.related">
                                                    <div class="flex items-center gap-2">
                                                        <span>{{ data.related.name }}</span>
                                                        <Button icon="pi pi-external-link" text rounded size="small" v-tooltip.top="t('dataQuality.tooltip.openRelated')" @click="openRecord('employees', data.related)" />
                                                    </div>
                                                </template>
                                                <span v-else>-</span>
                                            </template>
                                        </Column>
                                        <Column :header="t('dataQuality.columns.review')" style="min-width: 12rem">
                                            <template #body="{ data }">
                                                <Tag :value="issueStatusLabel(data.review?.status ?? 'open', t)" :severity="reviewSeverity(data.review?.status ?? 'open')" />
                                                <div v-if="data.review?.reason_code" class="text-xs text-surface-500 mt-1">
                                                    {{ reviewReasonLabel(data.review.reason_code, t) }}
                                                </div>
                                                <div v-if="data.review?.version" class="text-xs text-surface-500">{{ t('dataQuality.issues.reviewVersion', { version: data.review.version }) }}</div>
                                            </template>
                                        </Column>
                                        <Column :header="t('dataQuality.columns.action')" :exportable="false" style="min-width: 13rem">
                                            <template #body="{ data }">
                                                <Button icon="pi pi-external-link" variant="outlined" rounded v-tooltip.top="t('dataQuality.tooltip.openRecord')" @click="openRecord('employees', data.subject)" />
                                                <template v-if="canManageDomain('employees')">
                                                    <Button
                                                        v-if="data.review?.status !== 'acknowledged'"
                                                        icon="pi pi-check"
                                                        variant="outlined"
                                                        rounded
                                                        severity="success"
                                                        class="ml-2"
                                                        v-tooltip.top="t('dataQuality.tooltip.acknowledge')"
                                                        @click="openReview(data, 'employees', 'acknowledged')"
                                                    />
                                                    <Button
                                                        v-if="data.review?.status !== 'ignored'"
                                                        icon="pi pi-eye-slash"
                                                        variant="outlined"
                                                        rounded
                                                        severity="secondary"
                                                        class="ml-2"
                                                        v-tooltip.top="t('dataQuality.tooltip.ignore')"
                                                        @click="openReview(data, 'employees', 'ignored')"
                                                    />
                                                    <Button
                                                        v-if="data.review?.status !== 'open'"
                                                        icon="pi pi-replay"
                                                        variant="outlined"
                                                        rounded
                                                        severity="info"
                                                        class="ml-2"
                                                        v-tooltip.top="t('dataQuality.tooltip.reopen')"
                                                        @click="openReview(data, 'employees', 'open')"
                                                    />
                                                </template>
                                            </template>
                                        </Column>
                                    </DataTable>
                                </div>
                            </template>
                        </div>
                    </TabPanel>
                </TabPanels>
            </Tabs>

            <DataQualityReviewDialog
                v-model:visible="reviewDialogVisible"
                :issue="reviewIssue"
                :domain="reviewDomain"
                :school-year-id="reviewDomain === 'students' ? studentYearId : null"
                :initial-status="reviewInitialStatus"
                @reviewed="onReviewed"
                @stale="onReviewStale"
            />
        </div>
    </Fluid>
</template>
