<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import AdminLayout from '../../components/AdminLayout.vue'
import { adminVoiceRecordingApi } from '@shared/api/voiceRecordingApi'
import { useToastStore } from '@shared/stores/toastStore'
import type { VoiceRecording, RecordingFeedback } from '@shared/types'

const toast = useToastStore()
const recordings = ref<VoiceRecording[]>([])
const loading = ref(false)

// 필터
const filterType = ref('')

// 재생
const playingId = ref<number | null>(null)
const currentAudio = ref<HTMLAudioElement | null>(null)

// 피드백 모달
const showFeedbackModal = ref(false)
const selectedRecording = ref<VoiceRecording | null>(null)
const feedbackComment = ref('')
const submittingFeedback = ref(false)

async function fetchRecordings() {
  loading.value = true
  try {
    const params: Record<string, string> = {}
    if (filterType.value) params.assignable_type = filterType.value

    const res = await adminVoiceRecordingApi.list(params)
    if (res.data.success) {
      recordings.value = res.data.data
    }
  } catch (e: any) {
    toast.error(e.response?.data?.message || '녹음 목록을 불러오는데 실패했습니다.')
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
  return new Date(dateStr).toLocaleDateString('ko-KR', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function getProfileName(recording: VoiceRecording): string {
  if (!recording.profile) return '알 수 없음'
  return recording.profile.decrypted_name || recording.profile.name
}

function getTypeLabel(type: string): string {
  return type === 'learning_content' ? '학습 콘텐츠' : '챌린지'
}

function openFeedbackModal(recording: VoiceRecording) {
  selectedRecording.value = recording
  feedbackComment.value = ''
  showFeedbackModal.value = true
}

async function submitFeedback() {
  if (!selectedRecording.value || !feedbackComment.value.trim()) return

  submittingFeedback.value = true
  try {
    const res = await adminVoiceRecordingApi.addFeedback(
      selectedRecording.value.recording_id,
      feedbackComment.value.trim()
    )
    if (res.data.success) {
      // 로컬 데이터 업데이트
      const target = recordings.value.find(r => r.recording_id === selectedRecording.value?.recording_id)
      if (target) {
        if (!target.feedbacks) target.feedbacks = []
        target.feedbacks.push(res.data.data)
      }
      toast.success('피드백이 등록되었습니다.')
      showFeedbackModal.value = false
    }
  } catch (e: any) {
    toast.error(e.response?.data?.message || '피드백 등록에 실패했습니다.')
  } finally {
    submittingFeedback.value = false
  }
}

async function deleteFeedback(recording: VoiceRecording, feedback: RecordingFeedback) {
  if (!confirm('피드백을 삭제하시겠습니까?')) return

  try {
    const res = await adminVoiceRecordingApi.deleteFeedback(feedback.feedback_id)
    if (res.data.success) {
      if (recording.feedbacks) {
        recording.feedbacks = recording.feedbacks.filter(f => f.feedback_id !== feedback.feedback_id)
      }
      toast.success('피드백이 삭제되었습니다.')
    }
  } catch (e: any) {
    toast.error(e.response?.data?.message || '피드백 삭제에 실패했습니다.')
  }
}

const filteredRecordings = computed(() => recordings.value)

onMounted(() => {
  fetchRecordings()
})
</script>

<template>
  <AdminLayout>
    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-[22px] font-bold text-[#333]">음성 녹음 관리</h1>
          <p class="text-[14px] text-[#888] mt-1">학습자 녹음을 듣고 피드백을 남길 수 있습니다</p>
        </div>
      </div>

      <!-- 필터 -->
      <div class="flex items-center gap-3 mb-4">
        <select
          v-model="filterType"
          class="px-3 py-2 border border-[#E0E0E0] rounded-[8px] text-[13px] text-[#333] outline-none focus:border-[#4CAF50]"
          @change="fetchRecordings"
        >
          <option value="">전체 유형</option>
          <option value="learning_content">학습 콘텐츠</option>
          <option value="challenge">챌린지</option>
        </select>
        <span class="text-[13px] text-[#888]">총 {{ filteredRecordings.length }}건</span>
      </div>

      <!-- 로딩 -->
      <div v-if="loading" class="text-center py-12">
        <p class="text-[14px] text-[#888]">로딩 중...</p>
      </div>

      <!-- 빈 상태 -->
      <div v-else-if="filteredRecordings.length === 0" class="text-center py-12">
        <svg class="w-12 h-12 mx-auto text-[#DDD] mb-3" fill="currentColor" viewBox="0 0 24 24">
          <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z" />
          <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z" />
        </svg>
        <p class="text-[14px] text-[#888]">녹음이 없습니다</p>
      </div>

      <!-- 녹음 목록 -->
      <div v-else class="space-y-3">
        <div
          v-for="recording in filteredRecordings"
          :key="recording.recording_id"
          class="bg-white rounded-[12px] shadow-[0_1px_3px_rgba(0,0,0,0.08)] overflow-hidden"
        >
          <!-- 녹음 정보 -->
          <div class="flex items-center gap-3 p-4">
            <!-- 재생 버튼 -->
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

            <!-- 정보 -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-0.5">
                <span class="text-[14px] font-semibold text-[#333]">{{ getProfileName(recording) }}</span>
                <span class="text-[11px] px-2 py-0.5 rounded-full font-medium"
                  :class="recording.assignable_type === 'learning_content'
                    ? 'bg-[#E8F5E9] text-[#4CAF50]'
                    : 'bg-[#FFF3E0] text-[#FF9800]'"
                >
                  {{ getTypeLabel(recording.assignable_type) }}
                </span>
              </div>
              <div class="flex items-center gap-2 text-[12px] text-[#888]">
                <span>{{ formatDuration(recording.duration) }}</span>
                <span class="text-[#E0E0E0]">|</span>
                <span>{{ formatDate(recording.created_at) }}</span>
                <template v-if="recording.memo">
                  <span class="text-[#E0E0E0]">|</span>
                  <span class="truncate">{{ recording.memo }}</span>
                </template>
              </div>
            </div>

            <!-- 피드백 버튼 -->
            <button
              class="flex items-center gap-1.5 px-3 py-2 bg-[#FFF8E1] text-[#FF9800] rounded-[8px] text-[12px] font-semibold hover:bg-[#FFF0C0] transition-colors shrink-0"
              @click="openFeedbackModal(recording)"
            >
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z" />
              </svg>
              피드백
            </button>
          </div>

          <!-- 기존 피드백 목록 -->
          <div
            v-if="recording.feedbacks && recording.feedbacks.length > 0"
            class="border-t border-[#F0F0F0] px-4 py-3 space-y-2"
          >
            <p class="text-[11px] font-bold text-[#999] mb-1">피드백 ({{ recording.feedbacks.length }})</p>
            <div
              v-for="fb in recording.feedbacks"
              :key="fb.feedback_id"
              class="flex items-start gap-2 bg-[#FAFAFA] rounded-[8px] px-3 py-2"
            >
              <svg class="w-3.5 h-3.5 text-[#FF9800] shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z" />
              </svg>
              <div class="flex-1 min-w-0">
                <p class="text-[13px] text-[#333] leading-relaxed">{{ fb.comment }}</p>
                <p class="text-[11px] text-[#999] mt-0.5">
                  {{ fb.account?.username || '관리자' }} &middot; {{ formatDate(fb.created_at) }}
                </p>
              </div>
              <button
                class="w-6 h-6 rounded-full flex items-center justify-center text-[#CCC] hover:text-red-500 hover:bg-red-50 transition-colors shrink-0"
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

      <!-- 피드백 작성 모달 -->
      <Teleport to="body">
        <div
          v-if="showFeedbackModal"
          class="fixed inset-0 z-50 flex items-center justify-center"
          @click.self="showFeedbackModal = false"
        >
          <div class="absolute inset-0 bg-black/40" />
          <div class="relative bg-white rounded-[16px] shadow-xl w-full max-w-md mx-4 p-5">
            <h3 class="text-[16px] font-bold text-[#333] mb-1">피드백 작성</h3>
            <p v-if="selectedRecording" class="text-[12px] text-[#888] mb-4">
              {{ getProfileName(selectedRecording) }}님의 녹음에 피드백을 남깁니다
            </p>

            <!-- 녹음 재생 -->
            <div v-if="selectedRecording" class="flex items-center gap-3 p-3 bg-[#F8F9FA] rounded-[10px] mb-4">
              <button
                class="w-9 h-9 rounded-full flex items-center justify-center shrink-0"
                :class="playingId === selectedRecording.recording_id ? 'bg-[#FF9800]' : 'bg-[#4CAF50]'"
                @click="playRecording(selectedRecording)"
              >
                <svg v-if="playingId !== selectedRecording.recording_id" class="w-4 h-4 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M8 5v14l11-7z" />
                </svg>
                <svg v-else class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z" />
                </svg>
              </button>
              <div class="text-[13px] text-[#555]">
                {{ formatDuration(selectedRecording.duration) }}
                <span v-if="selectedRecording.memo" class="text-[#888]"> &middot; {{ selectedRecording.memo }}</span>
              </div>
            </div>

            <textarea
              v-model="feedbackComment"
              placeholder="피드백을 입력하세요 (예: 발음이 많이 좋아졌어요!)"
              maxlength="1000"
              rows="4"
              class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-[10px] text-[13px] text-[#333] placeholder-[#BBB] outline-none focus:border-[#4CAF50] transition-colors resize-none"
            />
            <p class="text-right text-[11px] text-[#BBB] mt-1">{{ feedbackComment.length }}/1000</p>

            <div class="flex gap-2 mt-4">
              <button
                class="flex-1 py-2.5 border border-[#E0E0E0] text-[#555] text-[13px] font-semibold rounded-[10px] hover:bg-[#F5F5F5] transition-colors"
                @click="showFeedbackModal = false"
              >
                취소
              </button>
              <button
                class="flex-1 py-2.5 bg-[#4CAF50] text-white text-[13px] font-semibold rounded-[10px] hover:bg-[#388E3C] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="!feedbackComment.trim() || submittingFeedback"
                @click="submitFeedback"
              >
                {{ submittingFeedback ? '등록 중...' : '피드백 등록' }}
              </button>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
  </AdminLayout>
</template>
