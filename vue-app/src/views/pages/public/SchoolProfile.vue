<script setup>
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import { RouterLink } from 'vue-router';
import { authStore } from '@/stores/authStore';
import { useToast } from 'primevue/usetoast';
import apiLogin from '@/apiLogin';
import { referenceLabel } from '@/utils/referenceLabels.mjs';

const toast = useToast();
const { t } = useI18n();
const dt = ref();
const useAuthStore = authStore();
const school_years = ref([]);
const school_years_now = ref(null);
const activeSchoolYearId = ref(null);
const school_profile = ref({});
const allEducationSchools = ref([]);
const principalEmployees = ref([]);
const editingField = ref(null);
const editingValue = ref('');
const editingSelected = ref(null);
const savingField = ref(null);

const saveUser = ref([]);
const WaitingDialog = ref(false);
const ErrorDialog = ref(false);
const titleError = ref('');
const fieldError = ref('');
const loading = ref(true);

const fetchDropdownOptions = async () => {
    try {
        const optionsRes = await apiLogin.get('/api/school-profile-options');
        const payload = optionsRes.data?.data ?? {};
        activeSchoolYearId.value = payload.active_school_year_id ?? null;
        allEducationSchools.value = payload.education_schools ?? [];
        principalEmployees.value = payload.principal_employees ?? [];
    } catch (err) {
        console.error('fetchDropdownOptions error:', err);
    }
};

const fetchDataSchoolYears = async () => {
    school_years.value = [];
    school_profile.value = {};
    loading.value = true;

    try {
        const [yearsRes] = await Promise.all([
            apiLogin.get('/api/school-years'),
            fetchDropdownOptions()
        ]);
        school_years.value = yearsRes.data?.data ?? [];
        await fetchDataSelected();
    } catch (err) {
        loading.value = false;
        console.error('fetchDataSchoolYears error:', err);
    }
};

const fetchDataSelected = async () => {
    if (!school_years_now.value && school_years.value.length > 0) {
        // 1. Prioritaskan Tahun Ajaran Aktif dari AcademicSetting
        if (activeSchoolYearId.value) {
            const activeMatch = school_years.value.find((sy) => Number(sy.id) === Number(activeSchoolYearId.value));
            if (activeMatch) {
                school_years_now.value = activeMatch;
            }
        }

        // 2. Fallback ke perhitungan kalender bila belum ada tahun aktif
        if (!school_years_now.value) {
            const years_now = new Date().getFullYear();
            const years_tomorrow = years_now + 1;
            const month_now = new Date().getMonth() + 1;
            const years_combine = month_now > 6 ? `${years_now}/${years_tomorrow}` : `${years_now - 1}/${years_tomorrow - 1}`;
            school_years_now.value = school_years.value.find((sy) => sy.name === years_combine) ?? school_years.value[0];
        }
    }

    if (school_years_now.value?.id) {
        await fetchDataSchoolProfile();
    } else {
        loading.value = false;
    }
};

const fetchDataSchoolProfile = async () => {
    dt.value = null;
    loading.value = true;
    try {
        const response = await apiLogin.get(`/api/school-profile/${school_years_now.value.id}`);
        const temp_data = response.data?.data;
        school_profile.value = Array.isArray(temp_data) ? (temp_data[0] ?? {}) : (temp_data ?? {});
    } catch (err) {
        console.error('fetchDataSchoolProfile error:', err);
    } finally {
        loading.value = false;
    }
};

const fetchDataUserRole = async () => {
    await useAuthStore.getUser();
    saveUser.value = useAuthStore.AllUserAssign;
    await fetchDataSchoolYears();
};

