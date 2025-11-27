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
          @click="$refs.fileInput.click()"
          class="text-blue-600 hover:text-blue-500 font-medium"
        >
          Upload images
        </button>
        <p class="text-gray-500">or drag and drop</p>
      </div>
      <p class="text-xs text-gray-500 mt-2">PNG, JPG, GIF up to 10MB</p>
    </div>

    <!-- Selected Images -->
    <div v-if="selectedImages.length > 0" class="space-y-4">
      <h3 class="text-lg font-medium text-gray-900">Selected Images</h3>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div
          v-for="(image, index) in selectedImages"
          :key="index"
          class="bg-white border border-gray-200 rounded-lg p-4"
        >
          <!-- Original Image Preview -->
          <div class="mb-4">
            <h4 class="text-sm font-medium text-gray-700 mb-2">Original Image</h4>
            <img
              :src="image.preview"
              :alt="`Original ${index + 1}`"
              class="w-full h-32 object-cover rounded border"
            />
          </div>

          <!-- Platform Crops -->
          <div class="space-y-3">
            <h4 class="text-sm font-medium text-gray-700">Platform Crops</h4>
            
            <div class="grid grid-cols-2 gap-2">
              <div
                v-for="platform in selectedPlatforms"
                :key="platform"
                class="text-center"
              >
                <div class="mb-1">
                  <span class="text-xs font-medium text-gray-600 capitalize">{{ platform }}</span>
                  <span class="text-xs text-gray-500 block">{{ platformSpecs[platform].label }}</span>
                </div>
                
                <div
                  class="relative border border-gray-200 rounded overflow-hidden mx-auto"
                  :style="{ 
                    width: '80px', 
                    height: `${80 / platformSpecs[platform].ratio}px` 
                  }"
                >
                  <canvas
                    :id="`canvas-${index}-${platform}`"
                    class="w-full h-full object-cover"
                  ></canvas>
                </div>
              </div>
            </div>

            <!-- Crop Controls -->
            <div class="mt-4 space-y-2">
              <label class="block text-xs font-medium text-gray-700">Adjust Crop Position</label>
              <div class="grid grid-cols-2 gap-2">
                <div>
                  <label class="block text-xs text-gray-600">X Position</label>
                  <input
                    v-model.number="image.cropSettings.x"
                    type="range"
                    min="0"
                    :max="image.maxX"
                    step="1"
                    @input="updateCrops(index)"
                    class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                  />
                </div>
                <div>
                  <label class="block text-xs text-gray-600">Y Position</label>
                  <input
                    v-model.number="image.cropSettings.y"
                    type="range"
                    min="0"
                    :max="image.maxY"
                    step="1"
                    @input="updateCrops(index)"
                    class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                  />
                </div>
              </div>
            </div>

            <!-- Remove Image -->
            <button
              @click="removeImage(index)"
              class="w-full mt-3 px-3 py-1 text-xs text-red-600 border border-red-200 rounded hover:bg-red-50"
            >
              Remove Image
            </button>
          </div>
        </div>
      </div>

      <!-- Finalize Button -->
      <div class="flex justify-end">
        <button
          @click="finalizeCrops"
          :disabled="uploading || selectedImages.length === 0"
          class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
        >
          {{ uploading ? 'Processing...' : 'Finalize Crops' }}
        </button>
      </div>
    </div>

    <!-- Uploaded Media -->
    <div v-if="uploadedMedia.length > 0" class="space-y-4">
      <h3 class="text-lg font-medium text-gray-900">Uploaded Media</h3>
      
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
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, computed, nextTick, onMounted } from 'vue'
import axios from 'axios'

