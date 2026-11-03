<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import apiLogin from '@/apiLogin';
import { useLayout } from '@/layout/composables/layout';

const toast = useToast();
const { isDarkTheme } = useLayout();

const limitYears = ref(5);
const limitOptions = [
    { label: '3 Tahun Terakhir', value: 3 },
    { label: '5 Tahun Terakhir', value: 5 },
    { label: '10 Tahun Terakhir', value: 10 }
];

const loading = ref(false);
const exporting = ref(false);
const error = ref('');
const trends = ref(null);

const studentChartData = ref(null);
const studentChartOptions = ref(null);
const mutationChartData = ref(null);
const mutationChartOptions = ref(null);

const latestYear = computed(() => {
    if (!trends.value?.years || trends.value.years.length === 0) return null;
    return trends.value.years[trends.value.years.length - 1];
});

const loadTrends = async () => {
    loading.value = true;
    error.value = '';
    try {
        const response = await apiLogin.get('/api/reports/school-statistics', {
            params: { limit_years: limitYears.value }
        });
        trends.value = response.data?.data || null;
        buildCharts();
    } catch (err) {
        error.value = err.response?.data?.message || 'Gagal memuat data statistik sekolah.';
        toast.add({
            severity: 'error',
            summary: 'Kesalahan',
            detail: error.value,
            life: 5000
        });
    } finally {
        loading.value = false;
    }
};

