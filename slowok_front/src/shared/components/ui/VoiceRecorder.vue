<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { voiceRecordingApi } from '@shared/api/voiceRecordingApi'
import { useToastStore } from '@shared/stores/toastStore'
import type { VoiceRecording } from '@shared/types'

interface Props {
  assignableType: 'learning_content' | 'challenge'
  assignableId: number
}

const props = defineProps<Props>()

// ===== State =====
const recordings = ref<VoiceRecording[]>([])
const toast = useToastStore()
const loading = ref(false)
const uploading = ref(false)
const error = ref<string | null>(null)

// 녹음 관련 상태
const isRecording = ref(false)
const recordingTime = ref(0)
const mediaRecorder = ref<MediaRecorder | null>(null)
const audioChunks = ref<Blob[]>([])
const recordedBlob = ref<Blob | null>(null)
const recordedUrl = ref<string | null>(null)
const memo = ref('')

// 재생 관련 상태
const playingId = ref<number | null>(null)
const currentAudio = ref<HTMLAudioElement | null>(null)
const previewAudio = ref<HTMLAudioElement | null>(null)
const isPreviewPlaying = ref(false)

// 타이머
let timerInterval: ReturnType<typeof setInterval> | null = null

// ===== Computed =====
const formattedTime = computed(() => {
  const min = Math.floor(recordingTime.value / 60)
  const sec = recordingTime.value % 60
  return `${String(min).padStart(2, '0')}:${String(sec).padStart(2, '0')}`
})

const hasRecordedAudio = computed(() => recordedBlob.value !== null)

// ===== Actions =====
async function fetchRecordings() {
  loading.value = true
  error.value = null
  try {
    const res = await voiceRecordingApi.getRecordings({
      assignable_type: props.assignableType,
      assignable_id: props.assignableId,
    })
    if (res.data.success) {
      recordings.value = res.data.data
    }
  } catch (e: any) {
    error.value = e.response?.data?.message || '녹음 목록을 불러오는데 실패했습니다.'
  } finally {
    loading.value = false
  }
}

async function startRecording() {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
    const recorder = new MediaRecorder(stream, { mimeType: 'audio/webm' })

    audioChunks.value = []

    recorder.ondataavailable = (event: BlobEvent) => {
      if (event.data.size > 0) {
        audioChunks.value.push(event.data)
      }
    }

    recorder.onstop = () => {
      const blob = new Blob(audioChunks.value, { type: 'audio/webm' })
      recordedBlob.value = blob
      recordedUrl.value = URL.createObjectURL(blob)
      stream.getTracks().forEach((track) => track.stop())
    }

    mediaRecorder.value = recorder
    recorder.start()
    isRecording.value = true
    recordingTime.value = 0

    timerInterval = setInterval(() => {
      recordingTime.value++
    }, 1000)
  } catch (e: any) {
    toast.error(e.response?.data?.message || '마이크 접근 권한이 필요합니다.')
    error.value = '마이크 접근 권한이 필요합니다.'
  }
}

function stopRecording() {
  if (mediaRecorder.value && mediaRecorder.value.state === 'recording') {
    mediaRecorder.value.stop()
    isRecording.value = false
    if (timerInterval) {
      clearInterval(timerInterval)
      timerInterval = null
    }
  }
}

function togglePreview() {
  if (!recordedUrl.value) return

  if (isPreviewPlaying.value && previewAudio.value) {
    previewAudio.value.pause()
    previewAudio.value.currentTime = 0
    isPreviewPlaying.value = false
    return
  }

  const audio = new Audio(recordedUrl.value)
  previewAudio.value = audio
  isPreviewPlaying.value = true

  audio.onended = () => {
    isPreviewPlaying.value = false
  }

  audio.play()
}

function cancelRecording() {
  if (recordedUrl.value) {
    URL.revokeObjectURL(recordedUrl.value)
  }
  recordedBlob.value = null
  recordedUrl.value = null
  recordingTime.value = 0
  memo.value = ''
  isPreviewPlaying.value = false
  if (previewAudio.value) {
    previewAudio.value.pause()
    previewAudio.value = null
  }
}

