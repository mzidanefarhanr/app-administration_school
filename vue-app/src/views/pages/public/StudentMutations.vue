<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import apiLogin from '@/apiLogin';
import { authStore } from '@/stores/authStore';
import { formatDate as formatDateUtil } from '@/utils/formatters';
import StudentMutationTimelineDialog from './StudentMutationTimelineDialog.vue';
import StudentMutationReportDialog from './StudentMutationReportDialog.vue';
import StudentMutationImportDialog from './StudentMutationImportDialog.vue';

const props = defineProps({
    schoolYear: {
        type: Object,
        default: () => null
    }
});

const emit = defineEmits(['mutated']);

const store = authStore();
const toast = useToast();
const { t, locale } = useI18n();

const canManage = computed(() => store.can('students.manage'));
const canCorrectLocked = computed(() => store.can('students.manage') && store.can('students.correct-locked'));

const typeOptions = computed(() => [
    { label: t('students.mutations.types.all'), value: '' },
    { label: t('students.mutations.types.in'), value: 'in' },
    { label: t('students.mutations.types.out'), value: 'out' }
]);

const statusOptions = computed(() => [
    { label: t('students.mutations.statuses.all'), value: '' },
    { label: t('students.mutations.statuses.active'), value: 'active' },
    { label: t('students.mutations.statuses.cancelled'), value: 'cancelled' }
]);

const correctionActions = computed(() => [
    { label: t('students.mutations.dialogCorrection.actions.mutation_in'), value: 'mutation_in' },
    { label: t('students.mutations.dialogCorrection.actions.mutation_out'), value: 'mutation_out' },
    { label: t('students.mutations.dialogCorrection.actions.mutation_cancel'), value: 'mutation_cancel' },
    { label: t('students.mutations.dialogCorrection.actions.membership_exit'), value: 'membership_exit' },
    { label: t('students.mutations.dialogCorrection.actions.membership_reopen'), value: 'membership_reopen' }
]);

const targetStatusOptions = computed(() => [
    { label: t('students.mutations.dialogCorrection.targetStatuses.mutationOut'), value: 2 },
    { label: t('students.mutations.dialogCorrection.targetStatuses.graduated'), value: 6 }
]);

const options = ref(null);
const optionsLoading = ref(false);
const optionsError = ref('');
const yearId = ref(null);

const recap = ref(null);
const recapError = ref('');
const recapLoading = ref(false);

const items = ref([]);
const perPage = ref(10);
const perPageOptions = [10, 20, 50, 100];
const meta = ref({ current_page: 1, per_page: 10, total: 0, last_page: 1 });
const listLoading = ref(false);
const listError = ref('');
const filters = reactive({ type: '', status: '', search: '' });
const page = ref(1);
let searchTimer = null;
let historyRequestId = 0;

const years = computed(() => options.value?.years ?? []);
const year = computed(() => years.value.find((row) => row.id === yearId.value) ?? null);
const classes = computed(() => options.value?.classes ?? []);
const canMutate = computed(() => canManage.value && year.value?.can_mutate === true);

const recapCards = computed(() => [
    { label: 'Mutation In', value: recap.value ? recap.value.mutation_in : '-', icon: 'pi pi-sign-in' },
    { label: 'Mutation Out', value: recap.value ? recap.value.mutation_out : '-', icon: 'pi pi-sign-out' },
    { label: 'Graduated', value: recap.value ? recap.value.graduated : '-', icon: 'pi pi-graduation-cap' },
    { label: 'Retained', value: recap.value ? recap.value.retained : '-', icon: 'pi pi-replay' }
]);

const typeLabel = (type) => (type === 'in' ? 'Mutation In' : 'Mutation Out');

const statusLabel = (id) => {
    const match = options.value?.references?.statuses?.find((row) => Number(row.id) === Number(id));
    return match?.name ?? (id === null || id === undefined ? 'Unknown' : `Status ${id}`);
};

const formatDate = (value) => {
    if (!value) return '-';
    return formatDateUtil(value);
};

