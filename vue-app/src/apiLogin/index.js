//import axios
import { notify } from '@kyvg/vue3-notification';
import axios from 'axios';

// Base URL API WAJIB berasal dari konfigurasi (VITE_API_BASE_URL).
// Tidak ada fallback diam-diam ke alamat lama: bila tidak tersedia/valid,
// tampilkan kesalahan konfigurasi yang jelas dan jangan mengirim request.
const configuredBaseUrl = import.meta.env.VITE_API_BASE_URL;

const isValidBaseUrl = (value) => {
    if (typeof value !== 'string' || value.trim() === '') return false;
    try {
        const url = new URL(value);
        return url.protocol === 'http:' || url.protocol === 'https:';
    } catch {
        return false;
    }
};

if (!isValidBaseUrl(configuredBaseUrl)) {
    // Tampilkan kesalahan konfigurasi (English) tanpa mengirim request ke alamat lama.
    notify({
        type: 'error',
        title: 'Configuration error',
        text: 'VITE_API_BASE_URL is missing or invalid. Set it to your API base URL (e.g. http://127.0.0.1:8001) before running the app.',
        duration: -1
    });
    // Gagalkan lebih awal agar tidak ada request ke endpoint yang salah.
    throw new Error('Configuration error: VITE_API_BASE_URL is missing or invalid.');
}

const apiLogin = axios.create({
    //set default endpoint API
    baseURL: configuredBaseUrl,
    withCredentials: true,
    withXSRFToken: true
});

axios.interceptors.response.use(
    (response) => response,
    (error) => {
        // Bedakan sesi kedaluwarsa (401) dari penolakan izin (403) dan
        // kegagalan koneksi (tanpa response). Jangan logout pada 403.
        if (error.response?.status === 401) {
            notify({
                type: 'error',
                title: 'Session expired',
                text: error.response.data?.message || 'Your session has expired. Please sign in again.'
            });
        } else if (!error.response) {
            notify({
                type: 'error',
                title: 'Connection error',
                text: 'Could not reach the API server. Check that it is running and reachable.'
            });
        }
        return Promise.reject(error);
    }
);
apiLogin.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
apiLogin.defaults.headers.common['Accept'] = 'application/json';

export default apiLogin;
