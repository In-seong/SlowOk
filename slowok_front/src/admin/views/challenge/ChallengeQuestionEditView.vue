<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { Challenge, ChallengeQuestion } from '@shared/types'
import { adminApi } from '@shared/api/adminApi'
import { useToastStore } from '@shared/stores/toastStore'
import QuestionPreviewCard from '@admin/components/challenge/QuestionPreviewCard.vue'
import QuestionEditPanel from '@admin/components/challenge/QuestionEditPanel.vue'
import QuestionTypeSelector from '@admin/components/challenge/QuestionTypeSelector.vue'

const route = useRoute()
const router = useRouter()
const toast = useToastStore()

const challengeId = Number(route.params.id)
const challenge = ref<Challenge | null>(null)
const questions = ref<ChallengeQuestion[]>([])
const loading = ref(true)
const fetchError = ref('')

// 편집 패널 상태
const editingQuestion = ref<Partial<ChallengeQuestion> | null>(null)
const showTypeSelector = ref(false)

const sortedQuestions = computed(() =>
  [...questions.value].sort((a, b) => a.order - b.order)
)

async function fetchData() {
  loading.value = true
  fetchError.value = ''
  try {
    const [challengeRes, questionsRes] = await Promise.all([
      adminApi.getChallenge(challengeId),
      adminApi.getChallengeQuestions(challengeId),
    ])
    if (challengeRes.data.success) challenge.value = challengeRes.data.data
    if (questionsRes.data.success) questions.value = questionsRes.data.data
  } catch (e: any) {
    fetchError.value = e.response?.data?.message || '데이터를 불러오지 못했습니다.'
  } finally {
    loading.value = false
  }
}

function goBack() {
  router.push({ name: 'challenges' })
}

// 문항 추가: 유형 선택기 표시
function showAddQuestion() {
  editingQuestion.value = null
  showTypeSelector.value = true
}

// 유형 선택 후 빈 폼 열기
function onTypeSelected(type: string) {
  showTypeSelector.value = false
  editingQuestion.value = {
    question_type: type,
    challenge_id: challengeId,
    order: questions.value.length + 1,
  }
}

// 문항 수정
function editQuestion(question: ChallengeQuestion) {
  showTypeSelector.value = false
  editingQuestion.value = { ...question }
}

// 저장 후 갱신
async function onSave() {
  editingQuestion.value = null
  showTypeSelector.value = false
  try {
    const res = await adminApi.getChallengeQuestions(challengeId)
    if (res.data.success) questions.value = res.data.data
  } catch {
    toast.error('문항 목록 갱신에 실패했습니다.')
  }
}

function onCancel() {
  editingQuestion.value = null
  showTypeSelector.value = false
}

// 문항 삭제
async function deleteQuestion(question: ChallengeQuestion) {
  if (!confirm(`문항 #${question.order}을(를) 정말 삭제하시겠습니까?`)) return
  try {
    await adminApi.deleteQuestion(question.question_id)
    questions.value = questions.value.filter(q => q.question_id !== question.question_id)
    if (editingQuestion.value?.question_id === question.question_id) {
      editingQuestion.value = null
    }
  } catch (e: any) {
    toast.error(e.response?.data?.message || '문항 삭제에 실패했습니다.')
  }
}

// 순서 변경
async function moveQuestion(question: ChallengeQuestion, direction: 'up' | 'down') {
  const sorted = sortedQuestions.value
  const idx = sorted.findIndex(q => q.question_id === question.question_id)
  if (idx < 0) return

  const swapIdx = direction === 'up' ? idx - 1 : idx + 1
  if (swapIdx < 0 || swapIdx >= sorted.length) return

  const current = sorted[idx]
  const swap = sorted[swapIdx]
  if (!current || !swap) return

  const currentOrder = current.order
  const swapOrder = swap.order

  try {
    await Promise.all([
      adminApi.updateQuestion(current.question_id, { order: swapOrder }),
      adminApi.updateQuestion(swap.question_id, { order: currentOrder }),
    ])
    current.order = swapOrder
    swap.order = currentOrder
  } catch (e: any) {
    toast.error(e.response?.data?.message || '순서 변경에 실패했습니다.')
  }
}

onMounted(fetchData)
</script>