const formatDateTime = (value) => {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    const loc = locale.value === 'en' ? 'en-GB' : 'id-ID';
    return parsed.toLocaleString(loc, { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const reportDialogVisible = ref(false);
const importDialogVisible = ref(false);

const onImported = () => {
    loadYearData();
    emit('mutated');
};

const dateToInput = (value) => {
    if (!value) return null;
    const [yearPart, month, day] = String(value).slice(0, 10).split('-').map(Number);
    if (!yearPart || !month || !day) return null;
    return new Date(yearPart, month - 1, day);
};

const dateToIso = (value) => {
    if (!value) return null;
    if (typeof value === 'string') return value.slice(0, 10) || null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${date.getFullYear()}-${month}-${day}`;
};

const errorMessage = (error, fallback) => error.response?.data?.message ?? fallback;

const fieldError = (errors, field) => {
    const value = errors?.[field];
    if (Array.isArray(value)) return value[0] ?? '';
    return typeof value === 'string' ? value : '';
};

const defaultEffectiveDate = () => {
    const today = options.value?.today;
    const start = year.value?.start_date;
    const end = year.value?.end_date;
    if (today && start && end && today >= start && today <= end) return today;
    return start ?? today ?? '';
};

const loadRecap = async () => {
    if (!yearId.value) return;
    recapLoading.value = true;
    recapError.value = '';
    try {
        const response = await apiLogin.get('/api/academic/mutations/recap', {
            params: { school_year_id: yearId.value }
        });
        recap.value = response.data?.data ?? null;
    } catch (error) {
        recap.value = null;
        recapError.value = errorMessage(error, 'Failed to load the year recap.');
    } finally {
        recapLoading.value = false;
    }
};

const loadHistory = async () => {
    if (!yearId.value) {
        items.value = [];
        return;
    }

    const requestId = ++historyRequestId;
    listLoading.value = true;
    listError.value = '';

    try {
        const params = { school_year_id: yearId.value, page: page.value, per_page: perPage.value };
        if (filters.type) params.type = filters.type;
        if (filters.status) params.status = filters.status;
        if (filters.search.trim()) params.search = filters.search.trim();

        const response = await apiLogin.get('/api/academic/mutations', { params });
        if (requestId !== historyRequestId) return;

        const data = response.data?.data ?? {};
        items.value = data.items ?? [];
        meta.value = data.meta ?? { current_page: 1, per_page: perPage.value, total: 0, last_page: 1 };

        if (items.value.length === 0 && page.value > 1 && page.value > meta.value.last_page) {
            page.value = Math.max(1, meta.value.last_page);
            return loadHistory();
        }
    } catch (error) {
        if (requestId !== historyRequestId) return;
        items.value = [];
        listError.value = errorMessage(error, 'Failed to load the mutation history. Please try again.');
    } finally {
        if (requestId === historyRequestId) listLoading.value = false;
    }
};

const loadYearData = async () => {
    page.value = 1;
    await Promise.all([loadRecap(), loadHistory()]);
};

const loadOptions = async (selectedYearId = null) => {
    optionsLoading.value = true;
    optionsError.value = '';
    try {
        const targetYearId = selectedYearId ?? props.schoolYear?.id ?? null;
        const params = targetYearId ? { school_year_id: targetYearId } : {};
        const response = await apiLogin.get('/api/academic/mutations/options', { params });
        options.value = response.data?.data ?? null;
        yearId.value = targetYearId ?? options.value?.selected_year_id ?? null;
        await loadYearData();
    } catch (error) {
        optionsError.value = errorMessage(error, 'Failed to load mutation options. Please try again.');
        options.value = null;
        recap.value = null;
        items.value = [];
    } finally {
        optionsLoading.value = false;
    }
};

const onYearChange = () => {
    if (!yearId.value) return;
    loadOptions(yearId.value);
};

const onSearchInput = () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        page.value = 1;
        loadHistory();
    }, 300);
};

const onFilterChange = () => {
    page.value = 1;
    loadHistory();
};

const clearFilters = () => {
    filters.type = '';
    filters.status = '';
    filters.search = '';
    page.value = 1;
    loadHistory();
};

const onPage = (event) => {
    if (event.rows && event.rows !== perPage.value) {
        perPage.value = event.rows;
        page.value = 1;
    } else {
        page.value = event.page + 1;
    }
    loadHistory();
};

const searchStudentOptions = async (query, target, placement = 'all') => {
    const term = String(query ?? '').trim();
    if (term.length < 2 || !yearId.value) {
        target.value = [];
        return;
    }
    try {
        const response = await apiLogin.get('/api/students', {
            params: { school_year_id: yearId.value, search: term, placement, per_page: 10 }
        });
        target.value = (response.data?.data ?? []).map((row) => {
            const parts = [row.name];
            if (row.nis) parts.push(row.nis);
            if (row.rombel?.name) parts.push(row.rombel.name);
            return { label: parts.join(' · '), student: row };
        });
    } catch {
        target.value = [];
    }
};

const searchSchoolOptions = async (query, target) => {
    const term = String(query ?? '').trim();
    if (term.length < 2) {
        target.value = [];
        return;
    }
    try {
        const response = await apiLogin.get('/api/academic/mutations/schools', {
            params: { search: term }
        });
        target.value = (response.data?.data?.items ?? []).map((row) => ({
            label: `${row.npsn} — ${row.name}`,
            npsn: row.npsn,
            name: row.name
        }));
    } catch {
        target.value = [];
    }
};

// Template refs are auto-unwrapped, so the ref must be captured here rather than passed from the template.
const completeInStudent = (event) => searchStudentOptions(event.query, inStudentSuggestions);
const completeInSchool = (event) => searchSchoolOptions(event.query, inSchoolSuggestions);
const completeOutStudent = (event) => searchStudentOptions(event.query, outStudentSuggestions, 'placed');
const completeOutSchool = (event) => searchSchoolOptions(event.query, outSchoolSuggestions);
const completeCorrectSchool = (event) => searchSchoolOptions(event.query, correctSchoolSuggestions);

const toastSuccess = (message) => {
    toast.add({ severity: 'success', summary: 'Student Mutations', detail: message, life: 4000 });
};

const applyActionError = (error, errorRef, fieldErrorsRef, conflictRef, fallback) => {
    const body = error.response?.data ?? {};
    fieldErrorsRef.value = body.errors ?? {};
    if (error.response?.status === 409) {
        errorRef.value = body.message ?? 'Conflict: The data changed since it was loaded. Reload and try again.';
        conflictRef.value = { message: body.message, versions: body.current_versions ?? null };
    } else {
        errorRef.value = body.message ?? fallback;
        conflictRef.value = null;
    }
};

const versionSummary = (conflict) => {
    if (!conflict?.versions) return '';
    const parts = [];
    if (conflict.versions.student_version != null) parts.push(`student ${conflict.versions.student_version}`);
    if (conflict.versions.membership_version != null) parts.push(`membership ${conflict.versions.membership_version}`);
    if (conflict.versions.mutation_version != null) parts.push(`mutation ${conflict.versions.mutation_version}`);
    return parts.length > 0 ? `Current versions: ${parts.join(', ')}.` : '';
};

const reloadAfterConflict = async () => {
    closeAllDialogs();
    await loadOptions(yearId.value);
    emit('mutated');
};

const closeAllDialogs = () => {
    inDialog.value = false;
    outDialog.value = false;
    cancelDialog.value = false;
    correctDialog.value = false;
};

// ── Mutation In ─────────────────────────────────────────────────────────────
const inDialog = ref(false);
const inSaving = ref(false);
const inError = ref('');
const inFieldErrors = ref({});
const inConflict = ref(null);
const inStudentSuggestions = ref([]);
const inSchoolSuggestions = ref([]);
const inForm = reactive({
    student: null,
    studentOption: null,
    classId: null,
    effectiveDate: null,
    schoolText: '',
    schoolNpsn: '',
    reason: ''
});

const resetInForm = () => {
    inForm.student = null;
    inForm.studentOption = null;
    inForm.classId = classes.value[0]?.id ?? null;
    inForm.effectiveDate = dateToInput(defaultEffectiveDate());
    inForm.schoolText = '';
    inForm.schoolNpsn = '';
    inForm.reason = '';
    inStudentSuggestions.value = [];
    inSchoolSuggestions.value = [];
    inError.value = '';
    inFieldErrors.value = {};
    inConflict.value = null;
};

const openInDialog = () => {
    resetInForm();
    inDialog.value = true;
};

const onInStudentSelect = (event) => {
    inForm.studentOption = event.value ?? null;
    inForm.student = event.value?.student ?? null;
};

const onInSchoolSelect = (event) => {
    inForm.schoolNpsn = event.value?.npsn ?? '';
    inForm.schoolText = event.value?.label ?? '';
};

const validateIn = () => {
    const errors = {};
    if (!inForm.student) errors.student = ['Select a student.'];
    if (!inForm.classId) errors.school_rombel_id = ['Select a class.'];
    if (!dateToIso(inForm.effectiveDate)) errors.effective_date = ['Select an effective date.'];
    if (!inForm.schoolNpsn.trim()) errors.school_npsn = ['Enter or select the origin school NPSN.'];
    const reason = inForm.reason.trim();
    if (reason.length < 10) errors.reason = ['The reason must be at least 10 characters.'];
    else if (reason.length > 255) errors.reason = ['The reason may not exceed 255 characters.'];
    return errors;
};

const submitIn = async () => {
    if (inSaving.value) return;
    inError.value = '';
    inFieldErrors.value = {};
    inConflict.value = null;

    const errors = validateIn();
    if (Object.keys(errors).length > 0) {
        inFieldErrors.value = errors;
        return;
    }

    inSaving.value = true;
    try {
        const response = await apiLogin.post(`/api/students/${inForm.student.id}/mutations/in`, {
            school_year_id: yearId.value,
            school_rombel_id: inForm.classId,
            effective_date: dateToIso(inForm.effectiveDate),
            school_npsn: inForm.schoolNpsn.trim(),
            reason: inForm.reason.trim(),
            expected_student_version: Number(inForm.student.version)
        });
        inDialog.value = false;
        toastSuccess(response.data?.message ?? 'Mutation-in recorded successfully.');
        emit('mutated');
        await loadYearData();
    } catch (error) {
        applyActionError(error, inError, inFieldErrors, inConflict, 'Failed to record the mutation-in.');
    } finally {
        inSaving.value = false;
    }
};

// ── Mutation Out ────────────────────────────────────────────────────────────
const outDialog = ref(false);
const outSaving = ref(false);
const outError = ref('');
const outFieldErrors = ref({});
const outConflict = ref(null);
const outStudentSuggestions = ref([]);
const outSchoolSuggestions = ref([]);
const outForm = reactive({
    student: null,
    studentOption: null,
    membershipId: null,
    effectiveDate: null,
    schoolText: '',
    schoolNpsn: '',
    reason: ''
});

const resetOutForm = () => {
    outForm.student = null;
    outForm.studentOption = null;
    outForm.membershipId = null;
    outForm.effectiveDate = dateToInput(defaultEffectiveDate());
    outForm.schoolText = '';
    outForm.schoolNpsn = '';
    outForm.reason = '';
    outStudentSuggestions.value = [];
    outSchoolSuggestions.value = [];
    outError.value = '';
    outFieldErrors.value = {};
    outConflict.value = null;
};

const openOutDialog = () => {
    resetOutForm();
    outDialog.value = true;
};

const onOutStudentSelect = (event) => {
    outForm.studentOption = event.value ?? null;
    outForm.student = event.value?.student ?? null;
    outForm.membershipId = outForm.student?.rombel?.membership_id ?? null;
};

const onOutSchoolSelect = (event) => {
    outForm.schoolNpsn = event.value?.npsn ?? '';
    outForm.schoolText = event.value?.label ?? '';
};

const validateOut = () => {
    const errors = {};
    if (!outForm.student) errors.student = ['Select a student.'];
    if (!outForm.membershipId) errors.student_rombel_id = ['The selected student has no active class membership in this academic year.'];
    if (!dateToIso(outForm.effectiveDate)) errors.effective_date = ['Select an effective date.'];
    if (!outForm.schoolNpsn.trim()) errors.school_npsn = ['Enter or select the destination school NPSN.'];
    const reason = outForm.reason.trim();
    if (reason.length < 10) errors.reason = ['The reason must be at least 10 characters.'];
    else if (reason.length > 255) errors.reason = ['The reason may not exceed 255 characters.'];
    return errors;
};

const submitOut = async () => {
    if (outSaving.value) return;
    outError.value = '';
    outFieldErrors.value = {};
    outConflict.value = null;

    const errors = validateOut();
    if (Object.keys(errors).length > 0) {
        outFieldErrors.value = errors;
        return;
    }

    outSaving.value = true;
    try {
        const response = await apiLogin.post(`/api/students/${outForm.student.id}/mutations/out`, {
            student_rombel_id: outForm.membershipId,
            effective_date: dateToIso(outForm.effectiveDate),
            school_npsn: outForm.schoolNpsn.trim(),
            reason: outForm.reason.trim(),
            expected_student_version: Number(outForm.student.version),
            expected_membership_version: Number(outForm.student.rombel.membership_version)
        });
        outDialog.value = false;
        toastSuccess(response.data?.message ?? 'Mutation-out recorded successfully.');
        emit('mutated');
        await loadYearData();
    } catch (error) {
        applyActionError(error, outError, outFieldErrors, outConflict, 'Failed to record the mutation-out.');
    } finally {
        outSaving.value = false;
    }
};

// ── Cancel ──────────────────────────────────────────────────────────────────
const cancelDialog = ref(false);
const cancelSaving = ref(false);
const cancelError = ref('');
const cancelFieldErrors = ref({});
const cancelConflict = ref(null);
const cancelTarget = ref(null);
const cancelForm = reactive({ reason: '' });

const cancelConsequence = computed(() =>
    cancelTarget.value?.type === 'in'
        ? 'The class membership created by this mutation will be archived. The record is kept for audit; nothing is deleted.'
        : 'The class membership will be reopened: the status returns to Active and the exit date, exit school, and graduation date are cleared.'
);

const openCancel = (row) => {
    cancelTarget.value = row;
    cancelForm.reason = '';
    cancelError.value = '';
    cancelFieldErrors.value = {};
    cancelConflict.value = null;
    cancelDialog.value = true;
};

const submitCancel = async () => {
    if (cancelSaving.value || !cancelTarget.value?.membership) return;
    cancelError.value = '';
    cancelFieldErrors.value = {};
    cancelConflict.value = null;

    const reason = cancelForm.reason.trim();
    if (reason.length < 10 || reason.length > 255) {
        cancelFieldErrors.value = { reason: [reason.length < 10 ? 'The reason must be at least 10 characters.' : 'The reason may not exceed 255 characters.'] };
        return;
    }

    cancelSaving.value = true;
    const target = cancelTarget.value;
    try {
        const response = await apiLogin.post(`/api/students/${target.student.id}/mutations/${target.id}/cancel`, {
            reason,
            expected_student_version: Number(target.student.version),
            expected_membership_version: Number(target.membership.version),
            expected_mutation_version: Number(target.version)
        });
        cancelDialog.value = false;
        toastSuccess(response.data?.message ?? 'Mutation cancelled successfully.');
        emit('mutated');
        await loadYearData();
    } catch (error) {
        applyActionError(error, cancelError, cancelFieldErrors, cancelConflict, 'Failed to cancel the mutation.');
    } finally {
        cancelSaving.value = false;
    }
};

// ── Locked corrections ──────────────────────────────────────────────────────
const correctDialog = ref(false);
const correctSaving = ref(false);
const correctError = ref('');
const correctFieldErrors = ref({});
const correctConflict = ref(null);
const correctTarget = ref(null);
const correctStudentVersion = ref(1);
const correctStudentId = ref(null);
const correctMutations = ref([]);
const correctMemberships = ref([]);
const correctMutationsLoading = ref(false);
const correctMembershipsLoading = ref(false);
const correctMutationSuggestions = ref([]);
const correctSchoolSuggestions = ref([]);
const correctForm = reactive({
    action: 'mutation_in',
    mutationId: null,
    membershipId: null,
    effectiveDate: null,
    schoolText: '',
    schoolNpsn: '',
    targetStatusId: 2,
    reason: '',
    approvalReference: ''
});

const isMutationAction = computed(() => ['mutation_in', 'mutation_out', 'mutation_cancel'].includes(correctForm.action));
const isMembershipAction = computed(() => ['membership_exit', 'membership_reopen'].includes(correctForm.action));
const needsDate = computed(() => ['mutation_in', 'mutation_out', 'membership_exit'].includes(correctForm.action));

const selectedCorrectMutation = computed(() => correctMutations.value.find((row) => row.id === correctForm.mutationId) ?? null);

const selectedCorrectMembership = computed(() => correctMemberships.value.find((row) => row.id === correctForm.membershipId) ?? null);

const correctMutationOptions = computed(() => {
    const type = correctForm.action === 'mutation_in' ? 'in' : correctForm.action === 'mutation_out' ? 'out' : null;
    return correctMutations.value
        .filter((row) => row.status === 'active' && (type === null || row.type === type))
        .map((row) => ({
            id: row.id,
            label: `${typeLabel(row.type)} · ${row.effective_date ?? '-'} · NPSN ${row.school_npsn}`
        }));
});

const correctMembershipOptions = computed(() => {
    if (correctForm.action === 'membership_exit') {
        return correctMemberships.value.filter((row) => !row.archived_at && !row.latest_student_exit_date && (row.student_status_id === 1 || row.student_status_id === null));
    }
    if (correctForm.action === 'membership_reopen') {
        return correctMemberships.value.filter((row) => !row.archived_at && [2, 6].includes(row.student_status_id));
    }
    return [];
});

const expectedCorrectMembership = computed(() => {
    if (isMutationAction.value) return selectedCorrectMutation.value?.membership ?? null;
    const membership = selectedCorrectMembership.value;
    return membership ? { id: membership.id, version: membership.version } : null;
});

const resetCorrectForm = () => {
    correctForm.action = 'mutation_in';
    correctForm.mutationId = null;
    correctForm.membershipId = null;
    correctForm.effectiveDate = null;
    correctForm.schoolText = '';
    correctForm.schoolNpsn = '';
    correctForm.targetStatusId = 2;
    correctForm.reason = '';
    correctForm.approvalReference = '';
    correctMutations.value = [];
    correctMemberships.value = [];
    correctMutationSuggestions.value = [];
    correctSchoolSuggestions.value = [];
    correctError.value = '';
    correctFieldErrors.value = {};
    correctConflict.value = null;
};

const applyMutationDefaults = (mutation) => {
    if (!mutation) return;
    if (correctForm.action === 'mutation_cancel') {
        correctForm.effectiveDate = null;
        correctForm.schoolText = '';
        correctForm.schoolNpsn = '';
        return;
    }
    correctForm.effectiveDate = dateToInput(mutation.effective_date);
    correctForm.schoolNpsn = mutation.school_npsn ?? '';
    correctForm.schoolText = mutation.school_npsn ? `${mutation.school_npsn}${mutation.school_name ? ' — ' + mutation.school_name : ''}` : '';
};

const loadCorrectMutations = async (studentId) => {
    correctMutationsLoading.value = true;
    try {
        const response = await apiLogin.get(`/api/students/${studentId}/mutations`);
        const data = response.data?.data ?? {};
        correctStudentVersion.value = Number(data.student?.version ?? correctStudentVersion.value);
        correctMutations.value = (data.mutations ?? []).filter((row) => row.year_id === yearId.value && row.status === 'active');
    } catch {
        correctMutations.value = [];
    } finally {
        correctMutationsLoading.value = false;
    }
};

const loadCorrectMemberships = async (studentId) => {
    correctMembershipsLoading.value = true;
    try {
        const response = await apiLogin.get(`/api/students/${studentId}`);
        const rows = response.data?.data?.student_rombels ?? [];
        correctMemberships.value = rows
            .filter((row) => Number(row.schoolRombel?.school_year_id) === Number(yearId.value))
            .map((row) => {
                const membership = {
                    id: Number(row.id),
                    version: Number(row.version ?? 1),
                    class_name: row.schoolRombel?.name ?? `Class #${row.school_rombel}`,
                    student_status_id: row.student_status_id === null ? null : Number(row.student_status_id),
                    latest_student_entry_date: row.latest_student_entry_date,
                    latest_student_exit_date: row.latest_student_exit_date,
                    latest_student_graduation_date: row.latest_student_graduation_date,
                    archived_at: row.archived_at
                };
                membership.label = `${membership.class_name} · ${statusLabel(membership.student_status_id)} · entry ${formatDate(membership.latest_student_entry_date)} · v${membership.version}`;
                return membership;
            });
    } catch {
        correctMemberships.value = [];
    } finally {
        correctMembershipsLoading.value = false;
    }
};