async function saveRecording() {
  if (!recordedBlob.value) return

  uploading.value = true
  error.value = null
  try {
    const formData = new FormData()
    formData.append('file', recordedBlob.value, 'recording.webm')
    formData.append('assignable_type', props.assignableType)
    formData.append('assignable_id', String(props.assignableId))
    formData.append('duration', String(recordingTime.value))
    if (memo.value.trim()) {
      formData.append('memo', memo.value.trim())
    }

    const res = await voiceRecordingApi.uploadRecording(formData)
    if (res.data.success) {
      cancelRecording()
      await fetchRecordings()
    }
  } catch (e: any) {
    error.value = e.response?.data?.message || '녹음 저장에 실패했습니다.'
  } finally {
    uploading.value = false
  }
}

async function deleteRecording(id: number) {
  if (!confirm('이 녹음을 삭제하시겠습니까?')) return

  try {
    const res = await voiceRecordingApi.deleteRecording(id)
    if (res.data.success) {
      recordings.value = recordings.value.filter((r) => r.recording_id !== id)
      if (playingId.value === id && currentAudio.value) {
        currentAudio.value.pause()
        currentAudio.value = null
        playingId.value = null
      }
    }
  } catch (e: any) {
    error.value = e.response?.data?.message || '녹음 삭제에 실패했습니다.'
  }
}

function playRecording(recording: VoiceRecording) {
  if (playingId.value === recording.recording_id && currentAudio.value) {
    currentAudio.value.pause()
    currentAudio.value = null
    playingId.value = null
    return
  }

  if (currentAudio.value) {
    currentAudio.value.pause()
  }

  const audio = new Audio(recording.file_url)
  currentAudio.value = audio
  playingId.value = recording.recording_id

  audio.onended = () => {
    playingId.value = null
    currentAudio.value = null
  }

  audio.play()
}

function formatDuration(seconds: number | null): string {
  if (seconds === null || seconds === undefined) return '--:--'
  const min = Math.floor(seconds / 60)
  const sec = seconds % 60
  return `${String(min).padStart(2, '0')}:${String(sec).padStart(2, '0')}`
}

function formatRecordingDate(dateStr?: string): string {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('ko-KR')
}

onMounted(() => {
  fetchRecordings()
})

onBeforeUnmount(() => {
  if (timerInterval) clearInterval(timerInterval)
  if (currentAudio.value) currentAudio.value.pause()
  if (previewAudio.value) previewAudio.value.pause()
  if (recordedUrl.value) URL.revokeObjectURL(recordedUrl.value)
  if (mediaRecorder.value && mediaRecorder.value.state === 'recording') {
    mediaRecorder.value.stop()
  }
})
</script>

