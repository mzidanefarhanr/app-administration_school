<script setup>
import { ref, onMounted, computed } from 'vue';
import Button from 'primevue/button';
import { authStore } from '@/stores/authStore';
import { useToast } from "primevue/usetoast";
import apiLogin from '@/apiLogin';
import gifSuccess from '@/assets/gif/success.gif';
import { buildStudentExportParams } from './studentExportParams.mjs';
import { referenceLabel, displayReferenceOptions } from '@/utils/referenceLabels.mjs';

const toast = useToast();
const doughnutData = ref(null);
const ChartStudentsData = ref(null);
const barData = ref(null);
const doughnutOptions = ref(null);
const ChartStudentsOptions = ref(null);
const ChartReligionsData = ref(null);
const ChartReligionsOptions = ref(null);
const rekapReligionsList = ref([]);
const rekapReligionsTotal = ref(0);
const barOptions = ref(null);
const activeTab = ref('');
const recapClassCounts = ref([]);
const dt = ref();
const useAuthStore = authStore();
const school_years = ref([]);
const school_years_now = ref('');
const school_years_pick = ref([]);
const searchQuery = ref([]);
const filtered_Cat1 = ref([]);
const active_students = ref('');
const mutation_students = ref('');
const dropout_students = ref('');
const graduation_students = ref('');
// ── 03c: Daftar siswa server-side (pagination/pencarian/sort/filter/rekap) ──
const listRows = ref([]);
const listMeta = ref({ current_page: 1, per_page: 20, total: 0, last_page: 1 });
const listRecap = ref({ total_matches: 0, placed_count: 0, unplaced_count: 0, by_gender: [], by_rombel: [] });
const listLoading = ref(false);
const listError = ref('');
const listSearch = ref('');
const listPlacement = ref('all');
const listRombelId = ref(null);
const listSortField = ref('name');
const listSortOrder = ref(1);
const listPage = ref(1);
const listRequestId = ref(0);
const listDebounceTimer = ref(null);
const rombelOptions = ref([]);
const placementOptions = [
    { label: 'All', value: 'all' },
    { label: 'Placed', value: 'placed' },
    { label: 'Unplaced', value: 'unplaced' },
];

const saveUser = ref([]);

const visibleStaticBackdropWarning = ref(false);
const visibleStaticBackdropDanger = ref(false);
var titleDanger = ref('');
var fieldDanger = ref('');
const visibleStaticBackdropSuccess = ref(false);
var titleSuccess = ref('');
var fieldSuccess = ref('');
const visibleStaticBackdropWaiting = ref(false);

//method fetchDataSchoolYears
const fetchDataSchoolYears = async () => {
    //fetch data
    school_years.value.length = 0;
    await apiLogin.get('/api/school-years')
    .then(response => {
        //set response data to state "Students"
        school_years.value = response.data.data;
        // console.log(school_years.value);
        fetchDataSelected();
    });

}

//method fetchDataSelected
const fetchDataSelected = async () => {
    //fetch data
    school_years_pick.value.length = 0;
    loading.value = true;
    let years_now = new Date().getFullYear();
    let years_tomorrow = years_now + 1;
    let month_now = new Date().getMonth() + 1;
    let years_combine = '';
    if (month_now > 6) {
        years_combine = years_now+"/"+years_tomorrow;
    } else {
        years_combine = (years_now-1)+"/"+(years_tomorrow-1);
    }
    // let years_combine = years_now+"/"+years_tomorrow;
    if (!school_years_now.value) {
        school_years_now.value = school_years.value.find(school_year => school_year.name === years_combine);
        fetchDataStudentRombels();
        // console.log("school_years is reset");
    } else {
        fetchDataStudentRombels();
        // console.log("school_years is : "+school_years_now.value.name);
    }
    // console.log(school_years_now.value);
}

const fetchRombelOptions = async () => {
    if (!school_years_now.value?.id) {
        rombelOptions.value = [];
        return;
    }
    try {
        const res = await apiLogin.get('/api/school-rombels');
        rombelOptions.value = (res.data.data ?? [])
            .filter((rombel) => rombel.school_year_id === school_years_now.value.id)
            .map((rombel) => ({ id: rombel.id, name: rombel.name }));
    } catch {
        rombelOptions.value = [];
    }
};

// 03c: daftar siswa server-side. Respons usang (requestId lama) diabaikan.
const fetchStudentList = async () => {
    if (!school_years_now.value?.id) return;

    const requestId = ++listRequestId.value;
    listLoading.value = true;
    listError.value = '';

    if (listPlacement.value === 'unplaced') {
        listRombelId.value = null;
    }

    try {
        const params = {
            school_year_id: school_years_now.value.id,
            page: listPage.value,
            per_page: length_rows_pick.value,
            placement: listPlacement.value,
            sort: listSortField.value,
            direction: listSortOrder.value === -1 ? 'desc' : 'asc',
        };
        if (listSearch.value.trim()) params.search = listSearch.value.trim();
        if (listRombelId.value && listPlacement.value !== 'unplaced') params.rombel = listRombelId.value;

        const res = await apiLogin.get('/api/students', { params });
        if (requestId !== listRequestId.value) return;

        listRows.value = res.data.data ?? [];
        listMeta.value = res.data.meta ?? { current_page: 1, per_page: length_rows_pick.value, total: 0, last_page: 1 };
        listRecap.value = res.data.recap ?? { total_matches: 0, placed_count: 0, unplaced_count: 0, by_gender: [], by_rombel: [] };

        // Halaman terakhir menjadi kosong (mis. setelah archive) → mundur satu halaman.
        if (listRows.value.length === 0 && listPage.value > 1 && listPage.value > listMeta.value.last_page) {
            listPage.value = Math.max(1, listMeta.value.last_page);
            return fetchStudentList();
        }
    } catch (error) {
        if (requestId !== listRequestId.value) return;
        listRows.value = [];
        listError.value = error.response?.data?.message ?? 'Failed to load students. Please try again.';
    } finally {
        if (requestId === listRequestId.value) listLoading.value = false;
    }
};

const onListPage = (event) => {
    listPage.value = event.page + 1;
    length_rows_pick.value = event.rows;
    fetchStudentList();
};

const onListSort = (event) => {
    listSortField.value = event.sortField || 'name';
    listSortOrder.value = event.sortOrder ?? 1;
    listPage.value = 1;
    fetchStudentList();
};

const onListFilterChange = () => {
    listPage.value = 1;
    fetchStudentList();
};

const onListSearchInput = () => {
    if (listDebounceTimer.value) clearTimeout(listDebounceTimer.value);
    listDebounceTimer.value = setTimeout(onListFilterChange, 300);
};

const rowDisplayNumber = (index) => (listMeta.value.current_page - 1) * listMeta.value.per_page + index + 1;

//method fetchDataStudentRombels
const fetchDataStudentRombels = async () => {
    //fetch data
    school_years_pick.value.length = 0;
    dt.value = null;
    await apiLogin.get(`/api/student-rombels/${school_years_now.value.id}`)
    .then(response => {
        //set response data to state "Students"
        school_years_pick.value = response.data.data;
        loading.value = false;
        let index_active_students = 0;
        let index_mutation_students = 0;
        let index_dropout_students = 0;
        let index_graduation_students = 0;

        // Mengelompokkan data berdasarkan status dalam satu kali jalan
        const counts = school_years_pick.value.reduce((acc, student) => {
            // Cek tahun ajaran
            const status = student.student_status_id;
            acc[status] = (acc[status] || 0) + 1;
            return acc;
        }, {});

        // Sekarang Anda tinggal mengambil hasilnya tanpa perlu looping berkali-kali
        index_active_students = counts[1] || 0;
        index_mutation_students = counts[2] || 0;
        index_dropout_students = counts[3] || 0;
        index_graduation_students = counts[4] || 0;
        if (school_years_pick.value.length > 0) {

            active_students.value = index_active_students;
            mutation_students.value = index_mutation_students;
            dropout_students.value = index_dropout_students;
            graduation_students.value = index_graduation_students;
        } else {
            active_students.value = 0;
            mutation_students.value = 0;
            dropout_students.value = 0;
            graduation_students.value = 0;

        }
        // console.log(school_years_pick.value);
    });
    fetchRombelOptions();
    fetchStudentList();
    updateChartData();
}

//method fetchDataUserRole
const fetchDataUserRole = async () => {
    // Muat kemampuan efektif (mis. students.export) untuk menampilkan tombol sesuai izin.
    await useAuthStore.checkLogin();
    await useAuthStore.getUser();
    // console.log(useAuthStore.AllUserAssign);
    saveUser.value = useAuthStore.AllUserAssign;

    fetchDataSchoolYears();

    // fetchDataStudentRombels();
};

function handleSchoolYearsChange() {
    listPage.value = 1;
    listRombelId.value = null;
    fetchDataSelected();
}

const loading = ref(true);
const length_rows_pick = ref(20);
const length_rows = ref([10, 20, 50, 100]);

const getSeverity = (status) => {
    switch (status) {
        case 'unqualified':
            return 'danger';

        case 'qualified':
            return 'success';

        case 'new':
            return 'info';

        case 'negotiation':
            return 'warn';

        case 'Laki-Laki':
            return 'success';

        case 'Perempuan':
            return 'info';

        case 'Aktif':
            return 'success';

        case 'Lulus':
            return 'info';

        case 'Mutasi Keluar':
            return 'danger';

        case 'Drop Out':
            return 'danger';

        case 'Siswa Baru':
            return 'success';

        case 'Mutasi Masuk':
            return 'info';

        case 'renewal':
            return null;
    }
}

const exportCSV = async () => {
    // Fase 01a: unduhan via endpoint backend terlindungi (students.view + students.export).
    // Backend menegakkan izin & lingkup data; perubahan izin berlaku pada request berikutnya.
    if (!school_years_now.value || !school_years_now.value.id) {
        toast.add({ severity: 'error', summary: 'Export', detail: 'Please select a school year first.', life: 3000 });
        return;
    }
    try {
        const params = buildStudentExportParams({
            schoolYearId: school_years_now.value.id,
            placement: listPlacement.value,
            search: listSearch.value,
            rombelId: listRombelId.value,
        });
        const response = await apiLogin.get('/api/students/export', {
            params,
            responseType: 'blob'
        });
        const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8;' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `students_${school_years_now.value.id}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    } catch (error) {
        let detail = 'Failed to export students. Please try again.';
        if (error.response?.status === 403) {
            detail = 'You do not have permission to export students.';
        } else if (error.response?.status === 422) {
            // Backend mengirim pesan (mis. melebihi batas baris) sebagai blob.
            try {
                const text = await error.response.data.text();
                detail = JSON.parse(text)?.message || detail;
            } catch {
                detail = 'The export could not be completed. Please narrow your filters.';
            }
        }
        toast.add({ severity: 'error', summary: 'Export', detail, life: 4000 });
    }
};

const clearFilter = () => {
    listSearch.value = '';
    listPlacement.value = 'all';
    listRombelId.value = null;
    listSortField.value = 'name';
    listSortOrder.value = 1;
    onListFilterChange();
};

// Tab item murni (tanpa efek samping) — DEC-22 lint terarah.
const tabItems = computed(() =>
    Object.keys(recapClassCounts.value).map(level => ({ label: `Class ${level}`, value: level }))
);