const openCorrect = async (row) => {
    if (!year.value?.is_locked || !canCorrectLocked.value) return;

    correctTarget.value = row;
    correctStudentId.value = row.student?.id ?? null;
    correctStudentVersion.value = Number(row.student?.version ?? 1);
    resetCorrectForm();
    correctForm.action = row.status === 'active' ? (row.type === 'in' ? 'mutation_in' : 'mutation_out') : 'membership_exit';
    correctForm.mutationId = row.id;
    applyMutationDefaults(row);
    correctDialog.value = true;

    await Promise.all([loadCorrectMutations(correctStudentId.value), loadCorrectMemberships(correctStudentId.value)]);
    onCorrectActionChange();
};

const onCorrectActionChange = () => {
    correctError.value = '';
    correctFieldErrors.value = {};
    correctConflict.value = null;

    if (isMutationAction.value) {
        const type = correctForm.action === 'mutation_in' ? 'in' : correctForm.action === 'mutation_out' ? 'out' : null;
        const candidates = correctMutations.value.filter((row) => row.status === 'active' && (type === null || row.type === type));
        if (!candidates.some((row) => row.id === correctForm.mutationId)) {
            correctForm.mutationId = candidates[0]?.id ?? null;
        }
        applyMutationDefaults(selectedCorrectMutation.value);
        return;
    }

    const candidates = correctMembershipOptions.value;
    if (!candidates.some((row) => row.id === correctForm.membershipId)) {
        correctForm.membershipId = candidates[0]?.id ?? null;
    }
    correctForm.effectiveDate = correctForm.action === 'membership_exit' ? dateToInput(defaultEffectiveDate()) : null;
    correctForm.schoolText = '';
    correctForm.schoolNpsn = '';
    correctForm.targetStatusId = 2;
};