const fieldConfig = computed(() => ({
    'education_school.name': {
        type: 'select_school',
        options: allEducationSchools.value,
        optionLabel: 'name',
        addNewLink: '/public/educationschools',
        addNewLabel: t('master.schoolProfile.addNewSchool'),
        endpoint: `/api/school-profile/${school_profile.value?.id}`,
        payloadKey: 'education_school_npsn'
    },
    'principal_employee.name': {
        type: 'select_principal',
        options: principalEmployees.value,
        optionLabel: 'name',
        addNewLink: '/public/employees',
        addNewLabel: t('master.schoolProfile.addNewPrincipalEmployee'),
        endpoint: `/api/school-profile/${school_profile.value?.id}`,
        payloadKey: 'principal_employee_id'
    },

    // Field manual (Identitas Sekolah, Identitas Kepala Sekolah, & Identitas Komite Sekolah)
    nds: { type: 'text', payloadKey: 'nds' },
    nss: { type: 'text', payloadKey: 'nss' },
    zip_code: { type: 'text', payloadKey: 'zip_code' },
    official_number: { type: 'text', payloadKey: 'official_number' },
    email: { type: 'text', payloadKey: 'email' },
    website: { type: 'text', payloadKey: 'website' },
    nrks: { type: 'text', payloadKey: 'nrks' },
    nuptk: { type: 'text', payloadKey: 'nuptk' },
    tmt_principal: { type: 'date', payloadKey: 'tmt_principal' },
    school_committee_name: { type: 'text', payloadKey: 'school_committee_name' },
    school_committee_number: { type: 'text', payloadKey: 'school_committee_number' }
}));

async function handleSchoolYearsChange() {
    cancelEdit();
    await fetchDataSelected();
}

const startEdit = async (fieldKey, currentValue) => {
    const config = fieldConfig.value[fieldKey];
    editingSelected.value = null;
    editingField.value = fieldKey;

    if (config?.type === 'select_school') {
        await fetchDropdownOptions();
        const currentNpsn = school_profile.value?.education_school_npsn ?? school_profile.value?.education_school?.npsn;
        editingSelected.value = allEducationSchools.value.find((s) => String(s.npsn) === String(currentNpsn)) ?? null;
    } else if (config?.type === 'select_principal') {
        await fetchDropdownOptions();
        const currentEmpId = school_profile.value?.principal_employee_id ?? school_profile.value?.principal_employee?.id;
        editingSelected.value = principalEmployees.value.find((e) => Number(e.id) === Number(currentEmpId)) ?? null;
    } else if (config?.type === 'date' && currentValue) {
        editingValue.value = new Date(currentValue);
    } else {
        editingValue.value = currentValue ?? '';
    }
};

const cancelEdit = () => {
    editingField.value = null;
    editingValue.value = '';
    editingSelected.value = null;
};

