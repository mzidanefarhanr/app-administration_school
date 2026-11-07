<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import Skeleton from 'primevue/skeleton';
import apiLogin from '@/apiLogin';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    schoolYearId: {
        type: [Number, String],
        default: null
    },
    schoolYearName: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['update:visible']);

const { t } = useI18n();

const loading = ref(false);
const error = ref('');
const reportData = ref(null);
const showConfigDialog = ref(false);

const months = [
    { label: 'Januari', value: 1 },
    { label: 'Februari', value: 2 },
    { label: 'Maret', value: 3 },
    { label: 'April', value: 4 },
    { label: 'Mei', value: 5 },
    { label: 'Juni', value: 6 },
    { label: 'Juli', value: 7 },
    { label: 'Agustus', value: 8 },
    { label: 'September', value: 9 },
    { label: 'Oktober', value: 10 },
    { label: 'November', value: 11 },
    { label: 'Desember', value: 12 }
];

const currentYear = new Date().getFullYear();
const years = [
    { label: String(currentYear - 2), value: currentYear - 2 },
    { label: String(currentYear - 1), value: currentYear - 1 },
    { label: String(currentYear), value: currentYear },
    { label: String(currentYear + 1), value: currentYear + 1 }
];

const selectedMonth = ref(11);
const selectedYear = ref(currentYear);

// Metadata Kop & TTD
const meta = ref({
    schoolName: 'SMA YAPPENDA',
    principalName: 'Wahyu Dawam Budi Utomo, M.Pd.',
    cityDate: 'Jakarta, 07 November 2026'
});

const getFormattedTodayDate = () => {
    try {
        const now = new Date();
        const d = String(now.getDate()).padStart(2, '0');
        const mNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return `Jakarta, ${d} ${mNames[now.getMonth()]} ${now.getFullYear()}`;
    } catch {
        return 'Jakarta, 07 November 2026';
    }
};

