<script setup>
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from 'primevue/usetoast';
import apiLogin from '@/apiLogin';
import { authStore } from '@/stores/authStore';

const { t } = useI18n();
const toast = useToast();
const store = authStore();

const canImport = computed(() => store.can('students.import'));

const selectedSchoolYearId = ref(null);
const schoolYears = ref([]);
const uploadedFile = ref(null);
const analyzing = ref(false);
const exporting = ref(false);
const result = ref(null);

const activeFilter = ref('ALL');
const searchQuery = ref('');

const loadSchoolYears = async () => {
    try {
        const res = await apiLogin.get('/api/school-years');
        const raw = res.data?.data || res.data || [];
        schoolYears.value = Array.isArray(raw) ? raw : [];
    } catch {
        schoolYears.value = [];
    }
};

const onFileSelect = (event) => {
    const files = event.files || (event.target && event.target.files);
    if (files && files.length > 0) {
        uploadedFile.value = files[0];
    }
};

const clearFile = () => {
    uploadedFile.value = null;
    result.value = null;
    activeFilter.value = 'ALL';
    searchQuery.value = '';
};

const runReconciliation = async () => {
    if (!uploadedFile.value) {
        toast.add({
            severity: 'warn',
            summary: t('reports.dapodik.toast.fileNotSelectedTitle'),
            detail: t('reports.dapodik.toast.fileNotSelectedDetail'),
            life: 3000
        });
        return;
    }

    analyzing.value = true;
    try {
        const formData = new FormData();
        formData.append('file', uploadedFile.value);
        if (selectedSchoolYearId.value) {
            formData.append('school_year_id', selectedSchoolYearId.value);
        }

        const response = await apiLogin.post('/api/reports/dapodik-reconciliation/compare', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        result.value = response.data?.data || null;

        toast.add({
            severity: 'success',
            summary: t('reports.dapodik.toast.reconcileSuccessTitle'),
            detail: t('reports.dapodik.toast.reconcileSuccessDetail', { count: result.value?.summary?.total_dapodik_rows || 0 }),
            life: 4000
        });
    } catch (err) {
        const msg = err.response?.data?.message || err.response?.data?.errors?.file?.[0] || 'Gagal memproses rekonsiliasi Dapodik.';
        toast.add({
            severity: 'error',
            summary: t('reports.dapodik.toast.reconcileFailedTitle'),
            detail: msg,
            life: 5000
        });
    } finally {
        analyzing.value = false;
    }
};

const exportAuditCsv = async () => {
    if (!uploadedFile.value) return;

    exporting.value = true;
    try {
        const formData = new FormData();
        formData.append('file', uploadedFile.value);
        if (selectedSchoolYearId.value) {
            formData.append('school_year_id', selectedSchoolYearId.value);
        }

        const response = await apiLogin.post('/api/reports/dapodik-reconciliation/export', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
            responseType: 'blob'
        });

        const url = window.URL.createObjectURL(new Blob([response.data], { type: 'text/csv;charset=utf-8;' }));
        const link = document.createElement('a');
        link.href = url;
        const nowStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
        link.setAttribute('download', `audit_rekonsiliasi_dapodik_${nowStr}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        toast.add({
            severity: 'success',
            summary: t('reports.dapodik.toast.downloadSuccessTitle'),
            detail: t('reports.dapodik.toast.downloadSuccessDetail'),
            life: 3000
        });
    } catch {
        toast.add({
            severity: 'error',
            summary: t('reports.dapodik.toast.downloadFailedTitle'),
            detail: t('reports.dapodik.toast.downloadFailedDetail'),
            life: 5000
        });
    } finally {
        exporting.value = false;
    }
};

const filteredItems = computed(() => {
    if (!result.value?.items) return [];

    let items = result.value.items;

    if (activeFilter.value !== 'ALL') {
        items = items.filter((item) => item.status === activeFilter.value);
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        items = items.filter((item) => {
            return (
                (item.local_name && item.local_name.toLowerCase().includes(q)) ||
                (item.dapodik_name && item.dapodik_name.toLowerCase().includes(q)) ||
                (item.local_nisn && item.local_nisn.includes(q)) ||
                (item.dapodik_nisn && item.dapodik_nisn.includes(q)) ||
                (item.local_nik && item.local_nik.includes(q)) ||
                (item.dapodik_nik && item.dapodik_nik.includes(q)) ||
                (item.local_rombel && item.local_rombel.toLowerCase().includes(q)) ||
                (item.dapodik_rombel && item.dapodik_rombel.toLowerCase().includes(q))
            );
        });
    }

    return items;
});

const getStatusBadge = (status) => {
    switch (status) {
        case 'MATCHED_IDENTICAL':
            return { severity: 'success', label: t('reports.dapodik.results.identicalMatch') };
        case 'MATCHED_DIFF':
            return { severity: 'warn', label: t('reports.dapodik.results.diffData') };
        case 'LOCAL_ONLY':
            return { severity: 'info', label: t('reports.dapodik.results.localOnly') };
        case 'DAPODIK_ONLY':
            return { severity: 'secondary', label: t('reports.dapodik.results.dapodikOnly') };
        default:
            return { severity: 'secondary', label: status };
    }
};

onMounted(() => {
    loadSchoolYears();
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Akses Dibatasi jika tidak memiliki students.import -->
        <div v-if="!canImport" class="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200">
            <div class="flex items-center gap-3">
                <i class="pi pi-lock text-xl"></i>
                <div>
                    <h5 class="font-semibold mb-1">{{ t('reports.dapodik.restricted.title') }}</h5>
                    <p class="text-sm">
                        {{ t('reports.dapodik.restricted.desc', { ability: 'students.import' }) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Zona Unggah & Konfigurasi -->
        <div v-else-if="!result" class="card p-6 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none">
            <h4 class="font-semibold text-lg text-surface-900 dark:text-surface-0 mb-2">{{ t('reports.dapodik.upload.title') }}</h4>
            <p class="text-surface-500 text-sm mb-6">{{ t('reports.dapodik.upload.subtitle') }}</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                <div>
                    <label class="block text-sm font-medium text-surface-700 dark:text-surface-200 mb-2">{{ t('reports.dapodik.upload.compareYearLabel') }}</label>
                    <Select v-model="selectedSchoolYearId" :options="schoolYears" optionLabel="name" optionValue="id" :placeholder="t('reports.dapodik.upload.activeYearDefault')" class="w-full" />
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-surface-700 dark:text-surface-200 mb-2">{{ t('reports.dapodik.upload.fileLabel') }}</label>
                    <div class="flex flex-col sm:flex-row items-center gap-3">
                        <input
                            type="file"
                            accept=".csv,.txt"
                            class="block w-full text-sm text-surface-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-surface-700 dark:file:text-surface-100"
                            @change="onFileSelect"
                        />
                        <Button
                            icon="pi pi-sync"
                            :label="analyzing ? t('reports.dapodik.upload.processingButton') : t('reports.dapodik.upload.startButton')"
                            severity="primary"
                            :loading="analyzing"
                            :disabled="!uploadedFile"
                            class="whitespace-nowrap"
                            @click="runReconciliation"
                        />
                    </div>
                </div>
            </div>

            <div class="mt-6 p-4 rounded-xl bg-surface-50 dark:bg-surface-800 border border-surface-200 dark:border-surface-700 text-xs text-surface-600 dark:text-surface-400">
                <div class="font-semibold mb-1 text-surface-800 dark:text-surface-200 flex items-center gap-2"><i class="pi pi-info-circle"></i> {{ t('reports.dapodik.upload.formatInfoTitle') }}</div>
                <ul class="list-disc pl-5 space-y-1 mt-1">
                    <li>{{ t('reports.dapodik.upload.delimiterInfo', { comma: ',', semicolon: ';' }) }}</li>
                    <li>{{ t('reports.dapodik.upload.columnsRead', { columns: 'nisn, nik, nama, rombel' }) }}</li>
                    <li>{{ t('reports.dapodik.upload.twoPassMatching') }}</li>
                    <li>{{ t('reports.dapodik.upload.noAutoMutation') }}</li>
                </ul>
            </div>
        </div>

        <!-- Tampilan Hasil Rekonsiliasi -->
        <div v-if="result" class="flex flex-col gap-6">
            <!-- Toolbar Atas -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-surface-50 dark:bg-surface-800 p-4 rounded-xl border border-surface-200 dark:border-surface-700">
                <div class="flex items-center gap-2">
                    <i class="pi pi-file-excel text-emerald-600 text-xl"></i>
                    <div>
                        <div class="font-bold text-surface-900 dark:text-surface-0">{{ uploadedFile?.name }}</div>
                        <div class="text-xs text-surface-500">
                            {{ t('reports.dapodik.results.summarySub', { count: result.summary.total_dapodik_rows, year: result.summary.school_year?.name || t('reports.dapodik.results.activeYear') }) }}
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button icon="pi pi-download" :label="t('reports.dapodik.results.downloadAudit')" severity="primary" size="small" :loading="exporting" @click="exportAuditCsv" />
                    <Button icon="pi pi-arrow-left" :label="t('reports.dapodik.results.uploadNew')" severity="secondary" outlined size="small" @click="clearFile" />
                </div>
            </div>

            <!-- KPI Cards Hasil Komparasi -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="card p-3 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none text-center">
                    <div class="text-xs text-surface-500 font-medium">{{ t('reports.dapodik.results.dapodikData') }}</div>
                    <div class="text-xl font-bold mt-1 text-surface-900 dark:text-surface-0">{{ result.summary.total_dapodik_rows }}</div>
                </div>

                <div class="card p-3 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none text-center">
                    <div class="text-xs text-surface-500 font-medium">{{ t('reports.dapodik.results.localStudents') }}</div>
                    <div class="text-xl font-bold mt-1 text-surface-900 dark:text-surface-0">{{ result.summary.total_local_students }}</div>
                </div>

                <div
                    class="card p-3 rounded-xl border border-emerald-200 dark:border-emerald-800/50 bg-emerald-50/50 dark:bg-emerald-950/20 mb-0 shadow-none text-center cursor-pointer transition-all hover:ring-2 hover:ring-emerald-500"
                    :class="{ 'ring-2 ring-emerald-500 font-bold': activeFilter === 'MATCHED_IDENTICAL' }"
                    @click="activeFilter = activeFilter === 'MATCHED_IDENTICAL' ? 'ALL' : 'MATCHED_IDENTICAL'"
                >
                    <div class="text-xs text-emerald-700 dark:text-emerald-300 font-medium">{{ t('reports.dapodik.results.identicalMatch') }}</div>
                    <div class="text-xl font-bold mt-1 text-emerald-700 dark:text-emerald-300">{{ result.summary.total_identical }}</div>
                </div>

                <div
                    class="card p-3 rounded-xl border border-amber-200 dark:border-amber-800/50 bg-amber-50/50 dark:bg-amber-950/20 mb-0 shadow-none text-center cursor-pointer transition-all hover:ring-2 hover:ring-amber-500"
                    :class="{ 'ring-2 ring-amber-500 font-bold': activeFilter === 'MATCHED_DIFF' }"
                    @click="activeFilter = activeFilter === 'MATCHED_DIFF' ? 'ALL' : 'MATCHED_DIFF'"
                >
                    <div class="text-xs text-amber-700 dark:text-amber-300 font-medium">{{ t('reports.dapodik.results.diffData') }}</div>
                    <div class="text-xl font-bold mt-1 text-amber-700 dark:text-amber-300">{{ result.summary.total_diff }}</div>
                </div>

                <div
                    class="card p-3 rounded-xl border border-blue-200 dark:border-blue-800/50 bg-blue-50/50 dark:bg-blue-950/20 mb-0 shadow-none text-center cursor-pointer transition-all hover:ring-2 hover:ring-blue-500"
                    :class="{ 'ring-2 ring-blue-500 font-bold': activeFilter === 'LOCAL_ONLY' }"
                    @click="activeFilter = activeFilter === 'LOCAL_ONLY' ? 'ALL' : 'LOCAL_ONLY'"
                >
                    <div class="text-xs text-blue-700 dark:text-blue-300 font-medium">{{ t('reports.dapodik.results.localOnly') }}</div>
                    <div class="text-xl font-bold mt-1 text-blue-700 dark:text-blue-300">{{ result.summary.total_local_only }}</div>
                </div>

                <div
                    class="card p-3 rounded-xl border border-purple-200 dark:border-purple-800/50 bg-purple-50/50 dark:bg-purple-950/20 mb-0 shadow-none text-center cursor-pointer transition-all hover:ring-2 hover:ring-purple-500"
                    :class="{ 'ring-2 ring-purple-500 font-bold': activeFilter === 'DAPODIK_ONLY' }"
                    @click="activeFilter = activeFilter === 'DAPODIK_ONLY' ? 'ALL' : 'DAPODIK_ONLY'"
                >
                    <div class="text-xs text-purple-700 dark:text-purple-300 font-medium">{{ t('reports.dapodik.results.dapodikOnly') }}</div>
                    <div class="text-xl font-bold mt-1 text-purple-700 dark:text-purple-300">{{ result.summary.total_dapodik_only }}</div>
                </div>
            </div>

            <!-- Filter & Pencarian Bar -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div class="flex flex-wrap gap-2">
                    <Button size="small" :severity="activeFilter === 'ALL' ? 'primary' : 'secondary'" :outlined="activeFilter !== 'ALL'" :label="t('reports.dapodik.results.filterAll')" @click="activeFilter = 'ALL'" />
                    <Button
                        size="small"
                        :severity="activeFilter === 'MATCHED_DIFF' ? 'warn' : 'secondary'"
                        :outlined="activeFilter !== 'MATCHED_DIFF'"
                        :label="t('reports.dapodik.results.filterDiff', { count: result.summary.total_diff })"
                        @click="activeFilter = 'MATCHED_DIFF'"
                    />
                    <Button
                        size="small"
                        :severity="activeFilter === 'LOCAL_ONLY' ? 'info' : 'secondary'"
                        :outlined="activeFilter !== 'LOCAL_ONLY'"
                        :label="t('reports.dapodik.results.filterLocalOnly', { count: result.summary.total_local_only })"
                        @click="activeFilter = 'LOCAL_ONLY'"
                    />
                    <Button
                        size="small"
                        :severity="activeFilter === 'DAPODIK_ONLY' ? 'secondary' : 'secondary'"
                        :outlined="activeFilter !== 'DAPODIK_ONLY'"
                        :label="t('reports.dapodik.results.filterDapodikOnly', { count: result.summary.total_dapodik_only })"
                        @click="activeFilter = 'DAPODIK_ONLY'"
                    />
                    <Button
                        size="small"
                        :severity="activeFilter === 'MATCHED_IDENTICAL' ? 'success' : 'secondary'"
                        :outlined="activeFilter !== 'MATCHED_IDENTICAL'"
                        :label="t('reports.dapodik.results.filterIdentical', { count: result.summary.total_identical })"
                        @click="activeFilter = 'MATCHED_IDENTICAL'"
                    />
                </div>

                <div class="w-full sm:w-72">
                    <IconField iconPosition="left">
                        <InputIcon class="pi pi-search" />
                        <InputText v-model="searchQuery" :placeholder="t('reports.dapodik.table.searchPlaceholder')" class="w-full text-sm" />
                    </IconField>
                </div>
            </div>

            <!-- Tabel Komparasi Rekonsiliasi -->
            <div class="card p-5 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none">
                <DataTable :value="filteredItems" stripedRows responsiveLayout="scroll" paginator :rows="10" :rowsPerPageOptions="[10, 25, 50]" class="p-datatable-sm">
                    <Column :header="t('reports.dapodik.table.statusCol')" style="width: 130px">
                        <template #body="{ data }">
                            <Tag :severity="getStatusBadge(data.status).severity" :value="getStatusBadge(data.status).label" />
                        </template>
                    </Column>

                    <Column :header="t('reports.dapodik.table.studentNameCol')">
                        <template #body="{ data }">
                            <div class="flex flex-col text-xs">
                                <div>
                                    <span class="text-surface-400 font-semibold">{{ t('reports.dapodik.table.localPrefix') }}</span>
                                    <span class="ml-1 font-medium text-surface-900 dark:text-surface-100">{{ data.local_name }}</span>
                                </div>
                                <div class="mt-0.5">
                                    <span class="text-surface-400 font-semibold">{{ t('reports.dapodik.table.dapodikPrefix') }}</span>
                                    <span class="ml-1" :class="{ 'bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-200 px-1 rounded font-medium': data.differences.some((d) => d.field === 'name') }">
                                        {{ data.dapodik_name }}
                                    </span>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column :header="t('reports.dapodik.table.nisnNikCol')">
                        <template #body="{ data }">
                            <div class="flex flex-col text-xs space-y-0.5">
                                <div>
                                    <span class="text-surface-400 font-mono">{{ t('reports.dapodik.table.nisnPrefix') }}</span>
                                    <span class="ml-1 font-mono">{{ data.local_nisn !== '-' ? data.local_nisn : data.dapodik_nisn }}</span>
                                </div>
                                <div>
                                    <span class="text-surface-400 font-mono">{{ t('reports.dapodik.table.nikPrefix') }}</span>
                                    <span class="ml-1 font-mono">{{ data.local_nik !== '-' ? data.local_nik : data.dapodik_nik }}</span>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column :header="t('reports.dapodik.table.rombelCol')">
                        <template #body="{ data }">
                            <div class="flex flex-col text-xs">
                                <div>
                                    <span class="text-surface-400">{{ t('reports.dapodik.table.localPrefix') }}</span>
                                    <span class="ml-1 font-medium">{{ data.local_rombel }}</span>
                                </div>
                                <div class="mt-0.5">
                                    <span class="text-surface-400">{{ t('reports.dapodik.table.dapodikPrefix') }}</span>
                                    <span class="ml-1" :class="{ 'bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-200 px-1 rounded font-medium': data.differences.some((d) => d.field === 'rombel') }">
                                        {{ data.dapodik_rombel }}
                                    </span>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column :header="t('reports.dapodik.table.discrepancyCol')">
                        <template #body="{ data }">
                            <div v-if="data.differences && data.differences.length" class="flex flex-wrap gap-1">
                                <span
                                    v-for="(diff, idx) in data.differences"
                                    :key="idx"
                                    class="text-[11px] px-1.5 py-0.5 rounded bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-300 border border-surface-200 dark:border-surface-700"
                                >
                                    {{ diff.label }}: {{ diff.local }} &rarr; {{ diff.dapodik }}
                                </span>
                            </div>
                            <span v-else class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">{{ t('reports.dapodik.table.fullyMatched') }}</span>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </div>
</template>
