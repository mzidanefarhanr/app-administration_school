<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import Dialog from 'primevue/dialog';
import apiLogin from '@/apiLogin';

const props = defineProps({
    schoolYear: {
        type: Object,
        default: () => ({})
    },
    schoolYearsPick: {
        type: Array,
        default: () => []
    },
    rombelOptions: {
        type: Array,
        default: () => []
    },
    loading: {
        type: Boolean,
        default: false
    }
});

const { t } = useI18n();

// ── State Filter & Pencarian ─────────────────────────────────────────────────
const selectedRombelId = ref(null);
const searchQuery = ref('');
const showConfigDialog = ref(false);

// ── State Metadata Sekolah & Dokumen (Default sesuai Dokumen PDF Model 8355) ──
const meta = ref({
    title1: 'DAFTAR NAMA SISWA SMA',
    title2: 'DI LINGKUNGAN DINAS PENDIDIKAN PROVINSI DKI JAKARTA',
    schoolName: 'SMA YAPPENDA',
    npsn: '20107400',
    address: 'Jl. Swasembada Timur V No. 10 RT. 006 RW. 010',
    district: 'Tanjung Priok',
    regency: 'Jakarta Utara',
    model: '8355',
    jurusan: 'KURMER',
    supervisorTitle: 'Pengawas Paket,',
    supervisorName: 'Dra. Ravida Shinta, M.Pd.',
    supervisorNip: '196310281993032003',
    principalTitle: 'Kepala Sekolah,',
    principalName: 'Wahyu Dawam Budi Utomo, M.Pd.',
    cityDate: 'Jakarta, 06 November 2026',
    tembusan: [
        '1. Dinas Pendidikan Prov. DKI Jakarta',
        '2. Sudin Pendidikan Kota Administrasi Jakarta Utara',
        '3. Pengawas Sekolah',
        '4. Sanggar / Rayon 04',
        '5. Arsip'
    ]
});

// Format tanggal hari ini dalam format Bahasa Indonesia (misal: "06 November 2026")
const getFormattedTodayDate = () => {
    try {
        const now = new Date();
        const d = String(now.getDate()).padStart(2, '0');
        const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        const m = months[now.getMonth()];
        const y = now.getFullYear();
        return `Jakarta, ${d} ${m} ${y}`;
    } catch {
        return 'Jakarta, 06 November 2026';
    }
};

// Ambil profil sekolah dari database saat komponen dimuat
const fetchSchoolProfile = async () => {
    try {
        const response = await apiLogin.get('/api/school-profiles');
        const list = response.data?.data ?? [];
        if (list.length > 0) {
            const profile = list[0];
            if (profile.education_school?.name) meta.value.schoolName = profile.education_school.name.replace(/^SMAS\s+/i, 'SMA ');
            if (profile.education_school?.npsn) meta.value.npsn = profile.education_school.npsn;
            if (profile.education_school?.address) meta.value.address = profile.education_school.address;
            if (profile.education_school?.district?.name) meta.value.district = profile.education_school.district.name;
            if (profile.education_school?.district?.regency?.name) {
                meta.value.regency = profile.education_school.district.regency.name.replace(/^KOTA ADM\.\s+/i, '');
            }
            if (profile.principal?.name) {
                meta.value.principalName = profile.principal.name;
            }
        }
    } catch {
        // Gunakan default PDF bila gagal load
    }
};

onMounted(() => {
    meta.value.cityDate = getFormattedTodayDate();
    fetchSchoolProfile();
});

// ── Inisialisasi Pilihan Rombel ───────────────────────────────────────────────
watch(
    () => props.rombelOptions,
    (options) => {
        if (options && options.length > 0) {
            // Jika belum ada yang dipilih atau rombel lama tidak ada di daftar baru,
            // utamakan cari rombel dengan nama "X - 1" (seperti di PDF), atau pilih yang pertama.
            const targetX1 = options.find((r) => r.name === 'X - 1' || r.name?.toLowerCase().includes('x - 1'));
            if (!selectedRombelId.value || !options.some((r) => r.id === selectedRombelId.value)) {
                selectedRombelId.value = targetX1 ? targetX1.id : options[0].id;
            }
        } else {
            selectedRombelId.value = null;
        }
    },
    { immediate: true }
);

