<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import apiLogin from '@/apiLogin';
import { authStore } from '@/stores/authStore';

const router = useRouter();
const toast = useToast();
const confirm = useConfirm();
const store = authStore();

const canManage = computed(() => store.can('tasks.manage') || store.isAdmin());

// State
const loading = ref(false);
const loadingSummary = ref(false);
const activeTab = ref('all');
const tasks = ref([]);
const pagination = ref({
    current_page: 1,
    per_page: 25,
    total: 0,
    total_pages: 1
});

const summary = ref({
    total_active: 0,
    total_high: 0,
    total_warn: 0,
    total_info: 0,
    completed_today: 0,
    by_domain: {}
});

const filters = reactive({
    domain: '',
    urgency: '',
    search: ''
});

// Reminder Dialog Form State
const reminderDialogVisible = ref(false);
const savingReminder = ref(false);
const isEditing = ref(false);
const reminderForm = reactive({
    id: null,
    title: '',
    description: '',
    category: 'general',
    priority: 'warn',
    due_date: ''
});

const domainOptions = [
    { label: 'Semua Domain', value: '' },
    { label: 'Persuratan (Letters)', value: 'letters' },
    { label: 'Perizinan (Leaves)', value: 'leaves' },
    { label: 'Pembelajaran (Teaching)', value: 'teaching' },
    { label: 'Arsip Dokumen (Archives)', value: 'archives' },
    { label: 'Kesiswaan (Students)', value: 'students' },
    { label: 'Ketenagaan (Personnel)', value: 'personnel' },
    { label: 'Umum & Operasional', value: 'general' }
];

const urgencyOptions = [
    { label: 'Semua Urgensi', value: '' },
    { label: 'Mendesak (High)', value: 'high' },
    { label: 'Perhatian (Warn)', value: 'warn' },
    { label: 'Informasi (Info)', value: 'info' }
];

const priorityOptions = [
    { label: 'Tinggi / Mendesak (High)', value: 'high' },
    { label: 'Sedang / Perhatian (Warn)', value: 'warn' },
    { label: 'Rendah / Info (Info)', value: 'info' }
];

const categoryOptions = [
    { label: 'Umum & Operasional', value: 'general' },
    { label: 'Persuratan', value: 'letters' },
    { label: 'Perizinan', value: 'leaves' },
    { label: 'Pembelajaran', value: 'teaching' },
    { label: 'Arsip Dokumen', value: 'archives' },
    { label: 'Kesiswaan', value: 'students' },
    { label: 'Ketenagaan', value: 'personnel' }
];

// Helper methods
const getDomainBadge = (domain) => {
    switch (domain) {
        case 'letters':
            return { label: 'Persuratan', severity: 'info', icon: 'pi pi-envelope' };
        case 'leaves':
            return { label: 'Perizinan', severity: 'warn', icon: 'pi pi-calendar-times' };
        case 'teaching':
            return { label: 'Pembelajaran', severity: 'help', icon: 'pi pi-book' };
        case 'archives':
            return { label: 'Arsip', severity: 'secondary', icon: 'pi pi-folder' };
        case 'students':
            return { label: 'Kesiswaan', severity: 'success', icon: 'pi pi-users' };
        case 'personnel':
            return { label: 'Ketenagaan', severity: 'primary', icon: 'pi pi-id-card' };
        default:
            return { label: 'Umum', severity: 'contrast', icon: 'pi pi-tag' };
    }
};

const getUrgencyBadge = (urgency) => {
    switch (urgency) {
        case 'high':
            return { label: 'Mendesak', severity: 'danger' };
        case 'warn':
            return { label: 'Perhatian', severity: 'warn' };
        default:
            return { label: 'Informasi', severity: 'info' };
    }
};

// API calls
const loadSummary = async () => {
    loadingSummary.value = true;
    try {
        const response = await apiLogin.get('/api/tasks/summary');
        if (response.data?.success) {
            summary.value = response.data.data;
        }
    } catch (err) {
        toast.add({
            severity: 'warn',
            summary: 'Peringatan',
            detail: 'Gagal memuat ringkasan tugas: ' + (err.response?.data?.message || err.message),
            life: 4000
        });
    } finally {
        loadingSummary.value = false;
    }
};

