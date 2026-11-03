<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import apiLogin from '@/apiLogin';
import { useLayout } from '@/layout/composables/layout';

const toast = useToast();
const { isDarkTheme } = useLayout();

const selectedSchoolYearId = ref(null);
const schoolYears = ref([]);
const loading = ref(false);

const demographics = ref(null);
const readiness = ref(null);

const genderChartData = ref(null);
const genderChartOptions = ref(null);
const religionChartData = ref(null);
const religionChartOptions = ref(null);

const loadSchoolYears = async () => {
    try {
        const res = await apiLogin.get('/api/school-years');
        const raw = res.data?.data || res.data || [];
        schoolYears.value = Array.isArray(raw) ? raw : [];
    } catch {
        // Fallback jika endpoint master belum tersedia
        schoolYears.value = [];
    }
};

const loadData = async () => {
    loading.value = true;
    try {
        const params = selectedSchoolYearId.value ? { school_year_id: selectedSchoolYearId.value } : {};
        const [demoRes, readRes] = await Promise.all([apiLogin.get('/api/reports/demographics', { params }), apiLogin.get('/api/reports/dapodik-readiness', { params })]);

        demographics.value = demoRes.data?.data || null;
        readiness.value = readRes.data?.data || null;

        if (!selectedSchoolYearId.value && demographics.value?.school_year?.id) {
            selectedSchoolYearId.value = demographics.value.school_year.id;
        }

        buildCharts();
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Gagal Memuat Data',
            detail: err.response?.data?.message || 'Gagal memuat demografi sekolah.',
            life: 5000
        });
    } finally {
        loading.value = false;
    }
};

const buildCharts = () => {
    if (!demographics.value) return;

    const docStyle = getComputedStyle(document.documentElement);
    const textColor = docStyle.getPropertyValue('--text-color') || '#495057';

    // Chart Gender Doughnut
    const g = demographics.value.gender;
    genderChartData.value = {
        labels: ['Laki-Laki', 'Perempuan'],
        datasets: [
            {
                data: [g.male, g.female],
                backgroundColor: ['#3B82F6', '#EC4899'],
                hoverBackgroundColor: ['#2563EB', '#DB2777']
            }
        ]
    };

    genderChartOptions.value = {
        plugins: {
            legend: {
                position: 'bottom',
                labels: { color: textColor }
            }
        }
    };

    // Chart Religion Doughnut
    const rels = demographics.value.religion || [];
    const relPalette = ['#10B981', '#06B6D4', '#6366F1', '#F59E0B', '#EF4444', '#8B5CF6', '#64748B'];
    religionChartData.value = {
        labels: rels.map((r) => r.name),
        datasets: [
            {
                data: rels.map((r) => r.count),
                backgroundColor: relPalette.slice(0, rels.length),
                hoverBackgroundColor: relPalette.slice(0, rels.length)
            }
        ]
    };

    religionChartOptions.value = {
        plugins: {
            legend: {
                position: 'bottom',
                labels: { color: textColor }
            }
        }
    };
};

const ageGroupList = computed(() => {
    if (!demographics.value?.age_groups) return [];
    return Object.entries(demographics.value.age_groups).map(([age, count]) => ({
        age,
        count,
        percent: demographics.value.gender.total > 0 ? Math.round((count / demographics.value.gender.total) * 100) : 0
    }));
});

watch(isDarkTheme, () => {
    buildCharts();
});

watch(selectedSchoolYearId, (newVal, oldVal) => {
    if (oldVal !== null && newVal !== oldVal) {
        loadData();
    }
});

