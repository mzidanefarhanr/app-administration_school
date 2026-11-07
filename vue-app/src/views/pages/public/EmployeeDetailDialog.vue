<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';

const { t } = useI18n();

const props = defineProps({
    visible: { type: Boolean, default: false },
    employee: { type: Object, default: null },
    canManage: { type: Boolean, default: false }
});

const emit = defineEmits(['update:visible', 'edit', 'archive', 'restore', 'link', 'unlink', 'generate-account', 'delete-account']);

const hasSensitive = computed(() => props.employee !== null && props.employee !== undefined && props.employee.nik !== undefined);
const isArchived = computed(() => Boolean(props.employee?.archived_at));

const relationName = (relation) => relation?.display_name ?? relation?.name ?? '-';
const isTeacher = computed(() => props.employee?.employee_type?.name === 'Pendidik');
const recordLabel = computed(() => (isTeacher.value ? t('employees.entity.teacher') : t('employees.entity.employee')));

const value = (key) => {
    const raw = props.employee?.[key];

    return raw === null || raw === undefined || raw === '' ? '-' : raw;
};

const accountLabel = computed(() => {
    if (!props.employee?.account?.linked) return t('employees.detail.account.notLinked');

    return props.employee.account.name ? `${props.employee.account.name} (${props.employee.account.username ?? '-'})` : t('employees.detail.account.numbered', { id: props.employee.account.user_id });
});
</script>