const fetchReport = async () => {
    if (!props.schoolYearId) return;
    loading.value = true;
    error.value = '';
    try {
        const response = await apiLogin.get('/api/academic/mutations/report', {
            params: {
                school_year_id: props.schoolYearId,
                month: selectedMonth.value,
                year: selectedYear.value
            }
        });
        reportData.value = response.data?.data ?? null;

        if (reportData.value?.school_profile?.school_name) {
            meta.value.schoolName = reportData.value.school_profile.school_name;
        }
        if (reportData.value?.school_profile?.principal_name) {
            meta.value.principalName = reportData.value.school_profile.principal_name;
        }
        if (reportData.value?.signature?.city_date) {
            meta.value.cityDate = reportData.value.signature.city_date;
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Gagal memuat data laporan mutasi siswa.';
        reportData.value = null;
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.visible,
    (val) => {
        if (val) {
            meta.value.cityDate = getFormattedTodayDate();
            fetchReport();
        }
    }
);

watch([selectedMonth, selectedYear], () => {
    if (props.visible) {
        fetchReport();
    }
});

// Pad table rows agar tampilan kedua tabel seimbang dan rapi
const tableRows = computed(() => {
    const listIn = reportData.value?.mutations_in ?? [];
    const listOut = reportData.value?.mutations_out ?? [];
    const maxLen = Math.max(listIn.length, listOut.length, 5);

    const rows = [];
    for (let i = 0; i < maxLen; i++) {
        rows.push({
            in: listIn[i] || null,
            out: listOut[i] || null
        });
    }
    return rows;
});

// Cetak Dokumen PDF A4
const printDocument = () => {
    window.print();
};

// Export Excel (.xlsx)
const exportExcel = async () => {
    if (!props.schoolYearId) return;
    try {
        const response = await apiLogin.get('/api/academic/mutations/report/excel', {
            params: {
                school_year_id: props.schoolYearId,
                month: selectedMonth.value,
                year: selectedYear.value
            },
            responseType: 'blob'
        });

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        const mLabel = months.find((m) => m.value === selectedMonth.value)?.label || selectedMonth.value;
        link.setAttribute('download', `DATA-MUTASI-SISWA-${meta.value.schoolName}-${mLabel}-${selectedYear.value}.xlsx`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (err) {
        alert(err.response?.data?.message || 'Gagal mengunduh file Excel.');
    }
};

const close = () => {
    emit('update:visible', false);
};
</script>

<template>
    <Dialog
        :visible="visible"
        @update:visible="emit('update:visible', $event)"
        modal
        header="Laporan Mutasi Siswa (Format PDF A4 / Excel)"
        class="w-full max-w-[1300px]"
        :dismissableMask="true"
        maximizable
    >
        <div class="mutation-report-wrapper flex flex-col gap-4">
            <!-- ── TOOLBAR ATAS ─────────────────────────────────────────────── -->
            <div class="no-print flex flex-wrap items-center justify-between gap-3 bg-surface-50 dark:bg-surface-800 p-3 rounded-lg border border-surface-200 dark:border-surface-700">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-semibold">Periode Bulan:</span>
                    <Select v-model="selectedMonth" :options="months" optionLabel="label" optionValue="value" class="w-36 text-sm" />
                    <Select v-model="selectedYear" :options="years" optionLabel="label" optionValue="value" class="w-28 text-sm" />
                    <Button icon="pi pi-refresh" severity="secondary" text rounded size="small" @click="fetchReport" v-tooltip.top="'Segarkan Data'" />
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button label="Sesuaikan Kop & TTD" icon="pi pi-sliders-h" severity="secondary" outlined size="small" @click="showConfigDialog = true" />
                    <Button label="Unduh Excel (.xlsx)" icon="pi pi-file-excel" severity="success" size="small" @click="exportExcel" />
                    <Button label="Cetak Dokumen (PDF A4)" icon="pi pi-print" severity="primary" size="small" @click="printDocument" />
                </div>
            </div>

            <!-- Loading & Error -->
            <div v-if="loading" class="no-print p-6 flex flex-col gap-3">
                <Skeleton width="100%" height="2rem" />
                <Skeleton width="100%" height="15rem" />
            </div>
            <div v-else-if="error" class="no-print p-4 text-red-600 bg-red-50 rounded border border-red-200">
                {{ error }}
            </div>

            <!-- ── LEMBAR DOKUMEN CETAK A4 LANDSCAPE (Persis File PDF) ─────── -->
            <div
                id="mutation-report-print-area"
                class="paper-sheet mx-auto w-full max-w-[1240px] bg-white text-black p-6 md:p-8 rounded-lg shadow border border-neutral-300 print:border-none print:shadow-none print:p-0 print:m-0"
            >
                <!-- 1. KOP JUDUL -->
                <div class="text-center font-bold tracking-wide uppercase text-sm md:text-base leading-snug mb-1 text-black">
                    <div>DATA MUTASI SISWA {{ meta.schoolName }}</div>
                    <div>TAHUN PELAJARAN {{ reportData?.school_year?.name || props.schoolYearName || '2025/2026' }}</div>
                </div>
                <div class="text-xs font-bold mb-3 text-black">
                    Bulan / Tahun : {{ reportData?.period?.label || 'November 2026' }}
                </div>

                <!-- 2. TABEL MASUK & KELUAR BERDAMPINGAN -->
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <!-- TABEL KIRI: MASUK -->
                    <div class="w-full">
                        <table class="w-full text-[10px] border-collapse border border-black text-black">
                            <thead>
                                <tr class="bg-neutral-100 font-bold uppercase text-center">
                                    <th colspan="7" class="border border-black py-1">MASUK</th>
                                </tr>
                                <tr class="bg-neutral-100 font-bold uppercase text-center">
                                    <th colspan="2" class="border border-black px-1 py-0.5">Nomor</th>
                                    <th rowspan="2" class="border border-black px-1.5 py-0.5 text-left min-w-[90px]">Nama Siswa</th>
                                    <th rowspan="2" class="border border-black px-1 py-0.5 w-6">L/P</th>
                                    <th rowspan="2" class="border border-black px-1 py-0.5 min-w-[60px]">Kelas</th>
                                    <th rowspan="2" class="border border-black px-1.5 py-0.5 text-left min-w-[90px]">Pindahan Dari</th>
                                    <th rowspan="2" class="border border-black px-1 py-0.5 min-w-[65px]">Tanggal</th>
                                </tr>
                                <tr class="bg-neutral-100 font-bold uppercase text-center">
                                    <th class="border border-black px-1 py-0.5 w-6">Urut</th>
                                    <th class="border border-black px-1 py-0.5 w-12">Induk</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="tableRows.length === 0">
                                    <td colspan="7" class="border border-black py-3 text-center text-neutral-500 italic">Tidak ada data mutasi masuk</td>
                                </tr>
                                <tr v-for="(r, idx) in tableRows" :key="'in-' + idx" class="hover:bg-neutral-50">
                                    <td class="border border-black px-1 py-0.5 text-center">{{ r.in?.no || (idx + 1) }}</td>
                                    <td class="border border-black px-1 py-0.5 text-center">{{ r.in?.nis || '' }}</td>
                                    <td class="border border-black px-1.5 py-0.5 truncate max-w-[120px]">{{ r.in?.name || '' }}</td>
                                    <td class="border border-black px-1 py-0.5 text-center font-bold">{{ r.in?.gender || '' }}</td>
                                    <td class="border border-black px-1 py-0.5 text-center">{{ r.in?.class_name || '' }}</td>
                                    <td class="border border-black px-1.5 py-0.5 truncate max-w-[120px]">{{ r.in?.from_school || '' }}</td>
                                    <td class="border border-black px-1 py-0.5 text-center whitespace-nowrap">{{ r.in?.date || '' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- TABEL KANAN: KELUAR -->
                    <div class="w-full">
                        <table class="w-full text-[10px] border-collapse border border-black text-black">
                            <thead>
                                <tr class="bg-neutral-100 font-bold uppercase text-center">
                                    <th colspan="7" class="border border-black py-1">KELUAR</th>
                                </tr>
                                <tr class="bg-neutral-100 font-bold uppercase text-center">
                                    <th colspan="2" class="border border-black px-1 py-0.5">Nomor</th>
                                    <th rowspan="2" class="border border-black px-1.5 py-0.5 text-left min-w-[90px]">Nama Siswa</th>
                                    <th rowspan="2" class="border border-black px-1 py-0.5 w-6">L/P</th>
                                    <th rowspan="2" class="border border-black px-1 py-0.5 min-w-[60px]">Kelas</th>
                                    <th rowspan="2" class="border border-black px-1.5 py-0.5 text-left min-w-[90px]">Alasan Keluar</th>
                                    <th rowspan="2" class="border border-black px-1 py-0.5 min-w-[65px]">Tanggal</th>
                                </tr>
                                <tr class="bg-neutral-100 font-bold uppercase text-center">
                                    <th class="border border-black px-1 py-0.5 w-6">Urut</th>
                                    <th class="border border-black px-1 py-0.5 w-12">Induk</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="tableRows.length === 0">
                                    <td colspan="7" class="border border-black py-3 text-center text-neutral-500 italic">Tidak ada data mutasi keluar</td>
                                </tr>
                                <tr v-for="(r, idx) in tableRows" :key="'out-' + idx" class="hover:bg-neutral-50">
                                    <td class="border border-black px-1 py-0.5 text-center">{{ r.out?.no || (idx + 1) }}</td>
                                    <td class="border border-black px-1 py-0.5 text-center">{{ r.out?.nis || '' }}</td>
                                    <td class="border border-black px-1.5 py-0.5 truncate max-w-[120px]">{{ r.out?.name || '' }}</td>
                                    <td class="border border-black px-1 py-0.5 text-center font-bold">{{ r.out?.gender || '' }}</td>
                                    <td class="border border-black px-1 py-0.5 text-center">{{ r.out?.class_name || '' }}</td>
                                    <td class="border border-black px-1.5 py-0.5 truncate max-w-[120px]">{{ r.out?.reason || '' }}</td>
                                    <td class="border border-black px-1 py-0.5 text-center whitespace-nowrap">{{ r.out?.date || '' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 3. REKAPITULASI BAWAH: 4 TABEL & BLOK TANDA TANGAN -->
                <div class="grid grid-cols-12 gap-3 items-start text-[10px]">
                    <!-- Block 1: KEADAAN AWAL BULAN (3 cols) -->
                    <div class="col-span-3">
                        <table class="w-full border-collapse border border-black text-black">
                            <thead>
                                <tr class="bg-neutral-100 font-bold text-center">
                                    <th colspan="5" class="border border-black py-0.5">KEADAAN AWAL BULAN</th>
                                </tr>
                                <tr class="bg-neutral-100 font-bold text-center">
                                    <th rowspan="2" class="border border-black px-1 py-0.5 w-5">No</th>
                                    <th rowspan="2" class="border border-black px-1 py-0.5">Rombel</th>
                                    <th colspan="2" class="border border-black px-1 py-0.5">Kelamin</th>
                                    <th rowspan="2" class="border border-black px-1 py-0.5 w-10">Jumlah</th>
                                </tr>
                                <tr class="bg-neutral-100 font-bold text-center">
                                    <th class="border border-black px-1 py-0.5 w-6">L</th>
                                    <th class="border border-black px-1 py-0.5 w-6">P</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in reportData?.rombels_start ?? []" :key="'rs-' + item.rombel_id">
                                    <td class="border border-black px-1 py-0.5 text-center">{{ item.no }}</td>
                                    <td class="border border-black px-1.5 py-0.5">{{ item.rombel_name }}</td>
                                    <td class="border border-black px-1 py-0.5 text-center">{{ item.male_count }}</td>
                                    <td class="border border-black px-1 py-0.5 text-center">{{ item.female_count }}</td>
                                    <td class="border border-black px-1 py-0.5 text-center font-semibold">{{ item.total }}</td>
                                </tr>
                                <tr class="bg-neutral-100 font-bold">
                                    <td colspan="2" class="border border-black px-1.5 py-0.5 text-center">Jumlah</td>
                                    <td class="border border-black px-1 py-0.5 text-center">{{ reportData?.totals_start?.male_count ?? 0 }}</td>
                                    <td class="border border-black px-1 py-0.5 text-center">{{ reportData?.totals_start?.female_count ?? 0 }}</td>
                                    <td class="border border-black px-1 py-0.5 text-center">{{ reportData?.totals_start?.total ?? 0 }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Block 2: KEADAAN AKHIR BULAN (3 cols) -->
                    <div class="col-span-3">
                        <table class="w-full border-collapse border border-black text-black">
                            <thead>
                                <tr class="bg-neutral-100 font-bold text-center">
                                    <th colspan="4" class="border border-black py-0.5">KEADAAN AKHIR BULAN</th>
                                </tr>
                                <tr class="bg-neutral-100 font-bold text-center">
                                    <th rowspan="2" class="border border-black px-1 py-0.5">Rombel</th>
                                    <th colspan="2" class="border border-black px-1 py-0.5">Kelamin</th>
                                    <th rowspan="2" class="border border-black px-1 py-0.5 w-10">Jumlah</th>
                                </tr>
                                <tr class="bg-neutral-100 font-bold text-center">
                                    <th class="border border-black px-1 py-0.5 w-6">L</th>
                                    <th class="border border-black px-1 py-0.5 w-6">P</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in reportData?.rombels_end ?? []" :key="'re-' + item.rombel_id">
                                    <td class="border border-black px-1.5 py-0.5">{{ item.rombel_name }}</td>
                                    <td class="border border-black px-1 py-0.5 text-center">{{ item.male_count }}</td>
                                    <td class="border border-black px-1 py-0.5 text-center">{{ item.female_count }}</td>
                                    <td class="border border-black px-1 py-0.5 text-center font-semibold">{{ item.total }}</td>
                                </tr>
                                <tr class="bg-neutral-100 font-bold">
                                    <td class="border border-black px-1.5 py-0.5 text-center">Jumlah</td>
                                    <td class="border border-black px-1 py-0.5 text-center">{{ reportData?.totals_end?.male_count ?? 0 }}</td>
                                    <td class="border border-black px-1 py-0.5 text-center">{{ reportData?.totals_end?.female_count ?? 0 }}</td>
                                    <td class="border border-black px-1 py-0.5 text-center">{{ reportData?.totals_end?.total ?? 0 }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Block 3 & 4: MUTASI MASUK & MUTASI KELUAR + SIGNATURE (6 cols) -->
                    <div class="col-span-6 flex flex-col justify-between h-full gap-6">
                        <div class="grid grid-cols-2 gap-3">
                            <!-- MUTASI MASUK -->
                            <div>
                                <table class="w-full border-collapse border border-black text-black">
                                    <thead>
                                        <tr class="bg-neutral-100 font-bold text-center">
                                            <th colspan="3" class="border border-black py-0.5">MUTASI MASUK</th>
                                        </tr>
                                        <tr class="bg-neutral-100 font-bold text-center">
                                            <th colspan="2" class="border border-black px-1 py-0.5">Kelamin</th>
                                            <th rowspan="2" class="border border-black px-1 py-0.5 w-12">Jumlah</th>
                                        </tr>
                                        <tr class="bg-neutral-100 font-bold text-center">
                                            <th class="border border-black px-1 py-0.5 w-8">L</th>
                                            <th class="border border-black px-1 py-0.5 w-8">P</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="border border-black px-1 py-1.5 text-center font-semibold">{{ reportData?.summary_in?.male_count ?? 0 }}</td>
                                            <td class="border border-black px-1 py-1.5 text-center font-semibold">{{ reportData?.summary_in?.female_count ?? 0 }}</td>
                                            <td class="border border-black px-1 py-1.5 text-center font-bold">{{ reportData?.summary_in?.total ?? 0 }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- MUTASI KELUAR -->
                            <div>
                                <table class="w-full border-collapse border border-black text-black">
                                    <thead>
                                        <tr class="bg-neutral-100 font-bold text-center">
                                            <th colspan="3" class="border border-black py-0.5">MUTASI KELUAR</th>
                                        </tr>
                                        <tr class="bg-neutral-100 font-bold text-center">
                                            <th colspan="2" class="border border-black px-1 py-0.5">Kelamin</th>
                                            <th rowspan="2" class="border border-black px-1 py-0.5 w-12">Jumlah</th>
                                        </tr>
                                        <tr class="bg-neutral-100 font-bold text-center">
                                            <th class="border border-black px-1 py-0.5 w-8">L</th>
                                            <th class="border border-black px-1 py-0.5 w-8">P</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="border border-black px-1 py-1.5 text-center font-semibold">{{ reportData?.summary_out?.male_count ?? 0 }}</td>
                                            <td class="border border-black px-1 py-1.5 text-center font-semibold">{{ reportData?.summary_out?.female_count ?? 0 }}</td>
                                            <td class="border border-black px-1 py-1.5 text-center font-bold">{{ reportData?.summary_out?.total ?? 0 }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TANDA TANGAN (Kanan Bawah) -->
                        <div class="flex flex-col items-end pr-6 text-xs text-black">
                            <div class="text-left">
                                <div>{{ meta.cityDate }}</div>
                                <div class="mb-14">Kepala {{ meta.schoolName }},</div>
                                <div class="font-bold underline">{{ meta.principalName }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── DIALOG KONFIGURASI KOP & TTD ───────────────────────────────────── -->
        <Dialog v-model:visible="showConfigDialog" modal header="Sesuaikan Kop & Tanda Tangan" class="w-full max-w-lg">
            <div class="flex flex-col gap-3 py-2 text-sm">
                <div>
                    <label class="block mb-1 font-semibold">Nama Sekolah</label>
                    <InputText v-model="meta.schoolName" class="w-full" />
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Nama Kepala Sekolah & Gelar</label>
                    <InputText v-model="meta.principalName" class="w-full" />
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Kota & Tanggal TTD</label>
                    <InputText v-model="meta.cityDate" class="w-full" />
                </div>
            </div>
            <template #footer>
                <Button label="Selesai" icon="pi pi-check" size="small" @click="showConfigDialog = false" />
            </template>
        </Dialog>
    </Dialog>
</template>

<style scoped>
@media print {
    :global(body) {
        background: white !important;
        color: black !important;
    }
    :global(.layout-sidebar),
    :global(.layout-topbar),
    :global(.layout-footer),
    :global(.layout-breadcrumb),
    :global(.layout-config-button),
    :global(.p-dialog-header),
    :global(.no-print) {
        display: none !important;
    }

    :global(.p-dialog) {
        position: static !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        box-shadow: none !important;
        background: transparent !important;
    }

    :global(.p-dialog-content) {
        padding: 0 !important;
        overflow: visible !important;
    }

    .mutation-report-wrapper {
        margin: 0 !important;
        padding: 0 !important;
    }

    #mutation-report-print-area {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 8mm !important;
        box-shadow: none !important;
        border: none !important;
    }

    table {
        page-break-inside: auto;
    }

    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }

    thead {
        display: table-header-group;
    }

    tfoot {
        display: table-footer-group;
    }
}
</style>
