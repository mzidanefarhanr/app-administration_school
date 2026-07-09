<script setup>
import { ref, reactive, onMounted, watch, computed } from 'vue';
import Button from 'primevue/button';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import { useLayout } from '@/layout/composables/layout';
import { authStore } from '@/stores/authStore';
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import apiLogin from '@/apiLogin';
import gifSuccess from '@/assets/gif/success.gif';
import { FilterMatchMode, FilterOperator } from '@primevue/core/api';
import { CustomerService } from '@/service/CustomerService';

const confirm = useConfirm();
const toast = useToast();
const router = useRouter();
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
const students = ref([]);
const all_students = ref('');
const active_students = ref('');
const mutation_students = ref('');
const dropout_students = ref('');
const graduation_students = ref('');
const genders = ref([]);
const school_rombels = ref([]);
const student_entries = ref([]);
const student_statuses = ref([]);

const saveUser = ref([]);
const saveCompany = ref([]);

const visibleStaticBackdropWarning = ref(false);
const validContinue = ref(false);
var titleWarning = ref('');
var fieldWarning = ref('');
const visibleStaticBackdropDanger = ref(false);
var titleDanger = ref('');
var fieldDanger = ref('');
const visibleStaticBackdropSuccess = ref(false);
var titleSuccess = ref('');
var fieldSuccess = ref('');
const visibleStaticBackdropWaiting = ref(false);
var titleWaiting = ref('');
var fieldWaiting = ref('');

//function visibleStaticBackdropWarnings
function visibleStaticBackdropWarnings() {
    visibleStaticBackdropWarning.value = !visibleStaticBackdropWarning.value;
}

//function visibleStaticBackdropDangers
function visibleStaticBackdropDangers() {
    visibleStaticBackdropDanger.value = !visibleStaticBackdropDanger.value;
}

//function visibleStaticBackdropWaitings
function visibleStaticBackdropWaitings() {
    visibleStaticBackdropWaiting.value = !visibleStaticBackdropWaiting.value;
}

//function visibleStaticBackdropSuccesss
function visibleStaticBackdropSuccesss() {
    visibleStaticBackdropSuccess.value = !visibleStaticBackdropSuccess.value;
}
//function saveValidContinue
function saveValidContinue() {
    validContinue.value = !validContinue.value;
}

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

const fetchFilterOptions = async () => {
    const [gendersRes, rombelsRes, entriesRes, statusesRes] = await Promise.all([
        apiLogin.get('/api/genders'),
        apiLogin.get('/api/school-rombels'),
        apiLogin.get('/api/student-entries'),
        apiLogin.get('/api/student-statuses'),
    ]);
    genders.value        = gendersRes.data.data.map(g => g.name);
    school_rombels.value = rombelsRes.data.data.map(r => r.name);
    student_entries.value  = entriesRes.data.data.map(e => e.name);
    student_statuses.value = statusesRes.data.data.map(s => s.name);
}

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

            fetchFilterOptions();

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
        // console.log(all_students.value);
        // console.log(school_years_pick.value);
    });
    updateChartData();
}

//method fetchDataUserRole
const fetchDataUserRole = async () => {
    await useAuthStore.getUser();
    // console.log(useAuthStore.AllUserAssign);
    saveUser.value = useAuthStore.AllUserAssign;

    fetchDataSchoolYears();

    // fetchDataStudentRombels();
};

function handleSchoolYearsChange() {
    fetchDataSelected();
}

const filters = ref();

const loading = ref(true);
const length_rows_pick = ref(10);
const length_rows = ref([10, 25, 50, 100]);

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

const exportCSV = () => {
    // fetchDataUserRole();
    // console.log(dt.value);
    // Pastikan dt.value ada sebelum memanggil metodenya
    if (dt.value) {
        dt.value.exportCSV();
    } else {
        console.error("DataTable reference is not available.");
    }
};

const clearFilter = () => {
    initFilters();
};

