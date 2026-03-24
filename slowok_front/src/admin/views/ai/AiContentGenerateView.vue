<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { adminApi } from '@shared/api/adminApi'
import { useToastStore } from '@shared/stores/toastStore'

const toast = useToastStore()

const prompt = ref('')
const generating = ref(false)
const saving = ref(false)
const previewData = ref<Record<string, unknown> | null>(null)
const error = ref('')

// 사용량 통계
interface UsageStats {
  today: { requests: number; success: number; tokens: number; limit: number }
  month: { requests: number; success: number; tokens: number }
}
const usage = ref<UsageStats | null>(null)

async function fetchUsage() {
  try {
    const res = await adminApi.getAiUsage()
    if (res.data.success) {
      usage.value = res.data.data
    }
  } catch {
    // 사용량 로딩 실패해도 기능은 작동
  }
}

onMounted(fetchUsage)

const EXAMPLE_PROMPTS = [
  '초등생 기준으로 사회성 발달 교육을 필요로 하는 내담자들이 집에서 편하게 할 수 있는 8가지 콘텐츠를 만들어줘. 콘텐츠 유형은 다양하게.',
  '유아(5~7세) 감정 인식 훈련 콘텐츠 6개 세트. 쉬운 난이도로, 게임과 퀴즈 위주로.',
  '청소년 또래관계 개선 프로그램 콘텐츠 5개. 역할극 시나리오와 자기 점검 퀴즈 포함.',
  '초등 저학년 언어 발달 촉진 콘텐츠 7개. 읽기 자료와 짝 맞추기 게임 포함.',
]

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
    const res = await adminApi.generateAiContent(prompt.value.trim())
    if (res.data.success) {
      previewData.value = res.data.data
      toast.success('AI 콘텐츠가 생성되었습니다. 아래에서 확인 후 저장해주세요.')
      fetchUsage()
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string }; status?: number }; message?: string; code?: string }
    const serverMsg = err.response?.data?.message
    const status = err.response?.status
    const code = err.code

    if (serverMsg) {
      error.value = serverMsg
    } else if (code === 'ECONNABORTED') {
      error.value = 'AI 생성 요청이 시간 초과되었습니다. 다시 시도해주세요.'
    } else if (status) {
      error.value = `서버 오류 (${status}). 잠시 후 다시 시도해주세요.`
    } else {
      error.value = `연결 실패: ${err.message || '네트워크 오류'}`
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

function getContentTypeLabel(type: string): string {
  const map: Record<string, string> = { VIDEO: '영상', QUIZ: '퀴즈', GAME: '게임', READING: '읽기' }
  return map[type] || type
}

function getContentTypeBg(type: string): string {
  const map: Record<string, string> = {
    VIDEO: 'bg-blue-100 text-blue-700',
    QUIZ: 'bg-orange-100 text-orange-700',
    GAME: 'bg-green-100 text-green-700',
    READING: 'bg-purple-100 text-purple-700',
  }
  return map[type] || 'bg-gray-100 text-gray-700'
}
</script>

<template>
  <div class="p-6">
    <div class="max-w-[900px] mx-auto">
      <!-- 헤더 -->
      <div class="mb-6">
        <h2 class="text-[18px] font-bold text-[#333]">AI 콘텐츠 생성</h2>
        <p class="text-[13px] text-[#888] mt-1">프롬프트를 입력하면 AI가 학습 콘텐츠 + 챌린지 + 진단검사를 한번에 생성합니다.</p>
      </div>

      <!-- 사용량 표시 -->
      <div v-if="usage" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.08)] p-4 mb-5">
        <div class="flex items-center gap-2 mb-3">
          <svg class="w-4 h-4 text-[#4CAF50]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18" /><path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3" /></svg>
          <span class="text-[13px] font-semibold text-[#333]">API 사용량</span>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <!-- 오늘 -->
          <div>
            <p class="text-[11px] text-[#999] mb-1">오늘</p>
            <div class="flex items-end gap-1">
              <span class="text-[20px] font-bold" :class="usage.today.requests >= usage.today.limit * 0.9 ? 'text-red-500' : 'text-[#333]'">{{ usage.today.requests }}</span>
              <span class="text-[12px] text-[#999] mb-0.5">/ {{ usage.today.limit }}회</span>
            </div>
            <!-- 프로그레스 바 -->
            <div class="mt-1.5 h-1.5 bg-[#F0F0F0] rounded-full overflow-hidden">
              <div
                class="h-full rounded-full transition-all"
                :class="usage.today.requests >= usage.today.limit * 0.9 ? 'bg-red-400' : usage.today.requests >= usage.today.limit * 0.7 ? 'bg-orange-400' : 'bg-[#4CAF50]'"
                :style="{ width: Math.min(100, (usage.today.requests / usage.today.limit) * 100) + '%' }"
              ></div>
            </div>
            <p class="text-[11px] text-[#999] mt-1">성공 {{ usage.today.success }}회 · {{ usage.today.tokens.toLocaleString() }} 토큰</p>
          </div>
          <!-- 이번 달 -->
          <div>
            <p class="text-[11px] text-[#999] mb-1">이번 달</p>
            <div class="flex items-end gap-1">
              <span class="text-[20px] font-bold text-[#333]">{{ usage.month.requests }}</span>
              <span class="text-[12px] text-[#999] mb-0.5">회</span>
            </div>
            <p class="text-[11px] text-[#999] mt-3">성공 {{ usage.month.success }}회 · {{ usage.month.tokens.toLocaleString() }} 토큰</p>
          </div>
        </div>
        <p v-if="usage.today.requests >= usage.today.limit * 0.9" class="mt-2 text-[12px] text-red-500 font-medium">
          일일 사용량 한도에 거의 도달했습니다. (무료: {{ usage.today.limit }}회/일)
        </p>
      </div>

      <!-- 프롬프트 입력 -->
      <div class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.08)] p-5 mb-5">
        <label class="block text-[14px] font-semibold text-[#333] mb-2">프롬프트 입력</label>
        <textarea
          v-model="prompt"
          rows="4"
          placeholder="예: 초등생 기준으로 사회성 발달 교육 콘텐츠 8개를 만들어줘..."
          class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[14px] focus:border-[#4CAF50] focus:outline-none transition-colors resize-none"
          :disabled="generating"
        ></textarea>

        <!-- 예시 프롬프트 -->
        <div class="mt-3">
          <p class="text-[12px] text-[#999] mb-1.5">예시 프롬프트:</p>
          <div class="flex flex-wrap gap-1.5">
            <button
              v-for="(ex, idx) in EXAMPLE_PROMPTS"
              :key="idx"
              @click="selectExample(ex)"
              class="text-[11px] px-2.5 py-1 rounded-full bg-[#F0F7F0] text-[#4CAF50] hover:bg-[#E8F5E9] transition-colors truncate max-w-[280px]"
            >
              {{ ex.slice(0, 40) }}...
            </button>
          </div>
        </div>

        <!-- 에러 -->
        <p v-if="error" class="mt-2 text-[13px] text-red-500">{{ error }}</p>

        <!-- 생성 버튼 -->
        <button
          @click="handleGenerate"
          :disabled="generating || !prompt.trim()"
          class="mt-4 w-full bg-[#4CAF50] hover:bg-[#388E3C] disabled:bg-[#ccc] text-white rounded-[12px] py-3 text-[14px] font-medium transition-all flex items-center justify-center gap-2"
        >
          <svg v-if="generating" class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
          <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /><path d="M18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456z" /></svg>
          {{ generating ? 'AI 생성 중... (최대 30초 소요)' : 'AI로 콘텐츠 생성' }}
        </button>
      </div>

      <!-- 미리보기 -->
      <div v-if="previewData" class="space-y-4">
        <!-- 패키지 정보 -->
        <div class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.08)] p-5">
          <div class="flex items-center gap-2 mb-3">
            <span class="w-6 h-6 rounded-full bg-[#4CAF50] text-white flex items-center justify-center text-[11px] font-bold">P</span>
            <h3 class="text-[15px] font-bold text-[#333]">패키지: {{ (previewData.package as Record<string, string>)?.name }}</h3>
          </div>
          <p class="text-[13px] text-[#666]">{{ (previewData.package as Record<string, string>)?.description }}</p>
        </div>

        <!-- 학습 콘텐츠 목록 -->
        <div v-if="(previewData.learning_contents as Array<Record<string, unknown>>)?.length" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.08)] p-5">
          <h3 class="text-[14px] font-bold text-[#333] mb-3">
            학습 콘텐츠 ({{ (previewData.learning_contents as Array<Record<string, unknown>>).length }}개)
          </h3>
          <div class="space-y-2">
            <div
              v-for="(lc, idx) in (previewData.learning_contents as Array<Record<string, unknown>>)"
              :key="idx"
              class="flex items-center gap-3 p-3 bg-[#FAFAFA] rounded-[10px]"
            >
              <span class="text-[12px] text-[#999] font-mono w-5">{{ idx + 1 }}</span>
              <span :class="['px-2 py-0.5 rounded-full text-[11px] font-medium', getContentTypeBg(lc.content_type as string)]">
                {{ getContentTypeLabel(lc.content_type as string) }}
              </span>
              <span class="text-[13px] text-[#333] flex-1">{{ lc.title }}</span>
              <span class="text-[11px] text-[#999]">Lv.{{ lc.difficulty_level }}</span>
            </div>
          </div>
        </div>

        <!-- 챌린지 -->
        <div v-if="previewData.challenge" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.08)] p-5">
          <h3 class="text-[14px] font-bold text-[#333] mb-3">
            챌린지: {{ (previewData.challenge as Record<string, unknown>)?.title }}
          </h3>
          <div class="space-y-2">
            <div
              v-for="(q, idx) in ((previewData.challenge as Record<string, unknown>)?.questions as Array<Record<string, unknown>> | undefined) ?? []"
              :key="idx"
              class="p-3 bg-[#FAFAFA] rounded-[10px]"
            >
              <div class="flex items-start gap-2">
                <span class="text-[12px] text-[#999] font-mono w-5 mt-0.5">{{ idx + 1 }}</span>
                <div class="flex-1">
                  <p class="text-[13px] text-[#333]">{{ q.content }}</p>
                  <div v-if="(q.options as string[] | undefined)?.length" class="mt-1 flex flex-wrap gap-1">
                    <span
                      v-for="(opt, oi) in (q.options as string[])"
                      :key="oi"
                      :class="['text-[11px] px-2 py-0.5 rounded-full', opt === q.correct_answer ? 'bg-green-100 text-green-700 font-medium' : 'bg-gray-100 text-gray-600']"
                    >{{ opt }}</span>
                  </div>
                  <div v-if="(q.match_pairs as Array<Record<string, string>> | undefined)?.length" class="mt-1 flex flex-wrap gap-1">
                    <span
                      v-for="(mp, mi) in (q.match_pairs as Array<Record<string, string>>)"
                      :key="mi"
                      class="text-[11px] px-2 py-0.5 rounded-full bg-blue-50 text-blue-600"
                    >{{ mp.left }} = {{ mp.right }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 진단검사 -->
        <div v-if="previewData.screening_test" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.08)] p-5">
          <h3 class="text-[14px] font-bold text-[#333] mb-1">
            진단검사: {{ (previewData.screening_test as Record<string, unknown>)?.title }}
          </h3>
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

        <!-- 저장/취소 버튼 -->
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
