<script setup>
import { ref, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Message from 'primevue/message';
import Dialog from 'primevue/dialog';
import apiLogin from '@/apiLogin';
import { firstValidationMessage } from './employeeDirectoryParams.mjs';

const { t } = useI18n();

const props = defineProps({
    visible: { type: Boolean, default: false },
    employees: { type: Array, default: () => [] },
    isTeacher: { type: Boolean, default: false }
});

const emit = defineEmits(['update:visible', 'archived']);

const reason = ref('');
const saving = ref(false);
const generalMessage = ref('');
const fieldError = ref('');

const entityLabel = computed(() => (props.isTeacher ? t('employees.entity.teacher') : t('employees.entity.employee')));

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
    if (saving.value || !props.employees || props.employees.length === 0) return;

    if (!reason.value.trim()) {
        fieldError.value = t('employees.archive.errors.reasonRequired');
        return;
    }

    saving.value = true;
    generalMessage.value = '';
    fieldError.value = '';

    try {
        const items = props.employees.map((e) => ({
            id: e.id,
            version: e.version
        }));

        await apiLogin.post('/api/employees/batch-archive', {
            items,
            archive_reason: reason.value.trim()
        });

        emit('archived', items.length);
        emit('update:visible', false);
    } catch (error) {
        if (error.response?.status === 422) {
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
    <Dialog
        :visible="visible"
        :header="`Arsipkan ${entityLabel} Terpilih`"
        modal
        :style="{ width: '36rem' }"
        :breakpoints="{ '768px': '92vw' }"
        :closable="!saving"
        @update:visible="emit('update:visible', $event)"
    >
        <div class="flex flex-col gap-3">
            <p class="text-sm text-surface-600 dark:text-surface-300">{{ t('employees.archive.notice') }}</p>

            <div class="bg-surface-50 dark:bg-surface-800 p-3 rounded-lg flex flex-col gap-2 text-sm border border-surface-200 dark:border-surface-700">
                <div class="flex items-center justify-between font-medium">
                    <span>Jumlah data yang akan diarsipkan:</span>
                    <Tag :value="`${employees.length} ${entityLabel}`" severity="warn" />
                </div>
                <div class="max-h-36 overflow-y-auto text-xs text-surface-600 dark:text-surface-300 divide-y divide-surface-100 dark:divide-surface-700">
                    <div v-for="emp in employees" :key="emp.id" class="py-1 flex justify-between">
                        <span class="font-medium">{{ emp.name }}</span>
                        <span class="text-surface-400">NUPTK: {{ emp.nuptk || '-' }} · NIK: {{ emp.nik || '-' }}</span>
                    </div>
                </div>
            </div>

            <Message v-if="generalMessage" severity="error" :closable="false">{{ generalMessage }}</Message>

            <div>
                <label class="block mb-2 font-medium text-sm">{{ t('employees.archive.reason') }}</label>
                <Textarea v-model="reason" rows="3" class="w-full" maxlength="255" autoResize placeholder="Contoh: Pensiun massal atau penyesuaian administratif" />
                <small v-if="fieldError" class="text-red-500">{{ fieldError }}</small>
            </div>
        </div>

        <template #footer>
            <Button :label="t('common.action.cancel')" severity="secondary" variant="outlined" :disabled="saving" @click="emit('update:visible', false)" />
            <Button label="Ya, Arsipkan Data" icon="pi pi-inbox" severity="warn" :loading="saving" @click="submit" />
        </template>
    </Dialog>
</template>
