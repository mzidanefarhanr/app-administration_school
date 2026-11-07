<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import apiLogin from '@/apiLogin';
import { firstValidationMessage } from './employeeDirectoryParams.mjs';
import { getEmployeeImportModes, buildEmployeeImportFormData, validateEmployeeImportFile } from './employeeImportParams.mjs';

const { t } = useI18n();

const props = defineProps({
    visible: { type: Boolean, default: false },
    employeeTypeName: { type: String, default: null }
});

const emit = defineEmits(['update:visible', 'imported']);

const importModes = computed(() => getEmployeeImportModes(t));
const templateLanguages = computed(() => [
    { label: t('employees.import.langEn'), value: 'en' },
    { label: t('employees.import.langId'), value: 'id' }
]);

const mode = ref('insert');
const templateLang = ref('en');
const file = ref(null);
const batch = ref(null);
const generalMessage = ref('');
const fileError = ref('');
const uploading = ref(false);
const committing = ref(false);
const downloading = ref(false);

const stage = computed(() => {
    if (!batch.value) return 'upload';
    if (batch.value.status === 'committed') return 'done';
    if (batch.value.status === 'ready') return 'ready';

    return 'invalid';
});

const reset = () => {
    mode.value = 'insert';
    templateLang.value = 'en';
    file.value = null;
    batch.value = null;
    generalMessage.value = '';
    fileError.value = '';
    uploading.value = false;
    committing.value = false;
    downloading.value = false;
};

watch(
    () => props.visible,
    (visible) => {
        if (visible) reset();
    }
);

const close = () => emit('update:visible', false);

const onFileChange = (event) => {
    file.value = event.target.files?.[0] ?? null;
    fileError.value = validateEmployeeImportFile(file.value, t);
    generalMessage.value = '';
};

const upload = async () => {
    if (uploading.value || committing.value) return;

    fileError.value = validateEmployeeImportFile(file.value, t);
    if (fileError.value) return;

    uploading.value = true;
    generalMessage.value = '';

    try {
        const response = await apiLogin.post('/api/imports/employees', buildEmployeeImportFormData(file.value, mode.value));
        batch.value = response.data?.data ?? null;

        if (!batch.value) {
            generalMessage.value = t('employees.import.errors.noPreview');
        }
    } catch (error) {
        generalMessage.value = firstValidationMessage(error.response) ?? t('employees.import.errors.upload');
    } finally {
        uploading.value = false;
    }
};

