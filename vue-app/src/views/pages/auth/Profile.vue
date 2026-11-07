<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import FloatLabel from 'primevue/floatlabel';
import Message from 'primevue/message';
import Dialog from 'primevue/dialog';
import ConfirmDialog from 'primevue/confirmdialog';
import Image from 'primevue/image';
import { authStore } from '@/stores/authStore';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import apiLogin from '@/apiLogin';
import gifSuccess from '@/assets/gif/success.gif';

const { t } = useI18n();
const confirm = useConfirm();
const toast = useToast();
const useAuthStore = authStore();

const saveUser = ref({});
const inputTypePassword_old = ref('password');
const inputTypePassword_new = ref('password');
const inputTypePassword_new_confirm = ref('password');
const inputIconPassword_old = ref('pi pi-eye !text-black/70');
const inputIconPassword_new = ref('pi pi-eye !text-black/70');
const inputIconPassword_new_confirm = ref('pi pi-eye !text-black/70');
const showPassword_old = ref(false);
const showPassword_new = ref(false);
const showPassword_new_confirm = ref(false);

const getDefaultFormData = () => ({
    password_old: '',
    password_new: '',
    password_new_confirm: ''
});
const form = reactive(getDefaultFormData());
const _method = 'PATCH';

const visibleStaticBackdropDanger = ref(false);
const titleDanger = ref('');
const fieldDanger = ref('');
const visibleStaticBackdropSuccess = ref(false);
const titleSuccess = ref('');
const fieldSuccess = ref('');
const visibleStaticBackdropWaiting = ref(false);

function togglePassword_oldVisibility() {
    showPassword_old.value = !showPassword_old.value;
    updatePasswordInputs();
}

function togglePassword_newVisibility() {
    showPassword_new.value = !showPassword_new.value;
    updatePasswordInputs();
}

function togglePassword_new_confirmVisibility() {
    showPassword_new_confirm.value = !showPassword_new_confirm.value;
    updatePasswordInputs();
}

const updatePasswordInputs = () => {
    inputTypePassword_old.value = showPassword_old.value ? 'text' : 'password';
    inputIconPassword_old.value = showPassword_old.value ? 'pi pi-eye-slash !text-black/70' : 'pi pi-eye !text-black/70';

    inputTypePassword_new.value = showPassword_new.value ? 'text' : 'password';
    inputIconPassword_new.value = showPassword_new.value ? 'pi pi-eye-slash !text-black/70' : 'pi pi-eye !text-black/70';

    inputTypePassword_new_confirm.value = showPassword_new_confirm.value ? 'text' : 'password';
    inputIconPassword_new_confirm.value = showPassword_new_confirm.value ? 'pi pi-eye-slash !text-black/70' : 'pi pi-eye !text-black/70';
};

const userInitials = computed(() => {
    if (!saveUser.value?.name) return 'U';
    const parts = saveUser.value.name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
});

const userRoles = computed(() => {
    if (!saveUser.value?.roles) return [];
    return saveUser.value.roles.map((r) => (typeof r === 'string' ? r : r.name || r.slug));
});

const linkedEmployee = computed(() => saveUser.value?.employee ?? null);

const isSystemAdmin = computed(() => {
    return saveUser.value?.type_user_id === 1 || userRoles.value.some((r) => String(r).toLowerCase().includes('admin'));
});

const fetchDataUserRole = async () => {
    const data = await useAuthStore.fetchSelfProfile();
    saveUser.value = data || {};
};

