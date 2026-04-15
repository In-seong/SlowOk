<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { adminApi } from '@shared/api/adminApi'
import { useToastStore } from '@shared/stores/toastStore'
import BottomSheet from '../../components/BottomSheet.vue'

const router = useRouter()
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

interface PromptHistory { log_id: number; prompt: string; created_at: string }
const promptHistory = ref<PromptHistory[]>([])
const showHistory = ref(false)

const modes = [
  { value: 'challenge' as GenerateMode, label: '챌린지 생성', icon: '🏆', desc: '아이가 직접 푸는 문제 세트' },
  { value: 'screening' as GenerateMode, label: '진단검사 생성', icon: '📋', desc: '보호자/교사 관찰 평가 문항' },
  { value: 'all' as GenerateMode, label: '함께 생성', icon: '✨', desc: '챌린지 + 진단검사 한번에' },
]

const EXAMPLE_PROMPTS: Record<GenerateMode, string[]> = {
  challenge: [
    '초등생 기준 감정 인식 챌린지 — 표정 보고 감정 맞추기',
    '유아 또래 상호작용 챌린지 — 인사하기, 차례 지키기',
  ],
  screening: [
    '초등생 사회성 발달 수준 진단 — 또래관계, 감정조절',
    '유아 감정 인식/표현 수준 체크리스트',
  ],
  all: [
    '초등생 사회성 기초 다지기 — 감정 인식 챌린지 + 사회성 수준 진단',
  ],
}

async function fetchUsage() {
  try {
    const res = await adminApi.getAiUsage()
    if (res.data.success) usage.value = res.data.data
  } catch { /* ignore */ }
}

async function fetchPromptHistory() {
  try {
    const res = await adminApi.getAiPromptHistory()
    if (res.data.success) promptHistory.value = res.data.data
  } catch { /* ignore */ }
}

onMounted(() => { fetchUsage(); fetchPromptHistory() })

async function handleGenerate() {
  if (!prompt.value.trim()) { error.value = '프롬프트를 입력해주세요.'; return }
  if (prompt.value.trim().length < 10) { error.value = '좀 더 구체적으로 입력해주세요. (최소 10자)'; return }
  generating.value = true
  error.value = ''
  previewData.value = null
  try {
    const res = await adminApi.generateAiContent(prompt.value.trim(), mode.value)
    if (res.data.success) {
      previewData.value = res.data.data
      toast.success('AI 생성 완료! 아래에서 확인 후 저장해주세요.')
      fetchUsage()
      fetchPromptHistory()
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } }; code?: string }
    if (err.code === 'ECONNABORTED') error.value = 'AI 응답 시간이 초과되었습니다. 다시 시도해주세요.'
    else error.value = err.response?.data?.message || '생성에 실패했습니다.'
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
      const counts = res.data.data.counts
      toast.success(`저장 완료! 챌린지 ${counts.challenges}개, 진단 ${counts.screening_tests}개`)
      previewData.value = null
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '저장에 실패했습니다.')
  } finally {
    saving.value = false
  }
}

function useHistoryPrompt(p: string) {
  prompt.value = p
  showHistory.value = false
}
</script>

