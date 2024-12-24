<template>
  <div>
    <!-- Search and Filter -->
    <div class="mb-4 flex space-x-2">
      <input type="text"
             v-model="search"
             placeholder="Search certificates..."
             class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white">
      <select v-model="filter"
              class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white">
        <option value="all">All</option>
        <option value="active">Active</option>
        <option value="expired">Expired</option>
        <option value="revoked">Revoked</option>
      </select>
    </div>

    <!-- Certificates List -->
    <div class="space-y-4">
      <div v-for="certificate in filteredCertificates"
           :key="certificate.id"
           class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
        <div class="flex justify-between items-start">
          <div>
            <h4 class="font-medium text-gray-900 dark:text-white">
              {{ certificate.recipient_name }}
            </h4>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              {{ certificate.certificate_number }}
            </p>
          </div>
          <span :class="getStatusClass(certificate.status)"
                class="px-2 py-1 text-xs rounded-full">
            {{ certificate.status }}
          </span>
        </div>
        
        <div class="mt-2 flex items-center text-sm text-gray-500 dark:text-gray-400">
          <calendar-icon class="h-4 w-4 mr-1" />
          <span>Issued: {{ formatDate(certificate.issue_date) }}</span>
          <span v-if="certificate.expiry_date" class="ml-4">
            <clock-icon class="h-4 w-4 mr-1 inline" />
            Expires: {{ formatDate(certificate.expiry_date) }}
          </span>
        </div>

        <div class="mt-3 flex space-x-2">
          <button @click="viewCertificate(certificate)"
                  class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm">
            View
          </button>
          <button @click="downloadCertificate(certificate)"
                  class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 text-sm">
            Download
          </button>
          <button v-if="canRevoke(certificate)"
                  @click="revokeCertificate(certificate)"
                  class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 text-sm">
            Revoke
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-4">
      <spinner class="h-6 w-6 text-blue-500" />
    </div>

    <!-- Empty State -->
    <div v-if="!loading && filteredCertificates.length === 0"
         class="text-center py-4 text-gray-500 dark:text-gray-400">
      No certificates found
    </div>

    <!-- Load More -->
    <div v-if="hasMore && !loading" class="text-center mt-4">
      <button @click="loadMore"
              class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
        Load More
      </button>
    </div>

    <!-- Revocation Modal -->
    <modal v-if="showRevocationModal" @close="showRevocationModal = false">
      <template #title>Revoke Certificate</template>
      <template #content>
        <div class="space-y-4">
          <p class="text-gray-600 dark:text-gray-300">
            Are you sure you want to revoke this certificate? This action cannot be undone.
          </p>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Revocation Reason
            </label>
            <select v-model="revocationReason"
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700">
              <option value="error">Error in Certificate</option>
              <option value="replacement">Replacement Issued</option>
              <option value="fraud">Fraudulent Use</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div v-if="revocationReason === 'other'">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Specify Reason
            </label>
            <textarea v-model="otherRevocationReason"
                      class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                      rows="3"></textarea>
          </div>
        </div>
      </template>
      <template #footer>
        <button @click="confirmRevocation"
                class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">
          Confirm Revocation
        </button>
        <button @click="showRevocationModal = false"
                class="ml-2 bg-gray-300 dark:bg-gray-600 px-4 py-2 rounded-md">
          Cancel
        </button>
      </template>
    </modal>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { format } from 'date-fns'

export default {
  name: 'RecentCertificatesWidget',

  props: {
    limit: {
      type: Number,
      default: 5
    }
  },

  setup(props) {
    const certificates = ref([])
    const loading = ref(false)
    const page = ref(1)
    const hasMore = ref(true)
    const search = ref('')
    const filter = ref('all')
    const showRevocationModal = ref(false)
    const selectedCertificate = ref(null)
    const revocationReason = ref('')
    const otherRevocationReason = ref('')

    const filteredCertificates = computed(() => {
      return certificates.value.filter(cert => {
        const matchesSearch = cert.recipient_name.toLowerCase().includes(search.value.toLowerCase()) ||
                            cert.certificate_number.toLowerCase().includes(search.value.toLowerCase())
        const matchesFilter = filter.value === 'all' || cert.status === filter.value
        return matchesSearch && matchesFilter
      })
    })

    async function fetchCertificates() {
      if (loading.value) return

      loading.value = true
      try {
        const response = await axios.get('/api/certificates/recent', {
          params: {
            page: page.value,
            limit: props.limit
          }
        })
        
        certificates.value = [...certificates.value, ...response.data.certificates]
        hasMore.value = response.data.hasMore
        page.value++
      } catch (error) {
        console.error('Failed to fetch certificates:', error)
      } finally {
        loading.value = false
      }
    }

    function formatDate(date) {
      return format(new Date(date), 'MMM d, yyyy')
    }

    function getStatusClass(status) {
      return {
        active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        expired: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        revoked: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
      }[status] || ''
    }

    function canRevoke(certificate) {
      return certificate.status === 'active'
    }

    function revokeCertificate(certificate) {
      selectedCertificate.value = certificate
      showRevocationModal.value = true
    }

    async function confirmRevocation() {
      try {
        await axios.post(`/api/certificates/${selectedCertificate.value.id}/revoke`, {
          reason: revocationReason.value === 'other' ? otherRevocationReason.value : revocationReason.value
        })

        // Update certificate status locally
        const certificate = certificates.value.find(c => c.id === selectedCertificate.value.id)
        if (certificate) {
          certificate.status = 'revoked'
        }

        showRevocationModal.value = false
        selectedCertificate.value = null
        revocationReason.value = ''
        otherRevocationReason.value = ''
      } catch (error) {
        console.error('Failed to revoke certificate:', error)
      }
    }

    onMounted(() => {
      fetchCertificates()
    })

    return {
      certificates,
      loading,
      hasMore,
      search,
      filter,
      showRevocationModal,
      revocationReason,
      otherRevocationReason,
      filteredCertificates,
      formatDate,
      getStatusClass,
      canRevoke,
      revokeCertificate,
      confirmRevocation,
      loadMore: fetchCertificates
    }
  }
}
</script>
