<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import apiLogin from '@/apiLogin';
import { authStore } from '@/stores/authStore';

const { t, locale } = useI18n();
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
    const loc = locale.value === 'en' ? 'en-GB' : 'id-ID';
    return parsed.toLocaleDateString(loc, { day: '2-digit', month: 'short', year: 'numeric' });
};

const daysLabel = (days) => {
    if (days === null || days === undefined) return t('dashboard.archiveExpiry.noExpiry');
    if (days < 0) {
        const abs = Math.abs(days);
        return abs === 1 ? t('dashboard.archiveExpiry.dayOverdue', { count: abs }) : t('dashboard.archiveExpiry.daysOverdue', { count: abs });
    }
    if (days === 0) return t('dashboard.archiveExpiry.expiresToday');
    return days === 1 ? t('dashboard.archiveExpiry.dayLeft', { count: days }) : t('dashboard.archiveExpiry.daysLeft', { count: days });
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
        error.value = t('dashboard.archiveExpiry.error');
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
            <div class="font-semibold text-xl">{{ t('dashboard.archiveExpiry.title') }}</div>
            <div class="flex items-center gap-2">
                <Tag :value="t('dashboard.archiveExpiry.toReview', { count: totalLabel })" :severity="totalLabel > 0 ? 'warn' : 'success'" />
                <Button icon="pi pi-refresh" size="small" variant="text" :loading="loading" :aria-label="t('dashboard.archiveExpiry.refreshAria')" @click="load" />
            </div>
        </div>

        <div v-if="loading" class="flex items-center gap-3 py-6 text-surface-500">
            <i class="pi pi-spin pi-spinner" aria-hidden="true"></i>
            <span>{{ t('dashboard.archiveExpiry.loading') }}</span>
        </div>
        <div v-else-if="error" class="flex flex-col items-start gap-3 py-4">
            <span class="text-red-500 text-sm">{{ error }}</span>
            <Button icon="pi pi-refresh" :label="t('common.action.retry')" size="small" severity="secondary" variant="outlined" @click="load" />
        </div>
        <template v-else>
            <p class="text-sm text-surface-500 mb-4">{{ t('dashboard.archiveExpiry.description', { days: thresholdDays }) }}</p>

            <div v-if="totalLabel === 0" class="py-3 text-center text-surface-500 text-sm flex items-center justify-center gap-2">
                <i class="pi pi-check-circle text-green-500" aria-hidden="true"></i>
                <span>{{ t('dashboard.archiveExpiry.empty') }}</span>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <section v-if="expired.total > 0">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="block text-muted-color font-medium">{{ t('dashboard.archiveExpiry.expired') }}</span>
                        <Button :label="t('dashboard.archiveExpiry.openList')" icon="pi pi-external-link" size="small" severity="secondary" variant="text" @click="openArchiveList('expired')" />
                    </div>
                    <ul class="p-0 m-0 list-none">
                        <li v-for="item in expired.items" :key="item.id" class="flex items-start gap-3 py-2 border-b border-surface last:border-b-0">
                            <i class="pi pi-exclamation-triangle text-red-500 shrink-0 mt-1" aria-hidden="true"></i>
                            <div class="min-w-0">
                                <button type="button" class="text-left font-medium text-primary hover:underline truncate block w-full" :title="item.title" @click="openArchiveList('expired')">{{ item.title }}</button>
                                <div class="text-xs text-surface-500">{{ item.category_label }} · {{ t('dashboard.archiveExpiry.until', { date: formatDate(item.valid_until) }) }} · {{ daysLabel(item.days_remaining) }}</div>
                            </div>
                        </li>
                    </ul>
                    <div v-if="expired.total > expired.items.length" class="text-xs text-surface-500 mt-2">
                        {{ expired.total - expired.items.length === 1 ? t('dashboard.archiveExpiry.moreExpiredSingle', { count: 1 }) : t('dashboard.archiveExpiry.moreExpiredPlural', { count: expired.total - expired.items.length }) }}
                    </div>
                </section>

                <section v-if="expiringSoon.total > 0">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="block text-muted-color font-medium">{{ t('dashboard.archiveExpiry.expiringSoon') }}</span>
                        <Button :label="t('dashboard.archiveExpiry.openList')" icon="pi pi-external-link" size="small" severity="secondary" variant="text" @click="openArchiveList('expiring_soon')" />
                    </div>
                    <ul class="p-0 m-0 list-none">
                        <li v-for="item in expiringSoon.items" :key="item.id" class="flex items-start gap-3 py-2 border-b border-surface last:border-b-0">
                            <i class="pi pi-clock text-orange-500 shrink-0 mt-1" aria-hidden="true"></i>
                            <div class="min-w-0">
                                <button type="button" class="text-left font-medium text-primary hover:underline truncate block w-full" :title="item.title" @click="openArchiveList('expiring_soon')">{{ item.title }}</button>
                                <div class="text-xs text-surface-500">{{ item.category_label }} · {{ t('dashboard.archiveExpiry.until', { date: formatDate(item.valid_until) }) }} · {{ daysLabel(item.days_remaining) }}</div>
                            </div>
                        </li>
                    </ul>
                    <div v-if="expiringSoon.total > expiringSoon.items.length" class="text-xs text-surface-500 mt-2">
                        {{
                            expiringSoon.total - expiringSoon.items.length === 1
                                ? t('dashboard.archiveExpiry.moreExpiringSingle', { count: 1 })
                                : t('dashboard.archiveExpiry.moreExpiringPlural', { count: expiringSoon.total - expiringSoon.items.length })
                        }}
                    </div>
                </section>
            </div>
        </template>
    </div>
</template>
