<template>
  <div class="space-y-4">
    <!-- Upload Area -->
    <div
      @drop="handleDrop"
      @dragover.prevent
      @dragenter.prevent
      class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition-colors"
      :class="{ 'border-blue-500 bg-blue-50': isDragging }"
    >
      <input
        ref="fileInput"
        type="file"
        accept="image/*"
        @change="handleFileSelect"
        class="hidden"
        multiple
      />
      
      <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
      
      <div class="mt-4">
        <button
          type="button"
          @click="fileInput.click()"
          class="text-blue-600 hover:text-blue-500 font-medium"
        >
          Upload images
        </button>
        <p class="text-gray-500">or drag and drop</p>
      </div>
      <p class="text-xs text-gray-500 mt-2">PNG, JPG, GIF up to 10MB</p>
      <p class="text-xs text-gray-500">Images will be automatically cropped for {{ platforms.join(', ') }}</p>
    </div>

    <!-- Upload Progress -->
    <div v-if="uploading" class="bg-blue-50 border border-blue-200 rounded-md p-4">
      <div class="flex items-center">
        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600 mr-3"></div>
        <span class="text-sm text-blue-800">Uploading and processing images...</span>
      </div>
    </div>

    <!-- Uploaded Media -->
    <div v-if="uploadedMedia.length > 0" class="space-y-4">
      <h3 class="text-lg font-medium text-gray-900">Media Library</h3>
      
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div
          v-for="media in uploadedMedia"
          :key="media.id"
          class="relative group"
        >
          <img
            :src="media.thumbnail_url"
            :alt="media.filename"
            class="w-full h-24 object-cover rounded border cursor-pointer"
            @click="selectMedia(media)"
          />
          
          <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-opacity rounded flex items-center justify-center">
            <button
              @click="selectMedia(media)"
              class="opacity-0 group-hover:opacity-100 px-2 py-1 bg-white text-xs rounded"
            >
              Select
            </button>
          </div>
          
          <div class="absolute top-1 right-1">
            <button
              @click="deleteMedia(media.id)"
              class="w-5 h-5 bg-red-500 text-white rounded-full text-xs hover:bg-red-600"
            >
              ×
            </button>
          </div>

          <!-- Platform indicators -->
          <div class="absolute bottom-1 left-1 flex space-x-1">
            <div
              v-for="platform in platforms"
              :key="platform"
              :class="hasPlatformCrop(media, platform) ? 'bg-green-500' : 'bg-gray-400'"
              class="w-2 h-2 rounded-full"
              :title="`${platform} crop ${hasPlatformCrop(media, platform) ? 'available' : 'not available'}`"
            ></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import axios from 'axios'

export default {
  name: 'SimpleMediaUpload',
  props: {
    platforms: {
      type: Array,
      default: () => ['instagram', 'facebook', 'linkedin', 'twitter']
    },
    maxFiles: {
      type: Number,
      default: 5
    }
  },
  emits: ['media-selected', 'media-uploaded'],
  setup(props, { emit }) {
    const isDragging = ref(false)
    const uploading = ref(false)
    const uploadedMedia = ref([])
    const fileInput = ref(null)

    const handleDrop = (e) => {
      e.preventDefault()
      isDragging.value = false
      
      const files = Array.from(e.dataTransfer.files).filter(file => 
        file.type.startsWith('image/')
      )
      
      processFiles(files)
    }

    const handleFileSelect = (e) => {
      const files = Array.from(e.target.files)
      processFiles(files)
    }

    const processFiles = async (files) => {
      if (files.length > props.maxFiles) {
        alert(`Maximum ${props.maxFiles} images allowed`)
        return
      }

      const validFiles = files.filter(file => {
        if (file.size > 10 * 1024 * 1024) {
          alert(`File ${file.name} is too large. Maximum size is 10MB.`)
          return false
        }
        return true
      })

      if (validFiles.length === 0) return

      uploading.value = true

      try {
        const formData = new FormData()
        
        validFiles.forEach((file, index) => {
          formData.append(`images[${index}]`, file)
          formData.append(`crops[${index}]`, JSON.stringify({
            platforms: props.platforms,
            crops: {} // Server will generate default crops
          }))
        })

        const response = await axios.post('/api/media/upload', formData, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        })

        const newMedia = response.data.media
        uploadedMedia.value.push(...newMedia)
        
        emit('media-uploaded', newMedia)

        // Clear file input
        if (fileInput.value) {
          fileInput.value.value = ''
        }

      } catch (error) {
        console.error('Upload failed:', error)
        alert('Failed to upload images. Please try again.')
      } finally {
        uploading.value = false
      }
    }

    const selectMedia = (media) => {
      emit('media-selected', media)
    }

    const deleteMedia = async (mediaId) => {
      if (!confirm('Are you sure you want to delete this media?')) return

      try {
        await axios.delete(`/api/media/${mediaId}`)
        uploadedMedia.value = uploadedMedia.value.filter(m => m.id !== mediaId)
      } catch (error) {
        console.error('Delete failed:', error)
        alert('Failed to delete media.')
      }
    }

    const hasPlatformCrop = (media, platform) => {
      if (!media.platform_crops) return false
      
      let crops
      if (typeof media.platform_crops === 'string') {
        try {
          crops = JSON.parse(media.platform_crops)
        } catch (e) {
          return false
        }
      } else {
        crops = media.platform_crops
      }
      
      return crops && crops[platform]
    }

    const loadUploadedMedia = async () => {
      try {
        const response = await axios.get('/api/media')
        uploadedMedia.value = response.data.media || []
      } catch (error) {
        console.error('Failed to load media:', error)
      }
    }

    onMounted(() => {
      loadUploadedMedia()
    })

    return {
      isDragging,
      uploading,
      uploadedMedia,
      fileInput,
      handleDrop,
      handleFileSelect,
      selectMedia,
      deleteMedia,
      hasPlatformCrop
    }
  }
}
</script>