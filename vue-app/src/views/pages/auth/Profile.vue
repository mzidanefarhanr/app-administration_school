<script setup>
import { ref, reactive, onMounted } from 'vue';
import Button from 'primevue/button';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import { authStore } from '@/stores/authStore';
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import apiLogin from '@/apiLogin';
import gifSuccess from '@/assets/gif/success.gif';
import { getChangeDetail } from '@/utils/diff';

const confirm = useConfirm();
const toast = useToast();
const router = useRouter();
const useAuthStore = authStore();
const saveUser = ref([]);
const saveCompany = ref([]);
const inputTypePassword_old = ref('');
const inputTypePassword_new = ref('');
const inputTypePassword_new_confirm = ref('');
const inputIconPassword_old = ref('');
const inputIconPassword_new = ref('');
const inputIconPassword_new_confirm = ref('');
const showPassword_old = ref(false);
const showPassword_new = ref(false);
const showPassword_new_confirm = ref(false);
const dropdownItems = ref([
    { name: 'Option 1', code: 'Option 1' },
    { name: 'Option 2', code: 'Option 2' },
    { name: 'Option 3', code: 'Option 3' }
]);
// Fungsi untuk menginisialisasi data default
const getDefaultFormData = () => ({
    username: '',
    password_old: '',
    password_new: '',
    password_new_confirm: '',
});
// const form = ref({
//     username: '',
//     password_old: '',
//     password_new: '',
//     password_new_confirm: '',
// });
const form = reactive(getDefaultFormData());
const _method= "PATCH";

const visibleStaticBackdropWarning = ref(false);
const validContinue = ref(false);
var titleWarning = ref('');
var fieldWarning = ref('');
const visibleStaticBackdropDanger = ref(false);
var titleDanger = ref('');
var fieldDanger = ref('');
const visibleStaticBackdropSuccess = ref(false);
var titleSuccess = ref('');
var fieldSuccess = ref('');
const visibleStaticBackdropWaiting = ref(false);
var titleWaiting = ref('');
var fieldWaiting = ref('');

//function visibleStaticBackdropWarnings
function visibleStaticBackdropWarnings() {
    visibleStaticBackdropWarning.value = !visibleStaticBackdropWarning.value;
}

//function visibleStaticBackdropDangers
function visibleStaticBackdropDangers() {
    visibleStaticBackdropDanger.value = !visibleStaticBackdropDanger.value;
}

//function visibleStaticBackdropWaitings
function visibleStaticBackdropWaitings() {
    visibleStaticBackdropWaiting.value = !visibleStaticBackdropWaiting.value;
}

//function visibleStaticBackdropSuccesss
function visibleStaticBackdropSuccesss() {
    visibleStaticBackdropSuccess.value = !visibleStaticBackdropSuccess.value;
}
//function saveValidContinue
function saveValidContinue() {
    validContinue.value = !validContinue.value;
}

const dropdownItem = ref(null);

//function togglePassword_oldVisibility
function togglePassword_oldVisibility() {
    showPassword_old.value = !showPassword_old.value;
    // console.log(showPassword_old.value);
    fetchValidate();
}
//function togglePassword_newVisibility
function togglePassword_newVisibility() {
    showPassword_new.value = !showPassword_new.value;
    // console.log(showPassword_new.value);
    fetchValidate();
}
//function togglePassword_new_confirmVisibility
function togglePassword_new_confirmVisibility() {
    showPassword_new_confirm.value = !showPassword_new_confirm.value;
    // console.log(showPassword_new_confirm.value);
    fetchValidate();
}
const fetchValidate = async () => {
    if (showPassword_old.value == true) {
        inputTypePassword_old.value = 'text';
        inputIconPassword_old.value = 'pi pi-eye-slash !text-black/70';
    } else {
        inputTypePassword_old.value = 'password';
        inputIconPassword_old.value = 'pi pi-eye !text-black/70';
    }
    if (showPassword_new.value == true) {
        inputTypePassword_new.value = 'text';
        inputIconPassword_new.value = 'pi pi-eye-slash !text-black/70';
    } else {
        inputTypePassword_new.value = 'password';
        inputIconPassword_new.value = 'pi pi-eye !text-black/70';
    }
    if (showPassword_new_confirm.value == true) {
        inputTypePassword_new_confirm.value = 'text';
        inputIconPassword_new_confirm.value = 'pi pi-eye-slash !text-black/70';
    } else {
        inputTypePassword_new_confirm.value = 'password';
        inputIconPassword_new_confirm.value = 'pi pi-eye !text-black/70';
    }
};

