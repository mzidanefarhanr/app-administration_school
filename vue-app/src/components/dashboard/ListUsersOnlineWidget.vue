<script setup>
import { onMounted, ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { authStore } from '@/stores/authStore';
import apiLogin from '@/apiLogin';

const { t } = useI18n();
const useAuthStore = authStore();

//define state
const userOnline = ref([]);
const users = ref();
const selectedUser = ref();
const filteredUsers = ref();
const loading = ref(false);
const loadError = ref(false);

// Widget ini memerlukan kemampuan melihat akun. Bila tidak dimiliki,
// widget tidak memuat data sama sekali (tidak memanggil /api/users).
const canViewUsers = computed(() => useAuthStore.can('users.view'));

const search = (event) => {
    setTimeout(() => {
        if (!event.query.trim().length) {
            filteredUsers.value = [...(users.value || [])];
        } else {
            filteredUsers.value = userOnline.value.filter((user) => {
                return user.name.toLowerCase().match(event.query.toLowerCase());
            });
        }
    }, 250);
};

const getBadge = (user) => {
    if (user?.type_user?.name === 'User') return 'info';
    else if (user?.type_user?.name === 'Guest') return 'warn';
    else return null;
};

//method fetchDataUsersOnline
const fetchDataUsersOnline = async () => {
    if (!canViewUsers.value) {
        // Tanpa izin: jangan panggil endpoint administratif.
        userOnline.value = [];
        return;
    }
    loading.value = true;
    loadError.value = false;
    try {
        const response = await apiLogin.get('/api/users');
        userOnline.value = response.data?.data ?? [];
    } catch {
        // Tangani kegagalan tanpa unhandled promise; jangan menghapus identitas sesi.
        userOnline.value = [];
        loadError.value = true;
    } finally {
        loading.value = false;
    }
};

//function toggleUserVisibility
function toggleUserVisibility() {
    filteredUsers.value.length = 0;
    selectedUser.value = null;
}

//run hook "onMounted"
onMounted(() => {
    fetchDataUsersOnline();
});
</script>

<template>
    <div v-if="canViewUsers" class="card sm:justify-center h-full">
        <Menubar>
            <template #start>
                <div class="font-semibold text-xl w-full">{{ t('dashboard.onlineUsers.title') }}</div>
            </template>
            <template #end>
                <div class="flex items-center gap-2">
                    <AutoComplete v-model="selectedUser" optionLabel="name" :placeholder="t('dashboard.onlineUsers.search')" :suggestions="filteredUsers" @complete="search" class="w-full" />
                    <i v-if="filteredUsers && filteredUsers.length > 0" @click="toggleUserVisibility" class="pi pi-times"></i>
                </div>
            </template>
        </Menubar>

        <div v-if="loading" class="p-4 text-surface-500">{{ t('dashboard.onlineUsers.loading') }}</div>

        <div v-else-if="loadError" class="p-4 text-red-500">
            {{ t('dashboard.onlineUsers.loadError') }}
            <Button :label="t('common.action.retry')" text size="small" @click="fetchDataUsersOnline" />
        </div>

        <ScrollPanel v-else-if="filteredUsers && filteredUsers.length > 0" style="width: 100%; height: 20rem">
            <ul class="m-0 list-none rounded p-4 flex flex-col gap-2 w-full">
                <li v-for="user in filteredUsers" :key="user.id" class="p-2 hover:bg-emphasis rounded border border-transparent transition-all duration-200 flex items-center justify-content-between">
                    <div class="flex flex-1 items-center gap-2">
                        <OverlayBadge severity="success" v-if="user.status_active">
                            <img :alt="user.name" :src="`https://primefaces.org/cdn/primevue/images/avatar/bernardodominic.png`" class="w-8 h-8" />
                        </OverlayBadge>
                        <img :alt="user.name" :src="`https://primefaces.org/cdn/primevue/images/avatar/bernardodominic.png`" class="w-8 h-8" v-else />
                        <span class="font-bold">{{ user.name }}</span>
                    </div>
                    <Tag :value="user.type_user ? user.type_user.name : ''" :severity="getBadge(user)" />
                </li>
            </ul>

            <ScrollTop target="parent" :threshold="100" icon="pi pi-arrow-up"></ScrollTop>
        </ScrollPanel>

        <ScrollPanel v-else style="width: 100%; height: 20rem">
            <ul class="m-0 list-none rounded p-4 flex flex-col gap-2 w-full">
                <li v-for="user in userOnline" :key="user.id" class="p-2 hover:bg-emphasis rounded border border-transparent transition-all duration-200 flex items-center justify-content-between">
                    <div class="flex flex-1 items-center gap-2">
                        <OverlayBadge severity="success" v-if="user.status_active">
                            <img :alt="user.name" :src="`https://primefaces.org/cdn/primevue/images/avatar/bernardodominic.png`" class="w-8 h-8" />
                        </OverlayBadge>
                        <img :alt="user.name" :src="`https://primefaces.org/cdn/primevue/images/avatar/bernardodominic.png`" class="w-8 h-8" v-else />
                        <span class="font-bold">{{ user.name }}</span>
                    </div>
                    <Tag :value="user.type_user ? user.type_user.name : ''" :severity="getBadge(user)" />
                </li>
                <li v-if="!userOnline.length" class="p-2 text-surface-500">{{ t('dashboard.onlineUsers.empty') }}</li>
            </ul>

            <ScrollTop target="parent" :threshold="100" icon="pi pi-arrow-up"></ScrollTop>
        </ScrollPanel>
    </div>
</template>
