<script setup>
import { computed, onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import apiLogin from '@/apiLogin';
import { authStore } from '@/stores/authStore';

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
            summary: 'Berkas Belum Dipilih',
            detail: 'Silakan pilih berkas CSV ekspor Dapodik terlebih dahulu.',
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
            summary: 'Rekonsiliasi Selesai',
            detail: `Berhasil membandingkan ${result.value?.summary?.total_dapodik_rows || 0} baris Dapodik dengan database sekolah.`,
            life: 4000
        });
    } catch (err) {
        const msg = err.response?.data?.message || err.response?.data?.errors?.file?.[0] || 'Gagal memproses rekonsiliasi Dapodik.';
        toast.add({
            severity: 'error',
            summary: 'Gagal Rekonsiliasi',
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
            summary: 'Unduh Berhasil',
            detail: 'Berkas audit rekonsiliasi Dapodik berhasil diunduh.',
            life: 3000
        });
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Gagal Unduh',
            detail: 'Terjadi kesalahan saat mengekspor hasil audit rekonsiliasi.',
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
            return { severity: 'success', label: 'Cocok Identik' };
        case 'MATCHED_DIFF':
            return { severity: 'warn', label: 'Beda Data' };
        case 'LOCAL_ONLY':
            return { severity: 'info', label: 'Hanya Lokal' };
        case 'DAPODIK_ONLY':
            return { severity: 'secondary', label: 'Hanya Dapodik' };
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
                    <h5 class="font-semibold mb-1">Akses Terbatas: Hanya Operator & Administrator</h5>
                    <p class="text-sm">Anda memiliki hak akses melihat laporan sekolah, namun proses unggah dan rekonsiliasi data dengan eksternal Dapodik memerlukan wewenang pengelola data kesiswaan (<code>students.import</code>).</p>
                </div>
            </div>
        </div>

        <!-- Zona Unggah & Konfigurasi -->
        <div v-else-if="!result" class="card p-6 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none">
            <h4 class="font-semibold text-lg text-surface-900 dark:text-surface-0 mb-2">Unggah Berkas CSV Ekspor Dapodik</h4>
            <p class="text-surface-500 text-sm mb-6">Pilih atau seret berkas CSV hasil unduhan aplikasi Dapodik Kemendikbudristek untuk membandingkan status rombel, NIK, dan NISN siswa lokal secara otomatis (DEC-43, DEC-12).</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                <div>
                    <label class="block text-sm font-medium text-surface-700 dark:text-surface-200 mb-2">Tahun Ajaran Pembanding:</label>
                    <Select v-model="selectedSchoolYearId" :options="schoolYears" optionLabel="name" optionValue="id" placeholder="Tahun Ajaran Aktif (Default)" class="w-full" />
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-surface-700 dark:text-surface-200 mb-2">Pilih Berkas CSV / TXT (Maks. 5 MB):</label>
                    <div class="flex flex-col sm:flex-row items-center gap-3">
                        <input
                            type="file"
                            accept=".csv,.txt"
                            class="block w-full text-sm text-surface-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-surface-700 dark:file:text-surface-100"
                            @change="onFileSelect"
                        />
                        <Button icon="pi pi-sync" label="Mulai Rekonsiliasi" severity="primary" :loading="analyzing" :disabled="!uploadedFile" class="whitespace-nowrap" @click="runReconciliation" />
                    </div>
                </div>
            </div>

            <div class="mt-6 p-4 rounded-xl bg-surface-50 dark:bg-surface-800 border border-surface-200 dark:border-surface-700 text-xs text-surface-600 dark:text-surface-400">
                <div class="font-semibold mb-1 text-surface-800 dark:text-surface-200 flex items-center gap-2"><i class="pi pi-info-circle"></i> Format Berkas & Mekanisme Pencocokan:</div>
                <ul class="list-disc pl-5 space-y-1 mt-1">
                    <li>Mendukung pemisah koma (<code>,</code>), titik-koma (<code>;</code>), atau tab secara otomatis.</li>
                    <li>Kolom yang dibaca: <code>nisn</code>, <code>nik</code>, <code>nama</code>, <code>rombel</code> (case-insensitive).</li>
                    <li>Sistem menggunakan <em>two-pass matching</em>: pertama dicocokkan berdasarkan <strong>NISN</strong>, jika belum cocok dilanjutkan pencocokan berdasarkan <strong>NIK</strong>.</li>
                    <li>Tidak ada perubahan data otomatis ke database lokal — hasil berupa visualisasi komparasi dan rekomendasi audit.</li>
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
                        <div class="text-xs text-surface-500">{{ result.summary.total_dapodik_rows }} baris Dapodik · {{ result.summary.school_year?.name || 'Tahun Aktif' }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button icon="pi pi-download" label="Unduh Audit Rekonsiliasi (CSV)" severity="primary" size="small" :loading="exporting" @click="exportAuditCsv" />
                    <Button icon="pi pi-arrow-left" label="Unggah Berkas Baru" severity="secondary" outlined size="small" @click="clearFile" />
                </div>
            </div>

            <!-- KPI Cards Hasil Komparasi -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="card p-3 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none text-center">
                    <div class="text-xs text-surface-500 font-medium">Data Dapodik</div>
                    <div class="text-xl font-bold mt-1 text-surface-900 dark:text-surface-0">{{ result.summary.total_dapodik_rows }}</div>
                </div>

                <div class="card p-3 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none text-center">
                    <div class="text-xs text-surface-500 font-medium">Siswa Lokal</div>
                    <div class="text-xl font-bold mt-1 text-surface-900 dark:text-surface-0">{{ result.summary.total_local_students }}</div>
                </div>

                <div
                    class="card p-3 rounded-xl border border-emerald-200 dark:border-emerald-800/50 bg-emerald-50/50 dark:bg-emerald-950/20 mb-0 shadow-none text-center cursor-pointer transition-all hover:ring-2 hover:ring-emerald-500"
                    :class="{ 'ring-2 ring-emerald-500 font-bold': activeFilter === 'MATCHED_IDENTICAL' }"
                    @click="activeFilter = activeFilter === 'MATCHED_IDENTICAL' ? 'ALL' : 'MATCHED_IDENTICAL'"
                >
                    <div class="text-xs text-emerald-700 dark:text-emerald-300 font-medium">Cocok Identik</div>
                    <div class="text-xl font-bold mt-1 text-emerald-700 dark:text-emerald-300">{{ result.summary.total_identical }}</div>
                </div>

                <div
                    class="card p-3 rounded-xl border border-amber-200 dark:border-amber-800/50 bg-amber-50/50 dark:bg-amber-950/20 mb-0 shadow-none text-center cursor-pointer transition-all hover:ring-2 hover:ring-amber-500"
                    :class="{ 'ring-2 ring-amber-500 font-bold': activeFilter === 'MATCHED_DIFF' }"
                    @click="activeFilter = activeFilter === 'MATCHED_DIFF' ? 'ALL' : 'MATCHED_DIFF'"
                >
                    <div class="text-xs text-amber-700 dark:text-amber-300 font-medium">Beda Data</div>
                    <div class="text-xl font-bold mt-1 text-amber-700 dark:text-amber-300">{{ result.summary.total_diff }}</div>
                </div>

                <div
                    class="card p-3 rounded-xl border border-blue-200 dark:border-blue-800/50 bg-blue-50/50 dark:bg-blue-950/20 mb-0 shadow-none text-center cursor-pointer transition-all hover:ring-2 hover:ring-blue-500"
                    :class="{ 'ring-2 ring-blue-500 font-bold': activeFilter === 'LOCAL_ONLY' }"
                    @click="activeFilter = activeFilter === 'LOCAL_ONLY' ? 'ALL' : 'LOCAL_ONLY'"
                >
                    <div class="text-xs text-blue-700 dark:text-blue-300 font-medium">Hanya di Lokal</div>
                    <div class="text-xl font-bold mt-1 text-blue-700 dark:text-blue-300">{{ result.summary.total_local_only }}</div>
                </div>

                <div
                    class="card p-3 rounded-xl border border-purple-200 dark:border-purple-800/50 bg-purple-50/50 dark:bg-purple-950/20 mb-0 shadow-none text-center cursor-pointer transition-all hover:ring-2 hover:ring-purple-500"
                    :class="{ 'ring-2 ring-purple-500 font-bold': activeFilter === 'DAPODIK_ONLY' }"
                    @click="activeFilter = activeFilter === 'DAPODIK_ONLY' ? 'ALL' : 'DAPODIK_ONLY'"
                >
                    <div class="text-xs text-purple-700 dark:text-purple-300 font-medium">Hanya di Dapodik</div>
                    <div class="text-xl font-bold mt-1 text-purple-700 dark:text-purple-300">{{ result.summary.total_dapodik_only }}</div>
                </div>
            </div>

            <!-- Filter & Pencarian Bar -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div class="flex flex-wrap gap-2">
                    <Button size="small" :severity="activeFilter === 'ALL' ? 'primary' : 'secondary'" :outlined="activeFilter !== 'ALL'" label="Semua Status" @click="activeFilter = 'ALL'" />
                    <Button size="small" :severity="activeFilter === 'MATCHED_DIFF' ? 'warn' : 'secondary'" :outlined="activeFilter !== 'MATCHED_DIFF'" :label="`Beda Data (${result.summary.total_diff})`" @click="activeFilter = 'MATCHED_DIFF'" />
                    <Button size="small" :severity="activeFilter === 'LOCAL_ONLY' ? 'info' : 'secondary'" :outlined="activeFilter !== 'LOCAL_ONLY'" :label="`Hanya Lokal (${result.summary.total_local_only})`" @click="activeFilter = 'LOCAL_ONLY'" />
                    <Button
                        size="small"
                        :severity="activeFilter === 'DAPODIK_ONLY' ? 'secondary' : 'secondary'"
                        :outlined="activeFilter !== 'DAPODIK_ONLY'"
                        :label="`Hanya Dapodik (${result.summary.total_dapodik_only})`"
                        @click="activeFilter = 'DAPODIK_ONLY'"
                    />
                    <Button
                        size="small"
                        :severity="activeFilter === 'MATCHED_IDENTICAL' ? 'success' : 'secondary'"
                        :outlined="activeFilter !== 'MATCHED_IDENTICAL'"
                        :label="`Cocok Identik (${result.summary.total_identical})`"
                        @click="activeFilter = 'MATCHED_IDENTICAL'"
                    />
                </div>

                <div class="w-full sm:w-72">
                    <IconField iconPosition="left">
                        <InputIcon class="pi pi-search" />
                        <InputText v-model="searchQuery" placeholder="Cari nama, NISN, NIK, rombel..." class="w-full text-sm" />
                    </IconField>
                </div>
            </div>

            <!-- Tabel Komparasi Rekonsiliasi -->
            <div class="card p-5 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none">
                <DataTable :value="filteredItems" stripedRows responsiveLayout="scroll" paginator :rows="10" :rowsPerPageOptions="[10, 25, 50]" class="p-datatable-sm">
                    <Column header="Status" style="width: 130px">
                        <template #body="{ data }">
                            <Tag :severity="getStatusBadge(data.status).severity" :value="getStatusBadge(data.status).label" />
                        </template>
                    </Column>

                    <Column header="Nama Siswa">
                        <template #body="{ data }">
                            <div class="flex flex-col text-xs">
                                <div>
                                    <span class="text-surface-400 font-semibold">Lokal:</span>
                                    <span class="ml-1 font-medium text-surface-900 dark:text-surface-100">{{ data.local_name }}</span>
                                </div>
                                <div class="mt-0.5">
                                    <span class="text-surface-400 font-semibold">Dapodik:</span>
                                    <span class="ml-1" :class="{ 'bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-200 px-1 rounded font-medium': data.differences.some((d) => d.field === 'name') }">
                                        {{ data.dapodik_name }}
                                    </span>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column header="NISN & NIK">
                        <template #body="{ data }">
                            <div class="flex flex-col text-xs space-y-0.5">
                                <div>
                                    <span class="text-surface-400 font-mono">NISN:</span>
                                    <span class="ml-1 font-mono">{{ data.local_nisn !== '-' ? data.local_nisn : data.dapodik_nisn }}</span>
                                </div>
                                <div>
                                    <span class="text-surface-400 font-mono">NIK:</span>
                                    <span class="ml-1 font-mono">{{ data.local_nik !== '-' ? data.local_nik : data.dapodik_nik }}</span>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column header="Rombongan Belajar">
                        <template #body="{ data }">
                            <div class="flex flex-col text-xs">
                                <div>
                                    <span class="text-surface-400">Lokal:</span>
                                    <span class="ml-1 font-medium">{{ data.local_rombel }}</span>
                                </div>
                                <div class="mt-0.5">
                                    <span class="text-surface-400">Dapodik:</span>
                                    <span class="ml-1" :class="{ 'bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-200 px-1 rounded font-medium': data.differences.some((d) => d.field === 'rombel') }">
                                        {{ data.dapodik_rombel }}
                                    </span>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column header="Rincian Diskrepansi">
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
                            <span v-else class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Sesuai Penuh</span>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </div>
</template>
