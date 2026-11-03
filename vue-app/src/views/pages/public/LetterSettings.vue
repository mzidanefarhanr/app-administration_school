<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import apiLogin from '@/apiLogin';
import { authStore } from '@/stores/authStore';

const store = authStore();
const toast = useToast();

const data = ref(null);
const loading = ref(false);
const loadError = ref('');
const conflictMessage = ref('');

const headerSaving = ref(false);
const headerErrors = ref({});
const headerError = ref('');
const signatorySaving = ref(false);
const signatoryErrors = ref({});
const signatoryError = ref('');

const headerLogoFile = ref(null);
const headerLogoInput = ref(null);
const headerRemoveLogo = ref(false);
const signatureFile = ref(null);
const signatureInput = ref(null);
const stampFile = ref(null);
const stampInput = ref(null);
const stampRemove = ref(false);

const headerForm = reactive({
    government_line: '',
    school_name: '',
    address_line: '',
    contact_line: '',
    place_of_issue: ''
});

const signatoryForm = reactive({
    signatory_name: '',
    signatory_title: '',
    signatory_identifier_label: '',
    signatory_identifier: ''
});

const assetUrls = reactive({ logo: '', signature: '', stamp: '' });
const filePreviews = reactive({ logo: '', signature: '', stamp: '' });

const settings = computed(() => data.value?.settings ?? null);
const readiness = computed(() => data.value?.readiness ?? null);
const canManageHeader = computed(() => data.value?.permissions?.can_manage_header === true);
const canManageSignatory = computed(() => data.value?.permissions?.can_manage_signatory === true);
const settingsVersion = computed(() => settings.value?.version ?? 0);

const missingLabels = {
    active_school_year: 'Active school year',
    school_profile: 'School profile',
    header: 'Letterhead',
    signatory: 'Signatory identity',
    signature: 'Signature image'
};

const readinessItems = computed(() => {
    const state = readiness.value;
    if (!state) return [];

    return [
        { key: 'active_year', label: missingLabels.active_school_year, ready: state.active_year },
        { key: 'profile', label: missingLabels.school_profile, ready: state.profile },
        { key: 'header', label: missingLabels.header, ready: state.header },
        { key: 'signatory', label: missingLabels.signatory, ready: state.signatory },
        { key: 'signature', label: missingLabels.signature, ready: state.signature },
        { key: 'template', label: 'PDF templates', ready: state.template }
    ];
});

const missingText = computed(() => {
    const missing = readiness.value?.missing ?? [];
    return missing.map((key) => missingLabels[key] ?? key).join(', ');
});

const notify = (severity, detail) => {
    toast.add({ severity, summary: 'Letter Settings', detail, life: 5000 });
};

const errorMessage = (error, fallback) => error.response?.data?.message ?? fallback;

const validationErrors = (error) => {
    const payload = error.response?.data ?? {};
    return payload.errors && typeof payload.errors === 'object' ? payload.errors : {};
};

const fieldError = (errors, field) => {
    const value = errors?.[field];
    if (Array.isArray(value)) return value[0] ?? '';
    return typeof value === 'string' ? value : '';
};

const revoke = (url) => {
    if (url) URL.revokeObjectURL(url);
};

const applyData = (payload) => {
    data.value = payload ?? null;

    const current = payload?.settings;
    const suggestions = payload?.suggestions ?? {};

    headerForm.government_line = current?.government_line ?? '';
    headerForm.school_name = current?.school_name ?? suggestions.school_name ?? '';
    headerForm.address_line = current?.address_line ?? suggestions.address_line ?? '';
    headerForm.contact_line = current?.contact_line ?? suggestions.contact_line ?? '';
    headerForm.place_of_issue = current?.place_of_issue ?? '';

    signatoryForm.signatory_name = current?.signatory_name ?? suggestions.signatory_name ?? '';
    signatoryForm.signatory_title = current?.signatory_title ?? suggestions.signatory_title ?? '';
    signatoryForm.signatory_identifier_label = current?.signatory_identifier_label ?? 'NIP';
    signatoryForm.signatory_identifier = current?.signatory_identifier ?? '';

    headerLogoFile.value = null;
    signatureFile.value = null;
    stampFile.value = null;
    headerRemoveLogo.value = false;
    stampRemove.value = false;

    if (headerLogoInput.value) headerLogoInput.value.value = '';
    if (signatureInput.value) signatureInput.value.value = '';
    if (stampInput.value) stampInput.value.value = '';

    for (const asset of ['logo', 'signature', 'stamp']) {
        revoke(filePreviews[asset]);
        filePreviews[asset] = '';
    }
};

