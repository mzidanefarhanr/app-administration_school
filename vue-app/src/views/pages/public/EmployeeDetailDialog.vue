<script setup>
import { computed } from 'vue';
import Button from 'primevue/button';

const props = defineProps({
    visible: { type: Boolean, default: false },
    employee: { type: Object, default: null },
    canManage: { type: Boolean, default: false },
});

const emit = defineEmits(['update:visible', 'edit', 'archive', 'restore', 'link', 'unlink']);

const hasSensitive = computed(() => props.employee !== null && props.employee !== undefined && props.employee.nik !== undefined);
const isArchived = computed(() => Boolean(props.employee?.archived_at));

const relationName = (relation) => relation?.display_name ?? relation?.name ?? '-';
const recordLabel = computed(() => props.employee?.employee_type?.name === 'Pendidik' ? 'Teacher' : 'Employee');

const value = (key) => {
    const raw = props.employee?.[key];

    return raw === null || raw === undefined || raw === '' ? '-' : raw;
};

const accountLabel = computed(() => {
    if (!props.employee?.account?.linked) return 'Not linked';

    return props.employee.account.name
        ? `${props.employee.account.name} (${props.employee.account.username ?? '-'})`
        : `Account #${props.employee.account.user_id}`;
});
</script>

<template>
    <Dialog
        :visible="visible"
        :header="`${recordLabel} Detail`"
        modal
        :style="{ width: '56rem' }"
        :breakpoints="{ '1200px': '92vw', '768px': '96vw' }"
        @update:visible="emit('update:visible', $event)">
        <div v-if="employee" class="flex flex-col gap-5">
            <div class="flex items-center gap-3">
                <Tag v-if="isArchived" value="Archived" severity="danger" />
                <Tag v-else value="Active" severity="success" />
                <span class="text-xl font-semibold">{{ employee.name }}</span>
            </div>

            <div>
                <h3 class="font-semibold mb-2">Employment</h3>
                <div class="grid grid-cols-12 gap-3 text-sm">
                    <div class="col-span-12 md:col-span-6">Type: <strong>{{ relationName(employee.employee_type) }}</strong></div>
                    <div class="col-span-12 md:col-span-6">Status: <strong>{{ relationName(employee.employee_status) }}</strong></div>
                    <div class="col-span-12 md:col-span-6">Employment: <strong>{{ relationName(employee.employment) }}</strong></div>
                    <div class="col-span-12 md:col-span-6">Subject: <strong>{{ relationName(employee.subject) }}</strong></div>
                    <div class="col-span-12 md:col-span-6">Gender: <strong>{{ relationName(employee.gender) }}</strong></div>
                    <div class="col-span-12 md:col-span-6">NUPTK: <strong>{{ value('nuptk') }}</strong></div>
                    <div class="col-span-12 md:col-span-6">Employment Start Date (TMT): <strong>{{ value('tmt_employee') }}</strong></div>
                    <div class="col-span-12 md:col-span-6">Exit Date: <strong>{{ value('exit_date') }}</strong></div>
                    <div class="col-span-12 md:col-span-6">Record Version: <strong>{{ employee.version }}</strong></div>
                    <div v-if="isArchived" class="col-span-12 md:col-span-6">Archived At: <strong>{{ value('archived_at') }}</strong></div>
                </div>
            </div>

            <div>
                <h3 class="font-semibold mb-2">Account</h3>
                <p class="text-sm">{{ accountLabel }}</p>
            </div>

            <template v-if="hasSensitive">
                <div>
                    <h3 class="font-semibold mb-2">Personal Data</h3>
                    <div class="grid grid-cols-12 gap-3 text-sm">
                        <div class="col-span-12 md:col-span-6">NIK: <strong>{{ value('nik') }}</strong></div>
                        <div class="col-span-12 md:col-span-6">Tax Identification Number (NPWP): <strong>{{ value('npwp') }}</strong></div>
                        <div class="col-span-12 md:col-span-6">Family Card: <strong>{{ value('no_kk') }}</strong></div>
                        <div class="col-span-12 md:col-span-6">Religion: <strong>{{ relationName(employee.religion) }}</strong></div>
                        <div class="col-span-12 md:col-span-6">District: <strong>{{ relationName(employee.district) }}</strong></div>
                        <div class="col-span-12 md:col-span-6">Marital Status: <strong>{{ relationName(employee.marital_status) }}</strong></div>
                        <div class="col-span-12 md:col-span-6">Place of Birth: <strong>{{ value('place_of_birth') }}</strong></div>
                        <div class="col-span-12 md:col-span-6">Date of Birth: <strong>{{ value('date_of_birth') }}</strong></div>
                        <div class="col-span-12 md:col-span-6">Email: <strong>{{ value('email') }}</strong></div>
                        <div class="col-span-12 md:col-span-6">WhatsApp: <strong>{{ value('wa_number') }}</strong></div>
                        <div class="col-span-12">Address: <strong>{{ value('address') }}</strong></div>
                        <div class="col-span-12 md:col-span-6">Mother's Name: <strong>{{ value('biological_mothers_name') }}</strong></div>
                        <div class="col-span-12 md:col-span-6">Partner's Name: <strong>{{ value('partners_name') }}</strong></div>
                    </div>
                </div>

                <div>
                    <h3 class="font-semibold mb-2">Education</h3>
                    <div class="grid grid-cols-12 gap-3 text-sm">
                        <div class="col-span-12 md:col-span-6">Bachelor: <strong>{{ employee.education?.bachelor_campus_name || '-' }}</strong></div>
                        <div class="col-span-12 md:col-span-6">Bachelor Major: <strong>{{ employee.education?.bachelor_major || '-' }}</strong></div>
                        <div class="col-span-12 md:col-span-6">Master: <strong>{{ employee.education?.master_campus_name || '-' }}</strong></div>
                        <div class="col-span-12 md:col-span-6">Master Major: <strong>{{ employee.education?.master_major || '-' }}</strong></div>
                    </div>
                </div>
            </template>
        </div>

        <template #footer>
            <Button label="Close" severity="secondary" variant="outlined" @click="emit('update:visible', false)" />
            <template v-if="canManage">
                <Button v-if="!isArchived && employee?.account?.linked" label="Unlink Account" icon="pi pi-user-minus" severity="warn" variant="outlined" @click="emit('unlink', employee)" />
                <Button v-if="!isArchived && !employee?.account?.linked" label="Link Account" icon="pi pi-user-plus" severity="info" variant="outlined" @click="emit('link', employee)" />
                <Button v-if="!isArchived" label="Archive" icon="pi pi-inbox" severity="danger" variant="outlined" @click="emit('archive', employee)" />
                <Button v-if="isArchived" label="Restore" icon="pi pi-replay" severity="success" variant="outlined" @click="emit('restore', employee)" />
                <Button v-if="!isArchived" label="Edit" icon="pi pi-pencil" @click="emit('edit', employee)" />
            </template>
        </template>
    </Dialog>
</template>
