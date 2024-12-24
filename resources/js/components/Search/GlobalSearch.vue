<template>
  <div class="global-search">
    <!-- Search Input -->
    <div class="relative">
      <div class="flex items-center">
        <div class="relative flex-1">
          <input type="text"
                 v-model="searchQuery"
                 @focus="showResults = true"
                 @keydown.down="navigateResults('down')"
                 @keydown.up="navigateResults('up')"
                 @keydown.enter="selectResult"
                 placeholder="Search certificates, templates, or users..."
                 class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
          <search-icon class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
          <button v-if="searchQuery"
                  @click="clearSearch"
                  class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
            <x-icon class="h-5 w-5" />
          </button>
        </div>
        <button @click="showAdvancedSearch = true"
                class="ml-2 p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
          <adjustments-icon class="h-5 w-5" />
        </button>
      </div>

      <!-- Search Results Dropdown -->
      <div v-if="showResults && (searchResults.length > 0 || isSearching)"
           class="absolute z-50 w-full mt-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700">
        <!-- Loading State -->
        <div v-if="isSearching" class="p-4 text-center">
          <spinner class="h-6 w-6 mx-auto text-blue-500" />
        </div>

        <!-- Results List -->
        <div v-else class="max-h-96 overflow-y-auto">
          <!-- Sections -->
          <div v-for="(section, type) in groupedResults"
               :key="type"
               class="py-2">
            <div class="px-4 py-2 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
              {{ type }}
            </div>
            <div v-for="(result, index) in section"
                 :key="result.id"
                 @click="selectResult(result)"
                 @mouseenter="selectedIndex = getAbsoluteIndex(type, index)"
                 :class="[
                   'px-4 py-2 cursor-pointer',
                   selectedIndex === getAbsoluteIndex(type, index)
                     ? 'bg-blue-50 dark:bg-blue-900'
                     : 'hover:bg-gray-50 dark:hover:bg-gray-700'
                 ]">
              <!-- Certificate Result -->
              <div v-if="type === 'Certificates'"
                   class="flex items-start space-x-3">
                <document-icon class="h-5 w-5 text-blue-500 mt-0.5" />
                <div>
                  <div class="font-medium text-gray-900 dark:text-white">
                    {{ result.certificate_number }}
                  </div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ result.recipient_name }}
                  </div>
                  <div class="mt-1 flex items-center space-x-2">
                    <span :class="getStatusClass(result.status)"
                          class="px-2 py-0.5 text-xs rounded-full">
                      {{ result.status }}
                    </span>
                    <span class="text-xs text-gray-500">
                      {{ formatDate(result.issue_date) }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Template Result -->
              <div v-else-if="type === 'Templates'"
                   class="flex items-start space-x-3">
                <template-icon class="h-5 w-5 text-purple-500 mt-0.5" />
                <div>
                  <div class="font-medium text-gray-900 dark:text-white">
                    {{ result.name }}
                  </div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ result.description }}
                  </div>
                  <div class="mt-1 text-xs text-gray-500">
                    Last modified {{ formatDate(result.updated_at) }}
                  </div>
                </div>
              </div>

              <!-- User Result -->
              <div v-else-if="type === 'Users'"
                   class="flex items-start space-x-3">
                <user-icon class="h-5 w-5 text-green-500 mt-0.5" />
                <div>
                  <div class="font-medium text-gray-900 dark:text-white">
                    {{ result.name }}
                  </div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ result.email }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Show All Results Link -->
          <div v-if="totalResults > searchResults.length"
               class="px-4 py-3 text-sm text-center border-t border-gray-200 dark:border-gray-700">
            <button @click="showAdvancedSearch = true"
                    class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
              Show all {{ totalResults }} results
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Advanced Search Modal -->
    <modal v-if="showAdvancedSearch" @close="showAdvancedSearch = false">
      <template #title>Advanced Search</template>
      <template #content>
        <div class="space-y-6">
          <!-- Search Types -->
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Search In
            </label>
            <div class="space-x-4">
              <label class="inline-flex items-center">
                <input type="checkbox"
                       v-model="advancedFilters.types"
                       value="certificates"
                       class="rounded border-gray-300 dark:border-gray-600">
                <span class="ml-2 text-sm text-gray-600 dark:text-gray-300">
                  Certificates
                </span>
              </label>
              <label class="inline-flex items-center">
                <input type="checkbox"
                       v-model="advancedFilters.types"
                       value="templates"
                       class="rounded border-gray-300 dark:border-gray-600">
                <span class="ml-2 text-sm text-gray-600 dark:text-gray-300">
                  Templates
                </span>
              </label>
              <label class="inline-flex items-center">
                <input type="checkbox"
                       v-model="advancedFilters.types"
                       value="users"
                       class="rounded border-gray-300 dark:border-gray-600">
                <span class="ml-2 text-sm text-gray-600 dark:text-gray-300">
                  Users
                </span>
              </label>
            </div>
          </div>

          <!-- Date Range -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                From Date
              </label>
              <input type="date"
                     v-model="advancedFilters.dateFrom"
                     class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                To Date
              </label>
              <input type="date"
                     v-model="advancedFilters.dateTo"
                     class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700">
            </div>
          </div>

          <!-- Status Filter -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Status
            </label>
            <select v-model="advancedFilters.status"
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700">
              <option value="">All Statuses</option>
              <option value="active">Active</option>
              <option value="expired">Expired</option>
              <option value="revoked">Revoked</option>
            </select>
          </div>

          <!-- Sort Options -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Sort By
            </label>
            <select v-model="advancedFilters.sortBy"
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700">
              <option value="relevance">Relevance</option>
              <option value="date">Date</option>
              <option value="name">Name</option>
            </select>
          </div>
        </div>
      </template>
      <template #footer>
        <div class="flex justify-between">
          <button @click="resetAdvancedFilters"
                  class="text-gray-600 dark:text-gray-400 hover:text-gray-800">
            Reset Filters
          </button>
          <div>
            <button @click="showAdvancedSearch = false"
                    class="mr-2 px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800">
              Cancel
            </button>
            <button @click="applyAdvancedSearch"
                    class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
              Search
            </button>
          </div>
        </div>
      </template>
    </modal>
  </div>
