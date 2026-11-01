<script setup>
import { computed, ref, watch } from 'vue';
import Button from 'primevue/button';
import apiLogin from '@/apiLogin';

const props = defineProps({
    visible: { type: Boolean, default: false },
    student: { type: Object, default: null }
});

const emit = defineEmits(['update:visible']);

const loading = ref(false);
const error = ref('');
const mutations = ref([]);
const corrections = ref([]);

const mutationLabel = (type) => (type === 'in' ? 'Mutation In' : 'Mutation Out');

const actionLabels = {
    mutation_in: 'Mutation In date/school',
    mutation_out: 'Mutation Out date/school',
    mutation_cancel: 'Mutation cancellation',
    membership_exit: 'Membership exit',
    membership_reopen: 'Membership reopen'
};

const formatDate = (value) => {
    if (!value) return '-';
    const raw = String(value).slice(0, 10);
    const parsed = new Date(raw + 'T00:00:00');
    if (Number.isNaN(parsed.getTime())) return raw;
    return parsed.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatDateTime = (value) => {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
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
            lines.push(`Effective date: ${displayValue(before.mutation.effective_date)} -> ${displayValue(after.mutation.effective_date)}`);
        }
        if (displayValue(before.mutation.school_npsn) !== displayValue(after.mutation.school_npsn)) {
            lines.push(`School NPSN: ${displayValue(before.mutation.school_npsn)} -> ${displayValue(after.mutation.school_npsn)}`);
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
    <Dialog :visible="visible" @update:visible="(value) => emit('update:visible', value)" modal :draggable="false" :style="{ width: 'min(94vw, 44rem)' }" header="Student Mutation Timeline">
        <div class="flex flex-col gap-4">
            <div v-if="student" class="text-sm text-surface-600 dark:text-surface-300">
                <strong>{{ student.name }}</strong>
                <span v-if="student.nis"> · NIS {{ student.nis }}</span>
            </div>

            <div v-if="loading" class="flex justify-center py-6">
                <i class="pi pi-spin pi-spinner text-2xl" aria-hidden="true"></i>
                <span class="sr-only">Loading the mutation timeline</span>
            </div>

            <Message v-else-if="error" severity="error" :closable="false">{{ error }}</Message>

            <div v-else-if="events.length === 0" class="text-surface-500 py-4">No mutations or locked corrections have been recorded for this student.</div>

            <ol v-else class="flex flex-col gap-4 list-none p-0 m-0">
                <li v-for="event in events" :key="event.key" class="border-l-4 pl-3" :class="event.kind === 'correction' ? 'border-orange-400' : event.mutation.status === 'cancelled' ? 'border-red-300' : 'border-green-500'">
                    <template v-if="event.kind === 'mutation'">
                        <div class="flex flex-wrap items-center gap-2">
                            <Tag :value="mutationLabel(event.mutation.type)" :severity="event.mutation.type === 'in' ? 'info' : 'warn'" />
                            <Tag :value="event.mutation.status === 'active' ? 'Active' : 'Cancelled'" :severity="event.mutation.status === 'active' ? 'success' : 'danger'" />
                            <span class="text-sm font-medium">{{ formatDate(event.mutation.effective_date) }}</span>
                        </div>
                        <div class="text-sm mt-1">
                            Class: <strong>{{ event.mutation.class?.name ?? '-' }}</strong> · School: <strong>{{ event.mutation.school_npsn }}</strong>
                            <span v-if="event.mutation.school_name"> — {{ event.mutation.school_name }}</span>
                        </div>
                        <div class="text-sm" v-if="event.mutation.reason">Reason: {{ event.mutation.reason }}</div>
                        <div class="text-xs text-surface-500 mt-1">Recorded by {{ event.mutation.created_by?.name ?? 'Unknown' }} on {{ formatDateTime(event.mutation.created_at) }}</div>
                        <div v-if="event.mutation.status === 'cancelled'" class="text-xs text-red-500 mt-1">
                            Cancelled by {{ event.mutation.cancelled_by?.name ?? 'Unknown' }} on {{ formatDateTime(event.mutation.cancelled_at) }}: {{ event.mutation.cancel_reason ?? '-' }}
                        </div>
                    </template>

                    <template v-else>
                        <div class="flex flex-wrap items-center gap-2">
                            <Tag value="Locked Correction" severity="secondary" />
                            <span class="text-sm font-medium">{{ actionLabels[event.correction.action] ?? event.correction.action }}</span>
                            <span class="text-sm">{{ formatDateTime(event.correction.created_at) }}</span>
                        </div>
                        <div class="text-sm mt-1">
                            Academic year: <strong>{{ event.correction.year_name ?? '-' }}</strong>
                        </div>
                        <ul class="text-sm mt-1 list-disc pl-5" v-if="summaryLines(event.correction).length > 0">
                            <li v-for="line in summaryLines(event.correction)" :key="line">{{ line }}</li>
                        </ul>
                        <div class="text-sm" v-if="event.correction.reason">Reason: {{ event.correction.reason }}</div>
                        <div class="text-sm" v-if="event.correction.approval_reference">Approval reference: {{ event.correction.approval_reference }}</div>
                        <div class="text-xs text-surface-500 mt-1">Recorded by {{ event.correction.created_by?.name ?? 'Unknown' }}</div>
                    </template>
                </li>
            </ol>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Reload" icon="pi pi-refresh" severity="secondary" variant="outlined" :disabled="loading" @click="load" />
                <Button label="Close" icon="pi pi-times" text @click="close" />
            </div>
        </template>
    </Dialog>
</template>
