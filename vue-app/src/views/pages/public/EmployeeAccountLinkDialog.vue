<script setup>
import { ref, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import apiLogin from '@/apiLogin';
import { firstValidationMessage } from './employeeDirectoryParams.mjs';

const { t } = useI18n();

const props = defineProps({
    visible: { type: Boolean, default: false },
    employee: { type: Object, default: null }
});

const emit = defineEmits(['update:visible', 'saved', 'conflict']);

const candidates = ref([]);
const search = ref('');
const selectedAccount = ref(null);
const loading = ref(false);
const saving = ref(false);
const generalMessage = ref('');
const fieldError = ref('');
const timer = ref(null);

const isLinked = computed(() => Boolean(props.employee?.account?.linked));

const loadCandidates = async () => {
    loading.value = true;

    try {
        const params = {};
        if (search.value.trim()) params.search = search.value.trim();

        const response = await apiLogin.get('/api/employees/account-candidates', { params });
        candidates.value = response.data?.data ?? [];
    } catch (error) {
        generalMessage.value = firstValidationMessage(error.response) ?? t('employees.accountLink.errors.loadCandidates');
        candidates.value = [];
    } finally {
        loading.value = false;
    }
};

const onSearchInput = () => {
    if (timer.value) clearTimeout(timer.value);
    timer.value = setTimeout(loadCandidates, 300);
};

watch(
    () => props.visible,
    (visible) => {
        if (!visible) return;

        search.value = '';
        selectedAccount.value = null;
        candidates.value = [];
        generalMessage.value = '';
        fieldError.value = '';
        saving.value = false;

        if (!isLinked.value) loadCandidates();
    }
);

const submit = async () => {
    if (saving.value || !props.employee) return;

    saving.value = true;
    generalMessage.value = '';
    fieldError.value = '';

    try {
        if (isLinked.value) {
            await apiLogin.post(`/api/employees/${props.employee.id}/unlink-account`, {
                version: props.employee.version
            });
        } else {
            if (!selectedAccount.value) {
                fieldError.value = t('employees.accountLink.errors.selectAccount');
                return;
            }

            await apiLogin.post(`/api/employees/${props.employee.id}/link-account`, {
                user_id: selectedAccount.value.id,
                version: props.employee.version
            });
        }

        emit('saved');
        emit('update:visible', false);
    } catch (error) {
        if (error.response?.status === 409) {
            emit('conflict', error.response?.data?.current_version ?? null);
            emit('update:visible', false);
        } else if (error.response?.status === 422) {
            fieldError.value = error.response?.data?.errors?.user_id?.[0] ?? '';
            generalMessage.value = fieldError.value ? '' : (firstValidationMessage(error.response) ?? '');
        } else {
            generalMessage.value = firstValidationMessage(error.response) ?? t('employees.accountLink.errors.update');
        }
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <Dialog
        :visible="visible"
        :header="isLinked ? t('employees.accountLink.titleUnlink') : t('employees.accountLink.titleLink')"
        modal
        :style="{ width: '34rem' }"
        :breakpoints="{ '768px': '92vw' }"
        :closable="!saving"
        @update:visible="emit('update:visible', $event)"
    >
        <div class="flex flex-col gap-3">
            <p class="text-sm font-medium">{{ employee?.name }}</p>

            <p v-if="isLinked" class="text-sm">{{ t('employees.accountLink.unlinkNotice') }}</p>

            <Message v-if="generalMessage" severity="error" :closable="false">{{ generalMessage }}</Message>

            <template v-if="!isLinked">
                <div class="flex gap-2">
                    <InputText v-model="search" :placeholder="t('employees.accountLink.searchPlaceholder')" class="w-full" @input="onSearchInput" />
                    <Button icon="pi pi-search" :loading="loading" @click="loadCandidates" />
                </div>
                <Listbox v-model="selectedAccount" :options="candidates" optionLabel="name" listStyle="max-height: 16rem" />
                <small v-if="fieldError" class="text-red-500">{{ fieldError }}</small>
                <small v-else-if="!loading && candidates.length === 0" class="text-surface-500">{{ t('employees.accountLink.noCandidates') }}</small>
            </template>
        </div>

        <template #footer>
            <Button :label="t('common.action.cancel')" severity="secondary" variant="outlined" :disabled="saving" @click="emit('update:visible', false)" />
            <Button
                :label="isLinked ? t('employees.accountLink.submitUnlink') : t('employees.accountLink.submitLink')"
                :icon="isLinked ? 'pi pi-user-minus' : 'pi pi-user-plus'"
                :severity="isLinked ? 'warn' : 'info'"
                :loading="saving"
                @click="submit"
            />
        </template>
    </Dialog>
</template>
