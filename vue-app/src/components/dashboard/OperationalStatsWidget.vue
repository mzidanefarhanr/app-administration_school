<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import apiLogin from '@/apiLogin';
import { authStore } from '@/stores/authStore';

const router = useRouter();
const store = authStore();

const overview = ref(null);
const loading = ref(false);
const error = ref('');

const canViewStudents = computed(() => store.can('students.view'));
const canViewEmployees = computed(() => store.can('employees.view'));
const canViewLetters = computed(() => store.can('letters.view'));
const canViewLeaves = computed(() => store.can('leaves.view'));
const canViewTeaching = computed(() => store.can('teaching.view'));

const academic = computed(() => overview.value?.academic ?? null);
const employees = computed(() => overview.value?.employees ?? null);
const letters = computed(() => overview.value?.letters ?? null);
const leaves = computed(() => overview.value?.leaves ?? null);
const teaching = computed(() => overview.value?.teaching ?? null);

const load = async () => {
    loading.value = true;
    error.value = '';
    try {
        const response = await apiLogin.get('/api/dashboard/overview');
        overview.value = response.data?.data ?? null;
    } catch {
        error.value = 'Ringkasan metrik dashboard tidak dapat dimuat.';
    } finally {
        loading.value = false;
    }
};

const navigateTo = (path) => {
    if (path) {
        router.push(path);
    }
};

onMounted(async () => {
    if (!store.abilities?.length) {
        await store.fetchIdentity();
    }
    await load();
});

defineExpose({ load });
</script>