// Objek Rombel Terpilih
const currentRombel = computed(() => {
    if (!selectedRombelId.value) return null;
    return props.rombelOptions.find((r) => r.id === selectedRombelId.value) ?? null;
});

// Nama Kelas Terpilih
const displayKelas = computed(() => {
    if (selectedRombelId.value === 'ALL') return 'SEMUA KELAS';
    return currentRombel.value?.name ?? 'X - 1';
});

// Nama Jurusan
const displayJurusan = computed(() => {
    // Cari siswa dalam rombel ini untuk mengambil nama jurusan dari relasi rombel
    const sample = props.schoolYearsPick.find((item) => (selectedRombelId.value === 'ALL' ? true : item.school_rombel?.id === selectedRombelId.value));
    return sample?.school_rombel?.student_major?.display_name || sample?.school_rombel?.student_major?.name || meta.value.jurusan || 'KURMER';
});

// Helper format tanggal lahir sesuai PDF (M/D/YYYY)
const formatBirthdateMdy = (val) => {
    if (!val) return '-';
    try {
        const d = new Date(val);
        if (isNaN(d.getTime())) return val;
        return `${d.getMonth() + 1}/${d.getDate()}/${d.getFullYear()}`;
    } catch {
        return val;
    }
};

// Helper format alamat lengkap
const formatFullAddress = (student) => {
    if (!student) return '-';
    // Jika kolom address sudah terisi lengkap dengan RT/RW/Kecamatan:
    const baseAddr = student.address?.trim() ?? '';
    const parts = [];
    if (baseAddr) parts.push(baseAddr);

    // Jika RT/RW ada dan belum tertulis di address:
    const rt = student.rt_num ? String(student.rt_num).trim() : '';
    const rw = student.rw_num ? String(student.rw_num).trim() : '';
    if ((rt || rw) && !baseAddr.toUpperCase().includes('RT.')) {
        const rtrw = [];
        if (rt) rtrw.push(`RT. ${rt.padStart(3, '0')}`);
        if (rw) rtrw.push(`RW. ${rw.padStart(3, '0')}`);
        if (rtrw.length > 0) parts.push(rtrw.join(' '));
    }

    if (student.village?.name && !baseAddr.toUpperCase().includes(student.village.name.toUpperCase())) {
        parts.push(`KEL. ${student.village.name}`);
    }
    if (student.village?.district?.name && !baseAddr.toUpperCase().includes(student.village.district.name.toUpperCase())) {
        parts.push(`KEC. ${student.village.district.name}`);
    }
    if (student.village?.district?.regency?.name && !baseAddr.toUpperCase().includes(student.village.district.regency.name.toUpperCase())) {
        parts.push(student.village.district.regency.name);
    }

    return parts.length > 0 ? parts.join(', ').toUpperCase() : '-';
};

// Helper format Nomor & Tahun Ijazah
const formatCertificate = (student) => {
    if (!student) return '-';
    const num = student.certificate_num?.trim() ?? '';
    const year = student.certificate_year ? String(student.certificate_year).trim() : '';
    if (num && year) return `${num} / ${year}`;
    if (year) return ` / ${year}`;
    if (num) return `${num} / -`;
    return '-';
};

// ── Daftar Siswa Terfilter ───────────────────────────────────────────────────
const filteredStudents = computed(() => {
    if (!props.schoolYearsPick || props.schoolYearsPick.length === 0) return [];

    let list = props.schoolYearsPick.filter((item) => {
        if (!selectedRombelId.value || selectedRombelId.value === 'ALL') return true;
        return item.school_rombel?.id === selectedRombelId.value;
    });

    if (searchQuery.value?.trim()) {
        const q = searchQuery.value.trim().toLowerCase();
        list = list.filter((item) => {
            const s = item.student || item.student_nik || {};
            const name = (s.name || item.name || '').toLowerCase();
            const nis = (s.nis || item.nis || '').toString().toLowerCase();
            const nisn = (s.nisn || item.nisn || '').toString().toLowerCase();
            return name.includes(q) || nis.includes(q) || nisn.includes(q);
        });
    }

    return list;
});

