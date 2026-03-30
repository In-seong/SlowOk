<script setup lang="ts">
import { ref, watch } from 'vue'
import type { ChallengeQuestion, MatchPair } from '@shared/types'
import { adminApi } from '@shared/api/adminApi'
import { compressImage } from '@shared/utils/imageCompress'

const props = defineProps<{
  question: Partial<ChallengeQuestion> | null
  challengeId: number
}>()

const emit = defineEmits<{
  (e: 'save'): void
  (e: 'cancel'): void
}>()

const QUESTION_TYPES = [
  { value: 'multiple_choice', label: '객관식 (4지선다)' },
  { value: 'matching', label: '매칭 게임' },
  { value: 'image_choice', label: '그림카드 택1' },
  { value: 'image_text', label: '그림카드 텍스트 입력' },
  // { value: 'image_voice', label: '그림카드 음성 답변' }, // [미사용] 음성 기능 비활성화
] as const

const saving = ref(false)
const errorMsg = ref('')
const imageUploading = ref(false)
const matchPairImageUploading = ref<number | null>(null)

const form = ref({
  content: '',
  question_type: 'multiple_choice',
  options: ['', '', '', ''],
  correct_answer: '',
  image_url: '',
  order: 1,
  match_pairs: [{ left: '', right: '', right_image: null }] as MatchPair[],
  accept_answers: [''] as string[],
})

watch(() => props.question, (q) => {
  if (!q) return
  if (q.question_id) {
    // 수정 모드
    const opts = Array.isArray(q.options) ? [...q.options] : ['', '', '', '']
    while (opts.length < 4) opts.push('')
    const pairs = Array.isArray(q.match_pairs) && q.match_pairs.length > 0
      ? q.match_pairs.map(p => ({ left: p.left, right: p.right, right_image: p.right_image ?? null }))
      : [{ left: '', right: '', right_image: null }]
    const accepts = Array.isArray(q.accept_answers) && q.accept_answers.length > 0
      ? [...q.accept_answers]
      : ['']
    form.value = {
      content: q.content || '',
      question_type: q.question_type || 'multiple_choice',
      options: opts,
      correct_answer: q.correct_answer || '',
      image_url: q.image_url || '',
      order: q.order ?? 1,
      match_pairs: pairs,
      accept_answers: accepts,
    }
  } else {
    // 생성 모드
    form.value = {
      content: '',
      question_type: q.question_type || 'multiple_choice',
      options: ['', '', '', ''],
      correct_answer: '',
      image_url: '',
      order: q.order ?? 1,
      match_pairs: [{ left: '', right: '', right_image: null }],
      accept_answers: [''],
    }
  }
  errorMsg.value = ''
}, { immediate: true })

function addMatchPair() {
  form.value.match_pairs.push({ left: '', right: '', right_image: null })
}

function removeMatchPair(idx: number) {
  if (form.value.match_pairs.length > 1) {
    form.value.match_pairs.splice(idx, 1)
  }
}

async function handleMatchPairImageUpload(event: Event, idx: number) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  matchPairImageUploading.value = idx
  try {
    const compressed = await compressImage(file)
    const res = await adminApi.uploadFile(compressed, 'image')
    if (res.data.success) {
      const url = res.data.data.url
      form.value.match_pairs = form.value.match_pairs.map((p, i) =>
        i === idx ? { ...p, right_image: url, right: p.right || '' } : p
      )
    }
  } catch (e: any) {
    errorMsg.value = e.response?.data?.message || '이미지 업로드에 실패했습니다.'
  } finally {
    matchPairImageUploading.value = null
    target.value = ''
  }
}

function removeMatchPairImage(idx: number) {
  form.value.match_pairs = form.value.match_pairs.map((p, i) =>
    i === idx ? { ...p, right_image: null } : p
  )
}

function addAcceptAnswer() {
  form.value.accept_answers.push('')
}

function removeAcceptAnswer(idx: number) {
  if (form.value.accept_answers.length > 1) {
    form.value.accept_answers.splice(idx, 1)
  }
}

function selectCorrectAnswer(optionText: string) {
  form.value.correct_answer = optionText
}

