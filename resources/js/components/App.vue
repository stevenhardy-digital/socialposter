<template>
  <div id="app" class="min-h-screen bg-gray-50">
    <LoadingScreen v-if="!authStore.isAuthInitialized" />
    <router-view v-else />
  </div>
</template>

<script>
import { useAuthStore } from '../stores/auth';
import LoadingScreen from './LoadingScreen.vue';

export default {
  name: 'App',
  components: {
    LoadingScreen
  },
  setup() {
    const authStore = useAuthStore();
    
    return {
      authStore
    };
  }
};
</script>