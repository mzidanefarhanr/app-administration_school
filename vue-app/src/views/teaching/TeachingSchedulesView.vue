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
    can: { view: false, manage: false },
    active_school_year_id: null,
    active_semester_id: null,
    actor_employee_id: null,
    school_years: [],
    semesters: [],
    rombels: [],
    subjects: [],
    teachers: [],
    days_of_week: [],
    period_presets: [],
    exception_types: [],
    journal_grace_days: 7
});

const canManage = computed(() => Boolean(options.value?.can?.manage ?? store.can('teaching.manage')));

const context = reactive({
    school_year_id: null,
    semester_id: null
});

const activeTab = ref('timetable');
const timetableMode = ref('matrix');

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

// ─── Tab 1: Timetable & Schedules State ───────────────────────────────────────
const scheduleFilters = reactive({
    school_rombel_id: null,
    employee_id: null,
    subject_id: null,
    day_of_week: null,
    room: '',
    status: 'active',
    search: '',
    page: 1,
    per_page: 50
});

const schedulesLoading = ref(false);
const schedulesError = ref('');
const scheduleList = ref([]);
const scheduleMatrix = ref([]);
const scheduleMeta = reactive({ current_page: 1, per_page: 50, total: 0, last_page: 1 });

// ─── Tab 2: Teaching Assignments State ────────────────────────────────────────
const assignmentFilters = reactive({
    school_rombel_id: null,
    subject_id: null,
    employee_id: null,
    status: 'active',
    search: '',
    page: 1,
    per_page: 20
});

const assignmentsLoading = ref(false);
const assignmentsError = ref('');
const assignmentList = ref([]);
const assignmentMeta = reactive({ current_page: 1, per_page: 20, total: 0, last_page: 1 });
const allPeriodAssignments = ref([]);

// ─── Tab 3: Schedule Exceptions State ─────────────────────────────────────────
const exceptionFilters = reactive({
    exception_type: 'all',
    school_rombel_id: null,
    employee_id: null,
    date_from: '',
    date_to: '',
    search: '',
    page: 1,
    per_page: 20
});

const exceptionsLoading = ref(false);
const exceptionsError = ref('');
const exceptionList = ref([]);
const exceptionMeta = reactive({ current_page: 1, per_page: 20, total: 0, last_page: 1 });
const allPeriodSchedules = ref([]);

function extractErrors(error, fallbackMessage) {
    const response = error?.response;
    if (!response) {
        return { message: fallbackMessage, fieldErrors: {}, conflict: false, latest: null };
    }
    const data = response.data || {};
    if (response.status === 409) {
        return {
            message: data.message || 'Record was modified by another user. Please reload the latest version.',
            fieldErrors: {},
            conflict: true,
            latest: data.latest || null
        };
    }
    const rawErrors = data.errors || {};
    const fieldErrors = {};
    let firstFieldMsg = '';
    Object.keys(rawErrors).forEach((key) => {
        const arr = Array.isArray(rawErrors[key]) ? rawErrors[key] : [String(rawErrors[key])];
        fieldErrors[key] = arr[0] || '';
        if (!firstFieldMsg && arr[0]) {
            firstFieldMsg = arr[0];
        }
    });
    return {
        message: firstFieldMsg || data.message || fallbackMessage,
        fieldErrors,
        conflict: false,
        latest: null
    };
}

async function loadOptions() {
    loadingOptions.value = true;
    optionsError.value = '';
    try {
        const res = await apiLogin.get('/teaching/options');
        const payload = res?.data?.data || {};
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
    } catch (err) {
        optionsError.value = err?.response?.data?.message || 'Failed to load teaching options.';
    } finally {
        loadingOptions.value = false;
    }
}

async function loadAllPeriodAssignments() {
    if (!context.school_year_id || !context.semester_id) {
        allPeriodAssignments.value = [];
        return;
    }
    try {
        const res = await apiLogin.get('/teaching/assignments', {
            params: {
                school_year_id: context.school_year_id,
                semester_id: context.semester_id,
                status: 'all',
                per_page: 200
            }
        });
        allPeriodAssignments.value = res?.data?.data || [];
    } catch {
        allPeriodAssignments.value = [];
    }
}

async function loadAllPeriodSchedules() {
    if (!context.school_year_id || !context.semester_id) {
        allPeriodSchedules.value = [];
        return;
    }
    try {
        const res = await apiLogin.get('/teaching/schedules', {
            params: {
                school_year_id: context.school_year_id,
                semester_id: context.semester_id,
                status: 'active',
                per_page: 300
            }
        });
        allPeriodSchedules.value = res?.data?.data || [];
    } catch {
        allPeriodSchedules.value = [];
    }
}

async function loadSchedules() {
    if (!context.school_year_id || !context.semester_id) {
        return;
    }
    schedulesLoading.value = true;
    schedulesError.value = '';
    try {
        const params = {
            school_year_id: context.school_year_id,
            semester_id: context.semester_id,
            view_mode: timetableMode.value,
            status: scheduleFilters.status,
            page: scheduleFilters.page,
            per_page: scheduleFilters.per_page
        };
        if (scheduleFilters.school_rombel_id) params.school_rombel_id = scheduleFilters.school_rombel_id;
        if (scheduleFilters.employee_id) params.employee_id = scheduleFilters.employee_id;
        if (scheduleFilters.subject_id) params.subject_id = scheduleFilters.subject_id;
        if (scheduleFilters.day_of_week) params.day_of_week = scheduleFilters.day_of_week;
        if (scheduleFilters.room.trim()) params.room = scheduleFilters.room.trim();
        if (scheduleFilters.search.trim()) params.search = scheduleFilters.search.trim();

        const res = await apiLogin.get('/teaching/schedules', { params });
        scheduleList.value = res?.data?.data || [];
        scheduleMatrix.value = res?.data?.matrix || [];
        Object.assign(scheduleMeta, res?.data?.meta || { current_page: 1, per_page: 50, total: 0, last_page: 1 });
    } catch (err) {
        schedulesError.value = err?.response?.data?.message || 'Failed to load teaching schedules.';
    } finally {
        schedulesLoading.value = false;
    }
}

async function loadAssignments() {
    if (!context.school_year_id || !context.semester_id) {
        return;
    }
    assignmentsLoading.value = true;
    assignmentsError.value = '';
    try {
        const params = {
            school_year_id: context.school_year_id,
            semester_id: context.semester_id,
            status: assignmentFilters.status,
            page: assignmentFilters.page,
            per_page: assignmentFilters.per_page
        };
        if (assignmentFilters.school_rombel_id) params.school_rombel_id = assignmentFilters.school_rombel_id;
        if (assignmentFilters.subject_id) params.subject_id = assignmentFilters.subject_id;
        if (assignmentFilters.employee_id) params.employee_id = assignmentFilters.employee_id;
        if (assignmentFilters.search.trim()) params.search = assignmentFilters.search.trim();

        const res = await apiLogin.get('/teaching/assignments', { params });
        assignmentList.value = res?.data?.data || [];
        Object.assign(assignmentMeta, res?.data?.meta || { current_page: 1, per_page: 20, total: 0, last_page: 1 });
    } catch (err) {
        assignmentsError.value = err?.response?.data?.message || 'Failed to load teaching assignments.';
    } finally {
        assignmentsLoading.value = false;
    }
}

async function loadExceptions() {
    if (!context.school_year_id || !context.semester_id) {
        return;
    }
    exceptionsLoading.value = true;
    exceptionsError.value = '';
    try {
        const params = {
            school_year_id: context.school_year_id,
            semester_id: context.semester_id,
            exception_type: exceptionFilters.exception_type,
            page: exceptionFilters.page,
            per_page: exceptionFilters.per_page
        };
        if (exceptionFilters.school_rombel_id) params.school_rombel_id = exceptionFilters.school_rombel_id;
        if (exceptionFilters.employee_id) params.employee_id = exceptionFilters.employee_id;
        if (exceptionFilters.date_from) params.date_from = exceptionFilters.date_from;
        if (exceptionFilters.date_to) params.date_to = exceptionFilters.date_to;
        if (exceptionFilters.search.trim()) params.search = exceptionFilters.search.trim();

        const res = await apiLogin.get('/teaching/exceptions', { params });
        exceptionList.value = res?.data?.data || [];
        Object.assign(exceptionMeta, res?.data?.meta || { current_page: 1, per_page: 20, total: 0, last_page: 1 });
    } catch (err) {
        exceptionsError.value = err?.response?.data?.message || 'Failed to load schedule exceptions.';
    } finally {
        exceptionsLoading.value = false;
    }
}

