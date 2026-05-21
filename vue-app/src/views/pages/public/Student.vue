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
const showPassword_old = ref(false);
const showPassword_new = ref(false);
const showPassword_new_confirm = ref(false);

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

//method fetchDataNewPassword
const fetchDataNewPassword = async () => {
    await apiLogin.post(`/api/users/${saveUser.value.id}`, {
        name              : saveUser.value.name,
        email             : saveUser.value.email,
        username          : saveUser.value.username,
        password          : form.password_old,
        password_new      : form.password_new_confirm,
        type_user_id      : saveUser.value.type_user_id,
        status_user_id    : saveUser.value.status_user_id,
        _method: _method,
    }).then(response => {
        if (response.data.success === true) {
            console.log("Sukses")
            visibleStaticBackdropWaitings();
            visibleStaticBackdropSuccesss();
            titleSuccess = 'Success!!';
            fieldSuccess = 'Password Baru Berhasil Dibuat!';
            setTimeout(function () {
                visibleStaticBackdropSuccesss();
                fetchDataUserRole();
                Object.assign(form, getDefaultFormData());
            }, 2000);
        }
        // console.log(response.data.message);
        // console.log(response.data);
    }).catch(error => {
        console.error(error);
        if (error.response.status === 422) {
            // alert('"These credentials do not match our records."');
            // getMessage.value = error.response.data.message;
            visibleStaticBackdropWaitings();
            titleDanger = 'GAGAL!!';
            fieldDanger = 'Password tidak tervalidasi, silahkan masukkan data dengan benar!!';
            visibleStaticBackdropDangers();
        } else {
            // alert('An error occurred');
            // getMessage.value = error.response.data.message;
            visibleStaticBackdropWaitings();
            titleDanger = 'GAGAL!!';
            fieldDanger = error.response.data.message;
            visibleStaticBackdropDangers();
        }
    });
}

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
            <ul class="list-none p-0 m-0 flex items-center font-medium mb-5">
                <li>
                    <a class="text-surface-500 dark:text-surface-300 no-underline leading-normal cursor-pointer">Dashboard</a>
                </li>
                <li class="px-2">
                    <i class="pi pi-angle-right text-surface-500 dark:text-surface-300 !text-sm !leading-normal" />
                </li>
                <li>
                    <span class="text-surface-900 dark:text-surface-0 leading-normal">Students</span>
                </li>
            </ul>
            <div class="flex items-start flex-col md:justify-between md:flex-row">
                <div>
                    <div class="font-bold text-3xl text-surface-900 dark:text-surface-0 mb-4">Students - {{ school_years_now ? school_years_now.name : "" }}</div>
                    <div class="flex items-center text-surface-700 dark:text-surface-300 flex-wrap gap-8">
                        <!-- <div class="flex items-center gap-2">
                            <i class="pi pi-graduation-cap !text-base !leading-normal" />
                            <span v-if="students">{{ loading == false ? all_students : "" }}<Skeleton v-if="loading == true" width="3rem" height="2rem" /> All Students</span>
                        </div> -->
                        <div class="flex items-center gap-2">
                            <i class="pi pi-graduation-cap !text-base !leading-normal" />
                            <i class="pi pi-plus !text-base !leading-normal" />
                            <span style="color: green;">{{ loading == false ? active_students : "" }}<Skeleton v-if="loading == true" width="3rem" height="2rem" /> Active Students</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i class="pi pi-graduation-cap !text-base !leading-normal" />
                            <i class="pi pi-minus !text-base !leading-normal" />
                            <span style="color: red;">{{ loading == false ? mutation_students : "" }}<Skeleton v-if="loading == true" width="3rem" height="2rem" /> Mutation Students</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i class="pi pi-graduation-cap !text-base !leading-normal" />
                            <i class="pi pi-minus !text-base !leading-normal" />
                            <span style="color: red;">{{ loading == false ? dropout_students : "" }}<Skeleton v-if="loading == true" width="3rem" height="2rem" /> Drop Out Students</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i class="pi pi-graduation-cap !text-base !leading-normal" />
                            <i class="pi pi-star-fill !text-base !leading-normal" />
                            <span style="color: greenyellow;">{{ loading == false ? graduation_students : "" }}<Skeleton v-if="loading == true" width="3rem" height="2rem" /> Graduation Students</span>
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

        <div class="grid grid-cols-12 mt-8 gap-8">
            <div class="col-span-12 xl:col-span-6">
                <div v-if="loading == true" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total Students</div>
                    <Skeleton shape="circle" size="20rem" class="mr-2"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total Students</div>
                    <span>No Students found.</span>
                </div>
                <div v-else class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total Students</div>
                    <Chart type="doughnut" :data="doughnutData" :options="doughnutOptions"></Chart>
                </div>
            </div>
            <div class="col-span-12 xl:col-span-6">
                <div v-if="loading == true" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total Genders</div>
                    <Skeleton shape="circle" size="20rem" class="mr-2"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total Genders</div>
                    <span>No Students found.</span>
                </div>
                <div v-else class="card flex flex-col items-center">
                    <div class="font-semibold text-xl mb-4">Total Genders</div>
                    <Chart type="pie" :data="pieData" :options="pieOptions"></Chart>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-12 mt-8 gap-8">
            <div class="col-span-12 xl:col-span-6">
                <div v-if="loading == true" class="card">
                    <div class="font-semibold text-xl mb-4">Class Breakdown</div>
                    <Skeleton width="100%" height="150px"></Skeleton>
                </div>
                <div v-else-if="school_years_pick.length == 0" class="card">
                    <div class="font-semibold text-xl mb-4">Class Breakdown</div>
                    <span>No Students found.</span>
                </div>
                <div v-else class="card">
                    <div class="font-semibold text-xl mb-4">Class Breakdown</div>

                    <Tabs v-model:value="activeTab" class="mb-4">
                        <TabList>
                            <Tab v-for="tab in tabItems" :key="tab.value" :value="tab.value">
                                {{ tab.label }}
                            </Tab>
                        </TabList>
                    </Tabs>

                    <Chart type="bar" :data="barData" :options="barOptions"></Chart>
                </div>
            </div>
        </div>

        <div class="flex mt-8">
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
</template>
