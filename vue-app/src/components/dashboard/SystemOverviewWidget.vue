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
        error.value = 'Informasi operasional sistem tidak dapat dimuat.';
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
    <div class="card mb-6">
        <div class="flex items-center justify-between mb-4">
            <span class="font-semibold text-xl">Sistem & Pintasan Operasional</span>
            <Button icon="pi pi-refresh" size="small" variant="text" severity="secondary" :loading="loading" aria-label="Segarkan sistem" @click="load" />
        </div>

        <div v-if="loading" class="flex items-center justify-center py-8 text-surface-500 gap-3">
            <i class="pi pi-spin pi-spinner text-lg" aria-hidden="true"></i>
            <span class="text-sm">Memuat informasi sistem...</span>
        </div>

        <div v-else-if="error" class="py-4 text-center text-xs text-red-500">
            {{ error }}
        </div>

        <div v-else class="space-y-4">
            <!-- Period & Server Info Box -->
            <div class="grid grid-cols-2 gap-3 p-3 rounded-lg border border-surface bg-surface-50/50 dark:bg-surface-800/20 text-xs">
                <div>
                    <span class="text-muted-color block mb-1">Tahun Ajaran Aktif</span>
                    <div class="font-semibold text-surface-900 dark:text-surface-0 flex items-center gap-1.5 flex-wrap">
                        <span>{{ academic?.active_school_year || 'Belum Diatur' }}</span>
                        <Tag :value="academic?.is_locked ? 'Terkunci' : 'Aktif'" :severity="academic?.is_locked ? 'danger' : 'success'" class="!text-[10px] !py-0 !px-1.5" />
                    </div>
                </div>
                <div>
                    <span class="text-muted-color block mb-1">Semester Berjalan</span>
                    <div class="font-semibold text-surface-900 dark:text-surface-0">
                        {{ academic?.active_semester || 'Belum Diatur' }}
                    </div>
                </div>
                <div class="col-span-2 pt-2 border-t border-surface/50 flex items-center justify-between">
                    <span class="text-muted-color">Waktu Server (WIB):</span>
                    <span class="font-mono text-surface-700 dark:text-surface-200">
                        {{ meta?.server_time_wib || '-' }}
                    </span>
                </div>
            </div>

            <!-- Backup Health (Admin / Backup Managers only) -->
            <div v-if="canManageBackup && system" class="p-3 rounded-lg border border-surface flex items-center justify-between text-xs">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0" :class="system.backup_status === 'healthy' ? 'bg-green-100 dark:bg-green-400/10 text-green-600' : 'bg-amber-100 dark:bg-amber-400/10 text-amber-600'">
                        <i class="pi pi-database" aria-hidden="true"></i>
                    </div>
                    <div>
                        <div class="font-medium text-surface-900 dark:text-surface-0">Status Backup Database</div>
                        <div class="text-muted-color">{{ system.last_backup_at ? `Terakhir: ${system.last_backup_at}` : 'Belum ada rekaman cadangan' }}</div>
                    </div>
                </div>
                <Tag :value="system.backup_status === 'healthy' ? 'Sehat' : 'Perlu Cek'" :severity="system.backup_status === 'healthy' ? 'success' : 'warn'" size="small" />
            </div>

            <!-- Quick Action Shortcuts -->
            <div>
                <span class="block text-xs font-semibold uppercase tracking-wider text-muted-color mb-2">Pintasan Fitur Cepat</span>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    <Button v-if="store.can('teaching.view')" label="Jurnal Mengajar" icon="pi pi-book" size="small" severity="secondary" variant="outlined" class="!justify-start !text-xs !py-2" @click="navigateTo('/public/teaching/journals')" />
                    <Button v-if="store.can('teaching.view')" label="Laporan KBM" icon="pi pi-chart-line" size="small" severity="secondary" variant="outlined" class="!justify-start !text-xs !py-2" @click="navigateTo('/public/teaching/reports')" />
                    <Button v-if="store.can('letters.view')" label="Surat Masuk" icon="pi pi-inbox" size="small" severity="secondary" variant="outlined" class="!justify-start !text-xs !py-2" @click="navigateTo('/public/letters/incoming')" />
                    <Button v-if="store.can('letters.view')" label="Surat Keluar" icon="pi pi-send" size="small" severity="secondary" variant="outlined" class="!justify-start !text-xs !py-2" @click="navigateTo('/public/letters/outgoing')" />
                    <Button
                        v-if="store.can('leaves.view') || store.can('leaves.request')"
                        label="Perizinan & Cuti"
                        icon="pi pi-calendar-times"
                        size="small"
                        severity="secondary"
                        variant="outlined"
                        class="!justify-start !text-xs !py-2"
                        @click="navigateTo('/public/leaves')"
                    />
                    <Button v-if="store.can('students.view')" label="Direktori Siswa" icon="pi pi-users" size="small" severity="secondary" variant="outlined" class="!justify-start !text-xs !py-2" @click="navigateTo('/public/students/directory')" />
                    <Button v-if="store.can('employees.view')" label="Direktori PTK" icon="pi pi-id-card" size="small" severity="secondary" variant="outlined" class="!justify-start !text-xs !py-2" @click="navigateTo('/public/employees')" />
                    <Button v-if="store.can('archives.view')" label="Arsip Dokumen" icon="pi pi-folder" size="small" severity="secondary" variant="outlined" class="!justify-start !text-xs !py-2" @click="navigateTo('/public/archives')" />
                </div>
            </div>
        </div>
    </div>
</template>