const buildCharts = () => {
    if (!trends.value?.charts?.labels?.length) {
        studentChartData.value = null;
        mutationChartData.value = null;
        return;
    }

    const docStyle = getComputedStyle(document.documentElement);
    const textColor = docStyle.getPropertyValue('--text-color') || '#495057';
    const textColorSecondary = docStyle.getPropertyValue('--text-color-secondary') || '#6c757d';
    const surfaceBorder = docStyle.getPropertyValue('--surface-border') || '#dfe7ef';

    const labels = trends.value.charts.labels;

    // Chart 1: Siswa L, P, Total
    studentChartData.value = {
        labels,
        datasets: [
            {
                label: 'Laki-Laki',
                backgroundColor: '#3B82F6',
                borderColor: '#3B82F6',
                borderRadius: 4,
                data: trends.value.charts.students.male
            },
            {
                label: 'Perempuan',
                backgroundColor: '#EC4899',
                borderColor: '#EC4899',
                borderRadius: 4,
                data: trends.value.charts.students.female
            },
            {
                type: 'line',
                label: 'Total Siswa',
                borderColor: '#10B981',
                backgroundColor: '#10B981',
                borderWidth: 2,
                fill: false,
                tension: 0.3,
                data: trends.value.charts.students.total
            }
        ]
    };

    studentChartOptions.value = {
        maintainAspectRatio: false,
        aspectRatio: 1.2,
        plugins: {
            legend: {
                labels: { color: textColor }
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: {
            x: {
                ticks: { color: textColorSecondary },
                grid: { display: false }
            },
            y: {
                ticks: { color: textColorSecondary },
                grid: { color: surfaceBorder }
            }
        }
    };

    // Chart 2: Mutasi Masuk & Keluar
    mutationChartData.value = {
        labels,
        datasets: [
            {
                label: 'Mutasi Masuk',
                backgroundColor: '#06B6D4',
                borderColor: '#06B6D4',
                borderRadius: 4,
                data: trends.value.charts.mutations.incoming
            },
            {
                label: 'Mutasi Keluar',
                backgroundColor: '#F59E0B',
                borderColor: '#F59E0B',
                borderRadius: 4,
                data: trends.value.charts.mutations.outgoing
            }
        ]
    };

    mutationChartOptions.value = {
        maintainAspectRatio: false,
        aspectRatio: 1.2,
        plugins: {
            legend: {
                labels: { color: textColor }
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: {
            x: {
                ticks: { color: textColorSecondary },
                grid: { display: false }
            },
            y: {
                ticks: { color: textColorSecondary, stepSize: 1 },
                grid: { color: surfaceBorder }
            }
        }
    };
};

const exportCsv = async () => {
    exporting.value = true;
    try {
        const response = await apiLogin.get('/api/reports/school-statistics/export', {
            params: { limit_years: limitYears.value },
            responseType: 'blob'
        });

        const url = window.URL.createObjectURL(new Blob([response.data], { type: 'text/csv;charset=utf-8;' }));
        const link = document.createElement('a');
        link.href = url;
        const nowStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
        link.setAttribute('download', `rekap_statistik_sekolah_${nowStr}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        toast.add({
            severity: 'success',
            summary: 'Ekspor Berhasil',
            detail: 'Berkas rekapitulasi statistik sekolah berhasil diunduh.',
            life: 3000
        });
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Gagal Ekspor',
            detail: 'Terjadi kesalahan saat mengunduh CSV rekapitulasi.',
            life: 5000
        });
    } finally {
        exporting.value = false;
    }
};

watch(isDarkTheme, () => {
    buildCharts();
});

watch(limitYears, () => {
    loadTrends();
});

onMounted(() => {
    loadTrends();
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Toolbar Kontrol -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-surface-50 dark:bg-surface-800 p-4 rounded-xl border border-surface-200 dark:border-surface-700">
            <div class="flex items-center gap-3">
                <span class="font-medium text-surface-700 dark:text-surface-200 text-sm">Rentang Waktu:</span>
                <Select v-model="limitYears" :options="limitOptions" optionLabel="label" optionValue="value" class="w-48" :disabled="loading" />
            </div>
            <div class="flex items-center gap-2">
                <Button icon="pi pi-refresh" label="Segarkan" severity="secondary" outlined size="small" :loading="loading" @click="loadTrends" />
                <Button icon="pi pi-download" label="Ekspor CSV Rekap" severity="primary" size="small" :loading="exporting" @click="exportCsv" />
            </div>
        </div>

        <!-- KPI Cards Ringkasan Terkini -->
        <div v-if="latestYear" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="card p-4 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-surface-500 dark:text-surface-400 font-medium text-sm">Siswa Aktif Terkini</span>
                    <span class="p-2 rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-400">
                        <i class="pi pi-users text-lg"></i>
                    </span>
                </div>
                <div class="text-2xl font-bold text-surface-900 dark:text-surface-0">{{ latestYear.total_students }}</div>
                <div class="text-xs text-surface-500 mt-1">
                    {{ latestYear.male_students }} L ({{ Math.round((latestYear.male_students / (latestYear.total_students || 1)) * 100) }}%) · {{ latestYear.female_students }} P ({{
                        Math.round((latestYear.female_students / (latestYear.total_students || 1)) * 100)
                    }}%)
                </div>
            </div>

            <div class="card p-4 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-surface-500 dark:text-surface-400 font-medium text-sm">Rombongan Belajar</span>
                    <span class="p-2 rounded-lg bg-purple-100 text-purple-600 dark:bg-purple-900/40 dark:text-purple-400">
                        <i class="pi pi-sitemap text-lg"></i>
                    </span>
                </div>
                <div class="text-2xl font-bold text-surface-900 dark:text-surface-0">{{ latestYear.total_rombels }}</div>
                <div class="text-xs text-surface-500 mt-1">Rata-rata {{ latestYear.total_rombels > 0 ? Math.round(latestYear.total_students / latestYear.total_rombels) : 0 }} siswa/kelas</div>
            </div>

            <div class="card p-4 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-surface-500 dark:text-surface-400 font-medium text-sm">Arus Mutasi Siswa</span>
                    <span class="p-2 rounded-lg bg-cyan-100 text-cyan-600 dark:bg-cyan-900/40 dark:text-cyan-400">
                        <i class="pi pi-arrows-h text-lg"></i>
                    </span>
                </div>
                <div class="text-2xl font-bold text-surface-900 dark:text-surface-0">{{ latestYear.incoming_mutations - latestYear.outgoing_mutations >= 0 ? '+' : '' }}{{ latestYear.incoming_mutations - latestYear.outgoing_mutations }}</div>
                <div class="text-xs text-surface-500 mt-1">+{{ latestYear.incoming_mutations }} Masuk · -{{ latestYear.outgoing_mutations }} Keluar</div>
            </div>

            <div class="card p-4 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-surface-500 dark:text-surface-400 font-medium text-sm">Total PTK Aktif</span>
                    <span class="p-2 rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400">
                        <i class="pi pi-briefcase text-lg"></i>
                    </span>
                </div>
                <div class="text-2xl font-bold text-surface-900 dark:text-surface-0">{{ latestYear.total_teachers + latestYear.total_staff }}</div>
                <div class="text-xs text-surface-500 mt-1">{{ latestYear.total_teachers }} Guru · {{ latestYear.total_staff }} Tenaga Kependidikan</div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="card p-5 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none">
                <h4 class="font-semibold text-lg text-surface-900 dark:text-surface-0 mb-4">Tren Populasi Siswa & Gender</h4>
                <div class="h-80">
                    <Chart v-if="studentChartData" type="bar" :data="studentChartData" :options="studentChartOptions" class="h-full" />
                    <div v-else class="flex items-center justify-center h-full text-surface-400">
                        {{ loading ? 'Memuat grafik...' : 'Data grafik belum tersedia' }}
                    </div>
                </div>
            </div>

            <div class="card p-5 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none">
                <h4 class="font-semibold text-lg text-surface-900 dark:text-surface-0 mb-4">Tren Mutasi Siswa (Masuk vs Keluar)</h4>
                <div class="h-80">
                    <Chart v-if="mutationChartData" type="bar" :data="mutationChartData" :options="mutationChartOptions" class="h-full" />
                    <div v-else class="flex items-center justify-center h-full text-surface-400">
                        {{ loading ? 'Memuat grafik...' : 'Data grafik belum tersedia' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabular Summary DataTable -->
        <div class="card p-5 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none">
            <h4 class="font-semibold text-lg text-surface-900 dark:text-surface-0 mb-4">Rekapitulasi Tabular per Tahun Ajaran</h4>
            <DataTable :value="trends?.years || []" :loading="loading" stripedRows responsiveLayout="scroll" class="p-datatable-sm">
                <Column field="school_year_name" header="Tahun Ajaran" class="font-medium">
                    <template #body="{ data }">
                        <div class="flex items-center gap-2">
                            <span>{{ data.school_year_name }}</span>
                            <Tag v-if="data.is_locked" severity="secondary" value="Terkunci" />
                        </div>
                    </template>
                </Column>
                <Column field="male_students" header="Siswa Laki-Laki" />
                <Column field="female_students" header="Siswa Perempuan" />
                <Column field="total_students" header="Total Siswa" class="font-semibold" />
                <Column field="total_rombels" header="Rombel" />
                <Column field="incoming_mutations" header="Mutasi Masuk">
                    <template #body="{ data }">
                        <span class="text-emerald-600 dark:text-emerald-400 font-medium">+{{ data.incoming_mutations }}</span>
                    </template>
                </Column>
                <Column field="outgoing_mutations" header="Mutasi Keluar">
                    <template #body="{ data }">
                        <span class="text-amber-600 dark:text-amber-400 font-medium">-{{ data.outgoing_mutations }}</span>
                    </template>
                </Column>
                <Column field="total_teachers" header="Guru" />
                <Column field="total_staff" header="Tendik" />
            </DataTable>
        </div>
    </div>
</template>
