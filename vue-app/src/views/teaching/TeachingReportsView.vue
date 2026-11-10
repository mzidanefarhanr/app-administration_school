<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';
import apiLogin from '@/apiLogin';
import { authStore } from '@/stores/authStore';

const { t } = useI18n();
const store = authStore();
const toast = useToast();

const loadingOptions = ref(false);
const optionsError = ref('');
const options = ref({
    can: {
        view: false,
        manage: false,
        create_journals: false,
        update_journals: false
    },
    active_school_year_id: null,
    active_semester_id: null,
    actor_employee_id: null,
    self_employee: null,
    school_years: [],
    semesters: [],
    rombels: [],
    subjects: [],
    teachers: [],
    days_of_week: [],
    period_presets: [],
    exception_types: [],
    journal_methods: [],
    journal_source_types: [],
    journal_grace_days: 7,
    today_wib: '',
    grace_deadline_date: ''
});

const canManage = computed(() => Boolean(options.value?.can?.manage ?? store.can('teaching.manage')));
const selfEmployee = computed(() => options.value?.self_employee || null);

const context = reactive({
    school_year_id: null,
    semester_id: null
});

const selectedSchoolYear = computed(() => {
    return (options.value.school_years || []).find((y) => Number(y.id) === Number(context.school_year_id)) || null;
});

const isSelectedYearLocked = computed(() => Boolean(selectedSchoolYear.value?.is_locked));

const rombelsForSelectedYear = computed(() => {
    const all = options.value.rombels || [];
    if (!context.school_year_id) {
        return all;
    }
    return all.filter((r) => Number(r.school_year_id) === Number(context.school_year_id));
});

const activeTab = ref('summary'); // 'summary' | 'drilldown'

// Filters state
const filters = reactive({
    date_from: '',
    date_to: '',
    school_rombel_id: null,
    subject_id: null,
    employee_id: null,
    status: 'all',
    scope: 'all',
    per_page: 10,
    page: 1
});

const statusOptions = [
    { value: 'all', label: 'All Sessions' },
    { value: 'missing', label: 'Missing Journals Only' },
    { value: 'missing_grace', label: 'Missing (Within Grace)' },
    { value: 'missing_overdue', label: 'Missing (Overdue)' },
    { value: 'recorded', label: 'Recorded Only' }
];

const perPageOptions = [
    { value: 10, label: '10 / page' },
    { value: 20, label: '20 / page' },
    { value: 50, label: '50 / page' },
    { value: 100, label: '100 / page' }
];

// Report Data State
const loadingReport = ref(false);
const reportError = ref('');
const summaryData = reactive({
    total_teachers_with_sessions: 0,
    total_expected_sessions: 0,
    total_recorded_journals: 0,
    total_missing_journals: 0,
    total_missing_within_grace: 0,
    total_missing_overdue: 0,
    overall_compliance_percentage: 100.0,
    total_excluded_slots: 0
});

const teachersReportList = ref([]);
const summaryPage = ref(1);
const summaryPerPage = ref(10);
const summaryLastPage = computed(() => Math.max(1, Math.ceil((teachersReportList.value?.length || 0) / summaryPerPage.value)));
const paginatedTeachersReportList = computed(() => {
    const start = (summaryPage.value - 1) * summaryPerPage.value;
    return (teachersReportList.value || []).slice(start, start + summaryPerPage.value);
});
const drilldownList = ref([]);
const drilldownMeta = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0
});
const reportMeta = reactive({
    today_wib: '',
    grace_days: 7,
    grace_deadline_date: '',
    date_from: '',
    date_to: '',
    scope: 'all',
    status_filter: 'all',
    can_view_all: true
});

const exportingCsv = ref(false);

// ─── Direct Journal Fill / View Dialog State ─────────────────────────────────
const journalDialogVisible = ref(false);
const journalDialogMode = ref('create'); // 'create' | 'view'
const journalSubmitting = ref(false);
const journalFormError = ref('');
const journalFieldErrors = ref({});
const activeSession = ref(null);

const journalForm = reactive({
    teaching_schedule_id: null,
    session_date: '',
    topic: '',
    method: 'lecture',
    summary: '',
    notes: '',
    actual_start_time: '',
    actual_end_time: '',
    teacher_employee_id: null,
    correction_reason: ''
});

function extractErrors(error, fallbackMessage) {
    const response = error?.response;
    if (!response) {
        return { message: fallbackMessage, fieldErrors: {} };
    }
    const data = response.data || {};
    const fieldErrors = data.errors && typeof data.errors === 'object' ? data.errors : {};
    const firstFieldError = Object.values(fieldErrors).flat()[0];
    return {
        message: firstFieldError || data.message || fallbackMessage,
        fieldErrors
    };
}

function shiftDateString(dateStr, deltaDays) {
    const base = dateStr || options.value.today_wib || new Date().toISOString().slice(0, 10);
    const parts = base.split('-').map((n) => Number(n));
    if (parts.length !== 3 || parts.some((n) => Number.isNaN(n))) {
        return base;
    }
    const utc = new Date(Date.UTC(parts[0], parts[1] - 1, parts[2]));
    utc.setUTCDate(utc.getUTCDate() + deltaDays);
    return utc.toISOString().slice(0, 10);
}

