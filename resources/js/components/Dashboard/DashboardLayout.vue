<template>
  <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
    <!-- Top Navigation -->
    <nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex">
            <!-- Logo -->
            <div class="flex-shrink-0 flex items-center">
              <img class="h-8 w-auto" src="/logo.svg" alt="CertificateHub">
            </div>
            <!-- Navigation Links -->
            <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
              <router-link v-for="item in navigation"
                          :key="item.name"
                          :to="item.href"
                          :class="[
                            isCurrentRoute(item.href)
                              ? 'border-blue-500 text-gray-900 dark:text-white'
                              : 'border-transparent text-gray-500 dark:text-gray-400 hover:border-gray-300 hover:text-gray-700 dark:hover:text-gray-300',
                            'inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium'
                          ]">
                {{ item.name }}
              </router-link>
            </div>
          </div>

          <!-- Right side -->
          <div class="flex items-center">
            <!-- Theme Toggle -->
            <button @click="toggleTheme" 
                    class="p-2 rounded-md text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
              <sun-icon v-if="isDarkMode" class="h-6 w-6" />
              <moon-icon v-else class="h-6 w-6" />
            </button>

            <!-- Search -->
            <div class="relative ml-4">
              <input type="text"
                     v-model="searchQuery"
                     placeholder="Search..."
                     class="w-64 px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
            </div>

            <!-- User Menu -->
            <div class="ml-4 relative">
              <dropdown>
                <template #trigger>
                  <button class="flex items-center space-x-2">
                    <img :src="user.avatar" class="h-8 w-8 rounded-full">
                    <span class="text-gray-900 dark:text-white">{{ user.name }}</span>
                  </button>
                </template>

                <template #content>
                  <dropdown-link :href="route('profile')">Profile</dropdown-link>
                  <dropdown-link :href="route('settings')">Settings</dropdown-link>
                  <dropdown-link :href="route('logout')" method="post">Logout</dropdown-link>
                </template>
              </dropdown>
            </div>
          </div>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
      <!-- Customizable Widget Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <draggable v-model="widgets" 
                  class="grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
                  handle=".widget-handle">
          <template #item="{ element: widget }">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
              <!-- Widget Header -->
              <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ widget.title }}</h3>
                <div class="flex items-center space-x-2">
                  <button class="widget-handle cursor-move">
                    <drag-icon class="h-5 w-5 text-gray-500" />
                  </button>
                  <button @click="removeWidget(widget.id)">
                    <x-icon class="h-5 w-5 text-gray-500" />
                  </button>
                </div>
              </div>
              <!-- Widget Content -->
              <div class="p-4">
                <component :is="widget.component" v-bind="widget.props" />
              </div>
            </div>
          </template>
        </draggable>
      </div>

      <!-- Add Widget Button -->
      <button @click="showWidgetModal = true"
              class="mt-6 px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
        Add Widget
      </button>

      <!-- Widget Selection Modal -->
      <modal v-if="showWidgetModal" @close="showWidgetModal = false">
        <template #title>Add Widget</template>
        <template #content>
          <div class="grid grid-cols-2 gap-4">
            <div v-for="widget in availableWidgets"
                 :key="widget.id"
                 class="p-4 border rounded-lg cursor-pointer hover:border-blue-500"
                 @click="addWidget(widget)">
              <h4 class="font-medium">{{ widget.title }}</h4>
              <p class="text-sm text-gray-500">{{ widget.description }}</p>
            </div>
          </div>
        </template>
      </modal>
    </main>
  </div>
</template>

<script>
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import draggable from 'vuedraggable'
import StatisticsWidget from './widgets/StatisticsWidget.vue'
import RecentCertificatesWidget from './widgets/RecentCertificatesWidget.vue'
import CalendarWidget from './widgets/CalendarWidget.vue'
import ActivityLogWidget from './widgets/ActivityLogWidget.vue'

export default {
  name: 'DashboardLayout',
  
  components: {
    draggable,
    StatisticsWidget,
    RecentCertificatesWidget,
    CalendarWidget,
    ActivityLogWidget
  },

  setup() {
    const route = useRoute()
    const searchQuery = ref('')
    const isDarkMode = ref(false)
    const showWidgetModal = ref(false)
    const widgets = ref([])

    const navigation = [
      { name: 'Dashboard', href: '/dashboard' },
      { name: 'Certificates', href: '/certificates' },
      { name: 'Templates', href: '/templates' },
      { name: 'Reports', href: '/reports' }
    ]

    const availableWidgets = [
      {
        id: 'statistics',
        title: 'Statistics',
        description: 'Show key metrics and statistics',
        component: 'StatisticsWidget',
        props: {}
      },
      {
        id: 'recent-certificates',
        title: 'Recent Certificates',
        description: 'Display recently created certificates',
        component: 'RecentCertificatesWidget',
        props: { limit: 5 }
      },
      {
        id: 'calendar',
        title: 'Calendar',
        description: 'View upcoming certificate expirations',
        component: 'CalendarWidget',
        props: {}
      },
      {
        id: 'activity-log',
        title: 'Activity Log',
        description: 'Track recent system activities',
        component: 'ActivityLogWidget',
        props: { limit: 10 }
      }
    ]

    function isCurrentRoute(href) {
      return route.path === href
    }

    function toggleTheme() {
      isDarkMode.value = !isDarkMode.value
      document.documentElement.classList.toggle('dark')
    }

    function addWidget(widget) {
      widgets.value.push({
        ...widget,
        id: `${widget.id}-${Date.now()}`
      })
      showWidgetModal.value = false
    }

    function removeWidget(widgetId) {
      widgets.value = widgets.value.filter(w => w.id !== widgetId)
    }

    return {
      navigation,
      searchQuery,
      isDarkMode,
      showWidgetModal,
      widgets,
      availableWidgets,
      isCurrentRoute,
      toggleTheme,
      addWidget,
      removeWidget
    }
  }
}
</script>

<style scoped>
.dark {
  color-scheme: dark;
}
</style>
