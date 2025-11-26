<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="max-w-md w-full space-y-8">
      <div class="text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
        <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
          Processing OAuth Connection
        </h2>
        <p class="mt-2 text-sm text-gray-600">
          {{ statusMessage }}
        </p>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import axios from 'axios';

export default {
  name: 'OAuthCallback',
  setup() {
    const router = useRouter();
    const authStore = useAuthStore();
    const statusMessage = ref('Connecting your account...');

    const handleOAuthCallback = async () => {
      try {
        const urlParams = new URLSearchParams(window.location.search);
        
        // Handle OAuth success
        if (urlParams.has('oauth_success')) {
          const platform = urlParams.get('oauth_success');
          const accountName = urlParams.get('account');
          const token = urlParams.get('token');
          
          statusMessage.value = `Successfully connected ${platform} account!`;
          
          // If we have a token, restore authentication
          if (token) {
            // Update axios authorization header
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
            
            // Store token in localStorage for persistence (use same key as auth store)
            localStorage.setItem('token', token);
            
            // Update auth store
            try {
              // Set the token in the store
              authStore.token = token;
              authStore.isAuthenticated = true;
              
              // Set token expiry (24 hours from now)
              const expiry = new Date();
              expiry.setHours(expiry.getHours() + 24);
              authStore.tokenExpiry = expiry.toISOString();
              localStorage.setItem('token_expiry', authStore.tokenExpiry);
              
              // Fetch user data
              await authStore.fetchUser();
              statusMessage.value = 'Redirecting to your accounts...';
              
              // Redirect to accounts page with success message
              setTimeout(() => {
                router.push({
                  name: 'AccountSettings',
                  query: {
                    success: `${platform.charAt(0).toUpperCase() + platform.slice(1)} account "${accountName}" connected successfully!`
                  }
                });
              }, 1500);
            } catch (error) {
              console.error('Failed to fetch user after OAuth:', error);
              statusMessage.value = 'Authentication failed. Redirecting to login...';
              setTimeout(() => {
                router.push('/login');
              }, 2000);
            }
          } else {
            // No token provided, redirect to login
            statusMessage.value = 'Authentication token missing. Redirecting to login...';
            setTimeout(() => {
              router.push('/login');
            }, 2000);
          }
        }
        
        // Handle OAuth error
        else if (urlParams.has('oauth_error')) {
          const platform = urlParams.get('oauth_error');
          const errorMessage = urlParams.get('message');
          
          statusMessage.value = `Failed to connect ${platform}: ${errorMessage}`;
          
          // If user is already authenticated, go to accounts page with error
          if (authStore.isAuthenticated) {
            setTimeout(() => {
              router.push({
                name: 'AccountSettings',
                query: {
                  error: `Failed to connect ${platform}: ${errorMessage}`
                }
              });
            }, 3000);
          } else {
            // Not authenticated, go to login
            setTimeout(() => {
              router.push('/login');
            }, 3000);
          }
        }
        
        // No OAuth parameters, redirect appropriately
        else {
          statusMessage.value = 'No OAuth data found. Redirecting...';
          setTimeout(() => {
            if (authStore.isAuthenticated) {
              router.push('/accounts');
            } else {
              router.push('/login');
            }
          }, 2000);
        }
        
      } catch (error) {
        console.error('OAuth callback error:', error);
        statusMessage.value = 'An error occurred processing your request. Redirecting to login...';
        setTimeout(() => {
          router.push('/login');
        }, 3000);
      }
    };

    onMounted(() => {
      handleOAuthCallback();
    });

    return {
      statusMessage
    };
  }
};
</script>