function setDatePreset(type) {
    const today = options.value.today_wib || new Date().toISOString().slice(0, 10);
    filters.date_to = today;
    if (type === '7d') {
        filters.date_from = shiftDateString(today, -7);
    } else if (type === '30d') {
        filters.date_from = shiftDateString(today, -30);
    } else if (type === 'month') {
        const parts = today.split('-');
        filters.date_from = `${parts[0]}-${parts[1]}-01`;
    }
    filters.page = 1;
    fetchReport();
}

function buildQueryParams() {
    const params = {
        school_year_id: context.school_year_id,
        semester_id: context.semester_id
    };

    if (filters.date_from) params.date_from = filters.date_from;
    if (filters.date_to) params.date_to = filters.date_to;
    if (filters.school_rombel_id) params.school_rombel_id = filters.school_rombel_id;
    if (filters.subject_id) params.subject_id = filters.subject_id;
    if (filters.employee_id) params.employee_id = filters.employee_id;
    if (filters.status && filters.status !== 'all') params.status = filters.status;
    if (filters.scope) params.scope = filters.scope;
    params.per_page = filters.per_page;
    params.page = filters.page;

    return params;
}

async function fetchReport() {
    if (!context.school_year_id || !context.semester_id) {
        return;
    }

    loadingReport.value = true;
    reportError.value = '';

    try {
        const params = buildQueryParams();
        const res = await apiLogin.get('/teaching/reports/missing-journals', { params });
        const data = res.data?.data || {};
        const meta = res.data?.meta || {};

        // Summary
        const s = data.summary || {};
        summaryData.total_teachers_with_sessions = Number(s.total_teachers_with_sessions || 0);
        summaryData.total_expected_sessions = Number(s.total_expected_sessions || 0);
        summaryData.total_recorded_journals = Number(s.total_recorded_journals || 0);
        summaryData.total_missing_journals = Number(s.total_missing_journals || 0);
        summaryData.total_missing_within_grace = Number(s.total_missing_within_grace || 0);
        summaryData.total_missing_overdue = Number(s.total_missing_overdue || 0);
        summaryData.overall_compliance_percentage = Number(s.overall_compliance_percentage ?? 100.0);
        summaryData.total_excluded_slots = Number(s.total_excluded_slots || 0);

        // Teacher list
        teachersReportList.value = data.by_teacher || [];
        summaryPage.value = 1;

        // Drilldown
        drilldownList.value = data.drilldown?.data || [];
        const dMeta = data.drilldown?.meta || {};
        drilldownMeta.current_page = Number(dMeta.current_page || 1);
        drilldownMeta.last_page = Number(dMeta.last_page || 1);
        drilldownMeta.per_page = Number(dMeta.per_page || filters.per_page);
        drilldownMeta.total = Number(dMeta.total || 0);

        // Report Meta
        reportMeta.today_wib = meta.today_wib || '';
        reportMeta.grace_days = Number(meta.grace_days || 7);
        reportMeta.grace_deadline_date = meta.grace_deadline_date || '';
        reportMeta.date_from = meta.date_from || '';
        reportMeta.date_to = meta.date_to || '';
        reportMeta.scope = meta.scope || 'all';
        reportMeta.status_filter = meta.status_filter || 'all';
        reportMeta.can_view_all = Boolean(meta.can_view_all);

        if (!filters.date_from && meta.date_from) filters.date_from = meta.date_from;
        if (!filters.date_to && meta.date_to) filters.date_to = meta.date_to;
    } catch (err) {
        const parsed = extractErrors(err, 'Failed to generate teaching journal compliance report.');
        reportError.value = parsed.message;
        toast.add({ severity: 'error', summary: t('teaching.reports.toast.reportError'), detail: parsed.message, life: 4000 });
    } finally {
        loadingReport.value = false;
    }
}

