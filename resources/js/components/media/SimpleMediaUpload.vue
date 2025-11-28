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
          class="relative group aspect-square"
        >
          <div class="w-full h-full bg-gray-100 rounded border overflow-hidden relative">
            <!-- Loading placeholder (behind image) -->
            <div class="absolute inset-0 bg-gray-200 animate-pulse flex items-center justify-center">
              <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
              </svg>
            </div>
            
            <!-- Image (on top of placeholder) -->
            <img
              :src="getImageUrl(media)"
              :alt="media.filename"
              class="relative w-full h-full object-cover cursor-pointer transition-opacity duration-200 z-10"
              :class="{ 
                'opacity-0': media.imageError,
                'loaded': media.imageLoaded
              }"
              @click="selectMedia(media)"
              @error="handleImageError($event, media)"
              @load="handleImageLoad($event, media)"
              loading="lazy"
            />
          </div>
          
          
                                                                                                                                                                    <!-- Hover overlay with buttons -->
          <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-opacity rounded flex items-center justify-center z-20">
            <div class="opacity-0 group-hover:opacity-100 flex space-x-2 transition-opacity duration-200">
              <button
                @click="openPreviewModal(media)"
                class="px-3 py-1 bg-white text-xs rounded shadow-md font-medium hover:bg-gray-100"
              >
                Preview
              </button>
              <button
                @click="selectMedia(media)"
                class="px-3 py-1 bg-blue-600 text-white text-xs rounded shadow-md font-medium hover:bg-blue-700"
              >
                Select
              </button>
            </div>
          </div>
          
          <!-- Delete button -->
          <div class="absolute top-2 right-2 z-30">
            <button
              @click="deleteMedia(media.id)"
              class="w-6 h-6 bg-red-500 text-white rounded-full text-xs hover:bg-red-600 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center shadow-md"
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

    <!-- Media Preview Modal -->
    <div v-if="showPreviewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" @click="closePreviewModal">
      <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-4/5 lg:w-3/4 xl:w-2/3 shadow-lg rounded-md bg-white" @click.stop>
        <div class="mt-3">
          <!-- Modal Header -->
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">Media Preview - {{ selectedMediaForPreview?.filename }}</h3>
            <button @click="closePreviewModal" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>

          <!-- Media Info -->
          <div class="mb-6 bg-gray-50 p-4 rounded-lg">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
              <div>
                <span class="font-medium text-gray-700">File Size:</span>
                <span class="text-gray-600 ml-1">{{ selectedMediaForPreview?.formatted_file_size }}</span>
              </div>
              <div>
                <span class="font-medium text-gray-700">Dimensions:</span>
                <span class="text-gray-600 ml-1">{{ selectedMediaForPreview?.width }}×{{ selectedMediaForPreview?.height }}</span>
              </div>
              <div>
                <span class="font-medium text-gray-700">Type:</span>
                <span class="text-gray-600 ml-1">{{ selectedMediaForPreview?.mime_type }}</span>
              </div>
              <div>
                <span class="font-medium text-gray-700">Uploaded:</span>
                <span class="text-gray-600 ml-1">{{ formatDate(selectedMediaForPreview?.created_at) }}</span>
              </div>
            </div>
          </div>

          <!-- Original Image -->
          <div class="mb-6">
            <h4 class="text-md font-medium text-gray-900 mb-3">Original Image</h4>
            <div class="bg-gray-100 rounded-lg p-4">
              <img
                :src="selectedMediaForPreview?.original_url"
                :alt="selectedMediaForPreview?.filename"
                class="max-w-full h-auto mx-auto rounded border shadow-sm"
                style="max-height: 400px;"
              />
              <div class="mt-2 text-center">
                <a
                  :href="selectedMediaForPreview?.original_url"
                  target="_blank"
                  class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700"
                >
                  <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                  </svg>
                  View Full Size
                </a>
              </div>
            </div>
          </div>

          <!-- Platform Crops -->
          <div v-if="getPlatformCrops(selectedMediaForPreview).length > 0">
            <h4 class="text-md font-medium text-gray-900 mb-3">Platform-Specific Crops</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div
                v-for="crop in getPlatformCrops(selectedMediaForPreview)"
                :key="crop.platform"
                class="bg-gray-50 rounded-lg p-4"
              >
                <h5 class="font-medium text-gray-800 mb-2 capitalize">{{ crop.platform }}</h5>
                
                <!-- Image container with correct aspect ratio -->
                <div 
                  class="mb-3 bg-white rounded border platform-crop-container relative" 
                  :style="getCropContainerStyle(crop.platform)"
                >
                  <!-- Loading placeholder -->
                  <div class="absolute inset-0 bg-gray-200 animate-pulse flex items-center justify-center">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                  </div>
                  
                  <!-- Image -->
                  <img
                    :src="getCropImageUrl(crop)"
                    :alt="`${selectedMediaForPreview?.filename} - ${crop.platform}`"
                    class="relative w-full h-full object-cover z-10 transition-opacity duration-200"
                    @error="handleCropImageError($event, crop)"
                    @load="handleCropImageLoad($event, crop)"
                    loading="lazy"
                  />
                </div>
                
                <div class="text-xs text-gray-600 space-y-1 mb-3">
                  <div>Size: {{ crop.width }}×{{ crop.height }}</div>
                  <div>Aspect: {{ getPlatformAspectRatio(crop.platform) }}</div>
                </div>
                
                <div class="flex space-x-2">
                  <button
                    @click="openCropEditor(selectedMediaForPreview, crop.platform)"
                    class="flex-1 inline-flex items-center justify-center px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700"
                  >
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit Crop
                  </button>
                  <a
                    :href="crop.url"
                    target="_blank"
                    class="flex-1 inline-flex items-center justify-center px-2 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700"
                  >
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                    </svg>
                    Download
                  </a>
                </div>
              </div>
            </div>
          </div>

          <!-- Thumbnail -->
          <div v-if="selectedMediaForPreview?.thumbnail_url" class="mt-6">
            <h4 class="text-md font-medium text-gray-900 mb-3">Thumbnail</h4>
            <div class="bg-gray-50 rounded-lg p-4 inline-block">
              <img
                :src="selectedMediaForPreview?.thumbnail_url"
                :alt="`${selectedMediaForPreview?.filename} thumbnail`"
                class="w-24 h-24 object-cover rounded border"
              />
              <div class="mt-2 text-xs text-gray-600 text-center">
                200×200px
              </div>
            </div>
          </div>

          <!-- Modal Actions -->
          <div class="flex justify-between items-center mt-6 pt-4 border-t border-gray-200">
            <button
              @click="selectMedia(selectedMediaForPreview)"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
            >
              Select This Media
            </button>
            
            <div class="flex space-x-3">
              <button
                @click="closePreviewModal"
                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400"
              >
                Close
              </button>
              <button
                @click="deleteMedia(selectedMediaForPreview.id)"
                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
              >
                Delete Media
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Crop Editor Modal -->
    <div v-if="showCropModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" @click="closeCropModal">
      <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-4/5 lg:w-3/4 xl:w-2/3 shadow-lg rounded-md bg-white" @click.stop>
        <div class="mt-3">
          <!-- Modal Header -->
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">
              Crop Editor - {{ cropData.platform }} ({{ getPlatformAspectRatio(cropData.platform) }})
            </h3>
            <button @click="closeCropModal" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>

          <!-- Crop Interface -->
          <div class="mb-6">
            <div class="bg-gray-100 p-4 rounded-lg">
              <div class="relative inline-block" ref="cropContainer">
                <img
                  ref="cropImage"
                  :src="cropData.media?.original_url"
                  :alt="cropData.media?.filename"
                  class="max-w-full h-auto"
                  style="max-height: 500px;"
                  @load="initializeCropArea"
                />
                
                <!-- Crop Overlay -->
                <div
                  v-if="cropArea.initialized"
                  class="absolute border-2 border-blue-500 bg-blue-500 bg-opacity-20 cursor-move"
                  :style="{
                    left: cropArea.x + 'px',
                    top: cropArea.y + 'px',
                    width: cropArea.width + 'px',
                    height: cropArea.height + 'px'
                  }"
                  @mousedown="startDrag"
                >
                  <!-- Resize handles -->
                  <div class="absolute -top-1 -left-1 w-3 h-3 bg-blue-500 cursor-nw-resize" @mousedown.stop="startResize('nw')"></div>
                  <div class="absolute -top-1 -right-1 w-3 h-3 bg-blue-500 cursor-ne-resize" @mousedown.stop="startResize('ne')"></div>
                  <div class="absolute -bottom-1 -left-1 w-3 h-3 bg-blue-500 cursor-sw-resize" @mousedown.stop="startResize('sw')"></div>
                  <div class="absolute -bottom-1 -right-1 w-3 h-3 bg-blue-500 cursor-se-resize" @mousedown.stop="startResize('se')"></div>
                  
                  <!-- Edge handles -->
                  <div class="absolute -top-1 left-1/2 transform -translate-x-1/2 w-3 h-3 bg-blue-500 cursor-n-resize" @mousedown.stop="startResize('n')"></div>
                  <div class="absolute -bottom-1 left-1/2 transform -translate-x-1/2 w-3 h-3 bg-blue-500 cursor-s-resize" @mousedown.stop="startResize('s')"></div>
                  <div class="absolute -left-1 top-1/2 transform -translate-y-1/2 w-3 h-3 bg-blue-500 cursor-w-resize" @mousedown.stop="startResize('w')"></div>
                  <div class="absolute -right-1 top-1/2 transform -translate-y-1/2 w-3 h-3 bg-blue-500 cursor-e-resize" @mousedown.stop="startResize('e')"></div>
                </div>
              </div>
            </div>

            <!-- Crop Controls -->
            <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">X Position</label>
                <input
                  v-model.number="cropArea.x"
                  type="range"
                  :min="0"
                  :max="maxCropX"
                  @input="updateCropArea"
                  class="w-full"
                />
                <span class="text-xs text-gray-500">{{ cropArea.x }}px</span>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Y Position</label>
                <input
                  v-model.number="cropArea.y"
                  type="range"
                  :min="0"
                  :max="maxCropY"
                  @input="updateCropArea"
                  class="w-full"
                />
                <span class="text-xs text-gray-500">{{ cropArea.y }}px</span>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Width</label>
                <input
                  v-model.number="cropArea.width"
                  type="range"
                  :min="minCropSize"
                  :max="maxCropWidth"
                  @input="updateCropArea"
                  class="w-full"
                />
                <span class="text-xs text-gray-500">{{ cropArea.width }}px</span>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Height</label>
                <span class="text-xs text-gray-500">{{ cropArea.height }}px (auto)</span>
              </div>
            </div>

            <!-- Preset Buttons -->
            <div class="mt-4 flex flex-wrap gap-2">
              <button
                @click="setCropPreset('center')"
                class="px-3 py-1 bg-gray-600 text-white text-sm rounded hover:bg-gray-700"
              >
                Center Crop
              </button>
              <button
                @click="setCropPreset('top')"
                class="px-3 py-1 bg-gray-600 text-white text-sm rounded hover:bg-gray-700"
              >
                Top Focus
              </button>
              <button
                @click="setCropPreset('bottom')"
                class="px-3 py-1 bg-gray-600 text-white text-sm rounded hover:bg-gray-700"
              >
                Bottom Focus
              </button>
              <button
                @click="setCropPreset('left')"
                class="px-3 py-1 bg-gray-600 text-white text-sm rounded hover:bg-gray-700"
              >
                Left Focus
              </button>
              <button
                @click="setCropPreset('right')"
                class="px-3 py-1 bg-gray-600 text-white text-sm rounded hover:bg-gray-700"
              >
                Right Focus
              </button>
            </div>
          </div>

          <!-- Preview -->
          <div class="mb-6">
            <h4 class="text-md font-medium text-gray-900 mb-2">Preview</h4>
            <div class="bg-gray-50 p-4 rounded-lg inline-block">
              <canvas
                ref="previewCanvas"
                class="border rounded"
                :style="{
                  width: getPreviewSize().width + 'px',
                  height: getPreviewSize().height + 'px'
                }"
              ></canvas>
              <div class="mt-2 text-xs text-gray-600 text-center">
                {{ getPreviewSize().width }}×{{ getPreviewSize().height }}px preview
              </div>
            </div>
          </div>

          <!-- Modal Actions -->
          <div class="flex justify-between items-center pt-4 border-t border-gray-200">
            <div class="text-sm text-gray-600">
              Platform: {{ cropData.platform }} | Aspect Ratio: {{ getPlatformAspectRatio(cropData.platform) }}
            </div>
            
            <div class="flex space-x-3">
              <button
                @click="closeCropModal"
                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400"
              >
                Cancel
              </button>
              <button
                @click="saveCrop"
                :disabled="savingCrop"
                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
              >
                {{ savingCrop ? 'Saving...' : 'Save Crop' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
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
    const showPreviewModal = ref(false)
    const selectedMediaForPreview = ref(null)
    const showCropModal = ref(false)
    const cropContainer = ref(null)
    const cropImage = ref(null)
    const previewCanvas = ref(null)
    const savingCrop = ref(false)
    
    const cropData = ref({
      media: null,
      platform: null
    })
    
    const cropArea = ref({
      x: 0,
      y: 0,
      width: 0,
      height: 0,
      initialized: false
    })
    
    const dragState = ref({
      isDragging: false,
      isResizing: false,
      resizeHandle: null,
      startX: 0,
      startY: 0,
      startCropX: 0,
      startCropY: 0,
      startCropWidth: 0,
      startCropHeight: 0
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
        
        // Initialize loading states for new media
        newMedia.forEach(item => {
          item.imageLoaded = false
          item.imageError = false
        })
        
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

    const getImageUrl = (media) => {
      // Try thumbnail first, then original, with cache busting
      const timestamp = new Date(media.updated_at).getTime()
      let url = null
      
      if (media.thumbnail_url) {
        url = `${media.thumbnail_url}?t=${timestamp}`
      } else if (media.original_url) {
        url = `${media.original_url}?t=${timestamp}`
      }
      
      // Debug logging - only log if there's an issue
      if (!url) {
        console.error('No URL available for media:', {
          id: media.id,
          filename: media.filename,
          thumbnail_url: media.thumbnail_url,
          original_url: media.original_url
        })
      }
      
      return url
    }

    const handleImageError = (event, media) => {
      console.error('Image failed to load:', event.target.src, media)
      
      // Mark as error to hide the image
      media.imageError = true
      
      // Try fallback to original URL if thumbnail failed
      if (event.target.src.includes('thumbnails') && media.original_url) {
        media.imageError = false
        event.target.src = media.original_url
      }
    }

    const handleImageLoad = (event, media) => {
      // Mark as loaded and show the image
      media.imageLoaded = true
      media.imageError = false
      event.target.style.opacity = '1'
    }

    const getCropImageUrl = (crop) => {
      // Add cache busting and debug logging for crop images
      const timestamp = Date.now()
      const url = `${crop.url}?t=${timestamp}`
      
      console.log('Getting crop image URL:', {
        platform: crop.platform,
        original_url: crop.url,
        final_url: url
      })
      
      return url
    }

    const handleCropImageError = (event, crop) => {
      console.error('Crop image failed to load:', event.target.src, crop)
      
      // Try fallback to original URL without cache busting
      if (event.target.src.includes('?t=')) {
        event.target.src = crop.url
      } else {
        // Hide the image and show placeholder
        event.target.style.opacity = '0'
      }
    }

    const handleCropImageLoad = (event, crop) => {
      // Show the image when loaded
      event.target.style.opacity = '1'
      console.log('Crop image loaded successfully:', crop.platform, event.target.src)
    }

    const openPreviewModal = (media) => {
      selectedMediaForPreview.value = media
      showPreviewModal.value = true
    }

    const closePreviewModal = () => {
      showPreviewModal.value = false
      selectedMediaForPreview.value = null
    }

    const getPlatformCrops = (media) => {
      if (!media || !media.platform_crops) return []
      
      let crops
      if (typeof media.platform_crops === 'string') {
        try {
          crops = JSON.parse(media.platform_crops)
        } catch (e) {
          return []
        }
      } else {
        crops = media.platform_crops
      }
      
      return Object.entries(crops).map(([platform, cropData]) => ({
        platform,
        url: cropData.url,
        width: cropData.width,
        height: cropData.height,
        crop_data: cropData.crop_data
      }))
    }

    const getPlatformAspectRatio = (platform) => {
      const ratios = {
        instagram: '1:1',
        facebook: '1.91:1',
        linkedin: '1.91:1',
        twitter: '1.91:1',
        'instagram-story': '9:16',
        'facebook-story': '9:16'
      }
      return ratios[platform] || 'Custom'
    }

    const formatDate = (dateString) => {
      if (!dateString) return 'Unknown'
      return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      })
    }

    const openCropEditor = (media, platform) => {
      cropData.value = { media, platform }
      showCropModal.value = true
      closePreviewModal()
    }

    const closeCropModal = () => {
      showCropModal.value = false
      cropData.value = { media: null, platform: null }
      cropArea.value.initialized = false
    }

    const getPlatformRatio = (platform) => {
      const ratios = {
        instagram: 1.0,
        facebook: 1.91,
        linkedin: 1.91,
        twitter: 1.91,
        'instagram-story': 0.5625,
        'facebook-story': 0.5625
      }
      return ratios[platform] || 1.0
    }

    const initializeCropArea = () => {
      if (!cropImage.value || !cropData.value.platform) return
      
      const img = cropImage.value
      const ratio = getPlatformRatio(cropData.value.platform)
      
      // Calculate initial crop area (center crop)
      const imgWidth = img.offsetWidth
      const imgHeight = img.offsetHeight
      
      let cropWidth, cropHeight
      
      if (imgWidth / imgHeight > ratio) {
        // Image is wider than target ratio
        cropHeight = imgHeight
        cropWidth = cropHeight * ratio
      } else {
        // Image is taller than target ratio
        cropWidth = imgWidth
        cropHeight = cropWidth / ratio
      }
      
      cropArea.value = {
        x: (imgWidth - cropWidth) / 2,
        y: (imgHeight - cropHeight) / 2,
        width: cropWidth,
        height: cropHeight,
        initialized: true
      }
      
      updatePreview()
    }

    const updateCropArea = () => {
      const ratio = getPlatformRatio(cropData.value.platform)
      cropArea.value.height = cropArea.value.width / ratio
      
      // Ensure crop area stays within bounds
      const img = cropImage.value
      if (!img) return
      
      const maxX = img.offsetWidth - cropArea.value.width
      const maxY = img.offsetHeight - cropArea.value.height
      
      cropArea.value.x = Math.max(0, Math.min(cropArea.value.x, maxX))
      cropArea.value.y = Math.max(0, Math.min(cropArea.value.y, maxY))
      
      updatePreview()
    }

    const updatePreview = () => {
      if (!previewCanvas.value || !cropImage.value || !cropArea.value.initialized) return
      
      const canvas = previewCanvas.value
      const ctx = canvas.getContext('2d')
      const img = cropImage.value
      
      // Set canvas size to match crop area
      canvas.width = cropArea.value.width
      canvas.height = cropArea.value.height
      
      // Calculate scale factor between displayed image and actual image
      const scaleX = cropData.value.media.width / img.offsetWidth
      const scaleY = cropData.value.media.height / img.offsetHeight
      
      // Create a new image element for drawing
      const drawImg = new Image()
      drawImg.crossOrigin = 'anonymous'
      drawImg.onload = () => {
        ctx.drawImage(
          drawImg,
          cropArea.value.x * scaleX,
          cropArea.value.y * scaleY,
          cropArea.value.width * scaleX,
          cropArea.value.height * scaleY,
          0,
          0,
          cropArea.value.width,
          cropArea.value.height
        )
      }
      drawImg.src = cropData.value.media.original_url
    }

    const setCropPreset = (preset) => {
      if (!cropImage.value) return
      
      const img = cropImage.value
      const ratio = getPlatformRatio(cropData.value.platform)
      
      let cropWidth, cropHeight
      
      if (img.offsetWidth / img.offsetHeight > ratio) {
        cropHeight = img.offsetHeight
        cropWidth = cropHeight * ratio
      } else {
        cropWidth = img.offsetWidth
        cropHeight = cropWidth / ratio
      }
      
      let x, y
      
      switch (preset) {
        case 'center':
          x = (img.offsetWidth - cropWidth) / 2
          y = (img.offsetHeight - cropHeight) / 2
          break
        case 'top':
          x = (img.offsetWidth - cropWidth) / 2
          y = 0
          break
        case 'bottom':
          x = (img.offsetWidth - cropWidth) / 2
          y = img.offsetHeight - cropHeight
          break
        case 'left':
          x = 0
          y = (img.offsetHeight - cropHeight) / 2
          break
        case 'right':
          x = img.offsetWidth - cropWidth
          y = (img.offsetHeight - cropHeight) / 2
          break
        default:
          x = (img.offsetWidth - cropWidth) / 2
          y = (img.offsetHeight - cropHeight) / 2
      }
      
      cropArea.value = {
        x,
        y,
        width: cropWidth,
        height: cropHeight,
        initialized: true
      }
      
      updatePreview()
    }

    const getCropContainerStyle = (platform) => {
      const ratio = getPlatformRatio(platform)
      const maxWidth = 782 // Maximum width for the container
      
      let width, height
      
      if (ratio >= 1) {
        // Landscape or square - width is constrained
        width = maxWidth
        height = maxWidth / ratio
      } else {
        // Portrait - height is constrained to prevent too tall images
        const maxHeight = 410
        height = maxHeight
        width = maxHeight * ratio
      }
      
      return {
        width: `${width}px`,
        height: `${height}px`,
        aspectRatio: ratio.toString()
      }
    }

    const getPreviewSize = () => {
      const maxSize = 200
      const ratio = getPlatformRatio(cropData.value.platform)
      
      if (ratio >= 1) {
        return { width: maxSize, height: maxSize / ratio }
      } else {
        return { width: maxSize * ratio, height: maxSize }
      }
    }

    const saveCrop = async () => {
      if (!cropData.value.media || !cropArea.value.initialized) return
      
      savingCrop.value = true
      
      try {
        // Calculate scale factor
        const img = cropImage.value
        const scaleX = cropData.value.media.width / img.offsetWidth
        const scaleY = cropData.value.media.height / img.offsetHeight
        
        const cropParams = {
          x: Math.round(cropArea.value.x * scaleX),
          y: Math.round(cropArea.value.y * scaleY),
          width: Math.round(cropArea.value.width * scaleX),
          height: Math.round(cropArea.value.height * scaleY)
        }
        
        await axios.put(`/api/media/${cropData.value.media.id}/crops`, {
          platforms: [cropData.value.platform],
          crops: {
            [cropData.value.platform]: cropParams
          }
        })
        
        // Refresh the media data
        await loadUploadedMedia()
        
        closeCropModal()
        
        // Reopen preview modal with updated data
        const updatedMedia = uploadedMedia.value.find(m => m.id === cropData.value.media.id)
        if (updatedMedia) {
          openPreviewModal(updatedMedia)
        }
        
      } catch (error) {
        console.error('Failed to save crop:', error)
        alert('Failed to save crop. Please try again.')
      } finally {
        savingCrop.value = false
      }
    }

    // Computed properties for crop constraints
    const maxCropX = computed(() => {
      if (!cropImage.value || !cropArea.value.initialized) return 0
      return cropImage.value.offsetWidth - cropArea.value.width
    })

    const maxCropY = computed(() => {
      if (!cropImage.value || !cropArea.value.initialized) return 0
      return cropImage.value.offsetHeight - cropArea.value.height
    })

    const maxCropWidth = computed(() => {
      if (!cropImage.value) return 0
      return cropImage.value.offsetWidth - cropArea.value.x
    })

    const minCropSize = computed(() => 50) // Minimum crop size

    // Mouse event handlers for drag and resize
    const startDrag = (event) => {
      dragState.value = {
        isDragging: true,
        isResizing: false,
        startX: event.clientX,
        startY: event.clientY,
        startCropX: cropArea.value.x,
        startCropY: cropArea.value.y
      }
      
      document.addEventListener('mousemove', handleMouseMove)
      document.addEventListener('mouseup', handleMouseUp)
      event.preventDefault()
    }

    const startResize = (handle) => {
      return (event) => {
        dragState.value = {
          isDragging: false,
          isResizing: true,
          resizeHandle: handle,
          startX: event.clientX,
          startY: event.clientY,
          startCropX: cropArea.value.x,
          startCropY: cropArea.value.y,
          startCropWidth: cropArea.value.width,
          startCropHeight: cropArea.value.height
        }
        
        document.addEventListener('mousemove', handleMouseMove)
        document.addEventListener('mouseup', handleMouseUp)
        event.preventDefault()
      }
    }

    const handleMouseMove = (event) => {
      if (!dragState.value.isDragging && !dragState.value.isResizing) return
      
      const deltaX = event.clientX - dragState.value.startX
      const deltaY = event.clientY - dragState.value.startY
      
      if (dragState.value.isDragging) {
        // Handle dragging
        cropArea.value.x = Math.max(0, Math.min(
          dragState.value.startCropX + deltaX,
          maxCropX.value
        ))
        cropArea.value.y = Math.max(0, Math.min(
          dragState.value.startCropY + deltaY,
          maxCropY.value
        ))
      } else if (dragState.value.isResizing) {
        // Handle resizing while maintaining aspect ratio
        const ratio = getPlatformRatio(cropData.value.platform)
        const handle = dragState.value.resizeHandle
        
        let newWidth = dragState.value.startCropWidth
        let newHeight = dragState.value.startCropHeight
        let newX = dragState.value.startCropX
        let newY = dragState.value.startCropY
        
        if (handle.includes('e')) {
          newWidth = dragState.value.startCropWidth + deltaX
        }
        if (handle.includes('w')) {
          newWidth = dragState.value.startCropWidth - deltaX
          newX = dragState.value.startCropX + deltaX
        }
        if (handle.includes('s')) {
          newHeight = dragState.value.startCropHeight + deltaY
        }
        if (handle.includes('n')) {
          newHeight = dragState.value.startCropHeight - deltaY
          newY = dragState.value.startCropY + deltaY
        }
        
        // Maintain aspect ratio
        if (handle.includes('e') || handle.includes('w')) {
          newHeight = newWidth / ratio
        } else if (handle.includes('n') || handle.includes('s')) {
          newWidth = newHeight * ratio
        }
        
        // Apply constraints
        const img = cropImage.value
        if (img) {
          newWidth = Math.max(minCropSize.value, Math.min(newWidth, img.offsetWidth - newX))
          newHeight = newWidth / ratio
          
          if (newX + newWidth > img.offsetWidth) {
            newWidth = img.offsetWidth - newX
            newHeight = newWidth / ratio
          }
          if (newY + newHeight > img.offsetHeight) {
            newHeight = img.offsetHeight - newY
            newWidth = newHeight * ratio
          }
          
          cropArea.value.x = Math.max(0, newX)
          cropArea.value.y = Math.max(0, newY)
          cropArea.value.width = newWidth
          cropArea.value.height = newHeight
        }
      }
      
      updatePreview()
    }

    const handleMouseUp = () => {
      dragState.value = {
        isDragging: false,
        isResizing: false,
        resizeHandle: null
      }
      
      document.removeEventListener('mousemove', handleMouseMove)
      document.removeEventListener('mouseup', handleMouseUp)
    }

    const loadUploadedMedia = async () => {
      try {
        const response = await axios.get('/api/media')
        const media = response.data.media || []
        
        // Initialize loading states for each media item
        media.forEach(item => {
          item.imageLoaded = false
          item.imageError = false
        })
        
        // Debug: Log the media data
        console.log('Loaded media:', media)
        
        uploadedMedia.value = media
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
      showPreviewModal,
      selectedMediaForPreview,
      showCropModal,
      cropContainer,
      cropImage,
      previewCanvas,
      savingCrop,
      cropData,
      cropArea,
      maxCropX,
      maxCropY,
      maxCropWidth,
      minCropSize,
      handleDrop,
      handleFileSelect,
      selectMedia,
      deleteMedia,
      hasPlatformCrop,
      getImageUrl,
      handleImageError,
      handleImageLoad,
      openPreviewModal,
      closePreviewModal,
      getPlatformCrops,
      getPlatformAspectRatio,
      formatDate,
      openCropEditor,
      closeCropModal,
      initializeCropArea,
      updateCropArea,
      updatePreview,
      setCropPreset,
      getCropContainerStyle,
      getPreviewSize,
      saveCrop,
      startDrag,
      startResize,
      getCropImageUrl,
      handleCropImageError,
      handleCropImageLoad
    }
  }
}
</script>

<style scoped>
.aspect-square {
  aspect-ratio: 1 / 1;
}

/* Fallback for browsers that don't support aspect-ratio */
@supports not (aspect-ratio: 1 / 1) {
  .aspect-square::before {
    content: '';
    display: block;
    padding-top: 100%;
  }
  
  .aspect-square > div {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
  }
}

/* Ensure proper layering */
.relative {
  position: relative;
}

.z-10 {
  z-index: 10;
}

.z-20 {
  z-index: 20;
}

.z-30 {
  z-index: 30;
}

/* Image loading states */
img[loading="lazy"] {
  opacity: 0;
  transition: opacity 0.3s ease-in-out;
}

img[loading="lazy"].loaded {
  opacity: 1 !important;
}

/* Force opacity for loaded images */
img[style*="opacity: 1"] {
  opacity: 1 !important;
}

/* Hover effects */
.group:hover .group-hover\:opacity-100 {
  opacity: 1 !important;
}

.group:hover .group-hover\:bg-opacity-30 {
  background-opacity: 0.3 !important;
}

/* Ensure buttons are clickable */
button {
  position: relative;
  z-index: inherit;
}

/* Platform crop containers */
.platform-crop-container {
  position: relative;
  overflow: hidden;
}

.platform-crop-container img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  opacity: 0;
  transition: opacity 0.3s ease-in-out;
}

.platform-crop-container img[style*="opacity: 1"] {
  opacity: 1 !important;
}
</style>