<template>
  <div class="min-h-screen bg-gray-50">
    <nav class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex items-center">
            <h1 class="text-xl font-semibold text-gray-900">Social Media Platform</h1>
            <div class="ml-4 flex items-center">
              <div class="flex items-center space-x-2">
                <div :class="systemStatusClass" class="w-2 h-2 rounded-full"></div>
                <span class="text-sm text-gray-600">{{ systemStatusText }}</span>
              </div>
            </div>
          </div>
          <div class="flex items-center space-x-4">
            <router-link
              to="/accounts"
              class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium"
            >
              Accounts
            </router-link>
            <router-link
              to="/brand-guidelines"
              class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium"
            >
              Brand Guidelines
            </router-link>
            <router-link
              to="/posts"
              class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium"
            >
              Posts
            </router-link>
            <router-link
              to="/calendar"
              class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium"
            >
              Calendar
            </router-link>
            <router-link
              to="/analytics"
              class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium"
            >
              Analytics
            </router-link>
            <router-link
              to="/system-status"
              class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium"
            >
              System Status
            </router-link>
            <button
              @click="handleLogout"
              class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium"
            >
              Logout
            </button>
          </div>
        </div>
      </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
      <div class="px-4 py-6 sm:px-0">
        <!-- System Alerts -->
        <div v-if="dashboardData.system_alerts && dashboardData.system_alerts.length > 0" class="mb-6">
          <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">System Alerts</h3>
            <div class="space-y-3">
              <div
                v-for="alert in dashboardData.system_alerts"
                :key="alert.message"
                :class="alertClasses(alert.type)"
                class="p-4 rounded-md"
              >
                <div class="flex">
                  <div class="flex-shrink-0">
                    <svg v-if="alert.type === 'error'" class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <svg v-else-if="alert.type === 'warning'" class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <svg v-else class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                  </div>
                  <div class="ml-3">
                    <p class="text-sm font-medium" :class="alertTextClasses(alert.type)">
                      {{ alert.message }}
                    </p>
                    <p v-if="alert.action" class="mt-1 text-sm" :class="alertActionClasses(alert.type)">
                      {{ alert.action }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Dashboard Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <!-- Connected Accounts -->
          <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                  </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                  <dl>
                    <dt class="text-sm font-medium text-gray-500 truncate">Connected Accounts</dt>
                    <dd class="text-lg font-medium text-gray-900">{{ connectedAccountsCount }}</dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>

          <!-- Total Posts -->
          <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                  <dl>
                    <dt class="text-sm font-medium text-gray-500 truncate">Total Posts</dt>
                    <dd class="text-lg font-medium text-gray-900">{{ dashboardData.post_stats?.total || 0 }}</dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>

          <!-- Scheduled Posts -->
          <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                  <dl>
                    <dt class="text-sm font-medium text-gray-500 truncate">Scheduled Posts</dt>
                    <dd class="text-lg font-medium text-gray-900">{{ dashboardData.post_stats?.scheduled || 0 }}</dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>

          <!-- Engagement Rate -->
          <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                  </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                  <dl>
                    <dt class="text-sm font-medium text-gray-500 truncate">Avg Engagement</dt>
                    <dd class="text-lg font-medium text-gray-900">{{ Math.round(dashboardData.engagement_summary?.average_engagement || 0) }}</dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Connected Accounts Overview -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
          <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
              <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Connected Accounts</h3>
              <div v-if="Object.keys(dashboardData.connected_accounts || {}).length === 0" class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No accounts connected</h3>
                <p class="mt-1 text-sm text-gray-500">Get started by connecting your social media accounts.</p>
                <div class="mt-6">
                  <router-link
                    to="/accounts"
                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
                  >
                    Connect Account
                  </router-link>
                </div>
              </div>
              <div v-else class="space-y-4">
                <div
                  v-for="(accounts, platform) in dashboardData.connected_accounts"
                  :key="platform"
                  class="flex items-center justify-between p-3 border border-gray-200 rounded-lg"
                >
                  <div class="flex items-center">
                    <div class="flex-shrink-0">
                      <div :class="platformIconClass(platform)" class="w-8 h-8 rounded-full flex items-center justify-center">
                        <span class="text-white text-sm font-medium">{{ platform.charAt(0).toUpperCase() }}</span>
                      </div>
                    </div>
                    <div class="ml-3">
                      <p class="text-sm font-medium text-gray-900">{{ platform.charAt(0).toUpperCase() + platform.slice(1) }}</p>
                      <p class="text-sm text-gray-500">{{ accounts.length }} account{{ accounts.length !== 1 ? 's' : '' }}</p>
                    </div>
                  </div>
                  <div class="flex items-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                      Connected
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Post Status Overview -->
          <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
              <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Post Status</h3>
              <div class="space-y-4">
                <div class="flex items-center justify-between">
                  <div class="flex items-center">
                    <div class="w-3 h-3 bg-yellow-400 rounded-full mr-3"></div>
                    <span class="text-sm text-gray-900">Draft</span>
                  </div>
                  <span class="text-sm font-medium text-gray-900">{{ dashboardData.post_stats?.draft || 0 }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <div class="flex items-center">
                    <div class="w-3 h-3 bg-blue-400 rounded-full mr-3"></div>
                    <span class="text-sm text-gray-900">Approved</span>
                  </div>
                  <span class="text-sm font-medium text-gray-900">{{ dashboardData.post_stats?.approved || 0 }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <div class="flex items-center">
                    <div class="w-3 h-3 bg-green-400 rounded-full mr-3"></div>
                    <span class="text-sm text-gray-900">Published</span>
                  </div>
                  <span class="text-sm font-medium text-gray-900">{{ dashboardData.post_stats?.published || 0 }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white shadow rounded-lg">
          <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Recent Posts</h3>
            <div v-if="!dashboardData.recent_posts || dashboardData.recent_posts.length === 0" class="text-center py-8">
              <p class="text-sm text-gray-500">No recent posts found.</p>
            </div>
            <div v-else class="space-y-4">
              <div
                v-for="post in dashboardData.recent_posts"
                :key="post.id"
                class="flex items-start space-x-3 p-3 border border-gray-200 rounded-lg"
              >
                <div class="flex-shrink-0">
                  <div :class="platformIconClass(post.social_account.platform)" class="w-8 h-8 rounded-full flex items-center justify-center">
                    <span class="text-white text-xs font-medium">{{ post.social_account.platform.charAt(0).toUpperCase() }}</span>
                  </div>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm text-gray-900 truncate">{{ post.content }}</p>
                  <div class="mt-1 flex items-center space-x-4 text-xs text-gray-500">
                    <span>{{ post.social_account.account_name }}</span>
                    <span>{{ formatDate(post.created_at) }}</span>
                    <span :class="statusClass(post.status)" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
                      {{ post.status }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import axios from 'axios';

export default {
  name: 'Dashboard',
  setup() {
    const router = useRouter();
    const authStore = useAuthStore();
    const dashboardData = ref({});
    const loading = ref(true);
    
    const handleLogout = async () => {
      await authStore.logout();
      router.push('/login');
    };

    const loadDashboardData = async () => {
      try {
        loading.value = true;
        const response = await axios.get('/api/system/dashboard');
        dashboardData.value = response.data;
      } catch (error) {
        console.error('Failed to load dashboard data:', error);
      } finally {
        loading.value = false;
      }
    };

    const connectedAccountsCount = computed(() => {
      if (!dashboardData.value.connected_accounts) return 0;
      return Object.values(dashboardData.value.connected_accounts)
        .reduce((total, accounts) => total + accounts.length, 0);
    });

    const systemStatusClass = computed(() => {
      const status = dashboardData.value.system_status;
      if (!status) return 'bg-gray-400';
      
      const hasUnhealthy = Object.values(status).some(s => s === 'unhealthy');
      const hasWarning = Object.values(status).some(s => s === 'warning');
      
      if (hasUnhealthy) return 'bg-red-400';
      if (hasWarning) return 'bg-yellow-400';
      return 'bg-green-400';
    });

    const systemStatusText = computed(() => {
      const status = dashboardData.value.system_status;
      if (!status) return 'Unknown';
      
      const hasUnhealthy = Object.values(status).some(s => s === 'unhealthy');
      const hasWarning = Object.values(status).some(s => s === 'warning');
      
      if (hasUnhealthy) return 'System Issues';
      if (hasWarning) return 'Minor Issues';
      return 'All Systems Operational';
    });

    const platformIconClass = (platform) => {
      const classes = {
        instagram: 'bg-pink-500',
        facebook: 'bg-blue-600',
        linkedin: 'bg-blue-700'
      };
      return classes[platform] || 'bg-gray-500';
    };

    const statusClass = (status) => {
      const classes = {
        draft: 'bg-yellow-100 text-yellow-800',
        approved: 'bg-blue-100 text-blue-800',
        published: 'bg-green-100 text-green-800',
        failed: 'bg-red-100 text-red-800'
      };
      return classes[status] || 'bg-gray-100 text-gray-800';
    };

    const alertClasses = (type) => {
      const classes = {
        error: 'bg-red-50 border border-red-200',
        warning: 'bg-yellow-50 border border-yellow-200',
        info: 'bg-blue-50 border border-blue-200'
      };
      return classes[type] || 'bg-gray-50 border border-gray-200';
    };

    const alertTextClasses = (type) => {
      const classes = {
        error: 'text-red-800',
        warning: 'text-yellow-800',
        info: 'text-blue-800'
      };
      return classes[type] || 'text-gray-800';
    };

    const alertActionClasses = (type) => {
      const classes = {
        error: 'text-red-700',
        warning: 'text-yellow-700',
        info: 'text-blue-700'
      };
      return classes[type] || 'text-gray-700';
    };

    const formatDate = (dateString) => {
      const date = new Date(dateString);
      return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    onMounted(() => {
      loadDashboardData();
      
      // Refresh dashboard data every 5 minutes
      const interval = setInterval(loadDashboardData, 5 * 60 * 1000);
      
      // Cleanup interval on unmount
      return () => clearInterval(interval);
    });
    
    return {
      handleLogout,
      dashboardData,
      loading,
      connectedAccountsCount,
      systemStatusClass,
      systemStatusText,
      platformIconClass,
      statusClass,
      alertClasses,
      alertTextClasses,
      alertActionClasses,
      formatDate
    };
  }
};
</script>