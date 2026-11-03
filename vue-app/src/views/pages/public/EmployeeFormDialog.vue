<script setup>
import { ref, computed, watch } from 'vue';
import Button from 'primevue/button';
import apiLogin from '@/apiLogin';
import { normalizeEmployeePayload, firstValidationMessage } from './employeeDirectoryParams.mjs';

const props = defineProps({
    visible: { type: Boolean, default: false },
    employee: { type: Object, default: null },
    fixedTypeId: { type: Number, default: null },
    options: { type: Object, default: () => ({}) }
});

const emit = defineEmits(['update:visible', 'saved']);

const emptyForm = () => ({
    name: '',
    nik: '',
    nuptk: '',
    employee_type_id: props.fixedTypeId,
    employee_status_id: null,
    employment_id: null,
    subject_id: null,
    religion_id: null,
    district_id: null,
    marital_status_id: null,
    gender_id: null,
    npwp: '',
    no_kk: '',
    place_of_birth: '',
    date_of_birth: '',
    address: '',
    rt: '',
    rw: '',
    zip_code: '',
    wa_number: '',
    email: '',
    appointment_certificate: '',
    tmt_employee: '',
    certificate_of_teaching_hours: '',
    biological_mothers_name: '',
    partners_name: '',
    check_dapodik: false,
    government_certificate: false,
    exit_date: '',
    elementary_school_name: '',
    elementary_school_entry: '',
    elementary_school_graduation: '',
    nisn: '',
    elementary_school_passing_grade: '',
    junior_high_school_name: '',
    junior_high_school_entry: '',
    junior_high_school_graduation: '',
    junior_high_school_passing_grade: '',
    senior_high_school_name: '',
    senior_high_school_entry: '',
    senior_high_school_graduation: '',
    senior_high_school_passing_grade: '',
    bachelor_campus_name: '',
    bachelor_major: '',
    bachelor_faculty: '',
    bachelor_entry: '',
    bachelor_graduation: '',
    bachelor_nim: '',
    bachelor_passing_grade: '',
    master_campus_name: '',
    master_major: '',
    master_faculty: '',
    master_entry: '',
    master_graduation: '',
    master_nim: '',
    master_passing_grade: ''
});

const form = ref(emptyForm());
const snapshot = ref('');
const version = ref(null);
const errors = ref({});
const generalMessage = ref('');
const saving = ref(false);
const conflict = ref(false);
const conflictVersion = ref(null);

const isEdit = computed(() => props.employee !== null && props.employee !== undefined);
const isDirty = computed(() => JSON.stringify(form.value) !== snapshot.value);

const selectedTypeName = computed(() => {
    const type = (props.options.employeeTypes ?? []).find((item) => item.id === form.value.employee_type_id);
    return type?.name ?? '';
});

const isTeacher = computed(() => selectedTypeName.value === 'Pendidik');
const recordLabel = computed(() => (isTeacher.value ? 'Teacher' : 'Employee'));

const educationSections = [
    {
        title: 'Elementary School',
        fields: [
            { key: 'elementary_school_name', label: 'School Name' },
            { key: 'elementary_school_entry', label: 'Entry Date', type: 'date' },
            { key: 'elementary_school_graduation', label: 'Graduation Date', type: 'date' },
            { key: 'nisn', label: 'NISN' },
            { key: 'elementary_school_passing_grade', label: 'Passing Grade' }
        ]
    },
    {
        title: 'Junior High School',
        fields: [
            { key: 'junior_high_school_name', label: 'School Name' },
            { key: 'junior_high_school_entry', label: 'Entry Date', type: 'date' },
            { key: 'junior_high_school_graduation', label: 'Graduation Date', type: 'date' },
            { key: 'junior_high_school_passing_grade', label: 'Passing Grade' }
        ]
    },
    {
        title: 'Senior High School',
        fields: [
            { key: 'senior_high_school_name', label: 'School Name' },
            { key: 'senior_high_school_entry', label: 'Entry Date', type: 'date' },
            { key: 'senior_high_school_graduation', label: 'Graduation Date', type: 'date' },
            { key: 'senior_high_school_passing_grade', label: 'Passing Grade' }
        ]
    },
    {
        title: 'Bachelor Degree',
        fields: [
            { key: 'bachelor_campus_name', label: 'Campus' },
            { key: 'bachelor_major', label: 'Major' },
            { key: 'bachelor_faculty', label: 'Faculty' },
            { key: 'bachelor_entry', label: 'Entry Date', type: 'date' },
            { key: 'bachelor_graduation', label: 'Graduation Date', type: 'date' },
            { key: 'bachelor_nim', label: 'NIM' },
            { key: 'bachelor_passing_grade', label: 'Passing Grade' }
        ]
    },
    {
        title: 'Master Degree',
        fields: [
            { key: 'master_campus_name', label: 'Campus' },
            { key: 'master_major', label: 'Major' },
            { key: 'master_faculty', label: 'Faculty' },
            { key: 'master_entry', label: 'Entry Date', type: 'date' },
            { key: 'master_graduation', label: 'Graduation Date', type: 'date' },
            { key: 'master_nim', label: 'NIM' },
            { key: 'master_passing_grade', label: 'Passing Grade' }
        ]
    }
];

