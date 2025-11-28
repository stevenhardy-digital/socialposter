<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Account Settings</h1>
        
        <!-- Connected Accounts Section -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
          <h2 class="text-lg font-medium text-gray-900 mb-4">Connected Social Media Accounts</h2>
          
          <!-- Loading State -->
          <div v-if="loading" class="text-center py-4">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
            <p class="text-gray-600 mt-2">Loading accounts...</p>
          </div>
          
          <!-- Connected Accounts List -->
          <div v-else-if="connectedAccounts.length > 0" class="space-y-4">
            <div 
              v-for="account in connectedAccounts" 
              :key="account.id"
              class="flex items-center justify-between p-4 border border-gray-200 rounded-lg"
            >
              <div class="flex items-center space-x-3">
                <div class="flex-shrink-0">
                  <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                    <span class="text-blue-600 font-medium text-sm">
                      {{ (account.platform || '').charAt(0).toUpperCase() }}
                    </span>
                  </div>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-900">{{ account.account_name }}</p>
                  <p class="text-sm text-gray-500">
                    {{ (account.platform || '').charAt(0).toUpperCase() + (account.platform || '').slice(1) }}
                    <span v-if="account.account_type === 'company'" class="ml-1 px-2 py-0.5 bg-blue-100 text-blue-800 text-xs rounded-full">
                      Company
                    </span>
                    <span v-else-if="account.account_type === 'personal'" class="ml-1 px-2 py-0.5 bg-green-100 text-green-800 text-xs rounded-full">
                      Personal
                    </span>
                  </p>
                </div>
              </div>
              <div class="flex items-center space-x-2">
                <button
                  @click="getAccountInfo(account)"
                  :disabled="loadingInfo[account.id]"
                  class="px-3 py-1 text-sm text-green-600 hover:text-green-800 disabled:opacity-50"
                >
                  {{ loadingInfo[account.id] ? 'Loading...' : 'Details' }}
                </button>
                <button
                  @click="refreshToken(account)"
                  :disabled="refreshingTokens[account.id]"
                  class="px-3 py-1 text-sm text-blue-600 hover:text-blue-800 disabled:opacity-50"
                >
                  {{ refreshingTokens[account.id] ? 'Refreshing...' : 'Refresh' }}
                </button>
                <button
                  @click="disconnectAccount(account)"
                  :disabled="disconnecting[account.id]"
                  class="px-3 py-1 text-sm text-red-600 hover:text-red-800 disabled:opacity-50"
                >
                  {{ disconnecting[account.id] ? 'Disconnecting...' : 'Disconnect' }}
                </button>
              </div>
            </div>
          </div>
          
          <!-- No Connected Accounts -->
          <div v-else class="text-center py-8">
            <p class="text-gray-500">No social media accounts connected yet.</p>
          </div>
        </div>
        
        <!-- Connect New Account Section -->
        <div class="bg-white shadow rounded-lg p-6">
          <h2 class="text-lg font-medium text-gray-900 mb-4">Connect New Account</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <button
              v-for="platform in availablePlatforms"
              :key="platform.name"
              @click="connectAccount(platform.name)"
              :disabled="connecting[platform.name]"
              class="flex items-center justify-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-colors disabled:opacity-50"
            >
              <div class="text-center">
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-2">
                  <span class="text-gray-600 font-medium">
                    {{ platform.name.charAt(0).toUpperCase() }}
                  </span>
                </div>
                <p class="text-sm font-medium text-gray-900">
                  {{ connecting[platform.name] ? 'Connecting...' : `Connect ${platform.label}` }}
                </p>
              </div>
            </button>
          </div>
        </div>
        
        <!-- Error/Success Messages -->
        <div v-if="message" class="mt-4">
          <div 
            :class="[
              'p-4 rounded-lg',
              messageType === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'
            ]"
          >
            {{ message }}
          </div>
        </div>
  </div>
</template>

