import './bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router';
import App from './components/App.vue';
import { useAuthStore } from './stores/auth';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

// Initialize auth store after pinia is set up
const authStore = useAuthStore();

// Listen for session expiry events
window.addEventListener('auth:session-expired', () => {
    authStore.handleSessionExpiry();
});

// Initialize auth before mounting the app
authStore.initializeAuth().then(() => {
    app.mount('#app');
});