<template>
    <Dialog :visible="visible" :header="t('employees.detail.title', { entity: recordLabel })" modal :style="{ width: '56rem' }" :breakpoints="{ '1200px': '92vw', '768px': '96vw' }" @update:visible="emit('update:visible', $event)">
        <div v-if="employee" class="flex flex-col gap-5">
            <div class="flex items-center gap-3">
                <Tag v-if="isArchived" :value="t('employees.detail.status.archived')" severity="danger" />
                <Tag v-else :value="t('employees.detail.status.active')" severity="success" />
                <span class="text-xl font-semibold">{{ employee.name }}</span>
            </div>

            <div>
                <h3 class="font-semibold mb-2">{{ t('employees.detail.sections.employment') }}</h3>
                <div class="grid grid-cols-12 gap-3 text-sm">
                    <div class="col-span-12 md:col-span-6">
                        {{ t('employees.detail.fields.type') }}: <strong>{{ relationName(employee.employee_type) }}</strong>
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        {{ t('employees.detail.fields.status') }}: <strong>{{ relationName(employee.employee_status) }}</strong>
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        {{ t('employees.detail.fields.employment') }}: <strong>{{ relationName(employee.employment) }}</strong>
                    </div>
                    <div v-if="isTeacher" class="col-span-12 md:col-span-6">
                        {{ t('employees.detail.fields.subject') }}: <strong>{{ relationName(employee.subject) }}</strong>
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        {{ t('employees.detail.fields.gender') }}: <strong>{{ relationName(employee.gender) }}</strong>
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        {{ t('employees.detail.fields.nuptk') }}: <strong>{{ value('nuptk') }}</strong>
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        {{ t('employees.detail.fields.tmt') }}: <strong>{{ value('tmt_employee') }}</strong>
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        {{ t('employees.detail.fields.exitDate') }}: <strong>{{ value('exit_date') }}</strong>
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        {{ t('employees.detail.fields.version') }}: <strong>{{ employee.version }}</strong>
                    </div>
                    <div v-if="isTeacher" class="col-span-12 md:col-span-6">
                        {{ t('employees.form.fields.teachingHoursCertificate') }}: <strong>{{ value('certificate_of_teaching_hours') }}</strong>
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        {{ t('employees.form.fields.appointmentCertificate') }}: <strong>{{ value('appointment_certificate') }}</strong>
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        {{ t('employees.detail.fields.dapodikStatus') }}: <strong>{{ value('dapodik_status') }}</strong>
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        {{ t('employees.detail.fields.certificationStatus') }}: <strong>{{ value('certification_status') }}</strong>
                    </div>
                    <div v-if="isArchived" class="col-span-12 md:col-span-6">
                        {{ t('employees.detail.fields.archivedAt') }}: <strong>{{ value('archived_at') }}</strong>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="font-semibold mb-2">{{ t('employees.detail.sections.account') }}</h3>
                <p class="text-sm">{{ accountLabel }}</p>
            </div>

            <template v-if="hasSensitive">
                <div>
                    <h3 class="font-semibold mb-2">{{ t('employees.detail.sections.personal') }}</h3>
                    <div class="grid grid-cols-12 gap-3 text-sm">
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.nik') }}: <strong>{{ value('nik') }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.npwp') }}: <strong>{{ value('npwp') }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.familyCard') }}: <strong>{{ value('no_kk') }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.religion') }}: <strong>{{ relationName(employee.religion) }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.district') }}: <strong>{{ relationName(employee.district) }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.maritalStatus') }}: <strong>{{ relationName(employee.marital_status) }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.placeOfBirth') }}: <strong>{{ value('place_of_birth') }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.dateOfBirth') }}: <strong>{{ value('date_of_birth') }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.email') }}: <strong>{{ value('email') }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.whatsapp') }}: <strong>{{ value('wa_number') }}</strong>
                        </div>
                        <div class="col-span-12">
                            {{ t('employees.detail.fields.address') }}: <strong>{{ value('address') }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.mothersName') }}: <strong>{{ value('biological_mothers_name') }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.partnersName') }}: <strong>{{ value('partners_name') }}</strong>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="font-semibold mb-2">{{ t('employees.detail.sections.education') }}</h3>
                    <div class="grid grid-cols-12 gap-3 text-sm">
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.elementarySchool') }}: <strong>{{ employee.education?.elementary_school_name || '-' }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.form.education.nisn') }} (SD): <strong>{{ employee.education?.nisn || '-' }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.juniorHighSchool') }}: <strong>{{ employee.education?.junior_high_school_name || '-' }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.form.education.nisn') }} (SMP): <strong>{{ employee.education?.junior_high_school_nisn || '-' }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.seniorHighSchool') }}: <strong>{{ employee.education?.senior_high_school_name || '-' }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.seniorHighMajor') }}: <strong>{{ employee.education?.senior_high_school_major || '-' }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.bachelor') }}: <strong>{{ employee.education?.bachelor_campus_name || '-' }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.bachelorMajor') }}: <strong>{{ employee.education?.bachelor_major || '-' }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.master') }}: <strong>{{ employee.education?.master_campus_name || '-' }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.masterMajor') }}: <strong>{{ employee.education?.master_major || '-' }}</strong>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="font-semibold mb-2">{{ t('employees.detail.fields.vervalSection') }}</h3>
                    <div class="grid grid-cols-12 gap-3 text-sm">
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.vervalElementarySchool') }}: <strong>{{ value('verval_elementary_school') }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.vervalJuniorHighSchool') }}: <strong>{{ value('verval_junior_high_school') }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.vervalSeniorHighSchool') }}: <strong>{{ value('verval_senior_high_school') }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.vervalBachelorDegree') }}: <strong>{{ value('verval_bachelor_degree') }}</strong>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            {{ t('employees.detail.fields.vervalDiploma') }}: <strong>{{ value('verval_diploma') }}</strong>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <template #footer>
            <Button :label="t('common.action.close')" severity="secondary" variant="outlined" @click="emit('update:visible', false)" />
            <template v-if="canManage">
                <Button v-if="!isArchived && employee?.account?.linked && !employee?.account?.is_admin" label="Hapus Akun" icon="pi pi-user-minus" severity="danger" variant="outlined" @click="emit('delete-account', employee)" />
                <Button v-if="!isArchived && employee?.account?.is_admin" label="Akun Admin" icon="pi pi-lock" severity="secondary" variant="outlined" disabled />
                <Button v-if="!isArchived && !employee?.account?.linked" label="Generate Akun" icon="pi pi-user-plus" severity="success" variant="outlined" @click="emit('generate-account', employee)" />
                <Button v-if="!isArchived" :label="t('employees.detail.actions.archive')" icon="pi pi-inbox" severity="danger" variant="outlined" @click="emit('archive', employee)" />
                <Button v-if="isArchived" :label="t('employees.detail.actions.restore')" icon="pi pi-replay" severity="success" variant="outlined" @click="emit('restore', employee)" />
                <Button v-if="!isArchived" :label="t('common.action.edit')" icon="pi pi-pencil" @click="emit('edit', employee)" />
            </template>
        </template>
    </Dialog>
</template>
