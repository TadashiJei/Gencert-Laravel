<template>
  <div class="template-builder">
    <div class="flex h-screen">
      <!-- Tools Panel -->
      <div class="w-64 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 p-4">
        <h3 class="text-lg font-semibold mb-4 dark:text-white">Elements</h3>
        <div class="space-y-2">
          <div v-for="element in elements" 
               :key="element.type"
               class="p-2 bg-gray-100 dark:bg-gray-700 rounded cursor-move"
               draggable="true"
               @dragstart="onDragStart($event, element)">
            <div class="flex items-center space-x-2">
              <component :is="element.icon" class="w-5 h-5" />
              <span class="dark:text-white">{{ element.label }}</span>
            </div>
          </div>
        </div>

        <!-- Properties Panel -->
        <div v-if="selectedElement" class="mt-8">
          <h3 class="text-lg font-semibold mb-4 dark:text-white">Properties</h3>
          <div class="space-y-4">
            <!-- Text Properties -->
            <template v-if="selectedElement.type === 'text'">
              <div>
                <label class="block text-sm font-medium dark:text-gray-300">Font Size</label>
                <input type="number" v-model="selectedElement.fontSize" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700">
              </div>
              <div>
                <label class="block text-sm font-medium dark:text-gray-300">Font Family</label>
                <select v-model="selectedElement.fontFamily" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                  <option v-for="font in fonts" :key="font" :value="font">{{ font }}</option>
                </select>
              </div>
            </template>

            <!-- Image Properties -->
            <template v-if="selectedElement.type === 'image'">
              <div>
                <label class="block text-sm font-medium dark:text-gray-300">Width</label>
                <input type="number" v-model="selectedElement.width" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700">
              </div>
              <div>
                <label class="block text-sm font-medium dark:text-gray-300">Height</label>
                <input type="number" v-model="selectedElement.height" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700">
              </div>
            </template>
          </div>
        </div>
      </div>

      <!-- Canvas Area -->
      <div class="flex-1 relative bg-gray-50 dark:bg-gray-900">
        <div class="absolute inset-0 overflow-auto">
          <!-- Certificate Preview -->
          <div ref="canvas" 
               class="relative mx-auto my-8 bg-white dark:bg-gray-800 shadow-lg"
               :style="canvasStyle"
               @dragover.prevent
               @drop="onDrop">
            <component v-for="element in canvasElements"
                      :key="element.id"
                      :is="element.component"
                      v-bind="element"
                      @click="selectElement(element)"
                      @update:position="updateElementPosition(element.id, $event)"
                      @update:size="updateElementSize(element.id, $event)"
                      class="absolute" />
          </div>
        </div>

        <!-- Live Preview Toggle -->
        <button @click="togglePreview" 
                class="fixed bottom-4 right-4 bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
          {{ isPreviewMode ? 'Edit Mode' : 'Preview' }}
        </button>
      </div>
    </div>

    <!-- Live Preview Modal -->
    <transition name="fade">
      <div v-if="isPreviewMode" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
        <div class="bg-white dark:bg-gray-800 p-8 rounded-lg max-w-4xl w-full mx-4">
          <h2 class="text-2xl font-bold mb-4 dark:text-white">Certificate Preview</h2>
          <div class="relative" :style="previewStyle">
            <!-- Render certificate with sample data -->
            <component v-for="element in previewElements"
                      :key="element.id"
                      :is="element.component"
                      v-bind="element"
                      class="absolute" />
          </div>
          <div class="mt-4 flex justify-end">
            <button @click="togglePreview" 
                    class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
              Close Preview
            </button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { v4 as uuidv4 } from 'uuid'
import TextElement from './elements/TextElement.vue'
import ImageElement from './elements/ImageElement.vue'
import QrElement from './elements/QrElement.vue'
import SignatureElement from './elements/SignatureElement.vue'

export default {
  name: 'TemplateBuilder',
  
  components: {
    TextElement,
    ImageElement,
    QrElement,
    SignatureElement
  },

  props: {
    initialTemplate: {
      type: Object,
      default: () => ({})
    },
    orientation: {
      type: String,
      default: 'landscape'
    },
    paperSize: {
      type: String,
      default: 'a4'
    }
  },

  setup(props) {
    const canvas = ref(null)
    const selectedElement = ref(null)
    const isPreviewMode = ref(false)
    const canvasElements = ref([])

    const elements = [
      { type: 'text', label: 'Text', component: 'TextElement', icon: 'TextIcon' },
      { type: 'image', label: 'Image', component: 'ImageElement', icon: 'ImageIcon' },
      { type: 'qr', label: 'QR Code', component: 'QrElement', icon: 'QrIcon' },
      { type: 'signature', label: 'Signature', component: 'SignatureElement', icon: 'SignatureIcon' }
    ]

    const fonts = [
      'Arial',
      'Times New Roman',
      'Helvetica',
      'Georgia',
      'Courier New'
    ]

    const canvasStyle = computed(() => ({
      width: props.orientation === 'landscape' ? '297mm' : '210mm',
      height: props.orientation === 'landscape' ? '210mm' : '297mm',
      position: 'relative'
    }))

    const previewStyle = computed(() => ({
      ...canvasStyle.value,
      transform: 'scale(0.8)',
      transformOrigin: 'top center'
    }))

    const previewElements = computed(() => 
      canvasElements.value.map(element => ({
        ...element,
        content: element.type === 'text' ? 
          replacePlaceholders(element.content) : element.content
      }))
    )

    function onDragStart(event, element) {
      event.dataTransfer.setData('element', JSON.stringify(element))
    }

    function onDrop(event) {
      const element = JSON.parse(event.dataTransfer.getData('element'))
      const rect = canvas.value.getBoundingClientRect()
      const x = event.clientX - rect.left
      const y = event.clientY - rect.top

      addElement(element, { x, y })
    }

    function addElement(element, position) {
      canvasElements.value.push({
        id: uuidv4(),
        ...element,
        x: position.x,
        y: position.y,
        content: element.type === 'text' ? 'Double click to edit' : null
      })
    }

    function selectElement(element) {
      selectedElement.value = element
    }

    function updateElementPosition(id, position) {
      const element = canvasElements.value.find(el => el.id === id)
      if (element) {
        element.x = position.x
        element.y = position.y
      }
    }

    function updateElementSize(id, size) {
      const element = canvasElements.value.find(el => el.id === id)
      if (element) {
        element.width = size.width
        element.height = size.height
      }
    }

    function togglePreview() {
      isPreviewMode.value = !isPreviewMode.value
    }

    function replacePlaceholders(text) {
      // Replace placeholders with sample data
      return text.replace(/\{(\w+)\}/g, (match, placeholder) => {
        const sampleData = {
          name: 'John Doe',
          date: new Date().toLocaleDateString(),
          course: 'Sample Course',
          // Add more sample data as needed
        }
        return sampleData[placeholder] || match
      })
    }

    onMounted(() => {
      if (props.initialTemplate.elements) {
        canvasElements.value = props.initialTemplate.elements
      }
    })

    return {
      canvas,
      elements,
      fonts,
      selectedElement,
      canvasElements,
      isPreviewMode,
      canvasStyle,
      previewStyle,
      previewElements,
      onDragStart,
      onDrop,
      selectElement,
      updateElementPosition,
      updateElementSize,
      togglePreview
    }
  }
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
