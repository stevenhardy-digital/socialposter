<template>
  <div>
        <div class="flex justify-between items-center mb-6">
          <h1 class="text-2xl font-bold text-gray-900">Calendar</h1>
          <div class="flex items-center space-x-4">
            <button
              @click="previousMonth"
              class="p-2 rounded-md bg-white border border-gray-300 hover:bg-gray-50"
            >
              ←
            </button>
            <h2 class="text-lg font-medium text-gray-900">
              {{ currentMonthYear }}
            </h2>
            <button
              @click="nextMonth"
              class="p-2 rounded-md bg-white border border-gray-300 hover:bg-gray-50"
            >
              →
            </button>
          </div>
        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden">
          <!-- Calendar Header -->
          <div class="grid grid-cols-7 bg-gray-50 border-b">
            <div
              v-for="day in weekDays"
              :key="day"
              class="px-4 py-3 text-sm font-medium text-gray-700 text-center"
            >
              {{ day }}
            </div>
          </div>

          <!-- Calendar Grid -->
          <div class="grid grid-cols-7">
            <div
              v-for="date in calendarDates"
              :key="date.dateString"
              class="min-h-32 border-r border-b border-gray-200 p-2"
              :class="{
                'bg-gray-50': !date.isCurrentMonth,
                'bg-blue-50': date.isToday
              }"
              @drop="onDrop($event, date.dateString)"
              @dragover.prevent
              @dragenter.prevent
            >
              <!-- Date Number -->
              <div class="text-sm font-medium text-gray-900 mb-2">
                {{ date.day }}
              </div>

              <!-- Posts for this date -->
              <div class="space-y-1">
                <div
                  v-for="post in getPostsForDate(date.dateString)"
                  :key="post.id"
                  :draggable="post.status !== 'published'"
                  @dragstart="onDragStart($event, post)"
                  @click="openPostModal(post)"
                  class="text-xs p-2 rounded cursor-pointer hover:opacity-80"
                  :class="getPostStatusClass(post.status)"
                >
                  <div class="font-medium truncate">
                    {{ post.social_account.platform }}
                  </div>
                  <div class="truncate">
                    {{ post.content.substring(0, 50) }}...
                  </div>
                </div>
              </div>

              <!-- Empty state for dates without posts -->
              <div
                v-if="getPostsForDate(date.dateString).length === 0"
                class="text-xs text-gray-400 italic text-center mt-4"
              >
                No posts scheduled
              </div>
            </div>
          </div>
        </div>

        <!-- Loading state -->
        <div v-if="loading" class="text-center py-4">
          <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        </div>

        <!-- Error state -->
        <div v-if="error" class="bg-red-50 border border-red-200 rounded-md p-4 mt-4">
          <div class="text-red-800">{{ error }}</div>
        </div>

        <!-- Post Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" @click="closeModal">
          <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white" @click.stop>
            <div class="mt-3">
              <!-- Modal Header -->
              <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Post Details</h3>
                <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                  </svg>
                </button>
              </div>

              <!-- Post Info -->
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700">Platform</label>
                  <p class="mt-1 text-sm text-gray-900">{{ selectedPost?.social_account?.platform }}</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700">Account</label>
                  <p class="mt-1 text-sm text-gray-900">{{ selectedPost?.social_account?.account_name }}</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700">Status</label>
                  <select 
                    v-model="editForm.status" 
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  >
                    <option value="draft">Draft</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="published" :disabled="selectedPost?.status !== 'published'">Published</option>
                  </select>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700">Content</label>
                  <textarea 
                    v-model="editForm.content"
                    rows="6"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    :disabled="selectedPost?.status === 'published'"
                  ></textarea>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700">Scheduled Date & Time</label>
                  <input 
                    v-model="editForm.scheduled_at"
                    type="datetime-local"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    :disabled="selectedPost?.status === 'published'"
                  />
                </div>

                <div v-if="selectedPost?.last_error" class="bg-red-50 border border-red-200 rounded-md p-3">
                  <div class="text-sm text-red-800">
                    <strong>Error:</strong> {{ selectedPost.last_error }}
                  </div>
                </div>
              </div>

              <!-- Modal Actions -->
              <div class="flex justify-between mt-6">
                <button
                  @click="deletePost"
                  :disabled="modalLoading"
                  class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:opacity-50"
                >
                  Delete Post
                </button>
                
                <div class="flex space-x-3">
                  <button
                    @click="closeModal"
                    class="px-4 py-2 bg-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500"
                  >
                    Cancel
                  </button>
                  <button
                    @click="savePost"
                    :disabled="modalLoading || selectedPost?.status === 'published'"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50"
                  >
                    {{ modalLoading ? 'Saving...' : 'Save Changes' }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';

export default {
  name: 'Calendar',
  setup() {
    const currentDate = ref(new Date());
    const posts = ref({});
    const loading = ref(false);
    const error = ref(null);
    const draggedPost = ref(null);
    const showModal = ref(false);
    const selectedPost = ref(null);
    const modalLoading = ref(false);
    const editForm = ref({
      status: '',
      content: '',
      scheduled_at: ''
    });

    const weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    const currentMonthYear = computed(() => {
      return currentDate.value.toLocaleDateString('en-US', {
        month: 'long',
        year: 'numeric'
      });
    });

    const calendarDates = computed(() => {
      const year = currentDate.value.getFullYear();
      const month = currentDate.value.getMonth();
      
      // First day of the month
      const firstDay = new Date(year, month, 1);
      // Last day of the month
      const lastDay = new Date(year, month + 1, 0);
      
      // Start from the first Sunday of the calendar view
      const startDate = new Date(firstDay);
      startDate.setDate(startDate.getDate() - startDate.getDay());
      
      // End at the last Saturday of the calendar view
      const endDate = new Date(lastDay);
      endDate.setDate(endDate.getDate() + (6 - endDate.getDay()));
      
      const dates = [];
      const current = new Date(startDate);
      
      while (current <= endDate) {
        const today = new Date();
        dates.push({
          day: current.getDate(),
          dateString: current.toISOString().split('T')[0],
          isCurrentMonth: current.getMonth() === month,
          isToday: current.toDateString() === today.toDateString()
        });
        current.setDate(current.getDate() + 1);
      }
      
      return dates;
    });

    const fetchCalendarPosts = async () => {
      loading.value = true;
      error.value = null;
      
      try {
        const year = currentDate.value.getFullYear();
        const month = currentDate.value.getMonth() + 1; // JavaScript months are 0-indexed
        
        const response = await axios.get('/api/posts/calendar', {
          params: { year, month }
        });
        
        posts.value = response.data;
      } catch (err) {
        error.value = 'Failed to load calendar posts';
        console.error('Error fetching calendar posts:', err);
      } finally {
        loading.value = false;
      }
    };

    const getPostsForDate = (dateString) => {
      return posts.value[dateString] || [];
    };

    const getPostStatusClass = (status) => {
      const classes = {
        draft: 'bg-yellow-100 text-yellow-800 border-yellow-200',
        approved: 'bg-green-100 text-green-800 border-green-200',
        published: 'bg-blue-100 text-blue-800 border-blue-200',
        rejected: 'bg-red-100 text-red-800 border-red-200'
      };
      return classes[status] || 'bg-gray-100 text-gray-800 border-gray-200';
    };

    const previousMonth = () => {
      const newDate = new Date(currentDate.value);
      newDate.setMonth(newDate.getMonth() - 1);
      currentDate.value = newDate;
    };

    const nextMonth = () => {
      const newDate = new Date(currentDate.value);
      newDate.setMonth(newDate.getMonth() + 1);
      currentDate.value = newDate;
    };

    const onDragStart = (event, post) => {
      draggedPost.value = post;
      event.dataTransfer.effectAllowed = 'move';
    };

    const onDrop = async (event, targetDate) => {
      event.preventDefault();
      
      if (!draggedPost.value || draggedPost.value.status === 'published') {
        return;
      }

      try {
        // Create new scheduled datetime with the target date but keep original time
        const originalDate = new Date(draggedPost.value.scheduled_at);
        const newDate = new Date(targetDate);
        newDate.setHours(originalDate.getHours(), originalDate.getMinutes(), originalDate.getSeconds());

        await axios.put(`/api/posts/${draggedPost.value.id}/schedule`, {
          scheduled_at: newDate.toISOString()
        });

        // Refresh calendar data
        await fetchCalendarPosts();
      } catch (err) {
        error.value = 'Failed to update post schedule';
        console.error('Error updating post schedule:', err);
      } finally {
        draggedPost.value = null;
      }
    };

    const openPostModal = (post) => {
      selectedPost.value = post;
      editForm.value = {
        status: post.status,
        content: post.content,
        scheduled_at: formatDateTimeLocal(post.scheduled_at)
      };
      showModal.value = true;
    };

    const closeModal = () => {
      showModal.value = false;
      selectedPost.value = null;
      editForm.value = {
        status: '',
        content: '',
        scheduled_at: ''
      };
    };

    const formatDateTimeLocal = (dateString) => {
      const date = new Date(dateString);
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      const hours = String(date.getHours()).padStart(2, '0');
      const minutes = String(date.getMinutes()).padStart(2, '0');
      return `${year}-${month}-${day}T${hours}:${minutes}`;
    };

    const savePost = async () => {
      modalLoading.value = true;
      
      try {
        await axios.put(`/api/posts/${selectedPost.value.id}`, {
          content: editForm.value.content,
          status: editForm.value.status,
          scheduled_at: new Date(editForm.value.scheduled_at).toISOString()
        });

        await fetchCalendarPosts();
        closeModal();
      } catch (err) {
        error.value = 'Failed to update post';
        console.error('Error updating post:', err);
      } finally {
        modalLoading.value = false;
      }
    };

    const deletePost = async () => {
      if (!confirm('Are you sure you want to delete this post?')) {
        return;
      }

      modalLoading.value = true;
      
      try {
        await axios.delete(`/api/posts/${selectedPost.value.id}`);
        await fetchCalendarPosts();
        closeModal();
      } catch (err) {
        error.value = 'Failed to delete post';
        console.error('Error deleting post:', err);
      } finally {
        modalLoading.value = false;
      }
    };

    // Watch for month changes and refetch data
    watch(currentDate, fetchCalendarPosts);

    onMounted(() => {
      fetchCalendarPosts();
    });

    return {
      currentDate,
      posts,
      loading,
      error,
      weekDays,
      currentMonthYear,
      calendarDates,
      getPostsForDate,
      getPostStatusClass,
      previousMonth,
      nextMonth,
      onDragStart,
      onDrop,
      fetchCalendarPosts,
      showModal,
      selectedPost,
      modalLoading,
      editForm,
      openPostModal,
      closeModal,
      savePost,
      deletePost
    };
  }
};
</script>

<style scoped>
.cursor-move {
  cursor: move;
}

.cursor-move:hover {
  opacity: 0.8;
}

.cursor-pointer {
  cursor: pointer;
}
</style>