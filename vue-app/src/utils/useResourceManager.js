// composables/useResourceManager.js
//
// Generic CRUD + validation composable.
// Designed to be copy-pasted across pages — all names stay generic here.
// Each consuming file aliases the returned values to domain-specific names
// via destructuring, so the Vue file itself reads naturally.
//
// Usage example (Accounts.vue):
//   const {
//     manyDatas,        // → alias to e.g. "users"
//     manyDatas_2,      // → alias to e.g. "typeUserOptions"
//     manyDatas_3,      // → alias to e.g. "statusUserOptions"
//     manyDatas_cat2,   // → alias to e.g. "typeUserNames"
//     manyDatas_cat3,   // → alias to e.g. "statusUserNames"
//     filtered_Cat1,    // → alias to e.g. "filteredTypeUsers"
//     filtered_Cat2,    // → alias to e.g. "filteredStatusUsers"
//     singleData, previousData, selectedManyDatas, saveUser,
//     loading, submitted, entityLabel, entityLabelPlural,
//     EditOrNewDialog, deleteDialog, deletesDialog, WaitingDialog, ErrorDialog,
//     titleError, fieldError,
//     isCat1Used, isCat2Used, isCat3Used, isCat4Used,
//     validFieldCount,
//     fetchDataAll, fetchDataUserRole,
//     openNew, hideDialog, editSingleData, saveSingleData,
//     confirmDeleteSingleData, deleteSingleData,
//     confirmDeleteSelected, deleteSelectedManyDatas,
//     search_Cat1, search_Cat2,
//     waitingDialogRender, errorDialogRender,
//   } = useResourceManager(config)

import { ref, computed, watch } from 'vue';
import apiLogin from '@/apiLogin';
import { authStore } from '@/stores/authStore';
import { useToast } from 'primevue/usetoast';
import { getChangeDetail, getChangeDetails } from '@/utils/diff';
import { useUniqueValidation } from '@/utils/useUniqueValidation';

/**
 * @param {Object} config
 * @param {string}   config.endpoint           - Primary resource API path, e.g. 'users'
 * @param {string[]} [config.endpoints]        - Cat2, Cat3, etc options API path, e.g. '/api/{enpoints}'
 * @param {string}   config.menuLabel          - Singular label shown in UI, e.g. 'Account'
 * @param {string}   config.menuLabelPlural    - Plural label shown in UI, e.g. 'Accounts'
 * @param {Object}   config.fieldLabels        - Display labels for diff/audit log
 *                                               e.g. { name: 'Name', email: 'Email', ... }
 * @param {string[]} config.uniqueFields       - Fields to validate for uniqueness [cat1, cat2, cat3, cat4]
 *                                               e.g. ['name', 'email', 'username', 'nik']
 * @param {number[]} config.uniqueMinLengths   - Min length per unique field for "available" state
 *                                               e.g. [3, 3, 3, 15]
 * @param {string}   [config.cat1_fk]          - FK object field on singleData, e.g. 'type_user'
 * @param {string}   [config.cat1_fk_id]       - FK id field to set on save, e.g. 'type_user_id'
 * @param {string}   [config.cat2_fk]          - FK object field on singleData, e.g. 'status_user'
 * @param {string}   [config.cat2_fk_id]       - FK id field to set on save, e.g. 'status_user_id'
 */