const loadTasks = async () => {
    loading.value = true;
    try {
        const params = {
            tab: activeTab.value,
            domain: filters.domain || undefined,
            urgency: filters.urgency || undefined,
            search: filters.search ? filters.search.trim() : undefined,
            page: pagination.value.current_page,
            per_page: pagination.value.per_page
        };

        const response = await apiLogin.get('/api/tasks', { params });
        if (response.data?.success) {
            tasks.value = response.data.data;
            pagination.value = response.data.pagination;
        }
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Gagal Memuat Tugas',
            detail: err.response?.data?.message || 'Terjadi kesalahan pada server.',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

const onTabChange = (newTab) => {
    activeTab.value = newTab;
    pagination.value.current_page = 1;
    loadTasks();
};

let searchDebounceTimer = null;
const onSearchInput = () => {
    if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
    pagination.value.current_page = 1;
    loadTasks();
};

const onSearchDebounce = () => {
    if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(onSearchInput, 300);
};

const resetFilters = () => {
    filters.domain = '';
    filters.urgency = '';
    filters.search = '';
    pagination.value.current_page = 1;
    loadTasks();
};

const openActionUrl = (url) => {
    if (url) {
        router.push(url);
    }
};

// Manual Reminder Actions
const openCreateReminder = () => {
    isEditing.value = false;
    reminderForm.id = null;
    reminderForm.title = '';
    reminderForm.description = '';
    reminderForm.category = 'general';
    reminderForm.priority = 'warn';
    reminderForm.due_date = '';
    reminderDialogVisible.value = true;
};

const openEditReminder = (task) => {
    isEditing.value = true;
    reminderForm.id = task.id;
    reminderForm.title = task.title;
    reminderForm.description = task.description || '';
    reminderForm.category = task.category || 'general';
    reminderForm.priority = task.urgency || 'warn';
    reminderForm.due_date = task.due_date || '';
    reminderDialogVisible.value = true;
};

const saveReminder = async () => {
    if (!reminderForm.title.trim()) {
        toast.add({
            severity: 'warn',
            summary: 'Peringatan Validasi',
            detail: 'Judul tugas pengingat wajib diisi.',
            life: 3000
        });
        return;
    }

    savingReminder.value = true;
    try {
        const payload = {
            title: reminderForm.title.trim(),
            description: reminderForm.description ? reminderForm.description.trim() : null,
            category: reminderForm.category,
            priority: reminderForm.priority,
            due_date: reminderForm.due_date || null
        };

        if (isEditing.value) {
            await apiLogin.put(`/api/tasks/reminders/${reminderForm.id}`, payload);
            toast.add({
                severity: 'success',
                summary: 'Berhasil Diperbarui',
                detail: 'Pengingat berhasil diperbarui.',
                life: 3000
            });
        } else {
            await apiLogin.post('/api/tasks/reminders', payload);
            toast.add({
                severity: 'success',
                summary: 'Berhasil Dibuat',
                detail: 'Pengingat baru berhasil ditambahkan.',
                life: 3000
            });
        }

        reminderDialogVisible.value = false;
        await Promise.all([loadTasks(), loadSummary()]);
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Gagal Menyimpan',
            detail: err.response?.data?.message || 'Gagal menyimpan pengingat.',
            life: 3000
        });
    } finally {
        savingReminder.value = false;
    }
};

const markComplete = async (task) => {
    try {
        await apiLogin.post(`/api/tasks/reminders/${task.id}/complete`);
        toast.add({
            severity: 'success',
            summary: 'Tugas Selesai',
            detail: `"${task.title}" telah ditandai selesai.`,
            life: 3000
        });
        await Promise.all([loadTasks(), loadSummary()]);
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Gagal Menyelesaikan Tugas',
            detail: err.response?.data?.message || 'Terjadi kesalahan.',
            life: 3000
        });
    }
};

const cancelReminder = (task) => {
    confirm.require({
        message: `Batalkan tugas "${task.title}"?`,
        header: 'Konfirmasi Pembatalan',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-warn',
        accept: async () => {
            try {
                await apiLogin.post(`/api/tasks/reminders/${task.id}/cancel`);
                toast.add({
                    severity: 'info',
                    summary: 'Tugas Dibatalkan',
                    detail: `"${task.title}" telah dibatalkan.`,
                    life: 3000
                });
                await Promise.all([loadTasks(), loadSummary()]);
            } catch (err) {
                toast.add({
                    severity: 'error',
                    summary: 'Gagal Membatalkan',
                    detail: err.response?.data?.message || 'Terjadi kesalahan.',
                    life: 3000
                });
            }
        }
    });
};

