<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import apiLogin from '@/apiLogin';
import { authStore } from '@/stores/authStore.js';

const { t } = useI18n();
const router = useRouter();
const useAuthStore = authStore();

const showPassword = ref(false);
const isSubmitting = ref(false);

const form = ref({
    username: '',
    password: '',
    remember: false
});

const fieldErrors = ref({
    username: '',
    password: ''
});

// alert: null | { type: 'processing' | 'danger' | 'warning' | 'info' | 'network' | 'success', title: string, message: string, canRetry?: boolean }
const alert = ref(null);

const togglePasswordVisibility = () => {
    showPassword.value = !showPassword.value;
};

const clearUsernameError = () => {
    if (fieldErrors.value.username) {
        fieldErrors.value.username = '';
    }
};

const clearPasswordError = () => {
    if (fieldErrors.value.password) {
        fieldErrors.value.password = '';
    }
};

const submitLogin = async () => {
    if (isSubmitting.value) return;

    // Reset validasi lokal
    fieldErrors.value.username = '';
    fieldErrors.value.password = '';
    alert.value = null;

    let hasValidationError = false;
    const trimmedUsername = form.value.username ? form.value.username.trim() : '';

    if (!trimmedUsername) {
        fieldErrors.value.username = 'Nama pengguna wajib diisi.';
        hasValidationError = true;
    }

    if (!form.value.password) {
        fieldErrors.value.password = 'Kata sandi wajib diisi.';
        hasValidationError = true;
    }

    if (hasValidationError) {
        return;
    }

    isSubmitting.value = true;
    alert.value = {
        type: 'processing',
        title: 'Memeriksa akun Anda',
        message: 'Mohon tunggu sebentar.'
    };

    // Bersihkan sesi / identitas sebelumnya
    useAuthStore.$reset();

    try {
        // Pastikan cookie CSRF Sanctum aktif sebelum otentikasi
        await apiLogin.get('/sanctum/csrf-cookie');

        const payload = {
            username: trimmedUsername,
            password: form.value.password,
            remember: Boolean(form.value.remember)
        };

        const response = await apiLogin.post('/login', payload);

        if (response.status === 204 || response.status === 200) {
            // Tunggu identitas dan kemampuan siap sebelum navigasi
            const currentUser = await useAuthStore.fetchIdentity();

            if (!currentUser || !useAuthStore.hasLogin) {
                throw new Error('Failed to validate user session.');
            }

            // Validasi status akun aktif (status_active boolean / status_user_id)
            if (currentUser.status_active === false || (currentUser.status_user_id !== undefined && currentUser.status_user_id !== 1)) {
                await useAuthStore.logOut();
                alert.value = {
                    type: 'warning',
                    title: 'Akun Anda sedang nonaktif',
                    message: 'Hubungi operator sekolah untuk mengaktifkannya kembali.'
                };
                isSubmitting.value = false;
                return;
            }

            // Catat login pertama akun actor sendiri jika didukung backend
            try {
                await apiLogin.post(`/api/firstlogin/${currentUser.id}`, {
                    first_login_at: true,
                    _method: 'PATCH'
                });
            } catch (firstLoginErr) {
                console.warn('First login update deferred:', firstLoginErr);
            }

            alert.value = {
                type: 'success',
                title: 'Berhasil Masuk',
                message: 'Login tervalidasi. Mengalihkan ke dashboard...'
            };

            setTimeout(() => {
                isSubmitting.value = false;
                router.push({ path: '/' });
            }, 800);
        } else {
            throw new Error('Unexpected login response status: ' + response.status);
        }
    } catch (error) {
        isSubmitting.value = false;

        if (error.message === 'Failed to validate user session.') {
            alert.value = {
                type: 'danger',
                title: 'Kesalahan Autentikasi',
                message: 'Gagal memvalidasi sesi pengguna setelah login.'
            };
        } else if (!error.response) {
            alert.value = {
                type: 'network',
                title: 'Tidak dapat terhubung ke server',
                message: 'Periksa koneksi internet atau server lokal Anda, lalu coba lagi. Isian Anda tidak hilang.',
                canRetry: true
            };
        } else if (error.response.status === 422) {
            const serverMsg = error.response.data?.message || '';
            const lowerMsg = serverMsg.toLowerCase();
            if (lowerMsg.includes('inactive') || lowerMsg.includes('nonaktif')) {
                alert.value = {
                    type: 'warning',
                    title: 'Akun Anda sedang nonaktif',
                    message: serverMsg || 'Hubungi operator sekolah untuk mengaktifkannya kembali.'
                };
            } else {
                alert.value = {
                    type: 'danger',
                    title: 'Nama pengguna atau kata sandi salah',
                    message: serverMsg || 'Periksa kembali data Anda, lalu coba lagi.'
                };
            }
        } else if (error.response.status === 401) {
            alert.value = {
                type: 'danger',
                title: 'Nama pengguna atau kata sandi salah',
                message: error.response.data?.message || 'Periksa kembali data Anda, lalu coba lagi.'
            };
        } else if (error.response.status === 419) {
            alert.value = {
                type: 'info',
                title: 'Sesi Anda telah berakhir',
                message: 'Token sesi telah kedaluwarsa. Silakan masuk kembali.'
            };
        } else {
            alert.value = {
                type: 'danger',
                title: 'Login Gagal',
                message: error.response.data?.message || error.message || 'Terjadi kesalahan pada sistem. Silakan coba lagi.'
            };
        }
    }
};