// Rekapitulasi L / P
const summaryCounts = computed(() => {
    let totalL = 0;
    let totalP = 0;
    filteredStudents.value.forEach((item) => {
        const s = item.student || item.student_nik || {};
        const gender = (s.gender?.name || item.gender?.name || '').toLowerCase();
        if (gender.includes('perempuan') || gender.includes('female') || gender === 'p') {
            totalP++;
        } else {
            totalL++;
        }
    });
    return {
        total: filteredStudents.value.length,
        totalL,
        totalP
    };
});

// ── Aksi Cetak & Unduh ───────────────────────────────────────────────────────
const printDocument = () => {
    window.print();
};

const exportCsv = () => {
    const rombelName = displayKelas.value.replace(/[^a-zA-Z0-9_-]/g, '_');
    const filename = `Model_8355_Kelas_${rombelName}.csv`;

    const headers = ['NO', 'NIS', 'NISN', 'NAMA', 'L/P', 'TEMPAT LAHIR', 'TGL LAHIR', 'AGAMA', 'NAMA ORANG TUA', 'ALAMAT', 'NO / TAHUN IJAZAH'];

    const rows = filteredStudents.value.map((item, idx) => {
        const s = item.student || item.student_nik || {};
        const gender = (s.gender?.name || item.gender?.name || '').toLowerCase();
        const lp = gender.includes('perempuan') || gender.includes('female') || gender === 'p' ? 'P' : 'L';
        const parentName = s.father_name || s.mother_name || s.guardian_name || '';

        return [
            idx + 1,
            `"${s.nis || ''}"`,
            `"${s.nisn || ''}"`,
            `"${(s.name || '').replace(/"/g, '""')}"`,
            lp,
            `"${(s.birthplace || s.place_of_birth || '').replace(/"/g, '""')}"`,
            formatBirthdateMdy(s.birthdate || s.date_of_birth),
            `"${s.religion?.name || ''}"`,
            `"${parentName.replace(/"/g, '""')}"`,
            `"${formatFullAddress(s).replace(/"/g, '""')}"`,
            `"${formatCertificate(s).replace(/"/g, '""')}"`
        ];
    });

    const csvContent = [headers.join(','), ...rows.map((r) => r.join(','))].join('\r\n');
    const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};
</script>