export default {
  name: 'MediaUpload',
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
    const selectedImages = ref([])
    const uploading = ref(false)
    const uploadedMedia = ref([])

    // Platform specifications for different aspect ratios
    const platformSpecs = {
      instagram: { ratio: 1, label: '1:1 Square' },
      facebook: { ratio: 1.91, label: '1.91:1 Landscape' },
      linkedin: { ratio: 1.91, label: '1.91:1 Landscape' },
      twitter: { ratio: 1.91, label: '1.91:1 Landscape' },
      'instagram-story': { ratio: 0.5625, label: '9:16 Story' },
      'facebook-story': { ratio: 0.5625, label: '9:16 Story' }
    }

    const selectedPlatforms = computed(() => {
      return props.platforms.filter(platform => platformSpecs[platform])
    })

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
      if (selectedImages.value.length + files.length > props.maxFiles) {
        alert(`Maximum ${props.maxFiles} images allowed`)
        return
      }

      for (const file of files) {
        if (file.size > 10 * 1024 * 1024) { // 10MB limit
          alert(`File ${file.name} is too large. Maximum size is 10MB.`)
          continue
        }

        // Upload immediately to get server URL for preview (CSP-safe)
        try {
          uploading.value = true
          const formData = new FormData()
          formData.append('images[0]', file)
          formData.append('crops[0]', JSON.stringify({
            platforms: selectedPlatforms.value,
            crops: {} // Will be updated when user adjusts crops
          }))

          const response = await axios.post('/api/media/upload', formData, {
            headers: {
              'Content-Type': 'multipart/form-data'
            }
          })

          const uploadedMedia = response.data.media[0]
          
          // Create image data with server URL
          const imageData = {
            id: uploadedMedia.id,
            file,
            preview: uploadedMedia.original_url,
            width: uploadedMedia.width,
            height: uploadedMedia.height,
            cropSettings: {
              x: 0,
              y: 0
            },
            maxX: 0,
            maxY: 0,
            crops: {},
            uploaded: true
          }

          // Calculate max crop positions
          const minRatio = Math.min(...selectedPlatforms.value.map(p => platformSpecs[p].ratio))
          const cropWidth = Math.min(uploadedMedia.width, uploadedMedia.height * minRatio)
          const cropHeight = Math.min(uploadedMedia.height, uploadedMedia.width / minRatio)
          
          imageData.maxX = Math.max(0, uploadedMedia.width - cropWidth)
          imageData.maxY = Math.max(0, uploadedMedia.height - cropHeight)

          selectedImages.value.push(imageData)
          
          // Add to uploaded media list
          if (!uploadedMedia.value.find(m => m.id === uploadedMedia.id)) {
            uploadedMedia.value.push(uploadedMedia)
          }
          
          nextTick(() => {
            updateCrops(selectedImages.value.length - 1)
          })

        } catch (error) {
          console.error('Upload failed:', error)
          alert(`Failed to upload ${file.name}. Please try again.`)
        } finally {
          uploading.value = false
        }
      }
    }

    const updateCrops = (imageIndex) => {
      const image = selectedImages.value[imageIndex]
      if (!image) return

      selectedPlatforms.value.forEach(platform => {
        const canvas = document.getElementById(`canvas-${imageIndex}-${platform}`)
        if (!canvas) return

        const ctx = canvas.getContext('2d')
        const spec = platformSpecs[platform]
        
        // Set canvas size
        canvas.width = 160
        canvas.height = 160 / spec.ratio

        // Calculate crop dimensions
        const sourceRatio = image.width / image.height
        let cropWidth, cropHeight

        if (sourceRatio > spec.ratio) {
          // Image is wider than target ratio
          cropHeight = image.height
          cropWidth = cropHeight * spec.ratio
        } else {
          // Image is taller than target ratio
          cropWidth = image.width
          cropHeight = cropWidth / spec.ratio
        }

        // Apply crop position
        const cropX = Math.min(image.cropSettings.x, image.width - cropWidth)
        const cropY = Math.min(image.cropSettings.y, image.height - cropHeight)

        // Create image element for drawing
        const img = new Image()
        img.onload = () => {
          ctx.drawImage(
            img,
            cropX, cropY, cropWidth, cropHeight,
            0, 0, canvas.width, canvas.height
          )
        }
        // Use crossOrigin to handle CORS for server images
        img.crossOrigin = 'anonymous'
        img.src = image.preview

        // Store crop data
        image.crops[platform] = {
          x: cropX,
          y: cropY,
          width: cropWidth,
          height: cropHeight
        }
      })

      // Update crops on server if image is already uploaded
      if (image.uploaded) {
        updateImageCrops(imageIndex)
      }
    }

    const removeImage = (index) => {
      selectedImages.value.splice(index, 1)
    }

    const updateImageCrops = async (imageIndex) => {
      const image = selectedImages.value[imageIndex]
      if (!image || !image.uploaded) return

      try {
        // Update crops on server
        await axios.put(`/api/media/${image.id}/crops`, {
          platforms: selectedPlatforms.value,
          crops: image.crops
        })
      } catch (error) {
        console.error('Failed to update crops:', error)
      }
    }

    const finalizeCrops = async () => {
      // Emit the uploaded media with final crops
      const finalizedMedia = selectedImages.value.filter(img => img.uploaded)
      emit('media-uploaded', finalizedMedia)
      
      // Clear selected images
      selectedImages.value = []
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
      selectedImages,
      uploading,
      uploadedMedia,
      platformSpecs,
      selectedPlatforms,
      handleDrop,
      handleFileSelect,
      updateCrops,
      removeImage,
      finalizeCrops,
      selectMedia,
      deleteMedia
    }
  }
}
</script>