const deleteReminder = (task) => {
    confirm.require({
        message: `Hapus permanen pengingat "${task.title}"?`,
        header: 'Konfirmasi Penghapusan',
        icon: 'pi pi-trash',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await apiLogin.delete(`/api/tasks/reminders/${task.id}`);
                toast.add({
                    severity: 'success',
                    summary: 'Pengingat Dihapus',
                    detail: 'Catatan pengingat berhasil dihapus permanen.',
                    life: 3000
                });
                await Promise.all([loadTasks(), loadSummary()]);
            } catch (err) {
                toast.add({
                    severity: 'error',
                    summary: 'Gagal Menghapus',
                    detail: err.response?.data?.message || 'Terjadi kesalahan.',
                    life: 3000
                });
            }
        }
    });
};

const dismissSystemTask = (task) => {
    confirm.require({
        message: `Tandai tugas sistem "${task.title}" sebagai selesai/diabaikan dari daftar aktif?`,
        header: 'Tandai Selesai Tugas Sistem',
        icon: 'pi pi-check-circle',
        accept: async () => {
            try {
                await apiLogin.post('/api/tasks/system/dismiss', {
                    system_source_key: task.id,
                    title: task.title,
                    domain: task.domain
                });
                toast.add({
                    severity: 'success',
                    summary: 'Tugas Diselesaikan',
                    detail: `Tugas "${task.title}" telah dipindahkan ke riwayat selesai.`,
                    life: 3000
                });
                await Promise.all([loadTasks(), loadSummary()]);
            } catch (err) {
                toast.add({
                    severity: 'error',
                    summary: 'Gagal Menyelesaikan Tugas Sistem',
                    detail: err.response?.data?.message || 'Terjadi kesalahan.',
                    life: 3000
                });
            }
        }
    });
};

