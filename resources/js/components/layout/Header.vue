<template>
  <nav class="bg-white shadow">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between h-16">
        <div class="flex items-center">
          <router-link to="/" class="flex items-center">
            <h1 class="text-xl font-semibold text-gray-900">Social Media Platform</h1>
          </router-link>
          <div class="ml-4 flex items-center">
            <div class="flex items-center space-x-2">
              <div :class="systemStatusClass" class="w-2 h-2 rounded-full"></div>
              <span class="text-sm text-gray-600">{{ systemStatusText }}</span>
            </div>
          </div>
        </div>
        <div class="flex items-center space-x-4">
          <router-link
            to="/"
            class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium"
            :class="{ 'text-indigo-600 border-b-2 border-indigo-600': $route.name === 'Dashboard' }"
          >
            Dashboard
          </router-link>
          <router-link
            to="/accounts"
            class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium"
            :class="{ 'text-indigo-600 border-b-2 border-indigo-600': $route.name === 'AccountSettings' }"
          >
            Accounts
          </router-link>
          <router-link
            to="/brand-guidelines"
            class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium"
            :class="{ 'text-indigo-600 border-b-2 border-indigo-600': $route.name === 'BrandGuidelines' }"
          >
            Brand Guidelines
          </router-link>
          <router-link
            to="/posts"
            class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium"
            :class="{ 'text-indigo-600 border-b-2 border-indigo-600': $route.name === 'PostManagement' }"
          >
            Posts
          </router-link>
          <router-link
            to="/calendar"
            class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium"
            :class="{ 'text-indigo-600 border-b-2 border-indigo-600': $route.name === 'Calendar' }"
          >
            Calendar
          </router-link>
          <router-link
            to="/analytics"
            class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium"
            :class="{ 'text-indigo-600 border-b-2 border-indigo-600': $route.name === 'Analytics' }"
          >
            Analytics
          </router-link>
          <router-link
            to="/media"
            class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium"
            :class="{ 'text-indigo-600 border-b-2 border-indigo-600': $route.name === 'MediaManager' }"
          >
            Media
          </router-link>
          
          <!-- User Menu Dropdown -->
          <div class="relative ml-3">
            <div>
              <button
                @click="showUserMenu = !showUserMenu"
                class="flex items-center text-sm rounded-full text-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                id="user-menu-button"
                aria-expanded="false"
                aria-haspopup="true"
              >
                <span class="sr-only">Open user menu</span>
                <div class="h-8 w-8 rounded-full bg-indigo-600 flex items-center justify-center">
                  <span class="text-sm font-medium text-white">
                    {{ userInitials }}
                  </span>
                </div>
              </button>
            </div>
            
            <transition
              enter-active-class="transition ease-out duration-100"
              enter-from-class="transform opacity-0 scale-95"
              enter-to-class="transform opacity-100 scale-100"
              leave-active-class="transition ease-in duration-75"
              leave-from-class="transform opacity-100 scale-100"
              leave-to-class="transform opacity-0 scale-95"
            >
              <div
                v-show="showUserMenu"
                class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-50"
                role="menu"
                aria-orientation="vertical"
                aria-labelledby="user-menu-button"
                tabindex="-1"
              >
                <div class="px-4 py-2 text-sm text-gray-700 border-b border-gray-200">
                  <div class="font-medium">{{ authStore.user?.name || 'User' }}</div>
                  <div class="text-gray-500">{{ authStore.user?.email || '' }}</div>
                </div>
                <a
                  href="#"
                  @click.prevent="handleLogout"
                  class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                  role="menuitem"
                  tabindex="-1"
                >
                  Sign out
                </a>
              </div>
            </transition>
          </div>
        </div>
      </div>
    </div>
  </nav>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import axios from 'axios';

export default {
  name: 'Header',
  setup() {
    const router = useRouter();
    const authStore = useAuthStore();
    const showUserMenu = ref(false);
    const systemStatus = ref({});
    
    const handleLogout = async () => {
      showUserMenu.value = false;
      await authStore.logout();
      router.push('/login');
    };

    const userInitials = computed(() => {
      const name = authStore.user?.name || 'U';
      const parts = name.split(' ');
      if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
      }
      return name[0].toUpperCase();
    });

    const systemStatusClass = computed(() => {
      if (!systemStatus.value || Object.keys(systemStatus.value).length === 0) {
        return 'bg-gray-400';
      }
      
      const hasUnhealthy = Object.values(systemStatus.value).some(s => s === 'unhealthy');
      const hasWarning = Object.values(systemStatus.value).some(s => s === 'warning');
      
      if (hasUnhealthy) return 'bg-red-400';
      if (hasWarning) return 'bg-yellow-400';
      return 'bg-green-400';
    });

    const systemStatusText = computed(() => {
      if (!systemStatus.value || Object.keys(systemStatus.value).length === 0) {
        return 'Unknown';
      }
      
      const hasUnhealthy = Object.values(systemStatus.value).some(s => s === 'unhealthy');
      const hasWarning = Object.values(systemStatus.value).some(s => s === 'warning');
      
      if (hasUnhealthy) return 'System Issues';
      if (hasWarning) return 'Minor Issues';
      return 'All Systems Operational';
    });

    const loadSystemStatus = async () => {
      try {
        const response = await axios.get('/api/system/status');
        systemStatus.value = response.data;
      } catch (error) {
        console.error('Failed to load system status:', error);
        systemStatus.value = {};
      }
    };

    // Close user menu when clicking outside
    const handleClickOutside = (event) => {
      if (showUserMenu.value && !event.target.closest('#user-menu-button') && !event.target.closest('[role="menu"]')) {
        showUserMenu.value = false;
      }
    };

    onMounted(() => {
      loadSystemStatus();
      document.addEventListener('click', handleClickOutside);
      
      // Refresh system status every 2 minutes
      const interval = setInterval(loadSystemStatus, 2 * 60 * 1000);
      
      // Store interval for cleanup
      return () => {
        clearInterval(interval);
        document.removeEventListener('click', handleClickOutside);
      };
    });

    onUnmounted(() => {
      document.removeEventListener('click', handleClickOutside);
    });
    
    return {
      handleLogout,
      showUserMenu,
      userInitials,
      systemStatusClass,
      systemStatusText,
      authStore
    };
  }
};
</script>