async function refreshAllForContext() {
    scheduleFilters.school_rombel_id = null;
    assignmentFilters.school_rombel_id = null;
    exceptionFilters.school_rombel_id = null;
    scheduleFilters.page = 1;
    assignmentFilters.page = 1;
    exceptionFilters.page = 1;
    await Promise.all([loadAllPeriodAssignments(), loadAllPeriodSchedules(), loadSchedules(), loadAssignments(), loadExceptions()]);
}

function filterMySchedule() {
    if (!options.value.actor_employee_id) return;
    scheduleFilters.employee_id = Number(options.value.actor_employee_id);
    scheduleFilters.page = 1;
    loadSchedules();
}

// ─── Assignment Dialog ────────────────────────────────────────────────────────
const assignmentDialog = reactive({
    visible: false,
    isEdit: false,
    saving: false,
    id: null,
    expected_version: 1,
    school_rombel_id: null,
    subject_id: null,
    employee_id: null,
    hours_per_week: 2,
    status: 'active',
    notes: '',
    errorMessage: '',
    fieldErrors: {},
    conflict: false,
    latest: null
});

const selectedTeacherForAssignment = computed(() => {
    return (options.value.teachers || []).find((t) => Number(t.id) === Number(assignmentDialog.employee_id)) || null;
});

function onAssignmentTeacherChange() {
    if (!assignmentDialog.isEdit && selectedTeacherForAssignment.value?.subject_id && !assignmentDialog.subject_id) {
        assignmentDialog.subject_id = Number(selectedTeacherForAssignment.value.subject_id);
    }
}

function openNewAssignmentDialog() {
    Object.assign(assignmentDialog, {
        visible: true,
        isEdit: false,
        saving: false,
        id: null,
        expected_version: 1,
        school_rombel_id: assignmentFilters.school_rombel_id || rombelsForSelectedYear.value[0]?.id || null,
        subject_id: assignmentFilters.subject_id || null,
        employee_id: assignmentFilters.employee_id || null,
        hours_per_week: 2,
        status: 'active',
        notes: '',
        errorMessage: '',
        fieldErrors: {},
        conflict: false,
        latest: null
    });
}

function openEditAssignmentDialog(row) {
    Object.assign(assignmentDialog, {
        visible: true,
        isEdit: true,
        saving: false,
        id: row.id,
        expected_version: row.version,
        school_rombel_id: row.school_rombel_id,
        subject_id: row.subject_id,
        employee_id: row.employee_id,
        hours_per_week: row.hours_per_week,
        status: row.status,
        notes: row.notes || '',
        errorMessage: '',
        fieldErrors: {},
        conflict: false,
        latest: null
    });
}

function reloadLatestAssignment() {
    if (!assignmentDialog.latest) return;
    openEditAssignmentDialog(assignmentDialog.latest);
    loadAssignments();
    loadAllPeriodAssignments();
}

async function saveAssignment() {
    assignmentDialog.saving = true;
    assignmentDialog.errorMessage = '';
    assignmentDialog.fieldErrors = {};
    assignmentDialog.conflict = false;
    try {
        const payload = {
            school_year_id: context.school_year_id,
            semester_id: context.semester_id,
            school_rombel_id: assignmentDialog.school_rombel_id,
            subject_id: assignmentDialog.subject_id,
            employee_id: assignmentDialog.employee_id,
            hours_per_week: assignmentDialog.hours_per_week !== '' && assignmentDialog.hours_per_week !== null ? Number(assignmentDialog.hours_per_week) : null,
            status: assignmentDialog.status,
            notes: assignmentDialog.notes || null
        };
        if (assignmentDialog.isEdit) {
            payload.expected_version = assignmentDialog.expected_version;
            await apiLogin.put(`/teaching/assignments/${assignmentDialog.id}`, payload);
            toast.add({ severity: 'success', summary: t('teaching.schedules.dialogs.assignment.save'), detail: t('teaching.schedules.toast.assignmentUpdated'), life: 3000 });
        } else {
            await apiLogin.post('/teaching/assignments', payload);
            toast.add({ severity: 'success', summary: t('teaching.schedules.dialogs.assignment.create'), detail: t('teaching.schedules.toast.assignmentCreated'), life: 3000 });
        }
        assignmentDialog.visible = false;
        await Promise.all([loadAssignments(), loadAllPeriodAssignments(), loadSchedules()]);
    } catch (err) {
        const parsed = extractErrors(err, 'Failed to save teaching assignment.');
        assignmentDialog.errorMessage = parsed.message;
        assignmentDialog.fieldErrors = parsed.fieldErrors;
        assignmentDialog.conflict = parsed.conflict;
        assignmentDialog.latest = parsed.latest;
    } finally {
        assignmentDialog.saving = false;
    }
}

async function removeAssignment(row) {
    try {
        await apiLogin.delete(`/teaching/assignments/${row.id}`);
        toast.add({ severity: 'success', summary: t('teaching.schedules.assignments.table.actionsCol'), detail: t('teaching.schedules.toast.assignmentDeleted'), life: 3000 });
        await Promise.all([loadAssignments(), loadAllPeriodAssignments(), loadSchedules()]);
    } catch (err) {
        const parsed = extractErrors(err, 'Failed to delete teaching assignment.');
        toast.add({ severity: 'error', summary: t('teaching.schedules.toast.cannotDeleteAssignment'), detail: parsed.message, life: 5000 });
    }
}

// ─── Schedule Slot Dialog ─────────────────────────────────────────────────────
const scheduleDialog = reactive({
    visible: false,
    isEdit: false,
    saving: false,
    id: null,
    expected_version: 1,
    teaching_assignment_id: null,
    day_of_week: 1,
    period_start: 1,
    period_end: 2,
    start_time: '07:00',
    end_time: '08:30',
    room: '',
    status: 'active',
    notes: '',
    errorMessage: '',
    fieldErrors: {},
    conflict: false,
    latest: null
});

const assignmentDropdownOptions = computed(() => {
    return (allPeriodAssignments.value || []).map((a) => {
        const classLabel = a.school_rombel?.name || `#${a.school_rombel_id}`;
        const subjLabel = a.subject?.name || `#${a.subject_id}`;
        const teacherLabel = a.employee?.name || `#${a.employee_id}`;
        const statusSuffix = a.status === 'inactive' ? ` [${t('teaching.schedules.dialogs.assignment.inactive')}]` : '';
        return {
            id: a.id,
            label: `${classLabel} — ${subjLabel} (${teacherLabel})${statusSuffix}`
        };
    });
});

function syncPresetTimesForSchedule() {
    const presets = options.value.period_presets || [];
    const startPreset = presets.find((p) => Number(p.period) === Number(scheduleDialog.period_start));
    if (Number(scheduleDialog.period_end) < Number(scheduleDialog.period_start)) {
        scheduleDialog.period_end = Number(scheduleDialog.period_start);
    }
    const endPreset = presets.find((p) => Number(p.period) === Number(scheduleDialog.period_end));
    if (startPreset) {
        scheduleDialog.start_time = startPreset.start_time;
    }
    if (endPreset) {
        scheduleDialog.end_time = endPreset.end_time;
    }
}

function openNewScheduleDialog(preselectedAssignmentId = null) {
    const firstAssignment = preselectedAssignmentId || allPeriodAssignments.value[0]?.id || null;
    Object.assign(scheduleDialog, {
        visible: true,
        isEdit: false,
        saving: false,
        id: null,
        expected_version: 1,
        teaching_assignment_id: firstAssignment,
        day_of_week: scheduleFilters.day_of_week || 1,
        period_start: 1,
        period_end: 2,
        start_time: '07:00',
        end_time: '08:30',
        room: '',
        status: 'active',
        notes: '',
        errorMessage: '',
        fieldErrors: {},
        conflict: false,
        latest: null
    });
    syncPresetTimesForSchedule();
}