async function exportCsv() {
    exportingCsv.value = true;
    try {
        const params = buildQueryParams();
        delete params.per_page;
        delete params.page;

        const res = await apiLogin.get('/teaching/reports/missing-journals/export', {
            params,
            responseType: 'blob'
        });

        const blob = new Blob([res.data], { type: 'text/csv;charset=utf-8;' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        const timestamp = new Date().toISOString().replace(/[-:T]/g, '').slice(0, 15);
        link.setAttribute('download', `teaching-missing-journals-${timestamp}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        toast.add({ severity: 'success', summary: t('teaching.reports.toast.exportSuccessTitle'), detail: t('teaching.reports.toast.exportSuccessDetail'), life: 3000 });
    } catch (err) {
        toast.add({ severity: 'error', summary: t('teaching.reports.toast.exportFailedTitle'), detail: t('teaching.reports.toast.exportFailedDetail'), life: 4000 });
    } finally {
        exportingCsv.value = false;
    }
}

function filterByTeacherInDrilldown(teacher) {
    filters.employee_id = teacher.employee_id;
    activeTab.value = 'drilldown';
    filters.page = 1;
    fetchReport();
}

function clearTeacherFilter() {
    filters.employee_id = null;
    filters.page = 1;
    fetchReport();
}

function onPeriodContextChange() {
    filters.school_rombel_id = null;
    filters.page = 1;
    fetchReport();
}

function onPageChange(newPage) {
    if (newPage < 1 || newPage > drilldownMeta.last_page) return;
    filters.page = newPage;
    fetchReport();
}

function onPerPageChange() {
    filters.page = 1;
    fetchReport();
}

function openFillJournalModal(session) {
    activeSession.value = session;
    journalDialogMode.value = 'create';
    journalFormError.value = '';
    journalFieldErrors.value = {};

    journalForm.teaching_schedule_id = session.teaching_schedule_id;
    journalForm.session_date = session.session_date;
    journalForm.topic = '';
    journalForm.method = options.value.journal_methods?.[0]?.value || 'lecture';
    journalForm.summary = '';
    journalForm.notes = '';
    journalForm.actual_start_time = session.effective_start_time || '';
    journalForm.actual_end_time = session.effective_end_time || '';
    journalForm.teacher_employee_id = session.effective_employee_id;
    journalForm.correction_reason = '';

    journalDialogVisible.value = true;
}

function openViewJournalModal(session) {
    activeSession.value = session;
    journalDialogMode.value = 'view';
    journalFormError.value = '';
    journalFieldErrors.value = {};

    journalForm.teaching_schedule_id = session.teaching_schedule_id;
    journalForm.session_date = session.session_date;
    journalForm.topic = session.journal_topic || '';
    journalForm.method = session.journal_method || 'lecture';
    journalForm.summary = '';
    journalForm.notes = '';
    journalForm.actual_start_time = session.effective_start_time || '';
    journalForm.actual_end_time = session.effective_end_time || '';
    journalForm.teacher_employee_id = session.effective_employee_id;
    journalForm.correction_reason = '';

    journalDialogVisible.value = true;
}

async function submitJournalForm() {
    if (journalDialogMode.value === 'view') {
        journalDialogVisible.value = false;
        return;
    }

    journalSubmitting.value = true;
    journalFormError.value = '';
    journalFieldErrors.value = {};

    try {
        const payload = {
            teaching_schedule_id: journalForm.teaching_schedule_id,
            session_date: journalForm.session_date,
            topic: journalForm.topic,
            method: journalForm.method,
            summary: journalForm.summary,
            notes: journalForm.notes || null,
            actual_start_time: journalForm.actual_start_time || null,
            actual_end_time: journalForm.actual_end_time || null
        };

        if (canManage.value && journalForm.teacher_employee_id) {
            payload.teacher_employee_id = journalForm.teacher_employee_id;
        }

        if (canManage.value && journalForm.correction_reason && journalForm.correction_reason.trim() !== '') {
            payload.correction_reason = journalForm.correction_reason.trim();
        }

        const res = await apiLogin.post('/teaching/journals', payload);
        const replay = Boolean(res.data?.idempotent_replay);

        toast.add({
            severity: replay ? 'info' : 'success',
            summary: replay ? t('teaching.journals.toast.alreadyRecorded') : t('teaching.journals.toast.journalRecorded'),
            detail: res.data?.message || t('teaching.journals.toast.journalRecorded'),
            life: 3500
        });

        journalDialogVisible.value = false;
        await fetchReport();
    } catch (err) {
        const parsed = extractErrors(err, 'Failed to save teaching journal.');
        journalFormError.value = parsed.message;
        journalFieldErrors.value = parsed.fieldErrors;
    } finally {
        journalSubmitting.value = false;
    }
}

async function loadOptions() {
    loadingOptions.value = true;
    optionsError.value = '';

    try {
        const res = await apiLogin.get('/teaching/options');
        const payload = res.data?.data || {};
        options.value = {
            ...options.value,
            ...payload
        };

        if (!context.school_year_id) {
            context.school_year_id = payload.active_school_year_id || payload.school_years?.[0]?.id || null;
        }
        if (!context.semester_id) {
            context.semester_id = payload.active_semester_id || payload.semesters?.[0]?.id || null;
        }

        const defaultScope = !payload.can?.manage && payload.self_employee ? 'mine' : 'all';
        filters.scope = defaultScope;

        const today = payload.today_wib || new Date().toISOString().slice(0, 10);
        if (!filters.date_to) {
            filters.date_to = today;
        }
        if (!filters.date_from) {
            filters.date_from = shiftDateString(today, -30);
        }

        await fetchReport();
    } catch (err) {
        const parsed = extractErrors(err, 'Failed to load teaching options.');
        optionsError.value = parsed.message;
    } finally {
        loadingOptions.value = false;
    }
}

onMounted(() => {
    loadOptions();
});
</script>

<template>
    <div class="grid">
        <div class="col-12">
            <div class="card">
                <!-- Page Header & Academic Year / Semester Context -->
                <div class="flex flex-column lg:flex-row lg:align-items-center lg:justify-content-between gap-3 mb-4">
                    <div>
                        <h2 class="text-2xl font-semibold m-0">{{ t('teaching.reports.title') }}</h2>
                        <p class="text-color-secondary m-0 mt-1">{{ t('teaching.reports.subtitle') }}</p>
                    </div>

                    <div class="flex flex-wrap align-items-center gap-2">
                        <div class="flex align-items-center gap-2">
                            <label class="font-medium text-sm">{{ t('teaching.journals.labels.academicYear') }}</label>
                            <Select
                                v-model="context.school_year_id"
                                :options="options.school_years"
                                optionLabel="name"
                                optionValue="id"
                                :placeholder="t('teaching.schedules.selectYear')"
                                class="w-12rem"
                                :loading="loadingOptions"
                                @change="onPeriodContextChange"
                            />
                        </div>

                        <div class="flex align-items-center gap-2">
                            <label class="font-medium text-sm">{{ t('teaching.journals.labels.semester') }}</label>
                            <Select
                                v-model="context.semester_id"
                                :options="options.semesters"
                                optionLabel="label"
                                optionValue="id"
                                :placeholder="t('teaching.schedules.selectSemester')"
                                class="w-13rem"
                                :loading="loadingOptions"
                                @change="onPeriodContextChange"
                            />
                        </div>
                    </div>
                </div>

                <!-- Error Banners -->
                <div v-if="optionsError" class="p-3 mb-4 border-round bg-red-50 text-red-700 border-1 border-red-200 flex align-items-center justify-content-between">
                    <span><i class="pi pi-exclamation-triangle mr-2"></i>{{ optionsError }}</span>
                    <Button :label="t('teaching.reports.retry')" icon="pi pi-refresh" size="small" severity="danger" outlined @click="loadOptions" />
                </div>

                <div v-if="reportError" class="p-3 mb-4 border-round bg-red-50 text-red-700 border-1 border-red-200 flex align-items-center justify-content-between">
                    <span><i class="pi pi-exclamation-triangle mr-2"></i>{{ reportError }}</span>
                    <Button :label="t('teaching.reports.retry')" icon="pi pi-refresh" size="small" severity="danger" outlined @click="fetchReport" />
                </div>

                <!-- Locked Year Warning -->
                <div v-if="isSelectedYearLocked" class="p-3 mb-4 border-round bg-amber-50 text-amber-800 border-1 border-amber-300 flex align-items-center justify-content-between">
                    <div>
                        <i class="pi pi-lock mr-2"></i>
                        <strong>Locked Academic Year ({{ selectedSchoolYear?.name }}):</strong>
                        This academic year is locked. Reports are read-only.
                    </div>
                    <Tag value="Read-Only Archive" severity="warn" />
                </div>

                <!-- Context Information Bar -->
                <div class="p-3 mb-4 border-round surface-100 flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="flex flex-wrap align-items-center gap-2">
                        <Tag v-if="canManage" value="Administrator / Operator Overview (School-Wide)" severity="info" icon="pi pi-shield" />
                        <Tag v-else-if="selfEmployee" :value="`Teacher View: ${selfEmployee.name}${selfEmployee.nuptk ? ' (' + selfEmployee.nuptk + ')' : ''}`" severity="success" icon="pi pi-id-card" />
                        <Tag v-else value="Read-Only Monitoring Mode" severity="secondary" icon="pi pi-eye" />
                    </div>

                    <div class="text-sm text-color-secondary flex flex-wrap align-items-center gap-3">
                        <span> <strong>Today (WIB):</strong> {{ reportMeta.today_wib || options.today_wib || '-' }} </span>
                        <span> <strong>Grace Window:</strong> {{ reportMeta.grace_days || options.journal_grace_days }} days (since {{ reportMeta.grace_deadline_date || options.grace_deadline_date || '-' }}) </span>
                    </div>
                </div>

                <!-- Filters Toolbar -->
                <div class="p-3 mb-4 border-round surface-50 border-1 surface-border">
                    <div class="flex flex-wrap align-items-end gap-3">
                        <!-- Date From -->
                        <div class="flex flex-column gap-1">
                            <label class="text-xs font-semibold text-color-secondary">{{ t('teaching.journals.labels.dateFrom') }}</label>
                            <InputText v-model="filters.date_from" type="date" class="p-inputtext-sm w-10rem" />
                        </div>

                        <!-- Date To -->
                        <div class="flex flex-column gap-1">
                            <label class="text-xs font-semibold text-color-secondary">{{ t('teaching.journals.labels.dateTo') }}</label>
                            <InputText v-model="filters.date_to" type="date" class="p-inputtext-sm w-10rem" />
                        </div>

                        <!-- Quick Range Presets -->
                        <div class="flex flex-column gap-1">
                            <label class="text-xs font-semibold text-color-secondary">{{ t('teaching.reports.labels.presets') }}</label>
                            <div class="flex gap-1">
                                <Button :label="t('teaching.reports.quickRange.sevenDays')" size="small" severity="secondary" outlined @click="setDatePreset('7d')" />
                                <Button :label="t('teaching.reports.quickRange.thirtyDays')" size="small" severity="secondary" outlined @click="setDatePreset('30d')" />
                                <Button :label="t('teaching.reports.quickRange.thisMonth')" size="small" severity="secondary" outlined @click="setDatePreset('month')" />
                            </div>
                        </div>

                        <!-- Class / Rombel -->
                        <div class="flex flex-column gap-1">
                            <label class="text-xs font-semibold text-color-secondary">{{ t('teaching.journals.labels.classRombel') }}</label>
                            <Select v-model="filters.school_rombel_id" :options="rombelsForSelectedYear" optionLabel="name" optionValue="id" :placeholder="t('teaching.schedules.timetable.allClasses')" showClear class="w-12rem p-select-sm" />
                        </div>

                        <!-- Subject -->
                        <div class="flex flex-column gap-1">
                            <label class="text-xs font-semibold text-color-secondary">{{ t('teaching.journals.labels.subject') }}</label>
                            <Select v-model="filters.subject_id" :options="options.subjects" optionLabel="name" optionValue="id" :placeholder="t('teaching.schedules.timetable.allSubjects')" showClear class="w-12rem p-select-sm" />
                        </div>

                        <!-- Teacher (Leadership/Operator only) -->
                        <div v-if="reportMeta.can_view_all" class="flex flex-column gap-1">
                            <label class="text-xs font-semibold text-color-secondary">{{ t('teaching.journals.labels.teacher') }}</label>
                            <Select v-model="filters.employee_id" :options="options.teachers" optionLabel="name" optionValue="id" :placeholder="t('teaching.schedules.timetable.allTeachers')" showClear class="w-14rem p-select-sm" />
                        </div>

                        <!-- Status Filter -->
                        <div class="flex flex-column gap-1">
                            <label class="text-xs font-semibold text-color-secondary">{{ t('teaching.reports.labels.journalStatus') }}</label>
                            <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value" class="w-13rem p-select-sm" />
                        </div>

                        <!-- Filter & Export Actions -->
                        <div class="flex align-items-center gap-2 ml-auto">
                            <Button :label="t('teaching.reports.applyFilters')" icon="pi pi-filter" size="small" severity="primary" :loading="loadingReport" @click="fetchReport" />
                            <Button :label="t('teaching.reports.exportCsv')" icon="pi pi-download" size="small" severity="success" outlined :loading="exportingCsv" @click="exportCsv" />
                        </div>
                    </div>

                    <!-- Active Teacher Drilldown Banner -->
                    <div v-if="filters.employee_id" class="mt-3 pt-3 border-top-1 surface-border flex align-items-center justify-content-between">
                        <span class="text-sm">
                            <i class="pi pi-user mr-2 text-primary"></i>
                            {{ t('teaching.reports.filteringBy', { id: filters.employee_id }) }}
                        </span>
                        <Button :label="t('teaching.reports.summary.clearTeacherFilter')" icon="pi pi-times" size="small" severity="secondary" text @click="clearTeacherFilter" />
                    </div>
                </div>

                <!-- KPI Summary Cards -->
                <div class="grid mb-4">
                    <div class="col-12 sm:col-6 lg:col-3">
                        <div class="p-3 border-round surface-card border-1 surface-border h-full flex flex-column justify-content-between">
                            <div>
                                <div class="text-sm text-color-secondary">{{ t('teaching.reports.kpi.totalScheduled') }}</div>
                                <div class="text-2xl font-bold mt-1">{{ summaryData.total_expected_sessions }}</div>
                            </div>
                            <div class="text-xs text-color-secondary mt-2">{{ t('teaching.reports.summary.table.scheduledCol') }}</div>
                        </div>
                    </div>

                    <div class="col-12 sm:col-6 lg:col-3">
                        <div class="p-3 border-round bg-green-50 border-1 border-green-200 h-full flex flex-column justify-content-between">
                            <div>
                                <div class="text-sm text-green-700">{{ t('teaching.reports.kpi.recordedCount') }}</div>
                                <div class="text-2xl font-bold text-green-800 mt-1">{{ summaryData.total_recorded_journals }}</div>
                            </div>
                            <div class="text-xs text-green-700 mt-2">{{ t('teaching.reports.recordedStatus') }}</div>
                        </div>
                    </div>

                    <div class="col-12 sm:col-6 lg:col-3">
                        <div class="p-3 border-round bg-red-50 border-1 border-red-200 h-full flex flex-column justify-content-between">
                            <div>
                                <div class="text-sm text-red-700">{{ t('teaching.reports.kpi.missingCount') }}</div>
                                <div class="text-2xl font-bold text-red-800 mt-1">{{ summaryData.total_missing_journals }}</div>
                            </div>
                            <div class="flex gap-2 text-xs mt-2">
                                <span class="text-amber-800 font-medium">{{ t('teaching.reports.withinGrace', { count: summaryData.total_missing_within_grace }) }}</span>
                                <span class="text-color-secondary">•</span>
                                <span class="text-red-700 font-medium">{{ t('teaching.reports.overdue', { count: summaryData.total_missing_overdue }) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 sm:col-6 lg:col-3">
                        <div class="p-3 border-round surface-card border-1 surface-border h-full flex flex-column justify-content-between">
                            <div>
                                <div class="text-sm text-color-secondary">{{ t('teaching.reports.overallCompliance') }}</div>
                                <div class="flex align-items-center gap-2 mt-1">
                                    <div class="text-2xl font-bold" :class="summaryData.overall_compliance_percentage >= 90 ? 'text-green-600' : summaryData.overall_compliance_percentage >= 75 ? 'text-primary' : 'text-red-600'">
                                        {{ summaryData.overall_compliance_percentage }}%
                                    </div>
                                    <Tag
                                        :severity="summaryData.overall_compliance_percentage >= 90 ? 'success' : summaryData.overall_compliance_percentage >= 75 ? 'info' : 'danger'"
                                        :value="summaryData.overall_compliance_percentage >= 90 ? t('teaching.reports.high') : summaryData.overall_compliance_percentage >= 75 ? t('teaching.reports.moderate') : t('teaching.reports.low')"
                                    />
                                </div>
                            </div>
                            <div class="text-xs text-color-secondary mt-2">
                                {{ t('teaching.reports.excludedSlots', { count: summaryData.total_excluded_slots }) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <div class="flex flex-wrap gap-2 mb-4 border-bottom-1 surface-border pb-3">
                    <Button :label="t('teaching.reports.tabs.summary')" icon="pi pi-users" :severity="activeTab === 'summary' ? 'primary' : 'secondary'" :outlined="activeTab !== 'summary'" @click="activeTab = 'summary'" />
                    <Button :label="t('teaching.reports.tabs.drilldown')" icon="pi pi-list" :severity="activeTab === 'drilldown' ? 'primary' : 'secondary'" :outlined="activeTab !== 'drilldown'" @click="activeTab = 'drilldown'" />
                </div>

                <!-- ─── TAB 1: TEACHER COMPLIANCE SUMMARY ─────────────────────── -->
                <div v-if="activeTab === 'summary'">
                    <div v-if="loadingReport" class="p-4 text-center text-color-secondary">
                        <i class="pi pi-spin pi-spinner text-2xl mb-2"></i>
                        <div>{{ t('teaching.reports.loadingReport') }}</div>
                    </div>

                    <div v-else-if="teachersReportList.length === 0" class="p-5 text-center text-color-secondary border-round surface-50 border-1 surface-border">
                        <i class="pi pi-info-circle text-3xl mb-2"></i>
                        <div class="font-medium">{{ t('teaching.reports.noSessionsFound') }}</div>
                        <div class="text-sm mt-1">{{ t('teaching.reports.noSessionsFoundHint') }}</div>
                    </div>

                    <div v-else class="overflow-x-auto border-round surface-border border-1">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="surface-100 border-bottom-1 surface-border text-color-secondary">
                                    <th class="p-3">{{ t('teaching.reports.summary.table.teacherCol') }}</th>
                                    <th class="p-3 text-center">{{ t('teaching.reports.summary.table.scheduledCol') }}</th>
                                    <th class="p-3 text-center">{{ t('teaching.reports.summary.table.recordedCol') }}</th>
                                    <th class="p-3 text-center">{{ t('teaching.reports.missingWithinGraceCol') }}</th>
                                    <th class="p-3 text-center">{{ t('teaching.reports.missingOverdueCol') }}</th>
                                    <th class="p-3 text-center">{{ t('teaching.reports.summary.table.rateCol') }}</th>
                                    <th class="p-3 text-right">{{ t('teaching.reports.summary.table.actionsCol') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="teacher in paginatedTeachersReportList" :key="teacher.employee_id" class="border-bottom-1 surface-border hover:surface-50 transition-colors">
                                    <td class="p-3">
                                        <div class="font-semibold">{{ teacher.employee_name }}</div>
                                        <div class="text-xs text-color-secondary font-mono">NUPTK: {{ teacher.employee_nuptk || '-' }}</div>
                                    </td>
                                    <td class="p-3 text-center font-medium">{{ teacher.expected_sessions }}</td>
                                    <td class="p-3 text-center">
                                        <Tag severity="success" :value="`${teacher.recorded_journals}`" />
                                    </td>
                                    <td class="p-3 text-center">
                                        <Tag v-if="teacher.missing_within_grace > 0" severity="warn" :value="`${teacher.missing_within_grace}`" />
                                        <span v-else class="text-color-secondary">0</span>
                                    </td>
                                    <td class="p-3 text-center">
                                        <Tag v-if="teacher.missing_overdue > 0" severity="danger" :value="`${teacher.missing_overdue}`" />
                                        <span v-else class="text-color-secondary">0</span>
                                    </td>
                                    <td class="p-3 text-center">
                                        <div class="flex flex-column align-items-center gap-1">
                                            <span class="font-bold" :class="teacher.compliance_percentage >= 90 ? 'text-green-600' : teacher.compliance_percentage >= 75 ? 'text-primary' : 'text-red-600'"> {{ teacher.compliance_percentage }}% </span>
                                            <div class="w-6rem surface-200 border-round h-4px overflow-hidden">
                                                <div
                                                    class="h-full border-round"
                                                    :class="teacher.compliance_percentage >= 90 ? 'bg-green-500' : teacher.compliance_percentage >= 75 ? 'bg-primary' : 'bg-red-500'"
                                                    :style="{ width: `${Math.min(100, teacher.compliance_percentage)}%` }"
                                                ></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-3 text-right">
                                        <Button :label="t('teaching.reports.summary.viewSessions')" icon="pi pi-list" size="small" severity="secondary" outlined @click="filterByTeacherInDrilldown(teacher)" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex flex-column sm:flex-row align-items-center justify-content-between gap-3 mt-3">
                        <div class="text-sm text-color-secondary">
                            Showing <strong>{{ paginatedTeachersReportList.length }}</strong> of <strong>{{ teachersReportList.length }}</strong> teachers (Page {{ summaryPage }} of {{ summaryLastPage }})
                        </div>
                        <div class="flex align-items-center gap-2">
                            <Select v-model="summaryPerPage" :options="perPageOptions" optionLabel="label" optionValue="value" class="p-select-sm w-9rem" @change="summaryPage = 1" />
                            <Button icon="pi pi-chevron-left" size="small" severity="secondary" outlined :disabled="summaryPage <= 1" @click="summaryPage -= 1" />
                            <Button icon="pi pi-chevron-right" size="small" severity="secondary" outlined :disabled="summaryPage >= summaryLastPage" @click="summaryPage += 1" />
                        </div>
                    </div>
                </div>

                <!-- ─── TAB 2: MISSING SESSIONS DRILLDOWN ──────────────────────── -->
                <div v-else>
                    <div v-if="loadingReport" class="p-4 text-center text-color-secondary">
                        <i class="pi pi-spin pi-spinner text-2xl mb-2"></i>
                        <div>{{ t('teaching.reports.loadingDrilldown') }}</div>
                    </div>

                    <div v-else-if="drilldownList.length === 0" class="p-5 text-center text-color-secondary border-round surface-50 border-1 surface-border">
                        <i class="pi pi-check-circle text-3xl mb-2 text-green-600"></i>
                        <div class="font-medium">{{ t('teaching.reports.noSessionsDrilldown') }}</div>
                        <div class="text-sm mt-1">{{ t('teaching.reports.noSessionsDrilldownHint') }}</div>
                    </div>

                    <div v-else>
                        <div class="overflow-x-auto border-round surface-border border-1">
                            <table class="w-full text-left border-collapse text-sm">
                                <thead>
                                    <tr class="surface-100 border-bottom-1 surface-border text-color-secondary">
                                        <th class="p-3">{{ t('teaching.reports.dateTimeCol') }}</th>
                                        <th class="p-3">{{ t('teaching.reports.classSubjectCol') }}</th>
                                        <th class="p-3">Teacher</th>
                                        <th class="p-3">{{ t('teaching.reports.sourceStatusCol') }}</th>
                                        <th class="p-3">{{ t('teaching.reports.statusCol') }}</th>
                                        <th class="p-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="session in drilldownList" :key="`${session.teaching_schedule_id}-${session.session_date}`" class="border-bottom-1 surface-border hover:surface-50 transition-colors">
                                        <td class="p-3">
                                            <div class="font-semibold">{{ session.session_date }}</div>
                                            <div class="text-xs text-color-secondary">{{ session.day_label }}, {{ session.period_label }} ({{ session.time_label }})</div>
                                        </td>
                                        <td class="p-3">
                                            <div class="font-semibold">{{ session.school_rombel_name }}</div>
                                            <div class="text-xs text-color-secondary">{{ session.subject_name }}</div>
                                        </td>
                                        <td class="p-3">
                                            <div class="font-medium">{{ session.teacher_name }}</div>
                                            <div v-if="session.assigned_teacher_id !== session.teacher_employee_id" class="text-xs text-amber-700">{{ t('teaching.reports.substitute', { name: session.assigned_teacher_name }) }}</div>
                                        </td>
                                        <td class="p-3">
                                            <Tag
                                                :value="session.source_type === 'rescheduled' ? t('teaching.reports.rescheduledSlot') : t('teaching.reports.regularTimetable')"
                                                :severity="session.source_type === 'rescheduled' ? 'info' : 'secondary'"
                                            />
                                        </td>
                                        <td class="p-3">
                                            <div v-if="session.has_journal">
                                                <Tag severity="success" :value="t('teaching.reports.recordedStatus')" icon="pi pi-check" />
                                                <div class="text-xs text-color-secondary mt-1 truncate max-w-10rem" :title="session.journal_topic">
                                                    {{ session.journal_topic || '-' }}
                                                </div>
                                            </div>
                                            <div v-else-if="session.is_within_grace_window">
                                                <Tag severity="warn" :value="t('teaching.reports.missingGraceStatus')" icon="pi pi-clock" />
                                                <div class="text-xs text-amber-700 mt-1">{{ t('teaching.reports.canRecordHint') }}</div>
                                            </div>
                                            <div v-else>
                                                <Tag severity="danger" :value="t('teaching.reports.missingOverdueStatus')" icon="pi pi-exclamation-triangle" />
                                                <div class="text-xs text-red-700 mt-1">{{ t('teaching.reports.requireOverrideHint') }}</div>
                                            </div>
                                        </td>
                                        <td class="p-3 text-right">
                                            <div class="flex justify-content-end gap-1">
                                                <Button v-if="session.can_record" :label="t('teaching.reports.drilldown.fillJournal')" icon="pi pi-plus-circle" size="small" severity="success" @click="openFillJournalModal(session)" />
                                                <Button v-if="session.has_journal" :label="t('teaching.reports.drilldown.viewJournal')" icon="pi pi-eye" size="small" severity="secondary" outlined @click="openViewJournalModal(session)" />
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Footer -->
                        <div class="flex flex-column sm:flex-row align-items-center justify-content-between gap-3 mt-3">
                            <div class="text-sm text-color-secondary">
                                Showing <strong>{{ drilldownList.length }}</strong> of <strong>{{ drilldownMeta.total }}</strong> sessions (Page {{ drilldownMeta.current_page }} of {{ drilldownMeta.last_page }})
                            </div>

                            <div class="flex align-items-center gap-2">
                                <Select v-model="filters.per_page" :options="perPageOptions" optionLabel="label" optionValue="value" class="p-select-sm w-9rem" @change="onPerPageChange" />
                                <Button icon="pi pi-chevron-left" size="small" severity="secondary" outlined :disabled="drilldownMeta.current_page <= 1" @click="onPageChange(drilldownMeta.current_page - 1)" />
                                <Button icon="pi pi-chevron-right" size="small" severity="secondary" outlined :disabled="drilldownMeta.current_page >= drilldownMeta.last_page" @click="onPageChange(drilldownMeta.current_page + 1)" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── MODAL: FILL / VIEW TEACHING JOURNAL ───────────────────────────── -->
        <Dialog v-model:visible="journalDialogVisible" modal :header="journalDialogMode === 'create' ? t('teaching.journals.dialog.createTitle') : t('teaching.journals.dialog.viewTitle')" :style="{ width: '560px' }" :closable="!journalSubmitting">
            <div v-if="activeSession" class="flex flex-column gap-3">
                <!-- Session Context Banner -->
                <div class="p-3 border-round surface-100 text-sm">
                    <div class="grid">
                        <div class="col-6">
                            <span class="text-color-secondary block text-xs">Date &amp; Period</span>
                            <strong>{{ activeSession.session_date }}</strong> ({{ activeSession.day_label }}, {{ activeSession.period_label }})
                        </div>
                        <div class="col-6">
                            <span class="text-color-secondary block text-xs">Class &amp; Subject</span>
                            <strong>{{ activeSession.school_rombel_name }}</strong> - {{ activeSession.subject_name }}
                        </div>
                        <div class="col-12 mt-1">
                            <span class="text-color-secondary block text-xs">Effective Teacher</span>
                            <strong>{{ activeSession.teacher_name }}</strong>
                        </div>
                    </div>
                </div>

                <!-- Overdue Warning Banner -->
                <div v-if="activeSession.is_overdue && journalDialogMode === 'create'" class="p-3 border-round bg-amber-50 text-amber-800 border-1 border-amber-300 text-xs">
                    <i class="pi pi-info-circle mr-1"></i>
                    <strong>Overdue Session:</strong> The 7-day grace window for self-recording has passed. An operator correction reason is required.
                </div>

                <!-- Form Error Banner -->
                <div v-if="journalFormError" class="p-3 border-round bg-red-50 text-red-700 border-1 border-red-200 text-xs">
                    <i class="pi pi-exclamation-triangle mr-1"></i>
                    {{ journalFormError }}
                </div>

                <!-- Topic -->
                <div class="flex flex-column gap-1">
                    <label class="text-xs font-semibold">{{ t('teaching.reports.labels.topicRequired') }}</label>
                    <InputText v-model="journalForm.topic" :disabled="journalDialogMode === 'view' || journalSubmitting" :placeholder="t('teaching.journals.dialog.form.topicPlaceholder')" :class="{ 'p-invalid': journalFieldErrors.topic }" />
                    <small v-if="journalFieldErrors.topic" class="text-red-500 text-xs">{{ journalFieldErrors.topic[0] }}</small>
                </div>

                <!-- Method -->
                <div class="flex flex-column gap-1">
                    <label class="text-xs font-semibold">{{ t('teaching.reports.labels.methodRequired') }}</label>
                    <Select
                        v-model="journalForm.method"
                        :options="options.journal_methods"
                        optionLabel="label"
                        optionValue="value"
                        :disabled="journalDialogMode === 'view' || journalSubmitting"
                        :placeholder="t('teaching.journals.dialog.form.selectMethod')"
                    />
                </div>

                <!-- Actual Times -->
                <div class="grid">
                    <div class="col-6">
                        <label class="text-xs font-semibold block mb-1">{{ t('teaching.journals.labels.actualStartTime') }}</label>
                        <InputText v-model="journalForm.actual_start_time" type="time" :disabled="journalDialogMode === 'view' || journalSubmitting" class="w-full" />
                    </div>
                    <div class="col-6">
                        <label class="text-xs font-semibold block mb-1">{{ t('teaching.journals.labels.actualEndTime') }}</label>
                        <InputText v-model="journalForm.actual_end_time" type="time" :disabled="journalDialogMode === 'view' || journalSubmitting" class="w-full" />
                    </div>
                </div>

                <!-- Summary -->
                <div class="flex flex-column gap-1">
                    <label class="text-xs font-semibold">{{ t('teaching.reports.labels.activitySummary') }}</label>
                    <Textarea v-model="journalForm.summary" rows="3" :disabled="journalDialogMode === 'view' || journalSubmitting" :placeholder="t('teaching.journals.dialog.form.activitiesPlaceholder')" />
                </div>

                <!-- Notes -->
                <div class="flex flex-column gap-1">
                    <label class="text-xs font-semibold">{{ t('teaching.reports.labels.teacherNotes') }}</label>
                    <Textarea v-model="journalForm.notes" rows="2" :disabled="journalDialogMode === 'view' || journalSubmitting" :placeholder="t('teaching.journals.dialog.form.notesPlaceholder')" />
                </div>

                <!-- Teacher Selector (Manage only) -->
                <div v-if="canManage && journalDialogMode === 'create'" class="flex flex-column gap-1">
                    <label class="text-xs font-semibold">{{ t('teaching.reports.labels.teacherAttribution') }}</label>
                    <Select v-model="journalForm.teacher_employee_id" :options="options.teachers" optionLabel="name" optionValue="id" :placeholder="t('teaching.schedules.dialogs.assignment.selectTeacher')" class="w-full" />
                </div>

                <!-- Correction Reason (Required if overdue) -->
                <div v-if="canManage && (activeSession.is_overdue || journalForm.correction_reason)" class="flex flex-column gap-1">
                    <label class="text-xs font-semibold">{{ t('teaching.reports.labels.correctionReasonRequired') }}</label>
                    <InputText
                        v-model="journalForm.correction_reason"
                        :disabled="journalDialogMode === 'view' || journalSubmitting"
                        :placeholder="t('teaching.journals.dialog.form.graceReasonPlaceholder')"
                        :class="{ 'p-invalid': journalFieldErrors.correction_reason }"
                    />
                    <small v-if="journalFieldErrors.correction_reason" class="text-red-500 text-xs">{{ journalFieldErrors.correction_reason[0] }}</small>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-content-end gap-2">
                    <Button :label="t('teaching.journals.dialog.buttons.close')" severity="secondary" outlined :disabled="journalSubmitting" @click="journalDialogVisible = false" />
                    <Button v-if="journalDialogMode === 'create'" :label="t('teaching.journals.dialog.buttons.save')" icon="pi pi-check" severity="primary" :loading="journalSubmitting" @click="submitJournalForm" />
                </div>
            </template>
        </Dialog>
    </div>
</template>
