<template>
  <div class="activity-log-widget">
    <!-- Filter Controls -->
    <div class="mb-4 flex space-x-2">
      <select v-model="filter"
              class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white">
        <option value="all">All Activities</option>
        <option value="certificate">Certificates</option>
        <option value="template">Templates</option>
        <option value="user">Users</option>
        <option value="system">System</option>
      </select>
      <button @click="refresh"
              class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
        <refresh-icon class="h-5 w-5" />
      </button>
    </div>

    <!-- Activity List -->
    <div class="space-y-4">
      <div v-for="activity in filteredActivities"
           :key="activity.id"
           class="flex space-x-4">
        <!-- Activity Icon -->
        <div class="flex-shrink-0">
          <div :class="[
            'w-8 h-8 rounded-full flex items-center justify-center',
            getActivityTypeClass(activity.type)
          ]">
            <component :is="getActivityIcon(activity.type)"
                      class="h-4 w-4 text-white" />
          </div>
        </div>

        <!-- Activity Content -->
        <div class="flex-1 min-w-0">
          <div class="flex justify-between items-start">
            <div class="text-sm font-medium text-gray-900 dark:text-white truncate">
              {{ activity.description }}
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
              {{ formatTime(activity.created_at) }}
            </div>
          </div>
          <div class="mt-1">
            <div class="text-sm text-gray-500 dark:text-gray-400">
              {{ activity.user ? `By ${activity.user.name}` : 'System' }}
            </div>
            <!-- Additional Details -->
            <div v-if="activity.details"
                 class="mt-2 text-sm">
              <div v-if="activity.type === 'certificate'"
                   class="flex items-center space-x-2">
                <document-icon class="h-4 w-4 text-gray-400" />
                <span class="text-gray-600 dark:text-gray-300">
                  Certificate #{{ activity.details.certificate_number }}
                </span>
              </div>
              <div v-if="activity.type === 'template'"
                   class="flex items-center space-x-2">
                <template-icon class="h-4 w-4 text-gray-400" />
                <span class="text-gray-600 dark:text-gray-300">
                  Template: {{ activity.details.template_name }}
                </span>
              </div>
              <!-- Changes List -->
              <div v-if="activity.details.changes"
                   class="mt-2 pl-4 border-l-2 border-gray-200 dark:border-gray-700">
                <div v-for="(change, field) in activity.details.changes"
                     :key="field"
                     class="text-xs text-gray-500 dark:text-gray-400">
                  <span class="font-medium">{{ field }}:</span>
                  <span class="line-through mr-1">{{ change.from }}</span>
                  <arrow-right-icon class="h-3 w-3 inline mx-1" />
                  <span class="text-gray-700 dark:text-gray-300">{{ change.to }}</span>
                </div>
              </div>
            </div>
          </div>
          <!-- Action Buttons -->
          <div v-if="activity.actions" class="mt-2 flex space-x-2">
            <button v-for="action in activity.actions"
                    :key="action.name"
                    @click="handleAction(action, activity)"
                    class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
              {{ action.name }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading"
         class="flex justify-center items-center py-4">
      <spinner class="h-6 w-6 text-blue-500" />
    </div>

    <!-- Empty State -->
    <div v-if="!loading && filteredActivities.length === 0"
         class="text-center py-4 text-gray-500 dark:text-gray-400">
      No activities found
    </div>

    <!-- Load More -->
    <div v-if="hasMore && !loading"
         class="text-center mt-4">
      <button @click="loadMore"
              class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
        Load More
      </button>
    </div>

    <!-- Action Confirmation Modal -->
    <modal v-if="showActionModal"
           @close="showActionModal = false">
      <template #title>{{ actionModal.title }}</template>
      <template #content>
        <div class="space-y-4">
          <p class="text-gray-600 dark:text-gray-300">
            {{ actionModal.message }}
          </p>
          <div v-if="actionModal.form">
            <component :is="actionModal.form"
                      v-model="actionModal.formData"
                      @submit="confirmAction" />
          </div>
        </div>
      </template>
      <template #footer>
        <button @click="confirmAction"
                :class="[
                  'px-4 py-2 rounded',
                  actionModal.type === 'danger' 
                    ? 'bg-red-600 text-white hover:bg-red-700'
                    : 'bg-blue-600 text-white hover:bg-blue-700'
                ]">
          {{ actionModal.confirmText || 'Confirm' }}
        </button>
        <button @click="showActionModal = false"
                class="ml-2 bg-gray-300 dark:bg-gray-600 px-4 py-2 rounded">
          Cancel
        </button>
      </template>
    </modal>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { format, formatDistanceToNow } from 'date-fns'
import axios from 'axios'

export default {
  name: 'ActivityLogWidget',

  props: {
    limit: {
      type: Number,
      default: 10
    }
  },

  setup(props) {
    const activities = ref([])
    const loading = ref(false)
    const page = ref(1)
    const hasMore = ref(true)
    const filter = ref('all')
    const showActionModal = ref(false)
    const actionModal = ref({})

    const filteredActivities = computed(() => {
      if (filter.value === 'all') return activities.value
      return activities.value.filter(activity => activity.type === filter.value)
    })

    function getActivityTypeClass(type) {
      return {
        certificate: 'bg-blue-500',
        template: 'bg-purple-500',
        user: 'bg-green-500',
        system: 'bg-gray-500'
      }[type] || 'bg-gray-500'
    }

    function getActivityIcon(type) {
      return {
        certificate: 'DocumentIcon',
        template: 'TemplateIcon',
        user: 'UserIcon',
        system: 'CogIcon'
      }[type] || 'InformationCircleIcon'
    }

    function formatTime(timestamp) {
      const date = new Date(timestamp)
      return formatDistanceToNow(date, { addSuffix: true })
    }

    async function fetchActivities() {
      if (loading.value) return

      loading.value = true
      try {
        const response = await axios.get('/api/activity-log', {
          params: {
            page: page.value,
            limit: props.limit,
            type: filter.value === 'all' ? undefined : filter.value
          }
        })
        
        activities.value = [...activities.value, ...response.data.activities]
        hasMore.value = response.data.hasMore
        page.value++
      } catch (error) {
        console.error('Failed to fetch activities:', error)
      } finally {
        loading.value = false
      }
    }

    function refresh() {
      activities.value = []
      page.value = 1
      hasMore.value = true
      fetchActivities()
    }

    function handleAction(action, activity) {
      actionModal.value = {
        title: action.title || 'Confirm Action',
        message: action.message || 'Are you sure you want to perform this action?',
        type: action.type || 'normal',
        confirmText: action.confirmText,
        form: action.form,
        formData: {},
        callback: () => action.handler(activity)
      }
      showActionModal.value = true
    }

    async function confirmAction() {
      try {
        await actionModal.value.callback(actionModal.value.formData)
        showActionModal.value = false
        refresh()
      } catch (error) {
        console.error('Action failed:', error)
      }
    }

    onMounted(() => {
      fetchActivities()
    })

    return {
      activities,
      loading,
      hasMore,
      filter,
      showActionModal,
      actionModal,
      filteredActivities,
      getActivityTypeClass,
      getActivityIcon,
      formatTime,
      refresh,
      loadMore: fetchActivities,
      handleAction,
      confirmAction
    }
  }
}
</script>

<style scoped>
.activity-log-widget {
  @apply bg-white dark:bg-gray-800 rounded-lg p-4;
}
</style>
