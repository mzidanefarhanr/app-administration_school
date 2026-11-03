<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import apiLogin from '@/apiLogin';

const router = useRouter();

const tasks = ref([]);
const loading = ref(false);
const error = ref('');

const load = async () => {
    loading.value = true;
    error.value = '';
    try {
        const response = await apiLogin.get('/api/dashboard/pending-tasks');
        tasks.value = response.data?.data ?? [];
    } catch {
        error.value = 'Daftar tugas operasional tidak dapat dimuat.';
    } finally {
        loading.value = false;
    }
};

const getDomainIcon = (domain) => {
    switch (domain) {
        case 'letters':
            return 'pi pi-envelope text-blue-500 bg-blue-100 dark:bg-blue-400/10';
        case 'leaves':
            return 'pi pi-calendar-times text-orange-500 bg-orange-100 dark:bg-orange-400/10';
        case 'teaching':
            return 'pi pi-book text-purple-500 bg-purple-100 dark:bg-purple-400/10';
        case 'archives':
            return 'pi pi-folder text-cyan-500 bg-cyan-100 dark:bg-cyan-400/10';
        default:
            return 'pi pi-bell text-surface-500 bg-surface-100 dark:bg-surface-800';
    }
};

const getUrgencySeverity = (urgency) => {
    switch (urgency) {
        case 'high':
            return 'danger';
        case 'warn':
            return 'warn';
        default:
            return 'info';
    }
};

const getUrgencyLabel = (urgency) => {
    switch (urgency) {
        case 'high':
            return 'Mendesak';
        case 'warn':
            return 'Perhatian';
        default:
            return 'Info';
    }
};

const openTask = (task) => {
    if (task?.action_url) {
        router.push(task.action_url);
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
            <div class="flex items-center gap-2">
                <span class="font-semibold text-xl">Tugas & Tindakan Operasional</span>
                <Tag v-if="tasks.length > 0" :value="`${tasks.length}`" severity="warn" rounded />
            </div>
            <Button icon="pi pi-refresh" size="small" variant="text" severity="secondary" :loading="loading" aria-label="Segarkan tugas" @click="load" />
        </div>

        <div v-if="loading" class="flex items-center gap-3 py-8 text-surface-500 justify-center">
            <i class="pi pi-spin pi-spinner text-lg" aria-hidden="true"></i>
            <span class="text-sm">Memeriksa tugas operasional tertunda...</span>
        </div>

        <div v-else-if="error" class="flex flex-col items-center gap-3 py-6 text-center">
            <i class="pi pi-exclamation-triangle text-2xl text-red-500" aria-hidden="true"></i>
            <span class="text-sm text-red-500">{{ error }}</span>
            <Button label="Coba Lagi" icon="pi pi-refresh" size="small" severity="secondary" variant="outlined" @click="load" />
        </div>

        <div v-else-if="tasks.length === 0" class="flex flex-col items-center gap-2 py-8 text-center text-surface-500">
            <div class="w-12 h-12 flex items-center justify-center rounded-full bg-green-100 dark:bg-green-400/10 text-green-600 mb-2">
                <i class="pi pi-check text-xl" aria-hidden="true"></i>
            </div>
            <span class="font-medium text-surface-800 dark:text-surface-100">Semua Beres!</span>
            <span class="text-xs text-surface-400 max-w-sm">Tidak ada surat, izin, atau jurnal mengajar yang memerlukan tindakan segera saat ini.</span>
        </div>

        <ul v-else class="p-0 m-0 list-none divide-y divide-surface">
            <li v-for="task in tasks" :key="task.id" class="py-3 flex items-start justify-between gap-3 hover:bg-surface-50 dark:hover:bg-surface-800/40 rounded-lg px-2 transition-colors cursor-pointer" @click="openTask(task)">
                <div class="flex items-start gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 mt-0.5" :class="getDomainIcon(task.domain)">
                        <i :class="getDomainIcon(task.domain).split(' ')[0]" class="text-base" aria-hidden="true"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-color">{{ task.category }}</span>
                            <Tag :value="getUrgencyLabel(task.urgency)" :severity="getUrgencySeverity(task.urgency)" size="small" class="!text-[10px] !py-0 !px-1.5" />
                        </div>
                        <div class="font-medium text-surface-900 dark:text-surface-0 text-sm truncate" :title="task.title">
                            {{ task.title }}
                        </div>
                        <div class="text-xs text-surface-500 mt-0.5 truncate">
                            {{ task.description }}
                            <span v-if="task.date" class="text-surface-400"> · {{ task.date }}</span>
                        </div>
                    </div>
                </div>

                <Button icon="pi pi-arrow-right" size="small" variant="text" severity="secondary" class="shrink-0 self-center" aria-label="Tindak Lanjuti" @click.stop="openTask(task)" />
            </li>
        </ul>

        <div class="mt-4 pt-3 border-t border-surface-200 dark:border-surface-700 flex justify-end">
            <Button label="Buka Pusat Tugas & Pengingat" icon="pi pi-arrow-right" icon-pos="right" size="small" variant="text" @click="router.push('/public/tasks')" />
        </div>
    </div>
</template>
