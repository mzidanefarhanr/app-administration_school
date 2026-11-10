<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import apiLogin from '@/apiLogin';

const { t } = useI18n();
const router = useRouter();

const onboarding = ref(null);
const loading = ref(false);
const error = ref('');

const STORAGE_COLLAPSED_KEY = 'sekolario_onboarding_collapsed';
const STORAGE_DISMISSED_KEY = 'sekolario_onboarding_dismissed';

const isCollapsed = ref(localStorage.getItem(STORAGE_COLLAPSED_KEY) === 'true');
const isDismissed = ref(localStorage.getItem(STORAGE_DISMISSED_KEY) === 'true');

const toggleCollapse = () => {
    isCollapsed.value = !isCollapsed.value;
    localStorage.setItem(STORAGE_COLLAPSED_KEY, isCollapsed.value.toString());
};

const dismissGuide = () => {
    isDismissed.value = true;
    localStorage.setItem(STORAGE_DISMISSED_KEY, 'true');
};

const showGuide = () => {
    isDismissed.value = false;
    isCollapsed.value = false;
    localStorage.removeItem(STORAGE_DISMISSED_KEY);
    localStorage.removeItem(STORAGE_COLLAPSED_KEY);
};

const load = async () => {
    loading.value = true;
    error.value = '';
    try {
        const response = await apiLogin.get('/api/dashboard/onboarding');
        onboarding.value = response.data?.data ?? null;
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

const getStepStatusSeverity = (status) => {
    switch (status) {
        case 'completed':
            return 'success';
        case 'in_progress':
            return 'warn';
        default:
            return 'secondary';
    }
};

const getStepStatusText = (status) => {
    switch (status) {
        case 'completed':
            return t('dashboard.onboarding.stepCompleted');
        case 'in_progress':
            return t('dashboard.onboarding.stepInProgress');
        default:
            return t('dashboard.onboarding.stepPending');
    }
};

const progressColor = computed(() => {
    const p = onboarding.value?.overall_percent ?? 0;
    if (p >= 100) return 'text-green-600 dark:text-green-400';
    if (p >= 50) return 'text-primary';
    return 'text-amber-500';
});

onMounted(async () => {
    await load();
});

defineExpose({ load });
</script>

<template>
    <!-- Mini button to restore guide if dismissed -->
    <div v-if="isDismissed" class="col-span-12 flex justify-end">
        <Button
            icon="pi pi-compass"
            :label="t('dashboard.onboarding.showGuide')"
            size="small"
            variant="outlined"
            severity="secondary"
            class="shadow-sm bg-surface-0 dark:bg-surface-900"
            @click="showGuide"
        />
    </div>

    <!-- Main Onboarding Card -->
    <div v-else-if="onboarding" class="col-span-12">
        <div class="card p-5 border border-primary/20 dark:border-primary/30 shadow-sm transition-all duration-300 bg-gradient-to-r from-surface-0 to-surface-50 dark:from-surface-900 dark:to-surface-850">
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-surface-200 dark:border-surface-700">
                <div class="flex items-start sm:items-center gap-3">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 bg-primary/10 text-primary">
                        <i class="pi pi-compass text-2xl" aria-hidden="true"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-bold text-lg text-surface-900 dark:text-surface-0">
                                {{ t('dashboard.onboarding.title') }}
                            </span>
                            <Tag
                                :value="`${onboarding.overall_percent}%`"
                                :severity="onboarding.is_all_completed ? 'success' : (onboarding.overall_percent >= 50 ? 'info' : 'warn')"
                                rounded
                                class="font-bold px-2 py-0.5 text-xs"
                            />
                        </div>
                        <p class="text-xs text-surface-500 dark:text-surface-400 mt-0.5">
                            {{ t('dashboard.onboarding.subtitle') }}
                        </p>
                    </div>
                </div>

                <!-- Controls -->
                <div class="flex items-center gap-1.5 self-end sm:self-center">
                    <Button
                        icon="pi pi-refresh"
                        size="small"
                        variant="text"
                        severity="secondary"
                        :loading="loading"
                        :tooltip="t('dashboard.onboarding.refresh')"
                        @click="load"
                    />
                    <Button
                        :icon="isCollapsed ? 'pi pi-chevron-down' : 'pi pi-chevron-up'"
                        size="small"
                        variant="text"
                        severity="secondary"
                        :tooltip="isCollapsed ? t('dashboard.onboarding.expand') : t('dashboard.onboarding.collapse')"
                        @click="toggleCollapse"
                    />
                    <Button
                        icon="pi pi-times"
                        size="small"
                        variant="text"
                        severity="secondary"
                        :tooltip="t('dashboard.onboarding.hide')"
                        @click="dismissGuide"
                    />
                </div>
            </div>

            <!-- Progress Summary Bar -->
            <div class="mt-3">
                <div class="flex justify-between items-center text-xs font-medium mb-1.5">
                    <span class="text-surface-600 dark:text-surface-300">
                        {{ t('dashboard.onboarding.progress', { percent: onboarding.overall_percent, completed: onboarding.completed_steps, total: onboarding.total_steps }) }}
                    </span>
                    <span :class="progressColor" class="font-semibold">
                        {{ onboarding.completed_steps }} / {{ onboarding.total_steps }} Selesai
                    </span>
                </div>
                <ProgressBar
                    :value="onboarding.overall_percent"
                    :showValue="false"
                    class="h-2 rounded-full overflow-hidden bg-surface-200 dark:bg-surface-700"
                />
            </div>

            <!-- Collapsible Body -->
            <div v-show="!isCollapsed" class="mt-4 space-y-4">
                <!-- Recommended Next Step Banner -->
                <div
                    v-if="!onboarding.is_all_completed && onboarding.next_step"
                    class="p-3.5 rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50/70 dark:bg-amber-950/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300">
                            <i class="pi pi-arrow-right text-sm" aria-hidden="true"></i>
                        </div>
                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-amber-800 dark:text-amber-400 block">
                                {{ t('dashboard.onboarding.nextStepTitle') }}
                            </span>
                            <span class="font-medium text-sm text-surface-900 dark:text-surface-100">
                                {{ onboarding.next_step.title }}
                            </span>
                            <span class="text-xs text-surface-500 dark:text-surface-400 block sm:inline sm:ml-2">
                                — {{ onboarding.next_step.description }}
                            </span>
                        </div>
                    </div>
                    <Button
                        :label="onboarding.next_step.action_label"
                        icon="pi pi-external-link"
                        size="small"
                        severity="warn"
                        class="shrink-0 self-start sm:self-auto font-medium"
                        @click="navigateTo(onboarding.next_step.action_route)"
                    />
                </div>

                <!-- All Completed Celebration Banner -->
                <div
                    v-else-if="onboarding.is_all_completed"
                    class="p-4 rounded-xl border border-green-200 dark:border-green-900/50 bg-green-50/70 dark:bg-green-950/20 flex items-center gap-3.5"
                >
                    <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 bg-green-100 dark:bg-green-900/50 text-green-600 dark:text-green-300">
                        <i class="pi pi-check-circle text-xl" aria-hidden="true"></i>
                    </div>
                    <div>
                        <div class="font-bold text-sm text-green-900 dark:text-green-200">
                            {{ t('dashboard.onboarding.allCompleted') }}
                        </div>
                        <div class="text-xs text-green-700 dark:text-green-400 mt-0.5">
                            {{ t('dashboard.onboarding.allCompletedDesc') }}
                        </div>
                    </div>
                </div>

                <!-- 6 Steps Grid / Sequential Timeline -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 pt-1">
                    <div
                        v-for="step in onboarding.steps"
                        :key="step.id"
                        class="relative flex flex-col justify-between p-3.5 rounded-xl border transition-all duration-200 hover:shadow-md cursor-pointer group"
                        :class="[
                            step.status === 'completed'
                                ? 'bg-surface-0 dark:bg-surface-800/80 border-green-200 dark:border-green-900/40 hover:border-green-300'
                                : (step.status === 'in_progress'
                                    ? 'bg-amber-50/30 dark:bg-amber-950/10 border-amber-300 dark:border-amber-700/60 ring-2 ring-amber-300/30'
                                    : 'bg-surface-0/60 dark:bg-surface-800/40 border-surface-200 dark:border-surface-700/60 opacity-85 hover:opacity-100')
                        ]"
                        @click="navigateTo(step.action_route)"
                    >
                        <div>
                            <!-- Step Header: Number / Status Icon & Badge -->
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0 transition-transform group-hover:scale-105"
                                        :class="[
                                            step.status === 'completed'
                                                ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300'
                                                : (step.status === 'in_progress'
                                                    ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300'
                                                    : 'bg-surface-200 dark:bg-surface-700 text-surface-600 dark:text-surface-300')
                                        ]"
                                    >
                                        <i v-if="step.status === 'completed'" class="pi pi-check text-xs font-bold" aria-hidden="true"></i>
                                        <span v-else>{{ step.order }}</span>
                                    </div>
                                    <span class="text-[11px] font-semibold uppercase tracking-wider text-surface-400">
                                        {{ t('dashboard.onboarding.stepLabel', { order: step.order }) }}
                                    </span>
                                </div>

                                <Tag
                                    :value="getStepStatusText(step.status)"
                                    :severity="getStepStatusSeverity(step.status)"
                                    class="text-[10px] px-1.5 py-0.5"
                                />
                            </div>

                            <!-- Step Title -->
                            <div class="font-semibold text-sm text-surface-900 dark:text-surface-100 line-clamp-1 mb-1 group-hover:text-primary transition-colors">
                                {{ step.title }}
                            </div>

                            <!-- Step Description -->
                            <p class="text-xs text-surface-500 dark:text-surface-400 line-clamp-2 leading-relaxed mb-3">
                                {{ step.description }}
                            </p>
                        </div>

                        <!-- Step Footer: Badge & Action Button -->
                        <div class="pt-2 border-t border-surface-100 dark:border-surface-700/50 mt-auto">
                            <div class="flex items-center justify-between gap-1 mb-2">
                                <span class="text-[11px] font-medium text-surface-600 dark:text-surface-300 truncate">
                                    {{ step.badge }}
                                </span>
                            </div>
                            <Button
                                :label="step.action_label"
                                icon="pi pi-arrow-right"
                                iconPos="right"
                                size="small"
                                :variant="step.status === 'completed' ? 'outlined' : (step.status === 'in_progress' ? 'solid' : 'outlined')"
                                :severity="step.status === 'completed' ? 'secondary' : (step.status === 'in_progress' ? 'warn' : 'primary')"
                                class="w-full text-xs font-medium py-1 px-2.5 justify-center"
                                @click.stop="navigateTo(step.action_route)"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
