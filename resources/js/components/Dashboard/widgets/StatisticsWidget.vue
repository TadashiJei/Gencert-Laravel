<template>
  <div class="space-y-4">
    <div class="grid grid-cols-2 gap-4">
      <!-- Total Certificates -->
      <div class="bg-blue-50 dark:bg-blue-900 p-4 rounded-lg">
        <h4 class="text-blue-600 dark:text-blue-200 text-sm font-medium">Total Certificates</h4>
        <div class="mt-2 flex items-baseline">
          <p class="text-2xl font-semibold text-blue-900 dark:text-blue-100">
            {{ statistics.totalCertificates }}
          </p>
          <p class="ml-2 text-sm text-blue-600 dark:text-blue-200">
            <span v-if="statistics.certificateIncrease > 0" class="text-green-500">
              +{{ statistics.certificateIncrease }}%
            </span>
            <span v-else class="text-red-500">
              {{ statistics.certificateIncrease }}%
            </span>
          </p>
        </div>
      </div>

      <!-- Active Templates -->
      <div class="bg-green-50 dark:bg-green-900 p-4 rounded-lg">
        <h4 class="text-green-600 dark:text-green-200 text-sm font-medium">Active Templates</h4>
        <div class="mt-2 flex items-baseline">
          <p class="text-2xl font-semibold text-green-900 dark:text-green-100">
            {{ statistics.activeTemplates }}
          </p>
        </div>
      </div>
    </div>

    <!-- Chart -->
    <div class="h-64">
      <line-chart :data="chartData" :options="chartOptions" />
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-2 gap-4 mt-4">
      <div class="text-center">
        <p class="text-sm text-gray-500 dark:text-gray-400">Certificates This Month</p>
        <p class="text-xl font-semibold text-gray-900 dark:text-white">
          {{ statistics.certificatesThisMonth }}
        </p>
      </div>
      <div class="text-center">
        <p class="text-sm text-gray-500 dark:text-gray-400">Pending Renewals</p>
        <p class="text-xl font-semibold text-gray-900 dark:text-white">
          {{ statistics.pendingRenewals }}
        </p>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { Line as LineChart } from 'vue-chartjs'
import axios from 'axios'

export default {
  name: 'StatisticsWidget',
  
  components: {
    LineChart
  },

  setup() {
    const statistics = ref({
      totalCertificates: 0,
      certificateIncrease: 0,
      activeTemplates: 0,
      certificatesThisMonth: 0,
      pendingRenewals: 0
    })

    const chartData = ref({
      labels: [],
      datasets: [{
        label: 'Certificates Generated',
        data: [],
        borderColor: '#3B82F6',
        tension: 0.4
      }]
    })

    const chartOptions = {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          grid: {
            display: false
          }
        },
        x: {
          grid: {
            display: false
          }
        }
      },
      plugins: {
        legend: {
          display: false
        }
      }
    }

    async function fetchStatistics() {
      try {
        const response = await axios.get('/api/statistics')
        statistics.value = response.data.statistics
        chartData.value.labels = response.data.chart.labels
        chartData.value.datasets[0].data = response.data.chart.data
      } catch (error) {
        console.error('Failed to fetch statistics:', error)
      }
    }

    onMounted(() => {
      fetchStatistics()
    })

    return {
      statistics,
      chartData,
      chartOptions
    }
  }
}
</script>
