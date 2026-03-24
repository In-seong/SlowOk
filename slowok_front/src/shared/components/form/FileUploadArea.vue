<script setup lang="ts">
import { ref, computed } from 'vue'
import { adminApi } from '@shared/api/adminApi'
import { useToastStore } from '@shared/stores/toastStore'

interface Props {
  modelValue: string
  accept?: string
  uploadType?: 'video' | 'image' | 'audio' | 'thumbnail'
  label?: string
  maxSizeMB?: number
}

const props = withDefaults(defineProps<Props>(), {
  accept: 'image/*',
  uploadType: 'image',
  label: '',
  maxSizeMB: 5,
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const toast = useToastStore()
const fileInputRef = ref<HTMLInputElement | null>(null)
const isDragging = ref(false)
const uploading = ref(false)
const progress = ref(0)
const errorMsg = ref('')
const uploadedPath = ref('')
const uploadedFileName = ref('')

const hasFile = computed(() => props.modelValue.length > 0)

const isImage = computed(() =>
  props.uploadType === 'image' || props.uploadType === 'thumbnail'
)

const isAudio = computed(() => props.uploadType === 'audio')

const isVideo = computed(() => props.uploadType === 'video')

const maxSizeBytes = computed(() => props.maxSizeMB * 1024 * 1024)

function openFilePicker(): void {
  fileInputRef.value?.click()
}

function handleFileInput(event: Event): void {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (file) {
    processFile(file)
  }
  input.value = ''
}

function handleDragOver(event: DragEvent): void {
  event.preventDefault()
  isDragging.value = true
}

function handleDragLeave(): void {
  isDragging.value = false
}

function handleDrop(event: DragEvent): void {
  event.preventDefault()
  isDragging.value = false
  const file = event.dataTransfer?.files?.[0]
  if (file) {
    processFile(file)
  }
}

function validateFile(file: File): string | null {
  if (file.size > maxSizeBytes.value) {
    return `파일 크기가 ${props.maxSizeMB}MB를 초과합니다. (현재: ${(file.size / 1024 / 1024).toFixed(1)}MB)`
  }
  if (props.accept && props.accept !== '*') {
    const acceptTypes = props.accept.split(',').map((t) => t.trim())
    const fileType = file.type
    const isAccepted = acceptTypes.some((accepted) => {
      if (accepted.endsWith('/*')) {
        const category = accepted.replace('/*', '')
        return fileType.startsWith(category)
      }
      return fileType === accepted
    })
    if (!isAccepted) {
      return '허용되지 않는 파일 형식입니다.'
    }
  }
  return null
}

async function processFile(file: File): Promise<void> {
  const validationError = validateFile(file)
  if (validationError) {
    errorMsg.value = validationError
    return
  }

  errorMsg.value = ''
  uploading.value = true
  progress.value = 0
  uploadedFileName.value = file.name

  // Simulate progress since axios doesn't expose it from adminApi wrapper
  const progressInterval = setInterval(() => {
    if (progress.value < 90) {
      progress.value += Math.random() * 15
      if (progress.value > 90) progress.value = 90
    }
  }, 200)

  try {
    const res = await adminApi.uploadFile(file, props.uploadType)
    clearInterval(progressInterval)
    progress.value = 100

    if (res.data.success) {
      const data = res.data.data
      uploadedPath.value = data.path
      uploadedFileName.value = data.original_name
      emit('update:modelValue', data.url)
    } else {
      errorMsg.value = res.data.message || '업로드에 실패했습니다.'
    }
  } catch (e: any) {
    clearInterval(progressInterval)
    errorMsg.value = e.response?.data?.message || '업로드 중 오류가 발생했습니다.'
  } finally {
    uploading.value = false
  }
}

async function handleDelete(): Promise<void> {
  if (!uploadedPath.value) {
    emit('update:modelValue', '')
    return
  }

  try {
    await adminApi.deleteFile(uploadedPath.value)
  } catch (e: any) {
    toast.error(e.response?.data?.message || '파일 삭제에 실패했습니다.')
  }

  uploadedPath.value = ''
  uploadedFileName.value = ''
  progress.value = 0
  emit('update:modelValue', '')
}
</script>

<template>
  <div>
    <label
      v-if="props.label"
      class="block text-[13px] font-medium text-[#555] mb-1.5"
    >
      {{ props.label }}
    </label>

    <!-- Upload progress -->
    <div
      v-if="uploading"
      class="border-2 border-[#E8E8E8] rounded-[12px] p-6"
    >
      <div class="flex items-center gap-3 mb-3">
        <svg class="w-5 h-5 text-[#4CAF50] animate-spin" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <span class="text-[14px] text-[#555]">{{ uploadedFileName }} 업로드 중...</span>
      </div>
      <div class="w-full bg-[#E8E8E8] rounded-full h-2 overflow-hidden">
        <div
          class="bg-[#4CAF50] h-2 rounded-full transition-all duration-300"
          :style="{ width: `${Math.min(progress, 100)}%` }"
        />
      </div>
      <p class="text-[12px] text-[#888] mt-1.5 text-right">
        {{ Math.round(Math.min(progress, 100)) }}%
      </p>
    </div>

    <!-- Preview area (file uploaded) -->
    <div
      v-else-if="hasFile"
      class="bg-[#F8F8F8] rounded-[12px] p-3"
    >
      <!-- Image / Thumbnail preview -->
      <div v-if="isImage" class="relative">
        <img
          :src="modelValue"
          :alt="uploadedFileName || '업로드된 이미지'"
          class="w-full max-h-[240px] object-contain rounded-[8px] bg-white"
        />
        <button
          type="button"
          @click="handleDelete"
          class="absolute top-2 right-2 w-7 h-7 flex items-center justify-center rounded-full bg-white shadow-md text-[#F44336] hover:bg-red-50 transition-colors"
          aria-label="파일 삭제"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>

      <!-- Audio preview -->
      <div v-else-if="isAudio" class="flex items-center gap-3">
        <div class="flex-1 min-w-0">
          <audio
            controls
            :src="modelValue"
            class="w-full"
          />
          <p class="text-[12px] text-[#888] mt-1 truncate">
            {{ uploadedFileName || '오디오 파일' }}
          </p>
        </div>
        <button
          type="button"
          @click="handleDelete"
          class="flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-full text-[#F44336] hover:bg-red-50 transition-colors"
          aria-label="파일 삭제"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>

      <!-- Video preview (filename only) -->
      <div v-else-if="isVideo" class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-[8px] bg-blue-50 flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 text-blue-500" viewBox="0 0 24 24" fill="currentColor">
            <path d="M8 5v14l11-7z" />
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-[14px] text-[#333] font-medium truncate">
            {{ uploadedFileName || '동영상 파일' }}
          </p>
          <p class="text-[12px] text-[#888]">동영상 업로드 완료</p>
        </div>
        <button
          type="button"
          @click="handleDelete"
          class="flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-full text-[#F44336] hover:bg-red-50 transition-colors"
          aria-label="파일 삭제"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Drop zone (no file) -->
    <div
      v-else
      class="border-2 border-dashed rounded-[12px] p-6 text-center cursor-pointer transition-colors"
      :class="isDragging
        ? 'border-[#4CAF50] bg-green-50'
        : 'border-[#E8E8E8] hover:border-[#4CAF50]'"
      @click="openFilePicker"
      @dragover="handleDragOver"
      @dragleave="handleDragLeave"
      @drop="handleDrop"
      role="button"
      tabindex="0"
      :aria-label="props.label ? `${props.label} 파일 업로드` : '파일 업로드'"
      @keydown.enter="openFilePicker"
      @keydown.space.prevent="openFilePicker"
    >
      <svg
        class="w-8 h-8 mx-auto mb-2 text-[#B0B0B0]"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.5"
      >
        <path d="M12 16V4m0 0L8 8m4-4l4 4" stroke-linecap="round" stroke-linejoin="round" />
        <path d="M20 16v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
      <p class="text-[14px] text-[#888] mb-1">
        파일을 드래그하거나 클릭하여 업로드
      </p>
      <p class="text-[12px] text-[#B0B0B0]">
        최대 {{ props.maxSizeMB }}MB
      </p>
    </div>

    <!-- Error message -->
    <p
      v-if="errorMsg"
      class="text-[12px] text-[#F44336] mt-1.5"
      role="alert"
    >
      {{ errorMsg }}
    </p>

    <!-- Hidden file input -->
    <input
      ref="fileInputRef"
      type="file"
      :accept="props.accept"
      class="hidden"
      @change="handleFileInput"
    />
  </div>
</template>
