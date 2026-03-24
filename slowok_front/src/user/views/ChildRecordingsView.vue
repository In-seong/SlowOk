<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { voiceRecordingApi } from '@shared/api/voiceRecordingApi'
import { useToastStore } from '@shared/stores/toastStore'
import BackHeader from '@shared/components/layout/BackHeader.vue'
import BottomNav from '@shared/components/layout/BottomNav.vue'
import type { VoiceRecording, RecordingFeedback } from '@shared/types'

const route = useRoute()
const router = useRouter()
const toast = useToastStore()

const childProfileId = Number(route.params.id)
const recordings = ref<VoiceRecording[]>([])
const loading = ref(true)

// 재생
const playingId = ref<number | null>(null)
const currentAudio = ref<HTMLAudioElement | null>(null)

// 피드백 입력
const feedbackTarget = ref<number | null>(null)
const feedbackComment = ref('')
const submitting = ref(false)

async function fetchRecordings() {
  loading.value = true
  try {
    const res = await voiceRecordingApi.getChildRecordings(childProfileId)
    if (res.data.success) {
      recordings.value = res.data.data
    }
  } catch (e: any) {
    toast.error(e.response?.data?.message || '녹음 목록을 불러오지 못했습니다.')
    router.back()
  } finally {
    loading.value = false
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

function formatDate(dateStr?: string): string {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('ko-KR', { month: 'short', day: 'numeric' })
}

function getTypeLabel(type: string): string {
  return type === 'learning_content' ? '학습' : '챌린지'
}

function toggleFeedbackInput(recordingId: number) {
  if (feedbackTarget.value === recordingId) {
    feedbackTarget.value = null
    feedbackComment.value = ''
  } else {
    feedbackTarget.value = recordingId
    feedbackComment.value = ''
  }
}

async function submitFeedback(recording: VoiceRecording) {
  if (!feedbackComment.value.trim()) return

  submitting.value = true
  try {
    const res = await voiceRecordingApi.addFeedback(recording.recording_id, feedbackComment.value.trim())
    if (res.data.success) {
      if (!recording.feedbacks) recording.feedbacks = []
      recording.feedbacks.push(res.data.data)
      feedbackTarget.value = null
      feedbackComment.value = ''
      toast.success('피드백을 남겼습니다.')
    }
  } catch (e: any) {
    toast.error(e.response?.data?.message || '피드백 등록에 실패했습니다.')
  } finally {
    submitting.value = false
  }
}

async function deleteFeedback(recording: VoiceRecording, feedback: RecordingFeedback) {
  if (!confirm('피드백을 삭제하시겠습니까?')) return

  try {
    const res = await voiceRecordingApi.deleteFeedback(feedback.feedback_id)
    if (res.data.success && recording.feedbacks) {
      recording.feedbacks = recording.feedbacks.filter(f => f.feedback_id !== feedback.feedback_id)
      toast.success('피드백이 삭제되었습니다.')
    }
  } catch (e: any) {
    toast.error(e.response?.data?.message || '피드백 삭제에 실패했습니다.')
  }
}

onMounted(() => {
  fetchRecordings()
})

onBeforeUnmount(() => {
  if (currentAudio.value) currentAudio.value.pause()
})
</script>

<template>
  <div class="min-h-screen bg-[#F5F5F5] flex justify-center">
    <div class="w-full max-w-[500px] bg-[#F5F5F5] min-h-screen pb-20">
      <BackHeader title="자녀 녹음" />

      <div class="px-4 pt-4">
        <!-- 로딩 -->
        <div v-if="loading" class="text-center py-12">
          <p class="text-[13px] text-[#888]">로딩 중...</p>
        </div>

        <!-- 빈 상태 -->
        <div v-else-if="recordings.length === 0" class="text-center py-12">
          <svg class="w-12 h-12 mx-auto text-[#DDD] mb-3" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z" />
            <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z" />
          </svg>
          <p class="text-[14px] text-[#888]">아직 녹음이 없습니다</p>
        </div>

        <!-- 녹음 목록 -->
        <div v-else class="space-y-3">
          <div
            v-for="recording in recordings"
            :key="recording.recording_id"
            class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.06)] overflow-hidden"
          >
            <!-- 녹음 정보 -->
            <div class="flex items-center gap-3 p-4">
              <button
                class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
                :class="playingId === recording.recording_id ? 'bg-[#FF9800]' : 'bg-[#4CAF50]'"
                @click="playRecording(recording)"
              >
                <svg v-if="playingId !== recording.recording_id" class="w-5 h-5 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M8 5v14l11-7z" />
                </svg>
                <svg v-else class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z" />
                </svg>
              </button>

              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 mb-0.5">
                  <span class="text-[11px] px-1.5 py-0.5 rounded-full font-medium"
                    :class="recording.assignable_type === 'learning_content'
                      ? 'bg-[#E8F5E9] text-[#4CAF50]'
                      : 'bg-[#FFF3E0] text-[#FF9800]'"
                  >
                    {{ getTypeLabel(recording.assignable_type) }}
                  </span>
                  <span v-if="recording.memo" class="text-[13px] text-[#333] truncate">{{ recording.memo }}</span>
                </div>
                <div class="flex items-center gap-2 text-[11px] text-[#888]">
                  <span>{{ formatDuration(recording.duration) }}</span>
                  <span class="text-[#E0E0E0]">|</span>
                  <span>{{ formatDate(recording.created_at) }}</span>
                </div>
              </div>

              <!-- 피드백 작성 토글 -->
              <button
                class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 transition-colors"
                :class="feedbackTarget === recording.recording_id
                  ? 'bg-[#FF9800] text-white'
                  : 'bg-[#FFF8E1] text-[#FF9800]'"
                @click="toggleFeedbackInput(recording.recording_id)"
              >
                <svg class="w-4.5 h-4.5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z" />
                </svg>
              </button>
            </div>

            <!-- 피드백 입력 -->
            <div
              v-if="feedbackTarget === recording.recording_id"
              class="px-4 pb-3"
            >
              <div class="flex gap-2">
                <input
                  v-model="feedbackComment"
                  type="text"
                  placeholder="피드백을 입력하세요"
                  maxlength="1000"
                  class="flex-1 px-3 py-2.5 border border-[#E0E0E0] rounded-[10px] text-[13px] text-[#333] placeholder-[#BBB] outline-none focus:border-[#4CAF50]"
                  @keyup.enter="submitFeedback(recording)"
                />
                <button
                  class="px-4 py-2.5 bg-[#4CAF50] text-white text-[13px] font-semibold rounded-[10px] shrink-0 disabled:opacity-50"
                  :disabled="!feedbackComment.trim() || submitting"
                  @click="submitFeedback(recording)"
                >
                  {{ submitting ? '...' : '전송' }}
                </button>
              </div>
            </div>

            <!-- 기존 피드백 목록 -->
            <div
              v-if="recording.feedbacks && recording.feedbacks.length > 0"
              class="border-t border-[#F0F0F0] px-4 py-3 space-y-1.5"
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
                    {{ fb.account?.username || '' }} &middot; {{ formatDate(fb.created_at) }}
                  </p>
                </div>
                <button
                  class="w-5 h-5 rounded-full flex items-center justify-center text-[#CCC] hover:text-red-500 shrink-0"
                  @click="deleteFeedback(recording, fb)"
                >
                  <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <BottomNav />
    </div>
  </div>
</template>
