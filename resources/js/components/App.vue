<template>
  <div id="app" class="min-h-screen bg-gray-50">
    <router-view />
  </div>
</template>

<script>
import { useAuthStore } from '../stores/auth';

export default {
  name: 'App',
  setup() {
    const authStore = useAuthStore();
    
    // Initialize authentication on app start
    authStore.initializeAuth();
    
    return {
      authStore
    };
  }
};
</script>