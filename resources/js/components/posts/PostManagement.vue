<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Post Management</h1>
          <button
            @click="showCreateModal = true"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium"
          >
            Create Post
          </button>
        </div>

        <!-- Filters -->
        <div class="bg-white shadow rounded-lg p-4 mb-6">
          <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <select v-model="filters.status" @change="loadPosts" class="w-full border-gray-300 rounded-md">
                <option value="">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="approved">Approved</option>
                <option value="published">Published</option>
                <option value="rejected">Rejected</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Platform</label>
              <select v-model="filters.platform" @change="loadPosts" class="w-full border-gray-300 rounded-md">
                <option value="">All Platforms</option>
                <option value="instagram">Instagram</option>
                <option value="facebook">Facebook</option>
                <option value="linkedin">LinkedIn</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Content Type</label>
              <select v-model="filters.content_type" @change="loadPosts" class="w-full border-gray-300 rounded-md">
                <option value="">All Types</option>
                <option value="ai_generated">AI Generated</option>
                <option value="manual">Manual</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
              <input
                v-model="filters.date_from"
                @change="loadPosts"
                type="date"
                class="w-full border-gray-300 rounded-md"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
              <input
                v-model="filters.date_to"
                @change="loadPosts"
                type="date"
                class="w-full border-gray-300 rounded-md"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
              <input
                v-model="filters.search"
                @input="debounceSearch"
                type="text"
                placeholder="Search content..."
                class="w-full border-gray-300 rounded-md"
              />
            </div>
          </div>
          <div class="mt-4 flex justify-between items-center">
            <div class="text-sm text-gray-600">
              <span v-if="pagination.total > 0">
                {{ pagination.total }} posts found
              </span>
            </div>
            <button
              @click="clearFilters"
              class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm"
            >
              Clear Filters
            </button>
          </div>
        </div>

        <!-- Posts List -->
        <div class="bg-white shadow rounded-lg">
          <div v-if="loading" class="p-6 text-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
            <p class="mt-2 text-gray-600">Loading posts...</p>
          </div>

          <div v-else-if="posts.length === 0" class="p-6 text-center text-gray-500">
            No posts found matching your criteria.
          </div>

          <div v-else class="divide-y divide-gray-200">
            <div
              v-for="post in posts"
              :key="post.id"
              class="p-6 hover:bg-gray-50"
            >
              <div class="flex justify-between items-start">
                <div class="flex-1">
                  <div class="flex items-center space-x-2 mb-2">
                    <span
                      :class="getStatusBadgeClass(post.status)"
                      class="px-2 py-1 text-xs font-medium rounded-full"
                    >
                      {{ post.status.charAt(0).toUpperCase() + post.status.slice(1) }}
                    </span>
                    <span class="text-sm text-gray-500">
                      {{ (post.social_account?.platform || '').charAt(0).toUpperCase() + (post.social_account?.platform || '').slice(1) }}
                    </span>
                    <span v-if="post.is_ai_generated" class="text-xs bg-purple-100 text-purple-800 px-2 py-1 rounded">
                      AI Generated
                    </span>
                  </div>
                  
                  <p class="text-gray-900 mb-2">{{ truncateContent(post.content) }}</p>
                  
                  <div class="text-sm text-gray-500">
                    <span>Scheduled: {{ formatDate(post.scheduled_at) }}</span>
                    <span v-if="post.published_at" class="ml-4">
                      Published: {{ formatDate(post.published_at) }}
                    </span>
                    <span v-if="post.engagement_metrics" class="ml-4">
                      Likes: {{ post.engagement_metrics.likes_count || 0 }} | 
                      Comments: {{ post.engagement_metrics.comments_count || 0 }}
                    </span>
                  </div>
                </div>

                <div class="flex space-x-2 ml-4">
                  <button
                    @click="editPost(post)"
                    class="text-blue-600 hover:text-blue-800 text-sm"
                  >
                    Edit
                  </button>
                  
                  <button
                    v-if="post.status === 'draft'"
                    @click="approvePost(post)"
                    class="text-green-600 hover:text-green-800 text-sm"
                  >
                    Approve
                  </button>
                  
                  <button
                    v-if="post.status === 'draft'"
                    @click="rejectPost(post)"
                    class="text-red-600 hover:text-red-800 text-sm"
                  >
                    Reject
                  </button>
                  
                  <button
                    v-if="post.status !== 'published'"
                    @click="deletePost(post)"
                    class="text-red-600 hover:text-red-800 text-sm"
                  >
                    Delete
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="pagination.last_page > 1" class="px-6 py-4 border-t border-gray-200">
            <div class="flex justify-between items-center">
              <div class="text-sm text-gray-700">
                Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} results
              </div>
              <div class="flex space-x-2">
                <button
                  @click="changePage(pagination.current_page - 1)"
                  :disabled="pagination.current_page === 1"
                  class="px-3 py-1 border rounded text-sm disabled:opacity-50"
                >
                  Previous
                </button>
                <button
                  @click="changePage(pagination.current_page + 1)"
                  :disabled="pagination.current_page === pagination.last_page"
                  class="px-3 py-1 border rounded text-sm disabled:opacity-50"
                >
                  Next
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showCreateModal || editingPost" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
          <div class="bg-white rounded-lg p-6 w-full max-w-2xl mx-4">
            <h3 class="text-lg font-medium text-gray-900 mb-4">
              {{ editingPost ? 'Edit Post' : 'Create New Post' }}
            </h3>
            
            <form @submit.prevent="savePost">
              <div class="space-y-4">
                <div v-if="!editingPost">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Platform</label>
                  <select v-model="postForm.social_account_id" required class="w-full border-gray-300 rounded-md">
                    <option value="">Select Platform</option>
                    <option
                      v-for="account in socialAccounts"
                      :key="account.id"
                      :value="account.id"
                    >
                      {{ (account.platform || '').charAt(0).toUpperCase() + (account.platform || '').slice(1) }} - {{ account.account_name || 'Unknown Account' }}
                    </option>
                  </select>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Content</label>
                  <textarea
                    v-model="postForm.content"
                    required
                    rows="6"
                    class="w-full border-gray-300 rounded-md"
                    placeholder="Enter your post content..."
                  ></textarea>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Scheduled Date</label>
                  <input
                    v-model="postForm.scheduled_at"
                    type="datetime-local"
                    class="w-full border-gray-300 rounded-md"
                  />
                </div>
              </div>

              <div class="flex justify-end space-x-3 mt-6">
                <button
                  type="button"
                  @click="cancelEdit"
                  class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  :disabled="saving"
                  class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
                >
                  {{ saving ? 'Saving...' : (editingPost ? 'Update' : 'Create') }}
                </button>
              </div>
            </form>
          </div>
        </div>
  </div>
