<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { Challenge, ChallengeQuestion } from '@shared/types'
import { adminApi } from '@shared/api/adminApi'
import { useToastStore } from '@shared/stores/toastStore'
import BottomSheet from '../../components/BottomSheet.vue'

const route = useRoute()
const router = useRouter()
const toast = useToastStore()

const challengeId = Number(route.params.id)
const challenge = ref<Challenge | null>(null)
const questions = ref<ChallengeQuestion[]>([])
const loading = ref(true)

// 편집 상태
const showEdit = ref(false)
const editingQuestion = ref<Partial<ChallengeQuestion> | null>(null)
const editSaving = ref(false)
const editError = ref('')

// 유형 선택
const showTypeSelector = ref(false)
const QUESTION_TYPES = [
  { value: 'multiple_choice', label: '객관식', icon: '📝' },
  { value: 'matching', label: '매칭', icon: '🔗' },
  { value: 'image_choice', label: '이미지 선택', icon: '🖼️' },
  { value: 'image_text', label: '이미지+텍스트', icon: '📷' },
  { value: 'image_voice', label: '이미지+음성', icon: '🎤' },
]

const sortedQuestions = computed(() => [...questions.value].sort((a, b) => a.order - b.order))

async function fetchData() {
  loading.value = true
  try {
    const [cRes, qRes] = await Promise.all([
      adminApi.getChallenge(challengeId),
      adminApi.getChallengeQuestions(challengeId),
    ])
    if (cRes.data.success) challenge.value = cRes.data.data
    if (qRes.data.success) questions.value = qRes.data.data
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '데이터를 불러오지 못했습니다.')
  } finally {
    loading.value = false
  }
}

function startAdd() {
  showTypeSelector.value = true
}

function onTypeSelected(type: string) {
  showTypeSelector.value = false
  editingQuestion.value = {
    question_type: type,
    challenge_id: challengeId,
    order: questions.value.length + 1,
    content: '',
    options: ['', '', '', ''],
    correct_answer: '',
  }
  editError.value = ''
  showEdit.value = true
}

function startEdit(q: ChallengeQuestion) {
  editingQuestion.value = { ...q, options: Array.isArray(q.options) ? [...q.options] : ['', '', '', ''] }
  editError.value = ''
  showEdit.value = true
}

async function handleSave() {
  if (!editingQuestion.value) return
  if (!editingQuestion.value.content?.trim()) { editError.value = '문항 내용을 입력해주세요.'; return }

  editSaving.value = true
  editError.value = ''
  try {
    const payload = {
      challenge_id: challengeId,
      question_type: editingQuestion.value.question_type,
      content: editingQuestion.value.content,
      options: editingQuestion.value.options,
      correct_answer: editingQuestion.value.correct_answer,
      order: editingQuestion.value.order,
      image_url: editingQuestion.value.image_url,
    }
    if (editingQuestion.value.question_id) {
      await adminApi.updateQuestion(editingQuestion.value.question_id, payload)
    } else {
      await adminApi.createQuestion(payload)
    }
    showEdit.value = false
    editingQuestion.value = null
    const res = await adminApi.getChallengeQuestions(challengeId)
    if (res.data.success) questions.value = res.data.data
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    editError.value = err.response?.data?.message || '저장에 실패했습니다.'
  } finally {
    editSaving.value = false
  }
}

async function handleDelete(q: ChallengeQuestion) {
  if (!confirm(`문항 #${q.order}을(를) 삭제하시겠습니까?`)) return
  try {
    await adminApi.deleteQuestion(q.question_id)
    questions.value = questions.value.filter(x => x.question_id !== q.question_id)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '삭제에 실패했습니다.')
  }
}

function addOption() {
  if (editingQuestion.value && Array.isArray(editingQuestion.value.options)) {
    editingQuestion.value.options.push('')
  }
}

function removeOption(idx: number) {
  if (editingQuestion.value && Array.isArray(editingQuestion.value.options) && editingQuestion.value.options.length > 2) {
    editingQuestion.value.options.splice(idx, 1)
  }
}

function typeLabel(type: string): string {
  return QUESTION_TYPES.find(t => t.value === type)?.label || type
}

onMounted(fetchData)
</script>

