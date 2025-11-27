<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Media Manager</h1>
      <p class="mt-1 text-sm text-gray-600">
        Upload and manage your media files with automatic cropping for different social platforms.
      </p>
    </div>

    <div class="bg-white shadow rounded-lg p-6">
      <MediaUpload
        :platforms="['instagram', 'facebook', 'linkedin', 'twitter']"
        @media-selected="onMediaSelected"
        @media-uploaded="onMediaUploaded"
      />
    </div>

    <!-- Success/Error Messages -->
    <div v-if="message" class="mt-4 p-4 rounded-md" :class="messageClass">
      {{ message }}
    </div>
  </div>
</template>

<script>
import { ref, computed } from 'vue'
import MediaUpload from './MediaUpload.vue'

export default {
  name: 'MediaManager',
  components: {
    MediaUpload
  },
  setup() {
    const message = ref('')
    const messageType = ref('success')

    const messageClass = computed(() => {
      return messageType.value === 'success' 
        ? 'bg-green-50 border border-green-200 text-green-800'
        : 'bg-red-50 border border-red-200 text-red-800'
    })

    const onMediaSelected = (media) => {
      message.value = `Selected media: ${media.filename}`
      messageType.value = 'success'
      setTimeout(() => { message.value = '' }, 3000)
    }

    const onMediaUploaded = (uploadedMedia) => {
      message.value = `Successfully uploaded ${uploadedMedia.length} media file(s)`
      messageType.value = 'success'
      setTimeout(() => { message.value = '' }, 3000)
    }

    return {
      message,
      messageClass,
      onMediaSelected,
      onMediaUploaded
    }
  }
}
</script>