const loadAssets = async () => {
    for (const asset of ['logo', 'signature', 'stamp']) {
        revoke(assetUrls[asset]);
        assetUrls[asset] = '';
    }

    const available = settings.value;
    if (!available) return;

    const assets = ['logo'];
    if (canManageSignatory.value) assets.push('signature', 'stamp');

    await Promise.all(
        assets.map(async (asset) => {
            if (!available[`${asset}_available`]) return;

            try {
                const response = await apiLogin.get(`/api/letters/settings/assets/${asset}`, { responseType: 'blob' });
                const type = response.headers?.['content-type'] || 'image/png';
                assetUrls[asset] = URL.createObjectURL(new Blob([response.data], { type }));
            } catch {
                assetUrls[asset] = '';
            }
        })
    );
};

const load = async () => {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await apiLogin.get('/api/letters/settings');
        applyData(response.data?.data ?? null);
        await loadAssets();
    } catch (error) {
        data.value = null;
        loadError.value = errorMessage(error, 'Failed to load letter settings. Please try again.');
    } finally {
        loading.value = false;
    }
};

const setFilePreview = (asset, file) => {
    revoke(filePreviews[asset]);
    filePreviews[asset] = file ? URL.createObjectURL(file) : '';
};

const onLogoSelected = (event) => {
    headerLogoFile.value = event.target.files?.[0] ?? null;
    if (headerLogoFile.value) headerRemoveLogo.value = false;
    setFilePreview('logo', headerLogoFile.value);
};

const onSignatureSelected = (event) => {
    signatureFile.value = event.target.files?.[0] ?? null;
    setFilePreview('signature', signatureFile.value);
};

const onStampSelected = (event) => {
    stampFile.value = event.target.files?.[0] ?? null;
    if (stampFile.value) stampRemove.value = false;
    setFilePreview('stamp', stampFile.value);
};

const onRemoveLogoChange = () => {
    if (!headerRemoveLogo.value) return;
    headerLogoFile.value = null;
    if (headerLogoInput.value) headerLogoInput.value.value = '';
    setFilePreview('logo', null);
};

const onRemoveStampChange = () => {
    if (!stampRemove.value) return;
    stampFile.value = null;
    if (stampInput.value) stampInput.value.value = '';
    setFilePreview('stamp', null);
};

const submitHeader = async () => {
    if (headerSaving.value || !canManageHeader.value) return;

    headerErrors.value = {};
    headerError.value = '';

    if (headerForm.school_name.trim().length > 150) {
        headerErrors.value = { school_name: ['The school name may not exceed 150 characters.'] };
        return;
    }

    if (headerForm.address_line.trim().length > 255) {
        headerErrors.value = { address_line: ['The address may not exceed 255 characters.'] };
        return;
    }

    const body = new FormData();
    body.append('expected_version', String(settingsVersion.value));
    body.append('government_line', headerForm.government_line.trim());
    body.append('school_name', headerForm.school_name.trim());
    body.append('address_line', headerForm.address_line.trim());
    body.append('contact_line', headerForm.contact_line.trim());
    body.append('place_of_issue', headerForm.place_of_issue.trim());
    body.append('remove_logo', headerRemoveLogo.value ? '1' : '0');
    if (headerLogoFile.value) body.append('logo', headerLogoFile.value);

    headerSaving.value = true;
    try {
        const response = await apiLogin.post('/api/letters/settings/header', body);
        applyData(response.data?.data ?? null);
        await loadAssets();
        notify('success', response.data?.message ?? 'Letter header saved.');
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            headerErrors.value = validationErrors(error);
            if (Object.keys(headerErrors.value).length === 0) headerError.value = errorMessage(error, 'The letter header could not be saved.');
        } else if (status === 409) {
            conflictMessage.value = errorMessage(error, 'These letter settings were changed by another request. The latest data has been loaded.');
            await load();
        } else {
            headerError.value = errorMessage(error, 'Failed to save the letter header.');
        }
    } finally {
        headerSaving.value = false;
    }
};