function openEditScheduleDialog(slot) {
    Object.assign(scheduleDialog, {
        visible: true,
        isEdit: true,
        saving: false,
        id: slot.id,
        expected_version: slot.version,
        teaching_assignment_id: slot.teaching_assignment_id,
        day_of_week: slot.day_of_week,
        period_start: slot.period_start,
        period_end: slot.period_end,
        start_time: slot.start_time,
        end_time: slot.end_time,
        room: slot.room || '',
        status: slot.status,
        notes: slot.notes || '',
        errorMessage: '',
        fieldErrors: {},
        conflict: false,
        latest: null
    });
}

function reloadLatestSchedule() {
    if (!scheduleDialog.latest) return;
    openEditScheduleDialog(scheduleDialog.latest);
    loadSchedules();
    loadAllPeriodSchedules();
}

async function saveSchedule() {
    scheduleDialog.saving = true;
    scheduleDialog.errorMessage = '';
    scheduleDialog.fieldErrors = {};
    scheduleDialog.conflict = false;
    try {
        const payload = {
            teaching_assignment_id: scheduleDialog.teaching_assignment_id,
            day_of_week: Number(scheduleDialog.day_of_week),
            period_start: Number(scheduleDialog.period_start),
            period_end: Number(scheduleDialog.period_end),
            start_time: scheduleDialog.start_time,
            end_time: scheduleDialog.end_time,
            room: scheduleDialog.room.trim() || null,
            status: scheduleDialog.status,
            notes: scheduleDialog.notes.trim() || null
        };
        if (scheduleDialog.isEdit) {
            payload.expected_version = scheduleDialog.expected_version;
            await apiLogin.put(`/teaching/schedules/${scheduleDialog.id}`, payload);
            toast.add({ severity: 'success', summary: t('teaching.schedules.dialogs.scheduleSlot.save'), detail: t('teaching.schedules.toast.scheduleUpdated'), life: 3000 });
        } else {
            await apiLogin.post('/teaching/schedules', payload);
            toast.add({ severity: 'success', summary: t('teaching.schedules.dialogs.scheduleSlot.create'), detail: t('teaching.schedules.toast.scheduleCreated'), life: 3000 });
        }
        scheduleDialog.visible = false;
        await Promise.all([loadSchedules(), loadAllPeriodSchedules(), loadAssignments(), loadAllPeriodAssignments()]);
    } catch (err) {
        const parsed = extractErrors(err, 'Failed to save schedule slot.');
        scheduleDialog.errorMessage = parsed.message;
        scheduleDialog.fieldErrors = parsed.fieldErrors;
        scheduleDialog.conflict = parsed.conflict;
        scheduleDialog.latest = parsed.latest;
    } finally {
        scheduleDialog.saving = false;
    }
}

async function removeSchedule(slot) {
    try {
        await apiLogin.delete(`/teaching/schedules/${slot.id}`);
        toast.add({ severity: 'success', summary: t('teaching.schedules.timetable.newSlot'), detail: t('teaching.schedules.toast.scheduleDeleted'), life: 3000 });
        await Promise.all([loadSchedules(), loadAllPeriodSchedules(), loadAssignments(), loadAllPeriodAssignments()]);
    } catch (err) {
        const parsed = extractErrors(err, 'Failed to delete schedule slot.');
        toast.add({ severity: 'error', summary: t('teaching.schedules.toast.cannotDeleteSlot'), detail: parsed.message, life: 5000 });
    }
}

// ─── Schedule Exception Dialog ────────────────────────────────────────────────
const exceptionDialog = reactive({
    visible: false,
    isEdit: false,
    saving: false,
    id: null,
    expected_version: 1,
    exception_type: 'holiday',
    exception_date: '',
    school_rombel_id: null,
    teaching_schedule_id: null,
    rescheduled_date: '',
    rescheduled_period_start: 1,
    rescheduled_period_end: 2,
    rescheduled_start_time: '07:00',
    rescheduled_end_time: '08:30',
    rescheduled_room: '',
    substitute_employee_id: null,
    reason: '',
    errorMessage: '',
    fieldErrors: {},
    conflict: false,
    latest: null
});

const scheduleDropdownOptions = computed(() => {
    return (allPeriodSchedules.value || []).map((s) => {
        const classLabel = s.assignment?.school_rombel_name || `#${s.assignment?.school_rombel_id}`;
        const subjLabel = s.assignment?.subject_name || `#${s.assignment?.subject_id}`;
        const teacherLabel = s.assignment?.employee_name || `#${s.assignment?.employee_id}`;
        return {
            id: s.id,
            day_of_week: s.day_of_week,
            label: `${s.day_label} P${s.period_start}–${s.period_end} (${s.start_time}–${s.end_time}) — ${classLabel}: ${subjLabel} (${teacherLabel})`
        };
    });
});

function syncPresetTimesForReschedule() {
    const presets = options.value.period_presets || [];
    const startPreset = presets.find((p) => Number(p.period) === Number(exceptionDialog.rescheduled_period_start));
    if (Number(exceptionDialog.rescheduled_period_end) < Number(exceptionDialog.rescheduled_period_start)) {
        exceptionDialog.rescheduled_period_end = Number(exceptionDialog.rescheduled_period_start);
    }
    const endPreset = presets.find((p) => Number(p.period) === Number(exceptionDialog.rescheduled_period_end));
    if (startPreset) {
        exceptionDialog.rescheduled_start_time = startPreset.start_time;
    }
    if (endPreset) {
        exceptionDialog.rescheduled_end_time = endPreset.end_time;
    }
}

function openNewExceptionDialog() {
    const defaultDate = selectedSchoolYear.value?.start_date || new Date().toISOString().slice(0, 10);
    Object.assign(exceptionDialog, {
        visible: true,
        isEdit: false,
        saving: false,
        id: null,
        expected_version: 1,
        exception_type: 'holiday',
        exception_date: defaultDate,
        school_rombel_id: null,
        teaching_schedule_id: allPeriodSchedules.value[0]?.id || null,
        rescheduled_date: defaultDate,
        rescheduled_period_start: 1,
        rescheduled_period_end: 2,
        rescheduled_start_time: '07:00',
        rescheduled_end_time: '08:30',
        rescheduled_room: '',
        substitute_employee_id: null,
        reason: '',
        errorMessage: '',
        fieldErrors: {},
        conflict: false,
        latest: null
    });
}

function openEditExceptionDialog(exc) {
    Object.assign(exceptionDialog, {
        visible: true,
        isEdit: true,
        saving: false,
        id: exc.id,
        expected_version: exc.version,
        exception_type: exc.exception_type,
        exception_date: exc.exception_date || '',
        school_rombel_id: exc.school_rombel_id,
        teaching_schedule_id: exc.teaching_schedule_id,
        rescheduled_date: exc.rescheduled_date || '',
        rescheduled_period_start: exc.rescheduled_period_start || 1,
        rescheduled_period_end: exc.rescheduled_period_end || 2,
        rescheduled_start_time: exc.rescheduled_start_time || '07:00',
        rescheduled_end_time: exc.rescheduled_end_time || '08:30',
        rescheduled_room: exc.rescheduled_room || '',
        substitute_employee_id: exc.substitute_employee_id,
        reason: exc.reason || '',
        errorMessage: '',
        fieldErrors: {},
        conflict: false,
        latest: null
    });
}

function reloadLatestException() {
    if (!exceptionDialog.latest) return;
    openEditExceptionDialog(exceptionDialog.latest);
    loadExceptions();
}

