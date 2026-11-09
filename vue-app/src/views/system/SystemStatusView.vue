<script setup>
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import apiLogin from '@/apiLogin';
import { formatDateTime } from '@/utils/formatters';

const { t } = useI18n();
const confirm = useConfirm();
const toast = useToast();

const statusData = ref(null);
const loading = ref(false);
const error = ref('');

// Dialog Buat Backup Baru
const createDialogVisible = ref(false);
const passphrase = ref('');
const showPassphrase = ref(false);
const creatingBackup = ref(false);
const pruningBackups = ref(false);
const downloadingFile = ref('');
const deletingFile = ref('');

const backup = computed(() => statusData.value?.backup ?? null);
const health = computed(() => backup.value?.health ?? null);
const archives = computed(() => backup.value?.archives ?? []);
const jsonSnapshots = computed(() => backup.value?.json_snapshots ?? []);
const scheduleConfig = computed(() => backup.value?.schedule_config ?? null);
const services = computed(() => statusData.value?.services ?? null);
const dbService = computed(() => services.value?.database ?? null);
const phpRuntime = computed(() => services.value?.php_runtime ?? null);
const storageDirs = computed(() => services.value?.storage_directories ?? []);
const binaries = computed(() => services.value?.binaries ?? null);

const hasConfiguredKey = computed(() => !!scheduleConfig.value?.has_configured_encryption_key);

const formatBytes = (bytes) => {
    const val = Number(bytes || 0);
    if (val === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(val) / Math.log(1024));
    return `${(val / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 2)} ${units[i]}`;
};

const load = async () => {
    loading.value = true;
    error.value = '';
    try {
        const response = await apiLogin.get('/api/system/status');
        statusData.value = response.data?.data ?? null;
    } catch {
        error.value = t('system.status.error');
    } finally {
        loading.value = false;
    }
};

const openCreateDialog = () => {
    passphrase.value = '';
    showPassphrase.value = true;
    createDialogVisible.value = true;
};

const generateSecurePassphrase = () => {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*';
    let randomPart = '';
    for (let i = 0; i < 12; i++) {
        randomPart += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    passphrase.value = `Sekolario-${randomPart}`;
    showPassphrase.value = true;
};

const copyToClipboard = async (text, msgKey = 'system.status.dialog.keyCopied') => {
    if (!text) return;
    try {
        await navigator.clipboard.writeText(text);
        toast.add({
            severity: 'info',
            summary: 'Info',
            detail: t(msgKey),
            life: 2500
        });
    } catch {
        // Ignore clipboard errors
    }
};

const runBackupNow = async () => {
    creatingBackup.value = true;
    try {
        const payload = {};
        if (passphrase.value.trim() !== '') {
            payload.passphrase = passphrase.value.trim();
        }

        const response = await apiLogin.post('/api/backups/run', payload);
        const createdFilename = response.data?.data?.filename ?? '';

        toast.add({
            severity: 'success',
            summary: 'Sukses',
            detail: t('system.status.dialog.successCreate', { filename: createdFilename }),
            life: 4000
        });

        createDialogVisible.value = false;
        await load();
    } catch (err) {
        const msg = err.response?.data?.errors?.passphrase?.[0]
            || err.response?.data?.message
            || 'Gagal membuat arsip cadangan.';
        toast.add({
            severity: 'error',
            summary: 'Gagal',
            detail: msg,
            life: 5000
        });
    } finally {
        creatingBackup.value = false;
    }
};

const runPrune = async () => {
    pruningBackups.value = true;
    try {
        const response = await apiLogin.post('/api/backups/prune');
        const data = response.data?.data ?? {};
        toast.add({
            severity: 'info',
            summary: 'Rotasi Retensi',
            detail: t('system.status.dialog.successPrune', {
                deleted: data.deleted_count ?? 0,
                kept: data.kept_count ?? 0
            }),
            life: 4000
        });
        await load();
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Gagal',
            detail: err.response?.data?.message || 'Gagal menjalankan rotasi retensi.',
            life: 4000
        });
    } finally {
        pruningBackups.value = false;
    }
};

