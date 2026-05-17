<script setup>
import { useLayout } from '@/layout/composables/layout';
import { onMounted, ref, watch } from 'vue';
import { useToast } from "primevue/usetoast";
import apiLogin from '@/apiLogin';

//define state
const userOnline = ref([]);
const users = ref();
const selectedUser = ref();
const filteredUsers = ref();

const search = (event) => {
    setTimeout(() => {
        // console.log(event.query.toLowerCase());
        if (!event.query.trim().length) {
            filteredUsers.value = [...users.value];
            // console.log('kosong');
        } else {
            filteredUsers.value = userOnline.value.filter((user) => {
                return user.name.toLowerCase().match(event.query.toLowerCase());
                // return user.name.toLowerCase().startsWith(event.query.toLowerCase());
            });
        }
    }, 250);
}

const getBadge = (user) => {
    if (user.type_user.name === 'User') return 'info';
    else if (user.type_user.name === 'Guest') return 'warn';
    else return null;
}
//method fetchDataUsersOnline
const fetchDataUsersOnline = async () => {
    //fetch data
    await apiLogin.get(`/api/users`)
    .then(response => {
        //set response data to state "usersOnline"
        userOnline.value = response.data.data;
    });
}

//function toggleUserVisibility
function toggleUserVisibility() {
    filteredUsers.value.length = 0;
    selectedUser.value = null;
}

//run hook "onMounted"
onMounted(() => {
    //call method "fetchDataUsersOnline"
    fetchDataUsersOnline();
  });
</script>

<template>
    <div class="card sm:justify-center h-full">
        <Menubar>
            <template #start>
                <div class="font-semibold text-xl w-full">List Users Online</div>
            </template>
            <template #end>
                <div class="flex items-center gap-2">
                    <!-- <InputText placeholder="Search" type="text" class="w-32 sm:w-auto" /> -->
                    <AutoComplete v-model="selectedUser" optionLabel="name" placeholder="Search" :suggestions="filteredUsers" @complete="search" class="w-full"/>
                    <!-- <InputIcon v-if="filteredUsers && filteredUsers.length > 0" @click="toggleUserVisibility" class="pi pi-times" /> -->
                    <i v-if="filteredUsers && filteredUsers.length > 0" @click="toggleUserVisibility" class="pi pi-times"></i>
                </div>
            </template>
        </Menubar>
        <ScrollPanel v-if="filteredUsers && filteredUsers.length > 0" style="width: 100%; height: 20rem">
            <ul class="m-0 list-none rounded p-4 flex flex-col gap-2 w-full">
                <li
                    v-for="user in filteredUsers"
                    :key="user.id"
                    class="p-2 hover:bg-emphasis rounded border border-transparent transition-all duration-200 flex items-center justify-content-between"
                >
                    <div class="flex flex-1 items-center gap-2">
                        <OverlayBadge severity="success" v-if="user.status_active">
                            <img :alt="user.name" :src="`https://primefaces.org/cdn/primevue/images/avatar/bernardodominic.png`" class="w-8 h-8" />
                        </OverlayBadge>
                        <img :alt="user.name" :src="`https://primefaces.org/cdn/primevue/images/avatar/bernardodominic.png`" class="w-8 h-8" v-else/>
                        <span class="font-bold">{{ user.name }}</span>
                    </div>
                    <Tag :value="user.type_user.name" :severity="getBadge(user)" />
                </li>
            </ul>

            <ScrollTop target="parent" :threshold="100" icon="pi pi-arrow-up"></ScrollTop>
        </ScrollPanel>
        <ScrollPanel v-else style="width: 100%; height: 20rem">
            <ul class="m-0 list-none rounded p-4 flex flex-col gap-2 w-full">
                <li
                    v-for="user in userOnline"
                    :key="user.id"
                    class="p-2 hover:bg-emphasis rounded border border-transparent transition-all duration-200 flex items-center justify-content-between"
                >
                    <div class="flex flex-1 items-center gap-2">
                        <OverlayBadge severity="success" v-if="user.status_active">
                            <img :alt="user.name" :src="`https://primefaces.org/cdn/primevue/images/avatar/bernardodominic.png`" class="w-8 h-8" />
                        </OverlayBadge>
                        <img :alt="user.name" :src="`https://primefaces.org/cdn/primevue/images/avatar/bernardodominic.png`" class="w-8 h-8" v-else/>
                        <span class="font-bold">{{ user.name }}</span>
                    </div>
                    <Tag :value="user.type_user.name" :severity="getBadge(user)" />
                </li>
            </ul>

            <ScrollTop target="parent" :threshold="100" icon="pi pi-arrow-up"></ScrollTop>
        </ScrollPanel>
    </div>
</template>
