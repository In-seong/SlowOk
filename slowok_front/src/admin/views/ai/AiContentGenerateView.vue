<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { adminApi } from '@shared/api/adminApi'
import { useToastStore } from '@shared/stores/toastStore'

const toast = useToastStore()

type GenerateMode = 'challenge' | 'screening' | 'all'

const mode = ref<GenerateMode>('challenge')
const prompt = ref('')
const generating = ref(false)
const saving = ref(false)
const previewData = ref<Record<string, unknown> | null>(null)
const error = ref('')

interface UsageStats {
  today: { requests: number; success: number; tokens: number; limit: number }
  month: { requests: number; success: number; tokens: number }
}
const usage = ref<UsageStats | null>(null)

async function fetchUsage() {
  try {
    const res = await adminApi.getAiUsage()
    if (res.data.success) usage.value = res.data.data
  } catch { /* ignore */ }
}

onMounted(fetchUsage)

const modes = [
  { value: 'challenge' as GenerateMode, label: '챌린지 생성', icon: '🏆', desc: '아이가 직접 푸는 문제 세트' },
  { value: 'screening' as GenerateMode, label: '진단검사 생성', icon: '📋', desc: '보호자/교사 관찰 평가 문항' },
  { value: 'all' as GenerateMode, label: '함께 생성', icon: '✨', desc: '챌린지 + 진단검사 한번에' },
] as const

const EXAMPLE_PROMPTS: Record<GenerateMode, string[]> = {
  challenge: [
    '초등생 기준 감정 인식 챌린지 — 표정 보고 감정 맞추기, 상황에 맞는 행동 고르기 등',
    '유아 또래 상호작용 챌린지 — 인사하기, 차례 지키기, 나누기 상황 문제',
    '초등 저학년 자기 조절 챌린지 — 화 다스리기, 기다리기 상황 판단',
  ],
  screening: [
    '초등생 사회성 발달 수준 진단 — 또래관계, 감정조절, 의사소통 하위영역',
    '유아 감정 인식/표현 수준 체크리스트 — 보호자 관찰 평가',
    '초등학생 학교 적응 행동 진단 — 규칙 따르기, 자기 주장, 협동 영역',
  ],
  all: [
    '초등생 사회성 기초 다지기 — 감정 인식 챌린지 + 사회성 수준 진단',
    '유아 또래관계 프로그램 — 상호작용 챌린지 + 또래관계 수준 진단',
  ],
}

async function handleGenerate() {
  if (!prompt.value.trim()) {
    error.value = '프롬프트를 입력해주세요.'
    return
  }
  if (prompt.value.trim().length < 10) {
    error.value = '좀 더 구체적으로 입력해주세요. (최소 10자)'
    return
  }

  generating.value = true
  error.value = ''
  previewData.value = null

  try {
    const res = await adminApi.generateAiContent(prompt.value.trim(), mode.value)
    if (res.data.success) {
      previewData.value = res.data.data
      toast.success('AI 생성 완료! 아래에서 확인 후 저장해주세요.')
      fetchUsage()
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string }; status?: number }; message?: string; code?: string }
    if (err.response?.data?.message) {
      error.value = err.response.data.message
    } else if (err.code === 'ECONNABORTED') {
      error.value = 'AI 생성 요청이 시간 초과되었습니다. 다시 시도해주세요.'
    } else {
      error.value = `오류: ${err.message || '네트워크 오류'}`
    }
    fetchUsage()
  } finally {
    generating.value = false
  }
}

async function handleSave() {
  if (!previewData.value) return
  saving.value = true
  try {
    const res = await adminApi.saveAiContent(previewData.value)
    if (res.data.success) {
      toast.success(res.data.message || '저장 완료!')
      previewData.value = null
      prompt.value = ''
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '저장에 실패했습니다.')
  } finally {
    saving.value = false
  }
}

function selectExample(text: string) {
  prompt.value = text
}
</script>