<template>
  <div class="min-h-screen bg-[#FAFAFA]">
    <header class="sticky top-0 z-40 bg-white border-b border-[#E8E8E8] h-[56px] flex items-center px-4">
      <button @click="router.back()" class="w-10 h-10 flex items-center justify-center">
        <svg class="w-5 h-5 text-[#333]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <h1 class="flex-1 text-center text-[16px] font-bold text-[#333] truncate px-2">{{ challenge?.title || '문항 편집' }}</h1>
      <button @click="startAdd" class="w-10 h-10 flex items-center justify-center text-[#4CAF50]">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </button>
    </header>

    <div v-if="loading" class="flex justify-center py-20">
      <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin" />
    </div>

    <div v-else class="px-4 py-4 space-y-3">
      <div v-if="sortedQuestions.length === 0" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-8 text-center">
        <p class="text-[14px] text-[#888]">문항이 없습니다. 상단 [+] 버튼으로 추가하세요.</p>
      </div>

      <div v-for="q in sortedQuestions" :key="q.question_id" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4">
        <div class="flex items-start justify-between gap-2">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <span class="text-[12px] font-bold text-[#4CAF50]">#{{ q.order }}</span>
              <span class="text-[11px] px-2 py-0.5 bg-[#F0F0F0] text-[#555] rounded-[6px]">{{ typeLabel(q.question_type || '') }}</span>
            </div>
            <p class="text-[14px] text-[#333] line-clamp-2">{{ q.content }}</p>
          </div>
        </div>
        <div class="flex gap-2 mt-3">
          <button @click="startEdit(q)" class="text-[12px] font-medium px-3 py-1.5 bg-[#E8F5E9] text-[#4CAF50] rounded-[8px] active:opacity-70">편집</button>
          <button @click="handleDelete(q)" class="text-[12px] font-medium px-3 py-1.5 bg-[#FFF0F0] text-[#FF4444] rounded-[8px] active:opacity-70">삭제</button>
        </div>
      </div>

      <!-- 문항 추가 버튼 -->
      <button @click="startAdd" class="w-full py-4 border-2 border-dashed border-[#DDD] rounded-[16px] text-[14px] text-[#888] active:bg-[#F5F5F5]">
        + 문항 추가
      </button>
    </div>

    <!-- 유형 선택 바텀시트 -->
    <BottomSheet v-model="showTypeSelector" title="문항 유형 선택">
      <div class="space-y-1 pb-4">
        <button
          v-for="t in QUESTION_TYPES"
          :key="t.value"
          @click="onTypeSelected(t.value)"
          class="w-full flex items-center gap-3 px-4 py-3.5 rounded-[12px] active:bg-[#F0F0F0]"
        >
          <span class="text-[24px]">{{ t.icon }}</span>
          <span class="text-[15px] font-medium text-[#333]">{{ t.label }}</span>
        </button>
      </div>
    </BottomSheet>

    <!-- 문항 편집 바텀시트 (풀스크린) -->
    <BottomSheet v-model="showEdit" :title="editingQuestion?.question_id ? '문항 편집' : '문항 추가'" max-height="100vh">
      <div v-if="editingQuestion" class="space-y-4 pb-4">
        <div v-if="editError" class="bg-red-50 text-[#FF4444] text-[13px] px-4 py-2.5 rounded-[10px]">{{ editError }}</div>

        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">유형</label>
          <p class="text-[14px] text-[#333] px-4 py-3 bg-[#F5F5F5] rounded-[12px]">{{ typeLabel(editingQuestion.question_type || '') }}</p>
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">문항 내용 <span class="text-[#FF4444]">*</span></label>
          <textarea v-model="editingQuestion.content" rows="3" placeholder="문항 내용을 입력하세요" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50] resize-none" />
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">순서</label>
          <input v-model.number="editingQuestion.order" type="number" min="1" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
        </div>

        <!-- 보기 (객관식 계열) -->
        <div v-if="editingQuestion.question_type === 'multiple_choice' || editingQuestion.question_type === 'image_choice'">
          <label class="block text-[13px] font-medium text-[#555] mb-2">보기</label>
          <div class="space-y-2">
            <div v-for="(_, idx) in editingQuestion.options" :key="idx" class="flex items-center gap-2">
              <span class="text-[13px] text-[#888] w-6 text-center">{{ idx + 1 }}</span>
              <input v-model="editingQuestion.options![idx]" type="text" :placeholder="`보기 ${idx + 1}`" class="flex-1 px-3 py-2.5 border border-[#E8E8E8] rounded-[10px] text-[14px] focus:outline-none focus:border-[#4CAF50]" />
              <button
                @click="editingQuestion.correct_answer = editingQuestion.options![idx]"
                class="shrink-0 text-[11px] px-2 py-1 rounded-[6px]"
                :class="editingQuestion.correct_answer === editingQuestion.options![idx] && editingQuestion.options![idx] ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#888]'"
              >정답</button>
              <button v-if="editingQuestion.options!.length > 2" @click="removeOption(idx)" class="shrink-0 text-[#FF4444] text-[14px] w-8 h-8 flex items-center justify-center">&times;</button>
            </div>
          </div>
          <button @click="addOption" class="mt-2 text-[13px] text-[#4CAF50] font-medium active:opacity-70">+ 보기 추가</button>
        </div>

        <!-- 정답 (매칭 등) -->
        <div v-else>
          <label class="block text-[13px] font-medium text-[#555] mb-1">정답</label>
          <input v-model="editingQuestion.correct_answer" type="text" placeholder="정답을 입력하세요" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
        </div>

        <button @click="handleSave" :disabled="editSaving" class="w-full py-3.5 bg-[#4CAF50] text-white rounded-[12px] text-[15px] font-semibold disabled:opacity-50 active:bg-[#388E3C]">
          {{ editSaving ? '저장 중...' : '저장' }}
        </button>
      </div>
    </BottomSheet>
  </div>
</template>
