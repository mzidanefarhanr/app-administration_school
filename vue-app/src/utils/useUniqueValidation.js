import { ref, watch, computed } from 'vue';

export function useUniqueValidation(sourceArray, oldData, fieldName) {
    const isAlreadyUsed = ref(false);
    let debounceTimeout = null;

    // Menyiapkan Set data yang sudah ada (kecuali data yang sedang diedit)
    const existingSet = computed(() => {
        const filtered = sourceArray.value.filter(
            item => Number(item.id) !== Number(oldData.value?.id)
        );
        return new Set(filtered.map(item => item[fieldName]?.toString().toLowerCase().trim()));
    });

    const validate = (value) => {
        clearTimeout(debounceTimeout);

        debounceTimeout = setTimeout(() => {
            const cleanValue = value?.toString().toLowerCase().trim() || '';

            if (cleanValue && cleanValue.length > 3) {
                isAlreadyUsed.value = existingSet.value.has(cleanValue);
            } else {
                isAlreadyUsed.value = false;
            }
        }, 500);
    };

    return {
        isAlreadyUsed,
        validate
    };
}