const saveField = async (fieldKey) => {
    const config = fieldConfig.value[fieldKey];
    if (!config || !school_profile.value?.id) return;

    if ((config.type === 'select_school' || config.type === 'select_principal') && !editingSelected.value) return;
    if (config.type === 'date' && !editingValue.value) return;

    WaitingDialog.value = true;
    savingField.value = fieldKey;

    try {
        let payloadValue;

        if (config.type === 'select_school') {
            payloadValue = editingSelected.value.npsn;
        } else if (config.type === 'select_principal') {
            payloadValue = editingSelected.value.id;
        } else if (config.type === 'date') {
            const d = editingValue.value instanceof Date ? editingValue.value : new Date(editingValue.value);
            payloadValue = [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
        } else {
            payloadValue = typeof editingValue.value === 'string' ? editingValue.value.trim() : editingValue.value;
        }

        const payload = {
            _method: 'PATCH',
            [config.payloadKey]: payloadValue
        };

        const response = await apiLogin.post(config.endpoint ?? `/api/school-profile/${school_profile.value.id}`, payload);
        if (response.data?.data) {
            school_profile.value = response.data.data;
        }

        WaitingDialog.value = false;
        toast.add({
            severity: 'success',
            summary: t('master.common.save'),
            detail: t('master.schoolProfile.savedSuccess', { field: fieldKey }),
            life: 3000
        });
        cancelEdit();
    } catch (error) {
        WaitingDialog.value = false;
        if (error.response?.status === 422) {
            const messages = error.response.data?.errors
                ? Object.values(error.response.data.errors).flat().join('\n')
                : Object.values(error.response.data).flat().join('\n');
            toast.add({
                severity: 'warn',
                summary: 'Validasi Gagal',
                detail: messages,
                life: 5000
            });
        } else {
            toast.add({
                severity: 'error',
                summary: 'Gagal',
                detail: t('master.schoolProfile.saveFailed'),
                life: 3000
            });
        }
        console.error('saveField error:', error);
    } finally {
        savingField.value = null;
    }
};

const isCurrentYearActive = computed(() => {
    return Boolean(school_years_now.value?.id && activeSchoolYearId.value && Number(school_years_now.value.id) === Number(activeSchoolYearId.value));
});

const principalDisplayName = computed(() => {
    return school_profile.value?.principal_employee?.name ?? school_profile.value?.principal?.name ?? '-';
});

onMounted(() => {
    fetchDataUserRole();
});
</script>

<template>
    <Fluid>
        <!-- Header & Pemilihan Tahun Ajaran -->
        <div class="bg-surface-0 dark:bg-surface-900 px-6 py-8 md:px-12 lg:px-20 rounded-xl shadow-xs">
            <div class="flex items-start flex-col md:justify-between md:flex-row md:items-center gap-4">
                <div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <div class="font-bold text-3xl text-surface-900 dark:text-surface-0">
                            {{ t('master.schoolProfile.title') }} {{ school_years_now ? `- ${school_years_now.name}` : '' }}
                        </div>
                        <Tag
                            v-if="isCurrentYearActive"
                            :value="t('master.schoolProfile.activePeriodBadge')"
                            severity="success"
                            icon="pi pi-check-circle"
                            class="text-xs font-semibold px-2.5 py-1"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <span class="font-medium text-surface-700 dark:text-surface-200 whitespace-nowrap">{{ t('master.schoolProfile.selectYear') }}</span>
                    <Select
                        v-model="school_years_now"
                        :options="school_years"
                        optionLabel="name"
                        :placeholder="t('master.schoolYears.selectSchoolYear')"
                        class="w-full md:w-56"
                        @change="handleSchoolYearsChange"
                    >
                        <template #option="slotProps">
                            <div class="flex items-center justify-between w-full gap-2">
                                <span>{{ slotProps.option.name }}</span>
                                <Tag
                                    v-if="Number(slotProps.option.id) === Number(activeSchoolYearId)"
                                    :value="t('master.schoolYears.active')"
                                    severity="success"
                                    class="text-[10px] px-1.5 py-0.5"
                                />
                            </div>
                        </template>
                    </Select>
                </div>
            </div>
        </div>

        <!-- FORM 1: A. Identitas Sekolah -->
        <div class="bg-surface-0 dark:bg-surface-900 px-6 py-8 md:px-12 lg:px-20 mt-4 mb-4 rounded-xl shadow-xs">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2 pb-4">
                    <div class="font-semibold text-xl text-surface-900 dark:text-surface-0 leading-tight">{{ t('master.schoolProfile.schoolIdentity') }}</div>
                </div>

                <div class="border-t border-surface-200 dark:border-surface-700" />

                <div class="flex flex-col gap-4">
                    <!-- Nama Sekolah (Pilihan dari Menu Sekolah) -->
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-start md:items-center gap-4 flex-1">
                            <div class="w-[140px] shrink-0 text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">{{ t('master.common.name') }}</div>
                            <div class="flex-1 min-w-0">
                                <span v-if="editingField !== 'education_school.name'" class="text-surface-900 dark:text-surface-0 text-base">
                                    <Skeleton v-if="loading" width="12rem" height="1.5rem" />
                                    <template v-else>{{ school_profile?.education_school?.name || '-' }}</template>
                                </span>
                                <div v-else class="flex flex-col gap-2">
                                    <div class="flex items-center gap-2">
                                        <Select
                                            v-model="editingSelected"
                                            :options="allEducationSchools"
                                            optionLabel="name"
                                            :filterFields="['name', 'npsn', 'address']"
                                            :virtualScrollerOptions="{ itemSize: 48 }"
                                            filter
                                            :placeholder="t('master.schoolProfile.searchSchoolPlaceholder')"
                                            class="flex-1 min-w-0"
                                        >
                                            <template #option="slotProps">
                                                <div class="flex flex-col">
                                                    <span class="font-medium">{{ slotProps.option.name }}</span>
                                                    <span class="text-xs text-surface-500">NPSN: {{ slotProps.option.npsn }} — {{ slotProps.option.address || '-' }}</span>
                                                </div>
                                            </template>
                                        </Select>
                                        <Button
                                            icon="pi pi-check"
                                            severity="success"
                                            rounded
                                            class="shrink-0"
                                            :loading="savingField === 'education_school.name'"
                                            :disabled="!editingSelected?.npsn"
                                            @click="saveField('education_school.name')"
                                        />
                                        <Button icon="pi pi-times" severity="secondary" rounded variant="outlined" class="shrink-0" @click="cancelEdit" />
                                    </div>

                                    <small class="text-surface-500 flex items-center gap-1 flex-wrap">
                                        {{ t('master.schoolProfile.notFoundHint') }}
                                        <RouterLink :to="fieldConfig['education_school.name'].addNewLink" class="text-primary-500 hover:underline font-medium flex items-center gap-1">
                                            <i class="pi pi-external-link text-xs" />
                                            {{ fieldConfig['education_school.name'].addNewLabel }}
                                        </RouterLink>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'education_school.name'" class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" @click="startEdit('education_school.name', school_profile?.education_school?.name)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <!-- NPSN (Otomatis dari Sekolah terpilih) -->
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] shrink-0 text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">{{ t('master.schoolProfile.npsn') }}</div>
                            <div class="flex-1 text-surface-900 dark:text-surface-0 text-base leading-tight">
                                <Skeleton v-if="loading" width="8rem" height="1.5rem" />
                                <template v-else>{{ school_profile?.education_school?.npsn || '-' }}</template>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <!-- Status Sekolah (Otomatis dari Sekolah terpilih) -->
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] shrink-0 text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">{{ t('master.schoolProfile.status') }}</div>
                            <div class="flex-1 text-surface-900 dark:text-surface-0 text-base leading-tight">
                                <Skeleton v-if="loading" width="8rem" height="1.5rem" />
                                <template v-else>
                                    {{ school_profile?.education_school?.status_education ? referenceLabel('school-status', school_profile.education_school.status_education) : '-' }}
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <!-- NDS (Manual) -->
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] shrink-0 text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">{{ t('master.schoolProfile.nds') }}</div>
                            <div class="flex-1">
                                <span v-if="editingField !== 'nds'" class="text-surface-900 dark:text-surface-0 text-base">
                                    <Skeleton v-if="loading" width="8rem" height="1.5rem" />
                                    <template v-else>{{ school_profile?.nds || '-' }}</template>
                                </span>
                                <div v-else class="flex items-center gap-2">
                                    <InputText v-model="editingValue" class="flex-1" autofocus @keyup.enter="saveField('nds')" @keyup.escape="cancelEdit" fluid />
                                    <Button icon="pi pi-check" severity="success" rounded :loading="savingField === 'nds'" @click="saveField('nds')" />
                                    <Button icon="pi pi-times" severity="secondary" rounded variant="outlined" @click="cancelEdit" />
                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'nds'" class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" @click="startEdit('nds', school_profile?.nds)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <!-- NSS (Manual) -->
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] shrink-0 text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">{{ t('master.schoolProfile.nss') }}</div>
                            <div class="flex-1">
                                <span v-if="editingField !== 'nss'" class="text-surface-900 dark:text-surface-0 text-base">
                                    <Skeleton v-if="loading" width="8rem" height="1.5rem" />
                                    <template v-else>{{ school_profile?.nss || '-' }}</template>
                                </span>
                                <div v-else class="flex items-center gap-2">
                                    <InputText v-model="editingValue" class="flex-1" autofocus @keyup.enter="saveField('nss')" @keyup.escape="cancelEdit" fluid />
                                    <Button icon="pi pi-check" severity="success" rounded :loading="savingField === 'nss'" @click="saveField('nss')" />
                                    <Button icon="pi pi-times" severity="secondary" rounded variant="outlined" @click="cancelEdit" />
                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'nss'" class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" @click="startEdit('nss', school_profile?.nss)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <!-- Alamat (Otomatis dari Sekolah terpilih) -->
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] shrink-0 text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">{{ t('master.schoolProfile.address') }}</div>
                            <div class="flex-1 text-surface-900 dark:text-surface-0 text-base leading-tight">
                                <Skeleton v-if="loading" width="14rem" height="1.5rem" />
                                <template v-else>{{ school_profile?.education_school?.address || '-' }}</template>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <!-- Kode Pos (Manual) -->
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] shrink-0 text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">{{ t('master.schoolProfile.zipCode') }}</div>
                            <div class="flex-1">
                                <span v-if="editingField !== 'zip_code'" class="text-surface-900 dark:text-surface-0 text-base">
                                    <Skeleton v-if="loading" width="6rem" height="1.5rem" />
                                    <template v-else>{{ school_profile?.zip_code || '-' }}</template>
                                </span>
                                <div v-else class="flex items-center gap-2">
                                    <InputText v-model="editingValue" class="flex-1" autofocus @keyup.enter="saveField('zip_code')" @keyup.escape="cancelEdit" fluid />
                                    <Button icon="pi pi-check" severity="success" rounded :loading="savingField === 'zip_code'" @click="saveField('zip_code')" />
                                    <Button icon="pi pi-times" severity="secondary" rounded variant="outlined" @click="cancelEdit" />
                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'zip_code'" class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" @click="startEdit('zip_code', school_profile?.zip_code)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <!-- Nomor SK / Izin (Manual) -->
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] shrink-0 text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">{{ t('master.schoolProfile.officialNumber') }}</div>
                            <div class="flex-1">
                                <span v-if="editingField !== 'official_number'" class="text-surface-900 dark:text-surface-0 text-base">
                                    <Skeleton v-if="loading" width="10rem" height="1.5rem" />
                                    <template v-else>{{ school_profile?.official_number || '-' }}</template>
                                </span>
                                <div v-else class="flex items-center gap-2">
                                    <InputText v-model="editingValue" class="flex-1" autofocus @keyup.enter="saveField('official_number')" @keyup.escape="cancelEdit" fluid />
                                    <Button icon="pi pi-check" severity="success" rounded :loading="savingField === 'official_number'" @click="saveField('official_number')" />
                                    <Button icon="pi pi-times" severity="secondary" rounded variant="outlined" @click="cancelEdit" />
                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'official_number'" class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" @click="startEdit('official_number', school_profile?.official_number)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <!-- Email (Manual) -->
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] shrink-0 text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">{{ t('master.accounts.email') }}</div>
                            <div class="flex-1">
                                <span v-if="editingField !== 'email'" class="text-surface-900 dark:text-surface-0 text-base">
                                    <Skeleton v-if="loading" width="12rem" height="1.5rem" />
                                    <template v-else>{{ school_profile?.email || '-' }}</template>
                                </span>
                                <div v-else class="flex items-center gap-2">
                                    <InputText v-model="editingValue" class="flex-1" autofocus @keyup.enter="saveField('email')" @keyup.escape="cancelEdit" fluid />
                                    <Button icon="pi pi-check" severity="success" rounded :loading="savingField === 'email'" @click="saveField('email')" />
                                    <Button icon="pi pi-times" severity="secondary" rounded variant="outlined" @click="cancelEdit" />
                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'email'" class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" @click="startEdit('email', school_profile?.email)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <!-- Website (Manual) -->
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] shrink-0 text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">{{ t('master.schoolProfile.website') }}</div>
                            <div class="flex-1">
                                <span v-if="editingField !== 'website'" class="text-surface-900 dark:text-surface-0 text-base">
                                    <Skeleton v-if="loading" width="12rem" height="1.5rem" />
                                    <template v-else>{{ school_profile?.website || '-' }}</template>
                                </span>
                                <div v-else class="flex items-center gap-2">
                                    <InputText v-model="editingValue" class="flex-1" autofocus @keyup.enter="saveField('website')" @keyup.escape="cancelEdit" fluid />
                                    <Button icon="pi pi-check" severity="success" rounded :loading="savingField === 'website'" @click="saveField('website')" />
                                    <Button icon="pi pi-times" severity="secondary" rounded variant="outlined" @click="cancelEdit" />
                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'website'" class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" @click="startEdit('website', school_profile?.website)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />
                </div>
            </div>
        </div>

        <!-- FORM 2: B. Identitas Kepala Sekolah -->
        <div class="bg-surface-0 dark:bg-surface-900 px-6 py-8 md:px-12 lg:px-20 mb-4 rounded-xl shadow-xs">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2 pb-4">
                    <div class="font-semibold text-xl text-surface-900 dark:text-surface-0 leading-tight">{{ t('master.schoolProfile.principalIdentity') }}</div>
                </div>

                <div class="border-t border-surface-200 dark:border-surface-700" />

                <div class="flex flex-col gap-4">
                    <!-- Nama Kepala Sekolah (Pilihan dari Menu Pegawai berkategori Kepala Sekolah) -->
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-start md:items-center gap-4 flex-1">
                            <div class="w-[140px] shrink-0 text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">{{ t('master.common.name') }}</div>
                            <div class="flex-1 min-w-0">
                                <span v-if="editingField !== 'principal_employee.name'" class="text-surface-900 dark:text-surface-0 text-base">
                                    <Skeleton v-if="loading" width="12rem" height="1.5rem" />
                                    <template v-else>{{ principalDisplayName }}</template>
                                </span>
                                <div v-else class="flex flex-col gap-2">
                                    <div class="flex items-center gap-2">
                                        <Select
                                            v-model="editingSelected"
                                            :options="principalEmployees"
                                            optionLabel="name"
                                            :filterFields="['name', 'nip', 'nik']"
                                            filter
                                            :placeholder="t('master.schoolProfile.searchPrincipalPlaceholder')"
                                            class="flex-1 min-w-0"
                                        >
                                            <template #option="slotProps">
                                                <div class="flex flex-col">
                                                    <span class="font-medium">{{ slotProps.option.name }}</span>
                                                    <span class="text-xs text-surface-500">
                                                        {{ slotProps.option.employment_name || 'Kepala Sekolah' }}
                                                        <template v-if="slotProps.option.nip"> — NIP: {{ slotProps.option.nip }}</template>
                                                    </span>
                                                </div>
                                            </template>
                                        </Select>
                                        <Button
                                            icon="pi pi-check"
                                            severity="success"
                                            rounded
                                            class="shrink-0"
                                            :loading="savingField === 'principal_employee.name'"
                                            :disabled="!editingSelected?.id"
                                            @click="saveField('principal_employee.name')"
                                        />
                                        <Button icon="pi pi-times" severity="secondary" rounded variant="outlined" class="shrink-0" @click="cancelEdit" />
                                    </div>

                                    <div v-if="principalEmployees.length === 0" class="text-xs text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                                        <i class="pi pi-info-circle" />
                                        <span>{{ t('master.schoolProfile.noPrincipalEmployeeHint') }}</span>
                                    </div>

                                    <small class="text-surface-500 flex items-center gap-1 flex-wrap">
                                        {{ t('master.schoolProfile.notFoundHint') }}
                                        <RouterLink :to="fieldConfig['principal_employee.name'].addNewLink" class="text-primary-500 hover:underline font-medium flex items-center gap-1">
                                            <i class="pi pi-external-link text-xs" />
                                            {{ fieldConfig['principal_employee.name'].addNewLabel }}
                                        </RouterLink>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'principal_employee.name'" class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" @click="startEdit('principal_employee.name', principalDisplayName)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <!-- NRKS (Manual) -->
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] shrink-0 text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">{{ t('master.schoolProfile.nrks') }}</div>
                            <div class="flex-1">
                                <span v-if="editingField !== 'nrks'" class="text-surface-900 dark:text-surface-0 text-base">
                                    <Skeleton v-if="loading" width="8rem" height="1.5rem" />
                                    <template v-else>{{ school_profile?.nrks || '-' }}</template>
                                </span>
                                <div v-else class="flex items-center gap-2">
                                    <InputText v-model="editingValue" class="flex-1" autofocus @keyup.enter="saveField('nrks')" @keyup.escape="cancelEdit" fluid />
                                    <Button icon="pi pi-check" severity="success" rounded :loading="savingField === 'nrks'" @click="saveField('nrks')" />
                                    <Button icon="pi pi-times" severity="secondary" rounded variant="outlined" @click="cancelEdit" />
                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'nrks'" class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" @click="startEdit('nrks', school_profile?.nrks)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <!-- NUPTK (Manual) -->
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] shrink-0 text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">{{ t('master.schoolProfile.nuptk') }}</div>
                            <div class="flex-1">
                                <span v-if="editingField !== 'nuptk'" class="text-surface-900 dark:text-surface-0 text-base">
                                    <Skeleton v-if="loading" width="8rem" height="1.5rem" />
                                    <template v-else>{{ school_profile?.nuptk || '-' }}</template>
                                </span>
                                <div v-else class="flex items-center gap-2">
                                    <InputText v-model="editingValue" class="flex-1" autofocus @keyup.enter="saveField('nuptk')" @keyup.escape="cancelEdit" fluid />
                                    <Button icon="pi pi-check" severity="success" rounded :loading="savingField === 'nuptk'" @click="saveField('nuptk')" />
                                    <Button icon="pi pi-times" severity="secondary" rounded variant="outlined" @click="cancelEdit" />
                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'nuptk'" class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" @click="startEdit('nuptk', school_profile?.nuptk)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <!-- TMT Kepala Sekolah (Manual) -->
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] shrink-0 text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">{{ t('master.schoolProfile.tmtPrincipal') }}</div>
                            <div class="flex-1">
                                <span v-if="editingField !== 'tmt_principal'" class="text-surface-900 dark:text-surface-0 text-base">
                                    <Skeleton v-if="loading" width="8rem" height="1.5rem" />
                                    <template v-else>{{ school_profile?.tmt_principal || '-' }}</template>
                                </span>
                                <div v-else class="flex items-center gap-2">
                                    <DatePicker v-model="editingValue" class="flex-1" dateFormat="yy-mm-dd" @keyup.escape="cancelEdit" fluid />
                                    <Button icon="pi pi-check" severity="success" rounded :loading="savingField === 'tmt_principal'" :disabled="!editingValue" @click="saveField('tmt_principal')" />
                                    <Button icon="pi pi-times" severity="secondary" rounded variant="outlined" @click="cancelEdit" />
                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'tmt_principal'" class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" @click="startEdit('tmt_principal', school_profile?.tmt_principal)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />
                </div>
            </div>
        </div>

        <!-- FORM 3: C. Identitas Komite Sekolah -->
        <div class="bg-surface-0 dark:bg-surface-900 px-6 py-8 md:px-12 lg:px-20 rounded-xl shadow-xs">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2 pb-4">
                    <div class="font-semibold text-xl text-surface-900 dark:text-surface-0 leading-tight">{{ t('master.schoolProfile.committeeIdentity') }}</div>
                </div>

                <div class="border-t border-surface-200 dark:border-surface-700" />

                <div class="flex flex-col gap-4">
                    <!-- Nama Komite (Manual) -->
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] shrink-0 text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">{{ t('master.common.name') }}</div>
                            <div class="flex-1">
                                <span v-if="editingField !== 'school_committee_name'" class="text-surface-900 dark:text-surface-0 text-base">
                                    <Skeleton v-if="loading" width="10rem" height="1.5rem" />
                                    <template v-else>{{ school_profile?.school_committee_name || '-' }}</template>
                                </span>
                                <div v-else class="flex items-center gap-2">
                                    <InputText v-model="editingValue" class="flex-1" autofocus @keyup.enter="saveField('school_committee_name')" @keyup.escape="cancelEdit" fluid />
                                    <Button icon="pi pi-check" severity="success" rounded :loading="savingField === 'school_committee_name'" @click="saveField('school_committee_name')" />
                                    <Button icon="pi pi-times" severity="secondary" rounded variant="outlined" @click="cancelEdit" />
                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'school_committee_name'" class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" @click="startEdit('school_committee_name', school_profile?.school_committee_name)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <!-- Nomor Kontak Komite (Manual) -->
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] shrink-0 text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">{{ t('master.schoolProfile.committeeNumber') }}</div>
                            <div class="flex-1">
                                <span v-if="editingField !== 'school_committee_number'" class="text-surface-900 dark:text-surface-0 text-base">
                                    <Skeleton v-if="loading" width="10rem" height="1.5rem" />
                                    <template v-else>{{ school_profile?.school_committee_number || '-' }}</template>
                                </span>
                                <div v-else class="flex items-center gap-2">
                                    <InputText v-model="editingValue" class="flex-1" autofocus @keyup.enter="saveField('school_committee_number')" @keyup.escape="cancelEdit" fluid />
                                    <Button icon="pi pi-check" severity="success" rounded :loading="savingField === 'school_committee_number'" @click="saveField('school_committee_number')" />
                                    <Button icon="pi pi-times" severity="secondary" rounded variant="outlined" @click="cancelEdit" />
                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'school_committee_number'" class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" @click="startEdit('school_committee_number', school_profile?.school_committee_number)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />
                </div>
            </div>
        </div>
    </Fluid>

    <!-- Loading Dialog -->
    <Dialog v-model:visible="WaitingDialog" modal :header="t('master.dialog.processing')">
        <div class="text-center justify-content-center align-items-center">
            <i class="pi pi-spin pi-spinner" style="font-size: 2rem; color: green"></i>
            <p class="text-success mt-3" style="font-size: 1.1rem; font-weight: 500">{{ t('master.dialog.pleaseWait') }}</p>
        </div>
    </Dialog>
    <!-- Error Dialog -->
    <Dialog v-model:visible="ErrorDialog" modal :header="titleError" style="width: 25rem; color: red">
        <div style="color: black">
            {{ fieldError }}
        </div>
    </Dialog>
</template>