const onCorrectMutationChange = () => {
    correctError.value = '';
    correctFieldErrors.value = {};
    applyMutationDefaults(selectedCorrectMutation.value);
};

const validateCorrect = () => {
    const errors = {};
    if (isMutationAction.value && !correctForm.mutationId) errors.mutation_id = ['Select an active mutation.'];
    if (isMembershipAction.value && !correctForm.membershipId) errors.student_rombel_id = ['Select a class membership.'];

    if (['mutation_in', 'mutation_out'].includes(correctForm.action)) {
        if (!dateToIso(correctForm.effectiveDate) && !correctForm.schoolNpsn.trim()) {
            errors.effective_date = ['Provide a corrected effective date or school.'];
        }
    }
    if (correctForm.action === 'membership_exit' && !dateToIso(correctForm.effectiveDate)) {
        errors.effective_date = ['Select an effective date.'];
    }
    if (correctForm.action === 'membership_exit' && !correctForm.targetStatusId) {
        errors.target_status_id = ['Select the target status.'];
    }
    if (!expectedCorrectMembership.value) {
        errors.student_rombel_id = ['The class membership for the selected record is not available.'];
    }

    const reason = correctForm.reason.trim();
    if (reason.length < 10) errors.reason = ['The reason must be at least 10 characters.'];
    else if (reason.length > 255) errors.reason = ['The reason may not exceed 255 characters.'];

    return errors;
};

const submitCorrect = async () => {
    if (correctSaving.value || !correctTarget.value) return;
    correctError.value = '';
    correctFieldErrors.value = {};
    correctConflict.value = null;

    const errors = validateCorrect();
    if (Object.keys(errors).length > 0) {
        correctFieldErrors.value = errors;
        return;
    }

    const membership = expectedCorrectMembership.value;
    const payload = {
        action: correctForm.action,
        school_year_id: yearId.value,
        mutation_id: isMutationAction.value ? correctForm.mutationId : null,
        student_rombel_id: isMembershipAction.value ? correctForm.membershipId : null,
        effective_date: needsDate.value ? dateToIso(correctForm.effectiveDate) : null,
        school_npsn: correctForm.schoolNpsn.trim() || null,
        target_status_id: correctForm.action === 'membership_exit' ? correctForm.targetStatusId : null,
        reason: correctForm.reason.trim(),
        approval_reference: correctForm.approvalReference.trim() || null,
        expected_student_version: correctStudentVersion.value,
        expected_membership_version: Number(membership.version),
        expected_mutation_version: isMutationAction.value ? Number(selectedCorrectMutation.value.version) : null
    };

    correctSaving.value = true;
    try {
        const response = await apiLogin.post(`/api/students/${correctTarget.value.student.id}/locked-corrections`, payload);
        correctDialog.value = false;
        toastSuccess(response.data?.message ?? 'Locked correction recorded successfully.');
        emit('mutated');
        await loadYearData();
    } catch (error) {
        applyActionError(error, correctError, correctFieldErrors, correctConflict, 'Failed to record the locked correction.');
    } finally {
        correctSaving.value = false;
    }
};

