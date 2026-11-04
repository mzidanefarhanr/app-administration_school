<script setup>
import { onMounted, ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { authStore } from '@/stores/authStore';
import apiLogin from '@/apiLogin';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Tag from 'primevue/tag';
import Paginator from 'primevue/paginator';

const { t } = useI18n();
const useAuthStore = authStore();

// define state
const userOnline = ref([]);
const loading = ref(false);
const loadError = ref(false);
const searchQuery = ref('');
const first = ref(0);
const rowsPerPage = 10;

// Widget ini memerlukan kemampuan melihat akun. Bila tidak dimiliki,
// widget tidak memuat data sama sekali (tidak memanggil /api/users).
const canViewUsers = computed(() => useAuthStore.can('users.view'));

const getBadge = (user) => {
    const role = (user?.type_user?.name || '').toLowerCase();
    if (role.includes('admin')) return 'success';
    if (role.includes('user')) return 'info';
    if (role.includes('guest')) return 'warn';
    return null;
};

// Filtered users by search query
const filteredUsers = computed(() => {
    const list = userOnline.value || [];
    if (!searchQuery.value || !searchQuery.value.trim()) {
        return list;
    }
    const q = searchQuery.value.toLowerCase().trim();
    return list.filter((user) => {
        const nameMatch = user.name?.toLowerCase().includes(q);
        const usernameMatch = user.username?.toLowerCase().includes(q);
        const roleMatch = user.type_user?.name?.toLowerCase().includes(q);
        return nameMatch || usernameMatch || roleMatch;
    });
});

// Paginated users strictly limited to 10 per page
const paginatedUsers = computed(() => {
    return filteredUsers.value.slice(first.value, first.value + rowsPerPage);
});

// Reset pagination when search query changes
watch(searchQuery, () => {
    first.value = 0;
});

// method fetchDataUsersOnline
const fetchDataUsersOnline = async () => {
    if (!canViewUsers.value) {
        userOnline.value = [];
        return;
    }
    loading.value = true;
    loadError.value = false;
    try {
        const response = await apiLogin.get('/api/users');
        userOnline.value = response.data?.data ?? [];
    } catch {
        userOnline.value = [];
        loadError.value = true;
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchDataUsersOnline();
});
</script>

<template>
    <div v-if="canViewUsers" class="card mb-0 h-full flex flex-col justify-between">
        <div>
            <!-- Header Widget: Judul & Input Pencarian -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-xl text-surface-900 dark:text-surface-0">{{ t('dashboard.onlineUsers.title') }}</span>
                    <Tag :value="`${filteredUsers.length}`" severity="secondary" rounded />
                </div>
                <div class="flex items-center gap-2">
                    <IconField class="w-full sm:w-44">
                        <InputIcon class="pi pi-search text-xs" />
                        <InputText
                            v-model="searchQuery"
                            :placeholder="t('dashboard.onlineUsers.search')"
                            size="small"
                            class="w-full !text-xs !py-1.5"
                        />
                    </IconField>
                    <Button
                        v-if="searchQuery"
                        icon="pi pi-times"
                        size="small"
                        variant="text"
                        severity="secondary"
                        aria-label="Bersihkan pencarian"
                        @click="searchQuery = ''"
                    />
                    <Button
                        icon="pi pi-refresh"
                        size="small"
                        variant="text"
                        severity="secondary"
                        :loading="loading"
                        :aria-label="t('common.action.retry')"
                        @click="fetchDataUsersOnline"
                    />
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-12 text-surface-500 gap-3">
                <i class="pi pi-spin pi-spinner text-lg" aria-hidden="true"></i>
                <span class="text-sm">{{ t('dashboard.onlineUsers.loading') }}</span>
            </div>

            <!-- Error State -->
            <div v-else-if="loadError" class="flex flex-col items-center justify-center py-10 text-center text-red-500">
                <i class="pi pi-exclamation-triangle text-2xl mb-2" aria-hidden="true"></i>
                <span class="text-sm">{{ t('dashboard.onlineUsers.loadError') }}</span>
                <Button :label="t('common.action.retry')" icon="pi pi-refresh" size="small" severity="secondary" variant="outlined" class="mt-3" @click="fetchDataUsersOnline" />
            </div>

            <!-- Empty State -->
            <div v-else-if="!filteredUsers.length" class="flex flex-col items-center justify-center py-12 text-center text-surface-400">
                <i class="pi pi-users text-3xl mb-2 text-surface-300 dark:text-surface-600" aria-hidden="true"></i>
                <span class="text-sm">{{ t('dashboard.onlineUsers.empty') }}</span>
            </div>

            <!-- List Pengguna (Maksimal 10 Baris per Halaman, Tanpa Gambar Profil) -->
            <ul v-else class="m-0 p-0 list-none flex flex-col gap-1 w-full">
                <li
                    v-for="user in paginatedUsers"
                    :key="user.id"
                    class="py-2 px-2.5 hover:bg-surface-100 dark:hover:bg-surface-800/60 rounded-lg transition-colors flex items-center justify-between gap-3"
                >
                    <div class="flex items-center gap-2.5 min-w-0">
                        <!-- Indikator Status Dot (Hijau untuk aktif) -->
                        <span
                            class="w-2.5 h-2.5 rounded-full shrink-0"
                            :class="user.status_active ? 'bg-emerald-500 shadow-sm' : 'bg-surface-300 dark:bg-surface-600'"
                            :title="user.status_active ? 'Aktif' : 'Nonaktif'"
                        ></span>
                        <div class="flex flex-col min-w-0">
                            <span class="font-medium text-sm text-surface-900 dark:text-surface-0 truncate leading-snug">
                                {{ user.name }}
                            </span>
                            <span v-if="user.username" class="text-xs text-muted-color truncate leading-tight">
                                @{{ user.username }}
                            </span>
                        </div>
                    </div>

                    <Tag
                        v-if="user.type_user?.name"
                        :value="user.type_user.name"
                        :severity="getBadge(user)"
                        class="!text-xs shrink-0"
                    />
                </li>
            </ul>
        </div>

        <!-- Pagination (Hanya muncul jika lebih dari 10 pengguna) -->
        <div v-if="filteredUsers.length > rowsPerPage" class="pt-3 mt-2 border-t border-surface flex items-center justify-between text-xs text-muted-color">
            <span>
                {{ first + 1 }} - {{ Math.min(first + rowsPerPage, filteredUsers.length) }} dari {{ filteredUsers.length }}
            </span>
            <Paginator
                v-model:first="first"
                :rows="rowsPerPage"
                :totalRecords="filteredUsers.length"
                template="PrevPageLink PageLinks NextPageLink"
                class="!p-0 !border-0 text-xs custom-dashboard-paginator"
            />
        </div>
    </div>
</template>

<style scoped>
:deep(.custom-dashboard-paginator) {
    background: transparent !important;
}
:deep(.custom-dashboard-paginator .p-paginator-page),
:deep(.custom-dashboard-paginator .p-paginator-prev),
:deep(.custom-dashboard-paginator .p-paginator-next) {
    min-width: 1.75rem !important;
    height: 1.75rem !important;
    font-size: 0.75rem !important;
    padding: 0 !important;
}
</style>
