<script setup>
import { ref, reactive, onMounted, watch, computed } from 'vue';
import Button from 'primevue/button';
import Chip from 'primevue/chip';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import { useLayout } from '@/layout/composables/layout';
import { authStore } from '@/stores/authStore';
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import apiLogin from '@/apiLogin';
import { FilterMatchMode, FilterOperator } from '@primevue/core/api';

const confirm = useConfirm();
const toast = useToast();
const router = useRouter();
const dt = ref();
const useAuthStore = authStore();
const school_years = ref([]);
const school_years_now = ref('');
const school_years_pick = ref([]);
const school_profile = ref([]);
const allEducationSchools = ref([]);
const allUsers            = ref([]);
const editingField  = ref(null);
const editingValue  = ref('');
const editingSelected = ref(null);
const savingField   = ref(null);

const saveUser = ref([]);
const entityLabel       = ref('');
const EditOrNewDialog = ref(false);
const WaitingDialog   = ref(false);
const ErrorDialog     = ref(false);
const titleError      = ref('');
const fieldError      = ref('');

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

//method fetchDataSchoolYears
const fetchDataSchoolYears = async () => {
    //fetch data
    school_years.value.length = 0;
    school_years_pick.value.length = 0;
    school_profile.value = [];
    loading.value = true;
    await apiLogin.get('/api/school-years')
    .then(response => {
        //set response data to state "Students"
        school_years.value = response.data.data;
        // console.log(school_years.value);
        fetchDataSelected();
    });

}

const fetchDataSelected = async () => {
    let years_now = new Date().getFullYear();
    let years_tomorrow = years_now + 1;
    let month_now = new Date().getMonth() + 1;
    let years_combine = '';
    if (month_now > 6) {
        years_combine = years_now+"/"+years_tomorrow;
    } else {
        years_combine = (years_now-1)+"/"+(years_tomorrow-1);
    }
    // let years_combine = years_now+"/"+years_tomorrow;
    // console.log(school_years_now.value);
    if (!school_years_now.value) {
        school_years_now.value = school_years.value.find(school_year => school_year.name === years_combine);
        fetchDataSchoolProfile();
        // console.log("school_years is reset");
        console.log(school_years_now.value);
    } else {
        fetchDataSchoolProfile();
        console.log("school_years is : ", school_years_now.value);
    }
}

//method fetchDataSchoolProfile
const fetchDataSchoolProfile = async () => {
    //fetch data
    dt.value = null;
    loading.value = false;
    await apiLogin.get(`/api/school-profile/${school_years_now.value.id}`)
    .then(response => {
        //set response data to state "School Profile"
        const temp_data = response.data.data;
        school_profile.value = temp_data[0];
        fetchDropdownOptions();
        // console.log(school_profile.value);
    });
}

//method fetchDataUserRole
const fetchDataUserRole = async () => {
    await useAuthStore.getUser();
    // console.log(useAuthStore.AllUserAssign);
    saveUser.value = useAuthStore.AllUserAssign;

    fetchDataSchoolYears();

    // fetchDataSchoolProfile();
};

const fetchDropdownOptions = async () => {
    const [schoolsRes, usersRes] = await Promise.all([
        apiLogin.get('/api/education-schools'),
        apiLogin.get('/api/users'),
    ]);
    allEducationSchools.value = schoolsRes.data.data;
    allUsers.value            = usersRes.data.data;
};

