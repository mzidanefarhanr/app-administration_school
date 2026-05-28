<script setup>
import { ref, reactive, onMounted, watch, computed } from 'vue';
import Button from 'primevue/button';
import Chip from 'primevue/chip';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import { useLayout } from '@/layout/composables/layout';
import { authStore } from '@/stores/authStore';
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import apiLogin from '@/apiLogin';
import { FilterMatchMode, FilterOperator } from '@primevue/core/api';

const confirm = useConfirm();
const toast = useToast();
const router = useRouter();
const { getPrimary, getSurface, isDarkTheme } = useLayout();
const lineData = ref(null);
const doughnutData = ref(null);
const pieData = ref(null);
const polarData = ref(null);
const barData = ref(null);
const radarData = ref(null);
const lineOptions = ref(null);
const doughnutOptions = ref(null);
const pieOptions = ref(null);
const setDoughnutOptions = ref(null);
const setPieOptions = ref(null);
const polarOptions = ref(null);
const barOptions = ref(null);
const radarOptions = ref(null);
const activeTab = ref('');
const recapClassCounts = ref([]);
const dt = ref();
const useAuthStore = authStore();
const school_years = ref([]);
const school_years_now = ref('');
const school_years_pick = ref([]);
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
const inputTypePassword_old = ref('');
const inputTypePassword_new = ref('');
const inputTypePassword_new_confirm = ref('');
const inputIconPassword_old = ref('');
const inputIconPassword_new = ref('');
const inputIconPassword_new_confirm = ref('');
const entityLabel       = ref('');
const entityLabelPlural = ref('');
const showPassword_old = ref(false);
const showPassword_new = ref(false);
const showPassword_new_confirm = ref(false);
const EditOrNewDialog = ref(false);
const deleteDialog    = ref(false);
const deletesDialog   = ref(false);
const WaitingDialog   = ref(false);
const ErrorDialog     = ref(false);
const titleError      = ref('');
const fieldError      = ref('');

// Fungsi untuk menginisialisasi data default
const getDefaultFormData = () => ({
    username: '',
    password_old: '',
    password_new: '',
    password_new_confirm: '',
});

const form = reactive(getDefaultFormData());
const _method= "PATCH";

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


const fetchValidate = async () => {
    if (showPassword_old.value == true) {
        inputTypePassword_old.value = 'text';
        inputIconPassword_old.value = 'pi pi-eye-slash !text-black/70';
    } else {
        inputTypePassword_old.value = 'password';
        inputIconPassword_old.value = 'pi pi-eye !text-black/70';
    }
    if (showPassword_new.value == true) {
        inputTypePassword_new.value = 'text';
        inputIconPassword_new.value = 'pi pi-eye-slash !text-black/70';
    } else {
        inputTypePassword_new.value = 'password';
        inputIconPassword_new.value = 'pi pi-eye !text-black/70';
    }
    if (showPassword_new_confirm.value == true) {
        inputTypePassword_new_confirm.value = 'text';
        inputIconPassword_new_confirm.value = 'pi pi-eye-slash !text-black/70';
    } else {
        inputTypePassword_new_confirm.value = 'password';
        inputIconPassword_new_confirm.value = 'pi pi-eye !text-black/70';
    }
};

//method fetchDataGenders
const fetchDataGenders = async () => {
    //fetch data
    const get_genders = ref([]);
    genders.value.length = 0;
    await apiLogin.get('/api/genders')
    .then(response => {
        //set response data to state "Genders"
        get_genders.value = response.data.data;
        genders.value = get_genders.value.map(person => person.name);
        // console.log(genders.value);
    });
}

//method fetchDataSchoolRombels
const fetchDataSchoolRombels = async () => {
    //fetch data
    const get_school_rombels = ref([]);
    school_rombels.value.length = 0;
    await apiLogin.get('/api/school-rombels')
    .then(response => {
        //set response data to state "School Rombels"
        get_school_rombels.value = response.data.data;
        school_rombels.value = get_school_rombels.value.map(person => person.name);
    });
}

//method fetchDataStudentEntries
const fetchDataStudentEntries = async () => {
    //fetch data
    const get_student_entries = ref([]);
    student_entries.value.length = 0;
    await apiLogin.get('/api/student-entries')
    .then(response => {
        //set response data to state "Students Entries"
        get_student_entries.value = response.data.data;
        student_entries.value = get_student_entries.value.map(person => person.name);
    });
}

