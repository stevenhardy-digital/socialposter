<template>
  <div>
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Brand Guidelines</h1>
        
        <!-- Social Account Selection -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
          <h2 class="text-lg font-medium text-gray-900 mb-4">Select Social Account</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div 
              v-for="account in socialAccounts" 
              :key="account.id"
              @click="selectAccount(account)"
              :class="[
                'border-2 rounded-lg p-4 cursor-pointer transition-colors',
                selectedAccount?.id === account.id 
                  ? 'border-blue-500 bg-blue-50' 
                  : 'border-gray-200 hover:border-gray-300'
              ]"
            >
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <span class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-gray-500">
                    <span class="text-sm font-medium leading-none text-white">
                      {{ account.platform.charAt(0).toUpperCase() }}
                    </span>
                  </span>
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-900">{{ account.platform }}</p>
                  <p class="text-sm text-gray-500">{{ account.account_name }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Brand Guidelines Form -->
        <div v-if="selectedAccount" class="bg-white shadow rounded-lg p-6">
          <h2 class="text-lg font-medium text-gray-900 mb-4">
            Brand Guidelines for {{ selectedAccount.platform }}
          </h2>
          
          <form @submit.prevent="saveGuidelines" class="space-y-6">
            <!-- Tone of Voice -->
            <div>
              <label for="tone_of_voice" class="block text-sm font-medium text-gray-700">
                Tone of Voice
              </label>
              <textarea
                id="tone_of_voice"
                v-model="form.tone_of_voice"
                rows="3"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                placeholder="Describe the tone of voice for this platform (e.g., Professional and informative, Casual and friendly)"
                required
              ></textarea>
              <p class="mt-1 text-sm text-gray-500">
                Describe how your brand should sound on this platform
              </p>
            </div>

            <!-- Brand Voice -->
            <div>
              <label for="brand_voice" class="block text-sm font-medium text-gray-700">
                Brand Voice
              </label>
              <textarea
                id="brand_voice"
                v-model="form.brand_voice"
                rows="3"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                placeholder="Describe your brand's personality (e.g., Confident, Approachable, Knowledgeable)"
                required
              ></textarea>
              <p class="mt-1 text-sm text-gray-500">
                Define your brand's personality and character
              </p>
            </div>

            <!-- Content Themes -->
            <div>
              <label for="content_themes" class="block text-sm font-medium text-gray-700">
                Content Themes
              </label>
              <div class="mt-1">
                <div class="flex flex-wrap gap-2 mb-2">
                  <span
                    v-for="(theme, index) in form.content_themes"
                    :key="index"
                    class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800"
                  >
                    {{ theme }}
                    <button
                      type="button"
                      @click="removeTheme(index)"
                      class="ml-2 text-blue-600 hover:text-blue-800"
                    >
                      ×
                    </button>
                  </span>
                </div>
                <div class="flex">
                  <input
                    v-model="newTheme"
                    type="text"
                    class="flex-1 border-gray-300 rounded-l-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Add a content theme"
                    @keyup.enter="addTheme"
                  />
                  <button
                    type="button"
                    @click="addTheme"
                    class="px-4 py-2 bg-blue-600 text-white rounded-r-md hover:bg-blue-700"
                  >
                    Add
                  </button>
                </div>
              </div>
              <p class="mt-1 text-sm text-gray-500">
                Topics and themes your content should focus on
              </p>
            </div>

            <!-- Hashtag Strategy -->
            <div>
              <label for="hashtag_strategy" class="block text-sm font-medium text-gray-700">
                Hashtag Strategy
              </label>
              <div class="mt-1">
                <div class="flex flex-wrap gap-2 mb-2">
                  <span
                    v-for="(hashtag, index) in form.hashtag_strategy"
                    :key="index"
                    class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-green-100 text-green-800"
                  >
                    {{ hashtag }}
                    <button
                      type="button"
                      @click="removeHashtag(index)"
                      class="ml-2 text-green-600 hover:text-green-800"
                    >
                      ×
                    </button>
                  </span>
                </div>
                <div class="flex">
                  <input
                    v-model="newHashtag"
                    type="text"
                    class="flex-1 border-gray-300 rounded-l-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Add a hashtag (with #)"
                    @keyup.enter="addHashtag"
                  />
                  <button
                    type="button"
                    @click="addHashtag"
                    class="px-4 py-2 bg-green-600 text-white rounded-r-md hover:bg-green-700"
                  >
                    Add
                  </button>
                </div>
              </div>
              <p class="mt-1 text-sm text-gray-500">
                Hashtags to use consistently for this platform
              </p>
            </div>

            <!-- Posting Frequency -->
            <div>
              <label for="posting_frequency" class="block text-sm font-medium text-gray-700">
                Posting Frequency
              </label>
              <select
                id="posting_frequency"
                v-model="form.posting_frequency"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                required
              >
                <option value="">Select frequency</option>
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="bi-weekly">Bi-weekly</option>
                <option value="monthly">Monthly</option>
              </select>
              <p class="mt-1 text-sm text-gray-500">
                How often you want to post on this platform
              </p>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-end space-x-3">
              <button
                type="button"
                @click="resetForm"
                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
              >
                Reset
              </button>
              <button
                type="submit"
                :disabled="loading"
                class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
              >
                {{ loading ? 'Saving...' : 'Save Guidelines' }}
              </button>
            </div>
          </form>
        </div>

        <!-- No Account Selected -->
        <div v-else class="bg-white shadow rounded-lg p-6">
          <div class="text-center">
            <p class="text-gray-500">Select a social account to configure brand guidelines</p>
          </div>
        </div>
  </div>
</template>

<script>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'

export default {
  name: 'BrandGuidelines',
  setup() {
    const socialAccounts = ref([])
    const selectedAccount = ref(null)
    const loading = ref(false)
    const newTheme = ref('')
    const newHashtag = ref('')

    const form = reactive({
      tone_of_voice: '',
      brand_voice: '',
      content_themes: [],
      hashtag_strategy: [],
      posting_frequency: ''
    })

    const fetchSocialAccounts = async () => {
      try {
        const response = await axios.get('/api/social-accounts')
        socialAccounts.value = response.data.social_accounts || []
      } catch (error) {
        console.error('Error fetching social accounts:', error)
      }
    }

    const selectAccount = async (account) => {
      selectedAccount.value = account
      await loadGuidelines(account.id)
    }

    const loadGuidelines = async (socialAccountId) => {
      try {
        const response = await axios.get(`/api/brand-guidelines/social-account/${socialAccountId}`)
        const guidelines = response.data.brand_guideline
        
        if (guidelines) {
          form.tone_of_voice = guidelines.tone_of_voice || ''
          form.brand_voice = guidelines.brand_voice || ''
          form.content_themes = guidelines.content_themes || []
          form.hashtag_strategy = guidelines.hashtag_strategy || []
          form.posting_frequency = guidelines.posting_frequency || ''
        } else {
          resetForm()
        }
      } catch (error) {
        console.error('Error loading guidelines:', error)
        resetForm()
      }
    }

    const saveGuidelines = async () => {
      if (!selectedAccount.value) return

      // Validation
      if (!form.tone_of_voice || !form.brand_voice || !form.posting_frequency) {
        alert('Please fill in all required fields')
        return
      }

      if (form.content_themes.length === 0) {
        alert('Please add at least one content theme')
        return
      }

      if (form.hashtag_strategy.length === 0) {
        alert('Please add at least one hashtag')
        return
      }

      loading.value = true
      try {
        await axios.post(`/api/brand-guidelines/social-account/${selectedAccount.value.id}`, form)
        alert('Brand guidelines saved successfully!')
      } catch (error) {
        console.error('Error saving guidelines:', error)
        alert('Error saving guidelines. Please try again.')
      } finally {
        loading.value = false
      }
    }

    const addTheme = () => {
      if (newTheme.value.trim() && !form.content_themes.includes(newTheme.value.trim())) {
        form.content_themes.push(newTheme.value.trim())
        newTheme.value = ''
      }
    }

    const removeTheme = (index) => {
      form.content_themes.splice(index, 1)
    }

    const addHashtag = () => {
      let hashtag = newHashtag.value.trim()
      if (hashtag && !hashtag.startsWith('#')) {
        hashtag = '#' + hashtag
      }
      if (hashtag && !form.hashtag_strategy.includes(hashtag)) {
        form.hashtag_strategy.push(hashtag)
        newHashtag.value = ''
      }
    }

    const removeHashtag = (index) => {
      form.hashtag_strategy.splice(index, 1)
    }

    const resetForm = () => {
      form.tone_of_voice = ''
      form.brand_voice = ''
      form.content_themes = []
      form.hashtag_strategy = []
      form.posting_frequency = ''
    }

    onMounted(() => {
      fetchSocialAccounts()
    })

    return {
      socialAccounts,
      selectedAccount,
      loading,
      newTheme,
      newHashtag,
      form,
      selectAccount,
      saveGuidelines,
      addTheme,
      removeTheme,
      addHashtag,
      removeHashtag,
      resetForm
    }
  }
}
</script>