<template>
  <div class="h-[calc(100vh-64px)] flex flex-col">
    <!-- 헤더 -->
    <div class="flex items-center gap-3 px-6 py-4 border-b border-[#E8E8E8] bg-white shrink-0">
      <button
        @click="goBack"
        class="flex items-center gap-1 text-[14px] text-[#4CAF50] font-medium hover:text-[#388E3C] transition-colors"
      >
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
        목록
      </button>
      <div class="h-5 w-px bg-[#E0E0E0]" />
      <h1 class="text-[16px] font-bold text-[#333] truncate">
        {{ challenge?.title ?? '...' }}
      </h1>
      <span class="text-[14px] text-[#888]">문항 편집</span>
      <div class="flex-1" />
      <button
        @click="showAddQuestion"
        class="bg-[#4CAF50] hover:bg-[#388E3C] text-white rounded-[10px] px-4 py-2 text-[13px] font-medium active:scale-[0.98] transition-all"
      >
        + 문항 추가
      </button>
    </div>

    <!-- 로딩 -->
    <div v-if="loading" class="flex-1 flex items-center justify-center">
      <div class="text-center">
        <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin mx-auto mb-3" />
        <p class="text-[13px] text-[#888]">불러오는 중...</p>
      </div>
    </div>

    <!-- 에러 -->
    <div v-else-if="fetchError" class="flex-1 flex items-center justify-center">
      <div class="text-center">
        <p class="text-red-500 text-[14px] mb-3">{{ fetchError }}</p>
        <button @click="fetchData" class="bg-[#4CAF50] hover:bg-[#388E3C] text-white rounded-[10px] px-5 py-2 text-[13px] font-medium">다시 시도</button>
      </div>
    </div>

    <!-- 2-column 레이아웃 -->
    <div v-else class="flex-1 flex overflow-hidden">
      <!-- 좌측: 미리보기 -->
      <div class="flex-1 overflow-y-auto bg-[#F0F0F0] p-6">
        <div class="max-w-[402px] mx-auto">
          <!-- 빈 상태 -->
          <div v-if="sortedQuestions.length === 0" class="bg-white rounded-[16px] p-8 text-center">
            <p class="text-[14px] text-[#888] mb-4">등록된 문항이 없습니다.</p>
            <button
              @click="showAddQuestion"
              class="bg-[#4CAF50] hover:bg-[#388E3C] text-white rounded-[10px] px-5 py-2.5 text-[13px] font-medium active:scale-[0.98] transition-all"
            >
              + 첫 문항 추가
            </button>
          </div>

          <!-- 문항 목록 -->
          <div v-else class="space-y-4">
            <QuestionPreviewCard
              v-for="(q, idx) in sortedQuestions"
              :key="q.question_id"
              :question="q"
              :index="idx"
              :total="sortedQuestions.length"
              :selected="editingQuestion?.question_id === q.question_id"
              @edit="editQuestion(q)"
              @delete="deleteQuestion(q)"
              @move-up="moveQuestion(q, 'up')"
              @move-down="moveQuestion(q, 'down')"
            />
          </div>

          <!-- 하단 추가 버튼 -->
          <div v-if="sortedQuestions.length > 0" class="mt-4">
            <button
              @click="showAddQuestion"
              class="w-full py-3 border-2 border-dashed border-[#C8E6C9] rounded-[16px] text-[14px] font-medium text-[#4CAF50] hover:bg-[#E8F5E9] hover:border-[#4CAF50] transition-all"
            >
              + 문항 추가
            </button>
          </div>
        </div>
      </div>

      <!-- 우측: 편집 패널 -->
      <div
        v-if="editingQuestion || showTypeSelector"
        class="w-[400px] shrink-0 border-l border-[#E8E8E8] bg-white p-5 overflow-y-auto"
      >
        <!-- 유형 선택기 -->
        <QuestionTypeSelector
          v-if="showTypeSelector"
          @select="onTypeSelected"
        />

        <!-- 편집 패널 -->
        <QuestionEditPanel
          v-else-if="editingQuestion"
          :question="editingQuestion"
          :challenge-id="challengeId"
          @save="onSave"
          @cancel="onCancel"
        />
      </div>

      <!-- 편집 패널 미선택 시 안내 -->
      <div
        v-else
        class="w-[400px] shrink-0 border-l border-[#E8E8E8] bg-[#FAFAFA] flex items-center justify-center"
      >
        <div class="text-center px-8">
          <div class="w-16 h-16 rounded-full bg-[#E8F5E9] flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-[#4CAF50]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 3a2.828 2.828 0 114 4L7.5 20.5 2 22l1.5-5.5L17 3z" />
            </svg>
          </div>
          <p class="text-[14px] font-medium text-[#555] mb-1">문항을 선택하세요</p>
          <p class="text-[12px] text-[#999]">좌측 미리보기에서 ✎ 버튼을 클릭하면<br />여기에서 편집할 수 있습니다.</p>
        </div>
      </div>
    </div>
  </div>
</template>