async function saveException() {
    exceptionDialog.saving = true;
    exceptionDialog.errorMessage = '';
    exceptionDialog.fieldErrors = {};
    exceptionDialog.conflict = false;
    try {
        const payload = {
            school_year_id: context.school_year_id,
            semester_id: context.semester_id,
            exception_type: exceptionDialog.exception_type,
            exception_date: exceptionDialog.exception_date,
            reason: exceptionDialog.reason.trim()
        };

        if (exceptionDialog.exception_type === 'holiday') {
            payload.school_rombel_id = exceptionDialog.school_rombel_id || null;
        } else {
            payload.teaching_schedule_id = exceptionDialog.teaching_schedule_id;
        }

        if (exceptionDialog.exception_type === 'rescheduled') {
            payload.rescheduled_date = exceptionDialog.rescheduled_date;
            payload.rescheduled_period_start = Number(exceptionDialog.rescheduled_period_start);
            payload.rescheduled_period_end = Number(exceptionDialog.rescheduled_period_end);
            payload.rescheduled_start_time = exceptionDialog.rescheduled_start_time;
            payload.rescheduled_end_time = exceptionDialog.rescheduled_end_time;
            payload.rescheduled_room = exceptionDialog.rescheduled_room.trim() || null;
            payload.substitute_employee_id = exceptionDialog.substitute_employee_id || null;
        }

        if (exceptionDialog.isEdit) {
            payload.expected_version = exceptionDialog.expected_version;
            await apiLogin.put(`/teaching/exceptions/${exceptionDialog.id}`, payload);
            toast.add({ severity: 'success', summary: t('teaching.schedules.dialogs.exception.save'), detail: t('teaching.schedules.toast.exceptionUpdated'), life: 3000 });
        } else {
            await apiLogin.post('/teaching/exceptions', payload);
            toast.add({ severity: 'success', summary: t('teaching.schedules.dialogs.exception.create'), detail: t('teaching.schedules.toast.exceptionCreated'), life: 3000 });
        }
        exceptionDialog.visible = false;
        await Promise.all([loadExceptions(), loadSchedules(), loadAllPeriodSchedules()]);
    } catch (err) {
        const parsed = extractErrors(err, 'Failed to save schedule exception.');
        exceptionDialog.errorMessage = parsed.message;
        exceptionDialog.fieldErrors = parsed.fieldErrors;
        exceptionDialog.conflict = parsed.conflict;
        exceptionDialog.latest = parsed.latest;
    } finally {
        exceptionDialog.saving = false;
    }
}

async function removeException(exc) {
    try {
        await apiLogin.delete(`/teaching/exceptions/${exc.id}`);
        toast.add({ severity: 'success', summary: t('teaching.schedules.exceptions.newException'), detail: t('teaching.schedules.toast.exceptionDeleted'), life: 3000 });
        await Promise.all([loadExceptions(), loadSchedules(), loadAllPeriodSchedules()]);
    } catch (err) {
        const parsed = extractErrors(err, 'Failed to delete schedule exception.');
        toast.add({ severity: 'error', summary: t('teaching.schedules.toast.cannotDeleteException'), detail: parsed.message, life: 5000 });
    }
}

function exceptionTypeSeverity(type) {
    if (type === 'holiday') return 'info';
    if (type === 'cancelled') return 'danger';
    if (type === 'rescheduled') return 'warn';
    return 'secondary';
}

onMounted(async () => {
    await loadOptions();
    await refreshAllForContext();
});
</script>

