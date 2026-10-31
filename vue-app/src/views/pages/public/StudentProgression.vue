<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import { authStore } from '@/stores/authStore';
import apiLogin from '@/apiLogin';

const store = authStore();
const toast = useToast();
const canManage = computed(() => store.can('students.manage'));

const steps = [
    { id: 1, label: 'Academic Years' },
    { id: 2, label: 'Students & Decisions' },
    { id: 3, label: 'Preview' },
    { id: 4, label: 'Result' }
];

const decisionOptions = [
    { label: 'Promote', value: 'promote' },
    { label: 'Retain', value: 'retain' },
    { label: 'Graduate', value: 'graduate' },
    { label: 'Skip', value: 'skip' }
];

const step = ref(1);

const years = ref([]);
const yearsLoading = ref(false);
const yearsError = ref('');
const sourceYearId = ref(null);
const targetYearId = ref(null);

const options = ref(null);
const optionsLoading = ref(false);
const optionsError = ref('');
const selectedSourceClasses = ref([]);

const roster = ref([]);
const rosterLoading = ref(false);
const rosterError = ref('');
const rosterSearch = ref('');
const rosterTotal = ref(0);
const rosterTruncated = ref(false);
const bulkTargetClassId = ref(null);
const decisions = reactive(new Map());
let searchTimer = null;

const graduationDate = ref('');

const preview = ref(null);
const previewLoading = ref(false);
const previewError = ref('');
const previewFieldErrors = ref({});

const commitDialog = ref(false);
const commitLoading = ref(false);
const commitError = ref('');
const commitResult = ref(null);

const conflictDialog = ref(false);
const conflictMessage = ref('');
const conflictItems = ref([]);

const batches = ref([]);
const batchesLoading = ref(false);
const batchesError = ref('');

const batchDialog = ref(false);
const batchDetail = ref(null);
const batchDetailLoading = ref(false);
const batchDetailError = ref('');
const historySection = ref(null);

const sourceYear = computed(() => years.value.find((year) => year.id === sourceYearId.value) ?? null);
const targetYear = computed(() => years.value.find((year) => year.id === targetYearId.value) ?? null);
const sourceYearName = computed(() => options.value?.source_school_year?.name ?? sourceYear.value?.name ?? '-');
const targetYearName = computed(() => options.value?.target_school_year?.name ?? targetYear.value?.name ?? '-');

const sourceClasses = computed(() => options.value?.source_classes ?? []);
const targetClasses = computed(() => options.value?.target_classes ?? []);
const maxBatchSize = computed(() => options.value?.max_batch_size ?? 500);

const selectedRows = computed(() => [...decisions.values()].filter((row) => row.decision !== 'skip'));
const decisionTotals = computed(() => {
    const totals = { promote: 0, retain: 0, graduate: 0 };
    decisions.forEach((row) => {
        if (!row.eligible) return;
        totals[row.decision] += 1;
    });
    return totals;
});

const hiddenDecisionCount = computed(() => selectedRows.value.length - roster.value.filter((row) => row.decision !== 'skip').length);

const skippedVisible = computed(() => roster.value.filter((row) => row.eligible && row.decision === 'skip').length);

const promoteWithoutTarget = computed(() => selectedRows.value.filter((row) => row.eligible && row.decision === 'promote' && !row.target_rombel_id).length);

const graduateMissingDate = computed(() => decisionTotals.value.graduate > 0 && !graduationDate.value);
const batchTooLarge = computed(() => selectedRows.value.length > maxBatchSize.value);
const hasLockWarning = computed(() => {
    const targetLocked = options.value?.target_school_year?.is_locked && decisionTotals.value.promote > 0;
    const sourceLocked = options.value?.source_school_year?.is_locked && decisionTotals.value.graduate > 0;
    return targetLocked || sourceLocked;
});

const previewFieldErrorList = computed(() => Object.values(previewFieldErrors.value).flat().filter(Boolean));

const canReview = computed(() => canManage.value && !rosterError.value && selectedRows.value.length > 0 && promoteWithoutTarget.value === 0 && !graduateMissingDate.value && !batchTooLarge.value);

const notify = (severity, detail, summary = 'Student Progression') => {
    toast.add({ severity, summary, detail, life: 5000 });
};

const errorMessage = (error, fallback) => error.response?.data?.message ?? fallback;

const reasonLabel = (reason) =>
    ({
        multiple_active_memberships: 'Multiple active class memberships',
        no_active_membership: 'No active class membership',
        target_membership_exists: 'Already has a membership in the target year'
    })[reason] ??
    reason ??
    'Not eligible';