const fieldConfig = computed(() => ({
    'education_school.name': {
        type:        'autocomplete',
        options:     allEducationSchools.value,
        optionLabel: 'name',
        addNewLink:  '/public/educationschools',   // ← route to Education Schools page
        addNewLabel: 'Add new Education School',
        endpoint:    `/api/school-profile/${school_profile.value.id}`,
        payloadKey:  'education_school_npsn',
        localUpdate: (selected) => {
            school_profile.value.education_school = selected;
        },
    },
    'principal.name': {
        type:        'autocomplete',
        options:     allUsers.value,
        optionLabel: 'name',
        addNewLink:  '/master/accounts',              // ← route to Accounts page
        addNewLabel: 'Add new User',
        endpoint:    `/api/school-profile/${school_profile.value.id}`,
        payloadKey:  'principal_id',
        localUpdate: (selected) => {
            school_profile.value.principal = selected;
        },
    },

    // Direct text fields — unchanged
    'nds':                     { type: 'text', payloadKey: 'nds' },
    'nss':                     { type: 'text', payloadKey: 'nss' },
    'official_number':         { type: 'text', payloadKey: 'official_number' },
    'email':                   { type: 'text', payloadKey: 'email' },
    'website':                 { type: 'text', payloadKey: 'website' },
    'nrks':                    { type: 'text', payloadKey: 'nrks' },
    'nuptk':                   { type: 'text', payloadKey: 'nuptk' },
    'tmt_principal':           { type: 'date', payloadKey: 'tmt_principal' },
    'school_committee_name':   { type: 'text', payloadKey: 'school_committee_name' },
    'school_committee_number': { type: 'text', payloadKey: 'school_committee_number' },
}));

const filteredOptions = ref([]);

const searchOptions = (event, fieldKey) => {
    const config  = fieldConfig.value[fieldKey];
    const query   = event.query.trim().toLowerCase();
    filteredOptions.value = !query
        ? [...config.options]
        : config.options.filter(item =>
            item[config.optionLabel].toLowerCase().includes(query)
        );
};

function handleSchoolYearsChange() {
    // Should be — only refetch profile when year changes
    fetchDataSelected();
}

const startEdit = (fieldKey, currentValue) => {
    const config = fieldConfig.value[fieldKey];
    editingSelected.value = null;
    editingField.value = fieldKey;

    // Date fields need a Date object, not a string
    if (config?.type === 'date' && currentValue) {
        editingValue.value = new Date(currentValue);
    } else {
        editingValue.value = currentValue ?? '';
    }
};

const cancelEdit = () => {
    editingField.value    = null;
    editingValue.value    = '';
    editingSelected.value = null;
};

const saveField = async (fieldKey) => {
    const config = fieldConfig.value[fieldKey];
    if (!config) return;

    if (config.type === 'text'          && !editingValue.value?.trim())  return;
    if (config.type === 'autocomplete'  && !editingSelected.value)       return;
    if (config.type === 'date'          && !editingValue.value)          return;

    WaitingDialog.value = true;
    savingField.value = fieldKey;

    try {
        let payloadValue;

        if (config.type === 'autocomplete') {
            // ── Determine what value to send based on payloadKey ──────────
            if (config.payloadKey === 'education_school_npsn') {
                payloadValue = editingSelected.value.npsn;  // ← send npsn
            } else {
                payloadValue = editingSelected.value.id;    // ← send id (for principal etc.)
            }
        } else if (config.type === 'date') {
            // ── Convert Date object → 'YYYY-MM-DD' string for Laravel ─────
            const d = editingValue.value instanceof Date
                ? editingValue.value
                : new Date(editingValue.value);

            payloadValue = [
                d.getFullYear(),
                String(d.getMonth() + 1).padStart(2, '0'),
                String(d.getDate()).padStart(2, '0'),
            ].join('-');

        } else {
            payloadValue = editingValue.value;
        }
        console.log(payloadValue);

        const payload = {
            _method:             'PATCH',
            [config.payloadKey]: payloadValue,
        };

        await apiLogin.post(
            config.endpoint ?? `/api/school-profile/${school_profile.value.id}`,
            payload
        );

        if (config.type === 'autocomplete') {
            config.localUpdate(editingSelected.value);
        } else if (config.localUpdate) {
            config.localUpdate(config.type === 'date' ? payloadValue : editingValue.value);
        } else {
            // For date — store the formatted string, not the Date object
            school_profile.value[config.payloadKey] = config.type === 'date'
                ? payloadValue
                : editingValue.value;
        }

        WaitingDialog.value = false;
        toast.add({
            severity: 'success',
            summary:  'Saved',
            detail:   'Field updated successfully.',
            life:     3000,
        });
        cancelEdit();

    } catch (error) {
        WaitingDialog.value = false;
        if (error.response?.status === 422) {
            toast.add({
                severity: 'warn',
                summary:  'Validation Failed!',
                detail:   Object.values(error.response.data).flat().join('\n'),
                life:     5000,
            });
        } else {
            toast.add({
                severity: 'error',
                summary:  'Failed',
                detail:   'Could not save. Please try again.',
                life:     3000,
            });
        }
        console.error('saveField error:', error);
    } finally {
        savingField.value = null;
    }
};