const submitNewPassword = async () => {
    if (!form.password_old || !form.password_new || !form.password_new_confirm) {
        titleDanger.value = t('profile.dialog.failedTitle');
        fieldDanger.value = t('profile.dialog.requiredFields');
        visibleStaticBackdropDanger.value = true;
    } else if (form.password_new.length < 12) {
        titleDanger.value = t('profile.dialog.failedTitle');
        fieldDanger.value = t('profile.dialog.minLength');
        visibleStaticBackdropDanger.value = true;
    } else if (form.password_new !== form.password_new_confirm) {
        titleDanger.value = t('profile.dialog.failedTitle');
        fieldDanger.value = t('profile.dialog.mismatch');
        visibleStaticBackdropDanger.value = true;
    } else if (form.password_old === form.password_new) {
        titleDanger.value = t('profile.dialog.failedTitle');
        fieldDanger.value = t('profile.dialog.sameAsOld');
        visibleStaticBackdropDanger.value = true;
    } else {
        confirm.require({
            group: 'headless',
            header: t('profile.dialog.confirmHeader'),
            message: t('profile.dialog.confirmMessage'),
            accept: () => {
                visibleStaticBackdropWaiting.value = true;
                fetchDataNewPassword();
            },
            reject: () => {
                toast.add({
                    severity: 'info',
                    summary: t('profile.dialog.cancelledSummary'),
                    detail: t('profile.dialog.cancelledDetail'),
                    life: 3000
                });
            }
        });
    }
};

const fetchDataNewPassword = async () => {
    await apiLogin
        .post(`/api/users/${saveUser.value.id}`, {
            name: saveUser.value.name,
            email: saveUser.value.email,
            username: saveUser.value.username,
            nik: saveUser.value.nik,
            password: form.password_old,
            password_new: form.password_new_confirm,
            type_user_id: saveUser.value.type_user_id,
            status_user_id: saveUser.value.status_user_id,
            _method: _method
        })
        .then((response) => {
            visibleStaticBackdropWaiting.value = false;
            if (response.data.success === true) {
                visibleStaticBackdropSuccess.value = true;
                titleSuccess.value = t('profile.dialog.successTitle');
                fieldSuccess.value = t('profile.dialog.successMessage');
                setTimeout(() => {
                    visibleStaticBackdropSuccess.value = false;
                    fetchDataUserRole();
                    Object.assign(form, getDefaultFormData());
                }, 2000);
            }
        })
        .catch((error) => {
            console.error(error);
            visibleStaticBackdropWaiting.value = false;
            titleDanger.value = t('profile.dialog.failedTitle');
            if (error.response?.status === 422) {
                fieldDanger.value = error.response?.data?.message || t('profile.dialog.validationError');
            } else {
                fieldDanger.value = error.response?.data?.message || t('profile.dialog.unexpectedError');
            }
            visibleStaticBackdropDanger.value = true;
        });
};

onMounted(() => {
    fetchDataUserRole();
    updatePasswordInputs();
});
</script>

