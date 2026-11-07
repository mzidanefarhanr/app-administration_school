<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Message from 'primevue/message';
import apiLogin from '@/apiLogin';

const props = defineProps({
    visible: { type: Boolean, default: false },
    schoolYearId: { type: [Number, String], default: null }
});

const emit = defineEmits(['update:visible', 'imported']);

const { t, locale } = useI18n();

const file = ref(null);
const fileInput = ref(null);
const fileError = ref('');
const generalError = ref('');
const successMessage = ref('');
const errorDetails = ref([]);
const uploading = ref(false);
const downloading = ref(false);

const reset = () => {
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
        fileError.value = 'Hanya berkas Excel (.xlsx, .xls) atau CSV yang didukung.';
    }
};

const downloadTemplate = async (format = 'xlsx') => {
    downloading.value = true;
    try {
        const lang = locale.value === 'id' ? 'id' : 'en';
        const response = await apiLogin.get('/api/academic/mutations/import/template', {
            params: { format, lang },
            responseType: 'blob'
        });

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        const ext = format === 'csv' ? 'csv' : 'xlsx';
        link.setAttribute('download', `mutations-import-template.${ext}`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch {
        alert('Gagal mengunduh templat.');
    } finally {
        downloading.value = false;
    }
};

const submitImport = async () => {
    if (!file.value || uploading.value) return;

    if (!props.schoolYearId) {
        generalError.value = 'Tahun ajaran belum dipilih.';
        return;
    }

    uploading.value = true;
    generalError.value = '';
    successMessage.value = '';
    errorDetails.value = [];

    const formData = new FormData();
    formData.append('file', file.value);
    formData.append('school_year_id', props.schoolYearId);

    try {
        const response = await apiLogin.post('/api/academic/mutations/import', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        const data = response.data;
        successMessage.value = data.message || 'Mutasi siswa berhasil diimpor.';
        if (data.errors && data.errors.length > 0) {
            errorDetails.value = data.errors;
        }

        emit('imported');
    } catch (err) {
        generalError.value = err.response?.data?.message || 'Gagal mengimpor mutasi siswa.';
        if (err.response?.data?.errors && Array.isArray(err.response.data.errors)) {
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
        header="Impor Mutasi Siswa"
        class="w-full max-w-lg"
        :dismissableMask="true"
    >
        <div class="flex flex-col gap-4 py-2">
            <div>
                <label class="block text-sm font-semibold mb-1">Berkas Excel atau CSV</label>
                <input
                    ref="fileInput"
                    type="file"
                    accept=".xlsx,.xls,.csv"
                    class="block w-full text-sm text-surface-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100"
                    @change="onFileChange"
                />
                <span v-if="fileError" class="text-xs text-red-500 mt-1 block">{{ fileError }}</span>
                <p class="text-xs text-surface-400 mt-1">
                    Gunakan template resmi untuk data mutasi masuk & keluar. Mendukung format tanggal Excel (M/D/YYYY).
                </p>
            </div>

            <!-- Download Template Buttons -->
            <div class="flex items-center gap-3 pt-1">
                <Button
                    label="Unduh Templat Excel (.xlsx)"
                    icon="pi pi-file-excel"
                    severity="success"
                    variant="outlined"
                    size="small"
                    :disabled="downloading"
                    @click="downloadTemplate('xlsx')"
                />
                <Button
                    label="Unduh Templat CSV"
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

            <div v-if="errorDetails.length > 0" class="max-h-40 overflow-y-auto bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800 p-2 rounded text-xs text-red-700 dark:text-red-300 flex flex-col gap-1">
                <div class="font-semibold">Catatan baris yang perlu diperiksa:</div>
                <div v-for="(e, i) in errorDetails" :key="i">• {{ e }}</div>
            </div>
        </div>

        <template #footer>
            <Button label="Batal" severity="secondary" variant="text" size="small" @click="close" />
            <Button
                label="Unggah dan Proses"
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