const downloadArchive = async (filename) => {
    downloadingFile.value = filename;
    try {
        const response = await apiLogin.get(`/api/backups/${encodeURIComponent(filename)}/download`, {
            responseType: 'blob'
        });
        const blob = new Blob([response.data], { type: 'application/zip' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch {
        toast.add({
            severity: 'error',
            summary: 'Gagal',
            detail: `Gagal mengunduh berkas ${filename}.`,
            life: 4000
        });
    } finally {
        downloadingFile.value = '';
    }
};

const confirmDeleteArchive = (filename) => {
    confirm.require({
        header: t('system.status.dialog.deleteConfirmTitle'),
        message: t('system.status.dialog.deleteConfirmMsg', { filename }),
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: t('system.status.dialog.cancelBtn'),
            severity: 'secondary',
            outlined: true
        },
        acceptProps: {
            label: t('system.status.archives.delete'),
            severity: 'danger'
        },
        accept: async () => {
            deletingFile.value = filename;
            try {
                await apiLogin.delete(`/api/backups/${encodeURIComponent(filename)}`);
                toast.add({
                    severity: 'success',
                    summary: 'Terhapus',
                    detail: t('system.status.dialog.successDelete'),
                    life: 3500
                });
                await load();
            } catch (err) {
                toast.add({
                    severity: 'error',
                    summary: 'Gagal',
                    detail: err.response?.data?.message || 'Gagal menghapus berkas cadangan.',
                    life: 4000
                });
            } finally {
                deletingFile.value = '';
            }
        }
    });
};

onMounted(async () => {
    await load();
});
</script>

<template>
    <div class="space-y-6">
        <ConfirmDialog />

        <!-- Page Header -->
        <div class="card p-5 mb-0 flex flex-col lg:flex-row lg:items-center justify-between gap-4 overflow-hidden">
            <div class="flex items-start gap-3.5 min-w-0 flex-1">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 bg-primary/10 text-primary">
                    <i class="pi pi-server text-2xl" aria-hidden="true"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <h1 class="text-xl font-bold text-surface-900 dark:text-surface-0 m-0 break-words">
                        {{ t('system.status.title') }}
                    </h1>
                    <p class="text-xs text-surface-500 dark:text-surface-400 mt-1 mb-0 leading-relaxed break-words">
                        {{ t('system.status.subtitle') }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap shrink-0 self-start lg:self-center">
                <Button
                    :label="t('system.status.pruneBtn')"
                    icon="pi pi-filter-slash"
                    size="small"
                    severity="secondary"
                    variant="outlined"
                    :loading="pruningBackups"
                    @click="runPrune"
                />
                <Button
                    :label="t('system.status.refreshBtn')"
                    icon="pi pi-refresh"
                    size="small"
                    severity="secondary"
                    variant="outlined"
                    :loading="loading"
                    @click="load"
                />
                <Button
                    :label="t('system.status.createBackupBtn')"
                    icon="pi pi-plus"
                    size="small"
                    severity="primary"
                    @click="openCreateDialog"
                />
            </div>
        </div>

        <!-- Loading / Error State -->
        <div v-if="loading && !statusData" class="card p-12 flex flex-col items-center justify-center gap-3 text-surface-500">
            <i class="pi pi-spin pi-spinner text-3xl text-primary" aria-hidden="true"></i>
            <span class="text-sm">{{ t('system.status.loading') }}</span>
        </div>

        <div v-else-if="error" class="card p-6 border border-red-200 dark:border-red-900/50 bg-red-50/60 dark:bg-red-950/20 text-center">
            <i class="pi pi-exclamation-circle text-2xl text-red-500 mb-2" aria-hidden="true"></i>
            <p class="text-sm text-red-600 dark:text-red-400 mb-3">{{ error }}</p>
            <Button :label="t('system.status.refreshBtn')" icon="pi pi-refresh" size="small" severity="danger" variant="outlined" @click="load" />
        </div>

        <template v-else-if="statusData">
            <!-- Row 1: 4 Summary KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <!-- Card 1: Backup Health -->
                <div class="card mb-0 p-4 flex flex-col justify-between border-l-4 overflow-hidden min-w-0" :class="health?.status === 'HEALTHY' ? 'border-l-green-500' : 'border-l-amber-500'">
                    <div class="flex items-start justify-between gap-2.5 min-w-0">
                        <div class="min-w-0 flex-1">
                            <span class="text-xs font-medium text-surface-500 dark:text-surface-400 block mb-1.5 truncate">
                                {{ t('system.status.kpi.backupHealth') }}
                            </span>
                            <Tag
                                :value="health?.status === 'HEALTHY' ? t('system.status.kpi.healthy') : (health?.status === 'NEVER_BACKED_UP' ? t('system.status.kpi.neverBackedUp') : t('system.status.kpi.checkNeeded'))"
                                :severity="health?.status === 'HEALTHY' ? 'success' : 'warn'"
                                class="font-bold"
                            />
                        </div>
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0" :class="health?.status === 'HEALTHY' ? 'bg-green-100 dark:bg-green-900/30 text-green-600' : 'bg-amber-100 dark:bg-amber-900/30 text-amber-600'">
                            <i class="pi pi-shield text-xl" aria-hidden="true"></i>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-surface-100 dark:border-surface-800 text-xs text-surface-500 dark:text-surface-400 leading-snug break-words" :title="health?.message">
                        {{ health?.last_success?.timestamp ? t('system.status.kpi.lastSuccess', { date: formatDateTime(health.last_success.timestamp) }) : t('system.status.kpi.noSuccessYet') }}
                    </div>
                </div>

                <!-- Card 2: Database Connection -->
                <div class="card mb-0 p-4 flex flex-col justify-between border-l-4 overflow-hidden min-w-0" :class="dbService?.connected ? 'border-l-blue-500' : 'border-l-red-500'">
                    <div class="flex items-start justify-between gap-2.5 min-w-0">
                        <div class="min-w-0 flex-1">
                            <span class="text-xs font-medium text-surface-500 dark:text-surface-400 block mb-1.5 truncate">
                                {{ t('system.status.kpi.dbConnection') }}
                            </span>
                            <div class="flex flex-col items-start gap-1 min-w-0">
                                <Tag
                                    :value="dbService?.connected ? t('system.status.kpi.connected') : t('system.status.kpi.disconnected')"
                                    :severity="dbService?.connected ? 'info' : 'danger'"
                                    class="font-bold shrink-0"
                                />
                                <span
                                    class="text-xs font-mono text-surface-700 dark:text-surface-200 truncate w-full block"
                                    :title="dbService?.database_name"
                                >
                                    {{ dbService?.database_name }}
                                </span>
                            </div>
                        </div>
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 bg-blue-100 dark:bg-blue-900/30 text-blue-600">
                            <i class="pi pi-database text-xl" aria-hidden="true"></i>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-surface-100 dark:border-surface-800 text-xs text-surface-500 dark:text-surface-400 flex items-center justify-between gap-2 flex-wrap">
                        <span class="truncate" :title="dbService?.version">{{ dbService?.version }}</span>
                        <span class="font-semibold text-blue-600 dark:text-blue-400 shrink-0">{{ t('system.status.kpi.latency', { ms: dbService?.latency_ms ?? 0 }) }}</span>
                    </div>
                </div>

                <!-- Card 3: Archives Stored -->
                <div class="card mb-0 p-4 flex flex-col justify-between border-l-4 border-l-purple-500 overflow-hidden min-w-0">
                    <div class="flex items-start justify-between gap-2.5 min-w-0">
                        <div class="min-w-0 flex-1">
                            <span class="text-xs font-medium text-surface-500 dark:text-surface-400 block mb-1 truncate">
                                {{ t('system.status.kpi.archivesStored') }}
                            </span>
                            <span class="text-2xl font-bold text-surface-900 dark:text-surface-0">
                                {{ backup?.archives_count ?? 0 }}
                            </span>
                        </div>
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 bg-purple-100 dark:bg-purple-900/30 text-purple-600">
                            <i class="pi pi-box text-xl" aria-hidden="true"></i>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-surface-100 dark:border-surface-800 text-xs text-surface-500 dark:text-surface-400 leading-snug break-words">
                        {{ t('system.status.kpi.totalSize', { size: formatBytes(backup?.total_archives_bytes ?? 0) }) }}
                    </div>
                </div>

                <!-- Card 4: Automated Schedule -->
                <div class="card mb-0 p-4 flex flex-col justify-between border-l-4 overflow-hidden min-w-0" :class="scheduleConfig?.schedule_enabled ? 'border-l-teal-500' : 'border-l-surface-400'">
                    <div class="flex items-start justify-between gap-2.5 min-w-0">
                        <div class="min-w-0 flex-1">
                            <span class="text-xs font-medium text-surface-500 dark:text-surface-400 block mb-1.5 truncate">
                                {{ t('system.status.kpi.autoSchedule') }}
                            </span>
                            <Tag
                                :value="scheduleConfig?.schedule_enabled ? t('system.status.kpi.enabled') : t('system.status.kpi.disabled')"
                                :severity="scheduleConfig?.schedule_enabled ? 'success' : 'secondary'"
                                class="font-bold"
                            />
                        </div>
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 bg-teal-100 dark:bg-teal-900/30 text-teal-600">
                            <i class="pi pi-clock text-xl" aria-hidden="true"></i>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-surface-100 dark:border-surface-800 text-xs text-surface-500 dark:text-surface-400 leading-snug break-words">
                        {{ t('system.status.kpi.scheduleTime', { time: scheduleConfig?.backup_time ?? '02:00', days: scheduleConfig?.retention_days ?? 7, weeks: scheduleConfig?.retention_weeks ?? 4 }) }}
                    </div>
                </div>
            </div>

            <!-- Row 2: Encrypted Backup Archives Table -->
            <div class="card p-5 mb-0 overflow-hidden">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <div class="min-w-0">
                        <h2 class="text-base font-bold text-surface-900 dark:text-surface-0 m-0 break-words">
                            {{ t('system.status.archives.title') }}
                        </h2>
                        <p class="text-xs text-surface-500 dark:text-surface-400 mt-0.5 mb-0 break-words">
                            {{ t('system.status.archives.subtitle') }}
                        </p>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="archives.length === 0" class="py-10 px-4 text-center border border-dashed border-surface-300 dark:border-surface-700 rounded-xl bg-surface-50/40 dark:bg-surface-800/20">
                    <div class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center mx-auto mb-3">
                        <i class="pi pi-database text-xl" aria-hidden="true"></i>
                    </div>
                    <div class="font-semibold text-sm text-surface-800 dark:text-surface-100 mb-1">
                        {{ t('system.status.archives.emptyTitle') }}
                    </div>
                    <p class="text-xs text-surface-500 dark:text-surface-400 max-w-md mx-auto mb-4">
                        {{ t('system.status.archives.emptySubtitle') }}
                    </p>
                    <Button
                        :label="t('system.status.createBackupBtn')"
                        icon="pi pi-plus"
                        size="small"
                        @click="openCreateDialog"
                    />
                </div>

                <!-- DataTable Archives -->
                <DataTable v-else :value="archives" responsiveLayout="scroll" class="text-sm" stripedRows>
                    <Column field="filename" :header="t('system.status.archives.colFilename')">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2 font-mono text-xs font-semibold text-surface-900 dark:text-surface-100">
                                <i class="pi pi-file-export text-primary shrink-0" aria-hidden="true"></i>
                                <span class="break-all">{{ data.filename }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column field="created_at" :header="t('system.status.archives.colCreatedAt')">
                        <template #body="{ data }">
                            <span class="text-xs text-surface-600 dark:text-surface-300 whitespace-nowrap">
                                {{ formatDateTime(data.created_at) }}
                            </span>
                        </template>
                    </Column>

                    <Column field="size_bytes" :header="t('system.status.archives.colSize')">
                        <template #body="{ data }">
                            <span class="text-xs font-medium whitespace-nowrap">
                                {{ formatBytes(data.size_bytes) }}
                            </span>
                        </template>
                    </Column>

                    <Column field="sha256" :header="t('system.status.archives.colChecksum')">
                        <template #body="{ data }">
                            <div class="flex items-center gap-1.5">
                                <code class="text-[11px] bg-surface-100 dark:bg-surface-800 px-2 py-0.5 rounded text-surface-600 dark:text-surface-300 whitespace-nowrap">
                                    {{ data.sha256 ? `${data.sha256.slice(0, 12)}...${data.sha256.slice(-6)}` : '-' }}
                                </code>
                                <Button
                                    v-if="data.sha256"
                                    icon="pi pi-copy"
                                    size="small"
                                    variant="text"
                                    severity="secondary"
                                    class="!p-1 !w-6 !h-6 shrink-0"
                                    :tooltip="t('system.status.archives.copySha')"
                                    @click="copyToClipboard(data.sha256, 'system.status.archives.copied')"
                                />
                            </div>
                        </template>
                    </Column>

                    <Column :header="t('system.status.archives.colSecurity')">
                        <template #body>
                            <Tag value="AES-256 ZIP" severity="success" icon="pi pi-lock" class="!text-[10px] whitespace-nowrap" />
                        </template>
                    </Column>

                    <Column :header="t('system.status.archives.colActions')" class="text-right">
                        <template #body="{ data }">
                            <div class="flex items-center justify-end gap-1.5">
                                <Button
                                    :label="t('system.status.archives.download')"
                                    icon="pi pi-download"
                                    size="small"
                                    severity="primary"
                                    variant="outlined"
                                    :loading="downloadingFile === data.filename"
                                    @click="downloadArchive(data.filename)"
                                />
                                <Button
                                    icon="pi pi-trash"
                                    size="small"
                                    severity="danger"
                                    variant="text"
                                    :loading="deletingFile === data.filename"
                                    :tooltip="t('system.status.archives.delete')"
                                    @click="confirmDeleteArchive(data.filename)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <!-- Pre-Reset JSON Snapshots Section (if any exist) -->
                <div v-if="jsonSnapshots.length > 0" class="mt-5 pt-4 border-t border-surface-200 dark:border-surface-700">
                    <div class="text-xs font-semibold text-surface-700 dark:text-surface-200 mb-1">
                        {{ t('system.status.archives.snapshotsTitle', { count: jsonSnapshots.length }) }}
                    </div>
                    <p class="text-[11px] text-surface-500 dark:text-surface-400 mb-2.5">
                        {{ t('system.status.archives.snapshotsSubtitle') }}
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2.5">
                        <div
                            v-for="snap in jsonSnapshots"
                            :key="snap.filename"
                            class="p-2.5 rounded-lg border border-surface-200 dark:border-surface-700 bg-surface-50/60 dark:bg-surface-800/40 flex items-center justify-between gap-2 text-xs overflow-hidden min-w-0"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="font-mono font-medium text-surface-800 dark:text-surface-100 truncate" :title="snap.filename">
                                    {{ snap.filename }}
                                </div>
                                <div class="text-[11px] text-surface-400 truncate">
                                    {{ formatDateTime(snap.created_at) }}
                                </div>
                            </div>
                            <Tag :value="formatBytes(snap.size_bytes)" severity="secondary" class="shrink-0" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 3: Runtime Health & Storage Directories -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Col 1: PHP Runtime & Extensions -->
                <div class="card p-5 mb-0 flex flex-col justify-between overflow-hidden min-w-0">
                    <div>
                        <h3 class="text-base font-bold text-surface-900 dark:text-surface-0 m-0 mb-4 flex items-center gap-2">
                            <i class="pi pi-cog text-primary shrink-0" aria-hidden="true"></i>
                            <span class="break-words">{{ t('system.status.services.runtimeTitle') }}</span>
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3.5 rounded-xl bg-surface-50 dark:bg-surface-800/40 border border-surface-200 dark:border-surface-700 text-xs mb-4">
                            <div class="min-w-0">
                                <span class="text-surface-500 block mb-0.5">{{ t('system.status.services.phpVersion') }}</span>
                                <span class="font-bold text-surface-900 dark:text-surface-0 break-words">PHP {{ phpRuntime?.php_version }} ({{ phpRuntime?.os_family }})</span>
                            </div>
                            <div class="min-w-0">
                                <span class="text-surface-500 block mb-0.5">{{ t('system.status.services.serverTime') }}</span>
                                <span class="font-mono font-semibold text-surface-900 dark:text-surface-0 break-words">{{ phpRuntime?.server_time_wib }}</span>
                            </div>
                            <div class="min-w-0">
                                <span class="text-surface-500 block mb-0.5">{{ t('system.status.services.zipAes256') }}</span>
                                <Tag
                                    :value="phpRuntime?.zip_aes256_supported ? t('system.status.services.supported') : t('system.status.services.notSupported')"
                                    :severity="phpRuntime?.zip_aes256_supported ? 'success' : 'danger'"
                                    class="!text-[10px]"
                                />
                            </div>
                            <div class="min-w-0">
                                <span class="text-surface-500 block mb-0.5">{{ t('system.status.services.mysqldump') }}</span>
                                <Tag
                                    :value="binaries?.mysqldump_available ? t('system.status.services.available') : t('system.status.services.missing')"
                                    :severity="binaries?.mysqldump_available ? 'success' : 'warn'"
                                    class="!text-[10px]"
                                />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div
                                v-for="ext in phpRuntime?.extensions || []"
                                :key="ext.name"
                                class="p-2.5 rounded-lg border border-surface-200 dark:border-surface-700 flex items-center justify-between gap-3 text-xs overflow-hidden"
                            >
                                <div class="min-w-0 flex-1 break-words">
                                    <span class="font-mono font-bold text-surface-900 dark:text-surface-100">{{ ext.name }}</span>
                                    <span class="text-surface-500 dark:text-surface-400 ml-1.5">— {{ ext.description }}</span>
                                </div>
                                <Tag
                                    :value="ext.loaded ? t('system.status.services.extLoaded') : t('system.status.services.extMissing')"
                                    :severity="ext.loaded ? 'success' : 'danger'"
                                    class="shrink-0 !text-[10px]"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Col 2: Storage Directories & Automation Guide -->
                <div class="card p-5 mb-0 flex flex-col justify-between space-y-4 overflow-hidden min-w-0">
                    <div>
                        <h3 class="text-base font-bold text-surface-900 dark:text-surface-0 m-0 mb-4 flex items-center gap-2">
                            <i class="pi pi-folder-open text-primary shrink-0" aria-hidden="true"></i>
                            <span class="break-words">{{ t('system.status.services.storageTitle') }}</span>
                        </h3>

                        <div class="space-y-2.5">
                            <div
                                v-for="dir in storageDirs"
                                :key="dir.key"
                                class="p-3 rounded-xl border border-surface-200 dark:border-surface-700 flex items-center justify-between gap-3 text-xs overflow-hidden"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-surface-900 dark:text-surface-100 break-words">{{ dir.label }}</div>
                                    <div class="font-mono text-[11px] text-surface-500 mt-0.5 break-all sm:break-words">
                                        {{ dir.relative_path }} • {{ t('system.status.services.filesCount', { count: dir.files_count }) }} ({{ formatBytes(dir.size_bytes) }})
                                    </div>
                                </div>
                                <Tag
                                    :value="dir.writable ? t('system.status.services.writable') : t('system.status.services.readOnly')"
                                    :severity="dir.writable ? 'success' : 'danger'"
                                    class="shrink-0 !text-[10px]"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Automation Info Box -->
                    <div class="p-4 rounded-xl border border-blue-200 dark:border-blue-900/50 bg-blue-50/60 dark:bg-blue-950/20 text-xs overflow-hidden">
                        <div class="flex items-start gap-2.5 min-w-0">
                            <i class="pi pi-info-circle text-blue-600 dark:text-blue-400 text-base mt-0.5 shrink-0" aria-hidden="true"></i>
                            <div class="min-w-0 flex-1">
                                <div class="font-bold text-blue-900 dark:text-blue-200 mb-1 break-words">
                                    {{ t('system.status.services.automationGuideTitle') }}
                                </div>
                                <p class="text-blue-800 dark:text-blue-300 leading-relaxed m-0 break-words">
                                    {{ t('system.status.services.automationGuideDesc', { time: scheduleConfig?.backup_time ?? '02:00' }) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Dialog Buat Backup Baru -->
        <Dialog
            v-model:visible="createDialogVisible"
            :header="t('system.status.dialog.createTitle')"
            modal
            :style="{ width: '32rem', maxWidth: '95vw' }"
            :closable="!creatingBackup"
        >
            <div class="space-y-4">
                <p class="text-xs text-surface-600 dark:text-surface-300 leading-relaxed m-0">
                    {{ t('system.status.dialog.createDesc') }}
                </p>

                <div>
                    <label class="block text-xs font-semibold text-surface-700 dark:text-surface-200 mb-1.5">
                        {{ t('system.status.dialog.passphraseLabel') }}
                    </label>
                    <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
                        <div class="relative flex-1 min-w-[200px]">
                            <InputText
                                v-model="passphrase"
                                :type="showPassphrase ? 'text' : 'password'"
                                :placeholder="t('system.status.dialog.passphrasePlaceholder')"
                                class="w-full text-sm font-mono pr-9"
                                :disabled="creatingBackup"
                            />
                            <button
                                type="button"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-600"
                                @click="showPassphrase = !showPassphrase"
                            >
                                <i :class="showPassphrase ? 'pi pi-eye-slash' : 'pi pi-eye'" aria-hidden="true"></i>
                            </button>
                        </div>
                        <Button
                            icon="pi pi-key"
                            :label="t('system.status.dialog.generateKeyBtn')"
                            size="small"
                            severity="secondary"
                            variant="outlined"
                            class="shrink-0"
                            :disabled="creatingBackup"
                            @click="generateSecurePassphrase"
                        />
                        <Button
                            v-if="passphrase"
                            icon="pi pi-copy"
                            size="small"
                            severity="info"
                            variant="outlined"
                            class="shrink-0"
                            :tooltip="t('system.status.dialog.copyKeyBtn')"
                            @click="copyToClipboard(passphrase)"
                        />
                    </div>
                    <small class="block text-[11px] text-amber-600 dark:text-amber-400 mt-1.5 leading-snug break-words">
                        {{ hasConfiguredKey ? t('system.status.dialog.passphraseOptionalHint') : t('system.status.dialog.passphraseRequiredHint') }}
                    </small>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button
                        :label="t('system.status.dialog.cancelBtn')"
                        severity="secondary"
                        variant="outlined"
                        size="small"
                        :disabled="creatingBackup"
                        @click="createDialogVisible = false"
                    />
                    <Button
                        :label="creatingBackup ? t('system.status.dialog.runningBtn') : t('system.status.dialog.submitBtn')"
                        icon="pi pi-check"
                        size="small"
                        :loading="creatingBackup"
                        :disabled="!hasConfiguredKey && passphrase.trim().length < 12"
                        @click="runBackupNow"
                    />
                </div>
            </template>
        </Dialog>
    </div>
</template>