<template>
  <div class="space-y-4">
    <!-- 녹음 컨트롤 -->
    <div class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-5">
      <p class="text-[14px] font-semibold text-[#333] mb-3">음성 녹음</p>

      <!-- 에러 메시지 -->
      <div v-if="error" class="mb-3 p-3 bg-red-50 rounded-[10px] text-[12px] text-red-600">
        {{ error }}
      </div>

      <!-- 녹음 중 -->
      <div v-if="isRecording" class="flex flex-col items-center gap-3">
        <div class="w-16 h-16 rounded-full bg-red-500 flex items-center justify-center animate-pulse">
          <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z" />
            <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z" />
          </svg>
        </div>
        <p class="text-[20px] font-bold text-red-500 tabular-nums">{{ formattedTime }}</p>
        <p class="text-[12px] text-[#888]">녹음 중...</p>
        <button
          class="mt-1 px-6 py-2.5 bg-[#333] text-white text-[13px] font-semibold rounded-[10px] active:scale-[0.98] transition-all"
          @click="stopRecording"
        >
          녹음 중지
        </button>
      </div>

      <!-- 녹음 완료 (미리듣기 + 저장) -->
      <div v-else-if="hasRecordedAudio" class="flex flex-col gap-3">
        <div class="flex items-center gap-3 p-3 bg-[#F0F7F0] rounded-[10px]">
          <button
            class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
            :class="isPreviewPlaying ? 'bg-[#FF9800]' : 'bg-[#4CAF50]'"
            @click="togglePreview"
          >
            <svg v-if="!isPreviewPlaying" class="w-5 h-5 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24">
              <path d="M8 5v14l11-7z" />
            </svg>
            <svg v-else class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
              <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z" />
            </svg>
          </button>
          <div class="flex-1">
            <p class="text-[13px] text-[#333]">녹음 완료</p>
            <p class="text-[12px] text-[#888]">{{ formattedTime }}</p>
          </div>
        </div>

        <!-- 메모 입력 -->
        <input
          v-model="memo"
          type="text"
          placeholder="메모 (선택사항)"
          maxlength="500"
          class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-[10px] text-[13px] text-[#333] placeholder-[#BBBBBB] outline-none focus:border-[#4CAF50] transition-colors"
        />

        <!-- 저장 / 취소 버튼 -->
        <div class="flex gap-2">
          <button
            class="flex-1 py-2.5 bg-white border border-[#E0E0E0] text-[#555] text-[13px] font-semibold rounded-[10px] active:scale-[0.98] transition-all"
            @click="cancelRecording"
          >
            취소
          </button>
          <button
            class="flex-1 py-2.5 bg-[#4CAF50] text-white text-[13px] font-semibold rounded-[10px] active:scale-[0.98] transition-all"
            :class="{ 'opacity-50 cursor-not-allowed': uploading }"
            :disabled="uploading"
            @click="saveRecording"
          >
            {{ uploading ? '저장 중...' : '저장' }}
          </button>
        </div>
      </div>

      <!-- 기본 상태 (녹음 시작 버튼) -->
      <div v-else class="flex flex-col items-center gap-3">
        <button
          class="w-16 h-16 rounded-full bg-[#4CAF50] flex items-center justify-center active:scale-[0.95] transition-all hover:bg-[#388E3C]"
          @click="startRecording"
        >
          <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z" />
            <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z" />
          </svg>
        </button>
        <p class="text-[12px] text-[#888]">눌러서 녹음 시작</p>
      </div>
    </div>

    <!-- 녹음 목록 -->
    <div v-if="recordings.length > 0" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-5">
      <p class="text-[14px] font-semibold text-[#333] mb-3">내 녹음 ({{ recordings.length }})</p>

      <div class="space-y-2">
        <div
          v-for="recording in recordings"
          :key="recording.recording_id"
          class="bg-[#F8F9FA] rounded-[10px] overflow-hidden"
        >
          <div class="flex items-center gap-3 p-3">
            <!-- 재생 버튼 -->
            <button
              class="w-9 h-9 rounded-full flex items-center justify-center shrink-0"
              :class="playingId === recording.recording_id ? 'bg-[#FF9800]' : 'bg-[#4CAF50]'"
              @click="playRecording(recording)"
            >
              <svg v-if="playingId !== recording.recording_id" class="w-4 h-4 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M8 5v14l11-7z" />
              </svg>
              <svg v-else class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
                <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z" />
              </svg>
            </button>

            <!-- 정보 -->
            <div class="flex-1 min-w-0">
              <p v-if="recording.memo" class="text-[13px] text-[#333] truncate">{{ recording.memo }}</p>
              <div class="flex items-center gap-2 text-[11px] text-[#888]">
                <span>{{ formatDuration(recording.duration) }}</span>
                <span class="text-[#E0E0E0]">|</span>
                <span>{{ formatRecordingDate(recording.created_at) }}</span>
              </div>
            </div>

            <!-- 삭제 버튼 -->
            <button
              class="w-8 h-8 rounded-full flex items-center justify-center text-[#999] hover:text-red-500 hover:bg-red-50 transition-colors shrink-0"
              @click="deleteRecording(recording.recording_id)"
            >
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z" />
              </svg>
            </button>
          </div>

          <!-- 피드백 표시 -->
          <div
            v-if="recording.feedbacks && recording.feedbacks.length > 0"
            class="mx-3 mb-3 space-y-1.5"
          >
            <div
              v-for="fb in recording.feedbacks"
              :key="fb.feedback_id"
              class="flex items-start gap-2 bg-[#FFF8E1] rounded-[8px] px-3 py-2"
            >
              <svg class="w-3.5 h-3.5 text-[#FF9800] shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z" />
              </svg>
              <div class="flex-1 min-w-0">
                <p class="text-[12px] text-[#333] leading-relaxed">{{ fb.comment }}</p>
                <p class="text-[10px] text-[#999] mt-0.5">
                  {{ fb.account?.username || '관리자' }} &middot; {{ formatRecordingDate(fb.created_at) }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 로딩 -->
    <div v-if="loading" class="text-center py-4">
      <p class="text-[12px] text-[#888]">로딩 중...</p>
    </div>
  </div>
</template>