//method fetchDataStudentStatuses
const fetchDataStudentStatuses = async () => {
    //fetch data
    const get_student_statuses = ref([]);
    student_statuses.value.length = 0;
    await apiLogin.get('/api/student-statuses')
    .then(response => {
        //set response data to state "Students Statuses"
        get_student_statuses.value = response.data.data;
        student_statuses.value = get_student_statuses.value.map(person => person.name);
    });
}

//method fetchDataSchoolYears
const fetchDataSchoolYears = async () => {
    //fetch data
    school_years.value.length = 0;
    school_years_pick.value.length = 0;
    loading.value = true;
    await apiLogin.get('/api/school-years')
    .then(response => {
        //set response data to state "Students"
        school_years.value = response.data.data;
    });
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

//method fetchDataStudentRombels
const fetchDataStudentRombels = async () => {
    //fetch data
    school_years_pick.value.length = 0;
    dt.value = null;
    await apiLogin.get('/api/student-rombels')
    .then(response => {
        //set response data to state "Students"
        students.value = response.data.data;
        // all_students.value = students.value.length;
        loading.value = false;
        let index_active_students = 0;
        let index_mutation_students = 0;
        let index_dropout_students = 0;
        let index_graduation_students = 0;

        // Mengelompokkan data berdasarkan status dalam satu kali jalan
        const counts = students.value.reduce((acc, student) => {
            // Cek tahun ajaran
            if (school_years_now.value && student.school_rombel.school_year_id == school_years_now.value.id) {
                const status = student.student_status_id;
                acc[status] = (acc[status] || 0) + 1;
                school_years_pick.value = students.value;
            }
            return acc;
        }, {});

        // Sekarang Anda tinggal mengambil hasilnya tanpa perlu looping berkali-kali
        index_active_students = counts[1] || 0;
        index_mutation_students = counts[2] || 0;
        index_dropout_students = counts[3] || 0;
        index_graduation_students = counts[4] || 0;
        if (school_years_pick.value.length > 0) {

            fetchDataGenders();

            fetchDataSchoolRombels();

            fetchDataStudentEntries();

            fetchDataStudentStatuses();

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
    fetchDataUserRole();
}


const customers = ref();
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
    fetchDataUserRole();
    console.log(dt.value);
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

    // ── 1. GENDER PIE CHART (Unchanged) ──
    const maleCount = school_years_pick.value.filter(s => s.student_nik?.gender?.name === 'Laki-Laki').length;
    const femaleCount = totalStudents - maleCount;

    pieData.value = {
        labels: ['Male', 'Female'],
        datasets: [
            {
                data: [maleCount, femaleCount],
                backgroundColor: ['rgba(54, 162, 235, 0.8)', 'rgba(255, 99, 132, 0.8)'],
                hoverBackgroundColor: ['rgba(54, 162, 235, 1)', 'rgba(255, 99, 132, 1)']
            }
        ]
    };
    // pieOptions.value = setPieOptions();
    pieOptions.value = {
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

        // If the key doesn't exist in our object yet, initialize it to 0, then add 1
        if (!gradeCounts[levelName]) {
            gradeCounts[levelName] = 0;
        }
        gradeCounts[levelName]++;

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
    // doughnutOptions.value = setPieOptions();
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
                backgroundColor: documentStyle.getPropertyValue('--p-primary-500'),
                borderColor: documentStyle.getPropertyValue('--p-primary-500'),
                data: maleData
            },
            {
                label: 'Female',
                backgroundColor: documentStyle.getPropertyValue('--p-primary-200'),
                borderColor: documentStyle.getPropertyValue('--p-primary-200'),
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
};

onMounted(() => {
    fetchDataUserRole();
    fetchValidate();
});
</script>
<template>
    <Fluid>
        <div class="bg-surface-0 dark:bg-surface-950 px-6 py-8 md:px-12 lg:px-20">
            <div class="flex items-start flex-col md:justify-between md:flex-row">
                <div>
                    <div class="font-bold text-3xl text-surface-900 dark:text-surface-0 mb-4">School Profile - {{ school_years_now ? school_years_now.name : "" }}</div>
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
        <div class="bg-surface-0 dark:bg-surface-950 px-6 py-8 md:px-12 lg:px-20 mt-4 mb-4">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2 pb-4">
                    <div class="font-semibold text-xl text-surface-900 dark:text-surface-0 leading-tight">A. School Identity</div>
                    <!-- <div class="text-surface-500 dark:text-surface-300 text-base leading-tight">Morbi tristique blandit turpis. In viverra ligula id nulla hendrerit rutrum.</div> -->
                </div>

                <div class="border-t border-surface-200 dark:border-surface-700" />

                <div class="flex flex-col gap-4">
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">Name</div>
                            <div class="flex-1 text-surface-900 dark:text-surface-0 text-base leading-tight">Heat</div>
                        </div>
                        <div class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" icon-only class="shrink-0" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">NPSN</div>
                            <div class="flex-1 flex flex-wrap gap-2">
                                <Chip label="Crime" severity="secondary" />
                                <Chip label="Drama" severity="secondary" />
                                <Chip label="Thriller" severity="secondary" />
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" icon-only class="shrink-0" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">Status</div>
                            <div class="flex-1 text-surface-900 dark:text-surface-0 text-base leading-tight">Michael Mann</div>
                        </div>
                        <div class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" icon-only class="shrink-0" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">NDS</div>
                            <div class="flex-1 text-surface-900 dark:text-surface-0 text-base leading-tight">Michael Mann</div>
                        </div>
                        <div class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" icon-only class="shrink-0" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-start gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">NSS</div>
                            <div class="flex-1 text-surface-900 dark:text-surface-0 text-base leading-normal">
                                A group of professional bank robbers start to feel the heat from police when they unknowingly leave a clue at their latest heist.
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" icon-only class="shrink-0" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">School Address</div>
                            <div class="flex-1 text-surface-900 dark:text-surface-0 text-base leading-tight">Michael Mann</div>
                        </div>
                        <div class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" icon-only class="shrink-0" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">ZIP Code</div>
                            <div class="flex-1 text-surface-900 dark:text-surface-0 text-base leading-tight">Michael Mann</div>
                        </div>
                        <div class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" icon-only class="shrink-0" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">Official Number</div>
                            <div class="flex-1 text-surface-900 dark:text-surface-0 text-base leading-tight">Michael Mann</div>
                        </div>
                        <div class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" icon-only class="shrink-0" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">Email</div>
                            <div class="flex-1 text-surface-900 dark:text-surface-0 text-base leading-tight">Michael Mann</div>
                        </div>
                        <div class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" icon-only class="shrink-0" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">Website</div>
                            <div class="flex-1 text-surface-900 dark:text-surface-0 text-base leading-tight">Michael Mann</div>
                        </div>
                        <div class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" icon-only class="shrink-0" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />
                </div>
            </div>
        </div>
        <div class="bg-surface-0 dark:bg-surface-950 px-6 py-8 md:px-12 lg:px-20 mb-4">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2 pb-4">
                    <div class="font-semibold text-xl text-surface-900 dark:text-surface-0 leading-tight">B. Principal Identity</div>
                    <!-- <div class="text-surface-500 dark:text-surface-300 text-base leading-tight">Morbi tristique blandit turpis. In viverra ligula id nulla hendrerit rutrum.</div> -->
                </div>

                <div class="border-t border-surface-200 dark:border-surface-700" />

                <div class="flex flex-col gap-4">
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">Name</div>
                            <div class="flex-1 text-surface-900 dark:text-surface-0 text-base leading-tight">Heat</div>
                        </div>
                        <div class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" icon-only class="shrink-0" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">NRKS</div>
                            <div class="flex-1 flex flex-wrap gap-2">
                                <Chip label="Crime" severity="secondary" />
                                <Chip label="Drama" severity="secondary" />
                                <Chip label="Thriller" severity="secondary" />
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" icon-only class="shrink-0" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">NUPTK</div>
                            <div class="flex-1 text-surface-900 dark:text-surface-0 text-base leading-tight">Michael Mann</div>
                        </div>
                        <div class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" icon-only class="shrink-0" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">TMT Principal</div>
                            <div class="flex-1 text-surface-900 dark:text-surface-0 text-base leading-tight">Michael Mann</div>
                        </div>
                        <div class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" icon-only class="shrink-0" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />
                </div>
            </div>
        </div>
        <div class="bg-surface-0 dark:bg-surface-950 px-6 py-8 md:px-12 lg:px-20">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2 pb-4">
                    <div class="font-semibold text-xl text-surface-900 dark:text-surface-0 leading-tight">C. Committee Identity</div>
                    <!-- <div class="text-surface-500 dark:text-surface-300 text-base leading-tight">Morbi tristique blandit turpis. In viverra ligula id nulla hendrerit rutrum.</div> -->
                </div>

                <div class="border-t border-surface-200 dark:border-surface-700" />

                <div class="flex flex-col gap-4">
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">Name</div>
                            <div class="flex-1 text-surface-900 dark:text-surface-0 text-base leading-tight">Heat</div>
                        </div>
                        <div class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" icon-only class="shrink-0" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">Call Number</div>
                            <div class="flex-1 flex flex-wrap gap-2">
                                <Chip label="Crime" severity="secondary" />
                                <Chip label="Drama" severity="secondary" />
                                <Chip label="Thriller" severity="secondary" />
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" icon-only class="shrink-0" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />
                </div>
            </div>
        </div>
    </Fluid>

    <!-- Confirm Delete Dialog -->
    <Dialog v-model:visible="deleteDialog" :style="{ width: '450px' }" header="Confirm" :modal="true">
        <div class="flex items-center gap-4">
            <i class="pi pi-exclamation-triangle !text-3xl" />
            <span v-if="singleData"
                >Are you sure you want to delete <b>{{ singleData.name }}</b
                >?</span
            >
        </div>
        <template #footer>
            <Button label="No" icon="pi pi-times" text @click="deleteDialog = false" severity="secondary" variant="text" />
            <Button label="Yes" icon="pi pi-check" @click="deleteSingleData" severity="danger" />
        </template>
    </Dialog>
    <!-- Confirm Delete Selected Dialog -->
    <Dialog v-model:visible="deletesDialog" :style="{ width: '450px' }" header="Confirm" :modal="true">
        <div class="flex items-center gap-4">
            <i class="pi pi-exclamation-triangle !text-3xl" />
            <span v-if="selectedManyDatas">Are you sure you want to delete {{ selectedManyDatas.length }} data selected {{ entityLabelPlural }}?</span>
        </div>
        <template #footer>
            <Button label="No" icon="pi pi-times" text @click="deletesDialog = false" severity="secondary" variant="text" />
            <Button label="Yes" icon="pi pi-check" text @click="deleteSelectedManyDatas" severity="danger" />
        </template>
    </Dialog>
    <!-- Confirm Edit Dialog -->
    <Dialog v-model:visible="EditOrNewDialog" :style="{ width: '450px' }" :header="entityLabel+' Details'" :modal="true">
        <div class="flex flex-col gap-6">
            <div>
                <label for="name" class="block font-bold mb-3">Name</label>
                <InputText id="name" v-model="singleData.name" required="true" autofocus :invalid="submitted && !singleData.name" fluid />
                <small v-if="submitted && !singleData.name" class="text-red-500">Name is required.</small>
                <small v-else-if="singleData.name && singleData.name.trim().length > 0 && singleData.name.trim().length <= 3" class="text-yellow-500">Name must be at least 4 characters.</small>
                <small v-else-if="singleData.name && singleData.name === previousData.name" class="text-blue-500">Current Name (no changes).</small>
                <small v-else-if="isCat1Used" class="text-red-500">This name is already taken.</small>
                <small v-else-if="singleData.name && singleData.name.trim().length > 3" class="text-green-500">Name is available!</small>

            </div>
        </div>

        <template #footer>
            <Button label="Cancel" icon="pi pi-times" text @click="hideDialog" />
            <Button v-if="!isCat1Used && validFieldCount > 0" label="Save" icon="pi pi-check" @click="saveSingleData" />
        </template>
    </Dialog>
    <!-- Loading Dialog -->
    <Dialog v-model:visible="WaitingDialog" modal header="Processing Request">
        <div class="text-center justify-content-center align-items-center">
            <i class="pi pi-spin pi-spinner" style="font-size: 2rem; color: green"></i>
            <p class="text-success mt-3" style="font-size: 1.1rem; font-weight: 500;">
            Please wait, your data is being processed...
        </p>
        </div>
    </Dialog>
    <!-- Error Dialog -->
    <Dialog v-model:visible="ErrorDialog" modal :header="titleError" style=" width: 25rem; color: red;">
        <div style="color: black;">
            {{ fieldError }}
        </div>
    </Dialog>
</template>
