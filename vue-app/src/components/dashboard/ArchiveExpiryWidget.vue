<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import apiLogin from '@/apiLogin';
import { authStore } from '@/stores/authStore';

const store = authStore();
const router = useRouter();

const tasks = ref(null);
const loading = ref(false);
const error = ref('');

const allowed = computed(() => store.can('archives.view'));
const expiringSoon = computed(() => tasks.value?.expiring_soon ?? { total: 0, items: [] });
const expired = computed(() => tasks.value?.expired ?? { total: 0, items: [] });
const thresholdDays = computed(() => tasks.value?.threshold_days ?? 30);
const totalLabel = computed(() => expiringSoon.value.total + expired.value.total);

const formatDate = (value) => {
    if (!value) return '-';
    const raw = String(value).slice(0, 10);
    const parsed = new Date(`${raw}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) return raw;
    return parsed.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const daysLabel = (days) => {
    if (days === null || days === undefined) return 'No expiry';
    if (days < 0) return `${Math.abs(days)} day${Math.abs(days) === 1 ? '' : 's'} overdue`;
    if (days === 0) return 'Expires today';
    return `${days} day${days === 1 ? '' : 's'} left`;
};

const load = async () => {
    if (!allowed.value || loading.value) return;

    loading.value = true;
    error.value = '';
    try {
        const response = await apiLogin.get('/api/archives/tasks/expiring', { params: { limit: 10 } });
        tasks.value = response.data?.data ?? null;
    } catch {
        tasks.value = null;
        error.value = 'The archive expiry reminder could not be loaded.';
    } finally {
        loading.value = false;
    }
};

const openArchiveList = (validity) => {
    router.push({ path: '/public/archives', query: { validity } });
};

onMounted(async () => {
    if (!store.abilities?.length) await store.fetchIdentity();
    await load();
});
</script>

<template>
    <div v-if="allowed" class="card col-span-12">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
            <div class="font-semibold text-xl">Archive Expiry Reminders</div>
            <div class="flex items-center gap-2">
                <Tag :value="`${totalLabel} to review`" :severity="totalLabel > 0 ? 'warn' : 'success'" />
                <Button icon="pi pi-refresh" size="small" variant="text" :loading="loading" aria-label="Refresh archive expiry reminders" @click="load" />
            </div>
        </div>

        <div v-if="loading" class="flex items-center gap-3 py-6 text-surface-500">
            <i class="pi pi-spin pi-spinner" aria-hidden="true"></i>
            <span>Loading expiry reminders...</span>
        </div>
        <div v-else-if="error" class="flex flex-col items-start gap-3 py-4">
            <span class="text-red-500 text-sm">{{ error }}</span>
            <Button icon="pi pi-refresh" label="Retry" size="small" severity="secondary" variant="outlined" @click="load" />
        </div>
        <template v-else>
            <p class="text-sm text-surface-500 mb-4">Documents whose validity ends within {{ thresholdDays }} days in WIB, or has already passed. Archived documents are excluded.</p>

            <div v-if="totalLabel === 0" class="py-6 text-center text-surface-500 text-sm">No archive documents are expiring or expired.</div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <section v-if="expired.total > 0">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="block text-muted-color font-medium">EXPIRED</span>
                        <Button label="Open list" icon="pi pi-external-link" size="small" severity="secondary" variant="text" @click="openArchiveList('expired')" />
                    </div>
                    <ul class="p-0 m-0 list-none">
                        <li v-for="item in expired.items" :key="item.id" class="flex items-start gap-3 py-2 border-b border-surface last:border-b-0">
                            <i class="pi pi-exclamation-triangle text-red-500 shrink-0 mt-1" aria-hidden="true"></i>
                            <div class="min-w-0">
                                <button type="button" class="text-left font-medium text-primary hover:underline truncate block w-full" :title="item.title" @click="openArchiveList('expired')">{{ item.title }}</button>
                                <div class="text-xs text-surface-500">{{ item.category_label }} · until {{ formatDate(item.valid_until) }} · {{ daysLabel(item.days_remaining) }}</div>
                            </div>
                        </li>
                    </ul>
                    <div v-if="expired.total > expired.items.length" class="text-xs text-surface-500 mt-2">{{ expired.total - expired.items.length }} more expired document{{ expired.total - expired.items.length === 1 ? '' : 's' }} not shown.</div>
                </section>

                <section v-if="expiringSoon.total > 0">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="block text-muted-color font-medium">EXPIRING SOON</span>
                        <Button label="Open list" icon="pi pi-external-link" size="small" severity="secondary" variant="text" @click="openArchiveList('expiring_soon')" />
                    </div>
                    <ul class="p-0 m-0 list-none">
                        <li v-for="item in expiringSoon.items" :key="item.id" class="flex items-start gap-3 py-2 border-b border-surface last:border-b-0">
                            <i class="pi pi-clock text-orange-500 shrink-0 mt-1" aria-hidden="true"></i>
                            <div class="min-w-0">
                                <button type="button" class="text-left font-medium text-primary hover:underline truncate block w-full" :title="item.title" @click="openArchiveList('expiring_soon')">{{ item.title }}</button>
                                <div class="text-xs text-surface-500">{{ item.category_label }} · until {{ formatDate(item.valid_until) }} · {{ daysLabel(item.days_remaining) }}</div>
                            </div>
                        </li>
                    </ul>
                    <div v-if="expiringSoon.total > expiringSoon.items.length" class="text-xs text-surface-500 mt-2">
                        {{ expiringSoon.total - expiringSoon.items.length }} more expiring document{{ expiringSoon.total - expiringSoon.items.length === 1 ? '' : 's' }} not shown.
                    </div>
                </section>
            </div>
        </template>
    </div>
</template>