const updateChartData = () => {
    const documentStyle = getComputedStyle(document.documentElement);
    const textColor = documentStyle.getPropertyValue('--text-color');
    const textColorSecondary = documentStyle.getPropertyValue('--text-color-secondary');
    const surfaceBorder = documentStyle.getPropertyValue('--surface-border');
    // console.log(school_years_pick.value);
    const totalStudents = school_years_pick.value.length;
    const selectedLevel = activeTab.value;
    if (totalStudents === 0) return;
    // if (!school_years_pick.value.length) return { list_religion: [], total_religion: 0 };

    const religionCounts = {};
    let relTotal = 0;

    // ── 1. GENDER PIE CHART (Unchanged) ──
    const maleCount = school_years_pick.value.filter(s => s.student_nik?.gender?.name === 'Laki-Laki').length;
    const femaleCount = totalStudents - maleCount;

    ChartStudentsData.value = {
        labels: ['Male', 'Female'],
        datasets: [
            {
                data: [maleCount, femaleCount],
                backgroundColor: ['rgba(54, 162, 235, 0.8)', 'rgba(255, 99, 132, 0.8)'],
                hoverBackgroundColor: ['rgba(54, 162, 235, 1)', 'rgba(255, 99, 132, 1)']
            }
        ]
    };
    ChartStudentsOptions.value = {
        plugins: {
            legend: {
                labels: {
                    usePointStyle: true,
                    color: textColor
                }
            }
        }
    };

    // ── 2. DYNAMICALLY GENERATED DOUGHNUT CHART ──
    // Instead of hardcoding, we initialize an empty object
    const gradeCounts = {};
    const recapClassCountss = {};

    school_years_pick.value.forEach(student => {
        // 1. Fetch your names safely from the API payload
        const levelName  = student.school_rombel?.school_level?.name || 'Unknown'; // e.g., "X"
        const level2Name = student.school_rombel?.name || 'Unknown';              // e.g., "X - 1"
        const relName = student.student_nik?.religion?.name || 'Unknown';

        // If the key doesn't exist in our object yet, initialize it to 0, then add 1
        if (!gradeCounts[levelName]) {
            gradeCounts[levelName] = 0;
        }
        gradeCounts[levelName]++;

        if (!religionCounts[relName]) {
            religionCounts[relName] = 0;
        }
        religionCounts[relName]++;
        relTotal++;

        // Using your exact API path: student.student_nik?.gender?.name
        // Let's normalize it to "M" or "F" (or map 'Laki-laki'/'Perempuan' cleanly)
        const rawGender = student.student_nik?.gender?.name || 'Unknown';
        const genderKey = (rawGender === 'Laki-Laki' || rawGender === 'Male' || rawGender === 'M') ? 'M' :
                        (rawGender === 'Perempuan' || rawGender === 'Female' || rawGender === 'F') ? 'F' : 'Unknown';

        // ── LAYER 1: Initialize the School Level (e.g., "X") ──
        if (!recapClassCountss[levelName]) {
            recapClassCountss[levelName] = {};
        }

        // ── LAYER 2: Initialize the Class/Rombel (e.g., "X - 1") ──
        if (!recapClassCountss[levelName][level2Name]) {
            recapClassCountss[levelName][level2Name] = {};
        }

        // ── LAYER 3: Initialize and increment the Gender key ("M" or "F") ──
        if (!recapClassCountss[levelName][level2Name][genderKey]) {
            recapClassCountss[levelName][level2Name][genderKey] = 0;
        }
        recapClassCountss[levelName][level2Name][genderKey]++;
    });
    recapClassCounts.value = recapClassCountss;

    // Pilih tab default di luar computed (menghindari efek samping pada computed).
    const levelNames = Object.keys(recapClassCountss);
    if (levelNames.length > 0 && (!activeTab.value || !levelNames.includes(activeTab.value))) {
        activeTab.value = levelNames[0];
    }

    const religionNames = Object.keys(religionCounts);
    const religionValues = Object.values(religionCounts);

    // Generate a beautiful, unique color scheme dynamically based on how many categories exist
    const dynamicColors = Object.keys(gradeCounts).map((_, index) => {
        const colors = [
            'rgba(75, 192, 192, 0.8)',  // Teal
            'rgba(54, 162, 235, 0.8)',  // Blue
            'rgba(153, 102, 255, 0.8)', // Purple
            'rgba(255, 159, 64, 0.8)',  // Orange
            'rgba(255, 99, 132, 0.8)',  // Red
            'rgba(255, 206, 86, 0.8)'   // Yellow
        ];
        return colors[index % colors.length]; // Loops through colors safely if there are many levels
    });

    doughnutData.value = {
        // Object.keys extract the names from your API data (e.g., "X", "XI", "XII")
        labels: Object.keys(gradeCounts),
        datasets: [
            {
                // Object.values extracts the counted numbers
                data: Object.values(gradeCounts),
                backgroundColor: dynamicColors,
                hoverBackgroundColor: dynamicColors.map(color => color.replace('0.8', '1')) // Makes color solid on hover
            }
        ]
    };
    doughnutOptions.value = {
        plugins: {
            legend: {
                labels: {
                    usePointStyle: true,
                    color: textColor
                }
            }
        }
    };

    if (!selectedLevel || !recapClassCounts.value[selectedLevel]) {
        barData.value = { labels: [], datasets: [] };
        return;
    }
    // Get all classrooms for the chosen level (e.g., ["X - 1", "X - 2"])
    const classes = Object.keys(recapClassCounts.value[selectedLevel]);

    // Extract the Male and Female totals for each classroom dynamically
    const maleData = classes.map(className => recapClassCounts.value[selectedLevel][className]['M'] || 0);
    const femaleData = classes.map(className => recapClassCounts.value[selectedLevel][className]['F'] || 0);

    barData.value = {
        labels: classes,
        datasets: [
            {
                label: 'Male',
                backgroundColor: 'rgba(54, 162, 235, 0.8)',
                borderColor: 'rgba(54, 162, 235, 0.8)',
                data: maleData
            },
            {
                label: 'Female',
                backgroundColor: 'rgba(255, 99, 132, 0.8)',
                borderColor: 'rgba(255, 99, 132, 0.8)',
                data: femaleData
            }
        ]
    };
    barOptions.value = {
        plugins: {
            legend: {
                labels: {
                    fontColor: textColor
                }
            }
        },
        scales: {
            x: {
                ticks: {
                    color: textColorSecondary,
                    font: {
                        weight: 500
                    }
                },
                grid: {
                    display: false,
                    drawBorder: false
                }
            },
            y: {
                ticks: {
                    color: textColorSecondary
                },
                grid: {
                    color: surfaceBorder,
                    drawBorder: false
                }
            }
        }
    };

    const religionColors = religionNames.map((_, index) => {
        const colors = [
            'rgba(54, 162, 235, 0.8)',  // Blue
            'rgba(255, 99, 132, 0.8)',  // Pink
            'rgba(75, 192, 192, 0.8)',  // Teal
            'rgba(255, 206, 86, 0.8)',  // Yellow
            'rgba(153, 102, 255, 0.8)', // Purple
            'rgba(255, 159, 64, 0.8)'   // Orange
        ];
        return colors[index % colors.length];
    });

    ChartReligionsData.value = {
        labels: religionNames.map(name => referenceLabel('religions', name)),
        datasets: [
            {
                data: religionValues,
                backgroundColor: religionColors,
                hoverBackgroundColor: religionColors.map(color => color.replace('0.8', '1'))
            }
        ]
    };

    ChartReligionsOptions.value = {
        plugins: {
            legend: {
                labels: {
                    usePointStyle: true,
                    color: textColor
                }
            }
        }
    };

    rekapReligionsList.value = religionNames.map(name => ({
        name: name,
        count: religionCounts[name]
    }));
    rekapReligionsTotal.value = relTotal;
};

// ── Student Group Navigation ───────────────────────────────────────────────
const activeGroup = ref('demographics');
const activeSub = ref('section-students');

const groupItems = [
    {
        key:   'demographics',
        label: 'Demographics',
        icon:  'pi pi-users',
        subItems: [
            { label: 'Students',         icon: 'pi pi-star',                 id: 'section-students' },
            { label: 'Genders',           icon: 'pi pi-heart',           id: 'section-gender' },
            { label: 'Religions',         icon: 'pi pi-heart',                id: 'section-religion' },
            { label: 'Class Breakdown',  icon: 'pi pi-chart-bar',            id: 'section-class-breakdown' },
            { label: 'Student Profile',  icon: 'pi pi-id-card',              id: 'section-profile-student' },
        ],
    },
    {
        key:   'administration',
        label: 'Administration',
        icon:  'pi pi-briefcase',
        subItems: [
            { label: 'F8355',           icon: 'pi pi-file-edit',             id: 'section-f8355' },
            { label: 'KJP',             icon: 'pi pi-wallet',                id: 'section-kjp' },
            { label: 'PPDB',            icon: 'pi pi-file-plus',             id: 'section-ppdb' },
            { label: 'Dapodik Status',  icon: 'pi pi-check-circle',          id: 'section-dapodik' },
            { label: 'Transfer',        icon: 'pi pi-arrow-right-arrow-left',id: 'section-mutation' },
        ],
    },
    {
        key:   'relationships',
        label: 'Relationships',
        icon:  'pi pi-sitemap',
        subItems: [
            { label: 'Parents',          icon: 'pi pi-home',                 id: 'section-parents' },
            { label: 'Homeroom Teacher', icon: 'pi pi-graduation-cap',       id: 'section-guardian' },
        ],
    },
];

const activeGroupData = computed(() =>
    groupItems.find(g => g.key === activeGroup.value)
);

const setActiveGroup = (key) => {
    activeGroup.value = key;
    const group = groupItems.find(g => g.key === key);
    activeSub.value = group?.subItems[0]?.id ?? null;
};

const setActiveSub = (subId) => {
    activeSub.value = subId;
    updateChartData();
};

// ── Rekapitulasi data — built from existing school_years_pick ─────────────
const rekapData = computed(() => {
    if (!school_years_pick.value.length) {
        return { levels: [], grandL: 0, grandP: 0, grandTotal: 0 };
    }

    const levelsMap = {};

    school_years_pick.value.forEach(student => {
        const levelName  = student.school_rombel?.school_level?.name ?? 'Unknown';
        const rombelName = student.school_rombel?.name               ?? 'Unknown';
        const majorName  = student.school_rombel?.student_major?.display_name ?? student.school_rombel?.student_major?.name ?? '-';
        const isMale     = student.student_nik?.gender?.name === 'Laki-Laki';

        if (!levelsMap[levelName]) {
            levelsMap[levelName] = { rombels: {}, totalL: 0, totalP: 0 };
        }
        if (!levelsMap[levelName].rombels[rombelName]) {
            levelsMap[levelName].rombels[rombelName] = { major: majorName, L: 0, P: 0 };
        }

        if (isMale) {
            levelsMap[levelName].rombels[rombelName].L++;
            levelsMap[levelName].totalL++;
        } else {
            levelsMap[levelName].rombels[rombelName].P++;
            levelsMap[levelName].totalP++;
        }
    });

    let grandL = 0, grandP = 0;

    const levels = Object.entries(levelsMap).map(([name, data]) => {
        grandL += data.totalL;
        grandP += data.totalP;
        return {
            name,
            rombels: Object.entries(data.rombels).map(([rombelName, rd]) => ({
                name:  rombelName,
                major: rd.major,
                L:     rd.L,
                P:     rd.P,
                total: rd.L + rd.P,
            })),
            totalL:   data.totalL,
            totalP:   data.totalP,
            totalAll: data.totalL + data.totalP,
        };
    });

    return { levels, grandL, grandP, grandTotal: grandL + grandP };
});

// ── Profile Student ────────────────────────────────────────────────────────
const selectedStudent   = ref(null);
const profileDialog     = ref(false);

const printProfile = () => {
    const el = document.getElementById('student-profile-print');
    if (!el) return;
    const win = window.open('', '_blank');
    win.document.write(`
        <html>
        <head>
            <title>Student Profile - ${selectedStudent.value?.student_nik?.name}</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, sans-serif; font-size: 11pt; }
                body { padding: 20px; color: #000; }
                h2 { text-align: center; font-size: 14pt; margin-bottom: 16px; text-transform: uppercase; }
                .section-title { font-weight: bold; border-bottom: 1px solid #000; padding-bottom: 4px; margin: 12px 0 8px; }
                .row { display: flex; margin-bottom: 4px; padding-left: 16px; }
                .label { width: 180px; flex-shrink: 0; }
                .colon { width: 12px; flex-shrink: 0; }
                .value { flex: 1; font-weight: bold; }
                .value.normal { font-weight: normal; }
                .inline { display: flex; gap: 32px; }
                .signature { margin-top: 40px; text-align: right; }
            </style>
        </head>
        <body>${el.innerHTML}</body>
        </html>
    `);
    win.document.close();
    win.print();
};