const loading = ref(true);

onMounted(() => {
    fetchDataUserRole();
});
</script>
<template>
    <Fluid>
        <div class="bg-surface-0 dark:bg-surface-900 px-6 py-8 md:px-12 lg:px-20">
            <div class="flex items-start flex-col md:justify-between md:flex-row">
                <div>
                    <div class="font-bold text-3xl text-surface-900 dark:text-surface-0 mb-4">School Profile - {{ school_years_now ? school_years_now.name : "" }}</div>
                </div>
                <div class="mt-6 md:mt-0 flex items-center">
                    <!-- <Button label="Add" class="mr-3" outlined icon="pi pi-user-plus" />
                    <Button label="Save Changes" icon="pi pi-check" class="whitespace-nowrap" /> -->
                    <span class="me-2">School Years</span>
                        <Select v-model="school_years_now" showClear :options="school_years" optionLabel="name" placeholder="Select Year" class="w-full" @change="handleSchoolYearsChange">
                        </Select>
                </div>
            </div>
        </div>
        <div class="bg-surface-0 dark:bg-surface-900 px-6 py-8 md:px-12 lg:px-20 mt-4 mb-4">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2 pb-4">
                    <div class="font-semibold text-xl text-surface-900 dark:text-surface-0 leading-tight">A. School Identity</div>
                    <!-- <div class="text-surface-500 dark:text-surface-300 text-base leading-tight">Morbi tristique blandit turpis. In viverra ligula id nulla hendrerit rutrum.</div> -->
                </div>

                <div class="border-t border-surface-200 dark:border-surface-700" />

                <div class="flex flex-col gap-4">
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">Name</div>
                            <div class="flex-1">
                                <span v-if="editingField !== 'education_school.name'"
                                    class="text-surface-900 dark:text-surface-0 text-base">
                                    {{ school_profile?.education_school?.name ?? "" }}
                                    <Skeleton v-if="loading == true" width="3rem" height="2rem" />
                                </span>
                                <div v-else class="flex flex-col gap-2">
                                    <div class="flex items-center gap-2">
                                        <AutoComplete
                                            v-model="editingSelected"
                                            :suggestions="filteredOptions"
                                            optionLabel="name"
                                            placeholder="Search education school..."
                                            class="flex-1"
                                            forceSelection
                                            @complete="searchOptions($event, 'education_school.name')"
                                        />
                                        <Button
                                            icon="pi pi-check"
                                            severity="success"
                                            rounded
                                            :loading="savingField === 'education_school.name'"
                                            :disabled="!editingSelected?.id"
                                            @click="saveField('education_school.name')"
                                        />
                                        <Button
                                            icon="pi pi-times"
                                            severity="secondary"
                                            rounded
                                            variant="outlined"
                                            @click="cancelEdit"
                                        />
                                    </div>

                                    <!-- "Not found" hint with link -->
                                    <small class="text-surface-500 flex items-center gap-1">
                                        Can't find what you're looking for?
                                        <RouterLink
                                            :to="fieldConfig['education_school.name'].addNewLink"
                                            class="text-primary-500 hover:underline font-medium flex items-center gap-1"
                                            target="_blank"
                                        >
                                            <i class="pi pi-external-link text-xs" />
                                            {{ fieldConfig['education_school.name'].addNewLabel }}
                                        </RouterLink>
                                    </small>

                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'education_school.name'" class="flex justify-end">
                            <Button
                                icon="pi pi-pen-to-square"
                                rounded outlined severity="secondary"
                                @click="startEdit('education_school.name', school_profile.education_school?.name)"
                            />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">NPSN</div>
                            <div class="flex-1 text-surface-900 dark:text-surface-0 text-base leading-tight">
                                {{ school_profile?.education_school?.npsn ?? "" }}
                                <Skeleton v-if="loading == true" width="3rem" height="2rem" />
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">Status</div>
                            <div class="flex-1 text-surface-900 dark:text-surface-0 text-base leading-tight">
                                {{ school_profile?.education_school?.status_education ?? "" }}
                                <Skeleton v-if="loading == true" width="3rem" height="2rem" />
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">NDS</div>
                            <div class="flex-1">
                                <span v-if="editingField !== 'nds'" class="text-surface-900 dark:text-surface-0 text-base">
                                    {{ school_profile?.nds ?? "" }}
                                    <Skeleton v-if="loading == true" width="3rem" height="2rem" />
                                </span>
                                <div v-else class="flex items-center gap-2">
                                    <InputText v-model="editingValue" class="flex-1" autofocus
                                        @keyup.enter="saveField('nds')" @keyup.escape="cancelEdit" fluid />
                                    <Button icon="pi pi-check" severity="success" rounded
                                        :loading="savingField === 'nds'" :disabled="!editingValue.trim()"
                                        @click="saveField('nds')" />
                                    <Button icon="pi pi-times" severity="secondary" rounded variant="outlined"
                                        @click="cancelEdit" />
                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'nds'" class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary"
                                @click="startEdit('nds', school_profile?.nds)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-start gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">NSS</div>
                            <div class="flex-1">
                                <span v-if="editingField !== 'nss'" class="text-surface-900 dark:text-surface-0 text-base">
                                    {{ school_profile?.nss ?? "" }}
                                    <Skeleton v-if="loading == true" width="3rem" height="2rem" />
                                </span>
                                <div v-else class="flex items-center gap-2">
                                    <InputText v-model="editingValue" class="flex-1" autofocus
                                        @keyup.enter="saveField('nss')" @keyup.escape="cancelEdit" fluid />
                                    <Button icon="pi pi-check" severity="success" rounded
                                        :loading="savingField === 'nss'" :disabled="!editingValue.trim()"
                                        @click="saveField('nss')" />
                                    <Button icon="pi pi-times" severity="secondary" rounded variant="outlined"
                                        @click="cancelEdit" />
                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'nss'" class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary"
                                @click="startEdit('nss', school_profile?.nss)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">School Address</div>
                            <div class="flex-1 text-surface-900 dark:text-surface-0 text-base leading-tight">
                                {{ school_profile?.education_school?.address ?? "" }}
                                <Skeleton v-if="loading == true" width="3rem" height="2rem" />
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">ZIP Code</div>
                            <div class="flex-1 text-surface-900 dark:text-surface-0 text-base leading-tight">
                                -
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" icon-only class="shrink-0" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">Official Number</div>
                            <div class="flex-1">
                                <span v-if="editingField !== 'official_number'" class="text-surface-900 dark:text-surface-0 text-base">
                                    {{ school_profile?.official_number ?? "" }}
                                    <Skeleton v-if="loading == true" width="3rem" height="2rem" />
                                </span>
                                <div v-else class="flex items-center gap-2">
                                    <InputText v-model="editingValue" class="flex-1" autofocus
                                        @keyup.enter="saveField('official_number')" @keyup.escape="cancelEdit" fluid />
                                    <Button icon="pi pi-check" severity="success" rounded
                                        :loading="savingField === 'official_number'" :disabled="!editingValue.trim()"
                                        @click="saveField('official_number')" />
                                    <Button icon="pi pi-times" severity="secondary" rounded variant="outlined"
                                        @click="cancelEdit" />
                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'official_number'" class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary"
                                @click="startEdit('official_number', school_profile?.official_number)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">Email</div>
                            <div class="flex-1">
                                <span v-if="editingField !== 'email'" class="text-surface-900 dark:text-surface-0 text-base">
                                    {{ school_profile?.email ?? "" }}
                                    <Skeleton v-if="loading == true" width="3rem" height="2rem" />
                                </span>
                                <div v-else class="flex items-center gap-2">
                                    <InputText v-model="editingValue" class="flex-1" autofocus
                                        @keyup.enter="saveField('email')" @keyup.escape="cancelEdit" fluid />
                                    <Button icon="pi pi-check" severity="success" rounded
                                        :loading="savingField === 'email'" :disabled="!editingValue.trim()"
                                        @click="saveField('email')" />
                                    <Button icon="pi pi-times" severity="secondary" rounded variant="outlined"
                                        @click="cancelEdit" />
                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'email'" class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary"
                                @click="startEdit('email', school_profile?.email)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">Website</div>
                            <div class="flex-1">
                                <span v-if="editingField !== 'website'" class="text-surface-900 dark:text-surface-0 text-base">
                                    {{ school_profile?.website ?? "" }}
                                    <Skeleton v-if="loading == true" width="3rem" height="2rem" />
                                </span>
                                <div v-else class="flex items-center gap-2">
                                    <InputText v-model="editingValue" class="flex-1" autofocus
                                        @keyup.enter="saveField('website')" @keyup.escape="cancelEdit" fluid />
                                    <Button icon="pi pi-check" severity="success" rounded
                                        :loading="savingField === 'website'" :disabled="!editingValue.trim()"
                                        @click="saveField('website')" />
                                    <Button icon="pi pi-times" severity="secondary" rounded variant="outlined"
                                        @click="cancelEdit" />
                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'website'" class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary"
                                @click="startEdit('website', school_profile?.website)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />
                </div>
            </div>
        </div>
        <div class="bg-surface-0 dark:bg-surface-900 px-6 py-8 md:px-12 lg:px-20 mb-4">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2 pb-4">
                    <div class="font-semibold text-xl text-surface-900 dark:text-surface-0 leading-tight">B. Principal Identity</div>
                    <!-- <div class="text-surface-500 dark:text-surface-300 text-base leading-tight">Morbi tristique blandit turpis. In viverra ligula id nulla hendrerit rutrum.</div> -->
                </div>

                <div class="border-t border-surface-200 dark:border-surface-700" />

                <div class="flex flex-col gap-4">
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">Name</div>
                            <div class="flex-1">
                                <span v-if="editingField !== 'principal.name'"
                                    class="text-surface-900 dark:text-surface-0 text-base">
                                    {{ school_profile?.principal?.name ?? '-' }}
                                    <Skeleton v-if="loading == true" width="3rem" height="2rem" />
                                </span>
                                <div v-else class="flex flex-col gap-2">
                                    <div class="flex items-center gap-2">
                                        <AutoComplete
                                            v-model="editingSelected"
                                            :suggestions="filteredOptions"
                                            optionLabel="name"
                                            placeholder="Search user..."
                                            class="flex-1"
                                            forceSelection
                                            @complete="searchOptions($event, 'principal.name')"
                                        />
                                        <Button
                                            icon="pi pi-check"
                                            severity="success"
                                            rounded
                                            :loading="savingField === 'principal.name'"
                                            :disabled="!editingSelected?.id"
                                            @click="saveField('principal.name')"
                                        />
                                        <Button
                                            icon="pi pi-times"
                                            severity="secondary"
                                            rounded
                                            variant="outlined"
                                            @click="cancelEdit"
                                        />
                                    </div>

                                    <!-- "Not found" hint with link -->
                                    <small class="text-surface-500 flex items-center gap-1">
                                        Can't find what you're looking for?
                                        <RouterLink
                                            :to="fieldConfig['principal.name'].addNewLink"
                                            class="text-primary-500 hover:underline font-medium flex items-center gap-1"
                                            target="_blank"
                                        >
                                            <i class="pi pi-external-link text-xs" />
                                            {{ fieldConfig['principal.name'].addNewLabel }}
                                        </RouterLink>
                                    </small>

                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'principal.name'" class="flex justify-end">
                            <Button
                                icon="pi pi-pen-to-square"
                                rounded outlined severity="secondary"
                                @click="startEdit('principal.name', school_profile.principal?.name)"
                            />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">NRKS</div>
                            <div class="flex-1">
                                <span v-if="editingField !== 'nrks'" class="text-surface-900 dark:text-surface-0 text-base">
                                    {{ school_profile?.nrks ?? "" }}
                                    <Skeleton v-if="loading == true" width="3rem" height="2rem" />
                                </span>
                                <div v-else class="flex items-center gap-2">
                                    <InputText v-model="editingValue" class="flex-1" autofocus
                                        @keyup.enter="saveField('nrks')" @keyup.escape="cancelEdit" fluid />
                                    <Button icon="pi pi-check" severity="success" rounded
                                        :loading="savingField === 'nrks'" :disabled="!editingValue.trim()"
                                        @click="saveField('nrks')" />
                                    <Button icon="pi pi-times" severity="secondary" rounded variant="outlined"
                                        @click="cancelEdit" />
                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'nrks'" class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary"
                                @click="startEdit('nrks', school_profile?.nrks)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">NUPTK</div>
                            <div class="flex-1 text-surface-900 dark:text-surface-0 text-base leading-tight">
                                (Not Yet)*
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary" icon-only class="shrink-0" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">TMT Principal</div>
                            <div class="flex-1">
                                <span v-if="editingField !== 'tmt_principal'" class="text-surface-900 dark:text-surface-0 text-base">
                                    {{ school_profile?.tmt_principal ?? "" }}
                                    <Skeleton v-if="loading == true" width="3rem" height="2rem" />
                                </span>
                                <div v-else class="flex items-center gap-2">
                                    <!-- <InputText v-model="editingValue" class="flex-1" autofocus
                                        @keyup.enter="saveField('tmt_principal')" @keyup.escape="cancelEdit" fluid /> -->
                                    <DatePicker v-model="editingValue" class="flex-1" dateFormat="yy-mm-dd"
                                        @keyup.escape="cancelEdit" fluid />
                                    <Button icon="pi pi-check" severity="success" rounded
                                        :loading="savingField === 'tmt_principal'" :disabled="!editingValue"
                                        @click="saveField('tmt_principal')" />
                                    <Button icon="pi pi-times" severity="secondary" rounded variant="outlined"
                                        @click="cancelEdit" />
                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'tmt_principal'" class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary"
                                @click="startEdit('tmt_principal', school_profile?.tmt_principal)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />
                </div>
            </div>
        </div>
        <div class="bg-surface-0 dark:bg-surface-900 px-6 py-8 md:px-12 lg:px-20">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2 pb-4">
                    <div class="font-semibold text-xl text-surface-900 dark:text-surface-0 leading-tight">C. Committee Identity</div>
                    <!-- <div class="text-surface-500 dark:text-surface-300 text-base leading-tight">Morbi tristique blandit turpis. In viverra ligula id nulla hendrerit rutrum.</div> -->
                </div>

                <div class="border-t border-surface-200 dark:border-surface-700" />

                <div class="flex flex-col gap-4">
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">Name</div>
                            <div class="flex-1">
                                <span v-if="editingField !== 'school_committee_name'" class="text-surface-900 dark:text-surface-0 text-base">
                                    {{ school_profile?.school_committee_name ?? "" }}
                                    <Skeleton v-if="loading == true" width="3rem" height="2rem" />
                                </span>
                                <div v-else class="flex items-center gap-2">
                                    <InputText v-model="editingValue" class="flex-1" autofocus
                                        @keyup.enter="saveField('school_committee_name')" @keyup.escape="cancelEdit" fluid />
                                    <Button icon="pi pi-check" severity="success" rounded
                                        :loading="savingField === 'school_committee_name'" :disabled="!editingValue.trim()"
                                        @click="saveField('school_committee_name')" />
                                    <Button icon="pi pi-times" severity="secondary" rounded variant="outlined"
                                        @click="cancelEdit" />
                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'school_committee_name'" class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary"
                                @click="startEdit('school_committee_name', school_profile?.school_committee_name)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />

                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-[140px] text-surface-900 dark:text-surface-0 font-medium text-base leading-tight">Call Number</div>
                            <div class="flex-1">
                                <span v-if="editingField !== 'school_committee_number'" class="text-surface-900 dark:text-surface-0 text-base">
                                    {{ school_profile?.school_committee_number ?? "" }}
                                    <Skeleton v-if="loading == true" width="3rem" height="2rem" />
                                </span>
                                <div v-else class="flex items-center gap-2">
                                    <InputText v-model="editingValue" class="flex-1" autofocus
                                        @keyup.enter="saveField('school_committee_number')" @keyup.escape="cancelEdit" fluid />
                                    <Button icon="pi pi-check" severity="success" rounded
                                        :loading="savingField === 'school_committee_number'" :disabled="!editingValue.trim()"
                                        @click="saveField('school_committee_number')" />
                                    <Button icon="pi pi-times" severity="secondary" rounded variant="outlined"
                                        @click="cancelEdit" />
                                </div>
                            </div>
                        </div>
                        <div v-if="editingField !== 'school_committee_number'" class="flex justify-end">
                            <Button icon="pi pi-pen-to-square" rounded outlined severity="secondary"
                                @click="startEdit('school_committee_number', school_profile?.school_committee_number)" />
                        </div>
                    </div>

                    <div class="border-t border-surface-200 dark:border-surface-700" />
                </div>
            </div>
        </div>
    </Fluid>

    <!-- Confirm Edit Dialog -->
    <Dialog v-model:visible="EditOrNewDialog" :style="{ width: '450px' }" :header="entityLabel+' Details'" :modal="true">
        <div class="flex flex-col gap-6">
            <div>
                <label for="name" class="block font-bold mb-3">Name</label>
                <InputText id="name" v-model="singleData.name" required="true" autofocus :invalid="submitted && !singleData.name" fluid />
                <small v-if="submitted && !singleData.name" class="text-red-500">Name is required.</small>
                <small v-else-if="singleData.name && singleData.name.trim().length > 0 && singleData.name.trim().length <= 3" class="text-yellow-500">Name must be at least 4 characters.</small>
                <small v-else-if="singleData.name && singleData.name === previousData.name" class="text-blue-500">Current Name (no changes).</small>
                <small v-else-if="isCat1Used" class="text-red-500">This name is already taken.</small>
                <small v-else-if="singleData.name && singleData.name.trim().length > 3" class="text-green-500">Name is available!</small>

            </div>
        </div>

        <template #footer>
            <Button label="Cancel" icon="pi pi-times" text @click="hideDialog" />
            <Button v-if="!isCat1Used && validFieldCount > 0" label="Save" icon="pi pi-check" @click="saveSingleData" />
        </template>
    </Dialog>
    <!-- Loading Dialog -->
    <Dialog v-model:visible="WaitingDialog" modal header="Processing Request">
        <div class="text-center justify-content-center align-items-center">
            <i class="pi pi-spin pi-spinner" style="font-size: 2rem; color: green"></i>
            <p class="text-success mt-3" style="font-size: 1.1rem; font-weight: 500;">
            Please wait, your data is being processed...
        </p>
        </div>
    </Dialog>
    <!-- Error Dialog -->
    <Dialog v-model:visible="ErrorDialog" modal :header="titleError" style=" width: 25rem; color: red;">
        <div style="color: black;">
            {{ fieldError }}
        </div>
    </Dialog>
</template>