<template>
    <div class="p-4 flex flex-col gap-4 max-w-full overflow-x-hidden">
        <!-- Header & Academic Context Selector -->
        <div class="card p-4 flex flex-col gap-3">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold m-0">{{ t('teaching.schedules.title') }}</h1>
                    <p class="text-sm text-surface-500 m-0">{{ t('teaching.schedules.subtitle') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Tag v-if="isSelectedYearLocked" severity="warn" icon="pi pi-lock" :value="t('teaching.schedules.lockedYearBadge')" data-testid="locked-year-badge" />
                    <Tag v-else-if="selectedSchoolYear" severity="success" icon="pi pi-check-circle" :value="t('teaching.schedules.periodBadge', { name: selectedSchoolYear.name })" />
                </div>
            </div>

            <div v-if="optionsError" class="p-3 rounded bg-red-50 text-red-700 text-sm">
                {{ optionsError }}
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                <div class="flex flex-col gap-1">
                    <label for="ctx-school-year" class="text-sm font-medium">{{ t('teaching.schedules.labels.schoolYear') }}</label>
                    <Select
                        inputId="ctx-school-year"
                        v-model="context.school_year_id"
                        :options="options.school_years"
                        optionLabel="name"
                        optionValue="id"
                        :placeholder="t('teaching.schedules.selectYear')"
                        class="w-full"
                        data-testid="ctx-school-year"
                        @change="refreshAllForContext"
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <label for="ctx-semester" class="text-sm font-medium">{{ t('teaching.schedules.labels.semester') }}</label>
                    <Select
                        inputId="ctx-semester"
                        v-model="context.semester_id"
                        :options="options.semesters"
                        optionLabel="label"
                        optionValue="id"
                        :placeholder="t('teaching.schedules.selectSemester')"
                        class="w-full"
                        data-testid="ctx-semester"
                        @change="refreshAllForContext"
                    />
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button :label="t('teaching.schedules.refresh')" icon="pi pi-refresh" severity="secondary" outlined :loading="schedulesLoading || assignmentsLoading || exceptionsLoading" @click="refreshAllForContext" />
                    <Button v-if="options.actor_employee_id" :label="t('teaching.schedules.timetable.mySchedule')" icon="pi pi-user" severity="info" outlined data-testid="btn-my-schedule" @click="filterMySchedule" />
                </div>
            </div>

            <div v-if="isSelectedYearLocked" class="p-3 rounded border border-amber-300 bg-amber-50 text-amber-900 text-sm flex items-center gap-2" data-testid="locked-year-banner">
                <i class="pi pi-lock"></i>
                <span>
                    The selected academic year (<strong>{{ selectedSchoolYear?.name }}</strong
                    >) is locked. Creating, editing, or deleting assignments, timetable slots, and exceptions is disabled.
                </span>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex flex-wrap gap-2 border-b border-surface-200 pb-2">
            <Button
                :label="t('teaching.schedules.tabs.timetable')"
                icon="pi pi-calendar"
                :severity="activeTab === 'timetable' ? 'primary' : 'secondary'"
                :outlined="activeTab !== 'timetable'"
                data-testid="tab-timetable"
                @click="activeTab = 'timetable'"
            />
            <Button
                :label="t('teaching.schedules.tabs.assignments', { count: assignmentMeta.total })"
                icon="pi pi-briefcase"
                :severity="activeTab === 'assignments' ? 'primary' : 'secondary'"
                :outlined="activeTab !== 'assignments'"
                data-testid="tab-assignments"
                @click="activeTab = 'assignments'"
            />
            <Button
                :label="t('teaching.schedules.tabs.exceptions', { count: exceptionMeta.total })"
                icon="pi pi-exclamation-circle"
                :severity="activeTab === 'exceptions' ? 'primary' : 'secondary'"
                :outlined="activeTab !== 'exceptions'"
                data-testid="tab-exceptions"
                @click="activeTab = 'exceptions'"
            />
        </div>

        <!-- TAB 1: WEEKLY TIMETABLE -->
        <div v-if="activeTab === 'timetable'" class="card p-4 flex flex-col gap-4" data-testid="panel-timetable">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        :label="t('teaching.schedules.timetable.matrixView')"
                        icon="pi pi-th-large"
                        size="small"
                        :severity="timetableMode === 'matrix' ? 'primary' : 'secondary'"
                        :outlined="timetableMode !== 'matrix'"
                        data-testid="btn-mode-matrix"
                        @click="
                            timetableMode = 'matrix';
                            loadSchedules();
                        "
                    />
                    <Button
                        :label="t('teaching.schedules.timetable.listView')"
                        icon="pi pi-list"
                        size="small"
                        :severity="timetableMode === 'list' ? 'primary' : 'secondary'"
                        :outlined="timetableMode !== 'list'"
                        data-testid="btn-mode-list"
                        @click="
                            timetableMode = 'list';
                            loadSchedules();
                        "
                    />
                </div>
                <div v-if="canManage && !isSelectedYearLocked" class="flex flex-wrap gap-2">
                    <Button :label="t('teaching.schedules.timetable.newSlot')" icon="pi pi-plus" data-testid="btn-new-schedule" @click="openNewScheduleDialog()" />
                </div>
            </div>

            <!-- Schedule Filters -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium">{{ t('teaching.schedules.labels.class') }}</label>
                    <Select
                        v-model="scheduleFilters.school_rombel_id"
                        :options="rombelsForSelectedYear"
                        optionLabel="name"
                        optionValue="id"
                        showClear
                        :placeholder="t('teaching.schedules.timetable.allClasses')"
                        class="w-full"
                        data-testid="filter-schedule-rombel"
                        @change="
                            scheduleFilters.page = 1;
                            loadSchedules();
                        "
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium">{{ t('teaching.schedules.labels.teacher') }}</label>
                    <Select
                        v-model="scheduleFilters.employee_id"
                        :options="options.teachers"
                        optionLabel="name"
                        optionValue="id"
                        showClear
                        filter
                        :placeholder="t('teaching.schedules.timetable.allTeachers')"
                        class="w-full"
                        data-testid="filter-schedule-teacher"
                        @change="
                            scheduleFilters.page = 1;
                            loadSchedules();
                        "
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium">{{ t('teaching.schedules.labels.dayOfWeek') }}</label>
                    <Select
                        v-model="scheduleFilters.day_of_week"
                        :options="options.days_of_week"
                        optionLabel="label"
                        optionValue="value"
                        showClear
                        :placeholder="t('teaching.schedules.timetable.allDays')"
                        class="w-full"
                        data-testid="filter-schedule-day"
                        @change="
                            scheduleFilters.page = 1;
                            loadSchedules();
                        "
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium">{{ t('teaching.schedules.labels.room') }}</label>
                    <InputText
                        v-model="scheduleFilters.room"
                        placeholder="e.g. LAB-KOMP-1"
                        class="w-full"
                        data-testid="filter-schedule-room"
                        @keyup.enter="
                            scheduleFilters.page = 1;
                            loadSchedules();
                        "
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium">{{ t('teaching.schedules.labels.status') }}</label>
                    <Select
                        v-model="scheduleFilters.status"
                        :options="[
                            { label: 'Active Only', value: 'active' },
                            { label: 'Inactive Only', value: 'inactive' },
                            { label: 'All Statuses', value: 'all' }
                        ]"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                        @change="
                            scheduleFilters.page = 1;
                            loadSchedules();
                        "
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium">{{ t('teaching.schedules.labels.search') }}</label>
                    <div class="flex gap-1">
                        <InputText
                            v-model="scheduleFilters.search"
                            :placeholder="t('teaching.schedules.assignments.filterSearch')"
                            class="w-full"
                            @keyup.enter="
                                scheduleFilters.page = 1;
                                loadSchedules();
                            "
                        />
                        <Button
                            icon="pi pi-search"
                            severity="secondary"
                            @click="
                                scheduleFilters.page = 1;
                                loadSchedules();
                            "
                        />
                    </div>
                </div>
            </div>

            <div v-if="schedulesError" class="p-3 rounded bg-red-50 text-red-700 text-sm">
                {{ schedulesError }}
            </div>

            <!-- Matrix View -->
            <div v-if="timetableMode === 'matrix'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4" data-testid="timetable-matrix-grid">
                <div v-for="dayGroup in scheduleMatrix" :key="dayGroup.day_of_week" class="border border-surface-200 rounded-lg p-3 flex flex-col gap-2 bg-surface-0">
                    <div class="flex items-center justify-between border-b border-surface-200 pb-2">
                        <span class="font-semibold text-base">{{ dayGroup.day_label }}</span>
                        <Tag :value="`${dayGroup.slots?.length || 0} slot(s)`" severity="secondary" />
                    </div>

                    <div v-if="!dayGroup.slots || dayGroup.slots.length === 0" class="text-xs text-surface-500 py-4 text-center">No schedule slots for {{ dayGroup.day_label }}.</div>

                    <div v-for="slot in dayGroup.slots" :key="slot.id" class="p-3 rounded border border-surface-200 flex flex-col gap-1 bg-surface-50" :data-testid="`schedule-slot-card-${slot.id}`">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <span class="text-xs font-bold text-primary"> Period {{ slot.period_start }}–{{ slot.period_end }} ({{ slot.start_time }}–{{ slot.end_time }}) </span>
                            <div class="flex items-center gap-1">
                                <Tag v-if="slot.room" :value="slot.room" severity="info" />
                                <Tag :value="slot.status === 'active' ? 'Active' : 'Inactive'" :severity="slot.status === 'active' ? 'success' : 'secondary'" />
                            </div>
                        </div>
                        <div class="font-semibold text-sm">{{ slot.assignment?.school_rombel_name }} — {{ slot.assignment?.subject_name }}</div>
                        <div class="text-xs text-surface-600">
                            Teacher: <strong>{{ slot.assignment?.employee_name }}</strong>
                        </div>
                        <div v-if="slot.notes" class="text-xs text-surface-500 italic">
                            {{ slot.notes }}
                        </div>
                        <div v-if="canManage && !isSelectedYearLocked" class="flex justify-end gap-1 pt-1">
                            <Button icon="pi pi-pencil" size="small" text rounded :aria-label="t('teaching.schedules.dialogs.scheduleSlot.editTitle')" :data-testid="`btn-edit-schedule-${slot.id}`" @click="openEditScheduleDialog(slot)" />
                            <Button icon="pi pi-trash" size="small" text rounded severity="danger" :aria-label="t('teaching.schedules.toast.scheduleDeleted')" :data-testid="`btn-delete-schedule-${slot.id}`" @click="removeSchedule(slot)" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- List View -->
            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm border-collapse" data-testid="timetable-list-table">
                    <thead>
                        <tr class="border-b border-surface-200 text-left bg-surface-50">
                            <th class="p-2">Day</th>
                            <th class="p-2">Period &amp; Time</th>
                            <th class="p-2">Class</th>
                            <th class="p-2">Subject</th>
                            <th class="p-2">Teacher</th>
                            <th class="p-2">Room</th>
                            <th class="p-2">Status</th>
                            <th v-if="canManage && !isSelectedYearLocked" class="p-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="scheduleList.length === 0">
                            <td colspan="8" class="p-4 text-center text-surface-500">No schedule slots found for the selected filters.</td>
                        </tr>
                        <tr v-for="slot in scheduleList" :key="slot.id" class="border-b border-surface-100">
                            <td class="p-2 font-medium">{{ slot.day_label }}</td>
                            <td class="p-2">P{{ slot.period_start }}–{{ slot.period_end }} ({{ slot.start_time }}–{{ slot.end_time }})</td>
                            <td class="p-2 font-semibold">{{ slot.assignment?.school_rombel_name }}</td>
                            <td class="p-2">{{ slot.assignment?.subject_name }}</td>
                            <td class="p-2">{{ slot.assignment?.employee_name }}</td>
                            <td class="p-2">
                                <Tag v-if="slot.room" :value="slot.room" severity="info" />
                                <span v-else class="text-surface-400">—</span>
                            </td>
                            <td class="p-2">
                                <Tag :value="slot.status === 'active' ? 'Active' : 'Inactive'" :severity="slot.status === 'active' ? 'success' : 'secondary'" />
                            </td>
                            <td v-if="canManage && !isSelectedYearLocked" class="p-2 text-right">
                                <div class="flex justify-end gap-1">
                                    <Button icon="pi pi-pencil" size="small" text rounded @click="openEditScheduleDialog(slot)" />
                                    <Button icon="pi pi-trash" size="small" text rounded severity="danger" @click="removeSchedule(slot)" />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 2: TEACHING ASSIGNMENTS -->
        <div v-if="activeTab === 'assignments'" class="card p-4 flex flex-col gap-4" data-testid="panel-assignments">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <h2 class="text-lg font-semibold m-0">{{ t('teaching.schedules.assignments.title') }}</h2>
                <Button v-if="canManage && !isSelectedYearLocked" :label="t('teaching.schedules.assignments.newAssignment')" icon="pi pi-plus" data-testid="btn-new-assignment" @click="openNewAssignmentDialog" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium">{{ t('teaching.schedules.labels.class') }}</label>
                    <Select
                        v-model="assignmentFilters.school_rombel_id"
                        :options="rombelsForSelectedYear"
                        optionLabel="name"
                        optionValue="id"
                        showClear
                        :placeholder="t('teaching.schedules.timetable.allClasses')"
                        class="w-full"
                        @change="
                            assignmentFilters.page = 1;
                            loadAssignments();
                        "
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium">{{ t('teaching.schedules.labels.subject') }}</label>
                    <Select
                        v-model="assignmentFilters.subject_id"
                        :options="options.subjects"
                        optionLabel="name"
                        optionValue="id"
                        showClear
                        filter
                        :placeholder="t('teaching.schedules.timetable.allSubjects')"
                        class="w-full"
                        @change="
                            assignmentFilters.page = 1;
                            loadAssignments();
                        "
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium">{{ t('teaching.schedules.labels.teacher') }}</label>
                    <Select
                        v-model="assignmentFilters.employee_id"
                        :options="options.teachers"
                        optionLabel="name"
                        optionValue="id"
                        showClear
                        filter
                        :placeholder="t('teaching.schedules.timetable.allTeachers')"
                        class="w-full"
                        @change="
                            assignmentFilters.page = 1;
                            loadAssignments();
                        "
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium">{{ t('teaching.schedules.labels.status') }}</label>
                    <Select
                        v-model="assignmentFilters.status"
                        :options="[
                            { label: 'Active Only', value: 'active' },
                            { label: 'Inactive Only', value: 'inactive' },
                            { label: 'All Statuses', value: 'all' }
                        ]"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                        @change="
                            assignmentFilters.page = 1;
                            loadAssignments();
                        "
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium">{{ t('teaching.schedules.labels.search') }}</label>
                    <div class="flex gap-1">
                        <InputText
                            v-model="assignmentFilters.search"
                            :placeholder="t('teaching.schedules.timetable.filterSearch')"
                            class="w-full"
                            @keyup.enter="
                                assignmentFilters.page = 1;
                                loadAssignments();
                            "
                        />
                        <Button
                            icon="pi pi-search"
                            severity="secondary"
                            @click="
                                assignmentFilters.page = 1;
                                loadAssignments();
                            "
                        />
                    </div>
                </div>
            </div>

            <div v-if="assignmentsError" class="p-3 rounded bg-red-50 text-red-700 text-sm">
                {{ assignmentsError }}
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse" data-testid="assignments-table">
                    <thead>
                        <tr class="border-b border-surface-200 text-left bg-surface-50">
                            <th class="p-2">Class</th>
                            <th class="p-2">Subject</th>
                            <th class="p-2">Teacher</th>
                            <th class="p-2">Hours/Week</th>
                            <th class="p-2">Scheduled Slots</th>
                            <th class="p-2">Status</th>
                            <th v-if="canManage && !isSelectedYearLocked" class="p-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="assignmentList.length === 0">
                            <td colspan="7" class="p-4 text-center text-surface-500">No teaching assignments found for the selected filters.</td>
                        </tr>
                        <tr v-for="row in assignmentList" :key="row.id" class="border-b border-surface-100" :data-testid="`assignment-row-${row.id}`">
                            <td class="p-2 font-semibold">{{ row.school_rombel?.name }}</td>
                            <td class="p-2">{{ row.subject?.name }}</td>
                            <td class="p-2">
                                <div class="font-medium">{{ row.employee?.name }}</div>
                                <div v-if="row.employee?.primary_subject_name" class="text-xs text-surface-500">Primary: {{ row.employee.primary_subject_name }}</div>
                            </td>
                            <td class="p-2">{{ row.hours_per_week ?? '—' }}</td>
                            <td class="p-2">
                                <Tag :value="`${row.active_schedules_count} active / ${row.schedules_count} total`" severity="info" />
                            </td>
                            <td class="p-2">
                                <Tag :value="row.status === 'active' ? 'Active' : 'Inactive'" :severity="row.status === 'active' ? 'success' : 'secondary'" />
                            </td>
                            <td v-if="canManage && !isSelectedYearLocked" class="p-2 text-right">
                                <div class="flex justify-end gap-1">
                                    <Button icon="pi pi-calendar-plus" size="small" text rounded title="Add Schedule Slot" :data-testid="`btn-add-slot-for-assignment-${row.id}`" @click="openNewScheduleDialog(row.id)" />
                                    <Button icon="pi pi-pencil" size="small" text rounded :aria-label="t('teaching.schedules.dialogs.assignment.editTitle')" :data-testid="`btn-edit-assignment-${row.id}`" @click="openEditAssignmentDialog(row)" />
                                    <Button
                                        icon="pi pi-trash"
                                        size="small"
                                        text
                                        rounded
                                        severity="danger"
                                        :aria-label="t('teaching.schedules.toast.assignmentDeleted')"
                                        :data-testid="`btn-delete-assignment-${row.id}`"
                                        @click="removeAssignment(row)"
                                    />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-surface-600">
                <span>Showing page {{ assignmentMeta.current_page }} of {{ assignmentMeta.last_page }} ({{ assignmentMeta.total }} total)</span>
                <div class="flex gap-1">
                    <Button
                        :label="t('teaching.schedules.pagination.prev')"
                        size="small"
                        outlined
                        :disabled="assignmentMeta.current_page <= 1"
                        @click="
                            assignmentFilters.page -= 1;
                            loadAssignments();
                        "
                    />
                    <Button
                        :label="t('teaching.schedules.pagination.next')"
                        size="small"
                        outlined
                        :disabled="assignmentMeta.current_page >= assignmentMeta.last_page"
                        @click="
                            assignmentFilters.page += 1;
                            loadAssignments();
                        "
                    />
                </div>
            </div>
        </div>

        <!-- TAB 3: SCHEDULE EXCEPTIONS -->
        <div v-if="activeTab === 'exceptions'" class="card p-4 flex flex-col gap-4" data-testid="panel-exceptions">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <h2 class="text-lg font-semibold m-0">{{ t('teaching.schedules.exceptions.title') }}</h2>
                <Button v-if="canManage && !isSelectedYearLocked" :label="t('teaching.schedules.exceptions.newException')" icon="pi pi-plus" data-testid="btn-new-exception" @click="openNewExceptionDialog" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium">{{ t('teaching.schedules.labels.exceptionType') }}</label>
                    <Select
                        v-model="exceptionFilters.exception_type"
                        :options="[
                            { label: 'All Types', value: 'all' },
                            { label: 'Holiday', value: 'holiday' },
                            { label: 'Cancelled', value: 'cancelled' },
                            { label: 'Rescheduled', value: 'rescheduled' }
                        ]"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                        @change="
                            exceptionFilters.page = 1;
                            loadExceptions();
                        "
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium">{{ t('teaching.schedules.labels.class') }}</label>
                    <Select
                        v-model="exceptionFilters.school_rombel_id"
                        :options="rombelsForSelectedYear"
                        optionLabel="name"
                        optionValue="id"
                        showClear
                        :placeholder="t('teaching.schedules.timetable.allClasses')"
                        class="w-full"
                        @change="
                            exceptionFilters.page = 1;
                            loadExceptions();
                        "
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium">{{ t('teaching.schedules.labels.dateFrom') }}</label>
                    <InputText
                        v-model="exceptionFilters.date_from"
                        type="date"
                        class="w-full"
                        @change="
                            exceptionFilters.page = 1;
                            loadExceptions();
                        "
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium">{{ t('teaching.schedules.labels.dateTo') }}</label>
                    <InputText
                        v-model="exceptionFilters.date_to"
                        type="date"
                        class="w-full"
                        @change="
                            exceptionFilters.page = 1;
                            loadExceptions();
                        "
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium">{{ t('teaching.schedules.labels.search') }}</label>
                    <div class="flex gap-1">
                        <InputText
                            v-model="exceptionFilters.search"
                            :placeholder="t('teaching.schedules.exceptions.filterSearch')"
                            class="w-full"
                            @keyup.enter="
                                exceptionFilters.page = 1;
                                loadExceptions();
                            "
                        />
                        <Button
                            icon="pi pi-search"
                            severity="secondary"
                            @click="
                                exceptionFilters.page = 1;
                                loadExceptions();
                            "
                        />
                    </div>
                </div>
            </div>

            <div v-if="exceptionsError" class="p-3 rounded bg-red-50 text-red-700 text-sm">
                {{ exceptionsError }}
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse" data-testid="exceptions-table">
                    <thead>
                        <tr class="border-b border-surface-200 text-left bg-surface-50">
                            <th class="p-2">Date</th>
                            <th class="p-2">Type</th>
                            <th class="p-2">Scope / Original Slot</th>
                            <th class="p-2">Rescheduled Target</th>
                            <th class="p-2">Reason</th>
                            <th v-if="canManage && !isSelectedYearLocked" class="p-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="exceptionList.length === 0">
                            <td colspan="6" class="p-4 text-center text-surface-500">No schedule exceptions recorded for the selected filters.</td>
                        </tr>
                        <tr v-for="exc in exceptionList" :key="exc.id" class="border-b border-surface-100" :data-testid="`exception-row-${exc.id}`">
                            <td class="p-2 font-medium">{{ exc.exception_date }}</td>
                            <td class="p-2">
                                <Tag :value="exc.exception_type_label" :severity="exceptionTypeSeverity(exc.exception_type)" />
                            </td>
                            <td class="p-2">
                                <template v-if="exc.exception_type === 'holiday'">
                                    <span class="font-semibold">{{ exc.school_rombel_name ? `Class ${exc.school_rombel_name}` : 'All Classes (School-Wide Holiday)' }}</span>
                                </template>
                                <template v-else-if="exc.schedule">
                                    <div class="font-semibold">{{ exc.schedule.school_rombel_name }} — {{ exc.schedule.subject_name }}</div>
                                    <div class="text-xs text-surface-500">
                                        {{ exc.schedule.day_label }} P{{ exc.schedule.period_start }}–{{ exc.schedule.period_end }} ({{ exc.schedule.start_time }}–{{ exc.schedule.end_time }}) · {{ exc.schedule.employee_name }}
                                    </div>
                                </template>
                            </td>
                            <td class="p-2">
                                <template v-if="exc.exception_type === 'rescheduled'">
                                    <div class="font-medium">{{ exc.rescheduled_date }} · P{{ exc.rescheduled_period_start }}–{{ exc.rescheduled_period_end }} ({{ exc.rescheduled_start_time }}–{{ exc.rescheduled_end_time }})</div>
                                    <div class="text-xs text-surface-500">
                                        <span v-if="exc.rescheduled_room">Room: {{ exc.rescheduled_room }}</span>
                                        <span v-if="exc.substitute_employee_name"> · Substitute: {{ exc.substitute_employee_name }}</span>
                                    </div>
                                </template>
                                <span v-else class="text-surface-400">—</span>
                            </td>
                            <td class="p-2">{{ exc.reason }}</td>
                            <td v-if="canManage && !isSelectedYearLocked" class="p-2 text-right">
                                <div class="flex justify-end gap-1">
                                    <Button icon="pi pi-pencil" size="small" text rounded :aria-label="t('teaching.schedules.dialogs.exception.editTitle')" :data-testid="`btn-edit-exception-${exc.id}`" @click="openEditExceptionDialog(exc)" />
                                    <Button icon="pi pi-trash" size="small" text rounded severity="danger" :aria-label="t('teaching.schedules.toast.exceptionDeleted')" :data-testid="`btn-delete-exception-${exc.id}`" @click="removeException(exc)" />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- DIALOG 1: CREATE / EDIT ASSIGNMENT -->
        <Dialog
            v-model:visible="assignmentDialog.visible"
            modal
            :header="assignmentDialog.isEdit ? t('teaching.schedules.dialogs.assignment.editTitle') : t('teaching.schedules.dialogs.assignment.createTitle')"
            :style="{ width: '34rem', maxWidth: '95vw' }"
        >
            <div class="flex flex-col gap-3">
                <div v-if="assignmentDialog.errorMessage" class="p-3 rounded bg-red-50 border border-red-200 text-red-700 text-sm flex flex-col gap-2" data-testid="assignment-dialog-error">
                    <span>{{ assignmentDialog.errorMessage }}</span>
                    <div v-if="assignmentDialog.conflict && assignmentDialog.latest">
                        <Button :label="t('teaching.schedules.dialogs.assignment.reloadLatest')" icon="pi pi-refresh" size="small" severity="warn" data-testid="btn-reload-latest-assignment" @click="reloadLatestAssignment" />
                    </div>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium">{{ t('teaching.schedules.labels.classRequired') }}</label>
                    <Select
                        v-model="assignmentDialog.school_rombel_id"
                        :options="rombelsForSelectedYear"
                        optionLabel="name"
                        optionValue="id"
                        :placeholder="t('teaching.schedules.dialogs.assignment.selectClass')"
                        class="w-full"
                        data-testid="input-assignment-rombel"
                    />
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium">{{ t('teaching.schedules.labels.teacherRequired') }}</label>
                    <Select
                        v-model="assignmentDialog.employee_id"
                        :options="options.teachers"
                        optionLabel="name"
                        optionValue="id"
                        filter
                        :placeholder="t('teaching.schedules.dialogs.assignment.selectTeacher')"
                        class="w-full"
                        data-testid="input-assignment-teacher"
                        @change="onAssignmentTeacherChange"
                    />
                    <span v-if="selectedTeacherForAssignment?.subject_name" class="text-xs text-surface-500">
                        Teacher's primary subject: <strong>{{ selectedTeacherForAssignment.subject_name }}</strong>
                    </span>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium">{{ t('teaching.schedules.labels.subjectRequired') }}</label>
                    <Select
                        v-model="assignmentDialog.subject_id"
                        :options="options.subjects"
                        optionLabel="name"
                        optionValue="id"
                        filter
                        :placeholder="t('teaching.schedules.dialogs.assignment.selectSubject')"
                        class="w-full"
                        data-testid="input-assignment-subject"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">{{ t('teaching.schedules.labels.targetHours') }}</label>
                        <InputText v-model="assignmentDialog.hours_per_week" type="number" min="1" max="24" class="w-full" data-testid="input-assignment-hours" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">{{ t('teaching.schedules.labels.status') }}</label>
                        <Select
                            v-model="assignmentDialog.status"
                            :options="[
                                { label: 'Active', value: 'active' },
                                { label: 'Inactive', value: 'inactive' }
                            ]"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                            data-testid="input-assignment-status"
                        />
                    </div>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium">{{ t('teaching.schedules.labels.notesOptional') }}</label>
                    <Textarea v-model="assignmentDialog.notes" rows="2" class="w-full" maxlength="255" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button :label="t('common.action.cancel')" severity="secondary" outlined @click="assignmentDialog.visible = false" />
                    <Button
                        :label="assignmentDialog.isEdit ? t('teaching.schedules.dialogs.assignment.save') : t('teaching.schedules.dialogs.assignment.create')"
                        icon="pi pi-check"
                        :loading="assignmentDialog.saving"
                        data-testid="btn-save-assignment"
                        @click="saveAssignment"
                    />
                </div>
            </div>
        </Dialog>

        <!-- DIALOG 2: CREATE / EDIT SCHEDULE SLOT -->
        <Dialog
            v-model:visible="scheduleDialog.visible"
            modal
            :header="scheduleDialog.isEdit ? t('teaching.schedules.dialogs.scheduleSlot.editTitle') : t('teaching.schedules.dialogs.scheduleSlot.createTitle')"
            :style="{ width: '36rem', maxWidth: '95vw' }"
        >
            <div class="flex flex-col gap-3">
                <div v-if="scheduleDialog.errorMessage" class="p-3 rounded bg-red-50 border border-red-200 text-red-700 text-sm flex flex-col gap-2" data-testid="schedule-dialog-error">
                    <span>{{ scheduleDialog.errorMessage }}</span>
                    <div v-if="scheduleDialog.conflict && scheduleDialog.latest">
                        <Button :label="t('teaching.schedules.dialogs.assignment.reloadLatest')" icon="pi pi-refresh" size="small" severity="warn" data-testid="btn-reload-latest-schedule" @click="reloadLatestSchedule" />
                    </div>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium">{{ t('teaching.schedules.labels.assignmentRequired') }}</label>
                    <Select
                        v-model="scheduleDialog.teaching_assignment_id"
                        :options="assignmentDropdownOptions"
                        optionLabel="label"
                        optionValue="id"
                        filter
                        :placeholder="t('teaching.schedules.dialogs.scheduleSlot.selectAssignment')"
                        class="w-full"
                        data-testid="input-schedule-assignment"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">{{ t('teaching.schedules.labels.dayOfWeekRequired') }}</label>
                        <Select v-model="scheduleDialog.day_of_week" :options="options.days_of_week" optionLabel="label" optionValue="value" class="w-full" data-testid="input-schedule-day" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">{{ t('teaching.schedules.labels.startPeriodRequired') }}</label>
                        <Select v-model="scheduleDialog.period_start" :options="options.period_presets" optionLabel="label" optionValue="period" class="w-full" data-testid="input-schedule-period-start" @change="syncPresetTimesForSchedule" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">{{ t('teaching.schedules.labels.endPeriodRequired') }}</label>
                        <Select v-model="scheduleDialog.period_end" :options="options.period_presets" optionLabel="label" optionValue="period" class="w-full" data-testid="input-schedule-period-end" @change="syncPresetTimesForSchedule" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">{{ t('teaching.schedules.labels.startTimeRequired') }}</label>
                        <InputText v-model="scheduleDialog.start_time" placeholder="07:00" class="w-full" data-testid="input-schedule-start-time" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">{{ t('teaching.schedules.labels.endTimeRequired') }}</label>
                        <InputText v-model="scheduleDialog.end_time" placeholder="08:30" class="w-full" data-testid="input-schedule-end-time" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">{{ t('teaching.schedules.labels.roomOptional') }}</label>
                        <InputText v-model="scheduleDialog.room" :placeholder="t('teaching.schedules.timetable.filterRoom')" maxlength="60" class="w-full" data-testid="input-schedule-room" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">{{ t('teaching.schedules.labels.status') }}</label>
                        <Select
                            v-model="scheduleDialog.status"
                            :options="[
                                { label: 'Active', value: 'active' },
                                { label: 'Inactive', value: 'inactive' }
                            ]"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                            data-testid="input-schedule-status"
                        />
                    </div>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium">{{ t('teaching.schedules.labels.notesOptional') }}</label>
                    <InputText v-model="scheduleDialog.notes" maxlength="255" class="w-full" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button :label="t('common.action.cancel')" severity="secondary" outlined @click="scheduleDialog.visible = false" />
                    <Button
                        :label="scheduleDialog.isEdit ? t('teaching.schedules.dialogs.scheduleSlot.save') : t('teaching.schedules.dialogs.scheduleSlot.create')"
                        icon="pi pi-check"
                        :loading="scheduleDialog.saving"
                        data-testid="btn-save-schedule"
                        @click="saveSchedule"
                    />
                </div>
            </div>
        </Dialog>

        <!-- DIALOG 3: CREATE / EDIT SCHEDULE EXCEPTION -->
        <Dialog
            v-model:visible="exceptionDialog.visible"
            modal
            :header="exceptionDialog.isEdit ? t('teaching.schedules.dialogs.exception.editTitle') : t('teaching.schedules.dialogs.exception.createTitle')"
            :style="{ width: '38rem', maxWidth: '95vw' }"
        >
            <div class="flex flex-col gap-3">
                <div v-if="exceptionDialog.errorMessage" class="p-3 rounded bg-red-50 border border-red-200 text-red-700 text-sm flex flex-col gap-2" data-testid="exception-dialog-error">
                    <span>{{ exceptionDialog.errorMessage }}</span>
                    <div v-if="exceptionDialog.conflict && exceptionDialog.latest">
                        <Button :label="t('teaching.schedules.dialogs.assignment.reloadLatest')" icon="pi pi-refresh" size="small" severity="warn" data-testid="btn-reload-latest-exception" @click="reloadLatestException" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">{{ t('teaching.schedules.labels.exceptionTypeRequired') }}</label>
                        <Select v-model="exceptionDialog.exception_type" :options="options.exception_types" optionLabel="label" optionValue="value" class="w-full" data-testid="input-exception-type" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium">{{ t('teaching.schedules.labels.exceptionDateRequired') }}</label>
                        <InputText v-model="exceptionDialog.exception_date" type="date" class="w-full" data-testid="input-exception-date" />
                    </div>
                </div>

                <div v-if="exceptionDialog.exception_type === 'holiday'" class="flex flex-col gap-1">
                    <label class="text-sm font-medium">{{ t('teaching.schedules.labels.holidayScope') }}</label>
                    <Select
                        v-model="exceptionDialog.school_rombel_id"
                        :options="rombelsForSelectedYear"
                        optionLabel="name"
                        optionValue="id"
                        showClear
                        :placeholder="t('teaching.schedules.dialogs.exception.allSchoolHoliday')"
                        class="w-full"
                        data-testid="input-exception-rombel"
                    />
                </div>

                <div v-else class="flex flex-col gap-1">
                    <label class="text-sm font-medium">{{ t('teaching.schedules.labels.affectedSlotRequired') }}</label>
                    <Select
                        v-model="exceptionDialog.teaching_schedule_id"
                        :options="scheduleDropdownOptions"
                        optionLabel="label"
                        optionValue="id"
                        filter
                        :placeholder="t('teaching.schedules.dialogs.exception.selectSchedule')"
                        class="w-full"
                        data-testid="input-exception-schedule"
                    />
                </div>

                <template v-if="exceptionDialog.exception_type === 'rescheduled'">
                    <div class="p-3 rounded bg-surface-50 border border-surface-200 flex flex-col gap-3">
                        <div class="font-semibold text-sm">Rescheduled Target Details</div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-medium">{{ t('teaching.schedules.labels.targetDateRequired') }}</label>
                                <InputText v-model="exceptionDialog.rescheduled_date" type="date" class="w-full" data-testid="input-rescheduled-date" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-medium">{{ t('teaching.schedules.labels.startPeriodRequired') }}</label>
                                <Select
                                    v-model="exceptionDialog.rescheduled_period_start"
                                    :options="options.period_presets"
                                    optionLabel="label"
                                    optionValue="period"
                                    class="w-full"
                                    data-testid="input-rescheduled-period-start"
                                    @change="syncPresetTimesForReschedule"
                                />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-medium">{{ t('teaching.schedules.labels.endPeriodRequired') }}</label>
                                <Select
                                    v-model="exceptionDialog.rescheduled_period_end"
                                    :options="options.period_presets"
                                    optionLabel="label"
                                    optionValue="period"
                                    class="w-full"
                                    data-testid="input-rescheduled-period-end"
                                    @change="syncPresetTimesForReschedule"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-medium">{{ t('teaching.schedules.labels.startTimeRequired') }}</label>
                                <InputText v-model="exceptionDialog.rescheduled_start_time" placeholder="07:00" class="w-full" data-testid="input-rescheduled-start-time" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-medium">{{ t('teaching.schedules.labels.endTimeRequired') }}</label>
                                <InputText v-model="exceptionDialog.rescheduled_end_time" placeholder="08:30" class="w-full" data-testid="input-rescheduled-end-time" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-medium">{{ t('teaching.schedules.labels.targetRoomOptional') }}</label>
                                <InputText v-model="exceptionDialog.rescheduled_room" placeholder="e.g. LAB-KOMP-1" maxlength="60" class="w-full" data-testid="input-rescheduled-room" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-medium">{{ t('teaching.schedules.labels.substituteTeacherOptional') }}</label>
                                <Select
                                    v-model="exceptionDialog.substitute_employee_id"
                                    :options="options.teachers"
                                    optionLabel="name"
                                    optionValue="id"
                                    showClear
                                    filter
                                    :placeholder="t('teaching.schedules.dialogs.exception.sameTeacher')"
                                    class="w-full"
                                    data-testid="input-rescheduled-substitute"
                                />
                            </div>
                        </div>
                    </div>
                </template>

                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium">{{ t('teaching.schedules.labels.reasonRequired') }}</label>
                    <Textarea v-model="exceptionDialog.reason" rows="2" maxlength="255" :placeholder="t('teaching.schedules.dialogs.exception.reasonPlaceholder')" class="w-full" data-testid="input-exception-reason" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button :label="t('common.action.cancel')" severity="secondary" outlined @click="exceptionDialog.visible = false" />
                    <Button
                        :label="exceptionDialog.isEdit ? t('teaching.schedules.dialogs.exception.save') : t('teaching.schedules.dialogs.exception.create')"
                        icon="pi pi-check"
                        :loading="exceptionDialog.saving"
                        data-testid="btn-save-exception"
                        @click="saveException"
                    />
                </div>
            </div>
        </Dialog>
    </div>
</template>