const submitSignatory = async () => {
    if (signatorySaving.value || !canManageSignatory.value) return;

    signatoryErrors.value = {};
    signatoryError.value = '';

    const errors = {};
    if (!signatoryForm.signatory_name.trim()) errors.signatory_name = ['The signatory name is required.'];
    if (!signatoryForm.signatory_title.trim()) errors.signatory_title = ['The signatory title is required.'];

    if (!settings.value?.signature_available && !signatureFile.value) {
        errors.signature = ['A signature image is required before signatory settings can be saved.'];
    }

    if (Object.keys(errors).length > 0) {
        signatoryErrors.value = errors;
        return;
    }

    const body = new FormData();
    body.append('expected_version', String(settingsVersion.value));
    body.append('signatory_name', signatoryForm.signatory_name.trim());
    body.append('signatory_title', signatoryForm.signatory_title.trim());
    body.append('signatory_identifier_label', signatoryForm.signatory_identifier_label.trim());
    body.append('signatory_identifier', signatoryForm.signatory_identifier.trim());
    body.append('remove_stamp', stampRemove.value ? '1' : '0');
    if (signatureFile.value) body.append('signature', signatureFile.value);
    if (stampFile.value) body.append('stamp', stampFile.value);

    signatorySaving.value = true;
    try {
        const response = await apiLogin.post('/api/letters/settings/signatory', body);
        applyData(response.data?.data ?? null);
        await loadAssets();
        notify('success', response.data?.message ?? 'Letter signatory saved.');
    } catch (error) {
        const status = error.response?.status;
        if (status === 422) {
            signatoryErrors.value = validationErrors(error);
            if (Object.keys(signatoryErrors.value).length === 0) signatoryError.value = errorMessage(error, 'The signatory settings could not be saved.');
        } else if (status === 409) {
            conflictMessage.value = errorMessage(error, 'These letter settings were changed by another request. The latest data has been loaded.');
            await load();
        } else {
            signatoryError.value = errorMessage(error, 'Failed to save signatory settings.');
        }
    } finally {
        signatorySaving.value = false;
    }
};

onMounted(async () => {
    await store.fetchIdentity();
    await load();
});

onBeforeUnmount(() => {
    for (const asset of ['logo', 'signature', 'stamp']) {
        revoke(assetUrls[asset]);
        revoke(filePreviews[asset]);
    }
});
</script>

