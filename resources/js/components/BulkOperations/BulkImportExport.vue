<template>
  <div class="bulk-operations">
    <!-- Tabs -->
    <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
      <nav class="-mb-px flex space-x-8">
        <button @click="activeTab = 'import'"
                :class="[
                  activeTab === 'import'
                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                    : 'border-transparent text-gray-500 dark:text-gray-400',
                  'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                ]">
          Import Certificates
        </button>
        <button @click="activeTab = 'export'"
                :class="[
                  activeTab === 'export'
                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                    : 'border-transparent text-gray-500 dark:text-gray-400',
                  'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                ]">
          Export Certificates
        </button>
      </nav>
    </div>

    <!-- Import Section -->
    <div v-if="activeTab === 'import'" class="space-y-6">
      <!-- Template Selection -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
          Certificate Template
        </label>
        <select v-model="selectedTemplate"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700">
          <option v-for="template in templates"
                  :key="template.id"
                  :value="template.id">
            {{ template.name }}
          </option>
        </select>
      </div>

      <!-- File Upload -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
          CSV File
        </label>
        <div class="flex items-center justify-center w-full">
          <label class="flex flex-col w-full h-32 border-2 border-dashed rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                 :class="[
                   isDragging ? 'border-blue-500' : 'border-gray-300 dark:border-gray-600'
                 ]"
                 @dragenter.prevent="isDragging = true"
                 @dragleave.prevent="isDragging = false"
                 @dragover.prevent
                 @drop.prevent="handleFileDrop">
            <div class="flex flex-col items-center justify-center pt-5 pb-6">
              <upload-icon class="w-8 h-8 mb-4 text-gray-500 dark:text-gray-400" />
              <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">
                <span class="font-semibold">Click to upload</span> or drag and drop
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                CSV files only (max. 10MB)
              </p>
            </div>
            <input type="file"
                   ref="fileInput"
                   class="hidden"
                   accept=".csv"
                   @change="handleFileSelect">
          </label>
        </div>
        <div v-if="selectedFile" class="flex items-center space-x-2">
          <document-icon class="h-5 w-5 text-gray-400" />
          <span class="text-sm text-gray-600 dark:text-gray-300">
            {{ selectedFile.name }}
          </span>
          <button @click="removeFile"
                  class="text-red-600 hover:text-red-800">
            <x-icon class="h-4 w-4" />
          </button>
        </div>
      </div>

      <!-- Field Mapping -->
      <div v-if="selectedFile && csvHeaders.length" class="space-y-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
          Map CSV Fields
        </h3>
        <div class="grid gap-4">
          <div v-for="field in templateFields"
               :key="field.name"
               class="grid grid-cols-2 gap-4 items-center">
            <div class="text-sm text-gray-700 dark:text-gray-300">
              {{ field.label }}
              <span v-if="field.required" class="text-red-500">*</span>
            </div>
            <select v-model="fieldMapping[field.name]"
                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700">
              <option value="">-- Select CSV Field --</option>
              <option v-for="header in csvHeaders"
                      :key="header"
                      :value="header">
                {{ header }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <!-- Import Options -->
      <div class="space-y-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
          Import Options
        </h3>
        <div class="space-y-2">
          <label class="flex items-center">
            <input type="checkbox"
                   v-model="options.skipHeader"
                   class="rounded border-gray-300 dark:border-gray-600">
            <span class="ml-2 text-sm text-gray-600 dark:text-gray-300">
              Skip header row
            </span>
          </label>
          <label class="flex items-center">
            <input type="checkbox"
                   v-model="options.validateOnly"
                   class="rounded border-gray-300 dark:border-gray-600">
            <span class="ml-2 text-sm text-gray-600 dark:text-gray-300">
              Validate only (no import)
            </span>
          </label>
        </div>
      </div>

      <!-- Import Button -->
      <div class="flex justify-end">
        <button @click="startImport"
                :disabled="!canImport"
                class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:opacity-50">
          {{ options.validateOnly ? 'Validate' : 'Start Import' }}
        </button>
      </div>
    </div>

    <!-- Export Section -->
    <div v-else class="space-y-6">
      <!-- Export Filters -->
      <div class="space-y-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
          Export Filters
        </h3>
        <div class="grid gap-4 sm:grid-cols-2">
          <!-- Date Range -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Date Range
            </label>
            <div class="mt-1 grid grid-cols-2 gap-2">
              <input type="date"
                     v-model="exportFilters.dateFrom"
                     class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700">
              <input type="date"
                     v-model="exportFilters.dateTo"
                     class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700">
            </div>
          </div>

          <!-- Template Filter -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Template
            </label>
            <select v-model="exportFilters.templateId"
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700">
              <option value="">All Templates</option>
              <option v-for="template in templates"
                      :key="template.id"
                      :value="template.id">
                {{ template.name }}
              </option>
            </select>
          </div>

          <!-- Status Filter -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Status
            </label>
            <select v-model="exportFilters.status"
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700">
              <option value="">All Statuses</option>
              <option value="active">Active</option>
              <option value="expired">Expired</option>
              <option value="revoked">Revoked</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Export Options -->
      <div class="space-y-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
          Export Options
        </h3>
        <div class="space-y-2">
          <label class="flex items-center">
            <input type="checkbox"
                   v-model="exportOptions.includeHeader"
                   class="rounded border-gray-300 dark:border-gray-600">
            <span class="ml-2 text-sm text-gray-600 dark:text-gray-300">
              Include header row
            </span>
          </label>
          <label class="flex items-center">
            <input type="checkbox"
                   v-model="exportOptions.includeMetadata"
                   class="rounded border-gray-300 dark:border-gray-600">
            <span class="ml-2 text-sm text-gray-600 dark:text-gray-300">
              Include metadata (created date, status changes, etc.)
            </span>
          </label>
        </div>
      </div>

      <!-- Export Button -->
      <div class="flex justify-end">
        <button @click="startExport"
                :disabled="!canExport"
                class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:opacity-50">
          Export Certificates
        </button>
      </div>
    </div>

    <!-- Progress Modal -->
    <modal v-if="showProgress" @close="cancelOperation">
      <template #title>
        {{ activeTab === 'import' ? 'Import Progress' : 'Export Progress' }}
      </template>
      <template #content>
        <div class="space-y-4">
          <div class="relative pt-1">
            <div class="flex mb-2 items-center justify-between">
              <div>
                <span class="text-xs font-semibold inline-block text-blue-600">
                  {{ Math.round(progress) }}%
                </span>
              </div>
              <div class="text-right">
                <span class="text-xs font-semibold inline-block">
                  {{ processedItems }}/{{ totalItems }}
                </span>
              </div>
            </div>
            <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-blue-200">
              <div :style="{ width: `${progress}%` }"
                   class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-blue-500">
              </div>
            </div>
          </div>
          <div v-if="errors.length" class="space-y-2">
            <h4 class="text-sm font-medium text-red-600">Errors</h4>
            <ul class="list-disc pl-5 space-y-1">
              <li v-for="error in errors"
                  :key="error.row"
                  class="text-sm text-red-600">
                Row {{ error.row }}: {{ error.message }}
              </li>
            </ul>
          </div>
        </div>
      </template>
      <template #footer>
        <button @click="cancelOperation"
                class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md">
          {{ operationComplete ? 'Close' : 'Cancel' }}
        </button>
      </template>
    </modal>
  </div>
</template>

<script>
import { ref, computed } from 'vue'
import axios from 'axios'
import { useToast } from '@/composables/useToast'

export default {
  name: 'BulkImportExport',

  setup() {
    const toast = useToast()
    const activeTab = ref('import')
    const selectedTemplate = ref(null)
    const selectedFile = ref(null)
    const isDragging = ref(false)
    const csvHeaders = ref([])
    const templates = ref([])
    const fieldMapping = ref({})
    const showProgress = ref(false)
    const progress = ref(0)
    const processedItems = ref(0)
    const totalItems = ref(0)
    const errors = ref([])
    const operationComplete = ref(false)

    const options = ref({
      skipHeader: true,
      validateOnly: false
    })

    const exportFilters = ref({
      dateFrom: '',
      dateTo: '',
      templateId: '',
      status: ''
    })

    const exportOptions = ref({
      includeHeader: true,
      includeMetadata: false
    })

    const templateFields = computed(() => {
      if (!selectedTemplate.value) return []
      const template = templates.value.find(t => t.id === selectedTemplate.value)
      return template ? template.fields : []
    })

    const canImport = computed(() => {
      return selectedTemplate.value && 
             selectedFile.value && 
             Object.keys(fieldMapping.value).length > 0
    })

    const canExport = computed(() => {
      return true // Add any validation logic if needed
    })

    async function fetchTemplates() {
      try {
        const response = await axios.get('/api/templates')
        templates.value = response.data
      } catch (error) {
        console.error('Failed to fetch templates:', error)
        toast.error('Failed to load templates')
      }
    }

    function handleFileDrop(event) {
      isDragging.value = false
      const file = event.dataTransfer.files[0]
      if (file && file.type === 'text/csv') {
        handleFile(file)
      } else {
        toast.error('Please upload a CSV file')
      }
    }

    function handleFileSelect(event) {
      const file = event.target.files[0]
      if (file) {
        handleFile(file)
      }
    }

    function handleFile(file) {
      selectedFile.value = file
      // Parse CSV headers
      const reader = new FileReader()
      reader.onload = (e) => {
        const firstLine = e.target.result.split('\n')[0]
        csvHeaders.value = firstLine.split(',').map(header => header.trim())
      }
      reader.readAsText(file)
    }

    function removeFile() {
      selectedFile.value = null
      csvHeaders.value = []
      fieldMapping.value = {}
    }

    async function startImport() {
      showProgress.value = true
      operationComplete.value = false
      errors.value = []
      progress.value = 0
      processedItems.value = 0

      const formData = new FormData()
      formData.append('file', selectedFile.value)
      formData.append('templateId', selectedTemplate.value)
      formData.append('fieldMapping', JSON.stringify(fieldMapping.value))
      formData.append('options', JSON.stringify(options.value))

      try {
        const response = await axios.post('/api/certificates/import', formData, {
          onUploadProgress: (progressEvent) => {
            const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total)
            progress.value = percentCompleted
          }
        })

        if (response.data.errors) {
          errors.value = response.data.errors
        }

        processedItems.value = response.data.processed
        totalItems.value = response.data.total
        operationComplete.value = true

        if (errors.value.length === 0) {
          toast.success('Import completed successfully')
        } else {
          toast.warning(`Import completed with ${errors.value.length} errors`)
        }
      } catch (error) {
        console.error('Import failed:', error)
        toast.error('Import failed')
        showProgress.value = false
      }
    }

    async function startExport() {
      showProgress.value = true
      operationComplete.value = false
      progress.value = 0

      try {
        const response = await axios.post('/api/certificates/export', {
          filters: exportFilters.value,
          options: exportOptions.value
        }, {
          responseType: 'blob',
          onDownloadProgress: (progressEvent) => {
            const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total)
            progress.value = percentCompleted
          }
        })

        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `certificates-${new Date().toISOString()}.csv`)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)

        operationComplete.value = true
        toast.success('Export completed successfully')
      } catch (error) {
        console.error('Export failed:', error)
        toast.error('Export failed')
      } finally {
        showProgress.value = false
      }
    }

    function cancelOperation() {
      if (!operationComplete.value) {
        // Implement cancellation logic if needed
      }
      showProgress.value = false
    }

    // Initialize
    fetchTemplates()

    return {
      activeTab,
      selectedTemplate,
      selectedFile,
      isDragging,
      csvHeaders,
      templates,
      fieldMapping,
      options,
      exportFilters,
      exportOptions,
      showProgress,
      progress,
      processedItems,
      totalItems,
      errors,
      operationComplete,
      templateFields,
      canImport,
      canExport,
      handleFileDrop,
      handleFileSelect,
      removeFile,
      startImport,
      startExport,
      cancelOperation
    }
  }
}
</script>