onMounted(async () => {
    await loadSchoolYears();
    await loadData();
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Toolbar Filter Tahun Ajaran -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-surface-50 dark:bg-surface-800 p-4 rounded-xl border border-surface-200 dark:border-surface-700">
            <div class="flex items-center gap-3">
                <span class="font-medium text-surface-700 dark:text-surface-200 text-sm">Tahun Ajaran:</span>
                <Select v-model="selectedSchoolYearId" :options="schoolYears" optionLabel="name" optionValue="id" placeholder="Pilih Tahun Ajaran" class="w-56" :disabled="loading" />
                <Tag v-if="demographics?.school_year?.is_locked" severity="secondary" value="Terkunci" />
            </div>
            <Button icon="pi pi-refresh" label="Segarkan" severity="secondary" outlined size="small" :loading="loading" @click="loadData" />
        </div>

        <!-- KPI Cards -->
        <div v-if="demographics" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="card p-4 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none">
                <span class="text-surface-500 text-sm font-medium">Total Siswa Aktif</span>
                <div class="text-2xl font-bold text-surface-900 dark:text-surface-0 mt-2">{{ demographics.gender.total }}</div>
                <div class="text-xs text-surface-400 mt-1">Terdaftar pada rombel aktif</div>
            </div>

            <div class="card p-4 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none">
                <span class="text-surface-500 text-sm font-medium">Siswa Laki-Laki</span>
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-2">
                    {{ demographics.gender.male }} <span class="text-sm font-normal text-surface-500">({{ demographics.gender.male_percent }}%)</span>
                </div>
                <div class="text-xs text-surface-400 mt-1">Proporsi siswa putra</div>
            </div>

            <div class="card p-4 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none">
                <span class="text-surface-500 text-sm font-medium">Siswa Perempuan</span>
                <div class="text-2xl font-bold text-pink-600 dark:text-pink-400 mt-2">
                    {{ demographics.gender.female }} <span class="text-sm font-normal text-surface-500">({{ demographics.gender.female_percent }}%)</span>
                </div>
                <div class="text-xs text-surface-400 mt-1">Proporsi siswa putri</div>
            </div>

            <div class="card p-4 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none">
                <span class="text-surface-500 text-sm font-medium">Kesiapan Profil Dapodik</span>
                <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-2">{{ readiness?.students?.readiness_percent ?? 0 }}%</div>
                <div class="text-xs text-surface-400 mt-1">Siswa dengan identitas lengkap</div>
            </div>
        </div>

        <!-- Charts: Gender & Agama -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="card p-5 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none">
                <h4 class="font-semibold text-lg text-surface-900 dark:text-surface-0 mb-4">Distribusi Gender Siswa</h4>
                <div class="h-64 flex items-center justify-center">
                    <Chart v-if="genderChartData" type="doughnut" :data="genderChartData" :options="genderChartOptions" class="w-full max-w-xs" />
                    <div v-else class="text-surface-400">Tidak ada data gender</div>
                </div>
            </div>

            <div class="card p-5 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none">
                <h4 class="font-semibold text-lg text-surface-900 dark:text-surface-0 mb-4">Distribusi Agama Siswa</h4>
                <div class="h-64 flex items-center justify-center">
                    <Chart v-if="religionChartData" type="doughnut" :data="religionChartData" :options="religionChartOptions" class="w-full max-w-xs" />
                    <div v-else class="text-surface-400">Tidak ada data agama</div>
                </div>
            </div>
        </div>

        <!-- Audit Kesiapan Profil Dapodik Detail -->
        <div v-if="readiness" class="card p-5 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none">
            <h4 class="font-semibold text-lg text-surface-900 dark:text-surface-0 mb-2">Audit Kesiapan Data Sinkronisasi Dapodik</h4>
            <p class="text-surface-500 text-sm mb-6">Memastikan kelengkapan kolom mandatori Dapodik (NIK 16 digit, NISN 10 digit, Nomor KK, Nama Ibu Kandung, Tempat/Tanggal Lahir) untuk pencegahan residu.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Kesiapan Siswa -->
                <div class="bg-surface-50 dark:bg-surface-800/50 p-4 rounded-xl border border-surface-200 dark:border-surface-700">
                    <div class="flex justify-between items-center mb-3">
                        <span class="font-semibold text-surface-800 dark:text-surface-100">Kesiapan Profil Siswa</span>
                        <Tag :severity="readiness.students.readiness_percent >= 80 ? 'success' : 'warn'" :value="`${readiness.students.readiness_percent}% Lengkap`" />
                    </div>

                    <div class="space-y-3">
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span>NIK Valid (16 Digit)</span>
                                <span class="font-medium">{{ readiness.students.valid_nik }} / {{ readiness.students.total }}</span>
                            </div>
                            <ProgressBar :value="readiness.students.total > 0 ? Math.round((readiness.students.valid_nik / readiness.students.total) * 100) : 0" :showValue="false" class="h-2" />
                        </div>

                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span>NISN Valid (10 Digit)</span>
                                <span class="font-medium">{{ readiness.students.valid_nisn }} / {{ readiness.students.total }}</span>
                            </div>
                            <ProgressBar :value="readiness.students.total > 0 ? Math.round((readiness.students.valid_nisn / readiness.students.total) * 100) : 0" :showValue="false" class="h-2" />
                        </div>

                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span>Nomor Kartu Keluarga (KK)</span>
                                <span class="font-medium">{{ readiness.students.has_kk }} / {{ readiness.students.total }}</span>
                            </div>
                            <ProgressBar :value="readiness.students.total > 0 ? Math.round((readiness.students.has_kk / readiness.students.total) * 100) : 0" :showValue="false" class="h-2" />
                        </div>

                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span>Nama Ibu Kandung</span>
                                <span class="font-medium">{{ readiness.students.has_mother_name }} / {{ readiness.students.total }}</span>
                            </div>
                            <ProgressBar :value="readiness.students.total > 0 ? Math.round((readiness.students.has_mother_name / readiness.students.total) * 100) : 0" :showValue="false" class="h-2" />
                        </div>

                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span>Tempat & Tanggal Lahir</span>
                                <span class="font-medium">{{ readiness.students.has_birth_data }} / {{ readiness.students.total }}</span>
                            </div>
                            <ProgressBar :value="readiness.students.total > 0 ? Math.round((readiness.students.has_birth_data / readiness.students.total) * 100) : 0" :showValue="false" class="h-2" />
                        </div>
                    </div>
                </div>

                <!-- Kesiapan PTK -->
                <div class="bg-surface-50 dark:bg-surface-800/50 p-4 rounded-xl border border-surface-200 dark:border-surface-700">
                    <div class="flex justify-between items-center mb-3">
                        <span class="font-semibold text-surface-800 dark:text-surface-100">Kesiapan Profil Pendidik & Tenaga Kependidikan</span>
                        <Tag :severity="readiness.employees.readiness_percent >= 80 ? 'success' : 'warn'" :value="`${readiness.employees.readiness_percent}% Lengkap`" />
                    </div>

                    <div class="space-y-3">
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span>NIK PTK Valid (16 Digit)</span>
                                <span class="font-medium">{{ readiness.employees.valid_nik }} / {{ readiness.employees.total }}</span>
                            </div>
                            <ProgressBar :value="readiness.employees.total > 0 ? Math.round((readiness.employees.valid_nik / readiness.employees.total) * 100) : 0" :showValue="false" class="h-2" />
                        </div>

                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span>Pendidik dengan NUPTK</span>
                                <span class="font-medium">{{ readiness.employees.teachers_with_nuptk }} Terisi</span>
                            </div>
                            <ProgressBar :value="readiness.employees.total > 0 ? Math.round((readiness.employees.teachers_with_nuptk / readiness.employees.total) * 100) : 0" :showValue="false" class="h-2" />
                        </div>

                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span>Pendidik Terpetakan Mata Pelajaran</span>
                                <span class="font-medium">{{ readiness.employees.teachers_with_subject }} Guru</span>
                            </div>
                            <ProgressBar :value="readiness.employees.total > 0 ? Math.round((readiness.employees.teachers_with_subject / readiness.employees.total) * 100) : 0" :showValue="false" class="h-2" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rombel Breakdown & Age Groups -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Kelompok Usia -->
            <div class="card p-5 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none lg:col-span-1">
                <h4 class="font-semibold text-lg text-surface-900 dark:text-surface-0 mb-4">Distribusi Kelompok Usia</h4>
                <DataTable :value="ageGroupList" stripedRows responsiveLayout="scroll" class="p-datatable-sm">
                    <Column field="age" header="Usia (Tahun)" />
                    <Column field="count" header="Jumlah Siswa" />
                    <Column field="percent" header="%">
                        <template #body="{ data }">
                            <span>{{ data.percent }}%</span>
                        </template>
                    </Column>
                </DataTable>
            </div>

            <!-- Rekap per Rombongan Belajar -->
            <div class="card p-5 rounded-xl border border-surface-200 dark:border-surface-700 mb-0 shadow-none lg:col-span-2">
                <h4 class="font-semibold text-lg text-surface-900 dark:text-surface-0 mb-4">Rekapitulasi per Rombongan Belajar</h4>
                <DataTable :value="demographics?.rombels || []" stripedRows responsiveLayout="scroll" class="p-datatable-sm" paginator :rows="8">
                    <Column field="name" header="Rombel" class="font-medium" />
                    <Column field="level" header="Jenjang" />
                    <Column field="male" header="Laki-Laki" />
                    <Column field="female" header="Perempuan" />
                    <Column field="total" header="Total Siswa" class="font-bold" />
                </DataTable>
            </div>
        </div>
    </div>
</template>
