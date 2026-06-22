import { defineStore } from 'pinia';
import Cookies from 'js-cookie';

const COOKIE_KEY = 'app-auth';
const COOKIE_TTL = 1 / 24; // 1 hour

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: null,
        isAuthenticated: false,
    }),

    getters: {
        userName: (state) => state.user?.name ?? 'User',
        userInitial: (state) => (state.user?.name ?? 'U').charAt(0).toUpperCase(),
    },

    actions: {
        login({ token, user }) {
            this.token = token;
            this.user = user;
            this.isAuthenticated = true;
            this._persist();
        },

        logout() {
            this.token = null;
            this.user = null;
            this.isAuthenticated = false;
            Cookies.remove(COOKIE_KEY);
        },

        hydrateState() {
            const raw = Cookies.get(COOKIE_KEY);
            if (raw) {
                try {
                    this.$patch(JSON.parse(raw));
                } catch (_) {
                    Cookies.remove(COOKIE_KEY);
                }
            }
        },

        getToken() {
            return this.token;
        },

        _persist() {
            Cookies.set(COOKIE_KEY, JSON.stringify(this.$state), { expires: COOKIE_TTL });
        },
    },
});
