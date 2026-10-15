import { defineStore } from 'pinia';
import apiLogin from '../apiLogin';

/**
 * Store identitas & kemampuan pengguna yang login (Fase 01a).
 *
 * Identitas diambil dari kontrak ringan `GET /api/check-auth` (payload minimum),
 * BUKAN dari daftar akun administratif `GET /api/users`. Pengguna tanpa izin
 * pengelolaan akun tetap dapat melihat identitas/peran/kemampuannya sendiri.
 *
 * Daftar akun (`AllUsers`) hanya dimuat bila pengguna memang punya kemampuan
 * `users.view` (widget/administrasi), dan kegagalannya tidak menghapus identitas.
 */
export const authStore = defineStore('auth', {
    state: () => ({
        // Request sequence ID untuk mencegah response lama menimpa state terbaru (race condition)
        _requestId: 0,
        // Identitas sesi (dari check-auth): id, name, username, type_user_id,
        // status_user_id, status_active, abilities[], roles[].
        identity: null,
        hasLogin: null,
        abilities: [],
        // Profil lengkap akun sendiri (dari /api/user), hanya untuk halaman profil.
        selfProfile: null,
        // Daftar akun administratif (opsional, hanya bila diizinkan).
        AllUsers: null,
        // Kegagalan terakhir saat memuat daftar akun (bukan identitas).
        usersError: null
    }),

    getters: {
        user: (state) => state.identity,
        authUser: (state) => state.identity,
        AllUserAssign: (state) => state.identity,
        users: (state) => state.AllUsers,
        // Nama tampilan pengguna login.
        displayName: (state) => state.identity?.name || null,
        // Peran tampilan (slug) pengguna login.
        roles: (state) => state.identity?.roles || [],
        // Pemeriksaan kemampuan bernama terpusat.
        can: (state) => (ability) => Array.isArray(state.abilities) && state.abilities.includes(ability)
    },

    actions: {
        async getToken() {
            await apiLogin.get('/sanctum/csrf-cookie');
        },

        /**
         * Ambil identitas + kemampuan dari kontrak ringan check-auth.
         * Tidak memanggil /api/users. Kegagalan jaringan tidak menghapus
         * identitas yang sudah ada kecuali memang sesi tidak valid.
         * Mengembalikan data user atau null.
         */
        async fetchIdentity() {
            const currentRequestId = ++this._requestId;
            try {
                const response = await apiLogin.get('/api/check-auth');

                // Jika ada request identitas yang lebih baru (mis. login baru dimulai),
                // abaikan response usang ini agar tidak menimpa state terbaru.
                if (currentRequestId !== this._requestId) {
                    return this.identity;
                }

                if (response.data?.authenticated && response.data.user) {
                    this.identity = response.data.user;
                    this.abilities = response.data.user.abilities || [];
                    this.hasLogin = true;
                    return this.identity;
                } else {
                    // Sesi tidak valid → bersihkan identitas.
                    this.clearIdentity();
                    this.hasLogin = false;
                    return null;
                }
            } catch (error) {
                if (currentRequestId !== this._requestId) {
                    return this.identity;
                }

                // Respons non-2xx (mis. server) bukan bukti sesi berakhir;
                // jangan hapus identitas karena kegagalan semata. Namun bila
                // belum ada identitas, tandai belum login.
                if (error.response?.status === 401) {
                    this.clearIdentity();
                    this.hasLogin = false;
                } else if (!this.identity) {
                    this.hasLogin = false;
                }
                return null;
            }
        },

        /**
         * Kompatibilitas: pemeriksaan login berbasis check-auth.
         */
        async checkLogin() {
            return await this.fetchIdentity();
        },

        /**
         * Muat daftar akun administratif HANYA bila diizinkan.
         * Dipakai widget/administrasi; tidak menggantikan identitas sesi.
         */
        async fetchUsers() {
            if (!this.can('users.view')) {
                // Tanpa izin: jangan memanggil /api/users sama sekali.
                this.AllUsers = null;
                return;
            }
            this.usersError = null;
            try {
                const response = await apiLogin.get('/api/users');
                this.AllUsers = response.data?.data ?? null;
            } catch (error) {
                this.AllUsers = null;
                this.usersError = error.response?.status || 'network';
            }
        },

        /**
         * Kompatibilitas: ambil identitas sesi (dari check-auth).
         * Tidak lagi memuat seluruh daftar akun untuk mencari akun sendiri.
         */
        async getUser() {
            return await this.fetchIdentity();
        },

        /**
         * Ambil profil lengkap akun sendiri dari endpoint self-profile
         * terlindungi `GET /api/user` (mengembalikan actor). Hanya dipakai
         * halaman profil; tidak menggantikan identitas ringan.
         */
        async fetchSelfProfile() {
            this.selfProfile = null;
            try {
                const response = await apiLogin.get('/api/user');
                this.selfProfile = response.data ?? null;
            } catch (error) {
                this.selfProfile = null;
            }
            return this.selfProfile;
        },

        clearIdentity() {
            this.identity = null;
            this.abilities = [];
        },

        clearUsers() {
            this.AllUsers = null;
            this.usersError = null;
        },

        /**
         * Logout: akhiri sesi di backend lalu bersihkan seluruh state.
         * Tidak bergantung pada id dari daftar akun.
         */
        async logOut() {
            try {
                await apiLogin.post('/logout');
            } catch (error) {
                // Meski request logout gagal, tetap bersihkan state lokal
                // agar tidak ada identitas yang tertinggal.
            } finally {
                this.$reset();
            }
        }
    },

    persist: {
        storage: sessionStorage // data in sessionStorage is cleared when the page session ends.
    }
});
