<script setup>
import { ref, onMounted, computed } from 'vue';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import apiLogin from '@/apiLogin';

// ── State ─────────────────────────────────────────────────────────────────
const roles = ref([]);
const abilities = ref([]);
const loading = ref(false);
const saving = ref(false);
const editingRole = ref(null);
const selectedAbilities = ref([]);
const dialogVisible = ref(false);
const errorMessage = ref('');

// ── Ability labels ────────────────────────────────────────────────────────
// Display labels are kept separate from ability identifiers so they can be
// translated later without changing backend identifiers or field names.
const GROUP_LABELS = {
    system: 'System',
    master: 'Master Data',
    students: 'Students',
    employees: 'Teachers & Employees',
    letters: 'Letters',
    leaves: 'Leaves',
    teaching: 'Teaching',
    backup: 'Backup',
    lainnya: 'Other'
};

const groupLabel = (group) => GROUP_LABELS[group] || group;

const ABILITY_LABELS = {
    'roles.view': 'View roles',
    'roles.manage': 'Manage roles',
    'abilities.manage': 'Manage abilities',
    'users.view': 'View accounts',
    'users.manage': 'Manage accounts',
    'access.assign': 'Assign roles',
    'audit.view': 'View audit log',
    'master.view': 'View master data',
    'master.manage': 'Manage master data',
    'students.view': 'View students',
    'students.manage': 'Manage students',
    'students.export': 'Export students',
    'employees.view': 'View staff',
    'employees.manage': 'Manage staff',
    'employees.export': 'Export staff',
    'backup.manage': 'Manage backups',
    'letters.view': 'View letters',
    'letters.manage': 'Manage letters',
    'letters.approve': 'Approve letters',
    'letters.sign': 'Sign documents',
    'leaves.view': 'View leaves',
    'leaves.request': 'Request own leave',
    'leaves.manage': 'Manage leaves',
    'leaves.approve': 'Approve leaves',
    'teaching.view': 'View teaching',
    'teaching.manage': 'Manage teaching'
};

const abilityLabel = (name) => ABILITY_LABELS[name] || name;

// ── Grouping abilities by module group ────────────────────────────────────
const abilityGroups = computed(() => {
    const groups = {};
    for (const ability of abilities.value) {
        const key = ability.group || 'lainnya';
        if (!groups[key]) groups[key] = [];
        groups[key].push(ability);
    }
    return groups;
});

// ── Fetch ─────────────────────────────────────────────────────────────────
const fetchRoles = async () => {
    loading.value = true;
    try {
        const response = await apiLogin.get('/api/roles');
        roles.value = response.data.data;
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Failed to load roles.';
    } finally {
        loading.value = false;
    }
};

const fetchAbilities = async () => {
    try {
        const response = await apiLogin.get('/api/abilities');
        abilities.value = response.data.data;
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Failed to load abilities.';
    }
};

// ── Edit ──────────────────────────────────────────────────────────────────
const openEdit = (role) => {
    editingRole.value = { ...role };
    selectedAbilities.value = (role.abilities || []).map((a) => a.name);
    errorMessage.value = '';
    dialogVisible.value = true;
};

const saveRole = async () => {
    if (!editingRole.value) return;
    saving.value = true;
    errorMessage.value = '';
    try {
        await apiLogin.patch(`/api/roles/${editingRole.value.id}`, {
            name: editingRole.value.name,
            description: editingRole.value.description,
            abilities: selectedAbilities.value
        });
        dialogVisible.value = false;
        await fetchRoles();
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Failed to save changes.';
    } finally {
        saving.value = false;
    }
};

const toggleGroupAll = (group, abilityList) => {
    const names = abilityList.map((a) => a.name);
    const allSelected = names.every((n) => selectedAbilities.value.includes(n));
    if (allSelected) {
        selectedAbilities.value = selectedAbilities.value.filter((n) => !names.includes(n));
    } else {
        selectedAbilities.value = Array.from(new Set([...selectedAbilities.value, ...names]));
    }
};

onMounted(() => {
    fetchRoles();
    fetchAbilities();
});
</script>

<template>
    <Fluid>
        <div class="bg-surface-0 dark:bg-surface-950 px-6 py-8 md:px-12 lg:px-20">
            <div class="flex items-start flex-col md:justify-between md:flex-row">
                <div>
                    <div class="font-bold text-3xl text-surface-900 dark:text-surface-0 mb-4">Roles &amp; Abilities</div>
                    <p class="text-surface-500 dark:text-surface-300">Configure abilities per role. Changes apply on the next request.</p>
                </div>
            </div>

            <Message v-if="errorMessage" severity="error" :closable="false" class="my-4">{{ errorMessage }}</Message>

            <div class="card flex flex-col gap-4 mt-4">
                <DataTable :value="roles" dataKey="id" :loading="loading" showGridlines paginator :rows="10">
                    <template #header>
                        <div class="flex flex-wrap gap-2 w-full">Total Roles: {{ roles ? roles.length : 'Loading' }}</div>
                    </template>
                    <template #empty> No roles found. </template>

                    <Column field="name" header="Role" style="min-width: 14rem">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span class="font-semibold">{{ data.name }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column field="description" header="Description" style="min-width: 16rem" />

                    <Column header="Abilities" style="min-width: 8rem">
                        <template #body="{ data }">
                            <Tag :value="`${data.abilities ? data.abilities.length : 0} abilities`" severity="info" />
                        </template>
                    </Column>

                    <Column header="Users" style="min-width: 6rem">
                        <template #body="{ data }">
                            {{ data.users_count }}
                        </template>
                    </Column>

                    <Column header="Action" :exportable="false" style="min-width: 6rem">
                        <template #body="slotProps">
                            <Button icon="pi pi-pencil" variant="outlined" rounded @click="openEdit(slotProps.data)" />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </Fluid>

    <Dialog v-model:visible="dialogVisible" :style="{ width: '52rem' }" :header="`Configure Abilities: ${editingRole?.name || ''}`" :modal="true">
        <div v-if="editingRole" class="flex flex-col gap-4 max-h-[70vh] overflow-auto">
            <div class="flex flex-col gap-2">
                <label class="font-bold">Role Name</label>
                <InputText v-model="editingRole.name" fluid />
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-bold">Description</label>
                <InputText v-model="editingRole.description" fluid />
            </div>

            <div v-for="(list, group) in abilityGroups" :key="group" class="flex flex-col gap-2 border-t pt-3">
                <div class="flex items-center justify-between">
                    <span class="font-bold">{{ groupLabel(group) }}</span>
                    <Button label="Select all" text size="small" @click="toggleGroupAll(group, list)" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <div v-for="ability in list" :key="ability.name" class="flex items-center gap-2">
                        <Checkbox v-model="selectedAbilities" :inputId="ability.name" :value="ability.name" />
                        <label :for="ability.name" class="cursor-pointer">
                            {{ abilityLabel(ability.name) }}
                            <span class="text-surface-400 text-xs ml-1">({{ ability.name }})</span>
                            <Tag v-if="ability.is_sensitive" value="sensitive" severity="warn" class="ml-2" />
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" icon="pi pi-times" text @click="dialogVisible = false" />
            <Button label="Save" icon="pi pi-check" :loading="saving" @click="saveRole" />
        </template>
    </Dialog>
</template>
