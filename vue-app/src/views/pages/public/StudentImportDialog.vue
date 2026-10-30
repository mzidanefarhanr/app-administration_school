<script setup>
import { computed, ref, watch } from 'vue';
import Button from 'primevue/button';
import apiLogin from '@/apiLogin';
import { firstValidationMessage } from './employeeDirectoryParams.mjs';
import { STUDENT_IMPORT_MODES, buildStudentImportFormData, validateStudentImportFile } from './studentImportParams.mjs';

const props = defineProps({
    visible: { type: Boolean, default: false }
});

const emit = defineEmits(['update:visible', 'imported']);

const mode = ref('insert');
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
    fileError.value = validateStudentImportFile(file.value);
    generalMessage.value = '';
};

const upload = async () => {
    if (uploading.value || committing.value) return;

    fileError.value = validateStudentImportFile(file.value);
    if (fileError.value) return;

    uploading.value = true;
    generalMessage.value = '';

    try {
        const response = await apiLogin.post('/api/imports/students', buildStudentImportFormData(file.value, mode.value));
        batch.value = response.data?.data ?? null;

        if (!batch.value) {
            generalMessage.value = 'The server did not return an import preview.';
        }
    } catch (error) {
        generalMessage.value = firstValidationMessage(error.response) ?? 'Failed to upload the file. Please try again.';
    } finally {
        uploading.value = false;
    }
};

const commit = async () => {
    if (!batch.value?.token || committing.value) return;

    committing.value = true;
    generalMessage.value = '';

    try {
        const response = await apiLogin.post(`/api/imports/${batch.value.token}/commit`);
        batch.value = { ...batch.value, ...(response.data?.data ?? {}) };
        emit('imported', response.data?.data ?? null);
    } catch (error) {
        generalMessage.value = firstValidationMessage(error.response) ?? 'The import could not be committed. Upload the file again.';

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

const downloadTemplate = async () => {
    try {
        const response = await apiLogin.get('/api/imports/students/template', { responseType: 'blob' });
        downloadBlob(new Blob([response.data], { type: 'text/csv;charset=utf-8;' }), 'students-import-template.csv');
    } catch (error) {
        generalMessage.value = firstValidationMessage(error.response) ?? 'Failed to download the template.';
    }
};

const downloadErrors = async () => {
    if (!batch.value?.token || downloading.value) return;

    downloading.value = true;

    try {
        const response = await apiLogin.get(`/api/imports/${batch.value.token}/errors`, { responseType: 'blob' });
        downloadBlob(new Blob([response.data], { type: 'text/csv;charset=utf-8;' }), 'students-import-errors.csv');
    } catch (error) {
        generalMessage.value = firstValidationMessage(error.response) ?? 'Failed to download the error report.';
    } finally {
        downloading.value = false;
    }
};
</script>

<template>
    <Dialog :visible="visible" header="Import Students" modal :style="{ width: '46rem' }" :breakpoints="{ '768px': '94vw' }" :closable="!uploading && !committing" @update:visible="emit('update:visible', $event)">
        <div class="flex flex-col gap-4">
            <Message v-if="generalMessage" severity="error" :closable="false">{{ generalMessage }}</Message>

            <template v-if="stage === 'upload'">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="student-import-mode">Mode</label>
                    <Select id="student-import-mode" v-model="mode" :options="STUDENT_IMPORT_MODES" optionLabel="label" optionValue="value" class="w-full" />
                    <small class="text-surface-500"> Update mode matches students by NIS, then NIK. Names are never used to match records. </small>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium" for="student-import-file">CSV file</label>
                    <input id="student-import-file" type="file" accept=".csv,text/csv" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-surface-100 dark:file:bg-surface-800 file:px-3 file:py-2" @change="onFileChange" />
                    <small v-if="fileError" class="text-red-500">{{ fileError }}</small>
                    <small class="text-surface-500"> UTF-8 CSV, up to 5 MB and 2,000 rows. Use the English template headers only. </small>
                    <div>
                        <Button label="Download Template" icon="pi pi-download" text size="small" @click="downloadTemplate" />
                    </div>
                </div>
            </template>

            <template v-else>
                <div class="flex flex-wrap gap-4 text-sm">
                    <span
                        >Status: <strong>{{ stage === 'done' ? 'Committed' : stage === 'ready' ? 'Ready to commit' : 'Needs fixes' }}</strong></span
                    >
                    <span
                        >Total: <strong>{{ batch.total_rows }}</strong></span
                    >
                    <span class="text-green-600"
                        >Valid: <strong>{{ batch.valid_rows }}</strong></span
                    >
                    <span class="text-red-500"
                        >Invalid: <strong>{{ batch.invalid_rows }}</strong></span
                    >
                </div>

                <div v-if="stage === 'done'" class="flex items-center gap-3 rounded-md bg-green-50 p-3 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-300">
                    <i class="pi pi-check-circle" />
                    <span>{{ batch.committed_rows }} student(s) imported successfully.</span>
                </div>

                <div v-if="batch.preview?.length" class="flex flex-col gap-2">
                    <span class="text-sm font-medium">Preview (first {{ batch.preview.length }} valid rows)</span>
                    <div class="max-h-56 overflow-auto rounded-md border border-surface-200 dark:border-surface-700">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-surface-100 dark:bg-surface-800">
                                <tr>
                                    <th class="px-2 py-1 text-left">Row</th>
                                    <th class="px-2 py-1 text-left">Action</th>
                                    <th class="px-2 py-1 text-left">Name</th>
                                    <th class="px-2 py-1 text-left">NIS</th>
                                    <th class="px-2 py-1 text-left">NISN</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in batch.preview" :key="row.row" class="border-t border-surface-200 dark:border-surface-700">
                                    <td class="px-2 py-1">{{ row.row }}</td>
                                    <td class="px-2 py-1">{{ row.action === 'update' ? 'Update' : 'Insert' }}</td>
                                    <td class="px-2 py-1">{{ row.name || '-' }}</td>
                                    <td class="px-2 py-1">{{ row.nis || '-' }}</td>
                                    <td class="px-2 py-1">{{ row.nisn || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="batch.errors?.length" class="flex flex-col gap-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-medium text-red-500">Validation errors ({{ batch.errors.length }})</span>
                        <Button label="Download Error Report" icon="pi pi-download" text size="small" :loading="downloading" @click="downloadErrors" />
                    </div>
                    <div class="max-h-56 overflow-auto rounded-md border border-red-200 dark:border-red-800">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-red-50 dark:bg-red-900/30">
                                <tr>
                                    <th class="px-2 py-1 text-left">Row</th>
                                    <th class="px-2 py-1 text-left">Field</th>
                                    <th class="px-2 py-1 text-left">Message</th>
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
                    <small class="text-surface-500"> Nothing is written while any row has an error. Fix the file and upload it again. </small>
                </div>

                <div v-if="stage === 'done'" class="flex flex-wrap gap-2">
                    <Button label="Import Another File" icon="pi pi-upload" severity="secondary" variant="outlined" @click="reset" />
                </div>
            </template>
        </div>

        <template #footer>
            <Button label="Close" severity="secondary" variant="outlined" :disabled="uploading || committing" @click="close" />
            <Button v-if="stage === 'upload'" label="Upload and Preview" icon="pi pi-search" :loading="uploading" :disabled="uploading" @click="upload" />
            <Button v-if="stage === 'ready'" label="Commit Import" icon="pi pi-check" severity="success" :loading="committing" :disabled="committing" @click="commit" />
        </template>
    </Dialog>
</template>