const buildForm = (employee) => {
    const base = emptyForm();

    if (!employee) return base;

    const merged = { ...base };
    const education = employee.education ?? {};

    Object.keys(base).forEach((key) => {
        if (Object.prototype.hasOwnProperty.call(employee, key)) {
            merged[key] = employee[key] ?? '';
        }
        if (Object.prototype.hasOwnProperty.call(education, key)) {
            merged[key] = education[key] ?? '';
        }
    });

    merged.employee_type_id = props.fixedTypeId ?? employee.employee_type?.id ?? base.employee_type_id;
    merged.subject_id = employee.subject?.id ?? null;
    merged.employee_status_id = employee.employee_status?.id ?? null;
    merged.employment_id = employee.employment?.id ?? null;
    merged.religion_id = employee.religion?.id ?? null;
    merged.district_id = employee.district?.id ?? null;
    merged.marital_status_id = employee.marital_status?.id ?? null;
    merged.gender_id = employee.gender?.id ?? null;

    Object.keys(merged).forEach((key) => {
        if (merged[key] === null || merged[key] === undefined) merged[key] = '';
    });

    return merged;
};

const reset = () => {
    form.value = buildForm(isEdit.value ? props.employee : null);
    snapshot.value = JSON.stringify(form.value);
    version.value = isEdit.value ? props.employee.version : null;
    errors.value = {};
    generalMessage.value = '';
    conflict.value = false;
    conflictVersion.value = null;
    saving.value = false;
};

watch(
    () => props.visible,
    (visible) => {
        if (visible) reset();
    }
);

const errorFor = (field) => (Array.isArray(errors.value[field]) ? errors.value[field][0] : '');

const requestClose = () => {
    if (saving.value) return;
    if (isDirty.value && !window.confirm('Discard unsaved changes?')) return;
    emit('update:visible', false);
};

const submit = async () => {
    if (saving.value) return;

    saving.value = true;
    errors.value = {};
    generalMessage.value = '';
    conflict.value = false;

    const payload = normalizeEmployeePayload({ ...form.value, employee_type_id: props.fixedTypeId });
    if (!isTeacher.value) payload.subject_id = null;
    if (isEdit.value) payload.version = version.value;

    try {
        const response = isEdit.value ? await apiLogin.put(`/api/employees/${employeeId()}`, payload) : await apiLogin.post('/api/employees', payload);

        snapshot.value = JSON.stringify(form.value);
        emit('saved', response.data?.data ?? null);
        emit('update:visible', false);
    } catch (error) {
        const status = error.response?.status;

        if (status === 409) {
            conflict.value = true;
            conflictVersion.value = error.response?.data?.current_version ?? null;
            generalMessage.value = error.response?.data?.message ?? 'This record was updated by another user.';
        } else if (status === 422) {
            errors.value = error.response?.data?.errors ?? {};
            generalMessage.value = firstValidationMessage(error.response) ?? 'Please review the highlighted fields.';
        } else {
            generalMessage.value = firstValidationMessage(error.response) ?? 'Failed to save the employee. Please try again.';
        }
    } finally {
        saving.value = false;
    }
};

const employeeId = () => props.employee?.id;

const reloadLatest = async () => {
    if (!isEdit.value) return;

    try {
        const response = await apiLogin.get(`/api/employees/${employeeId()}`);
        form.value = buildForm(response.data?.data ?? null);
        snapshot.value = JSON.stringify(form.value);
        version.value = response.data?.data?.version ?? null;
        conflict.value = false;
        conflictVersion.value = null;
        generalMessage.value = '';
        errors.value = {};
    } catch (error) {
        generalMessage.value = firstValidationMessage(error.response) ?? 'Failed to reload the latest record.';
    }
};
</script>