// ── Timeline ────────────────────────────────────────────────────────────────
const timelineVisible = ref(false);
const timelineStudent = ref(null);

const openTimeline = (row) => {
    timelineStudent.value = {
        id: row.student.id,
        name: row.student.name,
        nis: row.student.nis
    };
    timelineVisible.value = true;
};

watch(
    () => props.schoolYear?.id,
    (newId) => {
        if (newId && newId !== yearId.value) {
            loadOptions(newId);
        }
    }
);

onMounted(() => loadOptions(props.schoolYear?.id));
</script>

<template>
    <div class="card w-full">
        <div class="flex flex-col gap-4">
            <div class="flex flex-col xl:flex-row xl:items-center gap-3">
                <div>
                    <div class="font-semibold text-xl">{{ t('students.mutations.title') }}</div>
                    <div class="text-sm text-surface-500">{{ t('students.mutations.subtitle') }}</div>
                </div>
                <div class="flex flex-wrap gap-2 xl:ml-auto items-center">
                    <Select
                        v-if="!schoolYear?.id"
                        v-model="yearId"
                        :options="years"
                        optionLabel="name"
                        optionValue="id"
                        :placeholder="t('students.mutations.academicYear')"
                        class="w-full sm:w-56"
                        :loading="optionsLoading"
                        :disabled="optionsLoading || years.length === 0"
                        aria-label="Academic year"
                        @change="onYearChange"
                    />
                    <Tag v-if="year?.is_locked" value="Locked" severity="danger" />
                    <Tag v-else-if="year?.is_active" value="Active" severity="success" />
                    <Tag v-if="year && !year.has_dates" value="No dates" severity="warn" />
                    <Button
                        label="Laporan Mutasi (PDF A4 / Excel)"
                        icon="pi pi-file-pdf"
                        severity="success"
                        size="small"
                        @click="reportDialogVisible = true"
                    />
                    <Button
                        v-if="canManage"
                        label="Impor Mutasi"
                        icon="pi pi-upload"
                        severity="secondary"
                        variant="outlined"
                        size="small"
                        :disabled="!canMutate"
                        @click="importDialogVisible = true"
                    />
                    <Button v-if="canManage" :label="t('students.mutations.btnNewIn')" icon="pi pi-plus" :disabled="!canMutate" @click="openInDialog" />
                    <Button v-if="canManage" :label="t('students.mutations.btnNewOut')" icon="pi pi-arrow-right" severity="secondary" variant="outlined" :disabled="!canMutate" @click="openOutDialog" />
                </div>
            </div>

            <Message v-if="optionsError" severity="error" :closable="false">{{ optionsError }}</Message>
            <Message v-else-if="year?.is_locked" severity="informational" :closable="false"> {{ t('students.mutations.yearLockedNotice') }} </Message>
            <Message v-else-if="year && !year.has_dates" severity="warn" :closable="false"> {{ t('students.mutations.noPeriodDatesNotice') }} </Message>

            <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
                <div v-for="card in recapCards" :key="card.label" class="border border-surface-200 dark:border-surface-700 rounded p-4 flex flex-col gap-2">
                    <div class="flex items-center justify-between text-sm text-surface-500">
                        <span>{{ card.label }}</span>
                        <i :class="card.icon" aria-hidden="true"></i>
                    </div>
                    <Skeleton v-if="recapLoading" width="4rem" height="2rem" />
                    <div v-else class="text-2xl font-semibold">{{ card.value }}</div>
                </div>
            </div>
            <div class="text-xs text-surface-500">
                <span v-if="recap">{{ recap.period.start_date }} to {{ recap.period.end_date }}. {{ recap.note }}</span>
                <span v-else-if="recapError" class="text-red-500">{{ recapError }}</span>
            </div>

            <div class="flex flex-col xl:flex-row gap-3 xl:items-center">
                <IconField class="w-full xl:w-80">
                    <InputIcon><i class="pi pi-search" /></InputIcon>
                    <InputText v-model="filters.search" :placeholder="t('students.mutations.searchPlaceholder')" class="w-full" @input="onSearchInput" />
                </IconField>
                <Select v-model="filters.type" :options="typeOptions" optionLabel="label" optionValue="value" class="w-full xl:w-44" aria-label="Mutation type" @change="onFilterChange" />
                <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value" class="w-full xl:w-44" aria-label="Mutation status" @change="onFilterChange" />
                <Button :label="t('common.action.clear')" icon="pi pi-filter-slash" variant="outlined" class="xl:ml-auto" @click="clearFilters" />
            </div>

            <DataTable
                :value="items"
                :lazy="true"
                paginator
                showGridlines
                :rows="perPage"
                :totalRecords="meta.total"
                :first="(meta.current_page - 1) * meta.per_page"
                :rowsPerPageOptions="perPageOptions"
                dataKey="id"
                :loading="listLoading"
                paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                currentPageReportTemplate="Showing {first} to {last} of {totalRecords} mutations"
                @page="onPage"
            >
                <template #loading>{{ t('students.mutations.loading') }}</template>
                <template #empty>
                    <div class="flex flex-col items-center gap-3 py-6">
                        <span v-if="listError" class="text-red-500">{{ listError }}</span>
                        <span v-else>No mutations match the current filters.</span>
                        <Button v-if="listError" icon="pi pi-refresh" :label="t('common.action.retry')" @click="loadHistory" />
                    </div>
                </template>
                <Column field="effective_date" :header="t('students.mutations.columns.effectiveDate')" style="min-width: 8rem">
                    <template #body="{ data }">{{ formatDate(data.effective_date) }}</template>
                </Column>
                <Column :header="t('students.mutations.columns.student')" style="min-width: 12rem">
                    <template #body="{ data }">
                        <div class="font-medium">{{ data.student?.name ?? '-' }}</div>
                        <div class="text-xs text-surface-500">{{ data.student?.nis ?? '-' }}</div>
                    </template>
                </Column>
                <Column :header="t('students.mutations.columns.class')" style="min-width: 9rem">
                    <template #body="{ data }">{{ data.class?.name ?? '-' }}</template>
                </Column>
                <Column :header="t('students.mutations.columns.type')" style="min-width: 8rem">
                    <template #body="{ data }">
                        <Tag :value="typeLabel(data.type)" :severity="data.type === 'in' ? 'info' : 'warn'" />
                    </template>
                </Column>
                <Column :header="t('students.mutations.columns.school')" style="min-width: 12rem">
                    <template #body="{ data }">
                        <div class="font-medium">{{ data.school_npsn }}</div>
                        <div class="text-xs text-surface-500">{{ data.school_name ?? '-' }}</div>
                    </template>
                </Column>
                <Column :header="t('students.mutations.columns.reason')" style="min-width: 12rem">
                    <template #body="{ data }">
                        <span :title="data.reason">{{ data.reason }}</span>
                    </template>
                </Column>
                <Column :header="t('students.mutations.columns.status')" style="min-width: 7rem">
                    <template #body="{ data }">
                        <Tag :value="data.status === 'active' ? 'Active' : 'Cancelled'" :severity="data.status === 'active' ? 'success' : 'danger'" />
                    </template>
                </Column>
                <Column :header="t('students.mutations.columns.recordedBy')" style="min-width: 10rem">
                    <template #body="{ data }">
                        <div>{{ data.created_by?.name ?? '-' }}</div>
                        <div class="text-xs text-surface-500">{{ formatDateTime(data.created_at) }}</div>
                    </template>
                </Column>
                <Column :header="t('students.mutations.columns.action')" :exportable="false" style="min-width: 10rem">
                    <template #body="{ data }">
                        <Button icon="pi pi-history" aria-label="View student mutation timeline" variant="outlined" rounded class="mr-2" v-tooltip.top="t('students.mutations.tooltips.timeline')" @click="openTimeline(data)" />
                        <Button
                            v-if="canManage && !year?.is_locked && data.status === 'active' && data.membership"
                            icon="pi pi-times"
                            aria-label="Cancel mutation"
                            variant="outlined"
                            rounded
                            severity="danger"
                            class="mr-2"
                            v-tooltip.top="t('students.mutations.tooltips.cancel')"
                            @click="openCancel(data)"
                        />
                        <Button
                            v-if="canCorrectLocked && year?.is_locked"
                            icon="pi pi-pencil"
                            aria-label="Correct locked mutation"
                            variant="outlined"
                            rounded
                            severity="warn"
                            v-tooltip.top="t('students.mutations.tooltips.correctLocked')"
                            @click="openCorrect(data)"
                        />
                    </template>
                </Column>
            </DataTable>
        </div>

        <StudentMutationTimelineDialog v-model:visible="timelineVisible" :student="timelineStudent" />
    </div>

    <Dialog v-model:visible="inDialog" modal :draggable="false" :style="{ width: 'min(94vw, 40rem)' }" :header="t('students.mutations.dialogIn.title')">
        <div class="flex flex-col gap-4">
            <Message severity="info" :closable="false"> {{ t('students.mutations.dialogIn.notice') }} </Message>

            <div class="flex flex-col gap-2">
                <label for="inStudent" class="font-medium">{{ t('students.mutations.dialogIn.student') }} <span class="text-red-500">*</span></label>
                <AutoComplete
                    id="inStudent"
                    v-model="inForm.studentOption"
                    :suggestions="inStudentSuggestions"
                    optionLabel="label"
                    :minLength="2"
                    forceSelection
                    class="w-full"
                    :placeholder="t('students.mutations.dialogIn.studentSearchPlaceholder')"
                    @complete="completeInStudent"
                    @item-select="onInStudentSelect"
                />
                <small class="text-surface-500">{{ t('students.mutations.dialogIn.searchHelper') }}</small>
                <small v-if="fieldError(inFieldErrors, 'student_id')" class="text-red-500">{{ fieldError(inFieldErrors, 'student_id') }}</small>
            </div>

            <Message v-if="inForm.student && inForm.student.placement === 'placed'" severity="warn" :closable="false">
                This student already has an active class ({{ inForm.student.rombel?.name }}) in the selected academic year. Record a mutation-out before a new mutation-in.
            </Message>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label for="inClass" class="font-medium">{{ t('students.mutations.dialogIn.class') }} <span class="text-red-500">*</span></label>
                    <Select id="inClass" v-model="inForm.classId" :options="classes" optionLabel="name" optionValue="id" filter :placeholder="t('students.mutations.dialogIn.classSelectPlaceholder')" class="w-full" />
                    <small v-if="fieldError(inFieldErrors, 'school_rombel_id')" class="text-red-500">{{ fieldError(inFieldErrors, 'school_rombel_id') }}</small>
                </div>
                <div class="flex flex-col gap-2">
                    <label for="inDate" class="font-medium">{{ t('students.mutations.dialogIn.effectiveDate') }} <span class="text-red-500">*</span></label>
                    <DatePicker id="inDate" v-model="inForm.effectiveDate" dateFormat="yy-mm-dd" showIcon manualInput fluid />
                    <small v-if="fieldError(inFieldErrors, 'effective_date')" class="text-red-500">{{ fieldError(inFieldErrors, 'effective_date') }}</small>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label for="inSchool" class="font-medium">{{ t('students.mutations.dialogIn.originSchoolNpsn') }} <span class="text-red-500">*</span></label>
                <AutoComplete
                    id="inSchool"
                    v-model="inForm.schoolText"
                    :suggestions="inSchoolSuggestions"
                    optionLabel="label"
                    :minLength="2"
                    class="w-full"
                    :placeholder="t('students.mutations.dialogIn.schoolSearchPlaceholder')"
                    @complete="completeInSchool"
                    @item-select="onInSchoolSelect"
                />
                <small class="text-surface-500">{{ t('students.mutations.dialogIn.schoolSearchHelper') }}</small>
                <small v-if="fieldError(inFieldErrors, 'school_npsn')" class="text-red-500">{{ fieldError(inFieldErrors, 'school_npsn') }}</small>
            </div>

            <div class="flex flex-col gap-2">
                <label for="inReason" class="font-medium">{{ t('students.mutations.dialogIn.reason') }} <span class="text-red-500">*</span></label>
                <Textarea id="inReason" v-model="inForm.reason" rows="3" autoResize maxlength="255" class="w-full" />
                <small class="text-surface-500">{{ inForm.reason.length }}/255 characters (minimum 10).</small>
                <small v-if="fieldError(inFieldErrors, 'reason')" class="text-red-500">{{ fieldError(inFieldErrors, 'reason') }}</small>
            </div>

            <Message v-if="inError" severity="error" :closable="false">{{ inError }}</Message>
            <div v-if="inConflict" class="border border-red-300 rounded p-3 text-sm">
                <div class="font-medium text-red-600">Data conflict</div>
                <p class="mt-1">{{ inConflict.message }}</p>
                <p v-if="versionSummary(inConflict)" class="mt-1">{{ versionSummary(inConflict) }}</p>
                <Button class="mt-2" size="small" label="Reload" icon="pi pi-refresh" @click="reloadAfterConflict" />
            </div>
        </div>
        <template #footer>
            <div class="flex justify-end gap-2">
                <Button :label="t('common.action.cancel')" icon="pi pi-times" text severity="secondary" :disabled="inSaving" @click="inDialog = false" />
                <Button :label="t('students.mutations.dialogIn.btnSubmit')" icon="pi pi-check" :loading="inSaving" @click="submitIn" />
            </div>
        </template>
    </Dialog>

    <Dialog v-model:visible="outDialog" modal :draggable="false" :style="{ width: 'min(94vw, 40rem)' }" :header="t('students.mutations.dialogOut.title')">
        <div class="flex flex-col gap-4">
            <Message severity="info" :closable="false"> {{ t('students.mutations.dialogOut.notice') }} </Message>

            <div class="flex flex-col gap-2">
                <label for="outStudent" class="font-medium">{{ t('students.mutations.dialogOut.student') }} <span class="text-red-500">*</span></label>
                <AutoComplete
                    id="outStudent"
                    v-model="outForm.studentOption"
                    :suggestions="outStudentSuggestions"
                    optionLabel="label"
                    :minLength="2"
                    forceSelection
                    class="w-full"
                    :placeholder="t('students.mutations.dialogOut.studentSearchPlaceholder')"
                    @complete="completeOutStudent"
                    @item-select="onOutStudentSelect"
                />
                <small class="text-surface-500">{{ t('students.mutations.dialogOut.studentSearchHelper') }}</small>
                <small v-if="fieldError(outFieldErrors, 'student_id')" class="text-red-500">{{ fieldError(outFieldErrors, 'student_id') }}</small>
            </div>

            <Message v-if="outForm.student && !outForm.membershipId" severity="warn" :closable="false"> {{ t('students.mutations.dialogOut.noActiveClassNotice') }} </Message>

            <div v-if="outForm.student?.rombel" class="text-sm border border-surface-200 dark:border-surface-700 rounded p-3">
                <div>
                    Class: <strong>{{ outForm.student.rombel.name }}</strong>
                </div>
                <div class="text-surface-500">Membership version: {{ outForm.student.rombel.membership_version }}</div>
            </div>

            <div class="flex flex-col gap-2">
                <label for="outDate" class="font-medium">{{ t('students.mutations.dialogOut.effectiveDate') }} <span class="text-red-500">*</span></label>
                <DatePicker id="outDate" v-model="outForm.effectiveDate" dateFormat="yy-mm-dd" showIcon manualInput fluid />
                <small class="text-surface-500">{{ t('students.mutations.dialogOut.dateHelper') }}</small>
                <small v-if="fieldError(outFieldErrors, 'effective_date')" class="text-red-500">{{ fieldError(outFieldErrors, 'effective_date') }}</small>
            </div>

            <div class="flex flex-col gap-2">
                <label for="outSchool" class="font-medium">{{ t('students.mutations.dialogOut.destSchoolNpsn') }} <span class="text-red-500">*</span></label>
                <AutoComplete
                    id="outSchool"
                    v-model="outForm.schoolText"
                    :suggestions="outSchoolSuggestions"
                    optionLabel="label"
                    :minLength="2"
                    class="w-full"
                    :placeholder="t('students.mutations.dialogIn.schoolSearchPlaceholder')"
                    @complete="completeOutSchool"
                    @item-select="onOutSchoolSelect"
                />
                <small class="text-surface-500">{{ t('students.mutations.dialogIn.schoolSearchHelper') }}</small>
                <small v-if="fieldError(outFieldErrors, 'school_npsn')" class="text-red-500">{{ fieldError(outFieldErrors, 'school_npsn') }}</small>
            </div>

            <div class="flex flex-col gap-2">
                <label for="outReason" class="font-medium">{{ t('students.mutations.dialogOut.reason') }} <span class="text-red-500">*</span></label>
                <Textarea id="outReason" v-model="outForm.reason" rows="3" autoResize maxlength="255" class="w-full" />
                <small class="text-surface-500">{{ outForm.reason.length }}/255 characters (minimum 10).</small>
                <small v-if="fieldError(outFieldErrors, 'reason')" class="text-red-500">{{ fieldError(outFieldErrors, 'reason') }}</small>
            </div>

            <Message v-if="outError" severity="error" :closable="false">{{ outError }}</Message>
            <div v-if="outConflict" class="border border-red-300 rounded p-3 text-sm">
                <div class="font-medium text-red-600">Data conflict</div>
                <p class="mt-1">{{ outConflict.message }}</p>
                <p v-if="versionSummary(outConflict)" class="mt-1">{{ versionSummary(outConflict) }}</p>
                <Button class="mt-2" size="small" label="Reload" icon="pi pi-refresh" @click="reloadAfterConflict" />
            </div>
        </div>
        <template #footer>
            <div class="flex justify-end gap-2">
                <Button :label="t('common.action.cancel')" icon="pi pi-times" text severity="secondary" :disabled="outSaving" @click="outDialog = false" />
                <Button :label="t('students.mutations.dialogOut.btnSubmit')" icon="pi pi-check" :loading="outSaving" @click="submitOut" />
            </div>
        </template>
    </Dialog>

    <Dialog v-model:visible="cancelDialog" modal :draggable="false" :style="{ width: 'min(94vw, 36rem)' }" :header="t('students.mutations.dialogCancel.title')">
        <div v-if="cancelTarget" class="flex flex-col gap-4">
            <div class="text-sm border border-surface-200 dark:border-surface-700 rounded p-3 flex flex-col gap-1">
                <div>
                    <strong>{{ cancelTarget.student?.name }}</strong> · NIS {{ cancelTarget.student?.nis ?? '-' }}
                </div>
                <div>{{ typeLabel(cancelTarget.type) }} on {{ formatDate(cancelTarget.effective_date) }}</div>
                <div>Class: {{ cancelTarget.class?.name ?? '-' }} · School: {{ cancelTarget.school_npsn }}</div>
            </div>

            <Message severity="warn" :closable="false">{{ cancelConsequence }}</Message>

            <div class="flex flex-col gap-2">
                <label for="cancelReason" class="font-medium">{{ t('students.mutations.dialogCancel.reason') }} <span class="text-red-500">*</span></label>
                <Textarea id="cancelReason" v-model="cancelForm.reason" rows="3" autoResize maxlength="255" class="w-full" />
                <small class="text-surface-500">{{ cancelForm.reason.length }}/255 characters (minimum 10).</small>
                <small v-if="fieldError(cancelFieldErrors, 'reason')" class="text-red-500">{{ fieldError(cancelFieldErrors, 'reason') }}</small>
            </div>

            <Message v-if="cancelError" severity="error" :closable="false">{{ cancelError }}</Message>
            <div v-if="cancelConflict" class="border border-red-300 rounded p-3 text-sm">
                <div class="font-medium text-red-600">Data conflict</div>
                <p class="mt-1">{{ cancelConflict.message }}</p>
                <p v-if="versionSummary(cancelConflict)" class="mt-1">{{ versionSummary(cancelConflict) }}</p>
                <Button class="mt-2" size="small" label="Reload" icon="pi pi-refresh" @click="reloadAfterConflict" />
            </div>
        </div>
        <template #footer>
            <div class="flex justify-end gap-2">
                <Button :label="t('students.mutations.dialogCancel.btnKeep')" icon="pi pi-times" text severity="secondary" :disabled="cancelSaving" @click="cancelDialog = false" />
                <Button :label="t('students.mutations.dialogCancel.btnConfirm')" icon="pi pi-check" severity="danger" :loading="cancelSaving" @click="submitCancel" />
            </div>
        </template>
    </Dialog>

    <Dialog v-model:visible="correctDialog" modal :draggable="false" :style="{ width: 'min(94vw, 44rem)' }" :header="t('students.mutations.dialogCorrection.title')">
        <div class="flex flex-col gap-4">
            <Message severity="warn" :closable="false"> {{ t('students.mutations.dialogCorrection.notice') }} </Message>

            <div v-if="correctTarget" class="text-sm border border-surface-200 dark:border-surface-700 rounded p-3 flex flex-col gap-1">
                <div>
                    <strong>{{ correctTarget.student?.name }}</strong> · NIS {{ correctTarget.student?.nis ?? '-' }}
                </div>
                <div>{{ typeLabel(correctTarget.type) }} on {{ formatDate(correctTarget.effective_date) }} · {{ correctTarget.status === 'active' ? 'Active' : 'Cancelled' }}</div>
                <div>Class: {{ correctTarget.class?.name ?? '-' }} · School: {{ correctTarget.school_npsn }}</div>
                <div class="text-surface-500">Student version: {{ correctStudentVersion }}</div>
            </div>

            <div class="flex flex-col gap-2">
                <label for="correctAction" class="font-medium">{{ t('students.mutations.dialogCorrection.actionLabel') }} <span class="text-red-500">*</span></label>
                <Select id="correctAction" v-model="correctForm.action" :options="correctionActions" optionLabel="label" optionValue="value" class="w-full" @change="onCorrectActionChange" />
            </div>

            <div v-if="isMutationAction" class="flex flex-col gap-2">
                <label for="correctMutation" class="font-medium">{{ t('students.mutations.dialogCorrection.activeMutation') }} <span class="text-red-500">*</span></label>
                <Select
                    id="correctMutation"
                    v-model="correctForm.mutationId"
                    :options="correctMutationOptions"
                    optionLabel="label"
                    optionValue="id"
                    filter
                    class="w-full"
                    :placeholder="t('students.mutations.dialogCorrection.selectActiveMutation')"
                    :loading="correctMutationsLoading"
                    @change="onCorrectMutationChange"
                />
                <small v-if="correctMutationOptions.length === 0" class="text-orange-500">{{ t('students.mutations.dialogCorrection.noActiveMutation') }}</small>
                <small v-if="fieldError(correctFieldErrors, 'mutation_id')" class="text-red-500">{{ fieldError(correctFieldErrors, 'mutation_id') }}</small>
            </div>

            <div v-if="isMembershipAction" class="flex flex-col gap-2">
                <label for="correctMembership" class="font-medium">{{ t('students.mutations.dialogCorrection.classMembership') }} <span class="text-red-500">*</span></label>
                <Select
                    id="correctMembership"
                    v-model="correctForm.membershipId"
                    :options="correctMembershipOptions"
                    optionLabel="label"
                    optionValue="id"
                    filter
                    class="w-full"
                    :placeholder="t('students.mutations.dialogCorrection.selectClassMembership')"
                    :loading="correctMembershipsLoading"
                >
                    <template #option="{ option }">{{ option.label }}</template>
                </Select>
                <small v-if="correctMembershipOptions.length === 0" class="text-orange-500">{{ t('students.mutations.dialogCorrection.noClassMembership') }}</small>
                <small v-if="fieldError(correctFieldErrors, 'student_rombel_id')" class="text-red-500">{{ fieldError(correctFieldErrors, 'student_rombel_id') }}</small>
            </div>

            <div v-if="needsDate" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label for="correctDate" class="font-medium">{{ t('students.mutations.dialogCorrection.effectiveDate') }} <span class="text-red-500">*</span></label>
                    <DatePicker id="correctDate" v-model="correctForm.effectiveDate" dateFormat="yy-mm-dd" showIcon manualInput fluid />
                    <small v-if="fieldError(correctFieldErrors, 'effective_date')" class="text-red-500">{{ fieldError(correctFieldErrors, 'effective_date') }}</small>
                </div>
                <div class="flex flex-col gap-2">
                    <label for="correctSchool" class="font-medium">{{ t('students.mutations.dialogCorrection.schoolNpsn') }}</label>
                    <AutoComplete
                        id="correctSchool"
                        v-model="correctForm.schoolText"
                        :suggestions="correctSchoolSuggestions"
                        optionLabel="label"
                        :minLength="2"
                        class="w-full"
                        :placeholder="t('students.mutations.dialogIn.schoolSearchPlaceholder')"
                        @complete="completeCorrectSchool"
                        @item-select="
                            (event) => {
                                correctForm.schoolNpsn = event.value?.npsn ?? '';
                                correctForm.schoolText = event.value?.label ?? '';
                            }
                        "
                    />
                    <small v-if="fieldError(correctFieldErrors, 'school_npsn')" class="text-red-500">{{ fieldError(correctFieldErrors, 'school_npsn') }}</small>
                </div>
            </div>

            <div v-if="correctForm.action === 'membership_exit'" class="flex flex-col gap-2">
                <label for="correctStatus" class="font-medium">{{ t('students.mutations.dialogCorrection.targetStatus') }} <span class="text-red-500">*</span></label>
                <Select id="correctStatus" v-model="correctForm.targetStatusId" :options="targetStatusOptions" optionLabel="label" optionValue="value" class="w-full" />
                <small v-if="fieldError(correctFieldErrors, 'target_status_id')" class="text-red-500">{{ fieldError(correctFieldErrors, 'target_status_id') }}</small>
            </div>

            <div v-if="isMutationAction && selectedCorrectMutation" class="text-sm text-surface-500">
                Current mutation state: {{ typeLabel(selectedCorrectMutation.type) }} on {{ formatDate(selectedCorrectMutation.effective_date) }}, NPSN {{ selectedCorrectMutation.school_npsn }}.
            </div>

            <div v-if="selectedCorrectMembership && isMembershipAction" class="text-sm text-surface-500">
                {{ selectedCorrectMembership.class_name }} · {{ statusLabel(selectedCorrectMembership.student_status_id) }} · Entry {{ formatDate(selectedCorrectMembership.latest_student_entry_date) }} · Exit
                {{ formatDate(selectedCorrectMembership.latest_student_exit_date) }} · Version {{ selectedCorrectMembership.version }}
            </div>

            <div class="flex flex-col gap-2">
                <label for="correctReason" class="font-medium">{{ t('students.mutations.dialogCorrection.reason') }} <span class="text-red-500">*</span></label>
                <Textarea id="correctReason" v-model="correctForm.reason" rows="3" autoResize maxlength="255" class="w-full" />
                <small class="text-surface-500">{{ correctForm.reason.length }}/255 characters (minimum 10).</small>
                <small v-if="fieldError(correctFieldErrors, 'reason')" class="text-red-500">{{ fieldError(correctFieldErrors, 'reason') }}</small>
            </div>

            <div class="flex flex-col gap-2">
                <label for="correctApproval" class="font-medium">{{ t('students.mutations.dialogCorrection.approvalRef') }}</label>
                <InputText id="correctApproval" v-model="correctForm.approvalReference" maxlength="100" class="w-full" :placeholder="t('students.mutations.dialogCorrection.approvalRefPlaceholder')" />
                <small v-if="fieldError(correctFieldErrors, 'approval_reference')" class="text-red-500">{{ fieldError(correctFieldErrors, 'approval_reference') }}</small>
            </div>

            <Message v-if="correctError" severity="error" :closable="false">{{ correctError }}</Message>
            <div v-if="correctConflict" class="border border-red-300 rounded p-3 text-sm">
                <div class="font-medium text-red-600">Data conflict</div>
                <p class="mt-1">{{ correctConflict.message }}</p>
                <p v-if="versionSummary(correctConflict)" class="mt-1">{{ versionSummary(correctConflict) }}</p>
                <Button class="mt-2" size="small" label="Reload" icon="pi pi-refresh" @click="reloadAfterConflict" />
            </div>
        </div>
        <template #footer>
            <div class="flex justify-end gap-2">
                <Button :label="t('common.action.cancel')" icon="pi pi-times" text severity="secondary" :disabled="correctSaving" @click="correctDialog = false" />
                <Button :label="t('students.mutations.dialogCorrection.btnSubmit')" icon="pi pi-check" severity="warn" :loading="correctSaving" @click="submitCorrect" />
            </div>
        </template>
    </Dialog>
    <!-- ── Laporan Mutasi (Format PDF A4 / Excel) ── -->
    <StudentMutationReportDialog
        v-model:visible="reportDialogVisible"
        :schoolYearId="yearId"
        :schoolYearName="year?.name"
    />

    <!-- ── Impor Mutasi Siswa ── -->
    <StudentMutationImportDialog
        v-model:visible="importDialogVisible"
        :schoolYearId="yearId"
        @imported="onImported"
    />
</template>