<template>
    <Fluid>
        <!-- Header Banner Profil -->
        <div class="card mb-6">
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6">
                <!-- Avatar Inisial Modern -->
                <div class="w-24 h-24 rounded-full bg-primary-600 text-white flex items-center justify-center font-bold text-3xl shadow-md border-4 border-white dark:border-surface-800">
                    {{ userInitials }}
                </div>

                <!-- Info Ringkas Pengguna -->
                <div class="flex-1 text-center sm:text-left">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 mb-2">
                        <h2 class="text-2xl font-bold text-surface-900 dark:text-surface-0 m-0">{{ saveUser.name || 'Memuat...' }}</h2>
                        <span class="text-sm text-surface-500 font-mono">@{{ saveUser.username || '-' }}</span>
                    </div>

                    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 mb-3">
                        <Tag :value="saveUser.type_user?.name || (isSystemAdmin ? 'Admin' : 'User')" severity="info" class="text-xs" />
                        <Tag v-for="role in userRoles" :key="role" :value="role" severity="primary" class="text-xs" />
                        <Tag :value="saveUser.status_active ? t('profile.accountInfo.active') : t('profile.accountInfo.inactive')" :severity="saveUser.status_active ? 'success' : 'danger'" class="text-xs" />
                    </div>

                    <p class="text-sm text-surface-600 dark:text-surface-400 m-0">
                        <i class="pi pi-envelope mr-1"></i> {{ saveUser.email || '-' }}
                        <span class="mx-2">•</span>
                        <i class="pi pi-id-card mr-1"></i> NIK: {{ saveUser.nik || '-' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Konten 2 Kolom: Detail Akun / Kepegawaian & Ganti Kata Sandi -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Kolom Kiri: Informasi Profil & Kepegawaian Terhubung -->
            <div class="lg:col-span-7 flex flex-col gap-6">
                <!-- Kartu Detail Akun -->
                <div class="card">
                    <div class="flex items-center gap-3 mb-4 pb-3 border-b border-surface-200 dark:border-surface-800">
                        <i class="pi pi-user text-primary text-xl"></i>
                        <div>
                            <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-0 m-0">{{ t('profile.accountInfo.title') }}</h3>
                            <p class="text-xs text-surface-500 m-0">{{ t('profile.accountInfo.subtitle') }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <span class="text-xs font-semibold text-surface-500 uppercase tracking-wider block mb-1">{{ t('profile.accountInfo.name') }}</span>
                            <div class="p-2.5 bg-surface-50 dark:bg-surface-900 rounded border border-surface-200 dark:border-surface-700 text-sm font-medium">
                                {{ saveUser.name || '-' }}
                            </div>
                        </div>

                        <div>
                            <span class="text-xs font-semibold text-surface-500 uppercase tracking-wider block mb-1">{{ t('profile.accountInfo.username') }}</span>
                            <div class="p-2.5 bg-surface-50 dark:bg-surface-900 rounded border border-surface-200 dark:border-surface-700 text-sm font-mono font-medium">
                                {{ saveUser.username || '-' }}
                            </div>
                        </div>

                        <div>
                            <span class="text-xs font-semibold text-surface-500 uppercase tracking-wider block mb-1">{{ t('profile.accountInfo.email') }}</span>
                            <div class="p-2.5 bg-surface-50 dark:bg-surface-900 rounded border border-surface-200 dark:border-surface-700 text-sm font-medium">
                                {{ saveUser.email || '-' }}
                            </div>
                        </div>

                        <div>
                            <span class="text-xs font-semibold text-surface-500 uppercase tracking-wider block mb-1">{{ t('profile.accountInfo.nik') }}</span>
                            <div class="p-2.5 bg-surface-50 dark:bg-surface-900 rounded border border-surface-200 dark:border-surface-700 text-sm font-mono font-medium">
                                {{ saveUser.nik || '-' }}
                            </div>
                        </div>

                        <div>
                            <span class="text-xs font-semibold text-surface-500 uppercase tracking-wider block mb-1">{{ t('profile.accountInfo.typeUser') }}</span>
                            <div class="p-2.5 bg-surface-50 dark:bg-surface-900 rounded border border-surface-200 dark:border-surface-700 text-sm font-medium">
                                {{ saveUser.type_user?.name || (isSystemAdmin ? 'Admin' : 'User') }}
                            </div>
                        </div>

                        <div>
                            <span class="text-xs font-semibold text-surface-500 uppercase tracking-wider block mb-1">{{ t('profile.accountInfo.status') }}</span>
                            <div class="p-2.5 bg-surface-50 dark:bg-surface-900 rounded border border-surface-200 dark:border-surface-700 text-sm font-medium flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full" :class="saveUser.status_active ? 'bg-green-500' : 'bg-red-500'"></span>
                                {{ saveUser.status_active ? t('profile.accountInfo.active') : t('profile.accountInfo.inactive') }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kartu Data Kepegawaian Terhubung -->
                <div class="card">
                    <div class="flex items-center gap-3 mb-4 pb-3 border-b border-surface-200 dark:border-surface-800">
                        <i class="pi pi-id-card text-primary text-xl"></i>
                        <div>
                            <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-0 m-0">{{ t('profile.employeeInfo.title') }}</h3>
                            <p class="text-xs text-surface-500 m-0">{{ t('profile.employeeInfo.subtitle') }}</p>
                        </div>
                    </div>

                    <div v-if="linkedEmployee" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <span class="text-xs font-semibold text-surface-500 uppercase tracking-wider block mb-1">{{ t('profile.employeeInfo.type') }}</span>
                            <div class="p-2.5 bg-surface-50 dark:bg-surface-900 rounded border border-surface-200 dark:border-surface-700 text-sm font-medium">
                                <Tag :value="linkedEmployee.employee_type?.name || 'Pendidik / Tenaga Kependidikan'" severity="success" class="text-xs" />
                            </div>
                        </div>

                        <div>
                            <span class="text-xs font-semibold text-surface-500 uppercase tracking-wider block mb-1">{{ t('profile.employeeInfo.nuptk') }}</span>
                            <div class="p-2.5 bg-surface-50 dark:bg-surface-900 rounded border border-surface-200 dark:border-surface-700 text-sm font-mono font-medium">
                                {{ linkedEmployee.nuptk || '-' }}
                            </div>
                        </div>

                        <div>
                            <span class="text-xs font-semibold text-surface-500 uppercase tracking-wider block mb-1">{{ t('profile.employeeInfo.employment') }}</span>
                            <div class="p-2.5 bg-surface-50 dark:bg-surface-900 rounded border border-surface-200 dark:border-surface-700 text-sm font-medium">
                                {{ linkedEmployee.employment?.name || linkedEmployee.employee_status?.name || '-' }}
                            </div>
                        </div>

                        <div>
                            <span class="text-xs font-semibold text-surface-500 uppercase tracking-wider block mb-1">{{ t('profile.employeeInfo.subject') }}</span>
                            <div class="p-2.5 bg-surface-50 dark:bg-surface-900 rounded border border-surface-200 dark:border-surface-700 text-sm font-medium">
                                {{ linkedEmployee.subject?.name || '-' }}
                            </div>
                        </div>
                    </div>

                    <div v-else class="p-4 bg-primary-50 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800 rounded flex items-start gap-3">
                        <i class="pi pi-shield text-primary text-xl mt-0.5"></i>
                        <div>
                            <p class="text-sm font-semibold text-primary-900 dark:text-primary-100 m-0">{{ t('profile.employeeInfo.notLinked') }}</p>
                            <p class="text-xs text-primary-700 dark:text-primary-300 m-0 mt-1">{{ t('profile.employeeInfo.adminNotice') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan: Form Ubah Kata Sandi -->
            <div class="lg:col-span-5">
                <form @submit.prevent="submitNewPassword">
                    <div class="card flex flex-col gap-5">
                        <div class="flex items-center gap-3 pb-3 border-b border-surface-200 dark:border-surface-800">
                            <i class="pi pi-lock text-primary text-xl"></i>
                            <div>
                                <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-0 m-0">{{ t('profile.changePassword.title') }}</h3>
                                <p class="text-xs text-surface-500 m-0">{{ t('profile.changePassword.subtitle') }}</p>
                            </div>
                        </div>

                        <!-- Kata Sandi Saat Ini -->
                        <div class="flex flex-col gap-2">
                            <label for="password_old" class="text-sm font-medium text-surface-700 dark:text-surface-200">{{ t('profile.changePassword.currentPassword') }}</label>
                            <IconField>
                                <InputText id="password_old" :type="inputTypePassword_old" v-model="form.password_old" class="w-full" :placeholder="t('profile.changePassword.currentPassword')" />
                                <InputIcon @click="togglePassword_oldVisibility" :class="inputIconPassword_old" class="cursor-pointer" />
                            </IconField>
                        </div>

                        <!-- Kata Sandi Baru -->
                        <div class="flex flex-col gap-2">
                            <label for="password_new" class="text-sm font-medium text-surface-700 dark:text-surface-200">{{ t('profile.changePassword.newPassword') }}</label>
                            <IconField>
                                <InputText id="password_new" :type="inputTypePassword_new" v-model="form.password_new" class="w-full" :placeholder="t('profile.changePassword.newPassword')" />
                                <InputIcon @click="togglePassword_newVisibility" :class="inputIconPassword_new" class="cursor-pointer" />
                            </IconField>
                            <small class="text-xs text-surface-500">Minimal 12 karakter, kombinasikan huruf besar, huruf kecil, angka & simbol.</small>
                        </div>

                        <!-- Konfirmasi Kata Sandi Baru -->
                        <div class="flex flex-col gap-2">
                            <label for="password_new_confirm" class="text-sm font-medium text-surface-700 dark:text-surface-200">{{ t('profile.changePassword.confirmNewPassword') }}</label>
                            <IconField>
                                <InputText
                                    id="password_new_confirm"
                                    :type="inputTypePassword_new_confirm"
                                    v-model="form.password_new_confirm"
                                    :invalid="Boolean(form.password_new_confirm && form.password_new !== form.password_new_confirm)"
                                    class="w-full"
                                    :placeholder="t('profile.changePassword.confirmNewPassword')"
                                />
                                <InputIcon @click="togglePassword_new_confirmVisibility" :class="inputIconPassword_new_confirm" class="cursor-pointer" />
                            </IconField>
                            <Message v-if="form.password_new_confirm && form.password_new !== form.password_new_confirm" severity="error" size="small" variant="simple">
                                {{ t('profile.changePassword.passwordMismatch') }}
                            </Message>
                        </div>

                        <div class="pt-2 flex justify-end">
                            <Button type="submit" icon="pi pi-check" :label="t('common.action.save')" class="w-full sm:w-auto" />
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </Fluid>

    <!-- Dialog Konfirmasi Headless -->
    <ConfirmDialog group="headless">
        <template #container="{ message, acceptCallback, rejectCallback }">
            <div class="flex flex-col items-center p-8 bg-surface-0 dark:bg-surface-900 rounded-xl shadow-lg border border-surface-200 dark:border-surface-700 max-w-md">
                <div class="rounded-full bg-primary-100 dark:bg-primary-900/50 text-primary-600 inline-flex justify-center items-center h-20 w-20 mb-4">
                    <i class="pi pi-lock-open text-4xl"></i>
                </div>
                <span class="font-bold text-xl block mb-2 text-surface-900 dark:text-surface-0">{{ message.header }}</span>
                <p class="mb-6 text-sm text-center text-surface-600 dark:text-surface-400">{{ message.message }}</p>
                <div class="flex items-center gap-3 w-full justify-center">
                    <Button :label="t('common.action.cancel')" outlined severity="secondary" @click="rejectCallback" class="w-1/2" />
                    <Button :label="t('common.action.save')" severity="primary" @click="acceptCallback" class="w-1/2" />
                </div>
            </div>
        </template>
    </ConfirmDialog>

    <!-- Modal Error / Peringatan -->
    <Dialog v-model:visible="visibleStaticBackdropDanger" modal :header="titleDanger" :style="{ width: '28rem' }" :breakpoints="{ '960px': '75vw', '640px': '90vw' }">
        <div class="flex items-start gap-3 p-1">
            <i class="pi pi-exclamation-triangle text-red-500 text-2xl mt-0.5"></i>
            <div class="text-sm text-surface-700 dark:text-surface-200 leading-relaxed">
                {{ fieldDanger }}
            </div>
        </div>
        <template #footer>
            <Button label="Tutup" severity="secondary" @click="visibleStaticBackdropDanger = false" />
        </template>
    </Dialog>

    <!-- Modal Sukses -->
    <Dialog v-model:visible="visibleStaticBackdropSuccess" modal :header="titleSuccess" :style="{ width: '25rem' }">
        <div class="flex flex-col items-center justify-center p-4 text-center">
            <Image fluid :src="gifSuccess" width="60" height="60" class="mb-3" />
            <p class="text-base font-medium text-surface-800 dark:text-surface-100 m-0">{{ fieldSuccess }}</p>
        </div>
    </Dialog>

    <!-- Modal Loading / Waiting -->
    <Dialog v-model:visible="visibleStaticBackdropWaiting" modal :header="t('profile.dialog.waitingHeader')" :closable="false" :style="{ width: '22rem' }">
        <div class="flex flex-col items-center justify-center p-6 text-center">
            <i class="pi pi-spin pi-spinner text-primary text-4xl mb-4"></i>
            <p class="text-sm font-medium text-surface-600 dark:text-surface-300 m-0">{{ t('profile.dialog.loading') }}</p>
        </div>
    </Dialog>
</template>
