<script setup>
import { computed, ref, watch } from 'vue';
import Button from 'primevue/button';
import apiLogin from '@/apiLogin';
import { buildReviewPayload, issueFieldLabel, issueTypeLabel, reviewReasonOptions } from './dataQualityParams.mjs';

const props = defineProps({
    visible: { type: Boolean, default: false },
    issue: { type: Object, default: null },
    domain: { type: String, default: 'students' },
    schoolYearId: { type: [Number, String], default: null },
    initialStatus: { type: String, default: 'acknowledged' }
});

const emit = defineEmits(['update:visible', 'reviewed', 'stale']);

const statusOptions = [
    { value: 'acknowledged', label: 'Acknowledge' },
    { value: 'ignored', label: 'Ignore' },
    { value: 'open', label: 'Reopen' }
];

const status = ref('acknowledged');
const reasonCode = ref(null);
const saving = ref(false);
const generalMessage = ref('');
const fieldMessage = ref('');

const reasonOptions = computed(() => reviewReasonOptions(props.issue?.type));
const recordLabel = computed(() => (props.domain === 'students' ? 'Student' : 'Employee'));
const needsReason = computed(() => status.value === 'ignored');

const reset = () => {
    status.value = props.initialStatus || 'acknowledged';
    reasonCode.value = null;
    saving.value = false;
    generalMessage.value = '';
    fieldMessage.value = '';
};

watch(
    () => [props.visible, props.issue?.issue_key, props.initialStatus],
    () => {
        if (props.visible) reset();
    }
);

const firstError = (response) => {
    const errors = response?.data?.errors;
    if (errors && typeof errors === 'object') {
        const first = Object.values(errors)[0];
        if (Array.isArray(first) && first.length > 0) return first[0];
        if (typeof first === 'string') return first;
    }

    return response?.data?.message ?? null;
};

const submit = async () => {
    if (saving.value || !props.issue) return;

    if (needsReason.value && !reasonCode.value) {
        fieldMessage.value = 'Select a reason before ignoring this issue.';
        return;
    }

    saving.value = true;
    generalMessage.value = '';
    fieldMessage.value = '';

    try {
        const payload = buildReviewPayload(props.issue, {
            status: status.value,
            reasonCode: reasonCode.value,
            schoolYearId: props.domain === 'students' ? props.schoolYearId : null
        });
        const response = await apiLogin.put(`/api/data-quality/${props.domain}/reviews/${props.issue.issue_key}`, payload);
        emit('reviewed', response.data);
        emit('update:visible', false);
    } catch (error) {
        const httpStatus = error.response?.status;
        if (httpStatus === 409) {
            emit('stale', firstError(error.response) ?? 'This issue is out of date. Refresh the list.');
            emit('update:visible', false);
        } else if (httpStatus === 422) {
            fieldMessage.value = firstError(error.response) ?? 'Check the review values and try again.';
        } else if (httpStatus === 404) {
            generalMessage.value = firstError(error.response) ?? 'The record no longer exists.';
        } else {
            generalMessage.value = firstError(error.response) ?? 'Failed to save the review. Please try again.';
        }
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <Dialog :visible="visible" modal :header="`Review ${recordLabel} Issue`" :style="{ width: '34rem' }" :breakpoints="{ '768px': '92vw' }" :closable="!saving" @update:visible="emit('update:visible', $event)">
        <div v-if="issue" class="flex flex-col gap-4 text-sm">
            <div class="flex flex-wrap items-center gap-2">
                <Tag :value="issueTypeLabel(issue.type)" severity="warn" />
                <span class="font-medium">{{ issueFieldLabel(issue.field) }}</span>
            </div>

            <p class="text-surface-600 dark:text-surface-300">{{ issue.message }}</p>

            <div class="rounded-border bg-surface-50 dark:bg-surface-800 p-3 flex flex-col gap-1">
                <div>
                    <span class="text-surface-500">Record:</span> <span class="font-medium">{{ issue.subject?.name ?? '-' }}</span>
                </div>
                <div v-if="domain === 'students' && issue.subject?.nis"><span class="text-surface-500">NIS:</span> {{ issue.subject.nis }}</div>
                <div v-if="domain === 'employees' && issue.subject?.nuptk"><span class="text-surface-500">NUPTK:</span> {{ issue.subject.nuptk }}</div>
                <div v-if="issue.related"><span class="text-surface-500">Related record:</span> {{ issue.related.name ?? '-' }}</div>
                <div>
                    <span class="text-surface-500">Review status:</span>
                    {{ issue.review?.status ?? 'open' }}
                    <span v-if="issue.review?.version">(version {{ issue.review.version }})</span>
                </div>
            </div>

            <Message v-if="generalMessage" severity="error" :closable="false">{{ generalMessage }}</Message>

            <div>
                <label class="block mb-2 font-medium">Review action</label>
                <SelectButton v-model="status" :options="statusOptions" optionLabel="label" optionValue="value" :allowEmpty="false" />
            </div>

            <div v-if="needsReason">
                <label class="block mb-2 font-medium">Reason *</label>
                <Select v-model="reasonCode" :options="reasonOptions" optionLabel="label" optionValue="value" placeholder="Select a reason" class="w-full" />
                <small v-if="fieldMessage" class="text-red-500">{{ fieldMessage }}</small>
                <small v-else class="text-surface-500"> A reason is required when ignoring an issue. It is stored with the review audit trail. </small>
            </div>
            <small v-else-if="fieldMessage" class="text-red-500">{{ fieldMessage }}</small>

            <p class="text-xs text-surface-500">Review status records workflow only. It does not change {{ recordLabel.toLowerCase() }} data, completeness percentages, or duplicate candidates. Correct the record through the existing form.</p>
        </div>

        <template #footer>
            <Button label="Cancel" severity="secondary" variant="outlined" :disabled="saving" @click="emit('update:visible', false)" />
            <Button label="Save Review" icon="pi pi-check" :loading="saving" @click="submit" />
        </template>
    </Dialog>
</template>