</template>

<script>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'

export default {
  name: 'PostManagement',
  setup() {
    const posts = ref([])
    const socialAccounts = ref([])
    const loading = ref(false)
    const saving = ref(false)
    const showCreateModal = ref(false)
    const editingPost = ref(null)
    
    const filters = reactive({
      status: '',
      platform: '',
      search: '',
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

    const postForm = reactive({
      social_account_id: '',
      content: '',
      scheduled_at: ''
    })

    let searchTimeout = null

    const loadPosts = async (page = 1) => {
      loading.value = true
      try {
        const params = {
          page,
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

    const loadSocialAccounts = async () => {
      try {
        const response = await axios.get('/api/social-accounts')
        console.log(response)
        socialAccounts.value = response.data.accounts || response.data
        console.log(socialAccounts.value)
      } catch (error) {
        console.error('Error loading social accounts:', error)
      }
    }

    const debounceSearch = () => {
      clearTimeout(searchTimeout)
      searchTimeout = setTimeout(() => {
        loadPosts()
      }, 500)
    }

    const clearFilters = () => {
      filters.status = ''
      filters.platform = ''
      filters.search = ''
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

    const editPost = (post) => {
      editingPost.value = post
      postForm.content = post.content
      postForm.scheduled_at = post.scheduled_at ? new Date(post.scheduled_at).toISOString().slice(0, 16) : ''
    }

    const cancelEdit = () => {
      showCreateModal.value = false
      editingPost.value = null
      resetForm()
    }

    const resetForm = () => {
      postForm.social_account_id = ''
      postForm.content = ''
      postForm.scheduled_at = ''
    }

    const savePost = async () => {
      saving.value = true
      try {
        if (editingPost.value) {
          await axios.put(`/api/posts/${editingPost.value.id}`, {
            content: postForm.content,
            scheduled_at: postForm.scheduled_at
          })
        } else {
          await axios.post('/api/posts', postForm)
        }
        
        cancelEdit()
        loadPosts()
      } catch (error) {
        console.error('Error saving post:', error)
      } finally {
        saving.value = false
      }
    }

    const approvePost = async (post) => {
      try {
        await axios.post(`/api/posts/${post.id}/approve`)
        loadPosts()
      } catch (error) {
        console.error('Error approving post:', error)
      }
    }

    const rejectPost = async (post) => {
      try {
        await axios.post(`/api/posts/${post.id}/reject`)
        loadPosts()
      } catch (error) {
        console.error('Error rejecting post:', error)
      }
    }

    const deletePost = async (post) => {
      if (confirm('Are you sure you want to delete this post?')) {
        try {
          await axios.delete(`/api/posts/${post.id}`)
          loadPosts()
        } catch (error) {
          console.error('Error deleting post:', error)
        }
      }
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

    const truncateContent = (content) => {
      return content.length > 150 ? content.substring(0, 150) + '...' : content
    }

    const formatDate = (dateString) => {
      if (!dateString) return 'Not set'
      return new Date(dateString).toLocaleString()
    }

    onMounted(() => {
      loadPosts()
      loadSocialAccounts()
    })

    return {
      posts,
      socialAccounts,
      loading,
      saving,
      showCreateModal,
      editingPost,
      filters,
      pagination,
      postForm,
      loadPosts,
      debounceSearch,
      clearFilters,
      changePage,
      editPost,
      cancelEdit,
      savePost,
      approvePost,
      rejectPost,
      deletePost,
      getStatusBadgeClass,
      truncateContent,
      formatDate
    }
  }
}
</script>