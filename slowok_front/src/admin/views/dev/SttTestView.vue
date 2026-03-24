<script setup lang="ts">
import { ref } from 'vue'
import BackHeader from '@shared/components/layout/BackHeader.vue'

const isSupported = 'webkitSpeechRecognition' in window || 'SpeechRecognition' in window
const isListening = ref(false)
const transcript = ref('')
const interimText = ref('')
const confidence = ref<number | null>(null)
const errorMsg = ref('')
const logs = ref<string[]>([])

let recognition: SpeechRecognition | null = null

function addLog(msg: string) {
  const now = new Date()
  const time = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`
  logs.value.unshift(`[${time}] ${msg}`)
  if (logs.value.length > 30) logs.value.pop()
}

function startListening() {
  if (!isSupported) {
    errorMsg.value = 'Web Speech API를 지원하지 않는 브라우저입니다.'
    return
  }

  errorMsg.value = ''
  transcript.value = ''
  interimText.value = ''
  confidence.value = null

  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition
  recognition = new SpeechRecognition()
  recognition.lang = 'ko-KR'
  recognition.continuous = true
  recognition.interimResults = true
  recognition.maxAlternatives = 1

  recognition.onstart = () => {
    isListening.value = true
    addLog('음성 인식 시작')
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
        addLog(`확정: "${alt.transcript}" (신뢰도: ${Math.round(alt.confidence * 100)}%)`)
      } else {
        interim += alt.transcript
      }
    }

    if (finalText) transcript.value = finalText
    interimText.value = interim
  }

  recognition.onerror = (event: SpeechRecognitionErrorEvent) => {
    addLog(`오류: ${event.error} - ${event.message}`)
    errorMsg.value = `오류: ${event.error}`
    if (event.error !== 'no-speech') {
      isListening.value = false
    }
  }

  recognition.onend = () => {
    isListening.value = false
    addLog('음성 인식 종료')
  }

  recognition.start()
}

function stopListening() {
  if (recognition) {
    recognition.stop()
    recognition = null
  }
}

function clearAll() {
  transcript.value = ''
  interimText.value = ''
  confidence.value = null
  errorMsg.value = ''
  logs.value = []
}
</script>

<template>
  <div class="min-h-screen bg-[#F5F5F5]">
    <BackHeader title="STT 테스트 (Web Speech API)" />

    <main class="max-w-[600px] mx-auto px-5 py-6 space-y-5">
      <!-- 지원 여부 -->
      <div
        class="rounded-[12px] px-4 py-3 text-[13px] font-medium"
        :class="isSupported ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
      >
        {{ isSupported ? 'Web Speech API 지원됨' : 'Web Speech API 미지원 브라우저' }}
      </div>

      <!-- 컨트롤 버튼 -->
      <div class="flex gap-3">
        <button
          v-if="!isListening"
          @click="startListening"
          :disabled="!isSupported"
          class="flex-1 py-3.5 rounded-[12px] text-[15px] font-bold text-white bg-[#F44336] disabled:opacity-40 transition-all active:scale-[0.97]"
        >
          녹음 시작
        </button>
        <button
          v-else
          @click="stopListening"
          class="flex-1 py-3.5 rounded-[12px] text-[15px] font-bold text-white bg-[#333] transition-all active:scale-[0.97] animate-pulse"
        >
          녹음 중지
        </button>
        <button
          @click="clearAll"
          class="px-5 py-3.5 rounded-[12px] text-[14px] font-medium text-[#555] bg-white border border-[#E0E0E0] transition-all active:scale-[0.97]"
        >
          초기화
        </button>
      </div>

      <!-- 녹음 상태 표시 -->
      <div v-if="isListening" class="flex items-center justify-center gap-2 py-3">
        <span class="w-3 h-3 rounded-full bg-red-500 animate-pulse" />
        <span class="text-[14px] font-medium text-red-600">음성을 인식하고 있습니다...</span>
      </div>

      <!-- 결과 영역 -->
      <div class="bg-white rounded-[16px] p-5 space-y-4">
        <p class="text-[13px] font-semibold text-[#333]">변환 결과</p>

        <!-- 확정 텍스트 -->
        <div class="min-h-[80px] bg-[#F8F8F8] rounded-[12px] p-4">
          <p v-if="transcript" class="text-[15px] text-[#333] leading-relaxed whitespace-pre-wrap">{{ transcript }}</p>
          <p v-else class="text-[13px] text-[#BBB] italic">녹음 시작 후 말해보세요...</p>
        </div>

        <!-- 중간 텍스트 (실시간) -->
        <div v-if="interimText" class="bg-[#FFF8E1] rounded-[12px] p-4">
          <p class="text-[11px] text-[#999] mb-1">실시간 인식 중...</p>
          <p class="text-[14px] text-[#FF9800] leading-relaxed">{{ interimText }}</p>
        </div>

        <!-- 신뢰도 -->
        <div v-if="confidence !== null" class="flex items-center gap-2">
          <span class="text-[12px] text-[#888]">신뢰도:</span>
          <div class="flex-1 bg-[#F0F0F0] rounded-full h-2.5 overflow-hidden">
            <div
              class="h-full rounded-full transition-all duration-500"
              :style="{ width: `${confidence}%`, backgroundColor: confidence >= 80 ? '#4CAF50' : confidence >= 50 ? '#FF9800' : '#F44336' }"
            />
          </div>
          <span class="text-[13px] font-bold" :style="{ color: confidence >= 80 ? '#4CAF50' : confidence >= 50 ? '#FF9800' : '#F44336' }">
            {{ confidence }}%
          </span>
        </div>

        <!-- 에러 -->
        <div v-if="errorMsg" class="bg-red-50 rounded-[12px] px-4 py-3">
          <p class="text-[13px] text-red-600">{{ errorMsg }}</p>
        </div>
      </div>

      <!-- 로그 -->
      <div class="bg-white rounded-[16px] p-5">
        <p class="text-[13px] font-semibold text-[#333] mb-3">이벤트 로그</p>
        <div class="max-h-[200px] overflow-y-auto space-y-1">
          <p v-for="(log, i) in logs" :key="i" class="text-[11px] text-[#666] font-mono">{{ log }}</p>
          <p v-if="logs.length === 0" class="text-[12px] text-[#BBB] italic">아직 로그 없음</p>
        </div>
      </div>
    </main>
  </div>
</template>
