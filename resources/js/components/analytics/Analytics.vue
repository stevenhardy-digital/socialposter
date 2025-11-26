<template>
  <div>
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Analytics</h1>
        
        <!-- Filters -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
          <h2 class="text-lg font-medium text-gray-900 mb-4">Filters</h2>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Platform</label>
              <select v-model="filters.platform" @change="loadAnalytics" class="w-full border border-gray-300 rounded-md px-3 py-2">
                <option value="">All Platforms</option>
                <option value="instagram">Instagram</option>
                <option value="facebook">Facebook</option>
                <option value="linkedin">LinkedIn</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
              <select v-model="filters.status" @change="loadAnalytics" class="w-full border border-gray-300 rounded-md px-3 py-2">
                <option value="">All Statuses</option>
                <option value="published">Published</option>
                <option value="approved">Approved</option>
                <option value="draft">Draft</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
              <input v-model="filters.date_from" @change="loadAnalytics" type="date" class="w-full border border-gray-300 rounded-md px-3 py-2">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
              <input v-model="filters.date_to" @change="loadAnalytics" type="date" class="w-full border border-gray-300 rounded-md px-3 py-2">
            </div>
          </div>
        </div>

        <!-- Summary Cards -->
        <div v-if="summary" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
          <div class="bg-white shadow rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                  <span class="text-white text-sm font-medium">📊</span>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total Posts</p>
                <p class="text-2xl font-semibold text-gray-900">{{ summary.total_posts }}</p>
              </div>
            </div>
          </div>
          
          <div class="bg-white shadow rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                  <span class="text-white text-sm font-medium">❤️</span>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total Likes</p>
                <p class="text-2xl font-semibold text-gray-900">{{ summary.totals.likes.toLocaleString() }}</p>
                <p class="text-xs text-gray-500">Avg: {{ summary.averages.likes }}</p>
              </div>
            </div>
          </div>
          
          <div class="bg-white shadow rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                  <span class="text-white text-sm font-medium">💬</span>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total Comments</p>
                <p class="text-2xl font-semibold text-gray-900">{{ summary.totals.comments.toLocaleString() }}</p>
                <p class="text-xs text-gray-500">Avg: {{ summary.averages.comments }}</p>
              </div>
            </div>
          </div>
          
          <div class="bg-white shadow rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                  <span class="text-white text-sm font-medium">🔄</span>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total Shares</p>
                <p class="text-2xl font-semibold text-gray-900">{{ summary.totals.shares.toLocaleString() }}</p>
                <p class="text-xs text-gray-500">Avg: {{ summary.averages.shares }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Platform Breakdown -->
        <div v-if="summary && summary.platform_breakdown" class="bg-white shadow rounded-lg p-6 mb-6">
          <h2 class="text-lg font-medium text-gray-900 mb-4">Platform Breakdown</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div v-for="(data, platform) in summary.platform_breakdown" :key="platform" class="border border-gray-200 rounded-lg p-4">
              <h3 class="text-sm font-medium text-gray-900 capitalize mb-2">{{ platform }}</h3>
              <div class="space-y-1">
                <p class="text-xs text-gray-600">Posts: {{ data.posts_count }}</p>
                <p class="text-xs text-gray-600">With Metrics: {{ data.posts_with_metrics }}</p>
                <p class="text-xs text-gray-600">Total Likes: {{ data.total_likes.toLocaleString() }}</p>
                <p class="text-xs text-gray-600">Avg Likes: {{ data.avg_likes }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Posts List -->
        <div class="bg-white shadow rounded-lg">
          <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Post Analytics</h2>
          </div>
          
          <div v-if="loading" class="p-6 text-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto"></div>
            <p class="mt-2 text-gray-600">Loading analytics...</p>
          </div>
          
          <div v-else-if="posts.length === 0" class="p-6 text-center text-gray-500">
            No posts found with the current filters.
          </div>
          
          <div v-else class="divide-y divide-gray-200">
            <div v-for="post in posts" :key="post.post_id" class="p-6">
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <div class="flex items-center space-x-2 mb-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                          :class="getPlatformBadgeClass(post.platform)">
                      {{ post.platform }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                          :class="getStatusBadgeClass(post.status)">
                      {{ post.status }}
                    </span>
                    <span v-if="post.is_ai_generated" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                      AI Generated
                    </span>
                  </div>
                  
                  <p class="text-sm text-gray-900 mb-2">{{ truncateContent(post.content) }}</p>
                  
                  <div class="flex items-center space-x-4 text-xs text-gray-500">
                    <span>{{ post.account_name }}</span>
                    <span v-if="post.published_at">{{ formatDate(post.published_at) }}</span>
                  </div>
                </div>
                
                <div v-if="post.metrics" class="ml-6 flex-shrink-0">
                  <div class="grid grid-cols-2 gap-4 text-center">
                    <div>
                      <p class="text-lg font-semibold text-gray-900">{{ post.metrics.likes_count.toLocaleString() }}</p>
                      <p class="text-xs text-gray-500">Likes</p>
                    </div>
                    <div>
                      <p class="text-lg font-semibold text-gray-900">{{ post.metrics.comments_count.toLocaleString() }}</p>
                      <p class="text-xs text-gray-500">Comments</p>
                    </div>
                    <div>
                      <p class="text-lg font-semibold text-gray-900">{{ post.metrics.shares_count.toLocaleString() }}</p>
                      <p class="text-xs text-gray-500">Shares</p>
                    </div>
                    <div>
                      <p class="text-lg font-semibold text-gray-900">{{ post.metrics.reach.toLocaleString() }}</p>
                      <p class="text-xs text-gray-500">Reach</p>
                    </div>
                  </div>
                  <p class="text-xs text-gray-400 mt-2 text-center">
                    Updated: {{ formatDate(post.metrics.collected_at) }}
                  </p>
                </div>
                
                <div v-else class="ml-6 flex-shrink-0 text-center">
                  <p class="text-sm text-gray-500 mb-2">No metrics available</p>
                  <button v-if="post.status === 'published'" 
                          @click="collectMetrics(post.post_id)"
                          :disabled="collectingMetrics[post.post_id]"
                          class="text-xs bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 disabled:opacity-50">
                    {{ collectingMetrics[post.post_id] ? 'Collecting...' : 'Collect Now' }}
                  </button>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Pagination -->
          <div v-if="pagination && pagination.last_page > 1" class="px-6 py-4 border-t border-gray-200">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-700">
                Showing {{ ((pagination.current_page - 1) * pagination.per_page) + 1 }} to 
                {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} of 
                {{ pagination.total }} results
              </div>
              <div class="flex space-x-2">
                <button @click="loadPage(pagination.current_page - 1)" 
                        :disabled="pagination.current_page <= 1"
                        class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50 disabled:opacity-50">
                  Previous
                </button>
                <button @click="loadPage(pagination.current_page + 1)" 
                        :disabled="pagination.current_page >= pagination.last_page"
                        class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50 disabled:opacity-50">
                  Next
                </button>
              </div>
            </div>
          </div>
        </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'Analytics',
  data() {
    return {
      loading: false,
      posts: [],
      summary: null,
      pagination: null,
      collectingMetrics: {},
      filters: {
        platform: '',
        status: 'published',
        date_from: '',
        date_to: '',
        per_page: 20
      }
    };
  },
  mounted() {
    this.loadAnalytics();
    this.loadSummary();
  },
  methods: {
    async loadAnalytics(page = 1) {
      this.loading = true;
      try {
        const params = { ...this.filters, page };
        // Remove empty filters
        Object.keys(params).forEach(key => {
          if (params[key] === '' || params[key] === null) {
            delete params[key];
          }
        });
        
        const response = await axios.get('/api/analytics', { params });
        this.posts = response.data.data;
        this.pagination = response.data.pagination;
      } catch (error) {
        console.error('Failed to load analytics:', error);
        this.$toast?.error('Failed to load analytics data');
      } finally {
        this.loading = false;
      }
    },
    
    async loadSummary() {
      try {
        const params = {};
        if (this.filters.date_from) params.date_from = this.filters.date_from;
        if (this.filters.date_to) params.date_to = this.filters.date_to;
        
        const response = await axios.get('/api/analytics/summary', { params });
        this.summary = response.data.summary;
      } catch (error) {
        console.error('Failed to load summary:', error);
      }
    },
    
    async collectMetrics(postId) {
      this.$set(this.collectingMetrics, postId, true);
      try {
        await axios.post(`/api/analytics/post/${postId}/collect`);
        this.$toast?.success('Metrics collection started');
        // Reload analytics after a delay to show updated metrics
        setTimeout(() => {
          this.loadAnalytics();
        }, 2000);
      } catch (error) {
        console.error('Failed to collect metrics:', error);
        this.$toast?.error('Failed to start metrics collection');
      } finally {
        this.$set(this.collectingMetrics, postId, false);
      }
    },
    
    loadPage(page) {
      if (page >= 1 && page <= this.pagination.last_page) {
        this.loadAnalytics(page);
      }
    },
    
    getPlatformBadgeClass(platform) {
      const classes = {
        instagram: 'bg-pink-100 text-pink-800',
        facebook: 'bg-blue-100 text-blue-800',
        linkedin: 'bg-indigo-100 text-indigo-800'
      };
      return classes[platform] || 'bg-gray-100 text-gray-800';
    },
    
    getStatusBadgeClass(status) {
      const classes = {
        published: 'bg-green-100 text-green-800',
        approved: 'bg-yellow-100 text-yellow-800',
        draft: 'bg-gray-100 text-gray-800',
        rejected: 'bg-red-100 text-red-800'
      };
      return classes[status] || 'bg-gray-100 text-gray-800';
    },
    
    truncateContent(content, maxLength = 100) {
      if (content.length <= maxLength) return content;
      return content.substring(0, maxLength) + '...';
    },
    
    formatDate(dateString) {
      return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    }
  },
  watch: {
    'filters.date_from'() {
      this.loadSummary();
    },
    'filters.date_to'() {
      this.loadSummary();
    }
  }
};
</script>