const formatDate = (value) => {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatDateTime = (value) => {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const batchSummaryTotal = (batch) => {
    const summary = batch?.summary ?? {};
    if (typeof summary.total === 'number') return summary.total;
    return Object.values(summary.by_decision ?? {}).reduce((sum, value) => sum + Number(value ?? 0), 0);
};

const loadYears = async () => {
    yearsLoading.value = true;
    yearsError.value = '';
    try {
        const response = await apiLogin.get('/api/school-years');
        years.value = response.data?.data ?? [];
        const active = years.value.find((year) => year.is_active);
        if (active) {
            sourceYearId.value = active.id;
            const next = years.value.find((year) => year.id !== active.id && year.start_date && active.end_date && year.start_date >= active.end_date);
            targetYearId.value = next?.id ?? years.value.find((year) => year.id !== active.id)?.id ?? null;
        }
    } catch (error) {
        yearsError.value = errorMessage(error, 'Failed to load academic years. Please try again.');
    } finally {
        yearsLoading.value = false;
    }
};

const resetOptions = () => {
    options.value = null;
    roster.value = [];
    decisions.clear();
    selectedSourceClasses.value = [];
    rosterTotal.value = 0;
    rosterTruncated.value = false;
    preview.value = null;
    previewError.value = '';
    previewFieldErrors.value = {};
    commitResult.value = null;
    commitError.value = '';
    graduationDate.value = '';
    step.value = 1;
};

watch([sourceYearId, targetYearId], () => {
    if (options.value !== null) {
        resetOptions();
    }
});

const loadOptions = async () => {
    if (!sourceYearId.value || !targetYearId.value) {
        optionsError.value = 'Select both a source and a target academic year.';
        return;
    }
    if (sourceYearId.value === targetYearId.value) {
        optionsError.value = 'The source and target academic years must be different.';
        return;
    }

    optionsLoading.value = true;
    optionsError.value = '';
    try {
        const response = await apiLogin.get('/api/academic/progression/options', {
            params: {
                source_school_year_id: sourceYearId.value,
                target_school_year_id: targetYearId.value
            }
        });
        options.value = response.data?.data ?? null;
        selectedSourceClasses.value = sourceClasses.value.map((rombel) => rombel.id);
        graduationDate.value = options.value?.source_school_year?.end_date ?? options.value?.target_school_year?.start_date ?? '';
        step.value = 2;
        await fetchRoster();
    } catch (error) {
        options.value = null;
        optionsError.value = errorMessage(error, 'Failed to load progression options. Please try again.');
    } finally {
        optionsLoading.value = false;
    }
};

const fetchRoster = async (pruneDecisions = false) => {
    if (!options.value) return;

    rosterLoading.value = true;
    rosterError.value = '';

    try {
        const params = {
            source_school_year_id: sourceYearId.value,
            target_school_year_id: targetYearId.value
        };
        if (selectedSourceClasses.value.length > 0) params.rombel_ids = selectedSourceClasses.value;
        if (rosterSearch.value.trim() !== '') params.search = rosterSearch.value.trim();

        const response = await apiLogin.get('/api/academic/progression/roster', { params });
        const data = response.data?.data ?? {};
        const fetchedIds = new Set();
        roster.value = (data.items ?? []).map((item) => {
            fetchedIds.add(item.student_id);
            if (!item.eligible) {
                decisions.delete(item.student_id);
                return { ...item, decision: 'skip', target_rombel_id: null };
            }
            const stored = decisions.get(item.student_id);
            const decision = stored?.decision ?? 'skip';
            const row = {
                ...item,
                decision,
                target_rombel_id: decision === 'promote' ? (stored?.target_rombel_id ?? null) : null
            };
            if (decision !== 'skip') decisions.set(item.student_id, { ...row });
            return row;
        });
        if (pruneDecisions) {
            [...decisions.keys()].forEach((studentId) => {
                if (!fetchedIds.has(studentId)) decisions.delete(studentId);
            });
        }
        rosterTotal.value = data.total ?? roster.value.length;
        rosterTruncated.value = data.truncated === true;
    } catch (error) {
        roster.value = [];
        rosterTotal.value = 0;
        rosterTruncated.value = false;
        rosterError.value = errorMessage(error, 'Failed to load students. Please try again.');
    } finally {
        rosterLoading.value = false;
    }
};

const onSearchInput = () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(fetchRoster, 300);
};

const onSourceClassesChange = () => {
    if (searchTimer) clearTimeout(searchTimer);
    rosterSearch.value = '';
    fetchRoster(true);
};

const syncDecision = (row) => {
    if (!row.eligible || row.decision === 'skip') {
        decisions.delete(row.student_id);
        return;
    }
    decisions.set(row.student_id, { ...row });
};

const applyBulk = (decision) => {
    if (!canManage.value) return;

    roster.value.forEach((row) => {
        if (!row.eligible) {
            row.decision = 'skip';
            row.target_rombel_id = null;
            syncDecision(row);
            return;
        }
        row.decision = decision;
        if (decision === 'promote') {
            row.target_rombel_id = bulkTargetClassId.value ?? null;
        } else {
            row.target_rombel_id = null;
        }
        syncDecision(row);
    });
};

const onDecisionChange = (row) => {
    if (row.decision !== 'promote') {
        row.target_rombel_id = null;
    }
    syncDecision(row);
};

const targetClass = (rombelId) => targetClasses.value.find((rombel) => rombel.id === rombelId) ?? null;

const targetWarning = (row) => {
    const target = targetClass(row.target_rombel_id);
    if (!target || !row.source_rombel) return '';
    const differences = [];
    if (target.school_level_id !== row.source_rombel.school_level_id) differences.push('level');
    if ((target.student_major_id ?? 0) !== (row.source_rombel.student_major_id ?? 0)) differences.push('major');
    return differences.length > 0 ? `Different ${differences.join(' and ')}` : '';
};

const goToPreview = () => {
    preview.value = null;
    previewError.value = '';
    previewFieldErrors.value = {};
    step.value = 3;
};

const backToStudents = () => {
    preview.value = null;
    previewError.value = '';
    previewFieldErrors.value = {};
    step.value = 2;
};

const buildPayload = () => ({
    source_school_year_id: sourceYearId.value,
    target_school_year_id: targetYearId.value,
    graduation_date: graduationDate.value || null,
    decisions: selectedRows.value.map((row) => ({
        student_id: row.student_id,
        membership_id: row.membership_id,
        expected_student_version: row.student_version,
        expected_membership_version: row.membership_version,
        decision: row.decision,
        target_rombel_id: row.decision === 'promote' ? row.target_rombel_id : null
    }))
});

const createPreview = async () => {
    if (!canReview.value) return;

    previewLoading.value = true;
    previewError.value = '';
    previewFieldErrors.value = {};

    try {
        const response = await apiLogin.post('/api/academic/progression/preview', buildPayload());
        preview.value = response.data?.data ?? null;
        if (preview.value?.valid === false) {
            previewError.value = 'The preview contains conflicts. Resolve them and create the preview again.';
            return;
        }
        notify('success', `Preview created for ${preview.value?.summary?.total ?? 0} decision(s). It expires in 30 minutes.`);
    } catch (error) {
        const status = error.response?.status;
        const body = error.response?.data ?? {};
        if (status === 422 && body?.data?.valid === false) {
            preview.value = body.data;
            previewError.value = body.message ?? 'The preview contains conflicts.';
        } else {
            preview.value = null;
            previewFieldErrors.value = body.errors ?? {};
            previewError.value = errorMessage(error, 'Failed to create the preview. Please review the decisions.');
        }
    } finally {
        previewLoading.value = false;
    }
};

const openCommitDialog = () => {
    if (!preview.value?.token) return;
    commitError.value = '';
    commitDialog.value = true;
};

const executeCommit = async () => {
    if (!preview.value?.token) return;

    commitLoading.value = true;
    commitError.value = '';

    try {
        const response = await apiLogin.post('/api/academic/progression/commit', { token: preview.value.token });
        commitResult.value = response.data?.data ?? null;
        decisions.clear();
        commitDialog.value = false;
        step.value = 4;
        notify('success', response.data?.message ?? 'Progression applied successfully.');
        fetchBatches();
    } catch (error) {
        const status = error.response?.status;
        const body = error.response?.data ?? {};
        commitDialog.value = false;
        if (status === 409) {
            conflictItems.value = body?.data?.items ?? [];
            conflictMessage.value = body.message ?? 'The progression data changed after the preview.';
            conflictDialog.value = true;
        } else if (status === 422) {
            previewError.value = body.message ?? 'The preview is no longer valid. Create a new preview.';
            preview.value = null;
            step.value = 3;
        } else {
            commitError.value = errorMessage(error, 'Failed to commit the progression batch.');
            notify('error', commitError.value);
        }
    } finally {
        commitLoading.value = false;
    }
};

const reloadAfterConflict = async () => {
    conflictDialog.value = false;
    conflictItems.value = [];
    preview.value = null;
    previewError.value = '';
    step.value = 2;
    await fetchRoster();
};

const startNewBatch = () => {
    resetOptions();
};

const fetchBatches = async () => {
    batchesLoading.value = true;
    batchesError.value = '';
    try {
        const response = await apiLogin.get('/api/academic/progression/batches');
        batches.value = response.data?.data ?? [];
    } catch (error) {
        batches.value = [];
        batchesError.value = errorMessage(error, 'Failed to load progression history. Please try again.');
    } finally {
        batchesLoading.value = false;
    }
};

const openBatch = async (batch) => {
    batchDialog.value = true;
    batchDetail.value = null;
    batchDetailError.value = '';
    batchDetailLoading.value = true;

    try {
        const response = await apiLogin.get(`/api/academic/progression/batches/${batch.id}`);
        batchDetail.value = response.data?.data ?? null;
    } catch (error) {
        batchDetailError.value = errorMessage(error, 'Failed to load the batch detail.');
    } finally {
        batchDetailLoading.value = false;
    }
};

const scrollToHistory = () => {
    historySection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

onMounted(() => {
    loadYears();
    fetchBatches();
});
</script>

<template>
    <Fluid>
        <div class="bg-surface-0 dark:bg-surface-950 px-4 py-6 md:px-12 md:py-8 lg:px-20">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <div class="font-bold text-3xl text-surface-900 dark:text-surface-0 mb-2">Student Progression</div>
                    <p class="text-surface-500 dark:text-surface-400">Promote, retain, or graduate students at the end of an academic year. Previews never change data; committing is atomic, audited, and protected by a short-lived token.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Tag v-if="!canManage" severity="secondary" value="View-only access" />
                </div>
            </div>

            <div class="card mt-6">
                <div class="flex flex-wrap items-center gap-2">
                    <Tag v-for="item in steps" :key="item.id" :value="`${item.id}. ${item.label}`" :severity="step === item.id ? 'success' : step > item.id ? 'info' : 'secondary'" />
                </div>

                <Message v-if="yearsError" severity="error" :closable="false" class="mt-4">{{ yearsError }}</Message>

                <div v-if="step === 1" class="mt-5 flex flex-col gap-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label for="sourceYear" class="font-medium">Source Academic Year <span class="text-red-500">*</span></label>
                            <Select id="sourceYear" v-model="sourceYearId" :options="years" optionLabel="name" optionValue="id" placeholder="Select source year" :loading="yearsLoading" fluid />
                            <small v-if="sourceYear" class="text-surface-500">
                                {{ formatDate(sourceYear.start_date) }} – {{ formatDate(sourceYear.end_date) }}
                                <span v-if="sourceYear.is_locked" class="text-red-500"> · Locked</span>
                            </small>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="targetYear" class="font-medium">Target Academic Year <span class="text-red-500">*</span></label>
                            <Select id="targetYear" v-model="targetYearId" :options="years" optionLabel="name" optionValue="id" placeholder="Select target year" :loading="yearsLoading" fluid />
                            <small v-if="targetYear" class="text-surface-500">
                                {{ formatDate(targetYear.start_date) }} – {{ formatDate(targetYear.end_date) }}
                                <span v-if="targetYear.is_locked" class="text-red-500"> · Locked</span>
                            </small>
                        </div>
                    </div>

                    <Message v-if="sourceYearId && targetYearId && sourceYearId === targetYearId" severity="warn" :closable="false"> The source and target academic years must be different. </Message>
                    <Message v-if="optionsError" severity="error" :closable="false">{{ optionsError }}</Message>

                    <div class="flex flex-wrap justify-end gap-2">
                        <Button label="Continue to Students" icon="pi pi-arrow-right" iconPos="right" :loading="optionsLoading" :disabled="optionsLoading || !sourceYearId || !targetYearId || sourceYearId === targetYearId" @click="loadOptions" />
                    </div>
                </div>

                <div v-if="step === 2" class="mt-5 flex flex-col gap-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <Tag :value="`Source: ${sourceYearName}`" severity="info" />
                        <Tag :value="`Target: ${targetYearName}`" severity="info" />
                        <Tag v-if="options?.source_school_year?.is_locked" value="Source locked" severity="danger" />
                        <Tag v-if="options?.target_school_year?.is_locked" value="Target locked" severity="danger" />
                    </div>

                    <Message v-if="!canManage" severity="secondary" :closable="false"> View-only access: you can review the roster, but preview and commit require the students.manage ability. </Message>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                        <div class="flex flex-col gap-2 lg:col-span-1">
                            <label class="font-medium">Source Classes</label>
                            <MultiSelect
                                v-model="selectedSourceClasses"
                                :options="sourceClasses"
                                optionLabel="name"
                                optionValue="id"
                                display="chip"
                                placeholder="All source classes"
                                filter
                                :maxSelectedLabels="2"
                                class="w-full"
                                @change="onSourceClassesChange"
                            />
                        </div>
                        <div class="flex flex-col gap-2 lg:col-span-1">
                            <label class="font-medium">Search Students</label>
                            <IconField>
                                <InputIcon><i class="pi pi-search" /></InputIcon>
                                <InputText v-model="rosterSearch" placeholder="Name, NIS, or NISN" class="w-full" @input="onSearchInput" />
                            </IconField>
                        </div>
                        <div class="flex flex-col gap-2 lg:col-span-1">
                            <label class="font-medium">Default Target Class (bulk promote)</label>
                            <Select v-model="bulkTargetClassId" :options="targetClasses" optionLabel="name" optionValue="id" placeholder="Select target class" showClear filter class="w-full" />
                        </div>
                    </div>

                    <div v-if="canManage" class="flex flex-wrap items-center gap-2">
                        <span class="text-sm text-surface-500">Apply to all eligible:</span>
                        <Button label="Promote" size="small" icon="pi pi-arrow-up" :disabled="!bulkTargetClassId" @click="applyBulk('promote')" />
                        <Button label="Retain" size="small" severity="warn" variant="outlined" @click="applyBulk('retain')" />
                        <Button label="Graduate" size="small" severity="success" variant="outlined" @click="applyBulk('graduate')" />
                        <Button label="Reset" size="small" severity="secondary" text @click="applyBulk('skip')" />
                    </div>

                    <Message v-if="rosterTruncated" severity="warn" :closable="false">
                        Only the first {{ maxBatchSize }} students are shown. Narrow the class filter or search to review the rest; a single batch accepts at most {{ maxBatchSize }} decisions.
                    </Message>
                    <Message v-if="hasLockWarning" severity="warn" :closable="false"> The selected decisions need an unlocked year: promote requires an unlocked target year and graduate requires an unlocked source year. </Message>
                    <Message v-if="rosterError" severity="error" :closable="false">{{ rosterError }}</Message>

                    <DataTable
                        :value="roster"
                        dataKey="student_id"
                        showGridlines
                        scrollable
                        scrollHeight="28rem"
                        size="small"
                        :loading="rosterLoading"
                        paginator
                        :rows="25"
                        :rowsPerPageOptions="[25, 50, 100]"
                        currentPageReportTemplate="Showing {first} to {last} of {totalRecords}"
                        paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                    >
                        <template #empty>
                            <div class="flex flex-col items-center gap-2 py-6">
                                <span v-if="rosterError">{{ rosterError }}</span>
                                <span v-else>No students found for the selected source classes.</span>
                                <Button v-if="rosterError" icon="pi pi-refresh" label="Retry" @click="fetchRoster(false)" />
                            </div>
                        </template>
                        <template #loading>Loading students. Please wait.</template>
                        <Column header="Student" frozen style="min-width: 15rem">
                            <template #body="{ data }">
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ data.name }}</span>
                                    <small class="text-surface-500">NIS {{ data.nis ?? '-' }} · NISN {{ data.nisn ?? '-' }}</small>
                                </div>
                            </template>
                        </Column>
                        <Column header="Source Class" style="min-width: 12rem">
                            <template #body="{ data }">
                                <div class="flex flex-col">
                                    <span>{{ data.source_rombel?.name ?? '-' }}</span>
                                    <small class="text-surface-500">
                                        {{ data.source_rombel?.school_level_name ?? '-' }}
                                        <span v-if="data.source_rombel?.student_major_name"> · {{ data.source_rombel.student_major_name }}</span>
                                    </small>
                                </div>
                            </template>
                        </Column>
                        <Column header="Eligibility" style="min-width: 12rem">
                            <template #body="{ data }">
                                <Tag v-if="data.eligible" value="Ready" severity="success" />
                                <Tag v-else :value="reasonLabel(data.ineligible_reason)" severity="danger" />
                            </template>
                        </Column>
                        <Column header="Decision" style="min-width: 11rem">
                            <template #body="{ data }">
                                <Select v-model="data.decision" :options="decisionOptions" optionLabel="label" optionValue="value" :disabled="!canManage || !data.eligible" class="w-full" @change="onDecisionChange(data)" />
                            </template>
                        </Column>
                        <Column header="Target Class (promote)" style="min-width: 15rem">
                            <template #body="{ data }">
                                <Select
                                    v-model="data.target_rombel_id"
                                    :options="targetClasses"
                                    optionLabel="name"
                                    optionValue="id"
                                    placeholder="Select target class"
                                    showClear
                                    filter
                                    :disabled="!canManage || !data.eligible || data.decision !== 'promote'"
                                    :invalid="data.decision === 'promote' && !data.target_rombel_id"
                                    class="w-full"
                                    @change="onDecisionChange(data)"
                                />
                                <small v-if="targetWarning(data)" class="text-orange-600 dark:text-orange-400">
                                    {{ targetWarning(data) }}
                                </small>
                            </template>
                        </Column>
                    </DataTable>

                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                        <div class="text-sm text-surface-500">
                            Ready students: {{ rosterTotal }} · Selected decisions: {{ selectedRows.length }} (Promote {{ decisionTotals.promote }}, Retain {{ decisionTotals.retain }}, Graduate {{ decisionTotals.graduate }})
                            <span v-if="hiddenDecisionCount > 0"> · {{ hiddenDecisionCount }} selected decision(s) are outside the current filter and stay in this batch. </span>
                        </div>
                        <div class="flex flex-wrap justify-end gap-2">
                            <Button label="Back" icon="pi pi-arrow-left" severity="secondary" text @click="step = 1" />
                            <Button v-if="canManage" label="Review Preview" icon="pi pi-eye" iconPos="right" :disabled="!canReview" @click="goToPreview" />
                        </div>
                    </div>
                    <small v-if="canManage && selectedRows.length === 0" class="text-surface-500"> Choose at least one decision other than Skip to continue. </small>
                    <small v-if="canManage && promoteWithoutTarget > 0" class="text-red-500"> {{ promoteWithoutTarget }} promoted student(s) still need a target class. </small>
                </div>

                <div v-if="step === 3" class="mt-5 flex flex-col gap-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <Tag :value="`Source: ${sourceYearName}`" severity="info" />
                        <Tag :value="`Target: ${targetYearName}`" severity="info" />
                        <Tag :value="`${selectedRows.length} decision(s)`" severity="secondary" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="flex flex-col gap-2">
                            <label for="graduationDate" class="font-medium">Graduation Date</label>
                            <InputText id="graduationDate" v-model="graduationDate" type="date" fluid :invalid="graduateMissingDate" />
                            <small v-if="graduateMissingDate" class="text-red-500"> A graduation date is required because at least one student graduates. </small>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div class="border border-surface-200 dark:border-surface-700 rounded p-3">
                            <div class="text-sm text-surface-500">Promote</div>
                            <div class="text-2xl font-semibold">{{ decisionTotals.promote }}</div>
                        </div>
                        <div class="border border-surface-200 dark:border-surface-700 rounded p-3">
                            <div class="text-sm text-surface-500">Retain</div>
                            <div class="text-2xl font-semibold">{{ decisionTotals.retain }}</div>
                        </div>
                        <div class="border border-surface-200 dark:border-surface-700 rounded p-3">
                            <div class="text-sm text-surface-500">Graduate</div>
                            <div class="text-2xl font-semibold">{{ decisionTotals.graduate }}</div>
                        </div>
                        <div class="border border-surface-200 dark:border-surface-700 rounded p-3">
                            <div class="text-sm text-surface-500">Skipped (not sent, current filter)</div>
                            <div class="text-2xl font-semibold">{{ skippedVisible }}</div>
                        </div>
                    </div>

                    <div class="border border-surface-200 dark:border-surface-700 rounded p-4 text-sm text-surface-600 dark:text-surface-300">
                        <p class="font-medium mb-1">What happens when you commit</p>
                        <ul class="list-disc pl-5 flex flex-col gap-1">
                            <li>Promote: a new active class membership is created in the selected target class.</li>
                            <li>Retain: nothing changes; the student remains unplaced in the target year.</li>
                            <li>Graduate: the source membership becomes Graduated with the selected graduation date.</li>
                            <li>Skip: the student is not included in this batch.</li>
                        </ul>
                    </div>

                    <Message v-if="hasLockWarning" severity="warn" :closable="false"> A locked academic year blocks the selected decisions. Unlock the year before creating the preview. </Message>
                    <Message v-if="previewError" severity="error" :closable="false">{{ previewError }}</Message>
                    <Message v-if="previewFieldErrorList.length > 0" severity="error" :closable="false">
                        <ul class="list-disc pl-5">
                            <li v-for="message in previewFieldErrorList" :key="message">{{ message }}</li>
                        </ul>
                    </Message>

                    <DataTable v-if="preview?.items?.length" :value="preview.items" dataKey="student_id" showGridlines scrollable scrollHeight="24rem" size="small">
                        <Column header="Student" style="min-width: 13rem">
                            <template #body="{ data }">
                                <span class="font-medium">{{ data.student_name ?? `Student #${data.student_id}` }}</span>
                            </template>
                        </Column>
                        <Column header="Decision" style="min-width: 8rem">
                            <template #body="{ data }"><Tag :value="data.decision" severity="info" /></template>
                        </Column>
                        <Column header="Target Class" style="min-width: 11rem">
                            <template #body="{ data }">{{ data.target_rombel?.name ?? '-' }}</template>
                        </Column>
                        <Column header="Status" style="min-width: 9rem">
                            <template #body="{ data }">
                                <Tag v-if="data.ready" value="Ready" severity="success" />
                                <Tag v-else value="Conflict" severity="danger" />
                            </template>
                        </Column>
                        <Column header="Details" style="min-width: 18rem">
                            <template #body="{ data }">
                                <ul v-if="data.conflicts?.length" class="list-disc pl-4 text-red-500">
                                    <li v-for="conflict in data.conflicts" :key="conflict.code">{{ conflict.message }}</li>
                                </ul>
                                <ul v-if="data.warnings?.length" class="list-disc pl-4 text-orange-600 dark:text-orange-400">
                                    <li v-for="warning in data.warnings" :key="warning.code">{{ warning.message }}</li>
                                </ul>
                                <span v-if="!data.conflicts?.length && !data.warnings?.length" class="text-surface-500">-</span>
                            </template>
                        </Column>
                    </DataTable>

                    <div v-if="preview?.valid" class="flex flex-wrap items-center gap-3 text-sm">
                        <Tag value="Preview ready" severity="success" />
                        <span class="text-surface-500">Expires: {{ formatDateTime(preview.expires_at) }}</span>
                        <span class="text-surface-500">Batch #{{ preview.batch_id }}</span>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <Button label="Back to Students" icon="pi pi-arrow-left" severity="secondary" text :disabled="previewLoading || commitLoading" @click="backToStudents" />
                        <Button v-if="!preview?.valid" label="Create Preview" icon="pi pi-search" :loading="previewLoading" :disabled="previewLoading || !canReview" @click="createPreview" />
                        <Button v-if="preview?.valid" label="Create New Preview" icon="pi pi-refresh" severity="secondary" variant="outlined" :disabled="previewLoading || commitLoading" @click="createPreview" />
                        <Button v-if="preview?.valid" label="Commit Decisions" icon="pi pi-check" severity="success" :disabled="commitLoading" @click="openCommitDialog" />
                    </div>
                </div>

                <div v-if="step === 4" class="mt-5 flex flex-col gap-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <Tag value="Committed" severity="success" />
                        <Tag :value="`Batch #${commitResult?.batch_id ?? '-'}`" severity="info" />
                        <Tag v-if="commitResult?.replayed" value="Replay" severity="secondary" />
                        <span class="text-sm text-surface-500">{{ formatDateTime(commitResult?.committed_at) }}</span>
                    </div>

                    <Message v-if="commitError" severity="error" :closable="false">{{ commitError }}</Message>

                    <DataTable :value="commitResult?.items ?? []" dataKey="student_id" showGridlines scrollable scrollHeight="24rem" size="small">
                        <Column header="Student" style="min-width: 13rem">
                            <template #body="{ data }">{{ data.student_name ?? `Student #${data.student_id}` }}</template>
                        </Column>
                        <Column header="Decision" style="min-width: 8rem">
                            <template #body="{ data }"><Tag :value="data.decision" severity="info" /></template>
                        </Column>
                        <Column header="From Class" style="min-width: 10rem">
                            <template #body="{ data }">{{ data.source_rombel_name ?? '-' }}</template>
                        </Column>
                        <Column header="To Class" style="min-width: 10rem">
                            <template #body="{ data }">{{ data.target_rombel_name ?? '-' }}</template>
                        </Column>
                        <Column header="New Membership" style="min-width: 9rem">
                            <template #body="{ data }">{{ data.new_membership_id ?? '-' }}</template>
                        </Column>
                    </DataTable>

                    <div class="flex flex-wrap justify-end gap-2">
                        <Button label="Start a New Batch" icon="pi pi-plus" @click="startNewBatch" />
                        <Button label="View Batch History" icon="pi pi-list" severity="secondary" variant="outlined" @click="scrollToHistory" />
                    </div>
                </div>
            </div>

            <div ref="historySection" class="card mt-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <div class="font-semibold text-xl">Batch History</div>
                        <p class="text-surface-500 text-sm">Committed batches are immutable history; previews are visible only to their creator.</p>
                    </div>
                    <Button icon="pi pi-refresh" label="Refresh" severity="secondary" variant="outlined" :loading="batchesLoading" @click="fetchBatches" />
                </div>

                <Message v-if="batchesError" severity="error" :closable="false" class="mt-3">{{ batchesError }}</Message>

                <DataTable :value="batches" dataKey="id" showGridlines size="small" :loading="batchesLoading" scrollable class="mt-3">
                    <template #empty>No progression batches yet.</template>
                    <Column header="#" style="width: 4rem">
                        <template #body="{ data }">#{{ data.id }}</template>
                    </Column>
                    <Column header="Academic Years" style="min-width: 15rem">
                        <template #body="{ data }"> {{ data.source_school_year?.name ?? '-' }} → {{ data.target_school_year?.name ?? '-' }} </template>
                    </Column>
                    <Column header="Status" style="min-width: 8rem">
                        <template #body="{ data }">
                            <Tag :value="data.status" :severity="data.status === 'committed' ? 'success' : 'warn'" />
                        </template>
                    </Column>
                    <Column header="Decisions" style="min-width: 8rem">
                        <template #body="{ data }">{{ batchSummaryTotal(data) }}</template>
                    </Column>
                    <Column header="Created By" style="min-width: 11rem">
                        <template #body="{ data }">{{ data.created_by?.name ?? '-' }}</template>
                    </Column>
                    <Column header="Created At" style="min-width: 12rem">
                        <template #body="{ data }">{{ formatDateTime(data.created_at) }}</template>
                    </Column>
                    <Column header="Committed At" style="min-width: 12rem">
                        <template #body="{ data }">{{ formatDateTime(data.committed_at) }}</template>
                    </Column>
                    <Column header="Action" style="min-width: 8rem">
                        <template #body="{ data }">
                            <Button icon="pi pi-eye" label="View" size="small" text @click="openBatch(data)" />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </Fluid>

    <Dialog v-model:visible="commitDialog" modal :style="{ width: '560px', maxWidth: '95vw' }" header="Commit Student Progression" :draggable="false">
        <div class="flex flex-col gap-3">
            <p>
                Apply <strong>{{ preview?.summary?.total ?? selectedRows.length }}</strong> decision(s) from <strong>{{ sourceYearName }}</strong> to <strong>{{ targetYearName }}</strong
                >?
            </p>
            <ul class="list-disc pl-5 text-sm">
                <li>Promote: {{ preview?.summary?.by_decision?.promote ?? decisionTotals.promote }}</li>
                <li>Retain: {{ preview?.summary?.by_decision?.retain ?? decisionTotals.retain }}</li>
                <li>Graduate: {{ preview?.summary?.by_decision?.graduate ?? decisionTotals.graduate }} ({{ formatDate(graduationDate) }})</li>
            </ul>
            <p class="text-sm text-surface-500">The commit is atomic and audited. If any roster data changed after the preview, nothing is applied and you will be asked to preview again.</p>
            <p v-if="commitError" class="text-red-500 text-sm">{{ commitError }}</p>
        </div>
        <template #footer>
            <Button label="Cancel" icon="pi pi-times" text severity="secondary" :disabled="commitLoading" @click="commitDialog = false" />
            <Button label="Commit Now" icon="pi pi-check" severity="success" :loading="commitLoading" :disabled="commitLoading" @click="executeCommit" />
        </template>
    </Dialog>

    <Dialog v-model:visible="conflictDialog" modal :style="{ width: '640px', maxWidth: '95vw' }" header="Data Conflict Detected" :draggable="false">
        <div class="flex flex-col gap-3">
            <div class="flex items-start gap-3">
                <i class="pi pi-exclamation-triangle !text-3xl text-orange-500" />
                <p>{{ conflictMessage }}</p>
            </div>
            <DataTable :value="conflictItems" dataKey="student_id" showGridlines size="small" scrollable scrollHeight="18rem">
                <Column header="Student" style="min-width: 12rem">
                    <template #body="{ data }">{{ data.student_name ?? `Student #${data.student_id}` }}</template>
                </Column>
                <Column header="Decision" style="min-width: 7rem">
                    <template #body="{ data }">{{ data.decision }}</template>
                </Column>
                <Column header="Conflicts" style="min-width: 18rem">
                    <template #body="{ data }">
                        <ul class="list-disc pl-4 text-red-500">
                            <li v-for="conflict in data.conflicts" :key="conflict.code">{{ conflict.message }}</li>
                        </ul>
                    </template>
                </Column>
            </DataTable>
            <p class="text-sm text-surface-500">No data was changed. Reload the roster, review the decisions, and create a new preview.</p>
        </div>
        <template #footer>
            <Button label="Close" icon="pi pi-times" text severity="secondary" @click="conflictDialog = false" />
            <Button label="Reload & Preview Again" icon="pi pi-refresh" @click="reloadAfterConflict" />
        </template>
    </Dialog>

    <Dialog v-model:visible="batchDialog" modal :style="{ width: '760px', maxWidth: '95vw' }" :header="`Progression Batch #${batchDetail?.id ?? ''}`" :draggable="false">
        <div v-if="batchDetailLoading" class="py-4 text-center text-surface-500">Loading batch detail...</div>
        <Message v-else-if="batchDetailError" severity="error" :closable="false">{{ batchDetailError }}</Message>
        <div v-else-if="batchDetail" class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <Tag :value="batchDetail.status" :severity="batchDetail.status === 'committed' ? 'success' : 'warn'" />
                <Tag :value="`${batchDetail.source_school_year?.name ?? '-'} → ${batchDetail.target_school_year?.name ?? '-'}`" severity="info" />
                <span class="text-sm text-surface-500">Created by {{ batchDetail.created_by?.name ?? '-' }}</span>
                <span v-if="batchDetail.committed_by" class="text-sm text-surface-500"> · Committed by {{ batchDetail.committed_by.name }} </span>
            </div>
            <DataTable :value="batchDetail.items ?? []" dataKey="student_id" showGridlines size="small" scrollable scrollHeight="20rem">
                <template #empty>No item details are stored for this preview.</template>
                <Column header="Student" style="min-width: 13rem">
                    <template #body="{ data }">{{ data.student_name ?? `Student #${data.student_id}` }}</template>
                </Column>
                <Column header="Decision" style="min-width: 8rem">
                    <template #body="{ data }">{{ data.decision }}</template>
                </Column>
                <Column header="Target Class" style="min-width: 11rem">
                    <template #body="{ data }">{{ data.target_rombel_name ?? '-' }}</template>
                </Column>
                <Column header="New Membership" style="min-width: 9rem">
                    <template #body="{ data }">{{ data.new_membership_id ?? '-' }}</template>
                </Column>
            </DataTable>
        </div>
        <template #footer>
            <Button label="Close" icon="pi pi-times" text severity="secondary" @click="batchDialog = false" />
        </template>
    </Dialog>
</template>