export function useResourceManager(config) {
    const {
        endpoint,
        endpoints = [],
        menuLabel,
        menuLabelPlural,
        fieldLabels      = {},
        uniqueFields     = [],
        uniqueMinLengths = [3, 3, 3, 3],
        fkFields = [],
    } = config;

    const toast     = useToast();
    const useAuthStore = authStore();

    // ── State ──────────────────────────────────────────────────────────────────

    const manyDatas      = ref([]);
    const manyDatas_cat1 = ref([]);
    const status_educations = ref([
        "Negeri",
        "Swasta"
    ]);

    // ── Secondary data sources (fully dynamic — no upper limit) ───────────────
    const _secondaryDatas = endpoints.map(() => ref([]));
    const _secondaryCats  = endpoints.map(() => ref([]));

    const singleData        = ref({});
    const previousData      = ref({});   // stores the original record before editing
    const selectedManyDatas = ref();
    const saveUser          = ref([]);

    const loading           = ref(true);
    const submitted         = ref(false);
    const entityLabel       = ref('');
    const entityLabelPlural = ref('');

    const EditOrNewDialog = ref(false);
    const deleteDialog    = ref(false);
    const deletesDialog   = ref(false);
    const WaitingDialog   = ref(false);
    const ErrorDialog     = ref(false);
    const titleError      = ref('');
    const fieldError      = ref('');

    // ── Unique validation (dynamic — supports any number of fields) ────────────

    // Build one validation entry per field using a loop
    const _validators = uniqueFields.map((field, i) => {
        const minLen = uniqueMinLengths[i] ?? 3;

        const { isAlreadyUsed, validate } = field
            ? useUniqueValidation(manyDatas, previousData, field)
            : { isAlreadyUsed: ref(false), validate: () => {} };

        return { field, minLen, isAlreadyUsed, validate };
    });

    // One debounce timeout per field — still separate, still safe
    const debounces = {};

    _validators.forEach(({ field, validate }) => {
        debounces[field] = null;
        watch(() => singleData.value[field], (newVal) => {
            clearTimeout(debounces[field]);
            debounces[field] = setTimeout(() => validate(newVal), 400);
        });
    });

    // In the return block — spread all isAlreadyUsed refs dynamically
    const catUsedRefs = Object.fromEntries(
        _validators.map(({ field, isAlreadyUsed }, i) => [`isCat${i + 1}Used`, isAlreadyUsed])
    );

    const validFieldCount = computed(() =>
        _validators.filter(({ field, minLen }) =>
            (singleData.value[field]?.trim().length ?? 0) > minLen
        ).length
    );

    // ── Helpers ────────────────────────────────────────────────────────────────

    function waitingDialogRender() {
        WaitingDialog.value = !WaitingDialog.value;
    }

    function errorDialogRender() {
        ErrorDialog.value = !ErrorDialog.value;
    }

    // ── Fetch (fully dynamic) ─────────────────────────────────────────────────

    const fetchDataAll = async () => {
        try {
            // Prepend /api/ to the main endpoint if not already there
            const mainUrl = endpoint.startsWith('/') ? endpoint : `/api/${endpoint}`;

            // Prepend /api/ to all secondary endpoints automatically
            const secondaryUrls = endpoints.map(ep =>
                ep.startsWith('/') ? ep : `/api/${ep}`
            );

            const [res1, ...restResults] = await Promise.all([
                apiLogin.get(mainUrl),
                ...secondaryUrls.map(ep => apiLogin.get(ep)),
            ]);

            manyDatas.value      = res1.data.data;
            manyDatas_cat1.value = manyDatas.value.map(item => item.name);

            restResults.forEach((res, i) => {
                _secondaryDatas[i].value = res.data.data;
                _secondaryCats[i].value  = _secondaryDatas[i].value.map(item => item.name);
            });
        } catch (error) {
            // console.error('fetchDataAll error:', error);
            titleError.value = 'Failed!!';
            fieldError.value = error.response?.data?.message || 'An error occurred';
            errorDialogRender();
        } finally {
            loading.value = false;
        }
    };

    const fetchDataUserRole = async () => {
        await useAuthStore.getUser();
        saveUser.value          = useAuthStore.AllUserAssign;
        entityLabel.value       = menuLabel;
        entityLabelPlural.value = menuLabelPlural;
        await fetchDataAll();
    };

    // ── AutoComplete search (fully dynamic — no upper limit) ──────────────────

    const _filteredCats = endpoints.map(() => ref([]));

    const searchCat = (index, event) => {
        setTimeout(() => {
            const source = _secondaryDatas[index];
            _filteredCats[index].value = !event.query.trim().length
                ? [...source.value]
                : source.value.filter(item =>
                    item.name.toLowerCase().includes(event.query.toLowerCase()));
        }, 250);
    };

    // Dynamic spread — exposes search_Cat5, search_Cat6, filtered_Cat5, filtered_Cat6, etc.
    // automatically based on how many endpoints are passed in config.
    const dynamicSearchCats   = Object.fromEntries(
        endpoints.map((_, i) => [`search_Cat${i + 1}`, (e) => searchCat(i, e)])
    );
    const dynamicFilteredCats = Object.fromEntries(
        endpoints.map((_, i) => [`filtered_Cat${i + 1}`, _filteredCats[i]])
    );
    const dynamicSecondaryDatas = Object.fromEntries(
        endpoints.map((_, i) => [`manyDatas_${i + 2}`, _secondaryDatas[i]])
    );
    const dynamicSecondaryCats = Object.fromEntries(
        endpoints.map((_, i) => [`manyDatas_cat${i + 2}`, _secondaryCats[i]])
    );

    // ── Dialog helpers ─────────────────────────────────────────────────────────

    const openNew = () => {
        previousData.value    = {};
        singleData.value      = {};
        submitted.value       = false;
        EditOrNewDialog.value = true;
    };

    const hideDialog = () => {
        singleData.value      = {};
        EditOrNewDialog.value = false;
        submitted.value       = false;
    };

    const editSingleData = (row) => {
        previousData.value    = { ...row };
        singleData.value      = { ...row };
        EditOrNewDialog.value = true;
    };

    // ── Save (create or update) ────────────────────────────────────────────────

    const saveSingleData = async () => {
        submitted.value = true;

        const textFieldsValid = uniqueFields.every(f => singleData.value[f]?.trim());

        // Replace the two hardcoded checks:
        const fkFieldsValid = fkFields.every(({ fk }) => singleData.value[fk]?.id);

        // Replace the two hardcoded id assignments:
        fkFields.forEach(({ fk, fk_id }) => {
            if (fk && fk_id) singleData.value[fk_id] = singleData.value[fk].id;
        });

        if (!textFieldsValid || !fkFieldsValid) return;

        if (singleData.value.id) {
            // ── UPDATE ──
            waitingDialogRender();
            const idx      = manyDatas.value.findIndex(item => item.id === singleData.value.id);
            const old_data = manyDatas.value[idx];

            singleData.value._method     = 'PATCH';
            if (endpoint == 'users') {
                singleData.value.type_editor = saveUser.value.type_user_id;
                delete singleData.value['password'];
            }

            const detailMessages = getChangeDetails(old_data, singleData.value, fieldLabels);
            const detailMessage  = getChangeDetail(old_data, singleData.value, fieldLabels);
            // console.log(old_data.update_at == singleData.value.update_at);

            if (detailMessage.hasChanges) {

                try {
                    await apiLogin.post(`/api/${endpoint}/${singleData.value.id}`, singleData.value);
                    // await apiLogin.post('/api/activities', {
                    //     before:      detailMessage.before,
                    //     after:       detailMessage.after,
                    //     new:         null,
                    //     delete:      null,
                    //     table_name:  endpoint,
                    //     record_id:   singleData.value.id,
                    //     information: 'Updated',
                    // });
                    manyDatas.value[idx] = singleData.value;

                    waitingDialogRender();
                    delete singleData.value['password_new'];
                    EditOrNewDialog.value = false;
                    singleData.value      = {};

                    toast.add({
                        severity: 'success',
                        summary:  'Data ' + old_data.name + ' Updated',
                        detail:   detailMessages,
                        life:     5000,
                    });
                } catch (error) {
                    // console.error('saveSingleData (update) error:', error);
                    waitingDialogRender();
                    // console.error('saveSingleData (update) error:', error);

                    if (error.response?.status === 409) {
                        // Another admin saved first — show conflict warning
                        titleError.value = 'Data Conflict Detected';
                        fieldError.value = error.response.data.message;
                        errorDialogRender();

                        // Reload the latest data into the form automatically
                        const latest = error.response.data.latest?.data;
                        if (latest) {
                            singleData.value  = { ...latest };
                            previousData.value = { ...latest };
                        }
                    } else if (error.response?.status === 422) {
                        titleError.value = 'Validation Failed';
                        fieldError.value = Object.values(error.response.data).flat().join('\n');
                        errorDialogRender();
                    } else {
                        titleError.value = 'Error Saving Data';
                        fieldError.value = error.response?.data?.message ?? 'Unprocessable Content.';
                        errorDialogRender();
                    }

                }

            } else {
                waitingDialogRender();
                titleError.value = 'Failed to process data!';
                fieldError.value = detailMessage.hasChanges + '\n' + detailMessages;
                errorDialogRender();
            }

        } else {
            // ── CREATE ──
            if (endpoint == 'users' && !singleData.value.password?.trim()) return;

            waitingDialogRender();
            try {
                const { data: newData } = await apiLogin.post(`/api/${endpoint}`, singleData.value);
                // await apiLogin.post('/api/activities', {
                //     before:      null,
                //     after:       null,
                //     new:         singleData.value,
                //     delete:      null,
                //     table_name:  endpoint,
                //     record_id:   newData.data.id,
                //     information: 'Created',
                // });

                EditOrNewDialog.value = false;
                await fetchDataAll();
                waitingDialogRender();
                toast.add({
                    severity: 'success',
                    summary:  'Data Created Successfully',
                    detail:   (singleData.value.name || 'New record') + ' has been created',
                    life:     3000,
                });
                singleData.value = {};
            } catch (error) {
                // console.error('saveSingleData (create) error:', error);
                waitingDialogRender();
                titleError.value = 'Creation Failed';
                fieldError.value = error.response?.data?.message || 'An unexpected error occurred.';
                errorDialogRender();
            }
        }
    };

    // ── Delete single ──────────────────────────────────────────────────────────

    const confirmDeleteSingleData = (row) => {
        singleData.value   = row;
        deleteDialog.value = true;
    };

    const deleteSingleData = async () => {
        waitingDialogRender();

        try {
            singleData.value._method = 'DELETE';
            singleData.value.delete  = true;

            await apiLogin.post(`/api/${endpoint}/${singleData.value.id}`, singleData.value);
            // await apiLogin.post('/api/activities', {
            //     before:      null,
            //     after:       null,
            //     new:         null,
            //     delete:      singleData.value,
            //     table_name:  endpoint,
            //     record_id:   singleData.value.id,
            //     information: 'Deleted',
            // });
            manyDatas.value          = manyDatas.value.filter(item => item.id !== singleData.value.id);

            waitingDialogRender();
            toast.add({
                severity: 'success',
                summary:  'Data Deleted',
                detail:   `Data ${singleData.value.name} has been deleted successfully`,
                life:     5000,
            });
        } catch (error) {
            // console.error('deleteSingleData error:', error);
            waitingDialogRender();
            titleError.value = 'Deletion Failed';
            fieldError.value = error.response?.data?.message || 'Unable to delete the data.';
            errorDialogRender();
        }

        deleteDialog.value = false;
        singleData.value   = {};
    };

    // ── Delete many ────────────────────────────────────────────────────────────

    const confirmDeleteSelected = () => {
        deletesDialog.value = true;
    };

    const deleteSelectedManyDatas = async () => {
        if (!selectedManyDatas.value?.length) return;

        waitingDialogRender();
        let count = selectedManyDatas.value.length;
        const totalSelected = selectedManyDatas.value.length;

        const names = selectedManyDatas.value.map(item => item.name);
        const formattedNames = names.length > 1
            ? names.slice(0, -1).join(', ') + ', and ' + names.slice(-1)
            : names[0] || '';

        for (const element of selectedManyDatas.value) {
            element._method = 'DELETE';
            element.delete  = true;
            if (element.id) {
                try {
                    await apiLogin.post(`/api/${endpoint}/${element.id}`, element);
                    // await apiLogin.post('/api/activities', {
                    //     before:      null,
                    //     after:       null,
                    //     new:         null,
                    //     delete:      element,
                    //     table_name:  endpoint,
                    //     record_id:   element.id,
                    //     information: 'Deleted',
                    // });
                    // manyDatas.value = manyDatas.value.filter(val => !selectedManyDatas.value.includes(val));
                    manyDatas.value = manyDatas.value.filter(val => val.id !== element.id);
                    count--;
                } catch (error) {
                    // console.error('deleteSelectedManyDatas error:', error);
                    waitingDialogRender();
                    titleError.value = 'Bulk Deletion Failed';
                    fieldError.value = error.response?.data?.message || 'Failed to delete some records.';
                    errorDialogRender();
                    break;
                }
            }
        }

        if (count === 0) {
            waitingDialogRender();
            toast.add({
                severity: 'success',
                summary:  `${totalSelected} ${entityLabelPlural.value} Deleted`,
                detail:   `${formattedNames} have been deleted successfully`,
                life:     5000,
            });
        }

        deletesDialog.value     = false;
        selectedManyDatas.value = null;
    };

    // ── Public API ─────────────────────────────────────────────────────────────

    return {
        // Data
        manyDatas, manyDatas_cat1, status_educations,
        ...dynamicSecondaryDatas,   // manyDatas_2, manyDatas_3, ... auto-generated
        ...dynamicSecondaryCats,    // manyDatas_cat2, manyDatas_cat3, ... auto-generated
        singleData, previousData, selectedManyDatas, saveUser,

        // UI state
        loading, submitted, entityLabel, entityLabelPlural,
        EditOrNewDialog, deleteDialog, deletesDialog, WaitingDialog, ErrorDialog,
        titleError, fieldError,

        // Filtered autocomplete lists
        ...dynamicFilteredCats,     // filtered_Cat1, filtered_Cat2, ... auto-generated

        // Validation
        ...catUsedRefs,
        validFieldCount,

        // Methods
        fetchDataAll, fetchDataUserRole,
        openNew, hideDialog, editSingleData, saveSingleData,
        confirmDeleteSingleData, deleteSingleData,
        confirmDeleteSelected, deleteSelectedManyDatas,
        ...dynamicSearchCats,       // search_Cat1, search_Cat2, ... auto-generated
        waitingDialogRender, errorDialogRender,
    };
}