<template>
    <div class="f8355-wrapper flex flex-col gap-4">
        <!-- ── TOOLBAR ATAS (Hanya tampil di layar, disembunyikan saat cetak) ── -->
        <div class="no-print card flex flex-wrap items-center justify-between gap-3 p-4 bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-xl shadow-sm">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <label for="f8355-rombel-select" class="text-sm font-semibold text-surface-700 dark:text-surface-200 whitespace-nowrap">
                        <i class="pi pi-table mr-1 text-primary-500"></i> Pilih Kelas / Rombel:
                    </label>
                    <Select
                        id="f8355-rombel-select"
                        v-model="selectedRombelId"
                        :options="[{ id: 'ALL', name: '-- Semua Kelas / Rombel --' }, ...rombelOptions]"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Pilih Kelas"
                        class="w-56"
                    />
                </div>

                <div class="relative">
                    <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-surface-400"></i>
                    <InputText
                        v-model="searchQuery"
                        placeholder="Cari Siswa / NIS / NISN..."
                        class="pl-9 w-60 text-sm"
                    />
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    label="Sesuaikan Kop & TTD"
                    icon="pi pi-sliders-h"
                    severity="secondary"
                    outlined
                    size="small"
                    @click="showConfigDialog = true"
                />
                <Button
                    label="Export CSV"
                    icon="pi pi-file-excel"
                    severity="success"
                    size="small"
                    :disabled="filteredStudents.length === 0"
                    @click="exportCsv"
                />
                <Button
                    label="Cetak Dokumen"
                    icon="pi pi-print"
                    severity="primary"
                    size="small"
                    :disabled="filteredStudents.length === 0"
                    @click="printDocument"
                />
            </div>
        </div>

        <!-- ── LEMBAR DOKUMEN MODEL 8355 (Persis Format PDF Resmi) ──────────── -->
        <div
            id="f8355-print-area"
            class="paper-sheet mx-auto w-full max-w-[1240px] bg-white text-black p-8 md:p-10 rounded-lg shadow-md border border-neutral-300 print:border-none print:shadow-none print:p-0 print:m-0"
        >
            <!-- 1. KOP DOKUMEN -->
            <div class="text-center font-bold tracking-wide uppercase text-sm md:text-base leading-snug mb-5 text-black">
                <div>{{ meta.title1 }}</div>
                <div>{{ meta.title2 }}</div>
                <div>TAHUN PELAJARAN {{ schoolYear?.name || '2025/2026' }}</div>
            </div>

            <!-- 2. BLOK METADATA DUA KOLOM -->
            <div class="grid grid-cols-12 text-xs font-bold leading-normal mb-3 text-black">
                <!-- Kolom Kiri: Info Sekolah -->
                <div class="col-span-8 flex flex-col gap-0.5">
                    <div class="flex">
                        <span class="w-40 shrink-0">NAMA SEKOLAH</span>
                        <span class="mr-2">:</span>
                        <span>{{ meta.schoolName }}</span>
                    </div>
                    <div class="flex">
                        <span class="w-40 shrink-0">NPSN</span>
                        <span class="mr-2">:</span>
                        <span>{{ meta.npsn }}</span>
                    </div>
                    <div class="flex">
                        <span class="w-40 shrink-0">ALAMAT</span>
                        <span class="mr-2">:</span>
                        <span>{{ meta.address }}</span>
                    </div>
                    <div class="flex">
                        <span class="w-40 shrink-0">KECAMATAN</span>
                        <span class="mr-2">:</span>
                        <span>{{ meta.district }}</span>
                    </div>
                    <div class="flex">
                        <span class="w-40 shrink-0">KOTA ADMINISTRASI</span>
                        <span class="mr-2">:</span>
                        <span>{{ meta.regency }}</span>
                    </div>
                </div>

                <!-- Kolom Kanan: Info Rombel & Model Form -->
                <div class="col-span-4 flex flex-col gap-0.5 pl-4">
                    <div class="flex">
                        <span class="w-24 shrink-0">MODEL</span>
                        <span class="mr-2">:</span>
                        <span>{{ meta.model }}</span>
                    </div>
                    <div class="h-4"></div>
                    <div class="flex">
                        <span class="w-24 shrink-0">KELAS</span>
                        <span class="mr-2">:</span>
                        <span>{{ displayKelas }}</span>
                    </div>
                    <div class="flex">
                        <span class="w-24 shrink-0">JURUSAN</span>
                        <span class="mr-2">:</span>
                        <span>{{ displayJurusan }}</span>
                    </div>
                </div>
            </div>

            <!-- 3. TABEL DATA SISWA (11 Kolom Model 8355) -->
            <div class="overflow-x-auto">
                <table class="w-full text-xs border-collapse border border-black text-black">
                    <thead>
                        <tr class="font-bold uppercase text-center bg-neutral-100 print:bg-transparent">
                            <th class="border border-black px-1.5 py-1.5 w-10">NO</th>
                            <th class="border border-black px-1.5 py-1.5 w-16">NIS</th>
                            <th class="border border-black px-1.5 py-1.5 w-24">NISN</th>
                            <th class="border border-black px-2 py-1.5 text-left min-w-[130px]">NAMA</th>
                            <th class="border border-black px-1 py-1.5 w-9">L/P</th>
                            <th class="border border-black px-1.5 py-1.5 text-left min-w-[90px]">TEMPAT LAHIR</th>
                            <th class="border border-black px-1 py-1.5 w-20">TGL LAHIR</th>
                            <th class="border border-black px-1.5 py-1.5 w-16">AGAMA</th>
                            <th class="border border-black px-2 py-1.5 text-left min-w-[120px]">NAMA ORANG TUA</th>
                            <th class="border border-black px-2 py-1.5 text-left min-w-[220px]">ALAMAT</th>
                            <th class="border border-black px-2 py-1.5 text-left min-w-[150px]">NO / TAHUN IJAZAH</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="11" class="border border-black text-center py-6 text-neutral-500 italic">
                                Memuat data siswa...
                            </td>
                        </tr>
                        <tr v-else-if="filteredStudents.length === 0">
                            <td colspan="11" class="border border-black text-center py-6 text-neutral-500 italic">
                                Tidak ada data siswa untuk kelas/filter yang dipilih.
                            </td>
                        </tr>
                        <tr
                            v-else
                            v-for="(item, idx) in filteredStudents"
                            :key="item.id || idx"
                            class="hover:bg-neutral-50 print:hover:bg-transparent leading-tight"
                        >
                            <!-- NO -->
                            <td class="border border-black px-1 py-1 text-center font-bold">
                                {{ idx + 1 }}
                            </td>
                            <!-- NIS -->
                            <td class="border border-black px-1 py-1 text-center font-bold whitespace-nowrap">
                                {{ item.student?.nis || item.student_nik?.nis || item.nis || '-' }}
                            </td>
                            <!-- NISN -->
                            <td class="border border-black px-1 py-1 text-center font-bold whitespace-nowrap">
                                {{ item.student?.nisn || item.student_nik?.nisn || item.nisn || '-' }}
                            </td>
                            <!-- NAMA -->
                            <td class="border border-black px-2 py-1 font-bold text-left uppercase">
                                {{ (item.student?.name || item.student_nik?.name || item.name || '-').toUpperCase() }}
                            </td>
                            <!-- L/P -->
                            <td class="border border-black px-1 py-1 text-center font-bold">
                                {{
                                    (() => {
                                        const g = (item.student?.gender?.name || item.student_nik?.gender?.name || item.gender?.name || '').toLowerCase();
                                        return g.includes('perempuan') || g.includes('female') || g === 'p' ? 'P' : 'L';
                                    })()
                                }}
                            </td>
                            <!-- TEMPAT LAHIR -->
                            <td class="border border-black px-1.5 py-1 text-left uppercase">
                                {{ (item.student?.birthplace || item.student?.place_of_birth || item.student_nik?.birthplace || item.student_nik?.place_of_birth || '-').toUpperCase() }}
                            </td>
                            <!-- TGL LAHIR -->
                            <td class="border border-black px-1 py-1 text-center font-bold whitespace-nowrap">
                                {{ formatBirthdateMdy(item.student?.birthdate || item.student?.date_of_birth || item.student_nik?.birthdate || item.student_nik?.date_of_birth) }}
                            </td>
                            <!-- AGAMA -->
                            <td class="border border-black px-1 py-1 text-center font-bold uppercase">
                                {{ (item.student?.religion?.name || item.student_nik?.religion?.name || item.religion?.name || '-').toUpperCase() }}
                            </td>
                            <!-- NAMA ORANG TUA -->
                            <td class="border border-black px-2 py-1 text-left uppercase font-bold text-[11px] leading-snug">
                                {{ (item.student?.father_name || item.student?.mother_name || item.student?.guardian_name || item.student_nik?.father_name || item.student_nik?.mother_name || item.student_nik?.guardian_name || '-').toUpperCase() }}
                            </td>
                            <!-- ALAMAT -->
                            <td class="border border-black px-2 py-1 text-left uppercase text-[10.5px] leading-snug">
                                {{ formatFullAddress(item.student || item.student_nik) }}
                            </td>
                            <!-- NO / TAHUN IJAZAH -->
                            <td class="border border-black px-2 py-1 text-left uppercase text-[11px] whitespace-nowrap">
                                {{ formatCertificate(item.student || item.student_nik) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- REKAPITULASI RINGKAS (Bawah Tabel) -->
            <div class="flex justify-between items-center text-xs font-bold mt-2 text-black print:text-[10px]">
                <div>
                    <span>Total Siswa: </span>
                    <span class="underline">{{ summaryCounts.total }}</span>
                    <span class="ml-3">( L: {{ summaryCounts.totalL }}, P: {{ summaryCounts.totalP }} )</span>
                </div>
            </div>

            <!-- 4. FOOTER: TEMBUSAN & TANDA TANGAN (Persis Sesuai PDF) -->
            <div class="grid grid-cols-12 text-xs mt-6 pt-2 text-black leading-tight break-inside-avoid">
                <!-- Kiri: Tembusan -->
                <div class="col-span-4 flex flex-col text-[11px]">
                    <div class="font-bold underline mb-1">Tembusan:</div>
                    <div v-for="(t, idx) in meta.tembusan" :key="idx" class="leading-tight">
                        {{ t }}
                    </div>
                </div>

                <!-- Tengah: Pengawas Paket -->
                <div class="col-span-4 flex flex-col items-center text-center">
                    <div class="font-bold">Mengetahui</div>
                    <div class="font-bold">{{ meta.supervisorTitle }}</div>
                    <!-- Ruang tanda tangan -->
                    <div class="h-16 md:h-20"></div>
                    <div class="font-bold underline">{{ meta.supervisorName }}</div>
                    <div class="text-[11px]">NIP. {{ meta.supervisorNip }}</div>
                </div>

                <!-- Kanan: Kepala Sekolah -->
                <div class="col-span-4 flex flex-col items-center text-center">
                    <div class="font-bold">{{ meta.cityDate }}</div>
                    <div class="font-bold">{{ meta.principalTitle }}</div>
                    <!-- Ruang tanda tangan -->
                    <div class="h-16 md:h-20"></div>
                    <div class="font-bold underline">{{ meta.principalName }}</div>
                </div>
            </div>
        </div>

        <!-- ── MODAL SESUAIKAN KOP & TANDA TANGAN ───────────────────────────── -->
        <Dialog
            v-model:visible="showConfigDialog"
            header="Sesuaikan Teks Dokumen & Tanda Tangan Model 8355"
            modal
            :style="{ width: '38rem' }"
            :breakpoints="{ '768px': '95vw' }"
        >
            <div class="flex flex-col gap-3 text-sm py-2">
                <div class="grid grid-cols-12 gap-2 items-center">
                    <label class="col-span-4 font-semibold">Nama Sekolah:</label>
                    <InputText v-model="meta.schoolName" class="col-span-8 text-sm" />
                </div>
                <div class="grid grid-cols-12 gap-2 items-center">
                    <label class="col-span-4 font-semibold">NPSN:</label>
                    <InputText v-model="meta.npsn" class="col-span-8 text-sm" />
                </div>
                <div class="grid grid-cols-12 gap-2 items-center">
                    <label class="col-span-4 font-semibold">Alamat:</label>
                    <InputText v-model="meta.address" class="col-span-8 text-sm" />
                </div>
                <div class="grid grid-cols-12 gap-2 items-center">
                    <label class="col-span-4 font-semibold">Kecamatan:</label>
                    <InputText v-model="meta.district" class="col-span-8 text-sm" />
                </div>
                <div class="grid grid-cols-12 gap-2 items-center">
                    <label class="col-span-4 font-semibold">Kota Administrasi:</label>
                    <InputText v-model="meta.regency" class="col-span-8 text-sm" />
                </div>
                <div class="grid grid-cols-12 gap-2 items-center">
                    <label class="col-span-4 font-semibold">Jurusan:</label>
                    <InputText v-model="meta.jurusan" class="col-span-8 text-sm" />
                </div>
                <hr class="my-1 border-surface-200 dark:border-surface-700" />
                <div class="grid grid-cols-12 gap-2 items-center">
                    <label class="col-span-4 font-semibold">Nama Pengawas:</label>
                    <InputText v-model="meta.supervisorName" class="col-span-8 text-sm" />
                </div>
                <div class="grid grid-cols-12 gap-2 items-center">
                    <label class="col-span-4 font-semibold">NIP Pengawas:</label>
                    <InputText v-model="meta.supervisorNip" class="col-span-8 text-sm" />
                </div>
                <div class="grid grid-cols-12 gap-2 items-center">
                    <label class="col-span-4 font-semibold">Nama Kepala Sekolah:</label>
                    <InputText v-model="meta.principalName" class="col-span-8 text-sm" />
                </div>
                <div class="grid grid-cols-12 gap-2 items-center">
                    <label class="col-span-4 font-semibold">Kota & Tanggal TTD:</label>
                    <InputText v-model="meta.cityDate" class="col-span-8 text-sm" />
                </div>
            </div>
            <template #footer>
                <Button label="Tutup" icon="pi pi-check" size="small" @click="showConfigDialog = false" />
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
@media print {
    /* Sembunyikan semua elemen layout default saat cetak */
    :global(body) {
        background: white !important;
        color: black !important;
    }
    :global(.layout-sidebar),
    :global(.layout-topbar),
    :global(.layout-footer),
    :global(.layout-breadcrumb),
    :global(.layout-config-button),
    :global(.no-print) {
        display: none !important;
    }

    .f8355-wrapper {
        margin: 0 !important;
        padding: 0 !important;
    }

    #f8355-print-area {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 10mm !important;
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