const commit = async () => {
    if (!batch.value?.token || committing.value) return;

    committing.value = true;
    generalMessage.value = '';

    try {
        const response = await apiLogin.post(`/api/imports/employees/${batch.value.token}/commit`);
        batch.value = { ...batch.value, ...(response.data?.data ?? {}) };
        emit('imported', response.data?.data ?? null);
    } catch (error) {
        generalMessage.value = firstValidationMessage(error.response) ?? t('employees.import.errors.commit');

        const errors = error.response?.data?.data?.errors;
        if (Array.isArray(errors) && errors.length > 0) {
            batch.value = { ...batch.value, status: 'invalid', errors };
        }
    } finally {
        committing.value = false;
    }
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

const downloadTemplate = async (format = 'xlsx') => {
    try {
        const isXlsx = format === 'xlsx';
        const params = {
            ...(props.employeeTypeName ? { type: props.employeeTypeName } : {}),
            format: isXlsx ? 'xlsx' : 'csv',
            lang: templateLang.value
        };
        const ext = isXlsx ? 'xlsx' : 'csv';
        const response = await apiLogin.get('/api/imports/employees/template', { params, responseType: 'blob' });
        const filename = props.employeeTypeName === 'Pendidik'
            ? `teachers-import-template.${ext}`
            : props.employeeTypeName === 'Tenaga Kependidikan'
            ? `employees-import-template.${ext}`
            : `employee-import-template.${ext}`;
        const mimeType = isXlsx ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv;charset=utf-8;';
        downloadBlob(new Blob([response.data], { type: mimeType }), filename);
    } catch (error) {
        generalMessage.value = firstValidationMessage(error.response) ?? t('employees.import.errors.template');
    }
};

const downloadErrors = async () => {
    if (!batch.value?.token || downloading.value) return;

    downloading.value = true;

    try {
        const response = await apiLogin.get(`/api/imports/employees/${batch.value.token}/errors`, { responseType: 'blob' });
        downloadBlob(new Blob([response.data], { type: 'text/csv;charset=utf-8;' }), 'employees-import-errors.csv');
    } catch (error) {
        generalMessage.value = firstValidationMessage(error.response) ?? t('employees.import.errors.errorReport');
    } finally {
        downloading.value = false;
    }
};
</script>

<template>
    <Dialog :visible="visible" :header="t('employees.import.title')" modal :style="{ width: '48rem' }" :breakpoints="{ '768px': '94vw' }" :closable="!uploading && !committing" @update:visible="emit('update:visible', $event)">
        <div class="flex flex-col gap-4">
            <Message v-if="generalMessage" severity="error" :closable="false">{{ generalMessage }}</Message>

            <template v-if="stage === 'upload'">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="employee-import-mode">{{ t('employees.import.modeLabel') }}</label>
                    <Select id="employee-import-mode" v-model="mode" :options="importModes" optionLabel="label" optionValue="value" class="w-full" />
                    <small class="text-surface-500">{{ t('employees.import.modeNotice') }}</small>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="employee-import-file">{{ t('employees.import.fileLabel') }}</label>
                    <input id="employee-import-file" type="file" accept=".csv,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-surface-100 dark:file:bg-surface-800 file:px-3 file:py-2" @change="onFileChange" />
                    <small v-if="fileError" class="text-red-500">{{ fileError }}</small>
                    <small class="text-surface-500">{{ t('employees.import.fileNotice') }}</small>

                    <div class="mt-2 flex flex-col gap-2 rounded-lg border border-surface-200 bg-surface-50 p-3 dark:border-surface-700 dark:bg-surface-800/50">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label class="text-xs font-semibold text-surface-700 dark:text-surface-300" for="employee-template-lang">{{ t('employees.import.templateLanguage') }}</label>
                            <Select id="employee-template-lang" v-model="templateLang" :options="templateLanguages" optionLabel="label" optionValue="value" class="w-56 text-xs" size="small" />
                        </div>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <Button :label="t('employees.import.downloadTemplateXlsx')" icon="pi pi-file-excel" severity="success" text size="small" @click="() => downloadTemplate('xlsx')" />
                            <Button :label="t('employees.import.downloadTemplate')" icon="pi pi-download" severity="secondary" text size="small" @click="() => downloadTemplate('csv')" />
                        </div>
                    </div>
                </div>
            </template>

            <template v-else>
                <div class="flex flex-wrap gap-4 text-sm">
                    <span
                        >{{ t('employees.import.status') }} <strong>{{ t(`employees.import.statuses.${stage === 'done' ? 'committed' : stage === 'ready' ? 'ready' : 'invalid'}`) }}</strong></span
                    >
                    <span
                        >{{ t('employees.import.total') }} <strong>{{ batch.total_rows }}</strong></span
                    >
                    <span class="text-green-600"
                        >{{ t('employees.import.valid') }} <strong>{{ batch.valid_rows }}</strong></span
                    >
                    <span class="text-red-500"
                        >{{ t('employees.import.invalid') }} <strong>{{ batch.invalid_rows }}</strong></span
                    >
                </div>

                <div v-if="stage === 'done'" class="flex items-center gap-3 rounded-md bg-green-50 p-3 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-300">
                    <i class="pi pi-check-circle" />
                    <span>{{ t('employees.import.done', { count: batch.committed_rows }) }}</span>
                </div>

                <div v-if="batch.preview?.length" class="flex flex-col gap-2">
                    <span class="text-sm font-medium">{{ t('employees.import.preview', { count: batch.preview.length }) }}</span>
                    <div class="max-h-56 overflow-auto rounded-md border border-surface-200 dark:border-surface-700">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-surface-100 dark:bg-surface-800">
                                <tr>
                                    <th class="px-2 py-1 text-left">{{ t('employees.import.previewColumns.row') }}</th>
                                    <th class="px-2 py-1 text-left">{{ t('employees.import.previewColumns.action') }}</th>
                                    <th class="px-2 py-1 text-left">{{ t('employees.import.previewColumns.name') }}</th>
                                    <th class="px-2 py-1 text-left">{{ t('employees.import.previewColumns.nik') }}</th>
                                    <th class="px-2 py-1 text-left">{{ t('employees.import.previewColumns.nuptk') }}</th>
                                    <th class="px-2 py-1 text-left">{{ t('employees.import.previewColumns.employeeType') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in batch.preview" :key="row.row" class="border-t border-surface-200 dark:border-surface-700">
                                    <td class="px-2 py-1">{{ row.row }}</td>
                                    <td class="px-2 py-1">{{ row.action === 'update' ? t('employees.import.actions.update') : t('employees.import.actions.insert') }}</td>
                                    <td class="px-2 py-1">{{ row.name || '-' }}</td>
                                    <td class="px-2 py-1">{{ row.nik || '-' }}</td>
                                    <td class="px-2 py-1">{{ row.nuptk || '-' }}</td>
                                    <td class="px-2 py-1">{{ row.employee_type || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="batch.errors?.length" class="flex flex-col gap-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-medium text-red-500">{{ t('employees.import.errorsTitle', { count: batch.errors.length }) }}</span>
                        <Button :label="t('employees.import.downloadErrorReport')" icon="pi pi-download" text size="small" :loading="downloading" @click="downloadErrors" />
                    </div>
                    <div class="max-h-56 overflow-auto rounded-md border border-red-200 dark:border-red-800">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-red-50 dark:bg-red-900/30">
                                <tr>
                                    <th class="px-2 py-1 text-left">{{ t('employees.import.errorColumns.row') }}</th>
                                    <th class="px-2 py-1 text-left">{{ t('employees.import.errorColumns.field') }}</th>
                                    <th class="px-2 py-1 text-left">{{ t('employees.import.errorColumns.message') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(error, index) in batch.errors" :key="`${error.row}-${error.field}-${index}`" class="border-t border-red-100 dark:border-red-900/40">
                                    <td class="px-2 py-1">{{ error.row }}</td>
                                    <td class="px-2 py-1">{{ error.field }}</td>
                                    <td class="px-2 py-1">{{ error.message }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <small class="text-surface-500">{{ t('employees.import.nothingWritten') }}</small>
                </div>

                <div v-if="stage === 'done'" class="flex flex-wrap gap-2">
                    <Button :label="t('employees.import.importAnother')" icon="pi pi-upload" severity="secondary" variant="outlined" @click="reset" />
                </div>
            </template>
        </div>

        <template #footer>
            <Button :label="t('common.action.close')" severity="secondary" variant="outlined" :disabled="uploading || committing" @click="close" />
            <Button v-if="stage === 'upload'" :label="t('employees.import.uploadPreview')" icon="pi pi-search" :loading="uploading" :disabled="uploading" @click="upload" />
            <Button v-if="stage === 'ready'" :label="t('employees.import.commit')" icon="pi pi-check" severity="success" :loading="committing" :disabled="committing" @click="commit" />
        </template>
    </Dialog>
</template>
