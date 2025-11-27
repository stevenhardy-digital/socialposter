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
                  class="text-xs p-2 rounded cursor-move"
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
      fetchCalendarPosts
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
</style>