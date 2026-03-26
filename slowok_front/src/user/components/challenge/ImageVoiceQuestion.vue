<script setup lang="ts">
import { ref, watch, onBeforeUnmount } from 'vue'
import type { ChallengeQuestion } from '@shared/types'
import { voiceRecordingApi } from '@shared/api/voiceRecordingApi'

const props = defineProps<{
  question: ChallengeQuestion
  challengeId: number
}>()

const emit = defineEmits<{
  (e: 'answered', result: { correct: boolean; sttText: string; confidence: number }): void
}>()

const isSupported = 'webkitSpeechRecognition' in window || 'SpeechRecognition' in window
const isListening = ref(false)
const sttText = ref('')
const interimText = ref('')
const confidence = ref(0)
const showResult = ref(false)
const isCorrect = ref(false)
const errorMsg = ref('')

// 녹음 관련 (서버 저장용)
const isRecording = ref(false)
const mediaRecorder = ref<MediaRecorder | null>(null)
const audioChunks = ref<Blob[]>([])
const uploading = ref(false)
const uploaded = ref(false)

let recognition: SpeechRecognition | null = null

watch(() => props.question.question_id, () => {
  stopAll()
  sttText.value = ''
  interimText.value = ''
  confidence.value = 0
  showResult.value = false
  isCorrect.value = false
  errorMsg.value = ''
  uploaded.value = false
}, { immediate: true })

async function startRecording() {
  if (!isSupported) {
    errorMsg.value = '음성 인식을 지원하지 않는 브라우저입니다.'
    return
  }

  errorMsg.value = ''
  sttText.value = ''
  interimText.value = ''
  showResult.value = false

  const SpeechRecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition
  recognition = new SpeechRecognitionCtor()
  recognition.lang = 'ko-KR'
  recognition.continuous = true
  recognition.interimResults = true
  recognition.maxAlternatives = 1

  recognition.onstart = () => {
    isListening.value = true
  }

  recognition.onresult = (event: SpeechRecognitionEvent) => {
    let finalText = ''
    let interim = ''
    for (let i = 0; i < event.results.length; i++) {
      const result = event.results[i]
      if (!result) continue
      const alt = result[0]
      if (!alt) continue
      if (result.isFinal) {
        finalText += alt.transcript
        confidence.value = Math.round(alt.confidence * 100)
      } else {
        interim += alt.transcript
      }
    }
    if (finalText) sttText.value = finalText
    interimText.value = interim
  }

  recognition.onerror = (event: SpeechRecognitionErrorEvent) => {
    if (event.error !== 'no-speech') {
      errorMsg.value = `음성 인식 오류: ${event.error}`
      isListening.value = false
    }
  }

  recognition.onend = () => {
    isListening.value = false
  }

  recognition.start()

  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
    const recorder = new MediaRecorder(stream, { mimeType: 'audio/webm' })
    audioChunks.value = []

    recorder.ondataavailable = (event: BlobEvent) => {
      if (event.data.size > 0) audioChunks.value.push(event.data)
    }

    recorder.onstop = () => {
      stream.getTracks().forEach(t => t.stop())
    }

    mediaRecorder.value = recorder
    recorder.start()
    isRecording.value = true
  } catch {
    // 마이크 접근 실패 시 STT만 진행
  }
}

function stopRecording() {
  if (recognition) {
    recognition.stop()
    recognition = null
  }
  if (mediaRecorder.value && mediaRecorder.value.state === 'recording') {
    mediaRecorder.value.stop()
    isRecording.value = false
  }
}

function stopAll() {
  stopRecording()
  isListening.value = false
  isRecording.value = false
}

function submitAnswer() {
  const text = sttText.value.trim()
  if (!text) return

  const answers = props.question.accept_answers ?? []
  isCorrect.value = answers.some(ans => text.includes(ans.trim()))
  showResult.value = true
  emit('answered', { correct: isCorrect.value, sttText: text, confidence: confidence.value })

  uploadRecording(text)
}

async function uploadRecording(text: string) {
  if (audioChunks.value.length === 0 || uploaded.value) return
  uploading.value = true
  try {
    const blob = new Blob(audioChunks.value, { type: 'audio/webm' })
    const formData = new FormData()
    formData.append('file', blob, 'voice_answer.webm')
    formData.append('assignable_type', 'challenge')
    formData.append('assignable_id', String(props.challengeId))
    formData.append('memo', `[STT] ${text}`)
    formData.append('question_id', String(props.question.question_id))
    formData.append('stt_text', text)
    formData.append('stt_confidence', String(confidence.value / 100))
    await voiceRecordingApi.uploadRecording(formData)
    uploaded.value = true
  } catch {
    // 업로드 실패해도 정답 판정에는 영향 없음
  } finally {
    uploading.value = false
  }
}