<template>
    <Fluid>
        <div class="bg-surface-0 dark:bg-surface-950 px-4 py-6 sm:px-6 sm:py-8 md:px-12 lg:px-20">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <div class="font-bold text-2xl sm:text-3xl text-surface-900 dark:text-surface-0 mb-2">Letter Settings</div>
                    <p class="text-surface-500 dark:text-surface-400">Letterhead, logo, signatory, signature, and stamp used to render typed letter PDFs for the active school year.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Tag v-if="data?.active_school_year" :value="data.active_school_year.name" severity="secondary" />
                    <Tag v-if="readiness" :value="readiness.ready ? 'Ready' : 'Incomplete'" :severity="readiness.ready ? 'success' : 'warn'" />
                </div>
            </div>

            <div v-if="loadError" class="mt-4 flex flex-col sm:flex-row sm:items-center gap-3 rounded border border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-950 px-4 py-3 text-sm text-red-700 dark:text-red-300">
                <span class="flex-1">{{ loadError }}</span>
                <Button label="Retry" icon="pi pi-refresh" size="small" severity="secondary" variant="outlined" @click="load" />
            </div>

            <Message v-if="conflictMessage" severity="warn" class="mt-4" :closable="false">{{ conflictMessage }}</Message>

            <div v-if="loading" class="flex justify-center py-16">
                <i class="pi pi-spin pi-spinner text-2xl" aria-hidden="true"></i>
            </div>

            <template v-else-if="data">
                <div class="mt-6 grid grid-cols-1 xl:grid-cols-3 gap-4">
                    <div class="card xl:col-span-1">
                        <div class="font-semibold text-lg mb-3">Active Period</div>
                        <div class="flex flex-col gap-3 text-sm">
                            <div>
                                <div class="text-surface-500">School year</div>
                                <div class="font-medium">{{ data.active_school_year?.name ?? 'No active school year' }}</div>
                            </div>
                            <div>
                                <div class="text-surface-500">School profile</div>
                                <div class="font-medium">{{ data.school_profile?.school_name ?? 'No profile for the active year' }}</div>
                                <div v-if="data.school_profile?.npsn" class="text-xs text-surface-500">NPSN {{ data.school_profile.npsn }}</div>
                            </div>
                            <div class="border-t border-surface-200 dark:border-surface-700 pt-3">
                                <div class="text-surface-500 mb-2">Readiness</div>
                                <ul class="flex flex-col gap-2">
                                    <li v-for="item in readinessItems" :key="item.key" class="flex items-center gap-2">
                                        <i :class="item.ready ? 'pi pi-check-circle text-green-500' : 'pi pi-circle text-surface-400'" aria-hidden="true"></i>
                                        <span :class="item.ready ? '' : 'text-surface-500'">{{ item.label }}</span>
                                    </li>
                                </ul>
                                <Message v-if="!readiness.ready" severity="warn" class="mt-3" :closable="false">Missing: {{ missingText }}</Message>
                            </div>
                            <div class="text-xs text-surface-500">
                                Typed letters can only be issued when every readiness item is complete.
                                <router-link to="/public/schoolprofile" class="text-primary font-medium">Open School Profile</router-link>
                            </div>
                        </div>
                    </div>

                    <div class="card xl:col-span-2">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
                            <div>
                                <div class="font-semibold text-lg">Letterhead</div>
                                <div class="text-sm text-surface-500">Printed at the top of every typed letter PDF.</div>
                            </div>
                            <Tag v-if="settings" :value="`Version ${settings.version}`" severity="contrast" />
                        </div>

                        <form v-if="canManageHeader" class="flex flex-col gap-4" @submit.prevent="submitHeader">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="flex flex-col gap-2 md:col-span-2">
                                    <label for="letterGovernmentLine" class="font-medium">Government line</label>
                                    <InputText id="letterGovernmentLine" v-model="headerForm.government_line" maxlength="150" placeholder="e.g. PEMERINTAH KOTA ..." />
                                    <small v-if="fieldError(headerErrors, 'government_line')" class="text-red-500">{{ fieldError(headerErrors, 'government_line') }}</small>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <label for="letterSchoolName" class="font-medium">School name</label>
                                    <InputText id="letterSchoolName" v-model="headerForm.school_name" maxlength="150" />
                                    <small v-if="fieldError(headerErrors, 'school_name')" class="text-red-500">{{ fieldError(headerErrors, 'school_name') }}</small>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <label for="letterPlaceOfIssue" class="font-medium">Place of issue</label>
                                    <InputText id="letterPlaceOfIssue" v-model="headerForm.place_of_issue" maxlength="100" />
                                    <small v-if="fieldError(headerErrors, 'place_of_issue')" class="text-red-500">{{ fieldError(headerErrors, 'place_of_issue') }}</small>
                                </div>
                                <div class="flex flex-col gap-2 md:col-span-2">
                                    <label for="letterAddressLine" class="font-medium">Address</label>
                                    <InputText id="letterAddressLine" v-model="headerForm.address_line" maxlength="255" />
                                    <small v-if="fieldError(headerErrors, 'address_line')" class="text-red-500">{{ fieldError(headerErrors, 'address_line') }}</small>
                                </div>
                                <div class="flex flex-col gap-2 md:col-span-2">
                                    <label for="letterContactLine" class="font-medium">Contact line</label>
                                    <InputText id="letterContactLine" v-model="headerForm.contact_line" maxlength="150" placeholder="Phone, email, or website" />
                                    <small v-if="fieldError(headerErrors, 'contact_line')" class="text-red-500">{{ fieldError(headerErrors, 'contact_line') }}</small>
                                </div>
                            </div>

                            <div class="border border-surface-200 dark:border-surface-700 rounded p-3 flex flex-col gap-3">
                                <div class="font-medium">Logo</div>
                                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                    <img
                                        v-if="filePreviews.logo || (assetUrls.logo && !headerRemoveLogo)"
                                        :src="filePreviews.logo || assetUrls.logo"
                                        alt="School logo preview"
                                        class="h-16 w-16 object-contain rounded border border-surface-200 dark:border-surface-700 bg-white p-1"
                                    />
                                    <span v-else class="text-sm text-surface-500">No logo uploaded.</span>
                                    <div class="flex flex-col gap-2">
                                        <input ref="headerLogoInput" type="file" accept=".png,.jpg,.jpeg,image/png,image/jpeg" class="block w-full text-sm" aria-label="Choose a school logo" @change="onLogoSelected" />
                                        <div v-if="settings?.logo_available" class="flex items-center gap-2">
                                            <Checkbox v-model="headerRemoveLogo" binary inputId="removeLetterLogo" @change="onRemoveLogoChange" />
                                            <label for="removeLetterLogo" class="text-sm">Remove current logo</label>
                                        </div>
                                    </div>
                                </div>
                                <small class="text-surface-500">PNG, JPG, or JPEG. Maximum 2 MB and 2000 pixels per side. Images are stored privately and re-encoded.</small>
                                <small v-if="fieldError(headerErrors, 'logo')" class="text-red-500">{{ fieldError(headerErrors, 'logo') }}</small>
                            </div>

                            <Message v-if="headerError" severity="error" :closable="false">{{ headerError }}</Message>
                            <small v-if="fieldError(headerErrors, 'letter_settings')" class="text-red-500">{{ fieldError(headerErrors, 'letter_settings') }}</small>

                            <div class="flex justify-end">
                                <Button type="submit" label="Save Header" icon="pi pi-check" :loading="headerSaving" />
                            </div>
                        </form>

                        <div v-else class="flex flex-col gap-3 text-sm">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <div class="text-surface-500">Government line</div>
                                    <div class="font-medium break-words">{{ settings?.government_line || '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-surface-500">School name</div>
                                    <div class="font-medium break-words">{{ settings?.school_name || '-' }}</div>
                                </div>
                                <div class="md:col-span-2">
                                    <div class="text-surface-500">Address</div>
                                    <div class="font-medium break-words">{{ settings?.address_line || '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-surface-500">Contact line</div>
                                    <div class="font-medium break-words">{{ settings?.contact_line || '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-surface-500">Place of issue</div>
                                    <div class="font-medium">{{ settings?.place_of_issue || '-' }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <img v-if="assetUrls.logo" :src="assetUrls.logo" alt="School logo" class="h-16 w-16 object-contain rounded border border-surface-200 dark:border-surface-700 bg-white p-1" />
                                <span v-else class="text-surface-500">No logo uploaded.</span>
                            </div>
                            <Message severity="info" :closable="false">You can view letterhead settings but not edit them.</Message>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
                        <div>
                            <div class="font-semibold text-lg">Signatory</div>
                            <div class="text-sm text-surface-500">Name, title, signature, and stamp printed on issued letters.</div>
                        </div>
                        <Tag v-if="settings" :value="`Version ${settings.version}`" severity="contrast" />
                    </div>

                    <form v-if="canManageSignatory" class="flex flex-col gap-4" @submit.prevent="submitSignatory">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-2">
                                <label for="letterSignatoryName" class="font-medium">Name <span class="text-red-500">*</span></label>
                                <InputText id="letterSignatoryName" v-model="signatoryForm.signatory_name" maxlength="150" />
                                <small v-if="fieldError(signatoryErrors, 'signatory_name')" class="text-red-500">{{ fieldError(signatoryErrors, 'signatory_name') }}</small>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label for="letterSignatoryTitle" class="font-medium">Title <span class="text-red-500">*</span></label>
                                <InputText id="letterSignatoryTitle" v-model="signatoryForm.signatory_title" maxlength="150" />
                                <small v-if="fieldError(signatoryErrors, 'signatory_title')" class="text-red-500">{{ fieldError(signatoryErrors, 'signatory_title') }}</small>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label for="letterIdentifierLabel" class="font-medium">Identifier label</label>
                                <InputText id="letterIdentifierLabel" v-model="signatoryForm.signatory_identifier_label" maxlength="30" placeholder="NIP" />
                                <small v-if="fieldError(signatoryErrors, 'signatory_identifier_label')" class="text-red-500">{{ fieldError(signatoryErrors, 'signatory_identifier_label') }}</small>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label for="letterIdentifier" class="font-medium">Identifier</label>
                                <InputText id="letterIdentifier" v-model="signatoryForm.signatory_identifier" maxlength="50" />
                                <small v-if="fieldError(signatoryErrors, 'signatory_identifier')" class="text-red-500">{{ fieldError(signatoryErrors, 'signatory_identifier') }}</small>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="border border-surface-200 dark:border-surface-700 rounded p-3 flex flex-col gap-3">
                                <div class="font-medium">Signature image <span class="text-red-500">*</span></div>
                                <img
                                    v-if="filePreviews.signature || assetUrls.signature"
                                    :src="filePreviews.signature || assetUrls.signature"
                                    alt="Signature preview"
                                    class="h-20 max-w-full object-contain rounded border border-surface-200 dark:border-surface-700 bg-white p-1"
                                />
                                <span v-else class="text-sm text-surface-500">No signature uploaded yet.</span>
                                <input ref="signatureInput" type="file" accept=".png,.jpg,.jpeg,image/png,image/jpeg" class="block w-full text-sm" aria-label="Choose a signature image" @change="onSignatureSelected" />
                                <small v-if="settings?.signature_available" class="text-surface-500">Uploading a new image replaces the current signature.</small>
                                <small v-if="fieldError(signatoryErrors, 'signature')" class="text-red-500">{{ fieldError(signatoryErrors, 'signature') }}</small>
                            </div>

                            <div class="border border-surface-200 dark:border-surface-700 rounded p-3 flex flex-col gap-3">
                                <div class="font-medium">Stamp</div>
                                <img
                                    v-if="filePreviews.stamp || (assetUrls.stamp && !stampRemove)"
                                    :src="filePreviews.stamp || assetUrls.stamp"
                                    alt="Stamp preview"
                                    class="h-20 max-w-full object-contain rounded border border-surface-200 dark:border-surface-700 bg-white p-1"
                                />
                                <span v-else class="text-sm text-surface-500">No stamp uploaded.</span>
                                <input ref="stampInput" type="file" accept=".png,.jpg,.jpeg,image/png,image/jpeg" class="block w-full text-sm" aria-label="Choose a stamp image" @change="onStampSelected" />
                                <div v-if="settings?.stamp_available" class="flex items-center gap-2">
                                    <Checkbox v-model="stampRemove" binary inputId="removeLetterStamp" @change="onRemoveStampChange" />
                                    <label for="removeLetterStamp" class="text-sm">Remove current stamp</label>
                                </div>
                                <small v-if="fieldError(signatoryErrors, 'stamp')" class="text-red-500">{{ fieldError(signatoryErrors, 'stamp') }}</small>
                            </div>
                        </div>

                        <small class="text-surface-500">PNG, JPG, or JPEG. Maximum 2 MB and 2000 pixels per side. Signature and stamp are stored privately and only visible to authorized signers.</small>
                        <Message v-if="signatoryError" severity="error" :closable="false">{{ signatoryError }}</Message>
                        <small v-if="fieldError(signatoryErrors, 'letter_settings')" class="text-red-500">{{ fieldError(signatoryErrors, 'letter_settings') }}</small>

                        <div class="flex justify-end">
                            <Button type="submit" label="Save Signatory" icon="pi pi-check" :loading="signatorySaving" />
                        </div>
                    </form>

                    <div v-else class="flex flex-col gap-3 text-sm">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <div class="text-surface-500">Name</div>
                                <div class="font-medium break-words">{{ settings?.signatory_name || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-surface-500">Title</div>
                                <div class="font-medium break-words">{{ settings?.signatory_title || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-surface-500">{{ settings?.signatory_identifier_label || 'Identifier' }}</div>
                                <div class="font-medium break-words">{{ settings?.signatory_identifier || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-surface-500">Signature</div>
                                <div class="font-medium">{{ settings?.signature_available ? 'On file' : 'Not available' }}</div>
                            </div>
                            <div>
                                <div class="text-surface-500">Stamp</div>
                                <div class="font-medium">{{ settings?.stamp_available ? 'On file' : 'Not available' }}</div>
                            </div>
                        </div>
                        <Message severity="info" :closable="false">You can view signatory metadata but not edit the signatory or raw signature assets.</Message>
                    </div>
                </div>

                <div v-if="settings?.updated_by" class="mt-4 text-xs text-surface-500">Last updated by {{ settings.updated_by.name }} on {{ settings.updated_at ? new Date(settings.updated_at).toLocaleString('en-GB') : '-' }}.</div>
            </template>
        </div>
    </Fluid>
</template>
