import { defineStore } from 'pinia';
import apiLogin from '../apiLogin';

export const authStore = defineStore('auth', {
    state: () => ({
        authUser: null,
        hasLogin: null,
        abilities: [],
        AllUsers: null,
        AllUserAssign: null
    }),

    getters: {
        user: (state) => state.authUser,
        users: (state) => state.AllUsers,
        userassign: (state) => state.AllUserAssign,
        can: (state) => (ability) => Array.isArray(state.abilities) && state.abilities.includes(ability)
    },

    actions: {
        async getToken() {
            await apiLogin.get('/sanctum/csrf-cookie');
        },

        async checkLogin() {
            try {
                const response = await apiLogin.get('/api/check-auth');

                if (response.data.authenticated) {
                    this.hasLogin = true;
                    this.abilities = response.data.user?.abilities || [];
                } else {
                    this.hasLogin = false;
                    this.abilities = [];
                }
            } catch (error) {
                // Ini hanya akan terpanggil jika terjadi error server (500) atau jaringan
                this.hasLogin = false;
                this.abilities = [];
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
                .catch(() => {
                    this.authUser = null;
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
                .catch(() => {
                    this.AllUsers = null;
                });
            this.AllUserAssign = Array.isArray(this.AllUsers) && this.authUser
                ? this.AllUsers.find(users => users.id == this.authUser.id)
                : null;
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
                .catch(() => {
                    this.AllUsers = null;
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