<template>
    <Dialog :visible="visible" :header="`${isEdit ? 'Edit' : 'New'} ${recordLabel}`" modal :style="{ width: '64rem' }" :breakpoints="{ '1200px': '92vw', '768px': '96vw' }" :closable="!saving" @update:visible="requestClose">
        <div class="flex flex-col gap-5">
            <Message v-if="generalMessage" :severity="conflict ? 'warn' : 'error'" :closable="false">
                {{ generalMessage }}
                <Button v-if="conflict" label="Reload Latest" size="small" class="ml-3" @click="reloadLatest" />
            </Message>

            <div>
                <h3 class="font-semibold mb-3">Identity</h3>
                <div class="grid grid-cols-12 gap-4">
                    <div class="col-span-12 md:col-span-6">
                        <label class="block mb-2">Name *</label>
                        <InputText v-model="form.name" class="w-full" maxlength="100" />
                        <small v-if="errorFor('name')" class="text-red-500">{{ errorFor('name') }}</small>
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        <label class="block mb-2">NIK (16 digits)</label>
                        <InputText v-model="form.nik" class="w-full" maxlength="16" />
                        <small v-if="errorFor('nik')" class="text-red-500">{{ errorFor('nik') }}</small>
                    </div>
                    <div class="col-span-12 md:col-span-4">
                        <label class="block mb-2">NUPTK</label>
                        <InputText v-model="form.nuptk" class="w-full" maxlength="50" />
                        <small v-if="errorFor('nuptk')" class="text-red-500">{{ errorFor('nuptk') }}</small>
                    </div>
                    <div class="col-span-12 md:col-span-4">
                        <label class="block mb-2">Gender *</label>
                        <Select v-model="form.gender_id" :options="options.genders ?? []" optionLabel="display_name" optionValue="id" class="w-full" />
                        <small v-if="errorFor('gender_id')" class="text-red-500">{{ errorFor('gender_id') }}</small>
                    </div>
                    <div class="col-span-12 md:col-span-4">
                        <label class="block mb-2">Religion *</label>
                        <Select v-model="form.religion_id" :options="options.religions ?? []" optionLabel="display_name" optionValue="id" class="w-full" />
                        <small v-if="errorFor('religion_id')" class="text-red-500">{{ errorFor('religion_id') }}</small>
                    </div>
                    <div class="col-span-12 md:col-span-4">
                        <label class="block mb-2">Place of Birth</label>
                        <InputText v-model="form.place_of_birth" class="w-full" maxlength="100" />
                    </div>
                    <div class="col-span-12 md:col-span-4">
                        <label class="block mb-2">Date of Birth</label>
                        <InputText v-model="form.date_of_birth" type="date" class="w-full" />
                        <small v-if="errorFor('date_of_birth')" class="text-red-500">{{ errorFor('date_of_birth') }}</small>
                    </div>
                    <div class="col-span-12 md:col-span-4">
                        <label class="block mb-2">Marital Status *</label>
                        <Select v-model="form.marital_status_id" :options="options.maritalStatuses ?? []" optionLabel="display_name" optionValue="id" class="w-full" />
                        <small v-if="errorFor('marital_status_id')" class="text-red-500">{{ errorFor('marital_status_id') }}</small>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="font-semibold mb-3">Employment</h3>
                <div class="grid grid-cols-12 gap-4">
                    <div class="col-span-12 md:col-span-4">
                        <label class="block mb-2">Employee Type</label>
                        <Select :modelValue="form.employee_type_id" :options="options.employeeTypes ?? []" optionLabel="display_name" optionValue="id" class="w-full" disabled />
                    </div>
                    <div class="col-span-12 md:col-span-4">
                        <label class="block mb-2">Employee Status *</label>
                        <Select v-model="form.employee_status_id" :options="options.employeeStatuses ?? []" optionLabel="display_name" optionValue="id" class="w-full" />
                        <small v-if="errorFor('employee_status_id')" class="text-red-500">{{ errorFor('employee_status_id') }}</small>
                    </div>
                    <div class="col-span-12 md:col-span-4">
                        <label class="block mb-2">Employment *</label>
                        <Select v-model="form.employment_id" :options="options.employments ?? []" optionLabel="display_name" optionValue="id" class="w-full" />
                        <small v-if="errorFor('employment_id')" class="text-red-500">{{ errorFor('employment_id') }}</small>
                    </div>
                    <div v-if="isTeacher" class="col-span-12 md:col-span-4">
                        <label class="block mb-2">Subject *</label>
                        <Select v-model="form.subject_id" :options="options.subjects ?? []" optionLabel="display_name" optionValue="id" class="w-full" />
                        <small v-if="errorFor('subject_id')" class="text-red-500">{{ errorFor('subject_id') }}</small>
                    </div>
                    <div class="col-span-12 md:col-span-4">
                        <label class="block mb-2">Employment Start Date (TMT) *</label>
                        <InputText v-model="form.tmt_employee" type="date" class="w-full" />
                        <small v-if="errorFor('tmt_employee')" class="text-red-500">{{ errorFor('tmt_employee') }}</small>
                    </div>
                    <div class="col-span-12 md:col-span-4">
                        <label class="block mb-2">Exit Date</label>
                        <InputText v-model="form.exit_date" type="date" class="w-full" />
                        <small v-if="errorFor('exit_date')" class="text-red-500">{{ errorFor('exit_date') }}</small>
                    </div>
                    <div class="col-span-12 md:col-span-4">
                        <label class="block mb-2">Appointment Certificate</label>
                        <InputText v-model="form.appointment_certificate" class="w-full" maxlength="255" />
                    </div>
                    <div class="col-span-12 md:col-span-4">
                        <label class="block mb-2">Teaching Hours Certificate</label>
                        <InputText v-model="form.certificate_of_teaching_hours" class="w-full" maxlength="255" />
                    </div>
                    <div class="col-span-12 md:col-span-4 flex items-center gap-4 pt-6">
                        <div class="flex items-center gap-2">
                            <Checkbox v-model="form.check_dapodik" :binary="true" inputId="checkDapodik" />
                            <label for="checkDapodik">Dapodik Checked</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <Checkbox v-model="form.government_certificate" :binary="true" inputId="governmentCertificate" />
                            <label for="governmentCertificate">Government Certificate</label>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="font-semibold mb-3">Contact &amp; Address</h3>
                <div class="grid grid-cols-12 gap-4">
                    <div class="col-span-12 md:col-span-6">
                        <label class="block mb-2">Address</label>
                        <InputText v-model="form.address" class="w-full" maxlength="1000" />
                    </div>
                    <div class="col-span-12 md:col-span-2">
                        <label class="block mb-2">RT</label>
                        <InputText v-model="form.rt" class="w-full" maxlength="5" />
                    </div>
                    <div class="col-span-12 md:col-span-2">
                        <label class="block mb-2">RW</label>
                        <InputText v-model="form.rw" class="w-full" maxlength="5" />
                    </div>
                    <div class="col-span-12 md:col-span-2">
                        <label class="block mb-2">ZIP</label>
                        <InputText v-model="form.zip_code" class="w-full" maxlength="10" />
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        <label class="block mb-2">District *</label>
                        <Select v-model="form.district_id" :options="options.districts ?? []" optionLabel="name" optionValue="id" class="w-full" filter />
                        <small v-if="errorFor('district_id')" class="text-red-500">{{ errorFor('district_id') }}</small>
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        <label class="block mb-2">WhatsApp</label>
                        <InputText v-model="form.wa_number" class="w-full" maxlength="30" />
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        <label class="block mb-2">Email</label>
                        <InputText v-model="form.email" type="email" class="w-full" maxlength="255" />
                        <small v-if="errorFor('email')" class="text-red-500">{{ errorFor('email') }}</small>
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        <label class="block mb-2">Family Card Number</label>
                        <InputText v-model="form.no_kk" class="w-full" maxlength="16" />
                        <small v-if="errorFor('no_kk')" class="text-red-500">{{ errorFor('no_kk') }}</small>
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        <label class="block mb-2">Tax Identification Number (NPWP)</label>
                        <InputText v-model="form.npwp" class="w-full" maxlength="30" />
                    </div>
                </div>
            </div>

            <div>
                <h3 class="font-semibold mb-3">Family</h3>
                <div class="grid grid-cols-12 gap-4">
                    <div class="col-span-12 md:col-span-6">
                        <label class="block mb-2">Biological Mother's Name</label>
                        <InputText v-model="form.biological_mothers_name" class="w-full" maxlength="255" />
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        <label class="block mb-2">Partner's Name</label>
                        <InputText v-model="form.partners_name" class="w-full" maxlength="255" />
                    </div>
                </div>
            </div>

            <div>
                <h3 class="font-semibold mb-3">Education</h3>
                <div v-for="section in educationSections" :key="section.title" class="mb-4">
                    <h4 class="font-medium mb-2">{{ section.title }}</h4>
                    <div class="grid grid-cols-12 gap-4">
                        <div v-for="field in section.fields" :key="field.key" class="col-span-12 md:col-span-4">
                            <label class="block mb-2">{{ field.label }}</label>
                            <InputText v-model="form[field.key]" :type="field.type ?? 'text'" class="w-full" />
                            <small v-if="errorFor(field.key)" class="text-red-500">{{ errorFor(field.key) }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" severity="secondary" variant="outlined" :disabled="saving" @click="requestClose" />
            <Button :label="isEdit ? 'Save Changes' : `Create ${recordLabel}`" icon="pi pi-check" :loading="saving" @click="submit" />
        </template>
    </Dialog>
</template>
