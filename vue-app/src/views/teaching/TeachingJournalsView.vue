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
const canCreateJournals = computed(() => Boolean(options.value?.can?.create_journals ?? (canManage.value || store.can('teaching.journals.create'))));
const selfEmployee = computed(() => options.value?.self_employee || null);

const context = reactive({
    school_year_id: null,
    semester_id: null
});

const activeTab = ref('sessions');

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

// ─── Tab 1: Daily Sessions Worklist State ─────────────────────────────────────
const sessionDateMode = ref('single'); // 'single' | 'range'
const sessionFilters = reactive({
    date: '',
    date_from: '',
    date_to: '',
    scope: 'all',
    status: 'all',
    school_rombel_id: null,
    subject_id: null,
    employee_id: null
});

const sessionsLoading = ref(false);
const sessionsError = ref('');
const sessionList = ref([]);
const sessionPage = ref(1);
const sessionPerPage = ref(10);
const sessionLastPage = computed(() => Math.max(1, Math.ceil((sessionList.value?.length || 0) / sessionPerPage.value)));
const paginatedSessionList = computed(() => {
    const start = (sessionPage.value - 1) * sessionPerPage.value;
    return (sessionList.value || []).slice(start, start + sessionPerPage.value);
});
const sessionSummary = reactive({
    total_slots: 0,
    effective_sessions: 0,
    recorded_journals: 0,
    missing_journals: 0,
    excluded_slots: 0
});
const sessionMeta = reactive({
    today_wib: '',
    grace_days: 7,
    grace_deadline_date: '',
    date_from: '',
    date_to: ''
});

// ─── Tab 2: Recorded Journals History State ───────────────────────────────────
const journalFilters = reactive({
    scope: 'all',
    school_rombel_id: null,
    subject_id: null,
    employee_id: null,
    method: '',
    source_type: '',
    date_from: '',
    date_to: '',
    search: '',
    page: 1,
    per_page: 10
});

const journalsLoading = ref(false);
const journalsError = ref('');
const journalList = ref([]);
const journalMeta = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0
});

// ─── Journal Modal State (Fill / Edit / View) ─────────────────────────────────
const journalDialogVisible = ref(false);
const journalDialogMode = ref('create'); // 'create' | 'edit' | 'view'
const journalSubmitting = ref(false);
const journalFormError = ref('');
const journalFieldErrors = ref({});
const journalConflictState = reactive({
    active: false,
    message: '',
    latest: null
});

const activeSessionContext = ref(null);
const activeJournalRecord = ref(null);

const journalForm = reactive({
    id: null,
    teaching_schedule_id: null,
    session_date: '',
    topic: '',
    method: 'lecture',
    summary: '',
    notes: '',
    actual_start_time: '',
    actual_end_time: '',
    teacher_employee_id: null,
    correction_reason: '',
    expected_version: 1
});

const statusFilterOptions = computed(() => [
    { value: 'all', label: t('teaching.journals.filters.allSlots') },
    { value: 'effective', label: t('teaching.journals.filters.effectiveOnly') },
    { value: 'missing', label: t('teaching.journals.filters.missingOnly') },
    { value: 'recorded', label: t('teaching.journals.filters.recordedOnly') },
    { value: 'excluded', label: t('teaching.journals.filters.excludedOnly') }
]);

const scopeOptions = computed(() => {
    const items = [{ value: 'all', label: t('teaching.journals.filters.allTeachers') }];
    if (selfEmployee.value) {
        items.unshift({ value: 'mine', label: t('teaching.journals.filters.mySessionsWithName', { name: selfEmployee.value.name }) });
    } else if (!canManage.value) {
        items.unshift({ value: 'mine', label: t('teaching.journals.filters.mySessions') });
    }
    return items;
});

const perPageOptions = computed(() => [
    { value: 10, label: t('teaching.journals.pagination.perPage', { count: 10 }) },
    { value: 20, label: t('teaching.journals.pagination.perPage', { count: 20 }) },
    { value: 50, label: t('teaching.journals.pagination.perPage', { count: 50 }) },
    { value: 100, label: t('teaching.journals.pagination.perPage', { count: 100 }) }
]);

const dialogNeedsCorrectionReason = computed(() => {
    if (!canManage.value || journalDialogMode.value === 'view') {
        return false;
    }
    const deadline = options.value.grace_deadline_date || sessionMeta.grace_deadline_date;
    const isPastGrace = Boolean(deadline && journalForm.session_date && journalForm.session_date < deadline);

    if (journalDialogMode.value === 'create') {
        const effectiveTeacherId = Number(activeSessionContext.value?.effective_employee_id || 0);
        const selectedTeacherId = Number(journalForm.teacher_employee_id || 0);
        return isPastGrace || (effectiveTeacherId > 0 && selectedTeacherId > 0 && effectiveTeacherId !== selectedTeacherId);
    }

    const originalTeacherId = Number(activeJournalRecord.value?.teacher_employee_id || 0);
    const selectedTeacherId = Number(journalForm.teacher_employee_id || 0);
    const selfId = Number(selfEmployee.value?.id || 0);
    const isOtherTeacher = selfId === 0 || selfId !== originalTeacherId;
    const isTeacherChanged = originalTeacherId > 0 && selectedTeacherId > 0 && originalTeacherId !== selectedTeacherId;

    return isPastGrace || isOtherTeacher || isTeacherChanged;
});