//method fetchDataUserRole
const fetchDataUserRole = async () => {
    await useAuthStore.getUser();
    // console.log(useAuthStore.AllUserAssign);
    saveUser.value = useAuthStore.AllUserAssign;
    // saveCompany.value = '' + saveUser.value.company;
    // console.log(saveUser.value);
    // console.log(saveUser.value.status_user);
};

const submitNewPassword = async (event) => {
    //
    if (!form.password_old || !form.password_new || !form.password_new_confirm) {
        titleDanger = 'GAGAL!!';
        fieldDanger = 'Password Harus Terisi!!';
        visibleStaticBackdropDangers();
    } else if (form.password_new != form.password_new_confirm) {
        titleDanger = 'GAGAL!!';
        fieldDanger = 'Password Baru tidak sama!!';
        visibleStaticBackdropDangers();
    } else if (form.password_old == form.password_new_confirm) {
        titleDanger = 'GAGAL!!';
        fieldDanger = 'Password Baru tidak boleh sama dengan password lama!!';
        visibleStaticBackdropDangers();
    } else {
        confirm.require({
            group: 'headless',
            header: 'Anda yakin ingin mengubah Password?',
            message: 'Klik Simpan untuk memproses.',
            accept: () => {
                visibleStaticBackdropWaitings();
                fetchDataNewPassword();
            },
            reject: () => {
                toast.add({ severity: 'error', summary: 'Batal', detail: 'Berhasil dibatalkan', life: 3000 });
            }
        });

    }
}
//method fetchDataNewPassword
const fetchDataNewPassword = async () => {
    await apiLogin.post(`/api/users/${saveUser.value.id}`, {
        name              : saveUser.value.name,
        email             : saveUser.value.email,
        username          : saveUser.value.username,
        nik               : saveUser.value.nik,
        password          : form.password_old,
        password_new      : form.password_new_confirm,
        type_user_id      : saveUser.value.type_user_id,
        status_user_id    : saveUser.value.status_user_id,
        _method: _method,
    }).then(response => {
        if (response.data.success === true) {
            console.log("Sukses")
            visibleStaticBackdropWaitings();
            visibleStaticBackdropSuccesss();
            titleSuccess = 'Success!!';
            fieldSuccess = 'Password Baru Berhasil Dibuat!';
            setTimeout(function () {
                visibleStaticBackdropSuccesss();
                fetchDataUserRole();
                Object.assign(form, getDefaultFormData());
            }, 2000);
        }
        // console.log(response.data.message);
        // console.log(response.data);
    }).catch(error => {
        console.error(error);
        if (error.response.status === 422) {
            // alert('"These credentials do not match our records."');
            // getMessage.value = error.response.data.message;
            visibleStaticBackdropWaitings();
            titleDanger = 'GAGAL!!';
            fieldDanger = 'Password tidak tervalidasi, silahkan masukkan data dengan benar!!';
            visibleStaticBackdropDangers();
        } else {
            // alert('An error occurred');
            // getMessage.value = error.response.data.message;
            visibleStaticBackdropWaitings();
            titleDanger = 'GAGAL!!';
            fieldDanger = error.response.data.message;
            visibleStaticBackdropDangers();
        }
    });
}

