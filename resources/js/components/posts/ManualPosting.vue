<template>
  <div>
        <div class="mb-6">
          <h1 class="text-2xl font-bold text-gray-900">Create Manual Post</h1>
          <p class="mt-1 text-sm text-gray-600">
            Create and publish a post immediately to your social media accounts.
          </p>
        </div>

        <!-- Manual Post Form -->
        <div class="bg-white shadow rounded-lg p-6">
          <form @submit.prevent="createAndPublish">
            <div class="space-y-6">
              <!-- Platform Selection -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Select Platform <span class="text-red-500">*</span>
                </label>
                <select 
                  v-model="postForm.social_account_id" 
                  required 
                  class="w-full border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                  :disabled="publishing"
                >
                  <option value="">Choose a platform...</option>
                  <option
                    v-for="account in socialAccounts"
                    :key="account.id"
                    :value="account.id"
                  >
                    {{ (account.platform || '').charAt(0).toUpperCase() + (account.platform || '').slice(1) }} - {{ account.account_name || 'Unknown Account' }}
                  </option>
                </select>
              </div>

              <!-- Content -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Post Content <span class="text-red-500">*</span>
                </label>
                <textarea
                  v-model="postForm.content"
                  required
                  rows="6"
                  maxlength="2200"
                  class="w-full border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                  placeholder="Write your post content here..."
                  :disabled="publishing"
                ></textarea>
                <div class="mt-1 text-sm text-gray-500 text-right">
                  {{ postForm.content.length }}/2200 characters
                </div>
              </div>

              <!-- Media URLs (Optional) -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Media URLs (Optional)
                </label>
                <div class="space-y-2">
                  <div
                    v-for="(url, index) in postForm.media_urls"
                    :key="index"
                    class="flex space-x-2"
                  >
                    <input
                      v-model="postForm.media_urls[index]"
                      type="url"
                      placeholder="https://example.com/image.jpg"
                      class="flex-1 border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                      :disabled="publishing"
                    />
                    <button
                      type="button"
                      @click="removeMediaUrl(index)"
                      class="px-3 py-2 text-red-600 hover:text-red-800"
                      :disabled="publishing"
                    >
                      Remove
                    </button>
                  </div>
                  <button
                    type="button"
                    @click="addMediaUrl"
                    class="text-blue-600 hover:text-blue-800 text-sm"
                    :disabled="publishing"
                  >
                    + Add Media URL
                  </button>
                </div>
              </div>

              <!-- Action Buttons -->
              <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                <button
                  type="button"
                  @click="saveDraft"
                  :disabled="publishing || saving"
                  class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                >
                  {{ saving ? 'Saving...' : 'Save as Draft' }}
                </button>
                <button
                  type="submit"
                  :disabled="publishing || saving || !postForm.social_account_id || !postForm.content"
                  class="px-6 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
                >
                  {{ publishing ? 'Publishing...' : 'Create & Publish Now' }}
                </button>
              </div>
            </div>
          </form>
        </div>

        <!-- Success Message -->
        <div
          v-if="successMessage"
          class="mt-6 bg-green-50 border border-green-200 rounded-md p-4"
        >
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium text-green-800">{{ successMessage }}</p>
            </div>
          </div>
        </div>

        <!-- Error Message -->
        <div
          v-if="errorMessage"
          class="mt-6 bg-red-50 border border-red-200 rounded-md p-4"
        >
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium text-red-800">{{ errorMessage }}</p>
            </div>
          </div>
        </div>

        <!-- Manual Posting Instructions -->
        <div
          v-if="manualInstructions"
          class="mt-6 bg-yellow-50 border border-yellow-200 rounded-md p-6"
        >
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-yellow-800">Manual Posting Required</h3>
              <div class="mt-2 text-sm text-yellow-700">
                <p class="mb-3">This platform requires manual posting. Please follow these steps:</p>
                <ol class="list-decimal list-inside space-y-1">
                  <li v-for="(instruction, key) in manualInstructions.instructions" :key="key">
                    {{ instruction }}
                  </li>
                </ol>
                
                <div class="mt-4 p-3 bg-white border border-yellow-300 rounded">
                  <p class="font-medium text-yellow-800 mb-2">Content to copy:</p>
                  <div class="relative">
                    <textarea
                      :value="manualInstructions.content_to_copy"
                      readonly
                      rows="3"
                      class="w-full border-gray-300 rounded text-sm bg-gray-50"
                    ></textarea>
                    <button
                      @click="copyContent"
                      class="absolute top-2 right-2 px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700"
                    >
                      Copy
                    </button>
                  </div>
                </div>

                <div class="mt-4 flex space-x-3">
                  <a
                    :href="manualInstructions.platform_url"
                    target="_blank"
                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
                  >
                    Open Platform
                    <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                  </a>
                  <button
                    @click="markAsPublished"
                    class="px-3 py-2 border border-green-300 text-sm font-medium rounded-md text-green-700 bg-green-50 hover:bg-green-100"
                  >
                    Mark as Published
                  </button>
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
  name: 'ManualPosting',
  setup() {
    const socialAccounts = ref([])
    const publishing = ref(false)
    const saving = ref(false)
    const successMessage = ref('')
    const errorMessage = ref('')
    const manualInstructions = ref(null)
    const createdPost = ref(null)

    const postForm = reactive({
      social_account_id: '',
      content: '',
      media_urls: ['']
    })

    const loadSocialAccounts = async () => {
      try {
        const response = await axios.get('/api/social-accounts')
        socialAccounts.value = response.data.accounts || response.data
      } catch (error) {
        console.error('Error loading social accounts:', error)
        errorMessage.value = 'Failed to load social accounts'
      }
    }

    const addMediaUrl = () => {
      postForm.media_urls.push('')
    }

    const removeMediaUrl = (index) => {
      postForm.media_urls.splice(index, 1)
      if (postForm.media_urls.length === 0) {
        postForm.media_urls.push('')
      }
    }

    const clearMessages = () => {
      successMessage.value = ''
      errorMessage.value = ''
      manualInstructions.value = null
    }

    const createAndPublish = async () => {
      clearMessages()
      
      // Validate required fields
      if (socialAccounts.value.length === 0) {
        errorMessage.value = 'No social media accounts available. Please connect an account first.'
        return
      }
      
      if (!postForm.social_account_id) {
        errorMessage.value = 'Please select a social media platform'
        return
      }
      
      if (!postForm.content.trim()) {
        errorMessage.value = 'Please enter post content'
        return
      }
      
      publishing.value = true

      try {
        // Filter out empty media URLs
        const mediaUrls = postForm.media_urls.filter(url => url.trim() !== '')
        
        const payload = {
          social_account_id: parseInt(postForm.social_account_id),
          content: postForm.content,
          media_urls: mediaUrls.length > 0 ? mediaUrls : null
        }
        
        console.log('Sending payload:', payload)

        const response = await axios.post('/api/posts/create-and-publish', payload)

        if (response.status === 201) {
          // Successfully published
          successMessage.value = 'Post created and published successfully!'
          createdPost.value = response.data.post
          resetForm()
        } else if (response.status === 202) {
          // Requires manual posting
          manualInstructions.value = {
            instructions: response.data.instructions,
            content_to_copy: response.data.content_to_copy,
            platform_url: response.data.platform_url
          }
          createdPost.value = response.data.post
        }
      } catch (error) {
        console.error('Error creating post:', error)
        console.error('Error response:', error.response?.data)
        
        if (error.response?.data?.message) {
          errorMessage.value = error.response.data.message
        } else if (error.response?.data?.error) {
          errorMessage.value = error.response.data.error
        } else {
          errorMessage.value = 'Failed to create and publish post'
        }
      } finally {
        publishing.value = false
      }
    }

    const saveDraft = async () => {
      clearMessages()
      saving.value = true

      try {
        // Filter out empty media URLs
        const mediaUrls = postForm.media_urls.filter(url => url.trim() !== '')

        await axios.post('/api/posts', {
          social_account_id: postForm.social_account_id,
          content: postForm.content,
          media_urls: mediaUrls.length > 0 ? mediaUrls : null,
          scheduled_at: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString() // Tomorrow
        })

        successMessage.value = 'Post saved as draft successfully!'
        resetForm()
      } catch (error) {
        console.error('Error saving draft:', error)
        errorMessage.value = 'Failed to save draft'
      } finally {
        saving.value = false
      }
    }

    const copyContent = async () => {
      try {
        await navigator.clipboard.writeText(manualInstructions.value.content_to_copy)
        // Could add a temporary "Copied!" message here
      } catch (error) {
        console.error('Failed to copy content:', error)
      }
    }

    const markAsPublished = async () => {
      if (!createdPost.value) return

      try {
        await axios.post(`/api/posts/${createdPost.value.id}/mark-published`)
        successMessage.value = 'Post marked as published successfully!'
        manualInstructions.value = null
        createdPost.value = null
        resetForm()
      } catch (error) {
        console.error('Error marking as published:', error)
        errorMessage.value = 'Failed to mark post as published'
      }
    }

    const resetForm = () => {
      postForm.social_account_id = ''
      postForm.content = ''
      postForm.media_urls = ['']
    }

    onMounted(() => {
      loadSocialAccounts()
    })

    return {
      socialAccounts,
      publishing,
      saving,
      successMessage,
      errorMessage,
      manualInstructions,
      postForm,
      createAndPublish,
      saveDraft,
      addMediaUrl,
      removeMediaUrl,
      copyContent,
      markAsPublished
    }
  }
}
</script>