const search_Cat1 = (event) => {
    setTimeout(() => {
        if (!event.query.trim().length) {
            filtered_Cat1.value = [...school_years_pick.value];
        } else {
            filtered_Cat1.value = school_years_pick.value.filter((level) => {
                const [year, month, day] = level.student_nik.birthdate.split('-')
                const dateObj = new Date(Date.UTC(year, month - 1, day))

                // Format into a human-readable string (e.g., August 26, 2008)
                level.student_nik.birthdate_human = new Intl.DateTimeFormat('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                }).format(dateObj);
                return level.student_nik.name.toLowerCase().includes(event.query.toLowerCase());
            });
        }
    }, 250);
};

// ── 03b: Student CRUD (create / edit / detail / archive / restore) ─────────
// Semua label & pesan English (DEC-14). Izin: students.view (baca) / students.manage (mutasi).
const canManage    = computed(() => useAuthStore.can('students.manage'));
const studentFormKeys = [
    'name', 'nik', 'nis', 'nisn', 'birthplace', 'birthdate',
    'gender_id', 'religion_id', 'education_level_id', 'blood_type_id', 'profession_id', 'family_status_id',
    'village_id', 'zip_code', 'rt_num', 'rw_num', 'address', 'hp_num',
    'kk', 'akta', 'child_order_to', 'child_order_total', 'body_height', 'body_weight', 'history_illness',
    'father_name', 'father_nik', 'father_birthplace', 'father_birthdate', 'father_education_level_id', 'father_profession_id', 'father_income', 'father_hp_num',
    'mother_name', 'mother_nik', 'mother_birthplace', 'mother_birthdate', 'mother_education_level_id', 'mother_profession_id', 'mother_income', 'mother_hp_num',
    'guardian_name', 'guardian_nik', 'guardian_birthplace', 'guardian_birthdate', 'guardian_education_level_id', 'guardian_profession_id', 'guardian_income', 'guardian_hp_num',
    'check_ppdbbersama', 'check_kjp', 'check_bpms', 'check_pip',
];

const studentFormDialog   = ref(false);
const studentDetailDialog = ref(false);
const archiveDialog       = ref(false);
const archivedListDialog  = ref(false);
const conflictDialog      = ref(false);
const conflictMessage     = ref('');
const conflictReloadAction = ref(null);
const reloadingConflict   = ref(false);
const isSubmitting        = ref(false);
const submitted           = ref(false);
const formErrors          = ref({});
const studentForm         = ref({});
const editTargetId        = ref(null);
const detailStudent       = ref(null);
const archiveTarget       = ref({});
const archiveReason       = ref('');
const archiveErrors       = ref({});
const archivedStudents    = ref([]);
const archivedLoading     = ref(false);
const formOptions         = ref({ genders: [], religions: [], education_levels: [], blood_types: [], professions: [], family_statuses: [] });
const formOptionsLoaded   = ref(false);

// 03d: Assign & Transfer Rombel
const placementDialog        = ref(false);
const placementMode          = ref('assign');
const placementTarget        = ref({});
const placementForm          = ref({ target_rombel_id: null, reason: '' });
const placementErrors        = ref({});
const isSubmittingPlacement  = ref(false);

const availablePlacementRombels = computed(() => {
    if (placementMode.value === 'transfer') {
        return rombelOptions.value.filter((r) => r.id !== placementTarget.value.rombelId);
    }
    return rombelOptions.value;
});

const emptyStudentForm = () => {
    const form = {};
    studentFormKeys.forEach((key) => { form[key] = ['check_ppdbbersama', 'check_kjp', 'check_bpms', 'check_pip'].includes(key) ? false : null; });
    form.name = '';
    form.version = null;
    return form;
};

const rowStudentId = (row) => row?.id ?? row?.student_id ?? row?.student?.id ?? row?.student_nik?.id ?? null;

const fetchStudentFormOptions = async () => {
    if (formOptionsLoaded.value) return;
    const [genders, religions, levels, bloodTypes, professions, familyStatuses] = await Promise.all([
        apiLogin.get('/api/genders'),
        apiLogin.get('/api/religions'),
        apiLogin.get('/api/education-levels'),
        apiLogin.get('/api/blood-types'),
        apiLogin.get('/api/professions'),
        apiLogin.get('/api/family-status'),
    ]);
    const normalize = (res) => res.data.data ?? [];
    formOptions.value = {
        genders: displayReferenceOptions('genders', normalize(genders)),
        religions: displayReferenceOptions('religions', normalize(religions)),
        education_levels: displayReferenceOptions('education-levels', normalize(levels)),
        blood_types: normalize(bloodTypes),
        professions: displayReferenceOptions('professions', normalize(professions)),
        family_statuses: displayReferenceOptions('family-statuses', normalize(familyStatuses)),
    };
    formOptionsLoaded.value = true;
};

const mapStudentToForm = (student) => {
    const form = emptyStudentForm();
    studentFormKeys.forEach((key) => {
        if (student[key] !== undefined) form[key] = student[key];
    });
    form.name = student.name ?? '';
    form.version = student.version ?? null;
    return form;
};

const loadStudentIntoForm = async (id) => {
    const res = await apiLogin.get(`/api/students/${id}`);
    studentForm.value = mapStudentToForm(res.data.data);
};

const openCreateStudent = async () => {
    await fetchStudentFormOptions();
    studentForm.value = emptyStudentForm();
    editTargetId.value = null;
    formErrors.value = {};
    submitted.value = false;
    studentFormDialog.value = true;
};

const openEditStudent = async (row) => {
    const id = rowStudentId(row);
    if (!id) {
        toast.add({ severity: 'error', summary: 'Edit Student', detail: 'Student record is not linked to a master record and cannot be edited.', life: 4000 });
        return;
    }
    await fetchStudentFormOptions();
    await loadStudentIntoForm(id);
    editTargetId.value = id;
    formErrors.value = {};
    submitted.value = false;
    studentFormDialog.value = true;
};

const viewStudentDetail = async (row) => {
    const id = rowStudentId(row);
    if (!id) {
        toast.add({ severity: 'error', summary: 'Student Detail', detail: 'Student record is not linked to a master record.', life: 4000 });
        return;
    }
    try {
        const res = await apiLogin.get(`/api/students/${id}`);
        detailStudent.value = res.data.data;
        studentDetailDialog.value = true;
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Student Detail', detail: error.response?.data?.message ?? 'Failed to load student details.', life: 4000 });
    }
};

const buildStudentPayload = (form) => {
    const payload = {};
    studentFormKeys.forEach((key) => { payload[key] = form[key]; });
    return payload;
};

const submitStudentForm = async () => {
    if (isSubmitting.value) return;
    submitted.value = true;
    formErrors.value = {};

    if (!studentForm.value.name || !String(studentForm.value.name).trim()) return;

    isSubmitting.value = true;
    try {
        const payload = buildStudentPayload(studentForm.value);
        if (editTargetId.value) {
            payload.version = studentForm.value.version;
            const res = await apiLogin.put(`/api/students/${editTargetId.value}`, payload);
            toast.add({ severity: 'success', summary: 'Student Updated', detail: `${res.data.data.name} has been updated successfully.`, life: 4000 });
            studentFormDialog.value = false;
            if (studentDetailDialog.value && detailStudent.value?.id === editTargetId.value) {
                detailStudent.value = res.data.data;
            }
            fetchStudentList();
        } else {
            const res = await apiLogin.post('/api/students', payload);
            toast.add({ severity: 'success', summary: 'Student Created', detail: `${res.data.data.name} has been created successfully.`, life: 4000 });
            studentFormDialog.value = false;
            detailStudent.value = res.data.data;
            studentDetailDialog.value = true;
            // Siswa baru tanpa rombel tetap tampil pada All/Unplaced setelah refresh.
            fetchStudentList();
        }
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            formErrors.value = error.response.data.errors ?? {};
        } else if (status === 409) {
            conflictMessage.value = error.response.data.message ?? 'Conflict: this student record was updated by another operator.';
            conflictReloadAction.value = () => loadStudentIntoForm(editTargetId.value);
            conflictDialog.value = true;
        } else {
            toast.add({ severity: 'error', summary: 'Save Failed', detail: error.response?.data?.message ?? 'An unexpected error occurred.', life: 4000 });
        }
    } finally {
        isSubmitting.value = false;
    }
};

const fieldError = (field) => formErrors.value?.[field]?.[0] ?? '';

const reloadAfterConflict = async () => {
    if (reloadingConflict.value) return;
    reloadingConflict.value = true;
    try {
        if (conflictReloadAction.value) await conflictReloadAction.value();
        formErrors.value = {};
        conflictDialog.value = false;
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Reload Failed', detail: error.response?.data?.message ?? 'Failed to reload the latest data.', life: 4000 });
    } finally {
        reloadingConflict.value = false;
    }
};

const openArchiveStudent = async (row) => {
    const id = rowStudentId(row);
    if (!id) {
        toast.add({ severity: 'error', summary: 'Archive Student', detail: 'Student record is not linked to a master record.', life: 4000 });
        return;
    }
    try {
        const res = await apiLogin.get(`/api/students/${id}`);
        archiveTarget.value = { id, name: res.data.data.name, version: res.data.data.version };
        archiveReason.value = '';
        archiveErrors.value = {};
        archiveDialog.value = true;
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Archive Student', detail: error.response?.data?.message ?? 'Failed to load student data.', life: 4000 });
    }
};

const confirmArchiveStudent = async () => {
    if (isSubmitting.value) return;
    archiveErrors.value = {};
    if (!archiveReason.value || !archiveReason.value.trim()) {
        archiveErrors.value = { archive_reason: ['An administrative archiving reason is required.'] };
        return;
    }
    isSubmitting.value = true;
    try {
        await apiLogin.delete(`/api/students/${archiveTarget.value.id}`, {
            data: { version: archiveTarget.value.version, archive_reason: archiveReason.value.trim() },
        });
        toast.add({ severity: 'success', summary: 'Student Archived', detail: `${archiveTarget.value.name} has been archived.`, life: 4000 });
        archiveDialog.value = false;
        if (school_years_now.value) fetchDataStudentRombels();
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            archiveErrors.value = error.response.data.errors ?? {};
        } else if (status === 409) {
            conflictMessage.value = error.response.data.message ?? 'Conflict: this student record was modified by another operator.';
            conflictReloadAction.value = async () => {
                const res = await apiLogin.get(`/api/students/${archiveTarget.value.id}`);
                archiveTarget.value = { id: res.data.data.id, name: res.data.data.name, version: res.data.data.version };
            };
            archiveDialog.value = false;
            conflictDialog.value = true;
        } else {
            toast.add({ severity: 'error', summary: 'Archive Failed', detail: error.response?.data?.message ?? 'An unexpected error occurred.', life: 4000 });
        }
    } finally {
        isSubmitting.value = false;
    }
};

const openArchivedList = async () => {
    archivedListDialog.value = true;
    await fetchArchivedStudents();
};

const fetchArchivedStudents = async () => {
    archivedLoading.value = true;
    try {
        const res = await apiLogin.get('/api/students', { params: { only_archived: 1 } });
        archivedStudents.value = res.data.data ?? [];
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Archived Students', detail: error.response?.data?.message ?? 'Failed to load archived students.', life: 4000 });
    } finally {
        archivedLoading.value = false;
    }
};

const restoreStudent = async (student) => {
    if (isSubmitting.value) return;
    isSubmitting.value = true;
    try {
        await apiLogin.post(`/api/students/${student.id}/restore`, { version: student.version });
        toast.add({ severity: 'success', summary: 'Student Restored', detail: `${student.name} has been restored. Previous class membership stays inactive.`, life: 5000 });
        archivedStudents.value = archivedStudents.value.filter((item) => item.id !== student.id);
        if (school_years_now.value) fetchDataStudentRombels();
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            toast.add({ severity: 'error', summary: 'Restore Failed', detail: Object.values(error.response.data.errors ?? {}).flat().join(' ') || 'Cannot restore this student.', life: 5000 });
            await fetchArchivedStudents();
        } else if (status === 409) {
            toast.add({ severity: 'warn', summary: 'Conflict', detail: error.response.data.message ?? 'The student record changed. Reloading the latest list.', life: 5000 });
            await fetchArchivedStudents();
        } else {
            toast.add({ severity: 'error', summary: 'Restore Failed', detail: error.response?.data?.message ?? 'An unexpected error occurred.', life: 4000 });
        }
    } finally {
        isSubmitting.value = false;
    }
};

const openAssignRombel = async (row) => {
    const id = rowStudentId(row);
    if (!id) return;
    try {
        const res = await apiLogin.get(`/api/students/${id}`);
        const student = res.data.data;
        placementTarget.value = {
            id: student.id,
            name: student.name,
            nis: student.nis ?? '-',
            version: student.version,
            rombelId: null,
            rombelName: null,
            membershipVersion: null
        };
        placementMode.value = 'assign';
        placementForm.value = { target_rombel_id: null, reason: '' };
        placementErrors.value = {};
        placementDialog.value = true;
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Assign Class', detail: error.response?.data?.message ?? 'Failed to load student data.', life: 4000 });
    }
};

const openTransferRombel = async (row) => {
    const id = rowStudentId(row);
    if (!id) return;
    try {
        const res = await apiLogin.get(`/api/students/${id}`);
        const student = res.data.data;
        const currentYearId = school_years_now.value?.id;
        const activeMembership = (student.student_rombels ?? []).find(
            (sr) => sr.school_rombel?.school_year_id === currentYearId && !sr.deleted_at && !sr.archived_at && !sr.latest_student_exit_date
        );

        placementTarget.value = {
            id: student.id,
            name: student.name,
            nis: student.nis ?? '-',
            version: student.version,
            rombelId: activeMembership?.school_rombel?.id ?? row.rombel?.id ?? null,
            rombelName: activeMembership?.school_rombel?.name ?? row.rombel?.name ?? '-',
            membershipVersion: activeMembership?.version ?? 1
        };
        placementMode.value = 'transfer';
        placementForm.value = { target_rombel_id: null, reason: '' };
        placementErrors.value = {};
        placementDialog.value = true;
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Transfer Class', detail: error.response?.data?.message ?? 'Failed to load student data.', life: 4000 });
    }
};

const confirmPlacement = async () => {
    if (isSubmittingPlacement.value) return;
    placementErrors.value = {};

    if (!placementForm.value.target_rombel_id) {
        placementErrors.value = { target_rombel_id: ['Target class is required.'] };
        return;
    }

    isSubmittingPlacement.value = true;
    try {
        if (placementMode.value === 'assign') {
            await apiLogin.post(`/api/students/${placementTarget.value.id}/assign-rombel`, {
                school_year_id: school_years_now.value.id,
                school_rombel_id: placementForm.value.target_rombel_id,
                expected_student_version: placementTarget.value.version
            });
            toast.add({ severity: 'success', summary: 'Placement Success', detail: `${placementTarget.value.name} has been assigned to a class.`, life: 4000 });
        } else {
            await apiLogin.post(`/api/students/${placementTarget.value.id}/transfer-rombel`, {
                school_year_id: school_years_now.value.id,
                target_rombel_id: placementForm.value.target_rombel_id,
                expected_student_version: placementTarget.value.version,
                expected_membership_version: placementTarget.value.membershipVersion,
                reason: placementForm.value.reason?.trim() || null
            });
            toast.add({ severity: 'success', summary: 'Transfer Success', detail: `${placementTarget.value.name} has been transferred to a new class.`, life: 4000 });
        }
        placementDialog.value = false;
        if (school_years_now.value) fetchDataStudentRombels();
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            placementErrors.value = error.response.data.errors ?? {};
            if (error.response.data.message && !error.response.data.errors) {
                toast.add({ severity: 'error', summary: 'Validation Error', detail: error.response.data.message, life: 4000 });
            }
        } else if (status === 409) {
            conflictMessage.value = error.response.data.message ?? 'Conflict: student or class membership was modified by another operator.';
            conflictReloadAction.value = async () => {
                if (placementMode.value === 'assign') {
                    await openAssignRombel({ id: placementTarget.value.id });
                } else {
                    await openTransferRombel({ id: placementTarget.value.id, rombel: { id: placementTarget.value.rombelId } });
                }
            };
            placementDialog.value = false;
            conflictDialog.value = true;
        } else {
            toast.add({ severity: 'error', summary: 'Action Failed', detail: error.response?.data?.message ?? 'An unexpected error occurred.', life: 4000 });
        }
    } finally {
        isSubmittingPlacement.value = false;
    }
};

