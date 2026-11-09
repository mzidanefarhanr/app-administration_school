<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import apiLogin from '@/apiLogin';
import { authStore } from '@/stores/authStore';

const { t } = useI18n();
const router = useRouter();
const store = authStore();

const overview = ref(null);
const loading = ref(false);
const error = ref('');

const academic = computed(() => overview.value?.academic ?? null);
const system = computed(() => overview.value?.system ?? null);
const meta = computed(() => overview.value?.meta ?? null);

const canManageBackup = computed(() => store.can('backup.manage') || store.can('users.manage'));

const load = async () => {
    loading.value = true;
    error.value = '';
    try {
        const response = await apiLogin.get('/api/dashboard/overview');
        overview.value = response.data?.data ?? null;
    } catch {
        error.value = t('dashboard.system.error');
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
    await load();
});

defineExpose({ load });
</script>

<template>
    <div class="card mb-0 h-full flex flex-col justify-between">
        <div class="flex items-center justify-between mb-4">
            <span class="font-semibold text-xl">{{ t('dashboard.system.title') }}</span>
            <Button icon="pi pi-refresh" size="small" variant="text" severity="secondary" :loading="loading" :aria-label="t('dashboard.system.refreshAria')" @click="load" />
        </div>

        <div v-if="loading" class="flex items-center justify-center py-8 text-surface-500 gap-3">
            <i class="pi pi-spin pi-spinner text-lg" aria-hidden="true"></i>
            <span class="text-sm">{{ t('dashboard.system.loading') }}</span>
        </div>

        <div v-else-if="error" class="py-4 text-center text-xs text-red-500">
            {{ error }}
        </div>

        <div v-else class="space-y-4">
            <!-- Period & Server Info Box -->
            <div class="grid grid-cols-2 gap-3 p-3 rounded-lg border border-surface bg-surface-50/50 dark:bg-surface-800/20 text-xs">
                <div>
                    <span class="text-muted-color block mb-1">{{ t('dashboard.system.activeSchoolYear') }}</span>
                    <div class="font-semibold text-surface-900 dark:text-surface-0 flex items-center gap-1.5 flex-wrap">
                        <span>{{ academic?.active_school_year || t('dashboard.system.notSet') }}</span>
                        <Tag :value="academic?.is_locked ? t('dashboard.system.locked') : t('dashboard.system.active')" :severity="academic?.is_locked ? 'danger' : 'success'" class="!text-[10px] !py-0 !px-1.5" />
                    </div>
                </div>
                <div>
                    <span class="text-muted-color block mb-1">{{ t('dashboard.system.currentSemester') }}</span>
                    <div class="font-semibold text-surface-900 dark:text-surface-0">
                        {{ academic?.active_semester || t('dashboard.system.notSet') }}
                    </div>
                </div>
                <div class="col-span-2 pt-2 border-t border-surface/50 flex items-center justify-between">
                    <span class="text-muted-color">{{ t('dashboard.system.serverTimeWib') }}</span>
                    <span class="font-mono text-surface-700 dark:text-surface-200">
                        {{ meta?.server_time_wib || '-' }}
                    </span>
                </div>
            </div>

            <!-- Backup Health (Admin / Backup Managers only) -->
            <div
                v-if="canManageBackup && system"
                class="p-3 rounded-lg border border-surface flex items-center justify-between text-xs cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800/40 transition-colors"
                @click="navigateTo('/system/status')"
            >
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0" :class="system.backup_status === 'healthy' ? 'bg-green-100 dark:bg-green-400/10 text-green-600' : 'bg-amber-100 dark:bg-amber-400/10 text-amber-600'">
                        <i class="pi pi-database" aria-hidden="true"></i>
                    </div>
                    <div>
                        <div class="font-medium text-surface-900 dark:text-surface-0 flex items-center gap-1.5">
                            <span>{{ t('dashboard.system.backupStatus') }}</span>
                            <i class="pi pi-arrow-up-right text-[10px] text-muted-color"></i>
                        </div>
                        <div class="text-muted-color">{{ system.last_backup_at ? t('dashboard.system.lastBackup', { date: system.last_backup_at }) : t('dashboard.system.noBackupYet') }}</div>
                    </div>
                </div>
                <Tag :value="system.backup_status === 'healthy' ? t('dashboard.system.healthy') : t('dashboard.system.checkNeeded')" :severity="system.backup_status === 'healthy' ? 'success' : 'warn'" size="small" />
            </div>

            <!-- Quick Action Shortcuts -->
            <div>
                <span class="block text-xs font-semibold uppercase tracking-wider text-muted-color mb-2">{{ t('dashboard.system.shortcutsTitle') }}</span>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    <Button
                        v-if="store.can('teaching.view')"
                        :label="t('dashboard.system.shortcuts.journals')"
                        icon="pi pi-book"
                        size="small"
                        severity="secondary"
                        variant="outlined"
                        class="!justify-start !text-xs !py-2"
                        @click="navigateTo('/public/teaching/journals')"
                    />
                    <Button
                        v-if="store.can('teaching.view')"
                        :label="t('dashboard.system.shortcuts.reports')"
                        icon="pi pi-chart-line"
                        size="small"
                        severity="secondary"
                        variant="outlined"
                        class="!justify-start !text-xs !py-2"
                        @click="navigateTo('/public/teaching/reports')"
                    />
                    <Button
                        v-if="store.can('letters.view')"
                        :label="t('dashboard.system.shortcuts.incomingLetters')"
                        icon="pi pi-inbox"
                        size="small"
                        severity="secondary"
                        variant="outlined"
                        class="!justify-start !text-xs !py-2"
                        @click="navigateTo('/public/letters/incoming')"
                    />
                    <Button
                        v-if="store.can('letters.view')"
                        :label="t('dashboard.system.shortcuts.outgoingLetters')"
                        icon="pi pi-send"
                        size="small"
                        severity="secondary"
                        variant="outlined"
                        class="!justify-start !text-xs !py-2"
                        @click="navigateTo('/public/letters/outgoing')"
                    />
                    <Button
                        v-if="store.can('leaves.view') || store.can('leaves.request')"
                        :label="t('dashboard.system.shortcuts.leaves')"
                        icon="pi pi-calendar-times"
                        size="small"
                        severity="secondary"
                        variant="outlined"
                        class="!justify-start !text-xs !py-2"
                        @click="navigateTo('/public/leaves')"
                    />
                    <Button
                        v-if="store.can('students.view')"
                        :label="t('dashboard.system.shortcuts.studentDirectory')"
                        icon="pi pi-users"
                        size="small"
                        severity="secondary"
                        variant="outlined"
                        class="!justify-start !text-xs !py-2"
                        @click="navigateTo('/public/students/directory')"
                    />
                    <Button
                        v-if="store.can('employees.view')"
                        :label="t('dashboard.system.shortcuts.employeeDirectory')"
                        icon="pi pi-id-card"
                        size="small"
                        severity="secondary"
                        variant="outlined"
                        class="!justify-start !text-xs !py-2"
                        @click="navigateTo('/public/employees')"
                    />
                    <Button
                        v-if="store.can('archives.view')"
                        :label="t('dashboard.system.shortcuts.documentArchives')"
                        icon="pi pi-folder"
                        size="small"
                        severity="secondary"
                        variant="outlined"
                        class="!justify-start !text-xs !py-2"
                        @click="navigateTo('/public/archives')"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
