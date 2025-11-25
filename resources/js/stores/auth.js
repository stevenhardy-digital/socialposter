import { defineStore } from 'pinia';
import axios from 'axios';
import router from '../router';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem('token'),
        isAuthenticated: false,
        tokenExpiry: localStorage.getItem('token_expiry')
    }),

    getters: {
        getUser: (state) => state.user,
        getToken: (state) => state.token,
        isLoggedIn: (state) => state.isAuthenticated
    },

    actions: {
        async login(credentials) {
            try {
                const response = await axios.post('/api/auth/login', credentials);
                
                this.setAuthData(response.data);
                
                return response.data;
            } catch (error) {
                if (error.response && error.response.data) {
                    throw error.response.data;
                }
                throw { message: 'Login failed. Please try again.' };
            }
        },

        async register(userData) {
            try {
                const response = await axios.post('/api/auth/register', userData);
                
                this.setAuthData(response.data);
                
                return response.data;
            } catch (error) {
                if (error.response && error.response.data) {
                    throw error.response.data;
                }
                throw { message: 'Registration failed. Please try again.' };
            }
        },

        async logout() {
            try {
                if (this.token) {
                    await axios.post('/api/auth/logout');
                }
            } catch (error) {
                // Continue with logout even if API call fails
                console.warn('Logout API call failed:', error);
            } finally {
                this.clearAuthData();
                
                // Redirect to login page
                if (router.currentRoute.value.path !== '/login') {
                    router.push('/login');
                }
            }
        },

        async fetchUser() {
            try {
                const response = await axios.get('/api/auth/user');
                this.user = response.data;
                this.isAuthenticated = true;
                return response.data;
            } catch (error) {
                if (error.response && error.response.status === 401) {
                    // Token is invalid or expired
                    this.handleSessionExpiry();
                }
                throw error;
            }
        },

        initializeAuth() {
            if (this.token) {
                // Check if token is expired
                if (this.isTokenExpired()) {
                    this.handleSessionExpiry();
                    return;
                }
                
                axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
                this.fetchUser().catch(() => {
                    this.handleSessionExpiry();
                });
            }
        },

        setAuthData(data) {
            this.token = data.token;
            this.user = data.user;
            this.isAuthenticated = true;
            
            // Set token expiry (24 hours from now as default)
            const expiry = new Date();
            expiry.setHours(expiry.getHours() + 24);
            this.tokenExpiry = expiry.toISOString();
            
            localStorage.setItem('token', this.token);
            localStorage.setItem('token_expiry', this.tokenExpiry);
            axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
        },

        clearAuthData() {
            this.token = null;
            this.user = null;
            this.isAuthenticated = false;
            this.tokenExpiry = null;
            
            localStorage.removeItem('token');
            localStorage.removeItem('token_expiry');
            delete axios.defaults.headers.common['Authorization'];
        },

        isTokenExpired() {
            if (!this.tokenExpiry) return false;
            return new Date() > new Date(this.tokenExpiry);
        },

        handleSessionExpiry() {
            this.clearAuthData();
            
            // Redirect to login if not already there
            if (router.currentRoute.value.path !== '/login') {
                router.push('/login');
            }
        }
    }
});