onBeforeUnmount(() => {
  stopAll()
})
</script>

<template>
  <div>
    <!-- 문항 내용 -->
    <p class="text-[20px] font-bold text-[#333] mb-4 leading-relaxed">{{ question.content }}</p>

    <!-- 이미지 -->
    <div v-if="question.image_url" class="mb-5">
      <img
        :src="question.image_url"
        :alt="question.content"
        class="w-full rounded-2xl object-cover max-h-[240px] border border-[#E8E8E8]"
      />
    </div>

    <!-- 미지원 -->
    <div v-if="!isSupported" class="bg-[#FFEBEE] rounded-2xl p-4 text-center mb-4">
      <p class="text-[13px] text-[#F44336]">음성 인식을 지원하지 않는 브라우저입니다.</p>
    </div>

    <template v-else>
      <!-- 녹음 버튼 -->
      <div class="flex justify-center mb-4">
        <button
          v-if="!isListening && !showResult"
          @click="startRecording"
          class="w-20 h-20 rounded-full bg-[#F44336] text-white flex items-center justify-center shadow-[0_4px_0_#C62828] active:shadow-[0_2px_0_#C62828] active:translate-y-[2px] transition-all"
        >
          <svg class="w-9 h-9" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z" />
            <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z" />
          </svg>
        </button>

        <button
          v-if="isListening"
          @click="stopRecording"
          class="w-20 h-20 rounded-full bg-[#333] text-white flex items-center justify-center shadow-[0_4px_0_#111] active:shadow-[0_2px_0_#111] active:translate-y-[2px] transition-all animate-pulse"
        >
          <svg class="w-8 h-8" viewBox="0 0 24 24" fill="currentColor">
            <rect x="6" y="6" width="12" height="12" rx="2" />
          </svg>
        </button>
      </div>

      <!-- 녹음 상태 -->
      <div v-if="isListening" class="flex items-center justify-center gap-2 mb-3">
        <span class="w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse" />
        <span class="text-[13px] text-red-600 font-medium">음성을 듣고 있습니다...</span>
      </div>

      <!-- 실시간 인식 텍스트 -->
      <div v-if="interimText && isListening" class="bg-[#FFF8E1] rounded-2xl p-3 mb-3">
        <p class="text-[12px] text-[#999] mb-0.5">인식 중...</p>
        <p class="text-[16px] text-[#FF9800]">{{ interimText }}</p>
      </div>

      <!-- 확정 텍스트 -->
      <div v-if="sttText" class="bg-[#F8F8F8] rounded-2xl p-4 mb-3">
        <p class="text-[12px] text-[#999] mb-1">인식된 텍스트</p>
        <p class="text-[18px] text-[#333] font-medium">{{ sttText }}</p>
        <div v-if="confidence > 0" class="flex items-center gap-2 mt-2">
          <span class="text-[11px] text-[#888]">신뢰도</span>
          <div class="flex-1 bg-[#E8E8E8] rounded-full h-1.5">
            <div class="h-full rounded-full transition-all" :style="{ width: `${confidence}%`, backgroundColor: confidence >= 80 ? '#4CAF50' : '#FF9800' }" />
          </div>
          <span class="text-[11px] font-bold" :style="{ color: confidence >= 80 ? '#4CAF50' : '#FF9800' }">{{ confidence }}%</span>
        </div>
      </div>

      <!-- 제출 버튼 -->
      <button
        v-if="sttText && !showResult && !isListening"
        @click="submitAnswer"
        class="w-full py-4 rounded-2xl text-[16px] font-bold bg-[#4CAF50] text-white shadow-[0_4px_0_#388E3C] active:shadow-[0_2px_0_#388E3C] active:translate-y-[2px] transition-all"
      >
        답변 제출
      </button>

      <!-- 에러 -->
      <div v-if="errorMsg" class="mt-3 bg-[#FFEBEE] rounded-2xl p-3 text-center">
        <p class="text-[13px] text-[#F44336]">{{ errorMsg }}</p>
      </div>
    </template>
  </div>
</template>
