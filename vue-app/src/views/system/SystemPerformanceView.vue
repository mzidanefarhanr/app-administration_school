<script setup>
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import apiLogin from '@/apiLogin';

const { t } = useI18n();

const perfData = ref(null);
const loading = ref(false);
const error = ref('');

const diskUsage = computed(() => perfData.value?.disk_usage ?? null);
const memoryRuntime = computed(() => perfData.value?.memory_and_runtime ?? null);
const dbTables = computed(() => perfData.value?.database_tables ?? null);
const activity = computed(() => perfData.value?.concurrency_and_activity ?? null);
const errorLogs = computed(() => perfData.value?.error_logs_summary ?? null);

const topTables = computed(() => dbTables.value?.top_tables ?? []);
const maxTableRows = computed(() => {
    const list = topTables.value;
    if (!list.length) return 1;
    const max = Math.max(...list.map((item) => item.rows_count || 0));
    return max > 0 ? max : 1;
});

const formatBytes = (bytes) => {
    const num = Number(bytes || 0);
    if (num === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.min(Math.floor(Math.log(num) / Math.log(k)), sizes.length - 1);
    return `${parseFloat((num / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
};

const formatNumber = (num) => {
    return new Intl.NumberFormat('id-ID').format(Number(num || 0));
};

const storageItems = computed(() => {
    const bd = diskUsage.value?.breakdown;
    if (!bd) return [];

    const items = [
        {
            key: 'database',
            label: t('system.performance.storage.database'),
            icon: 'pi pi-database',
            colorClass: 'bg-blue-500',
            badgeBg: 'bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400',
            bytes: bd.database_bytes || 0
        },
        {
            key: 'backups',
            label: t('system.performance.storage.backups'),
            icon: 'pi pi-shield',
            colorClass: 'bg-emerald-500',
            badgeBg: 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400',
            bytes: bd.backups_bytes || 0
        },
        {
            key: 'attachments',
            label: t('system.performance.storage.attachments'),
            icon: 'pi pi-paperclip',
            colorClass: 'bg-violet-500',
            badgeBg: 'bg-violet-50 dark:bg-violet-950/30 text-violet-600 dark:text-violet-400',
            bytes: bd.attachments_bytes || 0
        },
        {
            key: 'logs',
            label: t('system.performance.storage.logs'),
            icon: 'pi pi-file-edit',
            colorClass: 'bg-amber-500',
            badgeBg: 'bg-amber-50 dark:bg-amber-950/30 text-amber-600 dark:text-amber-400',
            bytes: bd.logs_bytes || 0
        }
    ];

    const totalAppBytes = items.reduce((sum, item) => sum + item.bytes, 0) || 1;
    return items.map((item) => ({
        ...item,
        sharePercent: Math.round((item.bytes / totalAppBytes) * 100)
    }));
});

const fetchPerformance = async () => {
    loading.value = true;
    error.value = '';
    try {
        const response = await apiLogin.get('/api/system/performance');
        perfData.value = response.data?.data ?? null;
    } catch (err) {
        error.value = err?.response?.data?.message || t('system.performance.error');
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    await fetchPerformance();
});
</script>

<template>
    <div class="space-y-6">
        <!-- Header Card -->
        <div class="card mb-0 border border-surface-200 dark:border-surface-700">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-start gap-3.5">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 bg-primary/10 text-primary">
                        <i class="pi pi-gauge text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-surface-900 dark:text-surface-0 m-0">
                            {{ t('system.performance.title') }}
                        </h1>
                        <p class="text-sm text-muted-color mt-1 mb-0">
                            {{ t('system.performance.subtitle') }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <Button
                        :label="t('system.performance.refreshBtn')"
                        icon="pi pi-refresh"
                        severity="secondary"
                        variant="outlined"
                        size="small"
                        :loading="loading"
                        @click="fetchPerformance"
                    />
                </div>
            </div>
        </div>

        <!-- Loading / Error States -->
        <div v-if="loading && !perfData" class="card flex items-center justify-center py-12 text-muted-color gap-3">
            <i class="pi pi-spin pi-spinner text-2xl"></i>
            <span>{{ t('system.performance.loading') }}</span>
        </div>

        <div v-else-if="error && !perfData" class="card py-8 text-center">
            <i class="pi pi-exclamation-triangle text-3xl text-red-500 mb-2"></i>
            <p class="text-sm text-red-500 mb-3">{{ error }}</p>
            <Button :label="t('system.performance.refreshBtn')" icon="pi pi-refresh" size="small" @click="fetchPerformance" />
        </div>

        <template v-else-if="perfData">
            <!-- 4 KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <!-- KPI 1: Disk Usage -->
                <div class="card mb-0 p-4 border border-surface-200 dark:border-surface-700 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-color">
                                {{ t('system.performance.kpi.diskUsage') }}
                            </span>
                            <div
                                class="w-8 h-8 rounded-lg flex items-center justify-center"
                                :class="(diskUsage?.used_percent || 0) > 85 ? 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' : 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400'"
                            >
                                <i class="pi pi-hdd"></i>
                            </div>
                        </div>
                        <div class="flex items-baseline gap-2 mb-2">
                            <span class="text-2xl font-bold text-surface-900 dark:text-surface-0">
                                {{ diskUsage?.used_percent ?? 0 }}%
                            </span>
                            <span class="text-xs text-muted-color">
                                ({{ formatBytes(diskUsage?.used_bytes) }})
                            </span>
                        </div>
                        <ProgressBar :value="diskUsage?.used_percent || 0" :showValue="false" style="height: 6px" class="mb-2" />
                    </div>
                    <div class="text-xs text-muted-color">
                        {{ t('system.performance.kpi.freeSpace', { free: formatBytes(diskUsage?.free_bytes), total: formatBytes(diskUsage?.total_bytes) }) }}
                    </div>
                </div>

                <!-- KPI 2: PHP Memory Usage -->
                <div class="card mb-0 p-4 border border-surface-200 dark:border-surface-700 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-color">
                                {{ t('system.performance.kpi.memoryUsage') }}
                            </span>
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-violet-100 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400">
                                <i class="pi pi-microchip"></i>
                            </div>
                        </div>
                        <div class="flex items-baseline gap-2 mb-2">
                            <span class="text-2xl font-bold text-surface-900 dark:text-surface-0">
                                {{ formatBytes(memoryRuntime?.memory_usage_bytes) }}
                            </span>
                            <span class="text-xs text-muted-color">
                                ({{ memoryRuntime?.memory_usage_percent ?? 0 }}%)
                            </span>
                        </div>
                        <ProgressBar :value="memoryRuntime?.memory_usage_percent || 0" :showValue="false" style="height: 6px" class="mb-2" />
                    </div>
                    <div class="text-xs text-muted-color">
                        {{ t('system.performance.kpi.peakMemory', { peak: formatBytes(memoryRuntime?.memory_peak_bytes), limit: memoryRuntime?.memory_limit || '-' }) }}
                    </div>
                </div>

                <!-- KPI 3: Database Load & Ping -->
                <div class="card mb-0 p-4 border border-surface-200 dark:border-surface-700 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-color">
                                {{ t('system.performance.kpi.dbLatency') }}
                            </span>
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                                <i class="pi pi-bolt"></i>
                            </div>
                        </div>
                        <div class="flex items-baseline gap-2 mb-1">
                            <span class="text-2xl font-bold text-surface-900 dark:text-surface-0">
                                {{ memoryRuntime?.db_ping_ms ?? 0 }} ms
                            </span>
                            <Tag :value="formatBytes(dbTables?.total_db_size_bytes)" severity="success" size="small" />
                        </div>
                    </div>
                    <div class="text-xs text-muted-color">
                        {{ t('system.performance.kpi.tablesSummary', { tables: formatNumber(dbTables?.total_tables_count), rows: formatNumber(dbTables?.total_rows_count) }) }}
                    </div>
                </div>

                <!-- KPI 4: Concurrency & Activity -->
                <div class="card mb-0 p-4 border border-surface-200 dark:border-surface-700 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-color">
                                {{ t('system.performance.kpi.activity') }}
                            </span>
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                                <i class="pi pi-users"></i>
                            </div>
                        </div>
                        <div class="flex items-baseline gap-2 mb-1">
                            <span class="text-xl font-bold text-surface-900 dark:text-surface-0">
                                {{ t('system.performance.kpi.onlineUsers', { online: formatNumber(activity?.online_users_count) }) }}
                            </span>
                        </div>
                    </div>
                    <div class="text-xs text-muted-color">
                        {{ t('system.performance.kpi.tokensAndLogs', { tokens: formatNumber(activity?.active_tokens_count), logs: formatNumber(activity?.activities_last_24h) }) }}
                    </div>
                </div>
            </div>

            <!-- Storage Breakdown & PHP Runtime Limits -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Storage Breakdown (7 cols) -->
                <div class="lg:col-span-7 card mb-0 border border-surface-200 dark:border-surface-700">
                    <div class="mb-4">
                        <h2 class="text-base font-bold text-surface-900 dark:text-surface-0 m-0">
                            {{ t('system.performance.storage.title') }}
                        </h2>
                        <p class="text-xs text-muted-color mt-0.5 mb-0">
                            {{ t('system.performance.storage.subtitle') }}
                        </p>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="item in storageItems"
                            :key="item.key"
                            class="p-3 rounded-lg border border-surface-200 dark:border-surface-700 bg-surface-50/40 dark:bg-surface-800/20"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" :class="item.badgeBg">
                                        <i :class="item.icon"></i>
                                    </div>
                                    <span class="text-sm font-medium text-surface-900 dark:text-surface-0">
                                        {{ item.label }}
                                    </span>
                                </div>
                                <div class="text-right">
                                    <span class="text-sm font-bold text-surface-900 dark:text-surface-0">
                                        {{ formatBytes(item.bytes) }}
                                    </span>
                                    <span class="text-xs text-muted-color ml-1.5">({{ item.sharePercent }}%)</span>
                                </div>
                            </div>
                            <div class="w-full h-1.5 bg-surface-200 dark:bg-surface-700 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-300" :class="item.colorClass" :style="{ width: `${Math.max(item.sharePercent, 2)}%` }"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PHP Runtime Limits (5 cols) -->
                <div class="lg:col-span-5 card mb-0 border border-surface-200 dark:border-surface-700 flex flex-col justify-between">
                    <div>
                        <h2 class="text-base font-bold text-surface-900 dark:text-surface-0 m-0 mb-4">
                            {{ t('system.performance.storage.runtimeLimitsTitle') }}
                        </h2>

                        <div class="space-y-3 text-xs">
                            <div class="flex items-center justify-between py-2 border-b border-surface-100 dark:border-surface-800">
                                <span class="text-muted-color">{{ t('system.performance.storage.memoryLimit') }}</span>
                                <span class="font-mono font-semibold text-surface-900 dark:text-surface-0">{{ memoryRuntime?.memory_limit || '-' }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-surface-100 dark:border-surface-800">
                                <span class="text-muted-color">{{ t('system.performance.storage.maxExecutionTime') }}</span>
                                <span class="font-mono font-semibold text-surface-900 dark:text-surface-0">{{ memoryRuntime?.max_execution_time ?? 0 }} s</span>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-surface-100 dark:border-surface-800">
                                <span class="text-muted-color">{{ t('system.performance.storage.uploadMaxFilesize') }}</span>
                                <span class="font-mono font-semibold text-surface-900 dark:text-surface-0">{{ memoryRuntime?.upload_max_filesize || '-' }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-surface-100 dark:border-surface-800">
                                <span class="text-muted-color">{{ t('system.performance.storage.postMaxSize') }}</span>
                                <span class="font-mono font-semibold text-surface-900 dark:text-surface-0">{{ memoryRuntime?.post_max_size || '-' }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2">
                                <span class="text-muted-color">{{ t('system.performance.storage.dbPing') }}</span>
                                <Tag :value="`${memoryRuntime?.db_ping_ms ?? 0} ms`" severity="info" size="small" />
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-surface-200 dark:border-surface-700 flex items-center justify-between text-xs text-muted-color">
                        <span>Diperbarui pada:</span>
                        <span class="font-mono">{{ perfData?.generated_at || '-' }} WIB</span>
                    </div>
                </div>
            </div>

            <!-- Top 15 Database Tables Load -->
            <div class="card mb-0 border border-surface-200 dark:border-surface-700">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                    <div>
                        <h2 class="text-base font-bold text-surface-900 dark:text-surface-0 m-0">
                            {{ t('system.performance.tables.title') }}
                        </h2>
                        <p class="text-xs text-muted-color mt-0.5 mb-0">
                            {{ t('system.performance.tables.subtitle') }}
                        </p>
                    </div>
                    <Tag :value="`${formatNumber(dbTables?.total_tables_count)} Tabel • ${formatBytes(dbTables?.total_db_size_bytes)}`" severity="secondary" />
                </div>

                <DataTable :value="topTables" size="small" stripedRows responsiveLayout="scroll" class="text-xs">
                    <Column field="table_name" :header="t('system.performance.tables.colTable')">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2 font-mono font-semibold text-surface-900 dark:text-surface-0">
                                <i class="pi pi-table text-primary text-xs"></i>
                                <span>{{ data.table_name }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column field="engine" :header="t('system.performance.tables.colEngine')">
                        <template #body="{ data }">
                            <Tag :value="data.engine || 'InnoDB'" severity="secondary" size="small" />
                        </template>
                    </Column>

                    <Column field="rows_count" :header="t('system.performance.tables.colRows')" style="min-width: 180px">
                        <template #body="{ data }">
                            <div class="flex items-center gap-3">
                                <span class="font-mono font-semibold w-16 text-right">{{ formatNumber(data.rows_count) }}</span>
                                <div class="flex-1 h-1.5 bg-surface-200 dark:bg-surface-700 rounded-full overflow-hidden">
                                    <div
                                        class="h-full bg-primary rounded-full"
                                        :style="{ width: `${Math.min(100, Math.round(((data.rows_count || 0) / maxTableRows) * 100))}%` }"
                                    ></div>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column field="data_bytes" :header="t('system.performance.tables.colDataSize')">
                        <template #body="{ data }">
                            <span class="font-mono">{{ formatBytes(data.data_bytes) }}</span>
                        </template>
                    </Column>

                    <Column field="index_bytes" :header="t('system.performance.tables.colIndexSize')">
                        <template #body="{ data }">
                            <span class="font-mono text-muted-color">{{ formatBytes(data.index_bytes) }}</span>
                        </template>
                    </Column>

                    <Column field="total_bytes" :header="t('system.performance.tables.colTotalSize')">
                        <template #body="{ data }">
                            <span class="font-mono font-semibold text-surface-900 dark:text-surface-0">{{ formatBytes(data.total_bytes) }}</span>
                        </template>
                    </Column>
                </DataTable>
            </div>

            <!-- Error & Warning Logs Summary -->
            <div class="card mb-0 border border-surface-200 dark:border-surface-700">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                    <div>
                        <h2 class="text-base font-bold text-surface-900 dark:text-surface-0 m-0">
                            {{ t('system.performance.logs.title') }}
                        </h2>
                        <p class="text-xs text-muted-color mt-0.5 mb-0">
                            {{ t('system.performance.logs.subtitle') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs text-muted-color">
                            {{ t('system.performance.logs.logFileSize', { size: formatBytes(errorLogs?.log_size_bytes) }) }}
                        </span>
                        <Tag :value="t('system.performance.logs.recentErrors', { count: errorLogs?.error_count_recent ?? 0 })" :severity="(errorLogs?.error_count_recent || 0) > 0 ? 'danger' : 'success'" size="small" />
                        <Tag :value="t('system.performance.logs.recentWarnings', { count: errorLogs?.warning_count_recent ?? 0 })" :severity="(errorLogs?.warning_count_recent || 0) > 0 ? 'warn' : 'secondary'" size="small" />
                    </div>
                </div>

                <div v-if="!errorLogs?.recent_entries?.length" class="py-8 text-center border border-dashed border-surface-200 dark:border-surface-700 rounded-xl">
                    <i class="pi pi-check-circle text-2xl text-emerald-500 mb-2"></i>
                    <p class="text-xs text-muted-color m-0">{{ t('system.performance.logs.empty') }}</p>
                </div>

                <DataTable v-else :value="errorLogs.recent_entries" size="small" stripedRows responsiveLayout="scroll" class="text-xs">
                    <Column field="timestamp" :header="t('system.performance.logs.colTime')" style="width: 170px">
                        <template #body="{ data }">
                            <span class="font-mono text-muted-color">{{ data.timestamp }}</span>
                        </template>
                    </Column>

                    <Column field="level" :header="t('system.performance.logs.colLevel')" style="width: 110px">
                        <template #body="{ data }">
                            <Tag :value="data.level" :severity="data.level === 'WARNING' ? 'warn' : 'danger'" size="small" />
                        </template>
                    </Column>

                    <Column field="environment" :header="t('system.performance.logs.colEnv')" style="width: 110px">
                        <template #body="{ data }">
                            <span class="font-mono">{{ data.environment }}</span>
                        </template>
                    </Column>

                    <Column field="message" :header="t('system.performance.logs.colMessage')">
                        <template #body="{ data }">
                            <span class="font-mono text-surface-800 dark:text-surface-200 break-all">{{ data.message }}</span>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </template>
    </div>
</template>
