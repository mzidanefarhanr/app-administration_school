<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import apiLogin from '@/apiLogin';
import { authStore } from '@/stores/authStore';

const { t, locale } = useI18n();
const router = useRouter();
const store = authStore();

const dataQuality = ref(null);
const loading = ref(false);
const error = ref('');

const canViewDataQuality = computed(() => store.can('data-quality.view') || store.can('students.view') || store.can('employees.view'));

const studentQuality = computed(() => dataQuality.value?.students ?? null);
const employeeQuality = computed(() => dataQuality.value?.employees ?? null);

const hasAnyData = computed(() => studentQuality.value !== null || employeeQuality.value !== null);

const formatIncomplete = (count) => {
    if (locale.value === 'en') {
        return `${count} incomplete profile${count === 1 ? '' : 's'}`;
    }
    return `${count} profil belum lengkap`;
};

const formatIssues = (count) => {
    if (locale.value === 'en') {
        return `Review ${count} issue${count === 1 ? '' : 's'}`;
    }
    return `Tinjau ${count} kendala`;
};

const load = async () => {
    if (!canViewDataQuality.value) return;

    loading.value = true;
    error.value = '';
    try {
        const response = await apiLogin.get('/api/dashboard/overview');
        dataQuality.value = response.data?.data?.data_quality ?? null;
    } catch {
        error.value = t('dashboard.dataQuality.error');
    } finally {
        loading.value = false;
    }
};

const openDataQuality = (tab) => {
    router.push({ path: '/public/data-quality', query: tab ? { tab } : {} });
};

onMounted(async () => {
    await load();
});

defineExpose({ load });
</script>

<template>
    <div v-if="canViewDataQuality" class="card mb-0 h-full flex flex-col justify-between">
        <div class="flex items-center justify-between mb-4">
            <div>
                <div class="font-semibold text-xl">{{ t('dashboard.dataQuality.title') }}</div>
                <div class="text-xs text-muted-color">{{ t('dashboard.dataQuality.subtitle') }}</div>
            </div>
            <Button :label="t('dashboard.dataQuality.openAudit')" icon="pi pi-external-link" size="small" variant="text" severity="secondary" @click="openDataQuality()" />
        </div>

        <div v-if="loading" class="flex items-center gap-3 py-6 text-surface-500 justify-center">
            <i class="pi pi-spin pi-spinner" aria-hidden="true"></i>
            <span class="text-sm">{{ t('dashboard.dataQuality.analyzing') }}</span>
        </div>

        <div v-else-if="error" class="py-4 text-center">
            <span class="text-xs text-red-500">{{ error }}</span>
        </div>

        <div v-else-if="!hasAnyData" class="py-6 text-center text-surface-400 text-sm">{{ t('dashboard.dataQuality.empty') }}</div>

        <div v-else class="space-y-4">
            <!-- Student Completeness -->
            <div v-if="studentQuality" class="p-3 rounded-lg border border-surface bg-surface-50/50 dark:bg-surface-800/20">
                <div class="flex justify-between items-center mb-1">
                    <span class="font-medium text-sm text-surface-900 dark:text-surface-0 flex items-center gap-2">
                        <i class="pi pi-users text-blue-500 text-xs" aria-hidden="true"></i>
                        {{ t('dashboard.dataQuality.studentCompleteness') }}
                    </span>
                    <span class="font-bold text-sm text-primary">{{ studentQuality.complete_percent }}%</span>
                </div>
                <div class="w-full bg-surface-200 dark:bg-surface-700 rounded-full h-2 overflow-hidden my-2">
                    <div
                        class="h-full rounded-full transition-all duration-500"
                        :class="studentQuality.complete_percent >= 90 ? 'bg-green-500' : studentQuality.complete_percent >= 70 ? 'bg-amber-500' : 'bg-red-500'"
                        :style="{ width: `${Math.min(100, Math.max(0, studentQuality.complete_percent))}%` }"
                    ></div>
                </div>
                <div class="flex justify-between items-center text-xs text-surface-500">
                    <span>{{ formatIncomplete(studentQuality.incomplete_records) }}</span>
                    <button type="button" class="text-primary hover:underline font-medium cursor-pointer" @click="openDataQuality('students')">{{ formatIssues(studentQuality.unresolved_issues) }}</button>
                </div>
            </div>

            <!-- Employee Completeness -->
            <div v-if="employeeQuality" class="p-3 rounded-lg border border-surface bg-surface-50/50 dark:bg-surface-800/20">
                <div class="flex justify-between items-center mb-1">
                    <span class="font-medium text-sm text-surface-900 dark:text-surface-0 flex items-center gap-2">
                        <i class="pi pi-id-card text-orange-500 text-xs" aria-hidden="true"></i>
                        {{ t('dashboard.dataQuality.employeeCompleteness') }}
                    </span>
                    <span class="font-bold text-sm text-primary">{{ employeeQuality.complete_percent }}%</span>
                </div>
                <div class="w-full bg-surface-200 dark:bg-surface-700 rounded-full h-2 overflow-hidden my-2">
                    <div
                        class="h-full rounded-full transition-all duration-500"
                        :class="employeeQuality.complete_percent >= 90 ? 'bg-green-500' : employeeQuality.complete_percent >= 70 ? 'bg-amber-500' : 'bg-red-500'"
                        :style="{ width: `${Math.min(100, Math.max(0, employeeQuality.complete_percent))}%` }"
                    ></div>
                </div>
                <div class="flex justify-between items-center text-xs text-surface-500">
                    <span>{{ formatIncomplete(employeeQuality.incomplete_records) }}</span>
                    <button type="button" class="text-primary hover:underline font-medium cursor-pointer" @click="openDataQuality('employees')">{{ formatIssues(employeeQuality.unresolved_issues) }}</button>
                </div>
            </div>
        </div>
    </div>
</template>
