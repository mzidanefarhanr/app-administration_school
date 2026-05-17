/**
 * Helper Pintar untuk Diff Data
 * @param {Object} oldData - Data asli dari tabel
 * @param {Object} newData - Data dari form (singleData.value)
 * @param {Object} config - Objek berisi mapping field dan label
 * Contoh config: { name: 'Nama Lengkap', email: 'Alamat Email' }
 */
export const getChangeDetails = (oldData, newData, config) => {
    const fields = Object.keys(config); // Mengambil key database (name, email, dll)

    const changes = fields
        .filter(field => {
            const getValue = (data) => {
                if (!data) return "";

                // Cek apakah field ini nested (mengandung titik, misal: 'type_user.name')
                if (field.includes('.')) {
                    const [parent, child] = field.split('.');
                    return data[parent]?.[child] || "";
                }

                // Jika object Select (PrimeVue), ambil .value
                if (typeof data[field] === 'object' && data[field] !== null) {
                    return data[field].value !== undefined ? data[field].value : "";
                }

                return data[field] || "";
            };

            const oldVal = getValue(oldData);
            const newVal = getValue(newData);

            // Lakukan trim jika nilainya string agar perbandingan akurat
            const finalOld = typeof oldVal === 'string' ? oldVal.trim() : oldVal;
            const finalNew = typeof newVal === 'string' ? newVal.trim() : newVal;

            // console.log(`Checking ${field}:`, { oldVal, newVal }); // Cek di Inspect Element -> Console

            return finalOld !== finalNew;
        })
        .map(field => {
            // MENGGANTI NAMA FIELD KE CUSTOM LABEL
            const label = config[field]; // Mengambil 'Nama Lengkap' bukannya 'name'

            const getDisplayText = (data) => {
                if (!data) return "Kosong";

                // Cek nested field untuk label
                if (field.includes('.')) {
                    const [parent, child] = field.split('.');
                    return data[parent]?.[child] || "Kosong";
                }

                if (typeof data[field] === 'object' && data[field] !== null) {
                    return data[field].label || data[field].value || "Kosong";
                }

                return data[field] || "Kosong";
            }

            return `\n [${label}] has been updated. \n From -> \n ${getDisplayText(oldData)} \n To -> \n ${getDisplayText(newData)} \n`;
        });

    return changes.join(', ');
};

export const getChangeDetail = (oldData, newData, config) => {
    const fields = Object.keys(config);
    const beforeObj = {};
    const afterObj = {};

    fields.forEach(field => {
        const getValue = (data) => {
            if (!data) return null;
            if (field.includes('.')) {
                const [parent, child] = field.split('.');
                return data[parent]?.[child];
            }
            return (data[field] && typeof data[field] === 'object') ? data[field].value : data[field];
        };

        const oldVal = getValue(oldData);
        const newVal = getValue(newData);

        // Hanya masukkan ke object jika ada perbedaan nyata
        if (String(oldVal ?? '').trim() !== String(newVal ?? '').trim()) {
            beforeObj[field] = oldVal;
            afterObj[field] = newVal;
        }
    });

    return {
        before: beforeObj,
        after: afterObj,
        hasChanges: Object.keys(afterObj).length > 0
    };
};