<template>
  <div class="p-6">
    <div class="max-w-[900px] mx-auto">
      <!-- 헤더 -->
      <div class="mb-6">
        <h2 class="text-[18px] font-bold text-[#333]">AI 생성</h2>
        <p class="text-[13px] text-[#888] mt-1">프롬프트를 입력하면 AI가 챌린지 문항 또는 진단검사를 자동 생성합니다.</p>
      </div>

      <!-- 사용량 표시 -->
      <div v-if="usage" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.08)] p-4 mb-5">
        <div class="flex items-center gap-2 mb-3">
          <svg class="w-4 h-4 text-[#4CAF50]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18" /><path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3" /></svg>
          <span class="text-[13px] font-semibold text-[#333]">API 사용량</span>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <p class="text-[11px] text-[#999] mb-1">오늘</p>
            <div class="flex items-end gap-1">
              <span class="text-[20px] font-bold" :class="usage.today.requests >= usage.today.limit * 0.9 ? 'text-red-500' : 'text-[#333]'">{{ usage.today.requests }}</span>
              <span class="text-[12px] text-[#999] mb-0.5">/ {{ usage.today.limit }}회</span>
            </div>
            <div class="mt-1.5 h-1.5 bg-[#F0F0F0] rounded-full overflow-hidden">
              <div
                class="h-full rounded-full transition-all"
                :class="usage.today.requests >= usage.today.limit * 0.9 ? 'bg-red-400' : usage.today.requests >= usage.today.limit * 0.7 ? 'bg-orange-400' : 'bg-[#4CAF50]'"
                :style="{ width: Math.min(100, (usage.today.requests / usage.today.limit) * 100) + '%' }"
              />
            </div>
          </div>
          <div>
            <p class="text-[11px] text-[#999] mb-1">이번 달</p>
            <div class="flex items-end gap-1">
              <span class="text-[20px] font-bold text-[#333]">{{ usage.month.requests }}</span>
              <span class="text-[12px] text-[#999] mb-0.5">회</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 모드 선택 -->
      <div class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.08)] p-5 mb-5">
        <label class="block text-[14px] font-semibold text-[#333] mb-3">생성 유형</label>
        <div class="grid grid-cols-3 gap-2">
          <button
            v-for="m in modes"
            :key="m.value"
            @click="mode = m.value; previewData = null"
            :class="mode === m.value
              ? 'border-[#4CAF50] bg-[#F0FFF0] ring-1 ring-[#4CAF50]/30'
              : 'border-[#E8E8E8] bg-white hover:border-[#C8E6C9]'"
            class="border-2 rounded-[12px] p-3 text-center transition-all"
          >
            <span class="text-[22px] block mb-1">{{ m.icon }}</span>
            <p class="text-[12px] font-bold" :class="mode === m.value ? 'text-[#2E7D32]' : 'text-[#555]'">{{ m.label }}</p>
            <p class="text-[10px] text-[#999] mt-0.5">{{ m.desc }}</p>
          </button>
        </div>
      </div>

      <!-- 프롬프트 입력 -->
      <div class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.08)] p-5 mb-5">
        <label class="block text-[14px] font-semibold text-[#333] mb-2">프롬프트 입력</label>
        <textarea
          v-model="prompt"
          rows="4"
          placeholder="예: 초등생 감정 인식 훈련 문제를 만들어줘..."
          class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[14px] focus:border-[#4CAF50] focus:outline-none transition-colors resize-none"
          :disabled="generating"
        />

        <!-- 예시 프롬프트 -->
        <div class="mt-3">
          <p class="text-[12px] text-[#999] mb-1.5">예시:</p>
          <div class="flex flex-wrap gap-1.5">
            <button
              v-for="(ex, idx) in EXAMPLE_PROMPTS[mode]"
              :key="idx"
              @click="selectExample(ex)"
              class="text-[11px] px-2.5 py-1 rounded-full bg-[#F0F7F0] text-[#4CAF50] hover:bg-[#E8F5E9] transition-colors truncate max-w-[320px]"
            >
              {{ ex.slice(0, 50) }}...
            </button>
          </div>
        </div>

        <p v-if="error" class="mt-2 text-[13px] text-red-500">{{ error }}</p>

        <button
          @click="handleGenerate"
          :disabled="generating || !prompt.trim()"
          class="mt-4 w-full bg-[#4CAF50] hover:bg-[#388E3C] disabled:bg-[#ccc] text-white rounded-[12px] py-3 text-[14px] font-medium transition-all flex items-center justify-center gap-2"
        >
          <svg v-if="generating" class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
          {{ generating ? 'AI 생성 중... (최대 30초)' : `AI로 ${modes.find(m => m.value === mode)?.label}` }}
        </button>
      </div>

      <!-- 미리보기 -->
      <div v-if="previewData" class="space-y-4">
        <!-- 챌린지 미리보기 -->
        <div v-if="previewData.challenge" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.08)] p-5">
          <div class="flex items-center gap-2 mb-3">
            <span class="text-[18px]">🏆</span>
            <h3 class="text-[15px] font-bold text-[#333]">{{ (previewData.challenge as Record<string, unknown>)?.title }}</h3>
          </div>
          <div class="space-y-2">
            <div
              v-for="(q, idx) in ((previewData.challenge as Record<string, unknown>)?.questions as Array<Record<string, unknown>> | undefined) ?? []"
              :key="idx"
              class="p-3 bg-[#FAFAFA] rounded-[10px]"
            >
              <div class="flex items-start gap-2">
                <span class="text-[12px] text-[#999] font-mono w-5 mt-0.5">{{ idx + 1 }}</span>
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-1">
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-500">{{ q.question_type }}</span>
                    <p class="text-[13px] text-[#333]">{{ q.content }}</p>
                  </div>
                  <div v-if="(q.options as string[] | undefined)?.length" class="flex flex-wrap gap-1">
                    <span
                      v-for="(opt, oi) in (q.options as string[])"
                      :key="oi"
                      :class="['text-[11px] px-2 py-0.5 rounded-full', opt === q.correct_answer ? 'bg-green-100 text-green-700 font-medium' : 'bg-gray-100 text-gray-600']"
                    >{{ opt }}</span>
                  </div>
                  <div v-if="(q.match_pairs as Array<Record<string, string>> | undefined)?.length" class="flex flex-wrap gap-1">
                    <span
                      v-for="(mp, mi) in (q.match_pairs as Array<Record<string, string>>)"
                      :key="mi"
                      class="text-[11px] px-2 py-0.5 rounded-full bg-blue-50 text-blue-600"
                    >{{ mp.left }} = {{ mp.right }}</span>
                  </div>
                  <div v-if="(q.accept_answers as string[] | undefined)?.length" class="flex flex-wrap gap-1">
                    <span
                      v-for="(ans, ai) in (q.accept_answers as string[])"
                      :key="ai"
                      class="text-[11px] px-2 py-0.5 rounded-full bg-teal-50 text-teal-600"
                    >{{ ans }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 진단검사 미리보기 -->
        <div v-if="previewData.screening_test" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.08)] p-5">
          <div class="flex items-center gap-2 mb-1">
            <span class="text-[18px]">📋</span>
            <h3 class="text-[15px] font-bold text-[#333]">{{ (previewData.screening_test as Record<string, unknown>)?.title }}</h3>
          </div>
          <p class="text-[12px] text-[#888] mb-3">{{ (previewData.screening_test as Record<string, unknown>)?.description }}</p>
          <div class="space-y-2">
            <div
              v-for="(q, idx) in ((previewData.screening_test as Record<string, unknown>)?.questions as Array<Record<string, unknown>> | undefined) ?? []"
              :key="idx"
              class="flex items-start gap-2 p-3 bg-[#FAFAFA] rounded-[10px]"
            >
              <span class="text-[12px] text-[#999] font-mono w-5 mt-0.5">{{ idx + 1 }}</span>
              <div>
                <p class="text-[13px] text-[#333]">{{ q.content }}</p>
                <p v-if="q.sub_domain" class="text-[11px] text-[#999] mt-0.5">{{ q.sub_domain }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- 저장/취소 -->
        <div class="flex gap-3">
          <button
            @click="previewData = null"
            class="flex-1 border border-[#E8E8E8] text-[#666] rounded-[12px] py-3 text-[14px] font-medium hover:bg-[#F8F8F8] transition-colors"
          >
            취소
          </button>
          <button
            @click="handleSave"
            :disabled="saving"
            class="flex-1 bg-[#4CAF50] hover:bg-[#388E3C] disabled:bg-[#ccc] text-white rounded-[12px] py-3 text-[14px] font-medium transition-all flex items-center justify-center gap-2"
          >
            <svg v-if="saving" class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
            {{ saving ? '저장 중...' : 'DB에 저장하기' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
