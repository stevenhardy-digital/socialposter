import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Response interceptor to handle token expiration
axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response && error.response.status === 401) {
            // Token expired or invalid - let the auth store handle it
            const event = new CustomEvent('auth:session-expired');
            window.dispatchEvent(event);
        }
        return Promise.reject(error);
    }
);