onMounted(async () => {
    try {
        await apiLogin.get('/sanctum/csrf-cookie');
    } catch (_) {
        // Abaikan error jaringan awal saat persiapan token
    }

    try {
        const flash = sessionStorage.getItem('auth_flash_message');
        if (flash) {
            sessionStorage.removeItem('auth_flash_message');
            alert.value = {
                type: 'info',
                title: 'Sesi Anda telah berakhir',
                message: flash || 'Masuk kembali untuk melanjutkan pekerjaan Anda.'
            };
        }
    } catch (_) {
        // Abaikan kegagalan baca storage
    }
});
</script>

<template>
    <div class="login-wrapper">
        <!-- Brand Panel (Desktop) -->
        <div class="brand-panel">
            <!-- Brand Header -->
            <div class="brand-header">
                <svg width="48" height="48" viewBox="0 0 48 48" role="img" aria-label="Sekolario">
                    <rect width="48" height="48" rx="12" fill="#059669"></rect>
                    <rect x="18" y="12" width="22" height="4.5" rx="2.25" fill="#ffffff"></rect>
                    <rect x="14" y="21.75" width="22" height="4.5" rx="2.25" fill="#ffffff"></rect>
                    <rect x="10" y="31.5" width="22" height="4.5" rx="2.25" fill="#ffffff"></rect>
                </svg>
                <div class="brand-title-wrap">
                    <div class="brand-title"><span class="brand-accent">S</span><span>ekolario</span></div>
                    <div class="brand-sub">SISTEM ADMINISTRASI SEKOLAH</div>
                </div>
            </div>

            <!-- Brand Slogan -->
            <div class="brand-hero">
                <h2 class="brand-heading">Data, dokumen, dan alur kerja sekolah dalam satu tempat.</h2>
                <p class="brand-desc">Kesiswaan, kepegawaian, persuratan, dan arsip untuk operator, guru, dan pimpinan sekolah.</p>
            </div>

            <!-- Decorative Graphic -->
            <svg class="brand-deco" width="320" height="140" viewBox="0 0 320 140" aria-hidden="true">
                <rect x="120" y="10" width="200" height="30" rx="15" fill="#059669" opacity="0.9"></rect>
                <rect x="70" y="55" width="200" height="30" rx="15" fill="#059669" opacity="0.55"></rect>
                <rect x="20" y="100" width="200" height="30" rx="15" fill="#059669" opacity="0.3"></rect>
            </svg>

            <!-- Brand Footer -->
            <div class="brand-version">Sekolario v1.0</div>
        </div>

        <!-- Form Panel -->
        <div class="form-panel">
            <div class="login-card">
                <!-- Mobile Brand Header (Tampil hanya di layar kecil) -->
                <div class="mobile-brand">
                    <svg width="40" height="40" viewBox="0 0 48 48" role="img" aria-label="Sekolario">
                        <rect width="48" height="48" rx="12" fill="#059669"></rect>
                        <rect x="18" y="12" width="22" height="4.5" rx="2.25" fill="#ffffff"></rect>
                        <rect x="14" y="21.75" width="22" height="4.5" rx="2.25" fill="#ffffff"></rect>
                        <rect x="10" y="31.5" width="22" height="4.5" rx="2.25" fill="#ffffff"></rect>
                    </svg>
                    <div class="mobile-brand-title-wrap">
                        <div class="mobile-brand-title"><span class="text-emerald">S</span><span>ekolario</span></div>
                        <div class="mobile-brand-sub">SISTEM ADMINISTRASI SEKOLAH</div>
                    </div>
                </div>

                <h1 class="card-title">Masuk ke Sekolario</h1>
                <p class="card-subtitle">Gunakan akun yang diberikan oleh operator sekolah Anda.</p>

                <!-- Feedback / Alert Notifications -->
                <div v-if="alert" class="alert-box" :class="`alert-${alert.type}`" :role="alert.type === 'danger' || alert.type === 'warning' || alert.type === 'network' ? 'alert' : 'status'">
                    <!-- Icon Alert Circle for Danger / Warning / Network -->
                    <svg v-if="alert.type === 'danger' || alert.type === 'warning' || alert.type === 'network'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" class="alert-icon">
                        <circle cx="12" cy="12" r="9"></circle>
                        <path d="M12 7v6M12 16.5v.01"></path>
                    </svg>

                    <!-- Icon Info for Session Expired / Processing -->
                    <svg v-else-if="alert.type === 'info' || alert.type === 'processing'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" class="alert-icon">
                        <circle cx="12" cy="12" r="9"></circle>
                        <path d="M12 11v6M12 7.5v.01"></path>
                    </svg>

                    <!-- Icon Success -->
                    <svg v-else-if="alert.type === 'success'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" class="alert-icon">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>

                    <div class="alert-content">
                        <div class="alert-title">{{ alert.title }}</div>
                        <div class="alert-msg">{{ alert.message }}</div>
                        <button v-if="alert.canRetry" type="button" @click="submitLogin" class="btn-retry">
                            Coba lagi
                        </button>
                    </div>
                </div>

                <!-- Form Login -->
                <form @submit.prevent="submitLogin" novalidate>
                    <!-- Input Nama Pengguna -->
                    <div class="form-group">
                        <label for="username" class="form-label">Nama pengguna</label>
                        <div class="input-wrap">
                            <input
                                id="username"
                                type="text"
                                v-model="form.username"
                                @input="clearUsernameError"
                                :disabled="isSubmitting"
                                placeholder="Masukkan nama pengguna"
                                autocomplete="username"
                                class="form-input"
                                :class="{ 'is-invalid': fieldErrors.username }"
                                :aria-invalid="fieldErrors.username ? 'true' : 'false'"
                                :aria-describedby="fieldErrors.username ? 'username-err' : null"
                            />
                        </div>
                        <div v-if="fieldErrors.username" id="username-err" class="field-error">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" class="error-icon">
                                <circle cx="12" cy="12" r="9"></circle>
                                <path d="M12 7v6M12 16.5v.01"></path>
                            </svg>
                            <span>{{ fieldErrors.username }}</span>
                        </div>
                    </div>

                    <!-- Input Kata Sandi -->
                    <div class="form-group">
                        <div class="label-row">
                            <label for="password" class="form-label">Kata sandi</label>
                            <router-link to="/auth/forgot-password" class="forgot-link">Lupa kata sandi?</router-link>
                        </div>
                        <div class="input-wrap relative">
                            <input
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                v-model="form.password"
                                @input="clearPasswordError"
                                :disabled="isSubmitting"
                                placeholder="Masukkan kata sandi"
                                autocomplete="current-password"
                                class="form-input form-input-password"
                                :class="{ 'is-invalid': fieldErrors.password }"
                                :aria-invalid="fieldErrors.password ? 'true' : 'false'"
                                :aria-describedby="fieldErrors.password ? 'password-err' : null"
                            />
                            <button
                                type="button"
                                @click="togglePasswordVisibility"
                                :disabled="isSubmitting"
                                :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                                class="btn-toggle-pw"
                            >
                                {{ showPassword ? 'Sembunyikan' : 'Tampilkan' }}
                            </button>
                        </div>
                        <div v-if="fieldErrors.password" id="password-err" class="field-error">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" class="error-icon">
                                <circle cx="12" cy="12" r="9"></circle>
                                <path d="M12 7v6M12 16.5v.01"></path>
                            </svg>
                            <span>{{ fieldErrors.password }}</span>
                        </div>
                    </div>

                    <!-- Remember Me Checkbox -->
                    <div class="remember-row">
                        <input
                            id="remember"
                            type="checkbox"
                            v-model="form.remember"
                            :disabled="isSubmitting"
                            class="checkbox-input"
                        />
                        <label for="remember" class="checkbox-label">Ingat saya di perangkat ini</label>
                    </div>

                    <!-- Tombol Masuk -->
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        :aria-busy="isSubmitting"
                        class="btn-submit"
                        :class="{ 'btn-loading': isSubmitting }"
                    >
                        <svg v-if="isSubmitting" class="spinner-svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="3" stroke-linecap="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" opacity="0.3"></circle>
                            <path d="M12 3a9 9 0 0 1 9 9"></path>
                        </svg>
                        <span>{{ isSubmitting ? 'Memproses…' : 'Masuk' }}</span>
                    </button>
                </form>

                <!-- Footer Hubungi Operator -->
                <p class="card-footer-text">
                    Belum punya akun atau kendala login? Hubungi operator sekolah.
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Reset & Basis */
.login-wrapper {
    min-height: 100vh;
    display: flex;
    font-family: 'Plus Jakarta Sans', Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #f8fafc;
    color: #0f172a;
    box-sizing: border-box;
}