onMounted(async () => {
    await Promise.all([loadSummary(), loadTasks()]);
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Header & Breadcrumb Context -->
        <div class="card p-6 rounded-2xl border border-surface-200 dark:border-surface-700 shadow-sm mb-0">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-surface-900 dark:text-surface-0 tracking-tight flex items-center gap-2">
                        <i class="pi pi-check-square text-primary text-2xl"></i>
                        Pusat Tugas Operasional & Pengingat Internal
                    </h2>
                    <p class="text-surface-500 text-sm mt-1">Pusat kendali tindak lanjut operasional terpadu: antrean persuratan, perizinan, pengarsipan, jurnal mengajar, serta catatan pengingat internal staf (DEC-44).</p>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        icon="pi pi-refresh"
                        label="Segarkan"
                        severity="secondary"
                        variant="outlined"
                        size="small"
                        :loading="loading || loadingSummary"
                        @click="
                            () => {
                                loadSummary();
                                loadTasks();
                            }
                        "
                    />
                    <Button v-if="canManage" icon="pi pi-plus" label="Buat Pengingat Baru" severity="primary" size="small" @click="openCreateReminder" />
                </div>
            </div>

            <!-- 4 KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
                <!-- Total Aktif -->
                <div class="p-4 rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/50 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-blue-100 dark:bg-blue-400/10 text-blue-600 flex items-center justify-center shrink-0">
                        <i class="pi pi-list-check text-2xl"></i>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-surface-500">Total Tugas Aktif</div>
                        <div class="text-2xl font-bold text-surface-900 dark:text-surface-0 mt-0.5">
                            {{ summary.total_active }}
                        </div>
                    </div>
                </div>

                <!-- Butuh Tindakan Segera (High) -->
                <div class="p-4 rounded-xl border border-red-200 dark:border-red-900/40 bg-red-50 dark:bg-red-900/10 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-red-100 dark:bg-red-400/20 text-red-600 flex items-center justify-center shrink-0">
                        <i class="pi pi-exclamation-circle text-2xl"></i>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-red-600 dark:text-red-400">Tindakan Segera</div>
                        <div class="text-2xl font-bold text-red-700 dark:text-red-300 mt-0.5">
                            {{ summary.total_high }}
                        </div>
                    </div>
                </div>

                <!-- Peringatan Operasional (Warn) -->
                <div class="p-4 rounded-xl border border-amber-200 dark:border-amber-900/40 bg-amber-50 dark:bg-amber-900/10 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-amber-100 dark:bg-amber-400/20 text-amber-600 flex items-center justify-center shrink-0">
                        <i class="pi pi-clock text-2xl"></i>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">Perhatian / Jatuh Tempo</div>
                        <div class="text-2xl font-bold text-amber-700 dark:text-amber-300 mt-0.5">
                            {{ summary.total_warn }}
                        </div>
                    </div>
                </div>

                <!-- Selesai Hari Ini -->
                <div class="p-4 rounded-xl border border-green-200 dark:border-green-900/40 bg-green-50 dark:bg-green-900/10 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-green-100 dark:bg-green-400/20 text-green-600 flex items-center justify-center shrink-0">
                        <i class="pi pi-check-circle text-2xl"></i>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-green-600 dark:text-green-400">Selesai Hari Ini</div>
                        <div class="text-2xl font-bold text-green-700 dark:text-green-300 mt-0.5">
                            {{ summary.completed_today }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card p-4 rounded-2xl border border-surface-200 dark:border-surface-700 shadow-sm mb-0">
            <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Domain Select -->
                    <Select v-model="filters.domain" :options="domainOptions" option-label="label" option-value="value" placeholder="Pilih Domain" class="w-full md:w-56" size="small" @change="loadTasks" />

                    <!-- Urgency Select -->
                    <Select v-model="filters.urgency" :options="urgencyOptions" option-label="label" option-value="value" placeholder="Pilih Urgensi" class="w-full md:w-48" size="small" @change="loadTasks" />

                    <Button v-if="filters.domain || filters.urgency || filters.search" icon="pi pi-filter-slash" label="Reset" severity="secondary" variant="text" size="small" @click="resetFilters" />
                </div>

                <!-- Search Input -->
                <div class="w-full md:w-72">
                    <IconField icon-position="left">
                        <InputIcon class="pi pi-search" />
                        <InputText v-model="filters.search" placeholder="Cari judul atau uraian..." class="w-full" size="small" @input="onSearchDebounce" @keyup.enter="onSearchInput" />
                    </IconField>
                </div>
            </div>
        </div>

        <!-- Main Task List Container with Tabs -->
        <div class="card p-6 rounded-2xl border border-surface-200 dark:border-surface-700 shadow-sm mb-0">
            <!-- Tab Navigation -->
            <Tabs v-model:value="activeTab" @update:value="onTabChange">
                <TabList>
                    <Tab value="all" class="flex items-center gap-2">
                        <i class="pi pi-th-large"></i>
                        <span>Semua Tugas</span>
                        <Tag v-if="summary.total_active > 0" :value="summary.total_active" severity="secondary" rounded class="!py-0 !px-1.5 !text-[11px]" />
                    </Tab>
                    <Tab value="system" class="flex items-center gap-2">
                        <i class="pi pi-cog"></i>
                        <span>Tugas Sistem</span>
                    </Tab>
                    <Tab value="manual" class="flex items-center gap-2">
                        <i class="pi pi-bookmark"></i>
                        <span>Pengingat Manual</span>
                    </Tab>
                    <Tab value="completed" class="flex items-center gap-2">
                        <i class="pi pi-history"></i>
                        <span>Riwayat Selesai</span>
                    </Tab>
                </TabList>
            </Tabs>

            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-16 text-surface-500 gap-3">
                <i class="pi pi-spin pi-spinner text-2xl text-primary"></i>
                <span class="text-sm">Memuat daftar tugas operasional...</span>
            </div>

            <!-- Empty State -->
            <div v-else-if="tasks.length === 0" class="flex flex-col items-center justify-center py-16 text-center text-surface-500">
                <div class="w-16 h-16 rounded-full bg-green-100 dark:bg-green-400/10 text-green-600 flex items-center justify-center mb-3">
                    <i class="pi pi-check-circle text-3xl"></i>
                </div>
                <span class="text-lg font-semibold text-surface-800 dark:text-surface-100">Tidak Ada Tugas Tertunda</span>
                <p class="text-sm text-surface-400 max-w-md mt-1">Semua antrean operasional telah selesai ditindaklanjuti atau sesuai kriteria filter yang Anda pilih.</p>
                <Button v-if="canManage" icon="pi pi-plus" label="Tambah Pengingat Baru" size="small" class="mt-4" @click="openCreateReminder" />
            </div>

            <!-- Task Items List -->
            <div v-else class="divide-y divide-surface-200 dark:divide-surface-700 mt-4">
                <div v-for="task in tasks" :key="task.id" class="py-4 px-3 rounded-xl hover:bg-surface-50 dark:hover:bg-surface-800/40 transition-colors flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <!-- Left: Domain Icon & Task Content -->
                    <div class="flex items-start gap-4 min-w-0 flex-1">
                        <div
                            class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 mt-0.5 text-lg"
                            :class="[
                                task.domain === 'letters'
                                    ? 'bg-blue-100 text-blue-600 dark:bg-blue-400/10'
                                    : task.domain === 'leaves'
                                      ? 'bg-orange-100 text-orange-600 dark:bg-orange-400/10'
                                      : task.domain === 'teaching'
                                        ? 'bg-purple-100 text-purple-600 dark:bg-purple-400/10'
                                        : task.domain === 'archives'
                                          ? 'bg-cyan-100 text-cyan-600 dark:bg-cyan-400/10'
                                          : task.domain === 'students'
                                            ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-400/10'
                                            : 'bg-surface-200 text-surface-700 dark:bg-surface-700 dark:text-surface-200'
                            ]"
                        >
                            <i :class="getDomainBadge(task.domain).icon"></i>
                        </div>

                        <div class="min-w-0 flex-1">
                            <!-- Badges Row -->
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <Tag :value="getDomainBadge(task.domain).label" :severity="getDomainBadge(task.domain).severity" size="small" class="!text-[11px] !py-0 !px-2" />
                                <Tag :value="getUrgencyBadge(task.urgency).label" :severity="getUrgencyBadge(task.urgency).severity" size="small" class="!text-[11px] !py-0 !px-2" />
                                <span class="text-xs px-2 py-0.5 rounded bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-400">
                                    {{ task.source_type === 'system' ? 'Sistem' : 'Pengingat Staf' }}
                                </span>
                                <span v-if="task.status === 'completed'" class="text-xs font-medium text-green-600 flex items-center gap-1"> <i class="pi pi-check"></i> Selesai </span>
                                <span v-else-if="task.status === 'cancelled'" class="text-xs font-medium text-surface-400 flex items-center gap-1"> <i class="pi pi-times"></i> Dibatalkan </span>
                            </div>

                            <!-- Title & Description -->
                            <div class="font-semibold text-surface-900 dark:text-surface-0 text-base">
                                {{ task.title }}
                            </div>
                            <div class="text-sm text-surface-600 dark:text-surface-300 mt-1 whitespace-pre-line">
                                {{ task.description }}
                            </div>

                            <!-- Meta Info (Due Date, Completed info) -->
                            <div class="flex items-center gap-4 text-xs text-surface-400 mt-2">
                                <span v-if="task.due_date" class="flex items-center gap-1 text-amber-600 dark:text-amber-400 font-medium"> <i class="pi pi-calendar"></i> Jatuh Tempo: {{ task.due_date }} </span>
                                <span v-if="task.completed_at" class="flex items-center gap-1 text-green-600 dark:text-green-400"> <i class="pi pi-clock"></i> Diselesaikan pada {{ task.completed_at }} </span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Action Buttons -->
                    <div class="flex items-center gap-2 self-end md:self-center shrink-0">
                        <!-- Direct Action Link (if present) -->
                        <Button v-if="task.action_url" icon="pi pi-external-link" label="Buka Modul" size="small" severity="info" variant="outlined" @click="openActionUrl(task.action_url)" />

                        <!-- System Task Actions -->
                        <template v-if="task.source_type === 'system' && task.status === 'pending'">
                            <Button v-if="canManage" icon="pi pi-check" label="Tandai Selesai" size="small" severity="success" variant="outlined" @click="dismissSystemTask(task)" />
                        </template>

                        <!-- Manual Reminder Actions -->
                        <template v-if="task.source_type === 'manual'">
                            <!-- If pending -->
                            <template v-if="task.status === 'pending'">
                                <Button v-if="canManage" icon="pi pi-check" size="small" severity="success" label="Selesai" @click="markComplete(task)" />
                                <Button v-if="canManage" icon="pi pi-pencil" size="small" severity="secondary" variant="text" v-tooltip="'Edit Catatan'" @click="openEditReminder(task)" />
                                <Button v-if="canManage" icon="pi pi-ban" size="small" severity="warn" variant="text" v-tooltip="'Batalkan Tugas'" @click="cancelReminder(task)" />
                            </template>

                            <!-- Delete Option -->
                            <Button v-if="canManage" icon="pi pi-trash" size="small" severity="danger" variant="text" v-tooltip="'Hapus Permanen'" @click="deleteReminder(task)" />
                        </template>
                    </div>
                </div>
            </div>

            <!-- Pagination Bar -->
            <div v-if="pagination.total > pagination.per_page" class="flex items-center justify-between pt-6 border-t border-surface-200 dark:border-surface-700 mt-4">
                <span class="text-xs text-surface-500">
                    Menampilkan {{ (pagination.current_page - 1) * pagination.per_page + 1 }} - {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} dari {{ pagination.total }} tugas
                </span>
                <div class="flex items-center gap-1">
                    <Button
                        icon="pi pi-chevron-left"
                        size="small"
                        severity="secondary"
                        variant="text"
                        :disabled="pagination.current_page <= 1"
                        @click="
                            () => {
                                pagination.current_page--;
                                loadTasks();
                            }
                        "
                    />
                    <span class="text-sm px-2 font-medium">{{ pagination.current_page }} / {{ pagination.total_pages }}</span>
                    <Button
                        icon="pi pi-chevron-right"
                        size="small"
                        severity="secondary"
                        variant="text"
                        :disabled="pagination.current_page >= pagination.total_pages"
                        @click="
                            () => {
                                pagination.current_page++;
                                loadTasks();
                            }
                        "
                    />
                </div>
            </div>
        </div>

        <!-- Dialog: Buat / Edit Pengingat Manual -->
        <Dialog v-model:visible="reminderDialogVisible" :header="isEditing ? 'Edit Pengingat Operasional' : 'Tambah Pengingat Baru'" :modal="true" :style="{ width: '560px' }" class="p-fluid">
            <div class="flex flex-col gap-4 pt-2">
                <!-- Judul -->
                <div>
                    <label class="block text-sm font-medium text-surface-700 dark:text-surface-200 mb-1"> Judul Tugas / Pengingat <span class="text-red-500">*</span> </label>
                    <InputText v-model="reminderForm.title" placeholder="Contoh: Koordinasi pengumpulan berkas Dapodik ke dinas" class="w-full" autofocus />
                </div>

                <!-- Domain Category & Priority -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 dark:text-surface-200 mb-1"> Kategori Bidang </label>
                        <Select v-model="reminderForm.category" :options="categoryOptions" option-label="label" option-value="value" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 dark:text-surface-200 mb-1"> Tingkat Prioritas </label>
                        <Select v-model="reminderForm.priority" :options="priorityOptions" option-label="label" option-value="value" class="w-full" />
                    </div>
                </div>

                <!-- Jatuh Tempo -->
                <div>
                    <label class="block text-sm font-medium text-surface-700 dark:text-surface-200 mb-1"> Batas Waktu / Jatuh Tempo (Opsional) </label>
                    <input
                        v-model="reminderForm.due_date"
                        type="date"
                        class="w-full p-2.5 rounded-lg border border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-900 text-surface-800 dark:text-surface-100 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    />
                </div>

                <!-- Deskripsi -->
                <div>
                    <label class="block text-sm font-medium text-surface-700 dark:text-surface-200 mb-1"> Uraian Detail / Catatan Tindak Lanjut </label>
                    <Textarea v-model="reminderForm.description" rows="3" placeholder="Tuliskan catatan detail atau hal-hal yang perlu disiapkan..." class="w-full" />
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2 pt-4">
                    <Button label="Batal" severity="secondary" variant="outlined" @click="reminderDialogVisible = false" />
                    <Button label="Simpan Pengingat" icon="pi pi-check" severity="primary" :loading="savingReminder" @click="saveReminder" />
                </div>
            </template>
        </Dialog>
    </div>
</template>