<template>
  <div class="min-h-screen bg-[#FAFAFA]">
    <header class="sticky top-0 z-40 bg-white border-b border-[#E8E8E8] h-[56px] flex items-center px-4">
      <button @click="router.back()" class="w-10 h-10 flex items-center justify-center">
        <svg class="w-5 h-5 text-[#333]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <h1 class="flex-1 text-center text-[16px] font-bold text-[#333]">AI 콘텐츠 생성</h1>
      <div class="w-10" />
    </header>

    <div class="px-4 py-5 space-y-5">
      <!-- 사용량 -->
      <div v-if="usage" class="grid grid-cols-2 gap-3">
        <div class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4 text-center">
          <p class="text-[18px] font-bold text-[#333]">{{ usage.today.requests }}/{{ usage.today.limit }}</p>
          <p class="text-[12px] text-[#888]">오늘 사용량</p>
        </div>
        <div class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4 text-center">
          <p class="text-[18px] font-bold text-[#333]">{{ usage.month.requests }}</p>
          <p class="text-[12px] text-[#888]">이번달 누적</p>
        </div>
      </div>

      <!-- 모드 선택 -->
      <div class="space-y-2">
        <p class="text-[13px] font-semibold text-[#555]">생성 모드</p>
        <div class="space-y-2">
          <button
            v-for="m in modes"
            :key="m.value"
            @click="mode = m.value"
            class="w-full flex items-center gap-3 p-4 rounded-[16px] border transition-colors"
            :class="mode === m.value ? 'bg-[#E8F5E9] border-[#4CAF50]' : 'bg-white border-[#E8E8E8]'"
          >
            <span class="text-[28px]">{{ m.icon }}</span>
            <div class="text-left">
              <p class="text-[15px] font-semibold text-[#333]">{{ m.label }}</p>
              <p class="text-[12px] text-[#888]">{{ m.desc }}</p>
            </div>
          </button>
        </div>
      </div>

      <!-- 프롬프트 -->
      <div>
        <div class="flex items-center justify-between mb-1">
          <label class="text-[13px] font-semibold text-[#555]">프롬프트</label>
          <button v-if="promptHistory.length > 0" @click="showHistory = true" class="text-[12px] text-[#4CAF50] font-medium">이전 프롬프트</button>
        </div>
        <textarea v-model="prompt" rows="4" placeholder="생성할 콘텐츠를 구체적으로 설명해주세요..." class="w-full bg-white border border-[#E8E8E8] rounded-[12px] px-4 py-3.5 text-[15px] focus:outline-none focus:border-[#4CAF50] resize-none" />
        <div class="flex flex-wrap gap-2 mt-2">
          <button
            v-for="(ex, i) in EXAMPLE_PROMPTS[mode]"
            :key="i"
            @click="prompt = ex"
            class="text-[11px] px-2.5 py-1.5 bg-[#F5F5F5] text-[#666] rounded-[8px] active:bg-[#E8E8E8]"
          >{{ ex.slice(0, 25) }}...</button>
        </div>
      </div>

      <p v-if="error" class="text-[13px] text-[#FF4444]">{{ error }}</p>

      <button
        @click="handleGenerate"
        :disabled="generating"
        class="w-full py-3.5 bg-[#4CAF50] text-white rounded-[12px] text-[15px] font-semibold disabled:opacity-50 active:bg-[#388E3C] flex items-center justify-center gap-2"
      >
        <div v-if="generating" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
        {{ generating ? 'AI 생성 중...' : '✨ AI 생성' }}
      </button>

      <!-- 미리보기 -->
      <div v-if="previewData" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4 space-y-3">
        <p class="text-[15px] font-semibold text-[#333]">생성 결과</p>
        <pre class="text-[12px] text-[#555] bg-[#F8F8F8] rounded-[10px] p-3 overflow-x-auto whitespace-pre-wrap max-h-[300px] overflow-y-auto">{{ JSON.stringify(previewData, null, 2) }}</pre>
        <button
          @click="handleSave"
          :disabled="saving"
          class="w-full py-3.5 bg-[#2196F3] text-white rounded-[12px] text-[15px] font-semibold disabled:opacity-50 active:opacity-80"
        >{{ saving ? '저장 중...' : '저장하기' }}</button>
      </div>
    </div>

    <!-- 프롬프트 이력 -->
    <BottomSheet v-model="showHistory" title="이전 프롬프트">
      <div class="space-y-1 pb-4">
        <button
          v-for="h in promptHistory"
          :key="h.log_id"
          @click="useHistoryPrompt(h.prompt)"
          class="w-full text-left px-4 py-3 rounded-[12px] active:bg-[#F0F0F0]"
        >
          <p class="text-[14px] text-[#333] line-clamp-2">{{ h.prompt }}</p>
          <p class="text-[11px] text-[#999] mt-0.5">{{ h.created_at }}</p>
        </button>
      </div>
    </BottomSheet>
  </div>
</template>