async function handleImageUpload(event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  imageUploading.value = true
  try {
    const compressed = await compressImage(file)
    const res = await adminApi.uploadFile(compressed, 'image')
    if (res.data.success) {
      form.value.image_url = res.data.data.url
    }
  } catch (e: any) {
    errorMsg.value = e.response?.data?.message || '이미지 업로드에 실패했습니다.'
  } finally {
    imageUploading.value = false
    target.value = ''
  }
}

function removeImage() {
  form.value.image_url = ''
}

function validate(): string | null {
  if (!form.value.content.trim()) return '문항 내용을 입력해주세요.'

  const type = form.value.question_type

  if (type === 'multiple_choice' || type === 'image_choice') {
    const filledOptions = form.value.options.filter(o => o.trim() !== '')
    if (filledOptions.length < 2) return '최소 2개의 보기를 입력해주세요.'
    if (!form.value.correct_answer.trim()) return '정답을 선택해주세요.'
    if (!filledOptions.includes(form.value.correct_answer)) return '정답은 보기 중 하나여야 합니다.'
    if (type === 'image_choice' && !form.value.image_url) return '그림카드 유형은 이미지가 필수입니다.'
  } else if (type === 'matching') {
    const filledPairs = form.value.match_pairs.filter(p => p.left.trim() && (p.right.trim() || p.right_image))
    if (filledPairs.length < 2) return '최소 2개의 매칭 쌍을 입력해주세요.'
  } else if (type === 'image_text' || type === 'image_voice') {
    if (!form.value.image_url) return '그림카드 유형은 이미지가 필수입니다.'
    const filledAnswers = form.value.accept_answers.filter(a => a.trim() !== '')
    if (filledAnswers.length < 1) return '최소 1개의 허용 정답을 입력해주세요.'
  }

  return null
}

