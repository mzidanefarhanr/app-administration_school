<script setup>
import { ref, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import apiLogin from '@/apiLogin';
import { firstValidationMessage } from './employeeDirectoryParams.mjs';

const { t } = useI18n();

const props = defineProps({
    visible: { type: Boolean, default: false },
    employee: { type: Object, default: null }
});

const emit = defineEmits(['update:visible', 'archived', 'conflict']);

const reason = ref('');
const saving = ref(false);
const generalMessage = ref('');
const fieldError = ref('');
const recordLabel = computed(() => (props.employee?.employee_type?.name === 'Pendidik' ? t('employees.entity.teacher') : t('employees.entity.employee')));

watch(
    () => props.visible,
    (visible) => {
        if (visible) {
            reason.value = '';
            saving.value = false;
            generalMessage.value = '';
            fieldError.value = '';
        }
    }
);

const submit = async () => {
    if (saving.value || !props.employee) return;

    if (!reason.value.trim()) {
        fieldError.value = t('employees.archive.errors.reasonRequired');
        return;
    }

    saving.value = true;
    generalMessage.value = '';
    fieldError.value = '';

    try {
        await apiLogin.delete(`/api/employees/${props.employee.id}`, {
            data: {
                version: props.employee.version,
                archive_reason: reason.value.trim()
            }
        });
        emit('archived');
        emit('update:visible', false);
    } catch (error) {
        if (error.response?.status === 409) {
            emit('conflict', error.response?.data?.current_version ?? null);
            emit('update:visible', false);
        } else if (error.response?.status === 422) {
            fieldError.value = error.response?.data?.errors?.archive_reason?.[0] ?? '';
            generalMessage.value = fieldError.value ? '' : (firstValidationMessage(error.response) ?? '');
        } else {
            generalMessage.value = firstValidationMessage(error.response) ?? t('employees.archive.errors.archive');
        }
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <Dialog :visible="visible" :header="t('employees.archive.title', { entity: recordLabel })" modal :style="{ width: '32rem' }" :breakpoints="{ '768px': '92vw' }" :closable="!saving" @update:visible="emit('update:visible', $event)">
        <div class="flex flex-col gap-3">
            <p class="text-sm">{{ t('employees.archive.notice') }}</p>
            <p class="text-sm font-medium">{{ employee?.name }}</p>
            <Message v-if="generalMessage" severity="error" :closable="false">{{ generalMessage }}</Message>
            <div>
                <label class="block mb-2">{{ t('employees.archive.reason') }}</label>
                <Textarea v-model="reason" rows="3" class="w-full" maxlength="255" autoResize />
                <small v-if="fieldError" class="text-red-500">{{ fieldError }}</small>
            </div>
        </div>

        <template #footer>
            <Button :label="t('common.action.cancel')" severity="secondary" variant="outlined" :disabled="saving" @click="emit('update:visible', false)" />
            <Button :label="t('employees.archive.submit')" icon="pi pi-inbox" severity="danger" :loading="saving" @click="submit" />
        </template>
    </Dialog>
</template>