<template>
    <div class="col-span-12">
        <div v-if="error" class="card mb-4 p-4 border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950/20 text-red-700 dark:text-red-400 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="pi pi-exclamation-circle text-lg" aria-hidden="true"></i>
                <span class="text-sm font-medium">{{ error }}</span>
            </div>
            <Button label="Muat Ulang" icon="pi pi-refresh" size="small" severity="danger" variant="outlined" :loading="loading" @click="load" />
        </div>

        <div class="grid grid-cols-12 gap-6">
            <!-- Card 1: Siswa & Rombel Aktif -->
            <div class="col-span-12 sm:col-span-6 xl:col-span-3">
                <div class="card mb-0 h-full flex flex-col justify-between transition-all duration-200" :class="{ 'cursor-pointer hover:shadow-md': canViewStudents }" @click="canViewStudents ? navigateTo('/public/students/directory') : null">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <span class="block text-muted-color font-medium mb-2">Siswa Aktif</span>
                            <div class="text-surface-900 dark:text-surface-0 font-bold text-2xl">
                                <span v-if="loading"><i class="pi pi-spin pi-spinner text-base"></i></span>
                                <span v-else-if="academic?.total_active_students !== null && academic?.total_active_students !== undefined">
                                    {{ Number(academic.total_active_students).toLocaleString('id-ID') }}
                                </span>
                                <span v-else class="text-surface-400 font-normal text-lg">Terbatas</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-center bg-blue-100 dark:bg-blue-400/10 rounded-border shrink-0" style="width: 2.75rem; height: 2.75rem">
                            <i class="pi pi-users text-blue-500 !text-xl" aria-hidden="true"></i>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-surface text-xs">
                        <span class="text-muted-color">
                            <template v-if="academic?.total_active_rombels !== null && academic?.total_active_rombels !== undefined">
                                <strong class="text-primary font-semibold">{{ academic.total_active_rombels }}</strong> Rombel Aktif
                            </template>
                            <template v-else> Periode: {{ academic?.active_school_year || 'Aktif' }} </template>
                        </span>
                        <i v-if="canViewStudents" class="pi pi-arrow-right text-surface-400" aria-hidden="true"></i>
                    </div>
                </div>
            </div>

            <!-- Card 2: Pendidik & Tenaga Kependidikan -->
            <div class="col-span-12 sm:col-span-6 xl:col-span-3">
                <div class="card mb-0 h-full flex flex-col justify-between transition-all duration-200" :class="{ 'cursor-pointer hover:shadow-md': canViewEmployees }" @click="canViewEmployees ? navigateTo('/public/employees') : null">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <span class="block text-muted-color font-medium mb-2">Pendidik & Tendik</span>
                            <div class="text-surface-900 dark:text-surface-0 font-bold text-2xl">
                                <span v-if="loading"><i class="pi pi-spin pi-spinner text-base"></i></span>
                                <span v-else-if="employees">
                                    {{ ((employees.total_teachers || 0) + (employees.total_staff || 0)).toLocaleString('id-ID') }}
                                </span>
                                <span v-else class="text-surface-400 font-normal text-lg">Terbatas</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-center bg-orange-100 dark:bg-orange-400/10 rounded-border shrink-0" style="width: 2.75rem; height: 2.75rem">
                            <i class="pi pi-id-card text-orange-500 !text-xl" aria-hidden="true"></i>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-surface text-xs">
                        <span class="text-muted-color">
                            <template v-if="employees">
                                <strong class="text-primary font-semibold">{{ employees.total_teachers || 0 }}</strong> Guru · <strong class="text-primary font-semibold">{{ employees.total_staff || 0 }}</strong> Tendik
                            </template>
                            <template v-else> Profil PTK Terbatas </template>
                        </span>
                        <i v-if="canViewEmployees" class="pi pi-arrow-right text-surface-400" aria-hidden="true"></i>
                    </div>
                </div>
            </div>

            <!-- Card 3: Administrasi Surat & Perizinan -->
            <div class="col-span-12 sm:col-span-6 xl:col-span-3">
                <div
                    class="card mb-0 h-full flex flex-col justify-between transition-all duration-200"
                    :class="{ 'cursor-pointer hover:shadow-md': canViewLetters || canViewLeaves }"
                    @click="canViewLetters ? navigateTo('/public/letters/incoming') : canViewLeaves ? navigateTo('/public/leaves') : null"
                >
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <span class="block text-muted-color font-medium mb-2">Surat & Izin Tertunda</span>
                            <div class="text-surface-900 dark:text-surface-0 font-bold text-2xl">
                                <span v-if="loading"><i class="pi pi-spin pi-spinner text-base"></i></span>
                                <span v-else-if="letters || leaves">
                                    {{ ((letters?.incoming_unprocessed_count || 0) + (letters?.outgoing_pending_approval_count || 0) + (leaves?.pending_requests_count || leaves?.my_pending_requests_count || 0)).toLocaleString('id-ID') }}
                                </span>
                                <span v-else class="text-surface-400 font-normal text-lg">0</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-center bg-cyan-100 dark:bg-cyan-400/10 rounded-border shrink-0" style="width: 2.75rem; height: 2.75rem">
                            <i class="pi pi-envelope text-cyan-500 !text-xl" aria-hidden="true"></i>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-surface text-xs">
                        <span
                            class="text-muted-color truncate"
                            :title="letters ? `${letters.incoming_unprocessed_count || 0} masuk · ${letters.outgoing_pending_approval_count || 0} review · ${leaves?.pending_requests_count || 0} izin` : 'Operasional normal'"
                        >
                            <template v-if="letters">
                                {{ letters.incoming_unprocessed_count || 0 }} masuk · {{ letters.outgoing_pending_approval_count || 0 }} keluar
                                <template v-if="leaves?.pending_requests_count"> · {{ leaves.pending_requests_count }} izin</template>
                            </template>
                            <template v-else-if="leaves"> {{ leaves.my_pending_requests_count || 0 }} izin diajukan </template>
                            <template v-else> Operasional normal </template>
                        </span>
                        <i v-if="canViewLetters || canViewLeaves" class="pi pi-arrow-right text-surface-400" aria-hidden="true"></i>
                    </div>
                </div>
            </div>

            <!-- Card 4: Kepatuhan Mengajar / Sesi Mengajar Hari Ini -->
            <div class="col-span-12 sm:col-span-6 xl:col-span-3">
                <div
                    class="card mb-0 h-full flex flex-col justify-between transition-all duration-200"
                    :class="{ 'cursor-pointer hover:shadow-md': canViewTeaching }"
                    @click="teaching?.view_type === 'school_wide' ? navigateTo('/public/teaching/reports') : navigateTo('/public/teaching/journals')"
                >
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <span class="block text-muted-color font-medium mb-2">
                                <template v-if="teaching?.view_type === 'teacher_personal'">Jadwal Hari Ini</template>
                                <template v-else>Kepatuhan Mengajar (30H)</template>
                            </span>
                            <div class="text-surface-900 dark:text-surface-0 font-bold text-2xl">
                                <span v-if="loading"><i class="pi pi-spin pi-spinner text-base"></i></span>
                                <span v-else-if="teaching?.view_type === 'teacher_personal'"> {{ teaching.today_sessions_count || 0 }} Sesi </span>
                                <span v-else-if="teaching?.view_type === 'school_wide'"> {{ teaching.compliance_rate_percent || 0 }}% </span>
                                <span v-else class="text-surface-400 font-normal text-lg">
                                    {{ academic?.is_locked ? 'Terkunci' : 'Aktif' }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center justify-center bg-purple-100 dark:bg-purple-400/10 rounded-border shrink-0" style="width: 2.75rem; height: 2.75rem">
                            <i class="!text-xl text-purple-500" :class="teaching?.view_type === 'teacher_personal' ? 'pi pi-calendar' : 'pi pi-book'" aria-hidden="true"></i>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-surface text-xs">
                        <span class="text-muted-color truncate">
                            <template v-if="teaching?.view_type === 'teacher_personal'">
                                Kepatuhan: {{ teaching.compliance_rate_percent || 0 }}%
                                <span v-if="teaching.missing_within_grace" class="text-amber-500 font-semibold"> ({{ teaching.missing_within_grace }} perlu isi)</span>
                            </template>
                            <template v-else-if="teaching?.view_type === 'school_wide'"> {{ teaching.recorded_journals_30d || 0 }}/{{ teaching.expected_sessions_30d || 0 }} jurnal terisi </template>
                            <template v-else>
                                {{ academic?.active_semester || 'Semester I' }}
                            </template>
                        </span>
                        <i v-if="canViewTeaching" class="pi pi-arrow-right text-surface-400" aria-hidden="true"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
