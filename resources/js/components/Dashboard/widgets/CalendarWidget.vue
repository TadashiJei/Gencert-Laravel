<template>
  <div class="calendar-widget">
    <!-- Calendar Header -->
    <div class="flex justify-between items-center mb-4">
      <div class="flex items-center space-x-4">
        <button @click="previousMonth" 
                class="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded">
          <chevron-left-icon class="h-5 w-5" />
        </button>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
          {{ currentMonthName }} {{ currentYear }}
        </h3>
        <button @click="nextMonth"
                class="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded">
          <chevron-right-icon class="h-5 w-5" />
        </button>
      </div>
      <div class="flex items-center space-x-2">
        <button @click="view = 'month'"
                :class="{ 'bg-blue-100 dark:bg-blue-900': view === 'month' }"
                class="px-3 py-1 rounded text-sm">
          Month
        </button>
        <button @click="view = 'week'"
                :class="{ 'bg-blue-100 dark:bg-blue-900': view === 'week' }"
                class="px-3 py-1 rounded text-sm">
          Week
        </button>
      </div>
    </div>

    <!-- Month View -->
    <div v-if="view === 'month'" class="grid grid-cols-7 gap-1">
      <!-- Day Headers -->
      <div v-for="day in weekDays" 
           :key="day"
           class="text-center text-sm font-medium text-gray-500 dark:text-gray-400 py-2">
        {{ day }}
      </div>

      <!-- Calendar Days -->
      <div v-for="(day, index) in calendarDays"
           :key="index"
           :class="[
             'min-h-[100px] p-2 border border-gray-200 dark:border-gray-700',
             {
               'bg-gray-50 dark:bg-gray-800': day.isCurrentMonth,
               'bg-gray-100 dark:bg-gray-700': !day.isCurrentMonth,
               'border-blue-500': isToday(day.date)
             }
           ]">
        <!-- Day Number -->
        <div class="flex justify-between items-center">
          <span :class="[
            'text-sm',
            isToday(day.date) ? 'text-blue-600 dark:text-blue-400 font-bold' : 'text-gray-700 dark:text-gray-300'
          ]">
            {{ day.dayOfMonth }}
          </span>
          <span v-if="day.events.length" 
                class="text-xs bg-blue-500 text-white px-1.5 rounded-full">
            {{ day.events.length }}
          </span>
        </div>

        <!-- Events -->
        <div class="mt-1 space-y-1">
          <div v-for="event in day.events.slice(0, 2)"
               :key="event.id"
               :class="[
                 'text-xs p-1 rounded truncate cursor-pointer',
                 getEventTypeClass(event.type)
               ]"
               @click="showEventDetails(event)">
            {{ event.title }}
          </div>
          <div v-if="day.events.length > 2"
               class="text-xs text-gray-500 dark:text-gray-400 cursor-pointer"
               @click="showMoreEvents(day)">
            +{{ day.events.length - 2 }} more
          </div>
        </div>
      </div>
    </div>

    <!-- Week View -->
    <div v-else class="space-y-2">
      <div v-for="day in weekViewDays"
           :key="day.date"
           class="flex border-l-4 p-2"
           :class="[
             getWeekDayClass(day),
             { 'border-blue-500': isToday(day.date) }
           ]">
        <div class="w-20 text-sm">
          <div class="font-medium">{{ formatWeekDay(day.date) }}</div>
          <div class="text-gray-500 dark:text-gray-400">{{ formatWeekDate(day.date) }}</div>
        </div>
        <div class="flex-1 space-y-1">
          <div v-for="event in day.events"
               :key="event.id"
               :class="[
                 'text-sm p-2 rounded cursor-pointer',
                 getEventTypeClass(event.type)
               ]"
               @click="showEventDetails(event)">
            <div class="font-medium">{{ event.title }}</div>
            <div class="text-xs">{{ event.time }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Event Details Modal -->
    <modal v-if="selectedEvent" @close="selectedEvent = null">
      <template #title>Event Details</template>
      <template #content>
        <div class="space-y-4">
          <div>
            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Title</h4>
            <p class="text-gray-900 dark:text-white">{{ selectedEvent.title }}</p>
          </div>
          <div>
            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Type</h4>
            <p class="text-gray-900 dark:text-white">{{ selectedEvent.type }}</p>
          </div>
          <div>
            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Date</h4>
            <p class="text-gray-900 dark:text-white">{{ formatEventDate(selectedEvent.date) }}</p>
          </div>
          <div v-if="selectedEvent.description">
            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</h4>
            <p class="text-gray-900 dark:text-white">{{ selectedEvent.description }}</p>
          </div>
        </div>
      </template>
      <template #footer>
        <button v-if="selectedEvent.type === 'expiration'"
                @click="handleRenewal(selectedEvent)"
                class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
          Renew Certificate
        </button>
        <button @click="selectedEvent = null"
                class="ml-2 bg-gray-300 dark:bg-gray-600 px-4 py-2 rounded">
          Close
        </button>
      </template>
    </modal>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import {
  startOfMonth,
  endOfMonth,
  startOfWeek,
  endOfWeek,
  eachDayOfInterval,
  format,
  addMonths,
  subMonths,
  isToday as dateFnsIsToday,
  parseISO
} from 'date-fns'
import axios from 'axios'

export default {
  name: 'CalendarWidget',

  setup() {
    const currentDate = ref(new Date())
    const view = ref('month')
    const events = ref([])
    const selectedEvent = ref(null)

    const weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

    const currentMonthName = computed(() => {
      return format(currentDate.value, 'MMMM')
    })

    const currentYear = computed(() => {
      return format(currentDate.value, 'yyyy')
    })

    const calendarDays = computed(() => {
      const start = startOfWeek(startOfMonth(currentDate.value))
      const end = endOfWeek(endOfMonth(currentDate.value))

      return eachDayOfInterval({ start, end }).map(date => ({
        date,
        dayOfMonth: format(date, 'd'),
        isCurrentMonth: format(date, 'M') === format(currentDate.value, 'M'),
        events: getEventsForDate(date)
      }))
    })

    const weekViewDays = computed(() => {
      const start = startOfWeek(currentDate.value)
      return eachDayOfInterval({
        start,
        end: endOfWeek(currentDate.value)
      }).map(date => ({
        date,
        events: getEventsForDate(date)
      }))
    })

    function getEventsForDate(date) {
      return events.value.filter(event => 
        format(parseISO(event.date), 'yyyy-MM-dd') === format(date, 'yyyy-MM-dd')
      )
    }

    function previousMonth() {
      currentDate.value = subMonths(currentDate.value, 1)
      fetchEvents()
    }

    function nextMonth() {
      currentDate.value = addMonths(currentDate.value, 1)
      fetchEvents()
    }

    function isToday(date) {
      return dateFnsIsToday(date)
    }

    function getEventTypeClass(type) {
      return {
        expiration: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        renewal: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        issuance: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
      }[type] || ''
    }

    function showEventDetails(event) {
      selectedEvent.value = event
    }

    function formatEventDate(date) {
      return format(parseISO(date), 'MMMM d, yyyy')
    }

    function formatWeekDay(date) {
      return format(date, 'EEEE')
    }

    function formatWeekDate(date) {
      return format(date, 'MMM d')
    }

    async function fetchEvents() {
      try {
        const response = await axios.get('/api/calendar-events', {
          params: {
            start: format(startOfMonth(currentDate.value), 'yyyy-MM-dd'),
            end: format(endOfMonth(currentDate.value), 'yyyy-MM-dd')
          }
        })
        events.value = response.data
      } catch (error) {
        console.error('Failed to fetch events:', error)
      }
    }

    async function handleRenewal(event) {
      try {
        await axios.post(`/api/certificates/${event.certificateId}/renew`)
        fetchEvents()
        selectedEvent.value = null
      } catch (error) {
        console.error('Failed to renew certificate:', error)
      }
    }

    onMounted(() => {
      fetchEvents()
    })

    return {
      currentDate,
      view,
      weekDays,
      currentMonthName,
      currentYear,
      calendarDays,
      weekViewDays,
      selectedEvent,
      previousMonth,
      nextMonth,
      isToday,
      getEventTypeClass,
      showEventDetails,
      formatEventDate,
      formatWeekDay,
      formatWeekDate,
      handleRenewal
    }
  }
}
</script>

<style scoped>
.calendar-widget {
  @apply bg-white dark:bg-gray-800 rounded-lg p-4;
}
</style>
