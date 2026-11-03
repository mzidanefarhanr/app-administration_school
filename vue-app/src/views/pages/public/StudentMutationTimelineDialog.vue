<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import apiLogin from '@/apiLogin';
import { formatDate as formatDateUtil } from '@/utils/formatters';

const props = defineProps({
    visible: { type: Boolean, default: false },
    student: { type: Object, default: null }
});

const emit = defineEmits(['update:visible']);

const { t, locale } = useI18n();

const loading = ref(false);
const error = ref('');
const mutations = ref([]);
const corrections = ref([]);

const mutationLabel = (type) => (type === 'in' ? t('students.mutations.timeline.mutationIn') : t('students.mutations.timeline.mutationOut'));

const actionLabel = (action) => {
    const key = `students.mutations.timeline.actions.${action}`;
    const translated = t(key);
    return translated !== key ? translated : action;
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

const displayValue = (value) => (value === null || value === undefined || value === '' ? '-' : String(value));

const summaryLines = (correction) => {
    const lines = [];
    const before = correction.before ?? {};
    const after = correction.after ?? {};

    if (before.mutation && after.mutation) {
        if (displayValue(before.mutation.status) !== displayValue(after.mutation.status)) {
            lines.push(`Status: ${displayValue(before.mutation.status)} -> ${displayValue(after.mutation.status)}`);
        }
        if (displayValue(before.mutation.effective_date) !== displayValue(after.mutation.effective_date)) {
            lines.push(`${t('students.mutations.columns.effectiveDate')}: ${displayValue(before.mutation.effective_date)} -> ${displayValue(after.mutation.effective_date)}`);
        }
        if (displayValue(before.mutation.school_npsn) !== displayValue(after.mutation.school_npsn)) {
            lines.push(`${t('students.mutations.columns.school')} NPSN: ${displayValue(before.mutation.school_npsn)} -> ${displayValue(after.mutation.school_npsn)}`);
        }
    }

    if (before.membership && after.membership) {
        if (displayValue(before.membership.student_status_id) !== displayValue(after.membership.student_status_id)) {
            lines.push(`Membership status: ${displayValue(before.membership.student_status_id)} -> ${displayValue(after.membership.student_status_id)}`);
        }
        if (displayValue(before.membership.latest_student_entry_date) !== displayValue(after.membership.latest_student_entry_date)) {
            lines.push(`Entry date: ${displayValue(before.membership.latest_student_entry_date)} -> ${displayValue(after.membership.latest_student_entry_date)}`);
        }
        if (displayValue(before.membership.latest_student_exit_date) !== displayValue(after.membership.latest_student_exit_date)) {
            lines.push(`Exit date: ${displayValue(before.membership.latest_student_exit_date)} -> ${displayValue(after.membership.latest_student_exit_date)}`);
        }
        if (displayValue(before.membership.latest_student_exit_school_id) !== displayValue(after.membership.latest_student_exit_school_id)) {
            lines.push(`Exit school NPSN: ${displayValue(before.membership.latest_student_exit_school_id)} -> ${displayValue(after.membership.latest_student_exit_school_id)}`);
        }
        if (displayValue(before.membership.latest_student_graduation_date) !== displayValue(after.membership.latest_student_graduation_date)) {
            lines.push(`Graduation date: ${displayValue(before.membership.latest_student_graduation_date)} -> ${displayValue(after.membership.latest_student_graduation_date)}`);
        }
    }

    return lines;
};

const events = computed(() => {
    const rows = mutations.value.map((mutation) => ({
        key: `mutation-${mutation.id}`,
        kind: 'mutation',
        sortAt: mutation.effective_date ?? '',
        mutation
    }));

    corrections.value.forEach((correction) => {
        rows.push({
            key: `correction-${correction.id}`,
            kind: 'correction',
            sortAt: correction.created_at ?? '',
            correction
        });
    });

    return rows.sort((a, b) => {
        if (a.sortAt === b.sortAt) return a.key.localeCompare(b.key);
        return a.sortAt < b.sortAt ? -1 : 1;
    });
});

const load = async () => {
    if (!props.student?.id) return;

    loading.value = true;
    error.value = '';
    mutations.value = [];
    corrections.value = [];

    try {
        const [mutationResponse, correctionResponse] = await Promise.all([apiLogin.get(`/api/students/${props.student.id}/mutations`), apiLogin.get(`/api/students/${props.student.id}/locked-corrections`)]);
        mutations.value = mutationResponse.data?.data?.mutations ?? [];
        corrections.value = correctionResponse.data?.data?.items ?? [];
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Failed to load the student mutation timeline.';
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.visible,
    (visible) => {
        if (visible) load();
    }
);

const close = () => emit('update:visible', false);
</script>

<template>
    <Dialog :visible="visible" modal :draggable="false" :style="{ width: 'min(94vw, 44rem)' }" :header="t('students.mutations.timeline.title')" @update:visible="(value) => emit('update:visible', value)">
        <div class="flex flex-col gap-4">
            <div v-if="student" class="text-sm text-surface-600 dark:text-surface-300">
                <strong>{{ student.name }}</strong>
                <span v-if="student.nis"> · NIS {{ student.nis }}</span>
            </div>

            <div v-if="loading" class="flex justify-center py-6">
                <i class="pi pi-spin pi-spinner text-2xl" aria-hidden="true"></i>
                <span class="sr-only">{{ t('students.mutations.timeline.loadingSr') }}</span>
            </div>

            <Message v-else-if="error" severity="error" :closable="false">{{ error }}</Message>

            <div v-else-if="events.length === 0" class="text-surface-500 py-4">{{ t('students.mutations.timeline.empty') }}</div>

            <ol v-else class="flex flex-col gap-4 list-none p-0 m-0">
                <li v-for="event in events" :key="event.key" class="border-l-4 pl-3" :class="event.kind === 'correction' ? 'border-orange-400' : event.mutation.status === 'cancelled' ? 'border-red-300' : 'border-green-500'">
                    <template v-if="event.kind === 'mutation'">
                        <div class="flex flex-wrap items-center gap-2">
                            <Tag :value="mutationLabel(event.mutation.type)" :severity="event.mutation.type === 'in' ? 'info' : 'warn'" />
                            <Tag :value="event.mutation.status === 'active' ? t('students.mutations.timeline.active') : t('students.mutations.timeline.cancelled')" :severity="event.mutation.status === 'active' ? 'success' : 'danger'" />
                            <span class="text-sm font-medium">{{ formatDate(event.mutation.effective_date) }}</span>
                        </div>
                        <div class="text-sm mt-1">
                            {{ t('students.mutations.timeline.class') }} <strong>{{ event.mutation.class?.name ?? '-' }}</strong> · {{ t('students.mutations.timeline.school') }} <strong>{{ event.mutation.school_npsn }}</strong>
                            <span v-if="event.mutation.school_name"> — {{ event.mutation.school_name }}</span>
                        </div>
                        <div v-if="event.mutation.reason" class="text-sm">{{ t('students.mutations.timeline.reason', { reason: event.mutation.reason }) }}</div>
                        <div class="text-xs text-surface-500 mt-1">{{ t('students.mutations.timeline.recordedBy', { user: event.mutation.created_by?.name ?? 'Unknown', time: formatDateTime(event.mutation.created_at) }) }}</div>
                        <div v-if="event.mutation.status === 'cancelled'" class="text-xs text-red-500 mt-1">
                            {{ t('students.mutations.timeline.cancelledBy', { user: event.mutation.cancelled_by?.name ?? 'Unknown', time: formatDateTime(event.mutation.cancelled_at), reason: event.mutation.cancel_reason ?? '-' }) }}
                        </div>
                    </template>

                    <template v-else>
                        <div class="flex flex-wrap items-center gap-2">
                            <Tag :value="t('students.mutations.timeline.lockedCorrection')" severity="secondary" />
                            <span class="text-sm font-medium">{{ actionLabel(event.correction.action) }}</span>
                            <span class="text-sm">{{ formatDateTime(event.correction.created_at) }}</span>
                        </div>
                        <div class="text-sm mt-1">
                            {{ t('students.mutations.timeline.academicYear') }} <strong>{{ event.correction.year_name ?? '-' }}</strong>
                        </div>
                        <ul v-if="summaryLines(event.correction).length > 0" class="text-sm mt-1 list-disc pl-5">
                            <li v-for="line in summaryLines(event.correction)" :key="line">{{ line }}</li>
                        </ul>
                        <div v-if="event.correction.reason" class="text-sm">{{ t('students.mutations.timeline.reason', { reason: event.correction.reason }) }}</div>
                        <div v-if="event.correction.approval_reference" class="text-sm">{{ t('students.mutations.timeline.approvalRef', { ref: event.correction.approval_reference }) }}</div>
                        <div class="text-xs text-surface-500 mt-1">{{ t('students.mutations.timeline.recordedBy', { user: event.correction.created_by?.name ?? 'Unknown', time: formatDateTime(event.correction.created_at) }) }}</div>
                    </template>
                </li>
            </ol>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button :label="t('common.action.refresh')" icon="pi pi-refresh" severity="secondary" variant="outlined" :disabled="loading" @click="load" />
                <Button :label="t('common.action.close')" icon="pi pi-times" text @click="close" />
            </div>
        </template>
    </Dialog>
</template>
