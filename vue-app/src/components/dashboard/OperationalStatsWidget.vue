<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import apiLogin from '@/apiLogin';
import { authStore } from '@/stores/authStore';
import { formatNumber } from '@/utils/formatters';

const { t } = useI18n();
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
        error.value = t('dashboard.stats.error');
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
            <Button :label="t('dashboard.stats.reload')" icon="pi pi-refresh" size="small" severity="danger" variant="outlined" :loading="loading" @click="load" />
        </div>

        <div class="grid grid-cols-12 gap-6">
            <!-- Card 1: Siswa & Rombel Aktif -->
            <div class="col-span-12 sm:col-span-6 xl:col-span-3">
                <div class="card mb-0 h-full flex flex-col justify-between transition-all duration-200" :class="{ 'cursor-pointer hover:shadow-md': canViewStudents }" @click="canViewStudents ? navigateTo('/public/students/directory') : null">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <span class="block text-muted-color font-medium mb-2">{{ t('dashboard.stats.activeStudents') }}</span>
                            <div class="text-surface-900 dark:text-surface-0 font-bold text-2xl">
                                <span v-if="loading"><i class="pi pi-spin pi-spinner text-base"></i></span>
                                <span v-else-if="academic?.total_active_students !== null && academic?.total_active_students !== undefined">
                                    {{ formatNumber(academic.total_active_students) }}
                                </span>
                                <span v-else class="text-surface-400 font-normal text-lg">{{ t('dashboard.stats.restricted') }}</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-center bg-blue-100 dark:bg-blue-400/10 rounded-border shrink-0" style="width: 2.75rem; height: 2.75rem">
                            <i class="pi pi-users text-blue-500 !text-xl" aria-hidden="true"></i>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-surface text-xs">
                        <span class="text-muted-color">
                            <template v-if="academic?.total_active_rombels !== null && academic?.total_active_rombels !== undefined">
                                <strong class="text-primary font-semibold">{{ academic.total_active_rombels }}</strong> {{ t('dashboard.stats.activeRombelsLabel') }}
                            </template>
                            <template v-else>
                                {{ t('dashboard.stats.period', { period: academic?.active_school_year || t('dashboard.stats.active') }) }}
                            </template>
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
                            <span class="block text-muted-color font-medium mb-2">{{ t('dashboard.stats.educatorsAndStaff') }}</span>
                            <div class="text-surface-900 dark:text-surface-0 font-bold text-2xl">
                                <span v-if="loading"><i class="pi pi-spin pi-spinner text-base"></i></span>
                                <span v-else-if="employees">
                                    {{ formatNumber((employees.total_teachers || 0) + (employees.total_staff || 0)) }}
                                </span>
                                <span v-else class="text-surface-400 font-normal text-lg">{{ t('dashboard.stats.restricted') }}</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-center bg-orange-100 dark:bg-orange-400/10 rounded-border shrink-0" style="width: 2.75rem; height: 2.75rem">
                            <i class="pi pi-id-card text-orange-500 !text-xl" aria-hidden="true"></i>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-surface text-xs">
                        <span class="text-muted-color">
                            <template v-if="employees">
                                <strong class="text-primary font-semibold">{{ employees.total_teachers || 0 }}</strong> {{ t('dashboard.stats.teachersLabel') }} · <strong class="text-primary font-semibold">{{ employees.total_staff || 0 }}</strong>
                                {{ t('dashboard.stats.staffLabel') }}
                            </template>
                            <template v-else> {{ t('dashboard.stats.ptkProfileRestricted') }} </template>
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
                            <span class="block text-muted-color font-medium mb-2">{{ t('dashboard.stats.pendingLettersAndLeaves') }}</span>
                            <div class="text-surface-900 dark:text-surface-0 font-bold text-2xl">
                                <span v-if="loading"><i class="pi pi-spin pi-spinner text-base"></i></span>
                                <span v-else-if="letters || leaves">
                                    {{ formatNumber((letters?.incoming_unprocessed_count || 0) + (letters?.outgoing_pending_approval_count || 0) + (leaves?.pending_requests_count || leaves?.my_pending_requests_count || 0)) }}
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
                            :title="
                                letters
                                    ? `${letters.incoming_unprocessed_count || 0} ${t('dashboard.stats.incomingLabel')} · ${letters.outgoing_pending_approval_count || 0} ${t('dashboard.stats.outgoingReviewLabel')} · ${leaves?.pending_requests_count || 0} ${t('dashboard.stats.leavesLabel')}`
                                    : t('dashboard.stats.normalOperation')
                            "
                        >
                            <template v-if="letters">
                                {{ letters.incoming_unprocessed_count || 0 }} {{ t('dashboard.stats.incomingLabel') }} · {{ letters.outgoing_pending_approval_count || 0 }} {{ t('dashboard.stats.outgoingLabel') }}
                                <template v-if="leaves?.pending_requests_count"> · {{ leaves.pending_requests_count }} {{ t('dashboard.stats.leavesLabel') }}</template>
                            </template>
                            <template v-else-if="leaves"> {{ leaves.my_pending_requests_count || 0 }} {{ t('dashboard.stats.leavesSubmittedLabel') }} </template>
                            <template v-else> {{ t('dashboard.stats.normalOperation') }} </template>
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
                                <template v-if="teaching?.view_type === 'teacher_personal'">{{ t('dashboard.stats.todaySchedule') }}</template>
                                <template v-else>{{ t('dashboard.stats.teachingCompliance30d') }}</template>
                            </span>
                            <div class="text-surface-900 dark:text-surface-0 font-bold text-2xl">
                                <span v-if="loading"><i class="pi pi-spin pi-spinner text-base"></i></span>
                                <span v-else-if="teaching?.view_type === 'teacher_personal'"> {{ teaching.today_sessions_count || 0 }} {{ t('dashboard.stats.sessionsLabel') }} </span>
                                <span v-else-if="teaching?.view_type === 'school_wide'"> {{ teaching.compliance_rate_percent || 0 }}% </span>
                                <span v-else class="text-surface-400 font-normal text-lg">
                                    {{ academic?.is_locked ? t('dashboard.stats.locked') : t('dashboard.stats.active') }}
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
                                {{ t('dashboard.stats.complianceRate', { percent: teaching.compliance_rate_percent || 0 }) }}
                                <span v-if="teaching.missing_within_grace" class="text-amber-500 font-semibold"> {{ t('dashboard.stats.needEntry', { count: teaching.missing_within_grace }) }}</span>
                            </template>
                            <template v-else-if="teaching?.view_type === 'school_wide'"> {{ t('dashboard.stats.journalsFilled', { recorded: teaching.recorded_journals_30d || 0, expected: teaching.expected_sessions_30d || 0 }) }} </template>
                            <template v-else>
                                {{ academic?.active_semester || t('dashboard.stats.defaultSemester') }}
                            </template>
                        </span>
                        <i v-if="canViewTeaching" class="pi pi-arrow-right text-surface-400" aria-hidden="true"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