onMounted(() => {
    fetchDataUserRole();
    activeSub.value = groupItems[0].subItems[0].id;
});
</script>
<template>
    <Fluid>
        <div class="bg-surface-0 dark:bg-surface-900 px-6 py-8 md:px-12 lg:px-20">
            <div class="flex items-start flex-col md:justify-between md:flex-row">
                <div>
                    <div class="font-bold text-3xl text-surface-900 dark:text-surface-0 mb-4">Students - {{ school_years_now ? school_years_now.name : "" }}</div>
                    <div class="flex items-center text-surface-700 dark:text-surface-300 flex-wrap gap-8">
                        <!-- <div class="flex items-center gap-2">
                            <i class="pi pi-graduation-cap !text-base !leading-normal" />
                            <span v-if="students">{{ loading == false ? all_students : "" }}<Skeleton v-if="loading == true" width="3rem" height="2rem" /> All Students</span>
                        </div> -->
                        <!-- Student status counters -->
                        <div class="flex flex-wrap gap-4 text-md mt-2">
                            <span class="flex items-center gap-1 text-green-500">
                                <i class="pi pi-plus-circle" />
                                {{ loading == false ? active_students : "" }}
                                <Skeleton v-if="loading == true" width="3rem" height="2rem" />
                                Active Students
                            </span>
                            <span class="flex items-center gap-1 text-orange-500">
                                <i class="pi pi-minus-circle" />
                                {{ loading == false ? mutation_students : "" }}
                                <Skeleton v-if="loading == true" width="3rem" height="2rem" />
                                Transfer Students
                            </span>
                            <span class="flex items-center gap-1 text-red-500">
                                <i class="pi pi-minus-circle" />
                                {{ loading == false ? dropout_students : "" }}
                                <Skeleton v-if="loading == true" width="3rem" height="2rem" />
                                Drop Out Students
                            </span>
                            <span class="flex items-center gap-1 text-blue-500">
                                <i class="pi pi-star" />
                                {{ loading == false ? graduation_students : "" }}
                                <Skeleton v-if="loading == true" width="3rem" height="2rem" />
                                Graduation Students
                            </span>
                        </div>
                    </div>
                </div>
                <div class="mt-6 md:mt-0 flex items-center">
                    <!-- <Button label="Add" class="mr-3" outlined icon="pi pi-user-plus" />
                    <Button label="Save Changes" icon="pi pi-check" class="whitespace-nowrap" /> -->
                    <span class="me-2">School Years</span>
                        <Select v-model="school_years_now" showClear :options="school_years" optionLabel="name" placeholder="Select Year" class="w-full" @change="handleSchoolYearsChange">
                        </Select>
                </div>
            </div>
        </div>

        <div class="flex mt-8">
            <div class="card flex flex-col gap-4 w-full">
                <!-- ── Group Navigation Bar ─────────────────────────────────────────── -->
                <div class="flex items-center gap-2 px-3 py-2
                            border border-surface-200 dark:border-surface-700
                            rounded-lg bg-surface-0 dark:bg-surface-950">
                    <button
                        v-for="group in groupItems"
                        :key="group.key"
                        @click="setActiveGroup(group.key)"
                        class="flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium
                            transition-all duration-200 cursor-pointer select-none"
                        :class="activeGroup === group.key
                            ? 'bg-primary-500 text-white shadow-sm'
                            : 'text-surface-600 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800'"
                    >
                        <i :class="group.icon" />
                        <span>{{ group.label }}</span>
                        <i class="pi pi-angle-down text-xs opacity-70" />
                    </button>
                </div>

                <!-- ── Sub-item Pills (scroll anchors within active group) ───────────── -->
                <div class="flex flex-wrap gap-2 mt-3">
                    <button
                        v-for="sub in activeGroupData.subItems"
                        :key="sub.id"
                        @click="setActiveSub(sub.id)"
                        class="flex items-center gap-1 px-3 py-1 rounded-full text-xs
                            transition-all duration-150 cursor-pointer border"
                        :class="activeSub === sub.id
                            ? 'bg-primary-500 text-white border-primary-500 shadow-sm'
                            : 'text-surface-500 dark:text-surface-400 border-surface-200 dark:border-surface-700 hover:border-primary-400 hover:text-primary-500'"
                    >
                        <i :class="sub.icon" class="text-xs" />
                        {{ sub.label }}
                    </button>
                </div>

            </div>
        </div>

        <!-- ── DEMOGRAPHICS ─────────────────────────────────────────────────────── -->
        <div class="grid grid-cols-12 mt-8 gap-8" v-show="activeGroup === 'demographics' && activeSub === 'section-students'">
            <div id="section-students" class="col-span-12 xl:col-span-6">
                <div v-if="loading == true" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Chart Students</div>
                    <Skeleton shape="circle" size="20rem" class="mr-2"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Chart Students</div>
                    <span>No Students found.</span>
                </div>
                <div v-else class="card h-full flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Chart Students</div>
                    <Chart type="doughnut" :data="doughnutData" :options="doughnutOptions"></Chart>
                </div>
            </div>
            <div class="col-span-12 xl:col-span-6">
                <div v-if="loading == true" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total Students</div>
                    <Skeleton width="100%" height="200px"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total Students</div>
                    <span>No Students found.</span>
                </div>
                <div v-else class="card h-full flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total Students</div>
                    <table class="w-full text-sm border-collapse text-surface-900 dark:text-surface-0 border border-surface-300 dark:border-surface-600">
                        <thead>
                            <tr>
                                <th class="border-b px-3 py-2 text-center font-bold">
                                    Level
                                </th>
                                <th class="border-b px-3 py-2 text-center font-bold">
                                    Total
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Loop through the calculated levels (e.g., X, XI, XII) -->
                            <tr v-for="level in rekapData.levels" :key="level.name" class="border-b border-surface-200 dark:border-surface-700 hover:bg-surface-50 dark:hover:bg-surface-800">
                                <td class="px-8 py-2 text-center font-medium">{{ level.name }}</td>
                                <td class="px-8 py-2 text-center">{{ level.totalAll }}</td>
                            </tr>
                            <!-- Grand Total Row -->
                            <tr class="font-bold bg-surface-100 dark:bg-surface-800">
                                <td class="px-8 py-2 text-center">Total</td>
                                <td class="px-8 py-2 text-center">{{ rekapData.grandTotal }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="flex mt-8" v-show="activeGroup === 'demographics' && activeSub === 'section-students'">
            <div class="card flex flex-col gap-4 w-full">
                <DataTable
                    :value="listRows"
                    :lazy="true"
                    paginator
                    showGridlines
                    :rows="length_rows_pick"
                    :totalRecords="listMeta.total"
                    :first="(listMeta.current_page - 1) * listMeta.per_page"
                    :rowsPerPageOptions="length_rows"
                    dataKey="id"
                    :loading="listLoading"
                    removableSort
                    :sortField="listSortField"
                    :sortOrder="listSortOrder"
                    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                    currentPageReportTemplate="Showing {first} to {last} of {totalRecords} students"
                    @page="onListPage"
                    @sort="onListSort">
                    <template #header>
                        <div class="mb-3 flex flex-wrap gap-4 items-center text-sm">
                            <span>Total: <strong>{{ listRecap.total_matches }}</strong></span>
                            <span class="text-green-600">Placed: <strong>{{ listRecap.placed_count }}</strong></span>
                            <span class="text-orange-500">Unplaced: <strong>{{ listRecap.unplaced_count }}</strong></span>
                            <span v-for="group in listRecap.by_gender" :key="group.label">{{ referenceLabel('genders', group.label) }}: <strong>{{ group.total }}</strong></span>
                        </div>
                        <div class="flex flex-col xl:flex-row gap-4 xl:items-center">
                            <IconField class="w-full xl:w-64">
                                <InputIcon><i class="pi pi-search" /></InputIcon>
                                <InputText v-model="listSearch" placeholder="Search name, NIS, or NISN" class="w-full" @input="onListSearchInput" />
                            </IconField>
                            <Select v-model="listPlacement" :options="placementOptions" optionLabel="label" optionValue="value" placeholder="Placement" class="w-full xl:w-40" @change="onListFilterChange" />
                            <Select v-model="listRombelId" :options="rombelOptions" optionLabel="name" optionValue="id" placeholder="Class" showClear class="w-full xl:w-48" :disabled="listPlacement === 'unplaced'" @change="onListFilterChange" />
                            <div class="flex flex-wrap gap-2">
                                <Button v-if="useAuthStore.can('students.export')" icon="pi pi-external-link" label="Export" @click="exportCSV($event)" />
                                <Button v-if="canManage" icon="pi pi-user-plus" label="New Student" @click="openCreateStudent" />
                                <Button v-if="canManage" icon="pi pi-inbox" label="Archived" severity="secondary" variant="outlined" @click="openArchivedList" />
                                <Button type="button" icon="pi pi-filter-slash" label="Clear" variant="outlined" @click="clearFilter()" />
                            </div>
                        </div>
                    </template>
                    <template #loading> Loading students. Please wait. </template>
                    <template #empty>
                        <div class="flex flex-col items-center gap-3 py-6">
                            <span v-if="listError" class="text-red-500">{{ listError }}</span>
                            <span v-else>No students match the current filters.</span>
                            <Button v-if="listError" icon="pi pi-refresh" label="Retry" @click="fetchStudentList()" />
                        </div>
                    </template>
                    <Column header="No" style="width: 3rem">
                        <template #body="{ index }">{{ rowDisplayNumber(index) }}</template>
                    </Column>
                    <Column field="name" header="Name" sortable style="min-width: 12rem" />
                    <Column field="gender" header="Gender" style="min-width: 8rem">
                        <template #body="{ data }">
                            <Tag v-if="data.gender" :value="referenceLabel('genders', data.gender)" :severity="getSeverity(data.gender)" />
                        </template>
                    </Column>
                    <Column field="nis" header="NIS" sortable style="min-width: 8rem" />
                    <Column field="nisn" header="NISN" sortable style="min-width: 10rem" />
                    <Column header="Class" style="min-width: 10rem">
                        <template #body="{ data }">
                            <Tag v-if="data.rombel" :value="data.rombel.name" :severity="getSeverity(data.rombel.name)" />
                            <span v-else>-</span>
                        </template>
                    </Column>
                    <Column header="Placement" style="min-width: 8rem">
                        <template #body="{ data }">
                            <Tag :value="data.placement === 'placed' ? 'Placed' : 'Unplaced'" :severity="data.placement === 'placed' ? 'success' : 'warn'" />
                        </template>
                    </Column>
                    <Column header="Action" :exportable="false" style="min-width: 12rem">
                        <template #body="{ data }">
                            <Button icon="pi pi-eye" variant="outlined" rounded class="mr-2" v-tooltip.top="'View Detail'" @click="viewStudentDetail(data)" />
                            <Button v-if="canManage" icon="pi pi-pencil" variant="outlined" rounded class="mr-2" v-tooltip.top="'Edit Student'" @click="openEditStudent(data)" />
                            <Button v-if="canManage && data.placement === 'unplaced'" icon="pi pi-user-plus" variant="outlined" rounded severity="info" class="mr-2" v-tooltip.top="'Assign Class'" @click="openAssignRombel(data)" />
                            <Button v-if="canManage && data.placement === 'placed'" icon="pi pi-arrow-right-arrow-left" variant="outlined" rounded severity="warn" class="mr-2" v-tooltip.top="'Transfer Class'" @click="openTransferRombel(data)" />
                            <Button v-if="canManage" icon="pi pi-inbox" variant="outlined" rounded severity="danger" v-tooltip.top="'Archive Student'" @click="openArchiveStudent(data)" />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <div class="grid grid-cols-12 mt-8 gap-8" v-show="activeGroup === 'demographics' && activeSub === 'section-gender'">
            <div id="section-gender" class="col-span-12 xl:col-span-6">
                <div v-if="loading == true" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Chart Genders</div>
                    <Skeleton shape="circle" size="20rem" class="mr-2"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Chart Genders</div>
                    <span>No Chart Genders found.</span>
                </div>
                <div v-else class="card h-full flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Chart Genders</div>
                    <Chart type="pie" :data="ChartStudentsData" :options="ChartStudentsOptions"></Chart>
                </div>
            </div>
            <div id="section-gender" class="col-span-12 xl:col-span-6">
                <div v-if="loading == true" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total Genders</div>
                    <Skeleton width="100%" height="200px"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total Genders</div>
                    <span>No Students found.</span>
                </div>
                <div v-else class="card h-full flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total Genders</div>

                    <table class="w-full text-sm border-collapse text-surface-900 dark:text-surface-0 border border-surface-300 dark:border-surface-600 max-w-sm mx-auto">
                        <thead>
                            <tr>
                                <th class="border-b px-3 py-2 text-center font-bold">Gender</th>
                                <th class="border-b px-3 py-2 text-center font-bold">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b border-surface-200 dark:border-surface-700 hover:bg-surface-50 dark:hover:bg-surface-800">
                                <td class="px-8 py-4 text-center font-medium">Male</td>
                                <!-- Displays the grand total of Male students -->
                                <td class="px-8 py-4 text-center text-blue-500 font-bold">{{ rekapData.grandL }}</td>
                            </tr>
                            <tr class="border-b border-surface-200 dark:border-surface-700 hover:bg-surface-50 dark:hover:bg-surface-800">
                                <td class="px-8 py-4 text-center font-medium">Female</td>
                                <!-- Displays the grand total of Female students -->
                                <td class="px-8 py-4 text-center text-pink-500 font-bold">{{ rekapData.grandP }}</td>
                            </tr>
                            <tr class="font-bold bg-surface-100 dark:bg-surface-800">
                                <td class="px-8 py-4 text-center">Total</td>
                                <!-- Displays the combined total of all students -->
                                <td class="px-8 py-4 text-center">{{ rekapData.grandTotal }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-12 mt-8 gap-8" v-show="activeGroup === 'demographics' && activeSub === 'section-religion'">
            <div id="section-religion" class="col-span-12 xl:col-span-6">
                <div v-if="loading == true" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Chart Religions</div>
                    <Skeleton shape="circle" size="20rem" class="mr-2"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Chart Religions</div>
                    <span>No Religions found.</span>
                </div>
                <div v-else class="card h-full flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Chart Religions</div>
                    <Chart type="doughnut" :data="ChartReligionsData" :options="ChartReligionsOptions"></Chart>
                </div>
            </div>
            <div id="section-religion" class="col-span-12 xl:col-span-6">
                <div v-if="loading == true" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total Religions</div>
                    <Skeleton shape="circle" size="20rem" class="mr-2"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total Religions</div>
                    <span>No Religions found.</span>
                </div>
                <div v-else class="card h-full flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total Religions</div>

                    <table class="w-full text-sm border-collapse text-surface-900 dark:text-surface-0 border border-surface-300 dark:border-surface-600 max-w-sm mx-auto">
                        <thead>
                            <tr>
                                <th class="border-b px-3 py-2 text-center font-bold">Religion</th>
                                <th class="border-b px-3 py-2 text-center font-bold">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamically loops through the list generated in updateChartData -->
                            <tr v-for="rel in rekapReligionsList" :key="rel.name" class="border-b border-surface-200 dark:border-surface-700 hover:bg-surface-50 dark:hover:bg-surface-800">
                                <td class="px-8 py-4 text-center font-medium">{{ referenceLabel('religions', rel.name) }}</td>
                                <td class="px-8 py-4 text-center text-primary-500 font-bold">{{ rel.count }}</td>
                            </tr>
                            <!-- Grand Total Row -->
                            <tr class="font-bold bg-surface-100 dark:bg-surface-800">
                                <td class="px-8 py-4 text-center">Total</td>
                                <td class="px-8 py-4 text-center">{{ rekapReligionsTotal }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-12 mt-8 gap-8" v-show="activeGroup === 'demographics' && activeSub === 'section-class-breakdown'">
            <div id="section-class-breakdown" class="col-span-12">
                <div v-if="loading == true" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Chart Class Breakdown</div>
                    <Skeleton width="100%" height="150px"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Chart Class Breakdown</div>
                    <span>No Class Breakdown found.</span>
                </div>
                <div v-else class="card flex flex-col items-center h-full justify-center">
                    <div class="font-semibold text-xl mb-4">Chart Class Breakdown</div>

                    <Tabs v-model:value="activeTab" class="mb-4">
                        <TabList>
                            <Tab v-for="tab in tabItems" :key="tab.value" :value="tab.value">
                                {{ tab.label }}
                            </Tab>
                        </TabList>
                    </Tabs>

                    <Chart type="bar" :data="barData" :options="barOptions" class="w-full"></Chart>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-12 mt-8 gap-8" v-show="activeGroup === 'demographics' && activeSub === 'section-class-breakdown'">
            <div id="section-class-breakdown" class="col-span-12">
                <div class="card items-center">
                    <!-- Header -->
                    <div class="text-center mb-4">
                        <div class="font-bold text-lg uppercase">
                            Recap Class Breakdown
                        </div>
                        <div class="text-sm text-surface-500 mt-1">
                            School Year {{ school_years_now?.name ?? '' }}
                        </div>
                        <div class="text-xs text-surface-400 mt-1">
                            Recap per date:
                            {{ new Date().toLocaleDateString('en-US', { day:'2-digit', month:'long', year:'numeric' }) }}
                        </div>
                    </div>

                    <!-- Loading -->
                    <div v-if="loading">
                        <Skeleton width="100%" height="200px" />
                    </div>

                    <!-- No data -->
                    <div v-else-if="!school_years_pick.length" class="text-center text-surface-400 py-8">
                        No Class Breakdown found.
                    </div>

                    <!-- Table -->
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm border-collapse
                                    text-surface-900 dark:text-surface-0">
                            <!-- Head -->
                            <thead>
                                <tr class="bg-surface-100 dark:bg-surface-800">
                                    <th rowspan="2"
                                        class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2 text-center font-semibold">
                                        Level
                                    </th>
                                    <th rowspan="2"
                                        class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2 text-center font-semibold">
                                        Class
                                    </th>
                                    <th colspan="3"
                                        class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2 text-center font-semibold">
                                        Gender
                                    </th>
                                    <th rowspan="2"
                                        class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2 text-center font-semibold">
                                        Major
                                    </th>
                                    <th colspan="3"
                                        class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2 text-center font-semibold">
                                        Total
                                    </th>
                                </tr>
                                <tr class="bg-surface-100 dark:bg-surface-800">
                                    <th class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2 text-center font-semibold text-blue-500">M</th>
                                    <th class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2 text-center font-semibold text-pink-500">F</th>
                                    <th class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2 text-center font-semibold">Total</th>
                                    <th class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2 text-center font-semibold text-blue-500">M</th>
                                    <th class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2 text-center font-semibold text-pink-500">F</th>
                                    <th class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2 text-center font-semibold">Total</th>
                                </tr>
                            </thead>

                            <!-- Body -->
                            <tbody>
                                <template v-for="level in rekapData.levels" :key="level.name">
                                    <tr v-for="(rombel, idx) in level.rombels" :key="rombel.name"
                                        class="hover:bg-surface-50 dark:hover:bg-surface-800
                                            transition-colors duration-100">

                                        <!-- Tingkat — rowspan to cover all rombels in this level -->
                                        <td v-if="idx === 0"
                                            :rowspan="level.rombels.length"
                                            class="border border-surface-300 dark:border-surface-600
                                                px-3 py-2 text-center font-semibold
                                                bg-surface-50 dark:bg-surface-900">
                                            {{ level.name }}
                                        </td>

                                        <!-- Rombel name -->
                                        <td class="border border-surface-300 dark:border-surface-600 px-3 py-2">
                                            {{ rombel.name }}
                                        </td>

                                        <!-- L per rombel -->
                                        <td class="border border-surface-300 dark:border-surface-600
                                                px-3 py-2 text-center text-blue-500 font-medium">
                                            {{ rombel.L }}
                                        </td>

                                        <!-- P per rombel -->
                                        <td class="border border-surface-300 dark:border-surface-600
                                                px-3 py-2 text-center text-pink-500 font-medium">
                                            {{ rombel.P }}
                                        </td>

                                        <!-- Jml per rombel -->
                                        <td class="border border-surface-300 dark:border-surface-600
                                                px-3 py-2 text-center font-medium">
                                            {{ rombel.total }}
                                        </td>

                                        <!-- Jurusan — rowspan -->
                                        <td v-if="idx === 0"
                                            :rowspan="level.rombels.length"
                                            class="border border-surface-300 dark:border-surface-600
                                                px-3 py-2 text-center
                                                bg-surface-50 dark:bg-surface-900">
                                            {{ rombel.major }}
                                        </td>

                                        <!-- Level Total L — rowspan, only on first row -->
                                        <td v-if="idx === 0"
                                            :rowspan="level.rombels.length"
                                            class="border border-surface-300 dark:border-surface-600
                                                px-3 py-2 text-center text-blue-500 font-bold
                                                bg-blue-50 dark:bg-blue-950">
                                            {{ level.totalL }}
                                        </td>

                                        <!-- Level Total P — rowspan -->
                                        <td v-if="idx === 0"
                                            :rowspan="level.rombels.length"
                                            class="border border-surface-300 dark:border-surface-600
                                                px-3 py-2 text-center text-pink-500 font-bold
                                                bg-pink-50 dark:bg-pink-950">
                                            {{ level.totalP }}
                                        </td>

                                        <!-- Level Total All — rowspan -->
                                        <td v-if="idx === 0"
                                            :rowspan="level.rombels.length"
                                            class="border border-surface-300 dark:border-surface-600
                                                px-3 py-2 text-center font-bold
                                                bg-surface-100 dark:bg-surface-800">
                                            {{ level.totalAll }}
                                        </td>
                                    </tr>
                                </template>

                                <!-- Grand Total row -->
                                <tr class="bg-surface-200 dark:bg-surface-700 font-bold">
                                    <td colspan="2"
                                        class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2 text-center font-bold">
                                        Total
                                    </td>
                                    <td class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2 text-center text-blue-600">
                                        {{ rekapData.grandL }}
                                    </td>
                                    <td class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2 text-center text-pink-600">
                                        {{ rekapData.grandP }}
                                    </td>
                                    <td class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2 text-center">
                                        {{ rekapData.grandTotal }}
                                    </td>
                                    <td class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2"></td>
                                    <td class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2 text-center text-blue-600">
                                        {{ rekapData.grandL }}
                                    </td>
                                    <td class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2 text-center text-pink-600">
                                        {{ rekapData.grandP }}
                                    </td>
                                    <td class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2 text-center">
                                        {{ rekapData.grandTotal }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>

        <div class="card w-full h-full" v-show="activeGroup === 'demographics' && activeSub === 'section-profile-student'">

            <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4 border-b border-surface-200 dark:border-surface-700 pb-4">
                <div class="font-semibold text-xl">Student Profile</div>
                <div class="flex gap-2 w-full md:w-auto">
                    <AutoComplete v-model="searchQuery" required="true" optionLabel="student_nik.name" forceSelection :suggestions="filtered_Cat1" @complete="search_Cat1" fluid />

                </div>
            </div>

            <div class="grid grid-cols-12 gap-6">

                <div class="col-span-12 xl:col-span-4">
                    <div class="card bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 flex flex-col items-center p-6 h-full shadow-none">
                        <div class="w-32 h-32 bg-surface-200 dark:bg-surface-800 rounded-full mb-6 flex items-center justify-center text-surface-500 shadow-sm border-4 border-surface-0 dark:border-surface-900">
                            <i class="pi pi-user text-5xl"></i>
                        </div>

                        <div class="w-full flex flex-col gap-5">
                            <div class="border-b border-surface-200 dark:border-surface-700 pb-2">
                                <span class="text-sm text-surface-500 block mb-1">Full Name</span>
                                <span class="font-bold text-lg text-surface-900 dark:text-surface-0">
                                    {{ searchQuery?.student_nik?.name ?? '-' }}
                                </span>
                            </div>
                            <div class="border-b border-surface-200 dark:border-surface-700 pb-2">
                                <span class="text-sm text-surface-500 block mb-1">Class</span>
                                <span class="font-bold text-lg text-surface-900 dark:text-surface-0">
                                    {{ searchQuery?.school_rombel?.name ?? '-' }}
                                </span>
                            </div>
                            <div class="border-b border-surface-200 dark:border-surface-700 pb-2">
                                <span class="text-sm text-surface-500 block mb-1">NIS / NISN</span>
                                <span class="font-bold text-lg text-surface-900 dark:text-surface-0">
                                    {{ searchQuery?.student_nik?.nis ?? '-' }} / {{ searchQuery?.student_nik?.nisn ?? '-' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 xl:col-span-8 flex flex-col gap-6">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                        <div class="card bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 p-6 shadow-none h-full">
                            <div class="font-semibold text-lg mb-4 border-b border-surface-200 dark:border-surface-700 pb-2">Personal Data</div>
                            <div class="flex flex-col gap-4 text-sm">
                                <div class="flex justify-between items-center">
                                    <span class="text-surface-500">Gender</span>
                                    <span class="font-medium text-right">{{ searchQuery?.student_nik?.gender?.display_name ?? searchQuery?.student_nik?.gender?.name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-surface-500">NIK</span>
                                    <span class="font-medium text-right">{{ searchQuery?.student_nik?.nik ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-surface-500">Family Card Number</span>
                                    <span class="font-medium text-right">{{ searchQuery?.student_nik?.kk ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-surface-500">Birthplace</span>
                                    <span class="font-medium text-right">{{ searchQuery?.student_nik?.birthplace ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-surface-500">Birthdate</span>
                                    <span class="font-medium text-right">{{ searchQuery?.student_nik?.birthdate_human ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-surface-500">The</span>
                                    <span class="font-medium text-right">{{ searchQuery?.student_nik?.child_order_to ?? '-' }} of {{ searchQuery?.student_nik?.child_order_total ?? '-' }} siblings</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-surface-500">Religion</span>
                                    <span class="font-medium text-right">{{ searchQuery?.student_nik?.religion?.display_name ?? searchQuery?.student_nik?.religion?.name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-surface-500">Birth Certificate Number</span>
                                    <span class="font-medium text-right">{{ searchQuery?.student_nik?.akta ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-surface-500">Family Status</span>
                                    <span class="font-medium text-right">{{ searchQuery?.student_nik?.family_status?.display_name ?? searchQuery?.student_nik?.family_status?.name ?? '-' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="card bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 p-6 shadow-none h-full">
                            <div class="font-semibold text-lg mb-4 border-b border-surface-200 dark:border-surface-700 pb-2">Address & Contact</div>
                            <div class="flex flex-col gap-4 text-sm">
                                <div class="flex justify-between items-center">
                                    <span class="text-surface-500">Full Address</span>
                                    <span class="font-medium text-right truncate w-1/2" :title="searchQuery?.student_nik?.address ?? '-'">{{ searchQuery?.student_nik?.address ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-surface-500">RT / RW</span>
                                    <span class="font-medium text-right">{{ searchQuery?.student_nik?.rt_num ?? '-' }} / {{ searchQuery?.student_nik?.rw_num ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-surface-500">Urban Village / Village</span>
                                    <span class="font-medium text-right">{{ searchQuery?.student_nik?.village?.name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-surface-500">District</span>
                                    <span class="font-medium text-right">{{ searchQuery?.student_nik?.village?.district?.name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-surface-500">City / Regency</span>
                                    <span class="font-medium text-right">{{ searchQuery?.student_nik?.village?.district?.regency?.name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-surface-500">ZIP Code</span>
                                    <span class="font-medium text-right">{{ searchQuery?.student_nik?.zip_code ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-surface-500">Email</span>
                                    <span class="font-medium text-right">{{ searchQuery?.student_nik?.email ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-surface-500">Phone Number</span>
                                    <span class="font-medium text-right">{{ searchQuery?.student_nik?.hp_number ?? '-' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 p-6 shadow-none">
                        <div class="font-semibold text-lg mb-6 border-b border-surface-200 dark:border-surface-700 pb-2">Parents / Guardian Data</div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

                            <div class="flex flex-col gap-3">
                                <div class="font-bold text-primary-500 mb-2 flex items-center"><i class="pi pi-user mr-2"></i> Father Data</div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Name</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.father_name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">NIK</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.father_nik ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Birthplace</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.father_birthplace ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Birthdate</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.father_birthdate ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Education Level</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.father_education_level?.display_name ?? searchQuery?.student_nik?.father_education_level?.name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Profession</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.father_profession?.display_name ?? searchQuery?.student_nik?.father_profession?.name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Income</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.father_income ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Phone Number</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.father_hp_num ?? '-' }}</span>
                                </div>
                            </div>

                            <div class="flex flex-col gap-3">
                                <div class="font-bold text-pink-500 mb-2 flex items-center"><i class="pi pi-user mr-2"></i> Mother Data</div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Name</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.mother_name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">NIK</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.mother_nik ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Birthplace</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.mother_birthplace ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Birthdate</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.mother_birthdate ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Education Level</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.mother_education_level?.display_name ?? searchQuery?.student_nik?.mother_education_level?.name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Profession</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.mother_profession?.display_name ?? searchQuery?.student_nik?.mother_profession?.name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Income</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.mother_income ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Phone Number</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.mother_hp_num ?? '-' }}</span>
                                </div>
                            </div>

                            <div class="flex flex-col gap-3">
                                <div class="font-bold text-surface-700 dark:text-surface-300 mb-2 flex items-center"><i class="pi pi-users mr-2"></i> Guardian Data</div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Name</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.guardian_name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">NIK</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.guardian_nik ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Birthplace</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.guardian_birthplace ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Birthdate</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.guardian_birthdate ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Education Level</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.guardian_education_level?.display_name ?? searchQuery?.student_nik?.guardian_education_level?.name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Profession</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.guardian_profession?.display_name ?? searchQuery?.student_nik?.guardian_profession?.name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Income</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.guardian_income ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Phone Number</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.guardian_hp_num ?? '-' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ── ADMINISTRATION ────────────────────────────────────────────────────── -->
        <div class="grid grid-cols-12 mt-8 gap-8" v-show="activeGroup === 'administration' && activeSub === 'section-f8355'">
            <div id="section-f8355" class="col-span-12">
                <div v-if="loading == true" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">F8355</div>
                    <Skeleton width="100%" height="150px"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">F8355</div>
                    <span>No F8355 found.</span>
                </div>
                <div v-else class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">F8355</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-12 mt-8 gap-8" v-show="activeGroup === 'administration' && activeSub === 'section-kjp'">
            <div id="section-kjp" class="col-span-12 xl:col-span-6">
                <div v-if="loading == true" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Chart KJP</div>
                    <Skeleton width="100%" height="150px"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Chart KJP</div>
                    <span>No KJP found.</span>
                </div>
                <div v-else class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Chart KJP</div>

                </div>
            </div>
            <div id="section-kjp" class="col-span-12 xl:col-span-6">
                <div v-if="loading == true" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total KJP</div>
                    <Skeleton width="100%" height="150px"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total KJP</div>
                    <span>No KJP found.</span>
                </div>
                <div v-else class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total KJP</div>

                </div>
            </div>
        </div>

        <div class="grid grid-cols-12 mt-8 gap-8" v-show="activeGroup === 'administration' && activeSub === 'section-ppdb'">
            <div id="section-ppdb" class="col-span-12 xl:col-span-6">
                <div v-if="loading == true" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Chart PPDB</div>
                    <Skeleton width="100%" height="150px"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Chart PPDB</div>
                    <span>No PPDB found.</span>
                </div>
                <div v-else class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Chart PPDB</div>

                </div>
            </div>
            <div id="section-ppdb" class="col-span-12 xl:col-span-6">
                <div v-if="loading == true" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total PPDB</div>
                    <Skeleton width="100%" height="150px"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total PPDB</div>
                    <span>No PPDB found.</span>
                </div>
                <div v-else class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total PPDB</div>

                </div>
            </div>
        </div>

        <div class="grid grid-cols-12 mt-8 gap-8" v-show="activeGroup === 'administration' && activeSub === 'section-dapodik'">
            <div id="section-dapodik" class="col-span-12 xl:col-span-6">
                <div v-if="loading == true" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Chart Dapodik Active</div>
                    <Skeleton width="100%" height="150px"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Chart Dapodik Active</div>
                    <span>No Dapodik Active found.</span>
                </div>
                <div v-else class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Chart Dapodik Active</div>

                </div>
            </div>
            <div id="section-dapodik" class="col-span-12 xl:col-span-6">
                <div v-if="loading == true" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total Dapodik Status</div>
                    <Skeleton width="100%" height="150px"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total Dapodik Status</div>
                    <span>No Dapodik Status found.</span>
                </div>
                <div v-else class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total Dapodik Status</div>

                </div>
            </div>
        </div>

        <div class="grid grid-cols-12 mt-8 gap-8" v-show="activeGroup === 'administration' && activeSub === 'section-dapodik'">
            <div id="section-dapodik" class="col-span-12">
                <div v-if="loading == true" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Dapodik Non-Active</div>
                    <Skeleton width="100%" height="150px"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Dapodik Non-Active</div>
                    <span>No Dapodik Non-Active found.</span>
                </div>
                <div v-else class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Dapodik Non-Active</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-12 mt-8 gap-8" v-show="activeGroup === 'administration' && activeSub === 'section-mutation'">
            <div id="section-mutation" class="col-span-12">
                <div v-if="loading == true" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Student Transfers</div>
                    <Skeleton width="100%" height="150px"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Student Transfers</div>
                    <span>No Student Transfers found.</span>
                </div>
                <div v-else class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Student Transfers</div>
                </div>
            </div>
        </div>

        <!-- ── RELATIONSHIPS ─────────────────────────────────────────────────────── -->
        <div class="grid grid-cols-12 mt-8 gap-8" v-show="activeGroup === 'relationships' && activeSub === 'section-parents'">
            <div id="section-parents" class="col-span-12">
                <div v-if="loading == true" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Parents</div>
                    <Skeleton width="100%" height="150px"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Parents</div>
                    <span>No Parents found.</span>
                </div>
                <div v-else class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Parents</div>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-12 mt-8 gap-8" v-show="activeGroup === 'relationships' && activeSub === 'section-guardian'">
            <div id="section-guardian" class="col-span-12">
                <div v-if="loading == true" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Homeroom Teacher</div>
                    <Skeleton width="100%" height="150px"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Homeroom Teacher</div>
                    <span>No Homeroom Teacher found.</span>
                </div>
                <div v-else class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Homeroom Teacher</div>
                </div>
            </div>
        </div>


    </Fluid>

    <!-- <Button label="Danger" @click="visibleStaticBackdropWarnings" /> -->
    <Dialog v-model:visible="visibleStaticBackdropWarning" modal header="Warning!!" :style="{ width: '25rem' }">
        <div class="text-center justify-content-center align-items-center">
            <p>Is the information you entered correct?</p>
        </div>
    </Dialog>
    <ConfirmDialog group="headless">
        <template #container="{ message, acceptCallback, rejectCallback }">
            <div class="flex flex-col items-center p-8 bg-surface-0 dark:bg-surface-900 rounded">
                <div class="rounded-full bg-primary text-primary-contrast inline-flex justify-center items-center h-24 w-24 -mt-20">
                    <i class="pi pi-question !text-4xl"></i>
                </div>
                <span class="font-bold text-2xl block mb-2 mt-6">{{ message.header }}</span>
                <p class="mb-0">{{ message.message }}</p>
                <div class="flex items-center gap-2 mt-6">
                    <Button label="Save" @click="acceptCallback"></Button>
                    <Button label="Cancel" outlined @click="rejectCallback"></Button>
                </div>
            </div>
        </template>
    </ConfirmDialog>

    <!-- <Button label="Danger" @click="visibleStaticBackdropDangers" /> -->
    <Dialog v-model:visible="visibleStaticBackdropDanger" modal :header="titleDanger" style=" width: 25rem; color: red;">
        <div style="color: black;">
            {{ fieldDanger }}
        </div>
    </Dialog>

    <!-- <Button label="Success" @click="visibleStaticBackdropSuccesss" /> -->
    <Dialog v-model:visible="visibleStaticBackdropSuccess" modal :header="titleSuccess">
        <div>{{ fieldSuccess }} <Image fluid :src="gifSuccess" width="55" height="55" /></div>
    </Dialog>

    <!-- <Button label="Waiting" @click="visibleStaticBackdropWaitings" /> -->
    <Dialog v-model:visible="visibleStaticBackdropWaiting" modal header="Please wait while your information is processed.">
        <div class="text-center justify-content-center align-items-center">
            <i class="pi pi-spin pi-spinner" style="font-size: 2rem; color: green"></i>
            <p class="text-success" style="font-size: medium">Loading...</p>
        </div>
    </Dialog>

    <!-- ── Profile Student Dialog ─────────────────────────────────────────────── -->
    <Dialog
        v-model:visible="profileDialog"
        modal
        :style="{ width: '700px', maxWidth: '95vw' }"
        header="Student Profile"
        :draggable="false"
    >
        <template #header>
            <div class="flex items-center justify-between w-full">
                <span class="font-bold text-lg">Student Profile</span>
                <Button
                    icon="pi pi-print"
                    label="Print"
                    severity="secondary"
                    size="small"
                    outlined
                    @click="printProfile"
                />
            </div>
        </template>

        <div v-if="selectedStudent" id="student-profile-print" class="text-sm text-surface-900 dark:text-surface-0">

            <!-- Document Title -->
            <h2 class="text-center font-bold text-base uppercase mb-4">
                Student Profile SMA YAPPENDA
            </h2>

            <!-- ── A. Profil Siswa ────────────────────────────────────────────── -->
            <div class="font-bold border-b border-surface-300 dark:border-surface-600 pb-1 mb-3">
                A. Student Profile
            </div>
            <div class="flex flex-col gap-1 pl-4 mb-4">
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Student Name</span>
                    <span class="w-3 shrink-0">:</span>
                    <span class="font-bold">{{ selectedStudent.student_nik?.name ?? '-' }}</span>
                </div>
                <div class="flex gap-4">
                    <div class="flex gap-2 flex-1">
                        <span class="w-40 shrink-0">NIS</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.nis ?? '-' }}</span>
                    </div>
                    <div class="flex gap-2 flex-1">
                        <span class="w-16 shrink-0">NISN</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.nisn ?? '-' }}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Class</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.school_rombel?.name ?? '-' }}</span>
                </div>
            </div>

            <!-- ── B. Identitas Siswa ─────────────────────────────────────────── -->
            <div class="font-bold border-b border-surface-300 dark:border-surface-600 pb-1 mb-3">
                B. Student Identity
            </div>
            <div class="flex flex-col gap-1 pl-4 mb-4">
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Gender</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.gender?.display_name ?? selectedStudent.student_nik?.gender?.name ?? '-' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">NIK</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.nik ?? '-' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Family Card Number</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.no_kk ?? '-' }}</span>
                </div>
                <div class="flex gap-4">
                    <div class="flex gap-2 flex-1">
                        <span class="w-40 shrink-0">Birthplace</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.birthplace ?? '-' }}</span>
                    </div>
                    <div class="flex gap-2 flex-1">
                        <span class="w-28 shrink-0">Birthdate</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.birthdate ?? '-' }}</span>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="flex gap-2 flex-1">
                        <span class="w-40 shrink-0">Religion</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.religion?.display_name ?? selectedStudent.student_nik?.religion?.name ?? '-' }}</span>
                    </div>
                    <div class="flex gap-2 flex-1">
                        <span class="w-28 shrink-0">Family Status</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.family_status?.display_name ?? selectedStudent.student_nik?.family_status?.name ?? '-' }}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Child Order</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.child_order ?? '-' }}</span>
                    <span class="mx-2">Of</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.child_of ?? '-' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Birth Certificate Number</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.birth_certificate_number ?? '-' }}</span>
                </div>
            </div>

            <!-- ── C. Kontak Siswa ────────────────────────────────────────────── -->
            <div class="font-bold border-b border-surface-300 dark:border-surface-600 pb-1 mb-3">
                C. Student Contact Details
            </div>
            <div class="flex flex-col gap-1 pl-4 mb-4">
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Address</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.address ?? '-' }}</span>
                </div>
                <div class="flex gap-4">
                    <div class="flex gap-2 flex-1">
                        <span class="w-40 shrink-0">RT</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.rt ?? '-' }}</span>
                    </div>
                    <div class="flex gap-2 flex-1">
                        <span class="w-10 shrink-0">RW</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.rw ?? '-' }}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Urban Village</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.village?.name ?? '-' }}</span>
                </div>
                <div class="flex gap-4">
                    <div class="flex gap-2 flex-1">
                        <span class="w-40 shrink-0">District</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.district?.name ?? '-' }}</span>
                    </div>
                    <div class="flex gap-2 flex-1">
                        <span class="w-10 shrink-0">City</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.regency?.name ?? '-' }}</span>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="flex gap-2 flex-1">
                        <span class="w-40 shrink-0">ZIP Code</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.zip_code ?? '-' }}</span>
                    </div>
                    <div class="flex gap-2 flex-1">
                        <span class="w-10 shrink-0">Phone Number</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.phone ?? '-' }}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Email</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.email ?? '-' }}</span>
                </div>
            </div>

            <!-- ── D. Data Ijazah ─────────────────────────────────────────────── -->
            <div class="font-bold border-b border-surface-300 dark:border-surface-600 pb-1 mb-3">
                D. Diploma Details
            </div>
            <div class="flex flex-col gap-1 pl-4 mb-4">
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Previous School</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.origin_school ?? '-' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Diploma Number</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.certificate_number ?? '-' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Diploma Year</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.certificate_year ?? '-' }}</span>
                </div>
            </div>

            <!-- ── E. Identitas Ayah ──────────────────────────────────────────── -->
            <div class="font-bold border-b border-surface-300 dark:border-surface-600 pb-1 mb-3">
                E. Father Identity
            </div>
            <div class="flex flex-col gap-1 pl-4 mb-4">
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Name</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.father_name ?? '-' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">NIK</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.father_nik ?? '-' }}</span>
                </div>
                <div class="flex gap-4">
                    <div class="flex gap-2 flex-1">
                        <span class="w-40 shrink-0">Birthplace</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.father_birthplace ?? '-' }}</span>
                    </div>
                    <div class="flex gap-2 flex-1">
                        <span class="w-28 shrink-0">Birthdate</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.father_birthdate ?? '-' }}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Highest Education Level</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ referenceLabel('education-levels', selectedStudent.student_nik?.father_last_education ?? '-') }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Occupation</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ referenceLabel('professions', selectedStudent.student_nik?.father_job ?? '-') }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Income</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.father_income
                        ? 'Rp' + Number(selectedStudent.student_nik.father_income).toLocaleString('id-ID')
                        : 'Rp0' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Phone Number</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.father_phone ?? '-' }}</span>
                </div>
            </div>

            <!-- ── F. Identitas Ibu ───────────────────────────────────────────── -->
            <div class="font-bold border-b border-surface-300 dark:border-surface-600 pb-1 mb-3">
                F. Mother Identity
            </div>
            <div class="flex flex-col gap-1 pl-4 mb-4">
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Name</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.mother_name ?? '-' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">NIK</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.mother_nik ?? '-' }}</span>
                </div>
                <div class="flex gap-4">
                    <div class="flex gap-2 flex-1">
                        <span class="w-40 shrink-0">Birthplace</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.mother_birthplace ?? '-' }}</span>
                    </div>
                    <div class="flex gap-2 flex-1">
                        <span class="w-28 shrink-0">Birthdate</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.mother_birthdate ?? '-' }}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Highest Education Level</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ referenceLabel('education-levels', selectedStudent.student_nik?.mother_last_education ?? '-') }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Occupation</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ referenceLabel('professions', selectedStudent.student_nik?.mother_job ?? '-') }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Income</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.mother_income
                        ? 'Rp' + Number(selectedStudent.student_nik.mother_income).toLocaleString('id-ID')
                        : 'Rp0' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Phone Number</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.mother_phone ?? '-' }}</span>
                </div>
            </div>

            <!-- ── Signature ──────────────────────────────────────────────────── -->
            <div class="flex justify-end mt-6 pr-4">
                <div class="text-center">
                    <p>Jakarta, {{ new Date().toLocaleDateString('en-US', { day: '2-digit', month: 'long', year: 'numeric' }) }}</p>
                    <p>Student of SMA YAPPENDA,</p>
                    <div class="mt-12 mb-1 font-bold underline">
                        {{ selectedStudent.student_nik?.name ?? '' }}
                    </div>
                </div>
            </div>

        </div>
    </Dialog>

    <!-- ── 03b: Add / Edit Student Dialog ─────────────────────────────────────── -->
    <Dialog v-model:visible="studentFormDialog" modal :style="{ width: '880px', maxWidth: '95vw' }" :draggable="false"
            :header="editTargetId ? 'Edit Student Details' : 'Add New Student'">
        <div class="flex flex-col gap-6 max-h-[70vh] overflow-y-auto pr-1">
            <div>
                <div class="font-bold border-b border-surface-200 dark:border-surface-700 pb-1 mb-3">Personal Details</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="sf-name" class="block font-bold mb-2">Full Name <span class="text-red-500">*</span></label>
                        <InputText id="sf-name" v-model="studentForm.name" :invalid="submitted && !studentForm.name" fluid />
                        <small v-if="submitted && !studentForm.name" class="text-red-500">Student name is required.</small>
                        <small v-else-if="fieldError('name')" class="text-red-500">{{ fieldError('name') }}</small>
                    </div>
                    <div>
                        <label for="sf-birthplace" class="block font-bold mb-2">Birthplace</label>
                        <InputText id="sf-birthplace" v-model="studentForm.birthplace" fluid />
                        <small v-if="fieldError('birthplace')" class="text-red-500">{{ fieldError('birthplace') }}</small>
                    </div>
                    <div>
                        <label for="sf-birthdate" class="block font-bold mb-2">Birthdate (YYYY-MM-DD)</label>
                        <InputText id="sf-birthdate" v-model="studentForm.birthdate" placeholder="2010-05-15" fluid />
                        <small v-if="fieldError('birthdate')" class="text-red-500">{{ fieldError('birthdate') }}</small>
                    </div>
                    <div>
                        <label for="sf-gender" class="block font-bold mb-2">Gender</label>
                        <Select id="sf-gender" v-model="studentForm.gender_id" :options="formOptions.genders" optionLabel="display_name" optionValue="id" placeholder="Select Gender" showClear filter fluid />
                        <small v-if="fieldError('gender_id')" class="text-red-500">{{ fieldError('gender_id') }}</small>
                    </div>
                    <div>
                        <label for="sf-religion" class="block font-bold mb-2">Religion</label>
                        <Select id="sf-religion" v-model="studentForm.religion_id" :options="formOptions.religions" optionLabel="display_name" optionValue="id" placeholder="Select Religion" showClear filter fluid />
                        <small v-if="fieldError('religion_id')" class="text-red-500">{{ fieldError('religion_id') }}</small>
                    </div>
                    <div>
                        <label for="sf-family-status" class="block font-bold mb-2">Family Status</label>
                        <Select id="sf-family-status" v-model="studentForm.family_status_id" :options="formOptions.family_statuses" optionLabel="display_name" optionValue="id" placeholder="Select Family Status" showClear filter fluid />
                        <small v-if="fieldError('family_status_id')" class="text-red-500">{{ fieldError('family_status_id') }}</small>
                    </div>
                    <div>
                        <label for="sf-blood-type" class="block font-bold mb-2">Blood Type</label>
                        <Select id="sf-blood-type" v-model="studentForm.blood_type_id" :options="formOptions.blood_types" optionLabel="name" optionValue="id" placeholder="Select Blood Type" showClear filter fluid />
                        <small v-if="fieldError('blood_type_id')" class="text-red-500">{{ fieldError('blood_type_id') }}</small>
                    </div>
                    <div>
                        <label for="sf-profession" class="block font-bold mb-2">Profession</label>
                        <Select id="sf-profession" v-model="studentForm.profession_id" :options="formOptions.professions" optionLabel="display_name" optionValue="id" placeholder="Select Profession" showClear filter fluid />
                        <small v-if="fieldError('profession_id')" class="text-red-500">{{ fieldError('profession_id') }}</small>
                    </div>
                    <div>
                        <label for="sf-education-level" class="block font-bold mb-2">Education Level</label>
                        <Select id="sf-education-level" v-model="studentForm.education_level_id" :options="formOptions.education_levels" optionLabel="display_name" optionValue="id" placeholder="Select Education Level" showClear filter fluid />
                        <small v-if="fieldError('education_level_id')" class="text-red-500">{{ fieldError('education_level_id') }}</small>
                    </div>
                </div>
            </div>

            <div>
                <div class="font-bold border-b border-surface-200 dark:border-surface-700 pb-1 mb-3">Identification & Documents</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="sf-nik" class="block font-bold mb-2">NIK (16 digits)</label>
                        <InputText id="sf-nik" v-model="studentForm.nik" fluid />
                        <small v-if="fieldError('nik')" class="text-red-500">{{ fieldError('nik') }}</small>
                    </div>
                    <div>
                        <label for="sf-kk" class="block font-bold mb-2">Family Card Number (16 digits)</label>
                        <InputText id="sf-kk" v-model="studentForm.kk" fluid />
                        <small v-if="fieldError('kk')" class="text-red-500">{{ fieldError('kk') }}</small>
                    </div>
                    <div>
                        <label for="sf-nis" class="block font-bold mb-2">NIS</label>
                        <InputText id="sf-nis" v-model="studentForm.nis" fluid />
                        <small v-if="fieldError('nis')" class="text-red-500">{{ fieldError('nis') }}</small>
                    </div>
                    <div>
                        <label for="sf-nisn" class="block font-bold mb-2">NISN (10 digits)</label>
                        <InputText id="sf-nisn" v-model="studentForm.nisn" fluid />
                        <small v-if="fieldError('nisn')" class="text-red-500">{{ fieldError('nisn') }}</small>
                    </div>
                    <div>
                        <label for="sf-akta" class="block font-bold mb-2">Birth Certificate Number</label>
                        <InputText id="sf-akta" v-model="studentForm.akta" fluid />
                        <small v-if="fieldError('akta')" class="text-red-500">{{ fieldError('akta') }}</small>
                    </div>
                </div>
            </div>

            <div>
                <div class="font-bold border-b border-surface-200 dark:border-surface-700 pb-1 mb-3">Address & Contact</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label for="sf-address" class="block font-bold mb-2">Full Address</label>
                        <Textarea id="sf-address" v-model="studentForm.address" rows="2" autoResize fluid />
                        <small v-if="fieldError('address')" class="text-red-500">{{ fieldError('address') }}</small>
                    </div>
                    <div>
                        <label for="sf-rt" class="block font-bold mb-2">RT Number</label>
                        <InputText id="sf-rt" v-model="studentForm.rt_num" fluid />
                    </div>
                    <div>
                        <label for="sf-rw" class="block font-bold mb-2">RW Number</label>
                        <InputText id="sf-rw" v-model="studentForm.rw_num" fluid />
                    </div>
                    <div>
                        <label for="sf-zip" class="block font-bold mb-2">ZIP Code</label>
                        <InputText id="sf-zip" v-model="studentForm.zip_code" fluid />
                        <small v-if="fieldError('zip_code')" class="text-red-500">{{ fieldError('zip_code') }}</small>
                    </div>
                    <div>
                        <label for="sf-hp" class="block font-bold mb-2">Phone Number</label>
                        <InputText id="sf-hp" v-model="studentForm.hp_num" fluid />
                        <small v-if="fieldError('hp_num')" class="text-red-500">{{ fieldError('hp_num') }}</small>
                    </div>
                </div>
            </div>

            <div>
                <div class="font-bold border-b border-surface-200 dark:border-surface-700 pb-1 mb-3">Parents & Guardian</div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="sf-father-name" class="block font-bold mb-2">Father Name</label>
                        <InputText id="sf-father-name" v-model="studentForm.father_name" fluid />
                    </div>
                    <div>
                        <label for="sf-father-nik" class="block font-bold mb-2">Father NIK</label>
                        <InputText id="sf-father-nik" v-model="studentForm.father_nik" fluid />
                        <small v-if="fieldError('father_nik')" class="text-red-500">{{ fieldError('father_nik') }}</small>
                    </div>
                    <div>
                        <label for="sf-father-hp" class="block font-bold mb-2">Father Phone</label>
                        <InputText id="sf-father-hp" v-model="studentForm.father_hp_num" fluid />
                    </div>
                    <div>
                        <label for="sf-mother-name" class="block font-bold mb-2">Mother Name</label>
                        <InputText id="sf-mother-name" v-model="studentForm.mother_name" fluid />
                    </div>
                    <div>
                        <label for="sf-mother-nik" class="block font-bold mb-2">Mother NIK</label>
                        <InputText id="sf-mother-nik" v-model="studentForm.mother_nik" fluid />
                        <small v-if="fieldError('mother_nik')" class="text-red-500">{{ fieldError('mother_nik') }}</small>
                    </div>
                    <div>
                        <label for="sf-mother-hp" class="block font-bold mb-2">Mother Phone</label>
                        <InputText id="sf-mother-hp" v-model="studentForm.mother_hp_num" fluid />
                    </div>
                    <div>
                        <label for="sf-guardian-name" class="block font-bold mb-2">Guardian Name</label>
                        <InputText id="sf-guardian-name" v-model="studentForm.guardian_name" fluid />
                    </div>
                    <div>
                        <label for="sf-guardian-nik" class="block font-bold mb-2">Guardian NIK</label>
                        <InputText id="sf-guardian-nik" v-model="studentForm.guardian_nik" fluid />
                        <small v-if="fieldError('guardian_nik')" class="text-red-500">{{ fieldError('guardian_nik') }}</small>
                    </div>
                    <div>
                        <label for="sf-guardian-hp" class="block font-bold mb-2">Guardian Phone</label>
                        <InputText id="sf-guardian-hp" v-model="studentForm.guardian_hp_num" fluid />
                    </div>
                </div>
            </div>

            <div>
                <div class="font-bold border-b border-surface-200 dark:border-surface-700 pb-1 mb-3">Assistance & Welfare</div>
                <div class="flex flex-wrap gap-6">
                    <div class="flex items-center gap-2">
                        <Checkbox v-model="studentForm.check_ppdbbersama" inputId="sf-ppdb" :binary="true" />
                        <label for="sf-ppdb">Joint Student Admissions (PPDB)</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox v-model="studentForm.check_kjp" inputId="sf-kjp" :binary="true" />
                        <label for="sf-kjp">KJP</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox v-model="studentForm.check_bpms" inputId="sf-bpms" :binary="true" />
                        <label for="sf-bpms">BPMS</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox v-model="studentForm.check_pip" inputId="sf-pip" :binary="true" />
                        <label for="sf-pip">PIP</label>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" icon="pi pi-times" text severity="secondary" :disabled="isSubmitting" @click="studentFormDialog = false" />
            <Button :label="editTargetId ? 'Save Changes' : 'Save Student'" icon="pi pi-check" :loading="isSubmitting" :disabled="isSubmitting" @click="submitStudentForm" />
        </template>
    </Dialog>

    <!-- ── 03b: Student Detail Dialog ─────────────────────────────────────────── -->
    <Dialog v-model:visible="studentDetailDialog" modal :style="{ width: '720px', maxWidth: '95vw' }" :draggable="false" header="Student Detail">
        <div v-if="detailStudent" class="flex flex-col gap-4 text-sm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-2">
                <div class="flex justify-between border-b border-surface-200 dark:border-surface-700 pb-1"><span class="text-surface-500">Full Name</span><span class="font-medium">{{ detailStudent.name ?? '-' }}</span></div>
                <div class="flex justify-between border-b border-surface-200 dark:border-surface-700 pb-1"><span class="text-surface-500">NIK</span><span class="font-medium">{{ detailStudent.nik ?? '-' }}</span></div>
                <div class="flex justify-between border-b border-surface-200 dark:border-surface-700 pb-1"><span class="text-surface-500">NIS</span><span class="font-medium">{{ detailStudent.nis ?? '-' }}</span></div>
                <div class="flex justify-between border-b border-surface-200 dark:border-surface-700 pb-1"><span class="text-surface-500">NISN</span><span class="font-medium">{{ detailStudent.nisn ?? '-' }}</span></div>
                <div class="flex justify-between border-b border-surface-200 dark:border-surface-700 pb-1"><span class="text-surface-500">Gender</span><span class="font-medium">{{ detailStudent.gender?.display_name ?? detailStudent.gender?.name ?? '-' }}</span></div>
                <div class="flex justify-between border-b border-surface-200 dark:border-surface-700 pb-1"><span class="text-surface-500">Religion</span><span class="font-medium">{{ detailStudent.religion?.display_name ?? detailStudent.religion?.name ?? '-' }}</span></div>
                <div class="flex justify-between border-b border-surface-200 dark:border-surface-700 pb-1"><span class="text-surface-500">Birthplace</span><span class="font-medium">{{ detailStudent.birthplace ?? '-' }}</span></div>
                <div class="flex justify-between border-b border-surface-200 dark:border-surface-700 pb-1"><span class="text-surface-500">Birthdate</span><span class="font-medium">{{ detailStudent.birthdate ?? '-' }}</span></div>
                <div class="flex justify-between border-b border-surface-200 dark:border-surface-700 pb-1"><span class="text-surface-500">Family Status</span><span class="font-medium">{{ detailStudent.family_status?.display_name ?? detailStudent.family_status?.name ?? '-' }}</span></div>
                <div class="flex justify-between border-b border-surface-200 dark:border-surface-700 pb-1"><span class="text-surface-500">Blood Type</span><span class="font-medium">{{ detailStudent.blood_type?.name ?? '-' }}</span></div>
                <div class="flex justify-between border-b border-surface-200 dark:border-surface-700 pb-1"><span class="text-surface-500">Address</span><span class="font-medium text-right">{{ detailStudent.address ?? '-' }}</span></div>
                <div class="flex justify-between border-b border-surface-200 dark:border-surface-700 pb-1"><span class="text-surface-500">Phone</span><span class="font-medium">{{ detailStudent.hp_num ?? '-' }}</span></div>
                <div class="flex justify-between border-b border-surface-200 dark:border-surface-700 pb-1"><span class="text-surface-500">Father Name</span><span class="font-medium">{{ detailStudent.father_name ?? '-' }}</span></div>
                <div class="flex justify-between border-b border-surface-200 dark:border-surface-700 pb-1"><span class="text-surface-500">Mother Name</span><span class="font-medium">{{ detailStudent.mother_name ?? '-' }}</span></div>
                <div class="flex justify-between border-b border-surface-200 dark:border-surface-700 pb-1"><span class="text-surface-500">Guardian Name</span><span class="font-medium">{{ detailStudent.guardian_name ?? '-' }}</span></div>
                <div class="flex justify-between border-b border-surface-200 dark:border-surface-700 pb-1"><span class="text-surface-500">Record Version</span><span class="font-medium">{{ detailStudent.version ?? '-' }}</span></div>
            </div>
            <div v-if="detailStudent.deleted_at" class="text-red-500">This student is archived. Reason: {{ detailStudent.archive_reason ?? '-' }}</div>
        </div>
        <template #footer>
            <Button label="Close" icon="pi pi-times" text severity="secondary" @click="studentDetailDialog = false" />
        </template>
    </Dialog>

    <!-- ── 03b: Archive Confirmation Dialog ───────────────────────────────────── -->
    <Dialog v-model:visible="archiveDialog" modal :style="{ width: '520px', maxWidth: '95vw' }" :draggable="false" header="Archive Student">
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle !text-3xl text-red-500" />
                <span>Are you sure you want to archive <b>{{ archiveTarget.name }}</b>? Active class memberships will be deactivated administratively (not recorded as a transfer-out).</span>
            </div>
            <div>
                <label for="archive-reason" class="block font-bold mb-2">Administrative Reason <span class="text-red-500">*</span></label>
                <Textarea id="archive-reason" v-model="archiveReason" rows="3" autoResize fluid />
                <small v-if="archiveErrors.archive_reason" class="text-red-500">{{ archiveErrors.archive_reason[0] }}</small>
            </div>
        </div>
        <template #footer>
            <Button label="Cancel" icon="pi pi-times" text severity="secondary" :disabled="isSubmitting" @click="archiveDialog = false" />
            <Button label="Archive Student" icon="pi pi-check" severity="danger" :loading="isSubmitting" :disabled="isSubmitting" @click="confirmArchiveStudent" />
        </template>
    </Dialog>

    <!-- ── 03b: Archived Students Dialog ──────────────────────────────────────── -->
    <Dialog v-model:visible="archivedListDialog" modal :style="{ width: '820px', maxWidth: '95vw' }" :draggable="false" header="Archived Students">
        <DataTable :value="archivedStudents" :loading="archivedLoading" dataKey="id" paginator :rows="10" showGridlines
                   paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                   currentPageReportTemplate="Showing {first} to {last} of {totalRecords} archived students">
            <template #empty> No archived students found. </template>
            <template #loading> Loading archived students. Please wait. </template>
            <Column header="No" style="width: 3rem">
                <template #body="slotProps">{{ slotProps.index + 1 }}</template>
            </Column>
            <Column field="name" header="Name" sortable style="min-width: 12rem" />
            <Column field="nik" header="NIK" style="min-width: 10rem" />
            <Column field="nis" header="NIS" style="min-width: 8rem" />
            <Column field="archive_reason" header="Archive Reason" style="min-width: 12rem" />
            <Column header="Action" :exportable="false" style="min-width: 8rem">
                <template #body="{ data }">
                    <Button label="Restore" icon="pi pi-refresh" size="small" severity="success" variant="outlined"
                            :loading="isSubmitting" :disabled="isSubmitting" @click="restoreStudent(data)" />
                </template>
            </Column>
        </DataTable>
        <template #footer>
            <Button label="Close" icon="pi pi-times" text severity="secondary" @click="archivedListDialog = false" />
        </template>
    </Dialog>

    <!-- ── 03d: Assign & Transfer Rombel Dialog ────────────────────────── -->
    <Dialog v-model:visible="placementDialog" modal :style="{ width: '500px', maxWidth: '95vw' }" :draggable="false"
            :header="placementMode === 'assign' ? 'Assign to Class' : 'Transfer Class'">
        <div class="flex flex-col gap-4">
            <div class="bg-surface-50 dark:bg-surface-800 p-3 rounded-border flex flex-col gap-1 text-sm border border-surface-200 dark:border-surface-700">
                <div class="flex justify-between">
                    <span class="text-surface-500 font-medium">Student Name:</span>
                    <span class="font-semibold">{{ placementTarget.name }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-surface-500 font-medium">NIS:</span>
                    <span>{{ placementTarget.nis }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-surface-500 font-medium">Academic Year:</span>
                    <span class="font-medium text-primary">{{ school_years_now?.name ?? '-' }}</span>
                </div>
                <div v-if="placementMode === 'transfer'" class="flex justify-between">
                    <span class="text-surface-500 font-medium">Current Class:</span>
                    <span class="font-semibold text-orange-600 dark:text-orange-400">{{ placementTarget.rombelName }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-surface-500 font-medium">Effective Date:</span>
                    <span class="text-surface-600 dark:text-surface-400 font-mono text-xs">Today (Asia/Jakarta server time)</span>
                </div>
            </div>

            <div class="flex flex-col gap-1">
                <label for="targetRombelSelect" class="font-medium text-sm">
                    Target Class <span class="text-red-500">*</span>
                </label>
                <Select id="targetRombelSelect" v-model="placementForm.target_rombel_id" :options="availablePlacementRombels"
                        optionLabel="name" optionValue="id" placeholder="Select target class"
                        :class="{ 'p-invalid': placementErrors.target_rombel_id || placementErrors.school_rombel_id }" />
                <small v-if="placementErrors.target_rombel_id" class="text-red-500 text-xs">{{ placementErrors.target_rombel_id[0] }}</small>
                <small v-if="placementErrors.school_rombel_id" class="text-red-500 text-xs">{{ placementErrors.school_rombel_id[0] }}</small>
            </div>

            <div v-if="placementMode === 'transfer'" class="flex flex-col gap-1">
                <label for="transferReason" class="font-medium text-sm">Transfer Reason (Optional)</label>
                <Textarea id="transferReason" v-model="placementForm.reason" rows="3" autoResize placeholder="Enter reason for class transfer..."
                          :class="{ 'p-invalid': placementErrors.reason }" />
                <small v-if="placementErrors.reason" class="text-red-500 text-xs">{{ placementErrors.reason[0] }}</small>
            </div>
        </div>
        <template #footer>
            <Button label="Cancel" icon="pi pi-times" text severity="secondary" :disabled="isSubmittingPlacement" @click="placementDialog = false" />
            <Button :label="placementMode === 'assign' ? 'Assign Class' : 'Transfer Student'"
                    :icon="placementMode === 'assign' ? 'pi pi-user-plus' : 'pi pi-arrow-right-arrow-left'"
                    :severity="placementMode === 'assign' ? 'info' : 'warn'"
                    :loading="isSubmittingPlacement" :disabled="isSubmittingPlacement"
                    @click="confirmPlacement" />
        </template>
    </Dialog>

    <!-- ── 03b: Optimistic Locking Conflict Dialog (HTTP 409) ─────────────────── -->
    <Dialog v-model:visible="conflictDialog" modal :style="{ width: '520px', maxWidth: '95vw' }" :draggable="false" header="Data Conflict Detected">
        <div class="flex items-start gap-4">
            <i class="pi pi-exclamation-triangle !text-3xl text-orange-500" />
            <div>
                <p class="mb-2">{{ conflictMessage }}</p>
                <p class="text-surface-500 text-sm">Your input is preserved. Click "Reload Latest" to fetch the newest data from the server, or "Keep Editing" to continue with your current input.</p>
            </div>
        </div>
        <template #footer>
            <Button label="Keep Editing" icon="pi pi-pencil" text severity="secondary" :disabled="reloadingConflict" @click="conflictDialog = false" />
            <Button label="Reload Latest" icon="pi pi-refresh" :loading="reloadingConflict" :disabled="reloadingConflict" @click="reloadAfterConflict" />
        </template>
    </Dialog>
</template>
