import { createPinia } from 'pinia';
import { createApp } from 'vue';

import App from './App.vue';
import router from './router';

import Notifications from '@kyvg/vue3-notification';
import Aura from '@primeuix/themes/aura';
import piniaPluginPersistedState from 'pinia-plugin-persistedstate';
import PrimeVue from 'primevue/config';
import ConfirmationService from 'primevue/confirmationservice';
import StyleClass from 'primevue/styleclass';
import ToastService from 'primevue/toastservice';

import '@/assets/styles.scss';

const pinia = createPinia();
pinia.use(piniaPluginPersistedState);

const app = createApp(App);

app.use(pinia);
app.use(Notifications);
app.use(router);
app.use(PrimeVue, {
    theme: {
        preset: Aura,
        options: {
            darkModeSelector: '.app-dark'
        }
    }
});
app.use(ToastService);
app.use(ConfirmationService);
// Register the PrimeVue StyleClass directive used by v-styleclass in the layout.
app.directive('styleclass', StyleClass);

if (import.meta.env.PROD) {
    console.error = () => {};
    console.warn  = () => {};
    console.log   = () => {};
}

app.mount('#app');