const initFilters = () => {
    filters.value = {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
        'country.name': { value: null, matchMode: FilterMatchMode.STARTS_WITH },
        'student_nik.name': { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        'student_nik.gender.name': { value: null, matchMode: FilterMatchMode.STARTS_WITH },
        'student_nik.birthplace': { value: null, matchMode: FilterMatchMode.STARTS_WITH },
        'student_nik.birthdate': { value: null, matchMode: FilterMatchMode.CONTAINS },
        'student_nik.nik': { value: null, matchMode: FilterMatchMode.CONTAINS },
        'student_nik.nis': { value: null, matchMode: FilterMatchMode.CONTAINS },
        'student_nik.nisn': { value: null, matchMode: FilterMatchMode.CONTAINS },
        'school_rombel.name': { value: null, matchMode: FilterMatchMode.IN },
        'student_entry.name': { value: null, matchMode: FilterMatchMode.EQUALS },
        'student_status.name': { value: null, matchMode: FilterMatchMode.IN },
        representative: { value: null, matchMode: FilterMatchMode.IN }
    };
};

initFilters();

// Automatically compute the tab items whenever recapClassCounts updates
const tabItems = computed(() => {
    const levels = Object.keys(recapClassCounts.value);

    // Set a default active tab if none is selected yet
    if (levels.length > 0 && !activeTab.value) {
        activeTab.value = levels[0];
    }
    updateChartData();
    return levels.map(level => ({ label: `Class ${level}`, value: level }));
});

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
        labels: religionNames,
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
            { label: 'Mutation',        icon: 'pi pi-arrow-right-arrow-left',id: 'section-mutation' },
        ],
    },
    {
        key:   'relationships',
        label: 'Relationships',
        icon:  'pi pi-sitemap',
        subItems: [
            { label: 'Parents',          icon: 'pi pi-home',                 id: 'section-parents' },
            { label: 'Guardian Teacher', icon: 'pi pi-graduation-cap',       id: 'section-guardian' },
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
        const majorName  = student.school_rombel?.student_major?.name ?? '-';
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

const viewStudentProfile = (studentRombel) => {
    selectedStudent.value = studentRombel;
    profileDialog.value   = true;
};

const printProfile = () => {
    const el = document.getElementById('student-profile-print');
    if (!el) return;
    const win = window.open('', '_blank');
    win.document.write(`
        <html>
        <head>
            <title>Profil Siswa - ${selectedStudent.value?.student_nik?.name}</title>
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
                                Mutation Students
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
                <DataTable ref="dt" v-model:filters="filters" :value="school_years_pick" paginator showGridlines :rows="length_rows_pick" dataKey="id" filterDisplay="menu" :loading="loading"
                        :globalFilterFields="['student_nik.name', 'student_nik.nik', 'student_nik.nis', 'student_nik.nisn', 'school_rombel.name', 'student_entry.name', 'student_status.name']"
                        paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                currentPageReportTemplate="Showing {first} to {last} of {totalRecords} students">
                    <template #header>
                        <div class="mb-3 flex flex-col md:flex-row gap-4">
                            <div class="flex flex-wrap gap-2 w-full">
                                Total Student in {{ school_years_now ? school_years_now.name : "" }} is {{ school_years_pick ? school_years_pick.length : "Loading" }}
                            </div>
                        </div>
                        <div class="flex flex-col md:flex-row gap-4">
                            <div class="flex flex-wrap gap-2">
                                <Select v-model="length_rows_pick" :options="length_rows" placeholder="Select" class="w-5">
                                </Select>

                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button icon="pi pi-external-link" label="Export" @click="exportCSV($event)" />

                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button type="button" icon="pi pi-filter-slash" label="Clear" variant="outlined" @click="clearFilter()" />
                            </div>
                            <div class="flex flex-wrap gap-2 w-full">

                            </div>
                            <div class="flex flex-wrap gap-2 w-full">

                            </div>
                            <div class="flex flex-wrap gap-2 w-full">
                                <IconField>
                                    <InputIcon>
                                        <i class="pi pi-search" />
                                    </InputIcon>
                                    <InputText v-model="filters['global'].value" placeholder="Search" />
                                </IconField>

                            </div>
                        </div>

                    </template>
                    <template #empty> No Students found. </template>
                    <template #loading> Loading Students data. Please wait. </template>
                    <Column  header="No"  sortable style="width: 3rem">
                        <template #body="slotProps">
                            {{ slotProps.index + 1 }}
                        </template>
                    </Column>
                    <Column field="student_nik.name" filterField="student_nik.name" exportHeader="Name" header="Name" sortable style="min-width: 12rem">
                        <template #body="{ data }">
                            {{ data.student_nik.name }}
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" placeholder="Search by Name" />
                        </template>
                    </Column>
                    <Column header="Gender" filterField="student_nik.gender.name" :showFilterMatchModes="false" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            <Tag :value="data.student_nik.gender.name" :severity="getSeverity(data.student_nik.gender.name)" />
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <Select v-model="filterModel.value" @change="filterCallback()" :options="genders" placeholder="Select One" style="min-width: 12rem" :showClear="true">
                                <template #option="slotProps">
                                    <Tag :value="slotProps.option" :severity="getSeverity(slotProps.option)" />
                                </template>
                            </Select>
                        </template>
                    </Column>
                    <Column field="student_nik.birthplace" sortable filterField="student_nik.birthplace" exportHeader="Birthplace" header="Birthplace" style="min-width: 12rem">
                        <template #body="{ data }">
                            {{ data.student_nik.birthplace }}
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" placeholder="Search by Birthplace" />
                        </template>
                    </Column>
                    <Column field="student_nik.birthdate" sortable filterField="student_nik.birthdate" exportHeader="Birthdate" header="Birthdate" style="min-width: 12rem">
                        <template #body="{ data }">
                            {{ data.student_nik.birthdate }}
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" placeholder="Search by Birthdate" />
                        </template>
                    </Column>
                    <Column field="student_nik.nik" filterField="student_nik.nik" exportHeader="NIK" header="NIK" style="min-width: 12rem">
                        <template #body="{ data }">
                            {{ data.student_nik.nik }}
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" placeholder="Search by NIK" />
                        </template>
                    </Column>
                    <Column field="student_nik.nis" sortable filterField="student_nik.nis" exportHeader="NIS" header="NIS" style="min-width: 12rem">
                        <template #body="{ data }">
                            {{ data.student_nik.nis }}
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" placeholder="Search by NIS" />
                        </template>
                    </Column>
                    <Column field="student_nik.nisn" filterField="student_nik.nisn" exportHeader="NISN" header="NISN" style="min-width: 12rem">
                        <template #body="{ data }">
                            {{ data.student_nik.nisn }}
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <InputText v-model="filterModel.value" type="text" @input="filterCallback()" placeholder="Search by NISN" />
                        </template>
                    </Column>
                    <Column field="school_rombel.name" header="School Rombel" sortable filterField="school_rombel.name" :showFilterMatchModes="false" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            <Tag :value="data.school_rombel.name" :severity="getSeverity(data.school_rombel.name)" />
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <MultiSelect v-model="filterModel.value" @change="filterCallback()" :options="school_rombels" placeholder="Any">
                                <template #option="slotProps">
                                    <div class="flex items-center gap-2">
                                        <Tag :value="slotProps.option" :severity="getSeverity(slotProps.option)" />
                                    </div>
                                </template>
                            </MultiSelect>
                        </template>
                    </Column>
                    <Column field="student_entry.name" header="Student Entry" filterField="student_entry.name" :showFilterMatchModes="false" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            <Tag :value="data.student_entry.name" :severity="getSeverity(data.student_entry.name)" />
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <Select v-model="filterModel.value" @change="filterCallback()" :options="student_entries" placeholder="Select One" style="min-width: 12rem" :showClear="true">
                                <template #option="slotProps">
                                    <Tag :value="slotProps.option" :severity="getSeverity(slotProps.option)" />
                                </template>
                            </Select>
                        </template>
                    </Column>
                    <Column field="student_status.name" header="Student Statuses" filterField="student_status.name" :showFilterMatchModes="false" :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                        <template #body="{ data }">
                            <Tag :value="data.student_status.name" :severity="getSeverity(data.student_status.name)" />
                        </template>
                        <template #filter="{ filterModel, filterCallback }">
                            <MultiSelect v-model="filterModel.value" @change="filterCallback()" :options="student_statuses" placeholder="Any">
                                <template #option="slotProps">
                                    <div class="flex items-center gap-2">
                                        <Tag :value="slotProps.option" :severity="getSeverity(slotProps.option)" />
                                    </div>
                                </template>
                            </MultiSelect>
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
                                <td class="px-8 py-4 text-center font-medium">{{ rel.name }}</td>
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
                            {{ new Date().toLocaleDateString('id-ID', { day:'2-digit', month:'long', year:'numeric' }) }}
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
                                            px-3 py-2 text-center font-semibold text-blue-500">L</th>
                                    <th class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2 text-center font-semibold text-pink-500">P</th>
                                    <th class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2 text-center font-semibold">Total</th>
                                    <th class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2 text-center font-semibold text-blue-500">L</th>
                                    <th class="border border-surface-300 dark:border-surface-600
                                            px-3 py-2 text-center font-semibold text-pink-500">P</th>
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
                <div class="font-semibold text-xl">Profile Siswa</div>
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
                                    <span class="font-medium text-right">{{ searchQuery?.student_nik?.gender?.name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-surface-500">NIK</span>
                                    <span class="font-medium text-right">{{ searchQuery?.student_nik?.nik ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-surface-500">KK Number</span>
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
                                    <span class="font-medium text-right">{{ searchQuery?.student_nik?.religion?.name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-surface-500">Akta Number</span>
                                    <span class="font-medium text-right">{{ searchQuery?.student_nik?.akta ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-surface-500">Family Status</span>
                                    <span class="font-medium text-right">{{ searchQuery?.student_nik?.family_status?.name ?? '-' }}</span>
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
                                    <span class="text-surface-500">HP Number</span>
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
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.father_education_level?.name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Profession</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.father_profession?.name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Income</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.father_income ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">HP Number</span>
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
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.mother_education_level?.name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Profession</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.mother_profession?.name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Income</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.mother_income ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">HP Number</span>
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
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.guardian_education_level?.name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Profession</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.guardian_profession?.name ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">Income</span>
                                    <span class="font-medium text-sm text-right">{{ searchQuery?.student_nik?.guardian_income ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-surface-500 text-sm">HP Number</span>
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
                    <div class="font-semibold text-xl mb-4">Student Mutations</div>
                    <Skeleton width="100%" height="150px"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Student Mutations</div>
                    <span>No Student Mutations found.</span>
                </div>
                <div v-else class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Student Mutations</div>
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
                    <div class="font-semibold text-xl mb-4">Guardian Teacher</div>
                    <Skeleton width="100%" height="150px"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Guardian Teacher</div>
                    <span>No Guardian Teacher found.</span>
                </div>
                <div v-else class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Guardian Teacher</div>
                </div>
            </div>
        </div>


    </Fluid>

    <!-- <Button label="Danger" @click="visibleStaticBackdropWarnings" /> -->
    <Dialog v-model:visible="visibleStaticBackdropWarning" modal header="Warning!!" :style="{ width: '25rem' }">
        <div class="text-center justify-content-center align-items-center">
            <p>Data yang anda masukkan sudah benar?</p>
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
                    <Button label="Simpan" @click="acceptCallback"></Button>
                    <Button label="Batal" outlined @click="rejectCallback"></Button>
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
    <Dialog v-model:visible="visibleStaticBackdropWaiting" modal header="Silahkan menunggu data anda diproses!">
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
        header="Profil Siswa"
        :draggable="false"
    >
        <template #header>
            <div class="flex items-center justify-between w-full">
                <span class="font-bold text-lg">Profil Siswa</span>
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
                Profil Siswa SMA YAPPENDA
            </h2>

            <!-- ── A. Profil Siswa ────────────────────────────────────────────── -->
            <div class="font-bold border-b border-surface-300 dark:border-surface-600 pb-1 mb-3">
                A. Profil Siswa
            </div>
            <div class="flex flex-col gap-1 pl-4 mb-4">
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Nama Siswa</span>
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
                    <span class="w-40 shrink-0">Kelas</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.school_rombel?.name ?? '-' }}</span>
                </div>
            </div>

            <!-- ── B. Identitas Siswa ─────────────────────────────────────────── -->
            <div class="font-bold border-b border-surface-300 dark:border-surface-600 pb-1 mb-3">
                B. Identitas Siswa
            </div>
            <div class="flex flex-col gap-1 pl-4 mb-4">
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Jenis Kelamin</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.gender?.name ?? '-' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">NIK</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.nik ?? '-' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">No. KK</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.no_kk ?? '-' }}</span>
                </div>
                <div class="flex gap-4">
                    <div class="flex gap-2 flex-1">
                        <span class="w-40 shrink-0">Tempat Lahir</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.birthplace ?? '-' }}</span>
                    </div>
                    <div class="flex gap-2 flex-1">
                        <span class="w-28 shrink-0">Tanggal Lahir</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.birthdate ?? '-' }}</span>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="flex gap-2 flex-1">
                        <span class="w-40 shrink-0">Agama</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.religion?.name ?? '-' }}</span>
                    </div>
                    <div class="flex gap-2 flex-1">
                        <span class="w-28 shrink-0">Status Keluarga</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.family_status?.name ?? '-' }}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Anak Ke</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.child_order ?? '-' }}</span>
                    <span class="mx-2">Dari</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.child_of ?? '-' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">No. Akta Kelahiran</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.birth_certificate_number ?? '-' }}</span>
                </div>
            </div>

            <!-- ── C. Kontak Siswa ────────────────────────────────────────────── -->
            <div class="font-bold border-b border-surface-300 dark:border-surface-600 pb-1 mb-3">
                C. Kontak Siswa
            </div>
            <div class="flex flex-col gap-1 pl-4 mb-4">
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Alamat</span>
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
                    <span class="w-40 shrink-0">Kelurahan</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.village?.name ?? '-' }}</span>
                </div>
                <div class="flex gap-4">
                    <div class="flex gap-2 flex-1">
                        <span class="w-40 shrink-0">Kecamatan</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.district?.name ?? '-' }}</span>
                    </div>
                    <div class="flex gap-2 flex-1">
                        <span class="w-10 shrink-0">Kota</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.regency?.name ?? '-' }}</span>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="flex gap-2 flex-1">
                        <span class="w-40 shrink-0">Kode Pos</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.zip_code ?? '-' }}</span>
                    </div>
                    <div class="flex gap-2 flex-1">
                        <span class="w-10 shrink-0">No. HP</span>
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
                D. Data Ijazah
            </div>
            <div class="flex flex-col gap-1 pl-4 mb-4">
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Asal Sekolah</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.origin_school ?? '-' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Nomor Ijazah</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.certificate_number ?? '-' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Tahun Ijazah</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.certificate_year ?? '-' }}</span>
                </div>
            </div>

            <!-- ── E. Identitas Ayah ──────────────────────────────────────────── -->
            <div class="font-bold border-b border-surface-300 dark:border-surface-600 pb-1 mb-3">
                E. Identitas Ayah
            </div>
            <div class="flex flex-col gap-1 pl-4 mb-4">
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Nama</span>
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
                        <span class="w-40 shrink-0">Tempat, Tgl Lahir</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.father_birthplace ?? '-' }}</span>
                    </div>
                    <div class="flex gap-2 flex-1">
                        <span class="w-28 shrink-0">Tanggal Lahir</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.father_birthdate ?? '-' }}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Pendidikan Terakhir</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.father_last_education ?? '-' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Pekerjaan</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.father_job ?? '-' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Penghasilan</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.father_income
                        ? 'Rp' + Number(selectedStudent.student_nik.father_income).toLocaleString('id-ID')
                        : 'Rp0' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">HP</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.father_phone ?? '-' }}</span>
                </div>
            </div>

            <!-- ── F. Identitas Ibu ───────────────────────────────────────────── -->
            <div class="font-bold border-b border-surface-300 dark:border-surface-600 pb-1 mb-3">
                F. Identitas Ibu
            </div>
            <div class="flex flex-col gap-1 pl-4 mb-4">
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Nama</span>
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
                        <span class="w-40 shrink-0">Tempat, Tgl Lahir</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.mother_birthplace ?? '-' }}</span>
                    </div>
                    <div class="flex gap-2 flex-1">
                        <span class="w-28 shrink-0">Tanggal Lahir</span>
                        <span class="w-3 shrink-0">:</span>
                        <span>{{ selectedStudent.student_nik?.mother_birthdate ?? '-' }}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Pendidikan Terakhir</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.mother_last_education ?? '-' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Pekerjaan</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.mother_job ?? '-' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">Penghasilan</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.mother_income
                        ? 'Rp' + Number(selectedStudent.student_nik.mother_income).toLocaleString('id-ID')
                        : 'Rp0' }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-40 shrink-0">HP</span>
                    <span class="w-3 shrink-0">:</span>
                    <span>{{ selectedStudent.student_nik?.mother_phone ?? '-' }}</span>
                </div>
            </div>

            <!-- ── Signature ──────────────────────────────────────────────────── -->
            <div class="flex justify-end mt-6 pr-4">
                <div class="text-center">
                    <p>Jakarta, {{ new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }) }}</p>
                    <p>Siswa SMA YAPPENDA,</p>
                    <div class="mt-12 mb-1 font-bold underline">
                        {{ selectedStudent.student_nik?.name ?? '' }}
                    </div>
                </div>
            </div>

        </div>
    </Dialog>
</template>