</template>

<script>
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { format } from 'date-fns'
import debounce from 'lodash/debounce'
import axios from 'axios'

export default {
  name: 'GlobalSearch',

  setup() {
    const router = useRouter()
    const searchQuery = ref('')
    const showResults = ref(false)
    const isSearching = ref(false)
    const searchResults = ref([])
    const selectedIndex = ref(-1)
    const totalResults = ref(0)
    const showAdvancedSearch = ref(false)

    const advancedFilters = ref({
      types: ['certificates', 'templates', 'users'],
      dateFrom: '',
      dateTo: '',
      status: '',
      sortBy: 'relevance'
    })

    const groupedResults = computed(() => {
      return searchResults.value.reduce((groups, result) => {
        const type = result.type.charAt(0).toUpperCase() + result.type.slice(1) + 's'
        if (!groups[type]) {
          groups[type] = []
        }
        groups[type].push(result)
        return groups
      }, {})
    })

    // Debounced search function
    const performSearch = debounce(async () => {
      if (!searchQuery.value) {
        searchResults.value = []
        isSearching.value = false
        return
      }

      isSearching.value = true
      try {
        const response = await axios.get('/api/search', {
          params: {
            query: searchQuery.value,
            types: advancedFilters.value.types,
            limit: 10
          }
        })
        searchResults.value = response.data.results
        totalResults.value = response.data.total
      } catch (error) {
        console.error('Search failed:', error)
        searchResults.value = []
      } finally {
        isSearching.value = false
      }
    }, 300)

    watch(searchQuery, () => {
      selectedIndex.value = -1
      performSearch()
    })

    function getAbsoluteIndex(type, index) {
      let absoluteIndex = index
      const types = Object.keys(groupedResults.value)
      const typeIndex = types.indexOf(type)
      
      for (let i = 0; i < typeIndex; i++) {
        absoluteIndex += groupedResults.value[types[i]].length
      }
      
      return absoluteIndex
    }

    function navigateResults(direction) {
      if (direction === 'down') {
        selectedIndex.value = Math.min(
          selectedIndex.value + 1,
          searchResults.value.length - 1
        )
      } else {
        selectedIndex.value = Math.max(selectedIndex.value - 1, -1)
      }
    }

    function selectResult(result) {
      if (!result) {
        result = searchResults.value[selectedIndex.value]
        if (!result) return
      }

      showResults.value = false
      searchQuery.value = ''

      // Navigate based on result type
      switch (result.type) {
        case 'certificate':
          router.push(`/certificates/${result.id}`)
          break
        case 'template':
          router.push(`/templates/${result.id}`)
          break
        case 'user':
          router.push(`/users/${result.id}`)
          break
      }
    }

    function clearSearch() {
      searchQuery.value = ''
      searchResults.value = []
      showResults.value = false
    }

    function resetAdvancedFilters() {
      advancedFilters.value = {
        types: ['certificates', 'templates', 'users'],
        dateFrom: '',
        dateTo: '',
        status: '',
        sortBy: 'relevance'
      }
    }

    async function applyAdvancedSearch() {
      showAdvancedSearch.value = false
      isSearching.value = true

      try {
        const response = await axios.get('/api/search', {
          params: {
            query: searchQuery.value,
            ...advancedFilters.value
          }
        })
        searchResults.value = response.data.results
        totalResults.value = response.data.total
      } catch (error) {
        console.error('Advanced search failed:', error)
        searchResults.value = []
      } finally {
        isSearching.value = false
      }
    }

    function getStatusClass(status) {
      return {
        active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        expired: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        revoked: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
      }[status] || ''
    }

    function formatDate(date) {
      return format(new Date(date), 'MMM d, yyyy')
    }

    return {
      searchQuery,
      showResults,
      isSearching,
      searchResults,
      selectedIndex,
      totalResults,
      showAdvancedSearch,
      advancedFilters,
      groupedResults,
      getAbsoluteIndex,
      navigateResults,
      selectResult,
      clearSearch,
      resetAdvancedFilters,
      applyAdvancedSearch,
      getStatusClass,
      formatDate
    }
  }
}
</script>

<style scoped>
.global-search {
  @apply relative;
}
</style>