function extractErrors(error, fallbackMessage) {
    const response = error?.response;
    if (!response) {
        return { message: fallbackMessage, fieldErrors: {}, conflict: false, latest: null };
    }
    const data = response.data || {};
    if (response.status === 409) {
        return {
            message: data.message || t('teaching.journals.dialog.conflictMsg'),
            fieldErrors: {},
            conflict: true,
            latest: data.latest || null
        };
    }
    const fieldErrors = data.errors && typeof data.errors === 'object' ? data.errors : {};
    const firstFieldError = Object.values(fieldErrors).flat()[0];
    return {
        message: firstFieldError || data.message || fallbackMessage,
        fieldErrors,
        conflict: false,
        latest: null
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

function stepSessionDate(deltaDays) {
    sessionDateMode.value = 'single';
    sessionFilters.date = shiftDateString(sessionFilters.date, deltaDays);
    fetchSessions();
}

function setSessionToday() {
    sessionDateMode.value = 'single';
    sessionFilters.date = options.value.today_wib || new Date().toISOString().slice(0, 10);
    fetchSessions();
}

function clampDefaultDateToSchoolYear(year, todayWib) {
    const fallback = todayWib || new Date().toISOString().slice(0, 10);
    if (!year) {
        return fallback;
    }
    if (year.start_date && fallback < year.start_date) {
        return year.start_date;
    }
    if (year.end_date && fallback > year.end_date) {
        return year.end_date;
    }
    return fallback;
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
        sessionFilters.scope = defaultScope;
        journalFilters.scope = defaultScope;

        const yearObj = (payload.school_years || []).find((y) => Number(y.id) === Number(context.school_year_id));
        const defaultDate = clampDefaultDateToSchoolYear(yearObj, payload.today_wib);
        if (!sessionFilters.date) {
            sessionFilters.date = defaultDate;
        }
        if (!sessionFilters.date_from) {
            sessionFilters.date_from = defaultDate;
            sessionFilters.date_to = defaultDate;
        }

        await refreshCurrentTab();
    } catch (err) {
        const parsed = extractErrors(err, 'Failed to load teaching options.');
        optionsError.value = parsed.message;
    } finally {
        loadingOptions.value = false;
    }
}

async function onPeriodContextChange() {
    sessionFilters.school_rombel_id = null;
    journalFilters.school_rombel_id = null;
    journalFilters.page = 1;

    const yearObj = selectedSchoolYear.value;
    if (yearObj) {
        const clamped = clampDefaultDateToSchoolYear(yearObj, options.value.today_wib);
        if ((yearObj.start_date && sessionFilters.date < yearObj.start_date) || (yearObj.end_date && sessionFilters.date > yearObj.end_date)) {
            sessionFilters.date = clamped;
            sessionFilters.date_from = clamped;
            sessionFilters.date_to = clamped;
        }
    }

    await refreshCurrentTab();
}

async function switchTab(tab) {
    activeTab.value = tab;
    await refreshCurrentTab();
}

async function refreshCurrentTab() {
    if (!context.school_year_id || !context.semester_id) {
        return;
    }
    if (activeTab.value === 'sessions') {
        await fetchSessions();
    } else {
        await fetchJournals();
    }
}

async function fetchSessions() {
    sessionPage.value = 1;
    if (!context.school_year_id || !context.semester_id) {
        return;
    }
    sessionsLoading.value = true;
    sessionsError.value = '';
    try {
        const params = {
            school_year_id: context.school_year_id,
            semester_id: context.semester_id,
            scope: sessionFilters.scope,
            status: sessionFilters.status
        };

        if (sessionDateMode.value === 'range') {
            if (sessionFilters.date_from) params.date_from = sessionFilters.date_from;
            if (sessionFilters.date_to) params.date_to = sessionFilters.date_to;
        } else if (sessionFilters.date) {
            params.date = sessionFilters.date;
        }

        if (sessionFilters.school_rombel_id) params.school_rombel_id = sessionFilters.school_rombel_id;
        if (sessionFilters.subject_id) params.subject_id = sessionFilters.subject_id;
        if (sessionFilters.employee_id) params.employee_id = sessionFilters.employee_id;

        const res = await apiLogin.get('/teaching/sessions', { params });
        sessionList.value = res.data?.data || [];

        const sum = res.data?.summary || {};
        sessionSummary.total_slots = Number(sum.total_slots || 0);
        sessionSummary.effective_sessions = Number(sum.effective_sessions || 0);
        sessionSummary.recorded_journals = Number(sum.recorded_journals || 0);
        sessionSummary.missing_journals = Number(sum.missing_journals || 0);
        sessionSummary.excluded_slots = Number(sum.excluded_slots || 0);

        const meta = res.data?.meta || {};
        sessionMeta.today_wib = meta.today_wib || options.value.today_wib;
        sessionMeta.grace_days = Number(meta.grace_days || options.value.journal_grace_days || 7);
        sessionMeta.grace_deadline_date = meta.grace_deadline_date || options.value.grace_deadline_date;
        sessionMeta.date_from = meta.date_from || '';
        sessionMeta.date_to = meta.date_to || '';
    } catch (err) {
        const parsed = extractErrors(err, 'Failed to load teaching sessions.');
        sessionsError.value = parsed.message;
    } finally {
        sessionsLoading.value = false;
    }
}

function resetSessionFilters() {
    const defaultScope = !canManage.value && selfEmployee.value ? 'mine' : 'all';
    const defaultDate = clampDefaultDateToSchoolYear(selectedSchoolYear.value, options.value.today_wib);
    sessionDateMode.value = 'single';
    sessionFilters.date = defaultDate;
    sessionFilters.date_from = defaultDate;
    sessionFilters.date_to = defaultDate;
    sessionFilters.scope = defaultScope;
    sessionFilters.status = 'all';
    sessionFilters.school_rombel_id = null;
    sessionFilters.subject_id = null;
    sessionFilters.employee_id = null;
    fetchSessions();
}

async function fetchJournals() {
    if (!context.school_year_id || !context.semester_id) {
        return;
    }
    journalsLoading.value = true;
    journalsError.value = '';
    try {
        const params = {
            school_year_id: context.school_year_id,
            semester_id: context.semester_id,
            scope: journalFilters.scope,
            page: journalFilters.page,
            per_page: journalFilters.per_page
        };
        if (journalFilters.school_rombel_id) params.school_rombel_id = journalFilters.school_rombel_id;
        if (journalFilters.subject_id) params.subject_id = journalFilters.subject_id;
        if (journalFilters.employee_id) params.employee_id = journalFilters.employee_id;
        if (journalFilters.method) params.method = journalFilters.method;
        if (journalFilters.source_type) params.source_type = journalFilters.source_type;
        if (journalFilters.date_from) params.date_from = journalFilters.date_from;
        if (journalFilters.date_to) params.date_to = journalFilters.date_to;
        if (journalFilters.search && journalFilters.search.trim() !== '') {
            params.search = journalFilters.search.trim();
        }

        const res = await apiLogin.get('/teaching/journals', { params });
        journalList.value = res.data?.data || [];

        const meta = res.data?.meta || {};
        journalMeta.current_page = Number(meta.current_page || 1);
        journalMeta.last_page = Number(meta.last_page || 1);
        journalMeta.per_page = Number(meta.per_page || journalFilters.per_page);
        journalMeta.total = Number(meta.total || 0);
    } catch (err) {
        const parsed = extractErrors(err, 'Failed to load recorded teaching journals.');
        journalsError.value = parsed.message;
    } finally {
        journalsLoading.value = false;
    }
}

function resetJournalFilters() {
    const defaultScope = !canManage.value && selfEmployee.value ? 'mine' : 'all';
    journalFilters.scope = defaultScope;
    journalFilters.school_rombel_id = null;
    journalFilters.subject_id = null;
    journalFilters.employee_id = null;
    journalFilters.method = '';
    journalFilters.source_type = '';
    journalFilters.date_from = '';
    journalFilters.date_to = '';
    journalFilters.search = '';
    journalFilters.page = 1;
    journalFilters.per_page = 20;
    fetchJournals();
}

function changeJournalPage(newPage) {
    if (newPage < 1 || newPage > journalMeta.last_page) {
        return;
    }
    journalFilters.page = newPage;
    fetchJournals();
}

function clearDialogState() {
    journalFormError.value = '';
    journalFieldErrors.value = {};
    journalConflictState.active = false;
    journalConflictState.message = '';
    journalConflictState.latest = null;
}

function openCreateJournalFromSession(session) {
    clearDialogState();
    journalDialogMode.value = 'create';
    activeSessionContext.value = session;
    activeJournalRecord.value = null;

    journalForm.id = null;
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
    journalForm.expected_version = 1;

    journalDialogVisible.value = true;
}

async function openSessionJournalDialog(session, mode = 'view') {
    if (!session.journal?.id) {
        return;
    }
    await openJournalById(session.journal.id, mode, session);
}

async function openJournalById(journalId, mode = 'view', sessionFallback = null) {
    clearDialogState();
    journalDialogMode.value = mode;
    activeSessionContext.value = sessionFallback;

    try {
        const res = await apiLogin.get(`/teaching/journals/${journalId}`);
        const detail = res.data?.data;
        if (!detail) {
            return;
        }
        applyJournalDetailToForm(detail);
        journalDialogVisible.value = true;
    } catch (err) {
        const parsed = extractErrors(err, 'Failed to load teaching journal details.');
        toast.add({ severity: 'error', summary: t('teaching.journals.toast.errorTitle'), detail: parsed.message, life: 4000 });
    }
}

function applyJournalDetailToForm(detail) {
    activeJournalRecord.value = detail;
    journalForm.id = detail.id;
    journalForm.teaching_schedule_id = detail.teaching_schedule_id;
    journalForm.session_date = detail.session_date;
    journalForm.topic = detail.topic || '';
    journalForm.method = detail.method || 'lecture';
    journalForm.summary = detail.summary || '';
    journalForm.notes = detail.notes || '';
    journalForm.actual_start_time = detail.actual_start_time || '';
    journalForm.actual_end_time = detail.actual_end_time || '';
    journalForm.teacher_employee_id = detail.teacher_employee_id;
    journalForm.correction_reason = detail.correction_reason || '';
    journalForm.expected_version = Number(detail.version || 1);
}

function reloadFromConflict() {
    if (!journalConflictState.latest) {
        return;
    }
    const latest = journalConflictState.latest;
    applyJournalDetailToForm(latest);
    journalDialogMode.value = latest.can_update ? 'edit' : 'view';
    clearDialogState();
    toast.add({ severity: 'info', summary: t('teaching.journals.dialog.reloadLatest'), detail: t('teaching.journals.toast.reloadedLatest', { version: latest.version }), life: 3500 });
}

async function submitJournalForm() {
    if (journalDialogMode.value === 'view') {
        journalDialogVisible.value = false;
        return;
    }

    journalSubmitting.value = true;
    journalFormError.value = '';
    journalFieldErrors.value = {};
    journalConflictState.active = false;

    try {
        if (journalDialogMode.value === 'create') {
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
        } else {
            const payload = {
                expected_version: journalForm.expected_version,
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

            const res = await apiLogin.put(`/teaching/journals/${journalForm.id}`, payload);
            toast.add({ severity: 'success', summary: t('teaching.journals.toast.journalUpdated'), detail: res.data?.message || t('teaching.journals.toast.journalUpdated'), life: 3500 });
        }

        journalDialogVisible.value = false;
        await fetchSessions();
        if (activeTab.value === 'journals') {
            await fetchJournals();
        }
    } catch (err) {
        const parsed = extractErrors(err, 'Failed to save teaching journal.');
        journalFormError.value = parsed.message;
        journalFieldErrors.value = parsed.fieldErrors;
        if (parsed.conflict) {
            journalConflictState.active = true;
            journalConflictState.message = parsed.message;
            journalConflictState.latest = parsed.latest;
        }
    } finally {
        journalSubmitting.value = false;
    }
}

function getSessionStatusSeverity(session) {
    if (!session.is_effective) {
        return session.session_status === 'holiday' ? 'info' : 'warn';
    }
    return session.has_journal ? 'success' : 'danger';
}

function getSessionStatusLabel(session) {
    if (!session.is_effective) {
        if (session.session_status === 'holiday') return 'Holiday';
        if (session.session_status === 'cancelled') return 'Cancelled';
        if (session.session_status === 'rescheduled_out') return 'Rescheduled Out';
        return 'Excluded';
    }
    return session.has_journal ? 'Recorded' : 'Missing Journal';
}

onMounted(() => {
    loadOptions();
});
</script>

<template>
    <div class="grid">
        <div class="col-12">
            <div class="card">
                <!-- Header & Period Context -->
                <div class="flex flex-column lg:flex-row lg:align-items-center lg:justify-content-between gap-3 mb-4">
                    <div>
                        <h2 class="text-2xl font-semibold m-0">Teaching Sessions &amp; Journals</h2>
                        <p class="text-color-secondary m-0 mt-1">Evaluate daily effective teaching sessions, record classroom learning journals, and review historical instruction logs.</p>
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

                <!-- Options Error Banner -->
                <div v-if="optionsError" class="p-3 mb-4 border-round bg-red-50 text-red-700 border-1 border-red-200 flex align-items-center justify-content-between">
                    <span><i class="pi pi-exclamation-triangle mr-2"></i>{{ optionsError }}</span>
                    <Button :label="t('common.action.retry')" icon="pi pi-refresh" size="small" severity="danger" outlined @click="loadOptions" />
                </div>

                <!-- Locked School Year Banner -->
                <div v-if="isSelectedYearLocked" class="p-3 mb-4 border-round bg-amber-50 text-amber-800 border-1 border-amber-300 flex align-items-center justify-content-between">
                    <div>
                        <i class="pi pi-lock mr-2"></i>
                        <strong>Locked Academic Year ({{ selectedSchoolYear?.name }}):</strong>
                        Recording or editing teaching journals is disabled while this academic year is locked.
                    </div>
                    <Tag value="Read-Only Archive" severity="warn" />
                </div>

                <!-- Actor & Grace Window Context Bar -->
                <div class="p-3 mb-4 border-round surface-100 flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="flex flex-wrap align-items-center gap-2">
                        <Tag v-if="canManage" value="Admin / Operator Override Enabled" severity="info" icon="pi pi-shield" />
                        <Tag v-else-if="selfEmployee" :value="`Linked Teacher: ${selfEmployee.name}${selfEmployee.nuptk ? ' (' + selfEmployee.nuptk + ')' : ''}`" severity="success" icon="pi pi-id-card" />
                        <Tag v-else-if="canCreateJournals" value="No Linked Teacher Profile (Cannot Self-Record)" severity="warn" icon="pi pi-exclamation-circle" />
                        <Tag v-else value="Read-Only Monitoring Mode" severity="secondary" icon="pi pi-eye" />
                    </div>

                    <div class="text-sm text-color-secondary flex flex-wrap align-items-center gap-3">
                        <span> <strong>Today (WIB):</strong> {{ options.today_wib || sessionMeta.today_wib || '-' }} </span>
                        <span> <strong>Self-Service Grace Window:</strong> {{ options.journal_grace_days }} days (since {{ options.grace_deadline_date || sessionMeta.grace_deadline_date || '-' }}) </span>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <div class="flex flex-wrap gap-2 mb-4 border-bottom-1 surface-border pb-3">
                    <Button :label="t('teaching.journals.tabs.worklist')" icon="pi pi-calendar-clock" :severity="activeTab === 'sessions' ? 'primary' : 'secondary'" :outlined="activeTab !== 'sessions'" @click="switchTab('sessions')" />
                    <Button :label="t('teaching.journals.tabs.history')" icon="pi pi-book" :severity="activeTab === 'journals' ? 'primary' : 'secondary'" :outlined="activeTab !== 'journals'" @click="switchTab('journals')" />
                </div>

                <!-- ─── TAB 1: DAILY SESSIONS WORKLIST ─────────────────────────── -->
                <div v-if="activeTab === 'sessions'">
                    <!-- Summary Cards -->
                    <div class="grid mb-3">
                        <div class="col-12 sm:col-6 lg:col-3">
                            <div class="p-3 border-round surface-card border-1 surface-border">
                                <div class="text-sm text-color-secondary">Effective Sessions</div>
                                <div class="text-2xl font-bold mt-1">{{ sessionSummary.effective_sessions }}</div>
                                <div class="text-xs text-color-secondary mt-1">Out of {{ sessionSummary.total_slots }} evaluated slots</div>
                            </div>
                        </div>
                        <div class="col-12 sm:col-6 lg:col-3">
                            <div class="p-3 border-round bg-green-50 border-1 border-green-200">
                                <div class="text-sm text-green-700">Recorded Journals</div>
                                <div class="text-2xl font-bold text-green-800 mt-1">{{ sessionSummary.recorded_journals }}</div>
                                <div class="text-xs text-green-700 mt-1">Completed instruction logs</div>
                            </div>
                        </div>
                        <div class="col-12 sm:col-6 lg:col-3">
                            <div class="p-3 border-round bg-red-50 border-1 border-red-200">
                                <div class="text-sm text-red-700">Missing Journals</div>
                                <div class="text-2xl font-bold text-red-800 mt-1">{{ sessionSummary.missing_journals }}</div>
                                <div class="text-xs text-red-700 mt-1">Effective sessions without journal</div>
                            </div>
                        </div>
                        <div class="col-12 sm:col-6 lg:col-3">
                            <div class="p-3 border-round surface-100 border-1 surface-border">
                                <div class="text-sm text-color-secondary">Excluded Slots</div>
                                <div class="text-2xl font-bold mt-1">{{ sessionSummary.excluded_slots }}</div>
                                <div class="text-xs text-color-secondary mt-1">Holiday, cancelled, or rescheduled out</div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters Toolbar -->
                    <div class="p-3 mb-3 border-round surface-50 border-1 surface-border">
                        <div class="flex flex-wrap align-items-end gap-3">
                            <div>
                                <label class="block text-xs font-medium mb-1">{{ t('teaching.journals.labels.dateFilterMode') }}</label>
                                <div class="flex gap-1">
                                    <Button
                                        :label="t('teaching.journals.worklist.singleDate')"
                                        size="small"
                                        :severity="sessionDateMode === 'single' ? 'primary' : 'secondary'"
                                        :outlined="sessionDateMode !== 'single'"
                                        @click="
                                            sessionDateMode = 'single';
                                            fetchSessions();
                                        "
                                    />
                                    <Button
                                        :label="t('teaching.journals.worklist.dateRange')"
                                        size="small"
                                        :severity="sessionDateMode === 'range' ? 'primary' : 'secondary'"
                                        :outlined="sessionDateMode !== 'range'"
                                        @click="
                                            sessionDateMode = 'range';
                                            fetchSessions();
                                        "
                                    />
                                </div>
                            </div>

                            <div v-if="sessionDateMode === 'single'" class="flex align-items-end gap-1">
                                <div>
                                    <label class="block text-xs font-medium mb-1">{{ t('teaching.journals.labels.sessionDate') }}</label>
                                    <InputText v-model="sessionFilters.date" type="date" class="w-10rem" @change="fetchSessions" />
                                </div>
                                <Button icon="pi pi-chevron-left" size="small" severity="secondary" outlined title="Previous Day" @click="stepSessionDate(-1)" />
                                <Button :label="t('teaching.journals.worklist.today')" size="small" severity="secondary" outlined @click="setSessionToday" />
                                <Button icon="pi pi-chevron-right" size="small" severity="secondary" outlined title="Next Day" @click="stepSessionDate(1)" />
                            </div>

                            <template v-else>
                                <div>
                                    <label class="block text-xs font-medium mb-1">{{ t('teaching.journals.labels.fromDate') }}</label>
                                    <InputText v-model="sessionFilters.date_from" type="date" class="w-10rem" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1">{{ t('teaching.journals.labels.toDate') }}</label>
                                    <InputText v-model="sessionFilters.date_to" type="date" class="w-10rem" />
                                </div>
                            </template>

                            <div>
                                <label class="block text-xs font-medium mb-1">{{ t('teaching.journals.labels.scope') }}</label>
                                <Select v-model="sessionFilters.scope" :options="scopeOptions" optionLabel="label" optionValue="value" class="w-13rem" @change="fetchSessions" />
                            </div>

                            <div>
                                <label class="block text-xs font-medium mb-1">{{ t('teaching.journals.labels.sessionStatus') }}</label>
                                <Select v-model="sessionFilters.status" :options="statusFilterOptions" optionLabel="label" optionValue="value" class="w-14rem" @change="fetchSessions" />
                            </div>

                            <div>
                                <label class="block text-xs font-medium mb-1">{{ t('teaching.journals.labels.classRombel') }}</label>
                                <Select
                                    v-model="sessionFilters.school_rombel_id"
                                    :options="rombelsForSelectedYear"
                                    optionLabel="name"
                                    optionValue="id"
                                    :placeholder="t('teaching.schedules.timetable.allClasses')"
                                    showClear
                                    class="w-11rem"
                                    @change="fetchSessions"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-medium mb-1">{{ t('teaching.journals.labels.subject') }}</label>
                                <Select
                                    v-model="sessionFilters.subject_id"
                                    :options="options.subjects"
                                    optionLabel="name"
                                    optionValue="id"
                                    :placeholder="t('teaching.schedules.timetable.allSubjects')"
                                    showClear
                                    class="w-12rem"
                                    @change="fetchSessions"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-medium mb-1">{{ t('teaching.journals.labels.teacher') }}</label>
                                <Select
                                    v-model="sessionFilters.employee_id"
                                    :options="options.teachers"
                                    optionLabel="name"
                                    optionValue="id"
                                    :placeholder="t('teaching.schedules.timetable.allTeachers')"
                                    showClear
                                    class="w-13rem"
                                    @change="fetchSessions"
                                />
                            </div>

                            <div class="flex gap-2">
                                <Button :label="t('teaching.journals.worklist.apply')" icon="pi pi-search" size="small" :loading="sessionsLoading" @click="fetchSessions" />
                                <Button :label="t('teaching.journals.worklist.reset')" icon="pi pi-filter-slash" size="small" severity="secondary" outlined @click="resetSessionFilters" />
                            </div>
                        </div>
                    </div>

                    <!-- Error Banner -->
                    <div v-if="sessionsError" class="p-3 mb-3 border-round bg-red-50 text-red-700 border-1 border-red-200"><i class="pi pi-exclamation-circle mr-2"></i>{{ sessionsError }}</div>

                    <!-- Sessions Table -->
                    <div class="overflow-x-auto border-1 surface-border border-round">
                        <table class="w-full text-sm" style="border-collapse: collapse">
                            <thead>
                                <tr class="surface-100 text-left border-bottom-1 surface-border">
                                    <th class="p-3">Date &amp; Day</th>
                                    <th class="p-3">Period &amp; Time</th>
                                    <th class="p-3">Class &amp; Subject</th>
                                    <th class="p-3">Effective Teacher</th>
                                    <th class="p-3">Session &amp; Journal Status</th>
                                    <th class="p-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="sessionsLoading">
                                    <td colspan="6" class="p-4 text-center text-color-secondary"><i class="pi pi-spin pi-spinner mr-2"></i>Evaluating teaching sessions...</td>
                                </tr>
                                <tr v-else-if="sessionList.length === 0">
                                    <td colspan="6" class="p-4 text-center text-color-secondary">No teaching sessions found for the selected date and filter criteria.</td>
                                </tr>
                                <tr v-for="session in paginatedSessionList" :key="session.session_key" class="border-bottom-1 surface-border" :class="{ 'surface-50 opacity-80': !session.is_effective }">
                                    <td class="p-3 vertical-align-top">
                                        <div class="font-semibold">{{ session.session_date }}</div>
                                        <div class="text-xs text-color-secondary">{{ session.day_label }}</div>
                                        <div class="mt-1">
                                            <Tag v-if="session.source_type === 'rescheduled'" value="Rescheduled In" severity="info" class="text-xs" />
                                        </div>
                                    </td>

                                    <td class="p-3 vertical-align-top">
                                        <div class="font-medium">Period {{ session.effective_period_start }}–{{ session.effective_period_end }}</div>
                                        <div class="text-xs text-color-secondary">{{ session.effective_start_time }} – {{ session.effective_end_time }}</div>
                                        <div v-if="session.effective_room" class="text-xs mt-1"><i class="pi pi-map-marker text-xs mr-1"></i>{{ session.effective_room }}</div>
                                    </td>

                                    <td class="p-3 vertical-align-top">
                                        <div class="font-semibold">{{ session.school_rombel_name }}</div>
                                        <div class="text-color-secondary">{{ session.subject_name }}</div>
                                    </td>

                                    <td class="p-3 vertical-align-top">
                                        <div class="font-medium">{{ session.effective_employee_name }}</div>
                                        <div v-if="session.is_substitute" class="text-xs text-blue-700 mt-1">
                                            <Tag value="Substitute" severity="info" class="text-xs mr-1" />
                                            for {{ session.assigned_employee_name }}
                                        </div>
                                    </td>

                                    <td class="p-3 vertical-align-top">
                                        <div class="flex flex-wrap align-items-center gap-1">
                                            <Tag :value="getSessionStatusLabel(session)" :severity="getSessionStatusSeverity(session)" />
                                            <Tag v-if="session.journal?.method_label" :value="session.journal.method_label" severity="secondary" />
                                            <Tag v-if="session.journal?.correction_reason" value="Corrected" severity="warn" title="Recorded or updated via admin/operator override" />
                                        </div>

                                        <div v-if="session.journal" class="mt-2">
                                            <div class="font-medium text-sm">{{ session.journal.topic }}</div>
                                            <div class="text-xs text-color-secondary line-clamp-2">{{ session.journal.summary }}</div>
                                        </div>
                                        <div v-else-if="session.exception" class="text-xs text-color-secondary mt-1">
                                            {{ session.exception.reason }}
                                            <span v-if="session.exception.rescheduled_date && session.session_status === 'rescheduled_out'"> (Moved to {{ session.exception.rescheduled_date }}) </span>
                                        </div>

                                        <div v-if="session.block_reason && !session.can_record && !session.can_update" class="text-xs text-color-secondary mt-1"><i class="pi pi-info-circle mr-1"></i>{{ session.block_reason }}</div>
                                    </td>

                                    <td class="p-3 vertical-align-top text-right">
                                        <div class="flex justify-content-end gap-1">
                                            <Button v-if="session.can_record" :label="t('teaching.journals.worklist.fillJournal')" icon="pi pi-plus-circle" size="small" severity="success" @click="openCreateJournalFromSession(session)" />
                                            <Button v-if="session.has_journal" :label="t('teaching.journals.worklist.viewJournal')" icon="pi pi-eye" size="small" severity="secondary" outlined @click="openSessionJournalDialog(session, 'view')" />
                                            <Button v-if="session.can_update" :label="t('teaching.journals.worklist.editJournal')" icon="pi pi-pencil" size="small" severity="primary" outlined @click="openSessionJournalDialog(session, 'edit')" />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex flex-wrap align-items-center justify-content-between gap-3 mt-3">
                        <div class="text-sm text-color-secondary">
                            Total sessions: <strong>{{ sessionList.length }}</strong> (Page {{ sessionPage }} of {{ sessionLastPage }})
                        </div>
                        <div class="flex align-items-center gap-2">
                            <Select v-model="sessionPerPage" :options="perPageOptions" optionLabel="label" optionValue="value" class="w-9rem" @change="sessionPage = 1" />
                            <Button icon="pi pi-chevron-left" size="small" severity="secondary" outlined :disabled="sessionPage <= 1" @click="sessionPage -= 1" />
                            <Button icon="pi pi-chevron-right" size="small" severity="secondary" outlined :disabled="sessionPage >= sessionLastPage" @click="sessionPage += 1" />
                        </div>
                    </div>
                </div>

                <!-- ─── TAB 2: RECORDED JOURNALS HISTORY ───────────────────────── -->
                <div v-else>
                    <!-- Filters Toolbar -->
                    <div class="p-3 mb-3 border-round surface-50 border-1 surface-border">
                        <div class="flex flex-wrap align-items-end gap-3">
                            <div>
                                <label class="block text-xs font-medium mb-1">{{ t('teaching.journals.labels.scope') }}</label>
                                <Select
                                    v-model="journalFilters.scope"
                                    :options="scopeOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    class="w-13rem"
                                    @change="
                                        journalFilters.page = 1;
                                        fetchJournals();
                                    "
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-medium mb-1">{{ t('teaching.journals.labels.dateFrom') }}</label>
                                <InputText v-model="journalFilters.date_from" type="date" class="w-10rem" />
                            </div>

                            <div>
                                <label class="block text-xs font-medium mb-1">{{ t('teaching.journals.labels.dateTo') }}</label>
                                <InputText v-model="journalFilters.date_to" type="date" class="w-10rem" />
                            </div>

                            <div>
                                <label class="block text-xs font-medium mb-1">{{ t('teaching.journals.labels.classRombel') }}</label>
                                <Select
                                    v-model="journalFilters.school_rombel_id"
                                    :options="rombelsForSelectedYear"
                                    optionLabel="name"
                                    optionValue="id"
                                    :placeholder="t('teaching.schedules.timetable.allClasses')"
                                    showClear
                                    class="w-11rem"
                                    @change="
                                        journalFilters.page = 1;
                                        fetchJournals();
                                    "
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-medium mb-1">{{ t('teaching.journals.labels.subject') }}</label>
                                <Select
                                    v-model="journalFilters.subject_id"
                                    :options="options.subjects"
                                    optionLabel="name"
                                    optionValue="id"
                                    :placeholder="t('teaching.schedules.timetable.allSubjects')"
                                    showClear
                                    class="w-12rem"
                                    @change="
                                        journalFilters.page = 1;
                                        fetchJournals();
                                    "
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-medium mb-1">{{ t('teaching.journals.labels.teacher') }}</label>
                                <Select
                                    v-model="journalFilters.employee_id"
                                    :options="options.teachers"
                                    optionLabel="name"
                                    optionValue="id"
                                    :placeholder="t('teaching.schedules.timetable.allTeachers')"
                                    showClear
                                    class="w-13rem"
                                    @change="
                                        journalFilters.page = 1;
                                        fetchJournals();
                                    "
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-medium mb-1">{{ t('teaching.journals.labels.method') }}</label>
                                <Select
                                    v-model="journalFilters.method"
                                    :options="options.journal_methods"
                                    optionLabel="label"
                                    optionValue="value"
                                    :placeholder="t('teaching.journals.history.allMethods')"
                                    showClear
                                    class="w-11rem"
                                    @change="
                                        journalFilters.page = 1;
                                        fetchJournals();
                                    "
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-medium mb-1">{{ t('teaching.journals.labels.keywordSearch') }}</label>
                                <InputText
                                    v-model="journalFilters.search"
                                    :placeholder="t('teaching.journals.history.searchPlaceholder')"
                                    class="w-14rem"
                                    @keyup.enter="
                                        journalFilters.page = 1;
                                        fetchJournals();
                                    "
                                />
                            </div>

                            <div class="flex gap-2">
                                <Button
                                    :label="t('teaching.journals.worklist.apply')"
                                    icon="pi pi-search"
                                    size="small"
                                    :loading="journalsLoading"
                                    @click="
                                        journalFilters.page = 1;
                                        fetchJournals();
                                    "
                                />
                                <Button :label="t('teaching.journals.worklist.reset')" icon="pi pi-filter-slash" size="small" severity="secondary" outlined @click="resetJournalFilters" />
                            </div>
                        </div>
                    </div>

                    <!-- Error Banner -->
                    <div v-if="journalsError" class="p-3 mb-3 border-round bg-red-50 text-red-700 border-1 border-red-200"><i class="pi pi-exclamation-circle mr-2"></i>{{ journalsError }}</div>

                    <!-- Journals History Table -->
                    <div class="overflow-x-auto border-1 surface-border border-round">
                        <table class="w-full text-sm" style="border-collapse: collapse">
                            <thead>
                                <tr class="surface-100 text-left border-bottom-1 surface-border">
                                    <th class="p-3">Session Date</th>
                                    <th class="p-3">Class &amp; Subject</th>
                                    <th class="p-3">Teacher</th>
                                    <th class="p-3">Topic &amp; Summary</th>
                                    <th class="p-3">Method &amp; Time</th>
                                    <th class="p-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="journalsLoading">
                                    <td colspan="6" class="p-4 text-center text-color-secondary"><i class="pi pi-spin pi-spinner mr-2"></i>Loading recorded teaching journals...</td>
                                </tr>
                                <tr v-else-if="journalList.length === 0">
                                    <td colspan="6" class="p-4 text-center text-color-secondary">No recorded teaching journals match the current filters.</td>
                                </tr>
                                <tr v-for="item in journalList" :key="item.id" class="border-bottom-1 surface-border">
                                    <td class="p-3 vertical-align-top">
                                        <div class="font-semibold">{{ item.session_date }}</div>
                                        <div class="text-xs text-color-secondary">{{ item.day_label }}</div>
                                        <Tag v-if="item.source_type === 'rescheduled'" value="Rescheduled" severity="info" class="text-xs mt-1" />
                                    </td>

                                    <td class="p-3 vertical-align-top">
                                        <div class="font-semibold">{{ item.school_rombel_name }}</div>
                                        <div class="text-color-secondary">{{ item.subject_name }}</div>
                                    </td>

                                    <td class="p-3 vertical-align-top">
                                        <div class="font-medium">{{ item.teacher_employee_name }}</div>
                                        <div v-if="item.teacher_employee_nuptk" class="text-xs text-color-secondary">
                                            {{ item.teacher_employee_nuptk }}
                                        </div>
                                        <Tag v-if="item.correction_reason" value="Admin Override" severity="warn" class="text-xs mt-1" />
                                    </td>

                                    <td class="p-3 vertical-align-top">
                                        <div class="font-semibold">{{ item.topic }}</div>
                                        <div class="text-xs text-color-secondary mt-1">{{ item.summary }}</div>
                                        <div v-if="item.notes" class="text-xs text-color-secondary mt-1"><strong>Notes:</strong> {{ item.notes }}</div>
                                    </td>

                                    <td class="p-3 vertical-align-top">
                                        <Tag :value="item.method_label" severity="secondary" />
                                        <div class="text-xs text-color-secondary mt-1">{{ item.actual_start_time }} – {{ item.actual_end_time }}</div>
                                        <div class="text-xs text-color-secondary mt-1">v{{ item.version }}</div>
                                    </td>

                                    <td class="p-3 vertical-align-top text-right">
                                        <div class="flex justify-content-end gap-1">
                                            <Button :label="t('teaching.journals.worklist.viewJournal')" icon="pi pi-eye" size="small" severity="secondary" outlined @click="openJournalById(item.id, 'view')" />
                                            <Button v-if="item.can_update" :label="t('teaching.journals.worklist.editJournal')" icon="pi pi-pencil" size="small" severity="primary" outlined @click="openJournalById(item.id, 'edit')" />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Controls -->
                    <div class="flex flex-wrap align-items-center justify-content-between gap-3 mt-3">
                        <div class="text-sm text-color-secondary">
                            Total recorded journals: <strong>{{ journalMeta.total }}</strong> (Page {{ journalMeta.current_page }} of {{ journalMeta.last_page }})
                        </div>

                        <div class="flex align-items-center gap-2">
                            <Select
                                v-model="journalFilters.per_page"
                                :options="perPageOptions"
                                optionLabel="label"
                                optionValue="value"
                                class="w-9rem"
                                @change="
                                    journalFilters.page = 1;
                                    fetchJournals();
                                "
                            />
                            <Button icon="pi pi-chevron-left" size="small" severity="secondary" outlined :disabled="journalMeta.current_page <= 1" @click="changeJournalPage(journalMeta.current_page - 1)" />
                            <Button icon="pi pi-chevron-right" size="small" severity="secondary" outlined :disabled="journalMeta.current_page >= journalMeta.last_page" @click="changeJournalPage(journalMeta.current_page + 1)" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── DIALOG: FILL / EDIT / VIEW JOURNAL ─────────────────────────────── -->
        <Dialog
            v-model:visible="journalDialogVisible"
            :header="journalDialogMode === 'create' ? t('teaching.journals.dialog.createTitle') : journalDialogMode === 'edit' ? t('teaching.journals.dialog.editTitle') : t('teaching.journals.dialog.viewTitle')"
            modal
            :style="{ width: '44rem', maxWidth: '96vw' }"
        >
            <!-- Session Snapshot Card -->
            <div class="p-3 mb-3 border-round surface-100 border-1 surface-border text-sm">
                <div class="grid">
                    <div class="col-12 sm:col-6">
                        <div>
                            <strong>Session Date:</strong>
                            {{ activeJournalRecord?.session_date || activeSessionContext?.session_date || journalForm.session_date }}
                            ({{ activeJournalRecord?.day_label || activeSessionContext?.day_label || '' }})
                        </div>
                        <div class="mt-1">
                            <strong>Class &amp; Subject:</strong>
                            {{ activeJournalRecord?.school_rombel_name || activeSessionContext?.school_rombel_name }} —
                            {{ activeJournalRecord?.subject_name || activeSessionContext?.subject_name }}
                        </div>
                    </div>
                    <div class="col-12 sm:col-6">
                        <div>
                            <strong>Source Type:</strong>
                            {{ activeJournalRecord?.source_type_label || activeSessionContext?.source_type_label || 'Regular' }}
                        </div>
                        <div class="mt-1">
                            <strong>Assigned / Effective Teacher:</strong>
                            {{ activeJournalRecord?.teacher_employee_name || activeSessionContext?.effective_employee_name }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- 409 Conflict Banner -->
            <div v-if="journalConflictState.active" class="p-3 mb-3 border-round bg-amber-50 text-amber-900 border-1 border-amber-300 flex align-items-center justify-content-between gap-2">
                <div>
                    <i class="pi pi-exclamation-triangle mr-2"></i>
                    <strong>Version Conflict:</strong> {{ journalConflictState.message }}
                </div>
                <Button v-if="journalConflictState.latest" :label="t('teaching.journals.dialog.reloadLatest')" icon="pi pi-refresh" size="small" severity="warn" @click="reloadFromConflict" />
            </div>

            <!-- General Form Error -->
            <div v-else-if="journalFormError" class="p-3 mb-3 border-round bg-red-50 text-red-700 border-1 border-red-200"><i class="pi pi-times-circle mr-2"></i>{{ journalFormError }}</div>

            <div class="grid">
                <div class="col-12 sm:col-8">
                    <label class="block text-sm font-medium mb-1">{{ t('teaching.journals.labels.learningTopicRequired') }}</label>
                    <InputText v-model="journalForm.topic" class="w-full" :placeholder="t('teaching.journals.dialog.form.topicPlaceholder')" :disabled="journalDialogMode === 'view'" />
                </div>

                <div class="col-12 sm:col-4">
                    <label class="block text-sm font-medium mb-1">{{ t('teaching.journals.labels.methodRequired') }}</label>
                    <Select v-model="journalForm.method" :options="options.journal_methods" optionLabel="label" optionValue="value" class="w-full" :disabled="journalDialogMode === 'view'" />
                </div>

                <div class="col-12 sm:col-6">
                    <label class="block text-xs font-medium mb-1">{{ t('teaching.journals.labels.actualStartTime') }}</label>
                    <InputText v-model="journalForm.actual_start_time" type="time" class="w-full" :disabled="journalDialogMode === 'view'" />
                </div>

                <div class="col-12 sm:col-6">
                    <label class="block text-xs font-medium mb-1">{{ t('teaching.journals.labels.actualEndTime') }}</label>
                    <InputText v-model="journalForm.actual_end_time" type="time" class="w-full" :disabled="journalDialogMode === 'view'" />
                </div>

                <div class="col-12">
                    <label class="block text-sm font-medium mb-1">{{ t('teaching.journals.labels.activitySummaryRequired') }}</label>
                    <Textarea v-model="journalForm.summary" rows="3" class="w-full" :placeholder="t('teaching.journals.dialog.form.activitiesPlaceholder')" :disabled="journalDialogMode === 'view'" />
                </div>

                <div class="col-12">
                    <label class="block text-xs font-medium mb-1">{{ t('teaching.journals.labels.notesOptional') }}</label>
                    <Textarea v-model="journalForm.notes" rows="2" class="w-full" :placeholder="t('teaching.journals.dialog.form.notesPlaceholder')" :disabled="journalDialogMode === 'view'" />
                </div>

                <!-- Admin / Operator Teacher Override & Correction Reason -->
                <div v-if="canManage && journalDialogMode !== 'view'" class="col-12">
                    <div class="p-3 border-round surface-50 border-1 surface-border">
                        <div class="font-semibold text-sm mb-2"><i class="pi pi-shield mr-1"></i>Admin / Operator Governance Controls</div>
                        <div class="grid">
                            <div class="col-12 sm:col-6">
                                <label class="block text-xs font-medium mb-1">{{ t('teaching.journals.labels.executingTeacher') }}</label>
                                <Select v-model="journalForm.teacher_employee_id" :options="options.teachers" optionLabel="name" optionValue="id" class="w-full" />
                            </div>
                            <div class="col-12 sm:col-6">
                                <label class="block text-xs font-medium mb-1">
                                    {{ t('teaching.journals.labels.correctionReasonRequired') }}
                                    <span v-if="dialogNeedsCorrectionReason" class="text-red-500">*</span>
                                </label>
                                <InputText v-model="journalForm.correction_reason" class="w-full" :placeholder="t('teaching.journals.dialog.form.graceReasonPlaceholder')" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Audit Metadata in View Mode -->
                <div v-if="journalDialogMode === 'view' && activeJournalRecord" class="col-12">
                    <div class="p-3 border-round surface-50 border-1 surface-border text-xs text-color-secondary">
                        <div>
                            <strong>Version:</strong> v{{ activeJournalRecord.version }}
                            <span v-if="activeJournalRecord.created_by_name"> | <strong>Created by:</strong> {{ activeJournalRecord.created_by_name }}</span>
                            <span v-if="activeJournalRecord.updated_by_name"> | <strong>Updated by:</strong> {{ activeJournalRecord.updated_by_name }}</span>
                        </div>
                        <div v-if="activeJournalRecord.correction_reason" class="mt-1 text-amber-800">
                            <strong>Admin/Operator Correction Reason:</strong> {{ activeJournalRecord.correction_reason }}
                            <span v-if="activeJournalRecord.corrected_by_name"> ({{ activeJournalRecord.corrected_by_name }})</span>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-content-end gap-2">
                    <Button :label="journalDialogMode === 'view' ? t('teaching.journals.dialog.buttons.close') : t('teaching.journals.dialog.buttons.cancel')" severity="secondary" outlined @click="journalDialogVisible = false" />
                    <Button v-if="journalDialogMode === 'view' && activeJournalRecord?.can_update" :label="t('teaching.journals.dialog.buttons.switchToEdit')" icon="pi pi-pencil" @click="journalDialogMode = 'edit'" />
                    <Button
                        v-if="journalDialogMode !== 'view'"
                        :label="journalDialogMode === 'create' ? t('teaching.journals.dialog.buttons.record') : t('teaching.journals.dialog.buttons.save')"
                        icon="pi pi-check"
                        :loading="journalSubmitting"
                        @click="submitJournalForm"
                    />
                </div>
            </template>
        </Dialog>
    </div>
</template>