async function handleSubmit() {
  const validationError = validate()
  if (validationError) {
    errorMsg.value = validationError
    return
  }

  saving.value = true
  errorMsg.value = ''

  const type = form.value.question_type
  const filteredOptions = form.value.options.filter(o => o.trim() !== '')
  const filteredPairs = form.value.match_pairs.filter(p => p.left.trim() && (p.right.trim() || p.right_image))
  const filteredAccepts = form.value.accept_answers.filter(a => a.trim() !== '')

  const payload: Record<string, unknown> = {
    challenge_id: props.challengeId,
    content: form.value.content.trim(),
    question_type: type,
    image_url: form.value.image_url || null,
    order: form.value.order,
    options: (type === 'multiple_choice' || type === 'image_choice') ? filteredOptions : null,
    correct_answer: (type === 'multiple_choice' || type === 'image_choice') ? form.value.correct_answer.trim() : null,
    match_pairs: type === 'matching' ? filteredPairs : null,
    accept_answers: (type === 'image_text' || type === 'image_voice') ? filteredAccepts : null,
  }

  try {
    if (props.question?.question_id) {
      await adminApi.updateQuestion(props.question.question_id, payload)
    } else {
      await adminApi.createQuestion(payload)
    }
    emit('save')
  } catch (e: any) {
    errorMsg.value = e.response?.data?.message || '문항 저장에 실패했습니다.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="h-full flex flex-col">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-[16px] font-bold text-[#333]">
        {{ question?.question_id ? '문항 수정' : '문항 추가' }}
      </h3>
      <button @click="emit('cancel')" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-[#F0F0F0] transition-colors">
        <svg class="w-5 h-5 text-[#999]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12" /></svg>
      </button>
    </div>

    <!-- 에러 -->
    <div v-if="errorMsg" class="mb-3 bg-red-50 border border-red-200 rounded-[12px] px-4 py-3 text-red-600 text-[13px]">
      {{ errorMsg }}
    </div>

    <div class="flex-1 overflow-y-auto space-y-4 pb-4">
      <!-- 문항 유형 -->
      <div>
        <label class="block text-[13px] font-semibold text-[#333] mb-1.5">문항 유형</label>
        <select
          v-model="form.question_type"
          class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-2.5 text-[14px] focus:border-[#4CAF50] focus:outline-none transition-colors"
        >
          <option v-for="qt in QUESTION_TYPES" :key="qt.value" :value="qt.value">{{ qt.label }}</option>
        </select>
      </div>

      <!-- 문항 내용 -->
      <div>
        <label class="block text-[13px] font-semibold text-[#333] mb-1.5">문항 내용</label>
        <textarea
          v-model="form.content"
          rows="3"
          placeholder="문항 내용을 입력해주세요"
          class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-2.5 text-[14px] focus:border-[#4CAF50] focus:outline-none transition-colors resize-y"
        />
      </div>

      <!-- 객관식 / 그림카드 택1: 보기 -->
      <div v-if="form.question_type === 'multiple_choice' || form.question_type === 'image_choice'">
        <label class="block text-[13px] font-semibold text-[#333] mb-1.5">보기</label>
        <div class="flex flex-col gap-2">
          <div v-for="(_, idx) in form.options" :key="idx" class="flex items-center gap-2">
            <label class="shrink-0 flex items-center justify-center cursor-pointer">
              <input
                type="radio"
                name="correct_answer_panel"
                :checked="form.correct_answer === form.options[idx] && form.options[idx]!.trim() !== ''"
                @change="selectCorrectAnswer(form.options[idx] ?? '')"
                :disabled="!form.options[idx] || form.options[idx]!.trim() === ''"
                class="w-4 h-4 accent-[#4CAF50]"
              />
            </label>
            <input
              v-model="form.options[idx]"
              type="text"
              :placeholder="`보기 ${idx + 1}`"
              class="flex-1 bg-[#F8F8F8] border border-[#E8E8E8] rounded-[10px] px-3 py-2 text-[13px] focus:border-[#4CAF50] focus:outline-none transition-colors"
            />
          </div>
        </div>
        <p class="mt-1 text-[11px] text-[#888]">라디오 버튼으로 정답을 선택해주세요.</p>
      </div>

      <!-- 매칭 게임 -->
      <div v-if="form.question_type === 'matching'">
        <label class="block text-[13px] font-semibold text-[#333] mb-1.5">매칭 쌍</label>
        <div class="flex flex-col gap-2.5">
          <div v-for="(pair, idx) in form.match_pairs" :key="idx" class="bg-[#FAFAFA] rounded-[10px] p-2.5">
            <div class="flex items-start gap-2">
              <input
                v-model="pair.left"
                type="text"
                :placeholder="`좌측 ${idx + 1}`"
                class="flex-1 bg-white border border-[#E8E8E8] rounded-[8px] px-3 py-2 text-[13px] focus:border-[#4CAF50] focus:outline-none transition-colors"
              />
              <span class="text-[#999] text-[13px] mt-2">=</span>
              <div class="flex-1">
                <input
                  v-model="pair.right"
                  type="text"
                  :placeholder="pair.right_image ? '(이미지 사용 중)' : `우측 ${idx + 1}`"
                  class="w-full bg-white border border-[#E8E8E8] rounded-[8px] px-3 py-2 text-[13px] focus:border-[#4CAF50] focus:outline-none transition-colors"
                />
                <div v-if="pair.right_image" class="flex items-center gap-2 mt-1.5">
                  <img :src="pair.right_image" class="w-12 h-12 rounded-[6px] object-cover border border-[#E8E8E8]" />
                  <button type="button" @click="removeMatchPairImage(idx)" class="text-[11px] text-red-400 hover:text-red-600">삭제</button>
                </div>
                <label v-if="!pair.right_image" class="inline-flex items-center gap-1 cursor-pointer text-[11px] text-[#2196F3] hover:underline mt-1">
                  <span v-if="matchPairImageUploading === idx">업로드 중...</span>
                  <span v-else>🖼 이미지 첨부</span>
                  <input type="file" accept="image/*" class="hidden" :disabled="matchPairImageUploading === idx" @change="handleMatchPairImageUpload($event, idx)" />
                </label>
              </div>
              <button
                type="button"
                @click="removeMatchPair(idx)"
                :disabled="form.match_pairs.length <= 1"
                class="shrink-0 w-6 h-6 mt-1.5 flex items-center justify-center rounded-full text-red-400 hover:bg-red-50 disabled:opacity-30 transition-colors"
              >
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
            </div>
          </div>
        </div>
        <button type="button" @click="addMatchPair" class="mt-2 text-[12px] text-[#4CAF50] font-medium hover:underline">+ 쌍 추가</button>
        <p class="mt-1 text-[11px] text-[#888]">좌측 멘트와 우측 답(텍스트 또는 이미지)을 짝지어 입력. (4~5개 권장)</p>
      </div>

      <!-- 그림카드 텍스트/음성: 허용 정답 -->
      <div v-if="form.question_type === 'image_text' || form.question_type === 'image_voice'">
        <label class="block text-[13px] font-semibold text-[#333] mb-1.5">허용 정답</label>
        <div class="flex flex-col gap-2">
          <div v-for="(_, idx) in form.accept_answers" :key="idx" class="flex items-center gap-2">
            <input
              v-model="form.accept_answers[idx]"
              type="text"
              :placeholder="`정답 ${idx + 1}`"
              class="flex-1 bg-[#F8F8F8] border border-[#E8E8E8] rounded-[10px] px-3 py-2 text-[13px] focus:border-[#4CAF50] focus:outline-none transition-colors"
            />
            <button
              type="button"
              @click="removeAcceptAnswer(idx)"
              :disabled="form.accept_answers.length <= 1"
              class="shrink-0 w-6 h-6 flex items-center justify-center rounded-full text-red-400 hover:bg-red-50 disabled:opacity-30 transition-colors"
            >
              <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
        </div>
        <button type="button" @click="addAcceptAnswer" class="mt-2 text-[12px] text-[#4CAF50] font-medium hover:underline">+ 정답 추가</button>
        <p class="mt-1 text-[11px] text-[#888]">
          {{ form.question_type === 'image_voice' ? '음성 인식 텍스트에 포함되면 정답 판정.' : '정확히 일치하는 텍스트 입력. (대소문자 무시)' }}
        </p>
      </div>

      <!-- 이미지 업로드 -->
      <div>
        <label class="block text-[13px] font-semibold text-[#333] mb-1.5">
          이미지 {{ form.question_type === 'multiple_choice' || form.question_type === 'matching' ? '(선택)' : '(필수)' }}
        </label>
        <div v-if="form.image_url" class="flex items-center gap-3 mb-2">
          <img :src="form.image_url" alt="업로드된 이미지" class="w-16 h-16 rounded-[8px] object-cover border border-[#E8E8E8]" />
          <button type="button" @click="removeImage" class="text-red-500 hover:text-red-700 text-[12px] font-medium">이미지 삭제</button>
        </div>
        <label
          v-if="!form.image_url"
          class="flex flex-col items-center justify-center w-full h-20 border-2 border-dashed border-[#E8E8E8] rounded-[12px] cursor-pointer hover:border-[#4CAF50] transition-colors"
          :class="{ 'opacity-50 pointer-events-none': imageUploading }"
        >
          <span class="text-[12px] text-[#888]">{{ imageUploading ? '업로드 중...' : '클릭하여 이미지 선택' }}</span>
          <input type="file" accept="image/*" class="hidden" @change="handleImageUpload" />
        </label>
      </div>

      <!-- 순서 -->
      <div>
        <label class="block text-[13px] font-semibold text-[#333] mb-1.5">순서</label>
        <input
          v-model.number="form.order"
          type="number"
          min="1"
          class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-2.5 text-[14px] focus:border-[#4CAF50] focus:outline-none transition-colors"
        />
      </div>
    </div>

    <!-- 하단 버튼 -->
    <div class="flex items-center gap-3 pt-4 border-t border-[#F0F0F0]">
      <button
        @click="handleSubmit"
        :disabled="saving || imageUploading"
        class="flex-1 bg-[#4CAF50] hover:bg-[#388E3C] disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-[12px] px-5 py-3 text-[14px] font-medium active:scale-[0.98] transition-all"
      >
        {{ saving ? '저장 중...' : question?.question_id ? '수정하기' : '추가하기' }}
      </button>
      <button
        @click="emit('cancel')"
        class="bg-[#F8F8F8] hover:bg-[#E8E8E8] text-[#555] rounded-[12px] px-5 py-3 text-[14px] font-medium active:scale-[0.98] transition-all"
      >
        취소
      </button>
    </div>
  </div>
</template>
