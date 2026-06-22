import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './components/App.vue';
import router from './router/routes.js';
import { useAuthStore } from './store/auth.js';
import AxiosPlugin from './plugins/axios.js';
import fontAwesomePlugin from './plugins/fontawesome.js';
import installToast from './plugins/toast.js';

const app = createApp(App);

// Pinia must be registered before any store is used
const pinia = createPinia();
app.use(pinia);

// Hydrate auth state from cookie on every page load
const authStore = useAuthStore();
authStore.hydrateState();

app.use(AxiosPlugin);
app.use(fontAwesomePlugin);
installToast(app);
app.use(router);

// Provide auth store for any Options API components that inject it
app.provide('authStore', authStore);

app.mount('#app');