/* Panel Kiri: Brand & Informasi */
.brand-panel {
    flex: 1 1 50%;
    max-width: 640px;
    background: #0f172a;
    color: #f8fafc;
    padding: 48px 56px;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
}

.brand-header {
    display: flex;
    align-items: center;
    gap: 14px;
}

.brand-title-wrap {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.brand-title {
    font-size: 26px;
    font-weight: 600;
    letter-spacing: -0.5px;
    line-height: 1.1;
    color: #f1f5f9;
}

.brand-accent {
    color: #34d399;
}

.brand-sub {
    font-size: 11px;
    letter-spacing: 1.8px;
    color: #94a3b8;
}

.brand-hero {
    max-width: 440px;
    position: relative;
    z-index: 1;
}

.brand-heading {
    margin: 0 0 16px;
    font-size: 36px;
    line-height: 1.2;
    font-weight: 600;
    letter-spacing: -0.5px;
    color: #f8fafc;
}

.brand-desc {
    margin: 0;
    font-size: 16px;
    line-height: 1.6;
    color: #cbd5e1;
}

.brand-deco {
    position: absolute;
    right: -40px;
    bottom: 40px;
    opacity: 0.9;
    pointer-events: none;
}

.brand-version {
    font-size: 13px;
    color: #94a3b8;
    position: relative;
    z-index: 1;
}

/* Panel Kanan: Form Card */
.form-panel {
    flex: 1 1 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 48px 24px;
    box-sizing: border-box;
}

.login-card {
    width: 100%;
    max-width: 420px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 40px 36px;
    box-sizing: border-box;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
}

/* Mobile Brand Header */
.mobile-brand {
    display: none;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
}

.mobile-brand-title-wrap {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.mobile-brand-title {
    font-size: 22px;
    font-weight: 600;
    letter-spacing: -0.5px;
    line-height: 1.1;
    color: #0f172a;
}

.text-emerald {
    color: #059669;
}

.mobile-brand-sub {
    font-size: 10px;
    letter-spacing: 1.8px;
    color: #64748b;
}

.card-title {
    margin: 0 0 8px;
    font-size: 24px;
    line-height: 1.3;
    font-weight: 600;
    color: #0f172a;
}

.card-subtitle {
    margin: 0 0 28px;
    font-size: 14px;
    line-height: 1.5;
    color: #475569;
}

/* Alert Box */
.alert-box {
    display: flex;
    gap: 12px;
    padding: 14px 16px;
    margin-bottom: 20px;
    border-radius: 8px;
    box-sizing: border-box;
}

.alert-danger, .alert-network {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

.alert-warning {
    background: #fffbeb;
    border: 1px solid #fde68a;
    color: #92400e;
}

.alert-info, .alert-processing {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1e40af;
}

.alert-success {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #065f46;
}

.alert-icon {
    flex: none;
    margin-top: 2px;
}

.alert-content {
    flex: 1 1 auto;
}

.alert-title {
    font-size: 14px;
    font-weight: 600;
    line-height: 1.3;
}

.alert-msg {
    margin-top: 2px;
    font-size: 13px;
    line-height: 1.5;
}

.btn-retry {
    margin-top: 10px;
    height: 34px;
    padding: 0 14px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    color: #991b1b;
    background: #ffffff;
    border: 1px solid #991b1b;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.15s ease;
}

.btn-retry:hover {
    background: #fef2f2;
}

/* Form Fields */
.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 18px;
}

.label-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
}

.form-label {
    font-size: 14px;
    font-weight: 500;
    color: #0f172a;
}

.forgot-link {
    font-size: 13px;
    font-weight: 500;
    color: #047857;
    text-decoration: none;
    transition: color 0.15s ease;
}

.forgot-link:hover {
    color: #065f46;
    text-decoration: underline;
}

.input-wrap {
    position: relative;
    width: 100%;
}

.form-input {
    height: 44px;
    padding: 0 14px;
    font-family: inherit;
    font-size: 14px;
    color: #0f172a;
    background: #ffffff;
    border: 1px solid #64748b;
    border-radius: 8px;
    box-sizing: border-box;
    width: 100%;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.form-input-password {
    padding-right: 96px;
}

.form-input:focus-visible {
    outline: 2px solid #059669;
    outline-offset: 2px;
}

.form-input.is-invalid {
    border-color: #b91c1c !important;
    box-shadow: 0 0 0 1px #b91c1c;
}

.form-input:disabled {
    background: #f1f5f9;
    color: #64748b;
    cursor: not-allowed;
}

.field-error {
    display: flex;
    gap: 6px;
    align-items: center;
    margin-top: 3px;
    font-size: 13px;
    color: #b91c1c;
    line-height: 1.4;
}

.error-icon {
    flex: none;
}

.btn-toggle-pw {
    position: absolute;
    right: 6px;
    top: 6px;
    height: 32px;
    padding: 0 10px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 500;
    color: #047857;
    background: transparent;
    border: 0;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.15s ease, color 0.15s ease;
}

.btn-toggle-pw:hover:not(:disabled) {
    background: #ecfdf5;
    color: #065f46;
}

.btn-toggle-pw:disabled {
    color: #94a3b8;
    cursor: not-allowed;
}

/* Remember Me Checkbox */
.remember-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 24px;
}

.checkbox-input {
    width: 18px;
    height: 18px;
    margin: 0;
    accent-color: #047857;
    cursor: pointer;
}

.checkbox-label {
    font-size: 14px;
    color: #334155;
    cursor: pointer;
    user-select: none;
}

/* Tombol Masuk Submit */
.btn-submit {
    width: 100%;
    height: 46px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-family: inherit;
    font-size: 15px;
    font-weight: 600;
    color: #ffffff;
    background: #047857;
    border: 0;
    border-radius: 8px;
    cursor: pointer;
    transition: background-color 0.15s ease, opacity 0.15s ease;
}

.btn-submit:hover:not(:disabled) {
    background: #065f46;
}

.btn-submit:focus-visible {
    outline: 2px solid #059669;
    outline-offset: 2px;
}

.btn-loading {
    opacity: 0.75;
    cursor: not-allowed;
}

.spinner-svg {
    animation: spin 1s linear infinite;
    flex: none;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Footer Helper */
.card-footer-text {
    margin: 24px 0 0;
    font-size: 13px;
    line-height: 1.5;
    color: #475569;
    text-align: center;
}

/* Responsif: Mobile & Layar <= 900px */
@media (max-width: 900px) {
    .brand-panel {
        display: none !important;
    }

    .form-panel {
        flex: 1 1 100%;
        padding: 32px 16px;
    }

    .login-card {
        padding: 32px 24px;
        box-shadow: none;
        border-color: #e2e8f0;
    }

    .mobile-brand {
        display: flex !important;
    }
}
</style>
