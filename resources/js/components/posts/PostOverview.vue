<template>
  <div>
        <div class="flex justify-between items-center mb-6">
          <h1 class="text-2xl font-bold text-gray-900">Post Overview</h1>
          <div class="flex space-x-3">
            <button
              @click="exportPosts"
              class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium"
            >
              Export
            </button>
            <router-link
              to="/posts/manage"
              class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium"
            >
              Manage Posts
            </router-link>
          </div>
        </div>

        <!-- Advanced Search and Filters -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Search & Filter Posts</h3>
          
          <!-- Primary Filters Row -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Search Content</label>
              <input
                v-model="filters.search"
                @input="debounceSearch"
                type="text"
                placeholder="Search in post content..."
                class="w-full border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Platform</label>
              <select v-model="filters.platform" @change="loadPosts" class="w-full border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                <option value="">All Platforms</option>
                <option value="instagram">Instagram</option>
                <option value="facebook">Facebook</option>
                <option value="linkedin">LinkedIn</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <select v-model="filters.status" @change="loadPosts" class="w-full border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                <option value="">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="approved">Approved</option>
                <option value="published">Published</option>
                <option value="rejected">Rejected</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Content Type</label>
              <select v-model="filters.content_type" @change="loadPosts" class="w-full border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                <option value="">All Types</option>
                <option value="ai_generated">AI Generated</option>
                <option value="manual">Manual</option>
              </select>
            </div>
          </div>

          <!-- Date Range Filters Row -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
              <input
                v-model="filters.date_from"
                @change="loadPosts"
                type="date"
                class="w-full border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
              <input
                v-model="filters.date_to"
                @change="loadPosts"
                type="date"
                class="w-full border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
            <div class="flex items-end">
              <button
                @click="clearFilters"
                class="w-full bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium"
              >
                Clear All Filters
              </button>
            </div>
          </div>

          <!-- Results Summary -->
          <div class="flex justify-between items-center pt-4 border-t border-gray-200">
            <div class="text-sm text-gray-600">
              <span v-if="pagination.total > 0">
                Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} posts
              </span>
              <span v-else>No posts found</span>
            </div>
            <div class="flex items-center space-x-4">
              <label class="text-sm text-gray-700">Posts per page:</label>
              <select v-model="perPage" @change="changePerPage" class="border-gray-300 rounded text-sm">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Posts Grid/List -->
        <div class="bg-white shadow rounded-lg">
          <!-- View Toggle -->
          <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
              <div class="flex space-x-2">
                <button
                  @click="viewMode = 'list'"
                  :class="viewMode === 'list' ? 'bg-blue-100 text-blue-700' : 'text-gray-500'"
                  class="px-3 py-1 rounded text-sm font-medium"
                >
                  List View
                </button>
                <button
                  @click="viewMode = 'grid'"
                  :class="viewMode === 'grid' ? 'bg-blue-100 text-blue-700' : 'text-gray-500'"
                  class="px-3 py-1 rounded text-sm font-medium"
                >
                  Grid View
                </button>
              </div>
              <div class="text-sm text-gray-500">
                {{ posts.length }} posts displayed
              </div>
            </div>
          </div>

          <!-- Loading State -->
          <div v-if="loading" class="p-12 text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
            <p class="mt-4 text-gray-600">Loading posts...</p>
          </div>

          <!-- Empty State -->
          <div v-else-if="posts.length === 0" class="p-12 text-center text-gray-500">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No posts found</h3>
            <p class="mt-1 text-sm text-gray-500">Try adjusting your search criteria or filters.</p>
          </div>

          <!-- List View -->
          <div v-else-if="viewMode === 'list'" class="divide-y divide-gray-200">
            <div
              v-for="post in posts"
              :key="post.id"
              class="p-6 hover:bg-gray-50 transition-colors duration-150"
            >
              <div class="flex justify-between items-start">
                <div class="flex-1 min-w-0">
                  <!-- Status and Platform Badges -->
                  <div class="flex items-center space-x-2 mb-3">
                    <span
                      :class="getStatusBadgeClass(post.status)"
                      class="px-2 py-1 text-xs font-medium rounded-full"
                    >
                      {{ post.status.charAt(0).toUpperCase() + post.status.slice(1) }}
                    </span>
                    <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">
                      {{ (post.social_account?.platform || '').charAt(0).toUpperCase() + (post.social_account?.platform || '').slice(1) }}
                    </span>
                    <span v-if="post.is_ai_generated" class="px-2 py-1 text-xs font-medium bg-purple-100 text-purple-800 rounded-full">
                      AI Generated
                    </span>
                  </div>
                  
                  <!-- Post Content -->
                  <p class="text-gray-900 mb-3 leading-relaxed">{{ truncateContent(post.content, 200) }}</p>
                  
                  <!-- Post Metadata -->
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-500">
                    <div>
                      <div class="flex items-center space-x-4">
                        <span>Account: {{ post.social_account.account_name }}</span>
                        <span>Scheduled: {{ formatDate(post.scheduled_at) }}</span>
                      </div>
                      <div v-if="post.published_at" class="mt-1">
                        Published: {{ formatDate(post.published_at) }}
                      </div>
                    </div>
                    <div v-if="post.engagement_metrics" class="text-right">
                      <div class="flex justify-end space-x-4">
                        <span>👍 {{ post.engagement_metrics.likes_count || 0 }}</span>
                        <span>💬 {{ post.engagement_metrics.comments_count || 0 }}</span>
                        <span>🔄 {{ post.engagement_metrics.shares_count || 0 }}</span>
                      </div>
                      <div class="mt-1">
                        Reach: {{ formatNumber(post.engagement_metrics.reach || 0) }}
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col space-y-2 ml-6">
                  <button
                    @click="viewPost(post)"
                    class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                  >
                    View Details
                  </button>
                  <button
                    v-if="post.status !== 'published'"
                    @click="editPost(post)"
                    class="text-gray-600 hover:text-gray-800 text-sm"
                  >
                    Edit
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Grid View -->
          <div v-else class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <div
                v-for="post in posts"
                :key="post.id"
                class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow duration-150"
              >
                <!-- Card Header -->
                <div class="flex items-center justify-between mb-3">
                  <div class="flex items-center space-x-2">
                    <span
                      :class="getStatusBadgeClass(post.status)"
                      class="px-2 py-1 text-xs font-medium rounded-full"
                    >
                      {{ post.status.charAt(0).toUpperCase() + post.status.slice(1) }}
                    </span>
                    <span class="text-xs text-gray-500">
                      {{ post.social_account.platform }}
                    </span>
                  </div>
                  <span v-if="post.is_ai_generated" class="text-xs bg-purple-100 text-purple-800 px-2 py-1 rounded">
                    AI
                  </span>
                </div>

                <!-- Card Content -->
                <p class="text-gray-900 text-sm mb-3 line-clamp-3">{{ truncateContent(post.content, 120) }}</p>

                <!-- Card Footer -->
                <div class="text-xs text-gray-500 space-y-1">
                  <div>{{ formatDate(post.scheduled_at) }}</div>
                  <div v-if="post.engagement_metrics" class="flex justify-between">
                    <span>👍 {{ post.engagement_metrics.likes_count || 0 }}</span>
                    <span>💬 {{ post.engagement_metrics.comments_count || 0 }}</span>
                    <span>🔄 {{ post.engagement_metrics.shares_count || 0 }}</span>
                  </div>
                </div>

                <!-- Card Actions -->
                <div class="mt-3 pt-3 border-t border-gray-100 flex justify-between">
                  <button
                    @click="viewPost(post)"
                    class="text-blue-600 hover:text-blue-800 text-xs font-medium"
                  >
                    View Details
                  </button>
                  <button
                    v-if="post.status !== 'published'"
                    @click="editPost(post)"
                    class="text-gray-600 hover:text-gray-800 text-xs"
                  >
                    Edit
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="pagination.last_page > 1" class="px-6 py-4 border-t border-gray-200 bg-gray-50">
            <div class="flex justify-between items-center">
              <div class="text-sm text-gray-700">
                Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} results
              </div>
              <div class="flex items-center space-x-2">
                <button
                  @click="changePage(1)"
                  :disabled="pagination.current_page === 1"
                  class="px-3 py-1 border rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  First
                </button>
                <button
                  @click="changePage(pagination.current_page - 1)"
                  :disabled="pagination.current_page === 1"
                  class="px-3 py-1 border rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Previous
                </button>
                <span class="px-3 py-1 text-sm">
                  Page {{ pagination.current_page }} of {{ pagination.last_page }}
                </span>
                <button
                  @click="changePage(pagination.current_page + 1)"
                  :disabled="pagination.current_page === pagination.last_page"
                  class="px-3 py-1 border rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Next
                </button>
                <button
                  @click="changePage(pagination.last_page)"
                  :disabled="pagination.current_page === pagination.last_page"
                  class="px-3 py-1 border rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Last
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Post Detail Modal -->
        <div v-if="selectedPost" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
          <div class="bg-white rounded-lg p-6 w-full max-w-4xl mx-4 max-h-screen overflow-y-auto">
            <div class="flex justify-between items-start mb-4">
              <h3 class="text-lg font-medium text-gray-900">Post Details</h3>
              <button
                @click="selectedPost = null"
                class="text-gray-400 hover:text-gray-600"
              >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <!-- Post Content -->
              <div>
                <h4 class="font-medium text-gray-900 mb-2">Content</h4>
                <div class="bg-gray-50 p-4 rounded-lg">
                  <p class="text-gray-900 whitespace-pre-wrap">{{ selectedPost.content }}</p>
                </div>
                
                <div class="mt-4 space-y-2">
                  <div class="flex items-center space-x-2">
                    <span
                      :class="getStatusBadgeClass(selectedPost.status)"
                      class="px-2 py-1 text-xs font-medium rounded-full"
                    >
                      {{ selectedPost.status.charAt(0).toUpperCase() + selectedPost.status.slice(1) }}
                    </span>
                    <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">
                      {{ (selectedPost.social_account?.platform || '').charAt(0).toUpperCase() + (selectedPost.social_account?.platform || '').slice(1) }}
                    </span>
                    <span v-if="selectedPost.is_ai_generated" class="px-2 py-1 text-xs font-medium bg-purple-100 text-purple-800 rounded-full">
                      AI Generated
                    </span>
                  </div>
                </div>
              </div>

              <!-- Post Metadata and Metrics -->
              <div>
                <h4 class="font-medium text-gray-900 mb-2">Details</h4>
                <div class="space-y-3 text-sm">
                  <div>
                    <span class="font-medium text-gray-700">Account:</span>
                    <span class="ml-2">{{ selectedPost.social_account.account_name }}</span>
                  </div>
                  <div>
                    <span class="font-medium text-gray-700">Scheduled:</span>
                    <span class="ml-2">{{ formatDate(selectedPost.scheduled_at) }}</span>
                  </div>
                  <div v-if="selectedPost.published_at">
                    <span class="font-medium text-gray-700">Published:</span>
                    <span class="ml-2">{{ formatDate(selectedPost.published_at) }}</span>
                  </div>
                  <div>
                    <span class="font-medium text-gray-700">Created:</span>
                    <span class="ml-2">{{ formatDate(selectedPost.created_at) }}</span>
                  </div>
                </div>

                <div v-if="selectedPost.engagement_metrics" class="mt-6">
                  <h4 class="font-medium text-gray-900 mb-2">Engagement Metrics</h4>
                  <div class="grid grid-cols-2 gap-4 text-sm">
                    <div class="bg-blue-50 p-3 rounded">
                      <div class="font-medium text-blue-900">Likes</div>
                      <div class="text-2xl font-bold text-blue-600">{{ selectedPost.engagement_metrics.likes_count || 0 }}</div>
                    </div>
                    <div class="bg-green-50 p-3 rounded">
                      <div class="font-medium text-green-900">Comments</div>
                      <div class="text-2xl font-bold text-green-600">{{ selectedPost.engagement_metrics.comments_count || 0 }}</div>
                    </div>
                    <div class="bg-purple-50 p-3 rounded">
                      <div class="font-medium text-purple-900">Shares</div>
                      <div class="text-2xl font-bold text-purple-600">{{ selectedPost.engagement_metrics.shares_count || 0 }}</div>
                    </div>
                    <div class="bg-orange-50 p-3 rounded">
                      <div class="font-medium text-orange-900">Reach</div>
                      <div class="text-2xl font-bold text-orange-600">{{ formatNumber(selectedPost.engagement_metrics.reach || 0) }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
  </div>
</template>

<script>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'

export default {
  name: 'PostOverview',
  setup() {
    const posts = ref([])
    const loading = ref(false)
    const selectedPost = ref(null)
    const viewMode = ref('list')
    const perPage = ref(25)
    
    const filters = reactive({
      search: '',
      platform: '',
      status: '',
      content_type: '',
      date_from: '',
      date_to: ''
    })

    const pagination = reactive({
      current_page: 1,
      last_page: 1,
      from: 0,
      to: 0,
      total: 0
    })

    let searchTimeout = null

    const loadPosts = async (page = 1) => {
      loading.value = true
      try {
        const params = {
          page,
          per_page: perPage.value,
          ...filters
        }
        
        // Convert content_type filter to is_ai_generated parameter
        if (filters.content_type === 'ai_generated') {
          params.is_ai_generated = true
        } else if (filters.content_type === 'manual') {
          params.is_ai_generated = false
        }
        delete params.content_type
        
        const response = await axios.get('/api/posts', { params })
        posts.value = response.data.data
        Object.assign(pagination, {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          from: response.data.from,
          to: response.data.to,
          total: response.data.total
        })
      } catch (error) {
        console.error('Error loading posts:', error)
      } finally {
        loading.value = false
      }
    }

    const debounceSearch = () => {
      clearTimeout(searchTimeout)
      searchTimeout = setTimeout(() => {
        loadPosts()
      }, 500)
    }

    const clearFilters = () => {
      filters.search = ''
      filters.platform = ''
      filters.status = ''
      filters.content_type = ''
      filters.date_from = ''
      filters.date_to = ''
      loadPosts()
    }

    const changePage = (page) => {
      if (page >= 1 && page <= pagination.last_page) {
        loadPosts(page)
      }
    }

    const changePerPage = () => {
      loadPosts(1)
    }

    const viewPost = (post) => {
      selectedPost.value = post
    }

    const editPost = (post) => {
      // Navigate to edit page or open edit modal
      console.log('Edit post:', post.id)
    }

    const exportPosts = () => {
      // Implement export functionality
      console.log('Export posts')
    }

    const getStatusBadgeClass = (status) => {
      const classes = {
        draft: 'bg-yellow-100 text-yellow-800',
        approved: 'bg-green-100 text-green-800',
        published: 'bg-blue-100 text-blue-800',
        rejected: 'bg-red-100 text-red-800'
      }
      return classes[status] || 'bg-gray-100 text-gray-800'
    }

    const truncateContent = (content, length = 150) => {
      return content.length > length ? content.substring(0, length) + '...' : content
    }

    const formatDate = (dateString) => {
      if (!dateString) return 'Not set'
      return new Date(dateString).toLocaleString()
    }

    const formatNumber = (num) => {
      if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M'
      } else if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K'
      }
      return num.toString()
    }

    onMounted(() => {
      loadPosts()
    })

    return {
      posts,
      loading,
      selectedPost,
      viewMode,
      perPage,
      filters,
      pagination,
      loadPosts,
      debounceSearch,
      clearFilters,
      changePage,
      changePerPage,
      viewPost,
      editPost,
      exportPosts,
      getStatusBadgeClass,
      truncateContent,
      formatDate,
      formatNumber
    }
  }
}
</script>

<style scoped>
.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>