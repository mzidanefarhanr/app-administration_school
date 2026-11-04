<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import apiLogin from '@/apiLogin';
import { buildEmployeeExportParams, firstValidationMessage } from './employeeDirectoryParams.mjs';

const { t } = useI18n();

const props = defineProps({
    visible: { type: Boolean, default: false },
    filters: { type: Object, default: () => ({}) },
    entityLabel: { type: String, default: 'Employees' }
});

const emit = defineEmits(['update:visible', 'downloaded']);

const columns = ref([]);
const selected = ref([]);
const defaultKeys = ref([]);
const sensitiveAllowed = ref(false);
const loadingOptions = ref(false);
const downloading = ref(false);
const optionsError = ref('');
const downloadError = ref('');

const operationalColumns = computed(() => columns.value.filter((column) => !column.sensitive));
const sensitiveColumns = computed(() => columns.value.filter((column) => column.sensitive));
const canDownload = computed(() => selected.value.length > 0 && !loadingOptions.value && !downloading.value);

const reset = () => {
    columns.value = [];
    selected.value = [];
    defaultKeys.value = [];
    sensitiveAllowed.value = false;
    loadingOptions.value = false;
    downloading.value = false;
    optionsError.value = '';
    downloadError.value = '';
};

const loadOptions = async () => {
    loadingOptions.value = true;
    optionsError.value = '';

    try {
        const response = await apiLogin.get('/api/employees/export/options');
        const data = response.data?.data ?? {};

        columns.value = Array.isArray(data.columns) ? data.columns : [];
        defaultKeys.value = Array.isArray(data.default) ? data.default : [];
        sensitiveAllowed.value = Boolean(data.sensitive_allowed);
        selected.value = [...defaultKeys.value];
    } catch (error) {
        optionsError.value = firstValidationMessage(error.response) ?? t('employees.export.errors.loadOptions');
    } finally {
        loadingOptions.value = false;
    }
};

watch(
    () => props.visible,
    (visible) => {
        if (visible) {
            reset();
            loadOptions();
        }
    }
);

const close = () => emit('update:visible', false);

const isSelected = (key) => selected.value.includes(key);

const toggleColumn = (key, checked) => {
    if (checked) {
        if (!selected.value.includes(key)) selected.value = [...selected.value, key];

        return;
    }

    selected.value = selected.value.filter((selectedKey) => selectedKey !== key);
};

const selectDefaults = () => {
    selected.value = [...defaultKeys.value];
};

const selectAll = () => {
    selected.value = columns.value.map((column) => column.key);
};

const clearAll = () => {
    selected.value = [];
};

const downloadBlob = (data, filename) => {
    const url = URL.createObjectURL(data);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
};

const download = async () => {
    if (!canDownload.value) return;

    downloading.value = true;
    downloadError.value = '';

    try {
        const params = buildEmployeeExportParams(props.filters, selected.value);
        const response = await apiLogin.get('/api/employees/export', { params, responseType: 'blob' });

        downloadBlob(new Blob([response.data], { type: 'text/csv;charset=utf-8;' }), `${props.entityLabel.toLowerCase()}.csv`);
        emit('downloaded', { columns: selected.value.length });
        close();
    } catch (error) {
        let message = t('employees.export.errors.export');

        if (error.response?.data instanceof Blob) {
            try {
                const payload = JSON.parse(await error.response.data.text());
                message = payload.message ?? firstValidationMessage(error.response) ?? message;
            } catch {
                // keep the generic message when the body is not JSON
            }
        } else {
            message = firstValidationMessage(error.response) ?? message;
        }

        downloadError.value = message;
    } finally {
        downloading.value = false;
    }
};
</script>

<template>
    <Dialog :visible="visible" :header="t('employees.export.title')" modal :style="{ width: '42rem' }" :breakpoints="{ '768px': '94vw' }" :closable="!downloading" @update:visible="emit('update:visible', $event)">
        <div class="flex flex-col gap-4">
            <Message v-if="optionsError" severity="error" :closable="false">{{ optionsError }}</Message>
            <Message v-if="downloadError" severity="error" :closable="false">{{ downloadError }}</Message>
            <Message v-if="loadingOptions" severity="info" :closable="false">{{ t('employees.export.loading') }}</Message>

            <template v-if="!loadingOptions && !optionsError">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm text-surface-500">{{ t('employees.export.summary', { defaults: defaultKeys.length, selected: selected.length }) }}</span>
                    <div class="flex flex-wrap gap-1">
                        <Button :label="t('employees.export.defaults')" size="small" text @click="selectDefaults" />
                        <Button :label="t('employees.export.selectAll')" size="small" text @click="selectAll" />
                        <Button :label="t('employees.export.clear')" size="small" text severity="secondary" @click="clearAll" />
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <span class="text-sm font-medium">{{ t('employees.export.operational') }}</span>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <label v-for="column in operationalColumns" :key="column.key" :for="`export-column-${column.key}`" class="flex items-center gap-2 text-sm">
                            <Checkbox :inputId="`export-column-${column.key}`" :modelValue="isSelected(column.key)" binary @update:modelValue="toggleColumn(column.key, $event)" />
                            <span>{{ column.header }}</span>
                        </label>
                    </div>
                </div>

                <div v-if="sensitiveColumns.length" class="flex flex-col gap-2 rounded-md border border-amber-200 p-3 dark:border-amber-800">
                    <div class="flex flex-col gap-1">
                        <span class="text-sm font-medium text-amber-700 dark:text-amber-300">{{ t('employees.export.sensitive') }}</span>
                        <small class="text-surface-500">{{ t('employees.export.sensitiveNotice') }}</small>
                    </div>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <label v-for="column in sensitiveColumns" :key="column.key" :for="`export-column-${column.key}`" class="flex items-center gap-2 text-sm">
                            <Checkbox :inputId="`export-column-${column.key}`" :modelValue="isSelected(column.key)" binary @update:modelValue="toggleColumn(column.key, $event)" />
                            <span>{{ column.header }}</span>
                        </label>
                    </div>
                </div>

                <small v-if="!sensitiveAllowed" class="text-surface-500">{{ t('employees.export.sensitiveHidden') }}</small>
                <small v-if="!selected.length" class="text-red-500">{{ t('employees.export.selectAtLeastOne') }}</small>
            </template>
        </div>

        <template #footer>
            <Button :label="t('common.action.cancel')" severity="secondary" variant="outlined" :disabled="downloading" @click="close" />
            <Button :label="t('employees.export.download')" icon="pi pi-download" :loading="downloading" :disabled="!canDownload" @click="download" />
        </template>
    </Dialog>
</template>
