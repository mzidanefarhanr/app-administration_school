import { defineStore } from 'pinia';
import apiLogin from '../apiLogin';

export const authStore = defineStore('auth', {
    state: () => ({
        authUser: null,
        hasLogin: null,
        AllUsers: null,
        AllUserAssign: null
    }),

    getters: {
        user: (state) => state.authUser,
        users: (state) => state.AllUsers,
        userassign: (state) => state.AllUserAssign
    },

    actions: {
        async getToken() {
            await apiLogin.get('/sanctum/csrf-cookie');
        },

        async checkLogin() {
            // try {
            //     const response = await apiLogin.get('/api/user');
            //     if (response.data && response.data.id) {
            //         this.hasLogin = true;
            //     } else {
            //         this.hasLogin = false;
            //     }
            // } catch (error) {
            //     // Biarkan kosong atau set variabel saja
            //     this.hasLogin = false;
            //     // Jangan ada console.error atau console.log di sini
            // }

            try {
                // Ganti URL ke route baru yang kita buat tadi
                const response = await apiLogin.get('/api/check-auth');

                if (response.data.authenticated) {
                    this.hasLogin = true;
                    // Anda juga bisa mengambil data user di sini: response.data.user
                } else {
                    this.hasLogin = false;
                }
            } catch (error) {
                // Ini hanya akan terpanggil jika terjadi error server (500) atau jaringan
                this.hasLogin = false;
            }
        },

        async getUser() {
            await apiLogin
                .get('/api/user')
                .then((response) => {
                    if (response.data.id != null) {
                        this.authUser = response.data;
                        // console.log(this.authUser.id);
                    } else {
                        this.authUser = null;
                    }
                })
                .catch((error) => {
                    this.authUser = null;
                    // console.log(error);
                });
            await apiLogin
                .get('/api/users')
                .then((response) => {
                    if (response.data.data != null) {
                        this.AllUsers = response.data.data;
                    } else {
                        this.AllUsers = null;
                    }
                })
                .catch((error) => {
                    this.AllUsers = null;
                    // console.log(error);
                });
            this.AllUserAssign = this.AllUsers.find(users => users.id == this.authUser.id);
            // console.log(this.AllUserAssign)
        },

        async getAllUsers() {
            await apiLogin
                .get('/api/user')
                .then((response) => {
                    if (response.data.id != null) {
                        this.AllUsers = response.data;
                    } else {
                        this.AllUsers = null;
                    }
                })
                .catch((error) => {
                    this.AllUsers = null;
                    // console.log(error);
                });

            // console.log(this.AllUsers.id);
            // console.log(this.AllUsers.company);
        },

        async logOut() {
            this.authUser = null;
        }
    },

    persist: {
        storage: sessionStorage // data in sessionStorage is cleared when the page session ends.
    }
});

// export default authStore;
