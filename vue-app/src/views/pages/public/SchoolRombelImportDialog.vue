<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Message from 'primevue/message';
import Select from 'primevue/select';
import apiLogin from '@/apiLogin';

const props = defineProps({
    visible: { type: Boolean, default: false },
    defaultSchoolYearId: { type: [Number, String], default: null },
    unlockedYears: { type: Array, default: () => [] }
});

const emit = defineEmits(['update:visible', 'imported']);

const { t, locale } = useI18n();

const selectedYearId = ref(null);
const file = ref(null);
const fileInput = ref(null);
const fileError = ref('');
const generalError = ref('');
const successMessage = ref('');
const errorDetails = ref([]);
const uploading = ref(false);
const downloading = ref(false);

const reset = () => {
    const preferred =
        props.defaultSchoolYearId && props.unlockedYears.some((y) => y.id === props.defaultSchoolYearId)
            ? props.defaultSchoolYearId
            : (props.unlockedYears[0]?.id ?? null);
    selectedYearId.value = preferred;
    file.value = null;
    fileError.value = '';
    generalError.value = '';
    successMessage.value = '';
    errorDetails.value = [];
    uploading.value = false;
    downloading.value = false;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

watch(
    () => props.visible,
    (val) => {
        if (val) reset();
    }
);

const close = () => {
    emit('update:visible', false);
};

const onFileChange = (e) => {
    file.value = e.target.files?.[0] ?? null;
    fileError.value = '';
    generalError.value = '';
    successMessage.value = '';
    errorDetails.value = [];

    if (!file.value) return;

    const ext = file.value.name.split('.').pop()?.toLowerCase();
    if (!['xlsx', 'xls', 'csv', 'txt'].includes(ext)) {
        fileError.value = t('master.schoolRombels.importExport.fileTypeError');
    }
};

const downloadTemplate = async (format = 'xlsx') => {
    downloading.value = true;
    generalError.value = '';
    try {
        const lang = locale.value === 'en' ? 'en' : 'id';
        const params = { format, lang };
        if (selectedYearId.value) {
            params.school_year_id = selectedYearId.value;
        }
        const response = await apiLogin.get('/api/school-rombels/import/template', {
            params,
            responseType: 'blob'
        });

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        const ext = format === 'csv' ? 'csv' : 'xlsx';
        link.setAttribute('download', `rombongan-belajar-import-template.${ext}`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (err) {
        generalError.value = err.response?.data?.message || 'Gagal mengunduh templat rombongan belajar.';
    } finally {
        downloading.value = false;
    }
};

const submitImport = async () => {
    if (!file.value || uploading.value) return;

    uploading.value = true;
    generalError.value = '';
    successMessage.value = '';
    errorDetails.value = [];

    const formData = new FormData();
    formData.append('file', file.value);
    if (selectedYearId.value) {
        formData.append('school_year_id', selectedYearId.value);
    }

    try {
        const response = await apiLogin.post('/api/school-rombels/import', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        const data = response.data;
        successMessage.value = data.message || 'Rombongan belajar berhasil diimpor.';
        if (Array.isArray(data.errors) && data.errors.length > 0) {
            errorDetails.value = data.errors;
        }

        emit('imported', data);
    } catch (err) {
        generalError.value = err.response?.data?.message || 'Gagal mengimpor rombongan belajar.';
        if (Array.isArray(err.response?.data?.errors)) {
            errorDetails.value = err.response.data.errors;
        }
    } finally {
        uploading.value = false;
    }
};
</script>

<template>
    <Dialog
        :visible="visible"
        @update:visible="emit('update:visible', $event)"
        modal
        :header="t('master.schoolRombels.importExport.dialogTitle')"
        class="w-full max-w-lg"
        :dismissableMask="!uploading"
    >
        <div class="flex flex-col gap-4 py-2">
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-semibold">{{ t('master.schoolRombels.importExport.defaultYearLabel') }}</label>
                <Select
                    v-model="selectedYearId"
                    :options="unlockedYears"
                    optionLabel="name"
                    optionValue="id"
                    :placeholder="t('master.schoolRombels.importExport.selectYearPlaceholder')"
                    showClear
                    class="w-full"
                />
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">{{ t('master.schoolRombels.importExport.fileLabel') }}</label>
                <input
                    ref="fileInput"
                    type="file"
                    accept=".xlsx,.xls,.csv"
                    class="block w-full text-sm text-surface-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100"
                    @change="onFileChange"
                />
                <span v-if="fileError" class="text-xs text-red-500 mt-1 block">{{ fileError }}</span>
                <p class="text-xs text-surface-400 mt-1">
                    {{ t('master.schoolRombels.importExport.fileHint') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-1">
                <Button
                    :label="t('master.schoolRombels.importExport.btnTemplateXlsx')"
                    icon="pi pi-file-excel"
                    severity="success"
                    variant="outlined"
                    size="small"
                    :disabled="downloading"
                    @click="downloadTemplate('xlsx')"
                />
                <Button
                    :label="t('master.schoolRombels.importExport.btnTemplateCsv')"
                    icon="pi pi-download"
                    severity="secondary"
                    variant="outlined"
                    size="small"
                    :disabled="downloading"
                    @click="downloadTemplate('csv')"
                />
            </div>

            <Message v-if="successMessage" severity="success" :closable="false">
                {{ successMessage }}
            </Message>
            <Message v-if="generalError" severity="error" :closable="false">
                {{ generalError }}
            </Message>

            <div
                v-if="errorDetails.length > 0"
                class="max-h-44 overflow-y-auto bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800 p-2.5 rounded text-xs text-red-700 dark:text-red-300 flex flex-col gap-1"
            >
                <div class="font-semibold">{{ t('master.schoolRombels.importExport.errorNotesTitle') }}</div>
                <div v-for="(e, i) in errorDetails" :key="i">• {{ e }}</div>
            </div>
        </div>

        <template #footer>
            <Button :label="t('common.action.cancel')" severity="secondary" variant="text" size="small" :disabled="uploading" @click="close" />
            <Button
                :label="t('master.schoolRombels.importExport.btnUpload')"
                icon="pi pi-check"
                severity="primary"
                size="small"
                :loading="uploading"
                :disabled="!file || !!fileError || uploading"
                @click="submitImport"
            />
        </template>
    </Dialog>
</template>