<script>
import { ref, onMounted, reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';

export default {
  name: 'AccountSettings',
  setup() {
    const route = useRoute();
    const router = useRouter();
    const connectedAccounts = ref([]);
    const loading = ref(true);
    const connecting = reactive({});
    const disconnecting = reactive({});
    const refreshingTokens = reactive({});
    const loadingInfo = reactive({});
    const message = ref('');
    const messageType = ref('');
    
    const availablePlatforms = [
      { name: 'instagram', label: 'Instagram' },
      { name: 'facebook', label: 'Facebook' },
      { name: 'linkedin', label: 'LinkedIn' }
    ];
    
    const loadConnectedAccounts = async () => {
      try {
        loading.value = true;
        const response = await axios.get('/api/social-accounts');
        connectedAccounts.value = response.data.accounts;
      } catch (error) {
        showMessage('Failed to load connected accounts', 'error');
      } finally {
        loading.value = false;
      }
    };
    
    const connectAccount = async (platform) => {
      try {
        connecting[platform] = true;
        const response = await axios.post(`/auth/connect/${platform}`);
        
        if (response.data.success) {
          // Redirect to OAuth URL
          window.location.href = response.data.redirect_url;
        } else {
          showMessage(response.data.message, 'error');
        }
      } catch (error) {
        showMessage(`Failed to connect ${platform} account`, 'error');
      } finally {
        connecting[platform] = false;
      }
    };
    
    const disconnectAccount = async (account) => {
      if (!confirm(`Are you sure you want to disconnect your ${account.platform} account?`)) {
        return;
      }
      
      try {
        disconnecting[account.id] = true;
        const response = await axios.delete(`/api/social-accounts/${account.id}`);
        
        if (response.data.success) {
          showMessage(response.data.message, 'success');
          await loadConnectedAccounts();
        } else {
          showMessage(response.data.message, 'error');
        }
      } catch (error) {
        showMessage('Failed to disconnect account', 'error');
      } finally {
        disconnecting[account.id] = false;
      }
    };
    
    const getAccountInfo = async (account) => {
      try {
        loadingInfo[account.id] = true;
        const response = await axios.get(`/api/social-accounts/${account.id}/info`);
        
        if (response.data.success) {
          console.log('Account Details:', response.data.details);
          
          // Show account details in a more user-friendly way
          let detailsMessage = `Account: ${account.account_name}\n`;
          
          if (response.data.details.profile) {
            const profile = response.data.details.profile;
            detailsMessage += `Profile: ${profile.name || 'Unknown'}\n`;
            if (profile.email) detailsMessage += `Email: ${profile.email}\n`;
          }
          
          if (response.data.details.company_pages && response.data.details.company_pages.length > 0) {
            detailsMessage += `Company Pages: ${response.data.details.company_pages.length} found\n`;
            response.data.details.company_pages.forEach(page => {
              detailsMessage += `- ${page.name}\n`;
            });
          }
          
          if (response.data.details.permissions) {
            const permissions = response.data.details.permissions;
            detailsMessage += `Permissions: ${Object.keys(permissions).filter(k => permissions[k]).join(', ')}`;
          }
          
          alert(detailsMessage);
        } else {
          showMessage(response.data.message, 'error');
        }
      } catch (error) {
        showMessage('Failed to get account information', 'error');
      } finally {
        loadingInfo[account.id] = false;
      }
    };

    const refreshToken = async (account) => {
      try {
        refreshingTokens[account.id] = true;
        const response = await axios.post(`/api/social-accounts/${account.id}/refresh`);
        
        if (response.data.success) {
          showMessage('Token refreshed successfully', 'success');
          await loadConnectedAccounts();
        } else {
          showMessage(response.data.message, 'error');
        }
      } catch (error) {
        showMessage('Failed to refresh token', 'error');
      } finally {
        refreshingTokens[account.id] = false;
      }
    };
    
    const showMessage = (text, type) => {
      message.value = text;
      messageType.value = type;
      setTimeout(() => {
        message.value = '';
        messageType.value = '';
      }, 5000);
    };
    
    const handleRouterMessages = () => {
      // Get router instance
      const route = useRoute();
      
      // Handle success message from router
      if (route.query.success) {
        showMessage(route.query.success, 'success');
        // Clean up query parameters
        router.replace({ name: 'AccountSettings' });
      }
      
      // Handle error message from router
      if (route.query.error) {
        showMessage(route.query.error, 'error');
        // Clean up query parameters
        router.replace({ name: 'AccountSettings' });
      }
    };
    
    onMounted(() => {
      handleRouterMessages();
      loadConnectedAccounts();
    });
    
    return {
      connectedAccounts,
      loading,
      connecting,
      disconnecting,
      refreshingTokens,
      loadingInfo,
      message,
      messageType,
      availablePlatforms,
      connectAccount,
      disconnectAccount,
      refreshToken,
      getAccountInfo
    };
  }
};
</script>