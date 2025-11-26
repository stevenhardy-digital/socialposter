<template>
  <div>
    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">System Status</h1>
      <p class="mt-2 text-sm text-gray-600">Monitor system health and performance</p>
    </div>

        <!-- Overall Status -->
        <div class="mb-8">
          <div class="bg-white shadow rounded-lg p-6">
            <div class="flex items-center justify-between">
              <div>
                <h2 class="text-lg font-medium text-gray-900">Overall System Status</h2>
                <p class="mt-1 text-sm text-gray-500">Last updated: {{ formatDate(lastUpdated) }}</p>
              </div>
              <div class="flex items-center">
                <div :class="overallStatusClass" class="w-4 h-4 rounded-full mr-3"></div>
                <span class="text-lg font-medium" :class="overallStatusTextClass">{{ overallStatusText }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Health Checks Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
          <div
            v-for="(check, name) in healthData.checks"
            :key="name"
            class="bg-white shadow rounded-lg p-6"
          >
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-medium text-gray-900 capitalize">{{ formatCheckName(name) }}</h3>
              <div :class="getStatusClass(check.status)" class="w-3 h-3 rounded-full"></div>
            </div>
            <div class="space-y-2">
              <p class="text-sm text-gray-600">{{ check.message }}</p>
              <div v-if="check.response_time" class="text-xs text-gray-500">
                Response time: {{ check.response_time }}ms
              </div>
              <div v-if="check.queue_size !== undefined" class="text-xs text-gray-500">
                Queue size: {{ check.queue_size }}
              </div>
              <div v-if="check.failed_jobs !== undefined" class="text-xs text-gray-500">
                Failed jobs: {{ check.failed_jobs }}
              </div>
              <div v-if="check.usage_percent !== undefined" class="text-xs text-gray-500">
                Usage: {{ check.usage_percent }}%
              </div>
            </div>
          </div>
        </div>

        <!-- API Status -->
        <div class="mb-8">
          <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Social Media API Status</h3>
            <div v-if="healthData.checks?.social_apis?.apis" class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div
                v-for="(api, platform) in healthData.checks.social_apis.apis"
                :key="platform"
                class="border border-gray-200 rounded-lg p-4"
              >
                <div class="flex items-center justify-between mb-2">
                  <span class="font-medium capitalize">{{ platform }}</span>
                  <div :class="getStatusClass(api.status)" class="w-3 h-3 rounded-full"></div>
                </div>
                <p class="text-sm text-gray-600">{{ api.message || 'Status check completed' }}</p>
                <div v-if="api.response_time_ms" class="text-xs text-gray-500 mt-1">
                  {{ api.response_time_ms }}ms
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Performance Metrics -->
        <div class="mb-8">
          <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Performance Metrics</h3>
            <div v-if="performanceData" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
              <div class="text-center">
                <div class="text-2xl font-bold text-blue-600">{{ performanceData.api_calls?.total || 0 }}</div>
                <div class="text-sm text-gray-500">API Calls (24h)</div>
              </div>
              <div class="text-center">
                <div class="text-2xl font-bold text-green-600">{{ performanceData.content_generation?.total || 0 }}</div>
                <div class="text-sm text-gray-500">Content Generated (24h)</div>
              </div>
              <div class="text-center">
                <div class="text-2xl font-bold text-purple-600">{{ performanceData.posts?.published || 0 }}</div>
                <div class="text-sm text-gray-500">Posts Published (24h)</div>
              </div>
              <div class="text-center">
                <div class="text-2xl font-bold text-red-600">{{ performanceData.errors?.total || 0 }}</div>
                <div class="text-sm text-gray-500">Errors (24h)</div>
              </div>
            </div>
          </div>
        </div>

        <!-- System Alerts -->
        <div v-if="systemAlerts && systemAlerts.length > 0" class="mb-8">
          <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">System Alerts</h3>
            <div class="space-y-4">
              <div
                v-for="alert in systemAlerts"
                :key="alert.message"
                :class="getAlertClass(alert.type)"
                class="p-4 rounded-md"
              >
                <div class="flex">
                  <div class="flex-shrink-0">
                    <svg v-if="alert.type === 'error'" class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <svg v-else class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                  </div>
                  <div class="ml-3">
                    <p class="text-sm font-medium" :class="getAlertTextClass(alert.type)">
                      {{ alert.message }}
                    </p>
                    <div v-if="alert.metric" class="text-xs text-gray-500 mt-1">
                      Metric: {{ alert.metric }} = {{ alert.value }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex space-x-4">
          <button
            @click="refreshStatus"
            :disabled="loading"
            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
          >
            <svg v-if="loading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ loading ? 'Refreshing...' : 'Refresh Status' }}
          </button>
          <button
            @click="runWorkflowTest"
            :disabled="loading"
            class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
          >
            Run Workflow Test
          </button>
        </div>

        <!-- Workflow Test Results -->
        <div v-if="workflowResults" class="mt-8">
          <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Workflow Test Results</h3>
            <div class="space-y-4">
              <div
                v-for="(result, test) in workflowResults.results"
                :key="test"
                class="flex items-center justify-between p-3 border border-gray-200 rounded-lg"
              >
                <div>
                  <span class="font-medium capitalize">{{ formatTestName(test) }}</span>
                  <p class="text-sm text-gray-600">{{ result.message }}</p>
                </div>
                <div :class="getStatusClass(result.status)" class="w-3 h-3 rounded-full"></div>
              </div>
            </div>
          </div>
  </div>
</template>

<script>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

export default {
  name: 'SystemStatus',
  setup() {
    const healthData = ref({});
    const performanceData = ref(null);
    const systemAlerts = ref([]);
    const workflowResults = ref(null);
    const loading = ref(false);
    const lastUpdated = ref(new Date());

    const loadSystemHealth = async () => {
      try {
        const response = await axios.get('/api/system/health');
        healthData.value = response.data;
        lastUpdated.value = new Date();
      } catch (error) {
        console.error('Failed to load system health:', error);
      }
    };

    const loadPerformanceData = async () => {
      try {
        const response = await axios.get('/api/system/performance');
        performanceData.value = response.data.performance_summary;
        systemAlerts.value = response.data.system_alerts;
      } catch (error) {
        console.error('Failed to load performance data:', error);
        // Fallback to mock data
        performanceData.value = {
          api_calls: { total: 0, failures: 0 },
          content_generation: { total: 0, success_rate: 0 },
          posts: { published: 0, failures: 0 },
          errors: { total: 0 }
        };
      }
    };

    const loadSystemAlerts = async () => {
      // System alerts are now loaded as part of loadPerformanceData
      // This function is kept for consistency but doesn't need to do anything
    };

    const refreshStatus = async () => {
      loading.value = true;
      try {
        await Promise.all([
          loadSystemHealth(),
          loadPerformanceData(),
          loadSystemAlerts()
        ]);
      } finally {
        loading.value = false;
      }
    };

    const runWorkflowTest = async () => {
      loading.value = true;
      try {
        const response = await axios.post('/api/system/test-workflow');
        workflowResults.value = response.data;
      } catch (error) {
        console.error('Workflow test failed:', error);
        workflowResults.value = {
          status: 'failed',
          results: {
            error: {
              status: 'failed',
              message: 'Workflow test failed: ' + error.message
            }
          }
        };
      } finally {
        loading.value = false;
      }
    };

    const overallStatusClass = computed(() => {
      if (!healthData.value.status) return 'bg-gray-400';
      return healthData.value.status === 'healthy' ? 'bg-green-400' : 'bg-red-400';
    });

    const overallStatusText = computed(() => {
      if (!healthData.value.status) return 'Unknown';
      return healthData.value.status === 'healthy' ? 'All Systems Operational' : 'System Issues Detected';
    });

    const overallStatusTextClass = computed(() => {
      if (!healthData.value.status) return 'text-gray-600';
      return healthData.value.status === 'healthy' ? 'text-green-600' : 'text-red-600';
    });

    const getStatusClass = (status) => {
      const classes = {
        healthy: 'bg-green-400',
        warning: 'bg-yellow-400',
        unhealthy: 'bg-red-400',
        error: 'bg-red-400',
        passed: 'bg-green-400',
        failed: 'bg-red-400'
      };
      return classes[status] || 'bg-gray-400';
    };

    const getAlertClass = (type) => {
      const classes = {
        error: 'bg-red-50 border border-red-200',
        warning: 'bg-yellow-50 border border-yellow-200'
      };
      return classes[type] || 'bg-gray-50 border border-gray-200';
    };

    const getAlertTextClass = (type) => {
      const classes = {
        error: 'text-red-800',
        warning: 'text-yellow-800'
      };
      return classes[type] || 'text-gray-800';
    };

    const formatCheckName = (name) => {
      return name.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    };

    const formatTestName = (name) => {
      return name.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    };

    const formatDate = (date) => {
      return new Date(date).toLocaleString();
    };

    onMounted(() => {
      refreshStatus();
      
      // Auto-refresh every 30 seconds
      const interval = setInterval(refreshStatus, 30000);
      
      return () => clearInterval(interval);
    });

    return {
      healthData,
      performanceData,
      systemAlerts,
      workflowResults,
      loading,
      lastUpdated,
      refreshStatus,
      runWorkflowTest,
      overallStatusClass,
      overallStatusText,
      overallStatusTextClass,
      getStatusClass,
      getAlertClass,
      getAlertTextClass,
      formatCheckName,
      formatTestName,
      formatDate
    };
  }
};
</script>