onMounted(() => {
    fetchDataUserRole();
    fetchValidate();
});
</script>
<template>
    <Fluid>
        <div class="bg-surface-0 dark:bg-surface-950 px-6 py-8 md:px-12 lg:px-20">
            <div class="flex items-center flex-col lg:flex-row lg:justify-between">
                <div class="flex items-start flex-col md:flex-row gap-8">
                    <img src="https://fqjltiegiezfetthbags.supabase.co/storage/v1/object/public/block.images/blocks/pageheading/kathryn.png" class="w-[6.42rem] h-[6.42rem]" />
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center">
                            <span class="text-surface-900 dark:text-surface-0 font-bold text-3xl">{{ saveUser.name }}</span>
                        </div>
                        <div class="flex items-center flex-wrap gap-8">
                            <div>
                                <span class="text-surface-500 dark:text-surface-300">Followers</span>
                                <div class="text-surface-700 dark:text-surface-100 mt-1 text-sm font-semibold">333</div>
                            </div>
                            <div>
                                <span class="text-surface-500 dark:text-surface-300">Projects</span>
                                <div class="text-surface-700 dark:text-surface-100 mt-1 text-sm font-semibold">26</div>
                            </div>
                            <div>
                                <span class="text-surface-500 dark:text-surface-300">Collections</span>
                                <div class="text-surface-700 dark:text-surface-100 mt-1 text-sm font-semibold">17</div>
                            </div>
                            <div>
                                <span class="text-surface-500 dark:text-surface-300">Shots</span>
                                <div class="text-surface-700 dark:text-surface-100 mt-1 text-sm font-semibold">130</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-6 lg:mt-0 flex items-center gap-4">
                    <Button icon="pi pi-bookmark" rounded severity="secondary" />
                    <Button icon="pi pi-heart" rounded severity="secondary" />
                    <Button icon="pi pi-list" rounded severity="secondary" />
                </div>
            </div>
        </div>

        <div class="flex flex-col md:flex-row gap-8 mt-8">
            <div class="md:w-1/2">
                <div class="card flex flex-col gap-4">
                    <div class="font-semibold text-xl">Vertical</div>
                    <div class="flex flex-col gap-2">
                        <label for="name1">Name</label>
                        <InputText id="name1" type="text" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="email1">Email</label>
                        <InputText id="email1" type="text" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="age1">Age</label>
                        <InputText id="age1" type="text" />
                    </div>
                </div>
            </div>
            <div class="md:w-1/2">
                <form @submit.prevent="submitNewPassword">
                    <div class="card flex flex-col gap-4">
                        <div class="font-semibold text-xl">Ubah Password</div>
                        <div class="grid grid-cols-12 gap-2">
                            <label for="password_old" class="flex items-center col-span-12 mb-2 md:col-span-6 md:mb-0">Password Lama</label>
                            <div class="col-span-12 md:col-span-6">
                                <IconField>
                                    <FloatLabel variant="on">
                                        <InputText :type="inputTypePassword_old" id="password_old" type="text" v-model="form.password_old" />
                                        <InputIcon @click="togglePassword_oldVisibility" :class="inputIconPassword_old" />
                                    </FloatLabel>
                                </IconField>
                            </div>
                        </div>
                        <div class="grid grid-cols-12 gap-2">
                            <label for="password_new" class="flex items-center col-span-12 mb-2 md:col-span-6 md:mb-0">Password Baru</label>
                            <div class="col-span-12 md:col-span-6">
                                <IconField>
                                    <FloatLabel variant="on">
                                        <InputText :type="inputTypePassword_new" id="password_new" type="text" v-model="form.password_new" />
                                        <InputIcon @click="togglePassword_newVisibility" :class="inputIconPassword_new" />
                                    </FloatLabel>
                                </IconField>
                            </div>
                        </div>
                        <div class="grid grid-cols-12 gap-2">
                            <label for="password_new_confirm" class="flex items-center col-span-12 mb-2 md:col-span-6 md:mb-0">Password Baru Konfirmasi</label>
                            <div class="col-span-12 md:col-span-6">
                                <IconField>
                                    <FloatLabel variant="on">
                                        <InputText :type="inputTypePassword_new_confirm" id="password_new_confirm" type="text" v-model="form.password_new_confirm" :invalid="form.password_new != form.password_new_confirm" />
                                        <InputIcon @click="togglePassword_new_confirmVisibility" :class="inputIconPassword_new_confirm" />
                                        <!-- <Message v-if="form.password_new != form.password_new_confirm" severity="error" size="small" variant="simple">Password Baru Tidak Sama!!</Message> -->
                                    </FloatLabel>
                                </IconField>
                            </div>
                        </div>
                        <div class="grid grid-cols-12 gap-2">
                            <label for="password_new_confirm" class="flex items-center col-span-12 mb-2 md:col-span-6 md:mb-0"></label>
                            <div class="col-span-12 md:col-span-6">
                                <IconField>
                                    <FloatLabel variant="on">
                                        <Message v-if="form.password_new != form.password_new_confirm" severity="error" size="small" variant="simple">Password Baru Tidak Sama!!</Message>
                                    </FloatLabel>
                                </IconField>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <Button class="md:w-1/6 flex items-center" type="submit" label="Simpan" :fluid="false" />
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="flex mt-8">
            <div class="card flex flex-col gap-4 w-full">
                <div class="font-semibold text-xl">Advanced</div>
                <div class="flex flex-col md:flex-row gap-4">
                    <div class="flex flex-wrap gap-2 w-full">
                        <label for="firstname2">Firstname</label>
                        <InputText id="firstname2" type="text" />
                    </div>
                    <div class="flex flex-wrap gap-2 w-full">
                        <label for="lastname2">Lastname</label>
                        <InputText id="lastname2" type="text" />
                    </div>
                </div>

                <div class="flex flex-wrap">
                    <label for="address">Address</label>
                    <Textarea id="address" rows="4" />
                </div>

                <div class="flex flex-col md:flex-row gap-4">
                    <div class="flex flex-wrap gap-2 w-full">
                        <label for="state">State</label>
                        <Select id="state" v-model="dropdownItem" :options="dropdownItems" optionLabel="name" placeholder="Select One" class="w-full"></Select>
                    </div>
                    <div class="flex flex-wrap gap-2 w-full">
                        <label for="zip">Zip</label>
                        <InputText id="zip" type="text" />
                    </div>
                </div>
            </div>
        </div>
    </Fluid>

    <!-- <Button label="Danger" @click="visibleStaticBackdropWarnings" /> -->
    <Dialog v-model:visible="visibleStaticBackdropWarning" modal header="Warning!!" :style="{ width: '25rem' }">
        <div class="text-center justify-content-center align-items-center">
            <p>Data yang anda masukkan sudah benar?</p>
        </div>
    </Dialog>
    <ConfirmDialog group="headless">
        <template #container="{ message, acceptCallback, rejectCallback }">
            <div class="flex flex-col items-center p-8 bg-surface-0 dark:bg-surface-900 rounded">
                <div class="rounded-full bg-primary text-primary-contrast inline-flex justify-center items-center h-24 w-24 -mt-20">
                    <i class="pi pi-question !text-4xl"></i>
                </div>
                <span class="font-bold text-2xl block mb-2 mt-6">{{ message.header }}</span>
                <p class="mb-0">{{ message.message }}</p>
                <div class="flex items-center gap-2 mt-6">
                    <Button label="Simpan" @click="acceptCallback"></Button>
                    <Button label="Batal" outlined @click="rejectCallback"></Button>
                </div>
            </div>
        </template>
    </ConfirmDialog>

    <!-- <Button label="Danger" @click="visibleStaticBackdropDangers" /> -->
    <Dialog v-model:visible="visibleStaticBackdropDanger" modal :header="titleDanger" style=" width: 25rem; color: red;">
        <div style="color: black;">
            {{ fieldDanger }}
        </div>
    </Dialog>

    <!-- <Button label="Success" @click="visibleStaticBackdropSuccesss" /> -->
    <Dialog v-model:visible="visibleStaticBackdropSuccess" modal :header="titleSuccess">
        <div>{{ fieldSuccess }} <Image fluid :src="gifSuccess" width="55" height="55" /></div>
    </Dialog>

    <!-- <Button label="Waiting" @click="visibleStaticBackdropWaitings" /> -->
    <Dialog v-model:visible="visibleStaticBackdropWaiting" modal header="Silahkan menunggu data anda diproses!">
        <div class="text-center justify-content-center align-items-center">
            <i class="pi pi-spin pi-spinner" style="font-size: 2rem; color: green"></i>
            <p class="text-success" style="font-size: medium">Loading...</p>
        </div>
    </Dialog>
</template>
