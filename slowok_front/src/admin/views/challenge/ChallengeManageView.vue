<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@shared/api'
import { adminApi } from '@shared/api/adminApi'
import type { Challenge, ChallengeQuestion, LearningCategory, ApiResponse, MatchPair } from '@shared/types'
import { compressImage } from '@shared/utils/imageCompress'
import { useToastStore } from '@shared/stores/toastStore'

const toast = useToastStore()

const challenges = ref<Challenge[]>([])
const categories = ref<LearningCategory[]>([])
const loading = ref(true)
const error = ref('')

// 챌린지 모달 상태
const showModal = ref(false)
const modalMode = ref<'create' | 'edit'>('create')
const saving = ref(false)
const modalError = ref('')
const editingId = ref<number | null>(null)

const form = ref({
  title: '',
  category_id: '' as number | '',
  challenge_type: '',
  difficulty_level: 1,
})

// 문항 관리 상태
const expandedChallengeId = ref<number | null>(null)
const questions = ref<ChallengeQuestion[]>([])
const questionsLoading = ref(false)
const showQuestionModal = ref(false)
const editingQuestion = ref<Partial<ChallengeQuestion> | null>(null)
const questionSaving = ref(false)
const questionModalError = ref('')
const imageUploading = ref(false)

const QUESTION_TYPES = [
  { value: 'multiple_choice', label: '객관식 (4지선다)' },
  { value: 'matching', label: '매칭 게임' },
  { value: 'image_choice', label: '그림카드 택1' },
  { value: 'image_text', label: '그림카드 텍스트 입력' },
  { value: 'image_voice', label: '그림카드 음성 답변' },
] as const

const questionForm = ref({
  challenge_id: 0,
  content: '',
  question_type: 'multiple_choice',
  options: ['', '', '', ''],
  correct_answer: '',
  image_url: '',
  order: 0,
  match_pairs: [{ left: '', right: '', right_image: null }] as MatchPair[],
  accept_answers: [''] as string[],
})

function resetForm() {
  form.value = {
    title: '',
    category_id: '',
    challenge_type: '',
    difficulty_level: 1,
  }
  modalError.value = ''
  editingId.value = null
}

function resetQuestionForm(challengeId: number) {
  questionForm.value = {
    challenge_id: challengeId,
    content: '',
    question_type: 'multiple_choice',
    options: ['', '', '', ''],
    correct_answer: '',
    image_url: '',
    order: questions.value.length + 1,
    match_pairs: [{ left: '', right: '' }],
    accept_answers: [''],
  }
  questionModalError.value = ''
  editingQuestion.value = null
}

function openCreateModal() {
  resetForm()
  modalMode.value = 'create'
  showModal.value = true
}

function openEditModal(challenge: Challenge) {
  modalMode.value = 'edit'
  editingId.value = challenge.challenge_id
  form.value = {
    title: challenge.title,
    category_id: challenge.category_id,
    challenge_type: challenge.challenge_type,
    difficulty_level: challenge.difficulty_level,
  }
  modalError.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  resetForm()
}

async function fetchData() {
  loading.value = true
  error.value = ''
  try {
    const [challengesRes, catsRes] = await Promise.all([
      api.get<ApiResponse<Challenge[]>>('/admin/challenges'),
      api.get<ApiResponse<LearningCategory[]>>('/admin/learning-categories'),
    ])
    if (challengesRes.data.success) challenges.value = challengesRes.data.data
    if (catsRes.data.success) categories.value = catsRes.data.data
  } catch (e: any) {
    error.value = e.response?.data?.message || '데이터를 불러오지 못했습니다.'
  } finally {
    loading.value = false
  }
}

function validateForm(): string | null {
  if (!form.value.title.trim()) return '제목을 입력해주세요.'
  if (!form.value.category_id) return '카테고리를 선택해주세요.'
  if (!form.value.challenge_type.trim()) return '챌린지 유형을 입력해주세요.'
  return null
}

async function handleSubmit() {
  const validationError = validateForm()
  if (validationError) {
    modalError.value = validationError
    return
  }

  saving.value = true
  modalError.value = ''

  const payload = {
    title: form.value.title.trim(),
    category_id: Number(form.value.category_id),
    challenge_type: form.value.challenge_type.trim(),
    difficulty_level: form.value.difficulty_level,
  }

  try {
    if (modalMode.value === 'edit' && editingId.value) {
      const res = await api.put<ApiResponse<Challenge>>(`/admin/challenges/${editingId.value}`, payload)
      if (res.data.success) {
        const idx = challenges.value.findIndex((c) => c.challenge_id === editingId.value)
        if (idx !== -1) challenges.value[idx] = res.data.data
      }
    } else {
      const res = await api.post<ApiResponse<Challenge>>('/admin/challenges', payload)
      if (res.data.success) challenges.value.push(res.data.data)
    }
    closeModal()
  } catch (e: any) {
    modalError.value = e.response?.data?.message || '저장에 실패했습니다.'
  } finally {
    saving.value = false
  }
}

async function deleteChallenge(challenge: Challenge) {
  if (!confirm(`"${challenge.title}" 챌린지를 정말 삭제하시겠습니까?\n이 작업은 되돌릴 수 없습니다.`)) return
  try {
    await api.delete(`/admin/challenges/${challenge.challenge_id}`)
    challenges.value = challenges.value.filter((c) => c.challenge_id !== challenge.challenge_id)
    if (expandedChallengeId.value === challenge.challenge_id) {
      expandedChallengeId.value = null
      questions.value = []
    }
  } catch (e: any) {
    toast.error(e.response?.data?.message || '삭제에 실패했습니다.')
  }
}

function getCategoryName(categoryId: number): string {
  return categories.value.find((c) => c.category_id === categoryId)?.name || '-'
}

function difficultyStars(level: number): string {
  return '★'.repeat(level) + '☆'.repeat(5 - level)
}

// 문항 관리 함수들
async function toggleQuestions(challengeId: number) {
  if (expandedChallengeId.value === challengeId) {
    expandedChallengeId.value = null
    questions.value = []
    return
  }
  expandedChallengeId.value = challengeId
  await fetchQuestions(challengeId)
}

async function fetchQuestions(challengeId: number) {
  questionsLoading.value = true
  try {
    const res = await adminApi.getChallengeQuestions(challengeId)
    if (res.data.success) {
      questions.value = res.data.data
    }
  } catch (e: any) {
    toast.error(e.response?.data?.message || '문항을 불러오지 못했습니다.')
  } finally {
    questionsLoading.value = false
  }
}

function openQuestionCreateModal(challengeId: number) {
  resetQuestionForm(challengeId)
  showQuestionModal.value = true
}

function openQuestionEditModal(question: ChallengeQuestion) {
  editingQuestion.value = question
  const opts = Array.isArray(question.options) ? [...question.options] : ['', '', '', '']
  while (opts.length < 4) opts.push('')
  const pairs = Array.isArray(question.match_pairs) && question.match_pairs.length > 0
    ? question.match_pairs.map(p => ({ left: p.left, right: p.right, right_image: p.right_image ?? null }))
    : [{ left: '', right: '', right_image: null }]
  const accepts = Array.isArray(question.accept_answers) && question.accept_answers.length > 0
    ? [...question.accept_answers]
    : ['']
  questionForm.value = {
    challenge_id: question.challenge_id,
    content: question.content,
    question_type: question.question_type || 'multiple_choice',
    options: opts,
    correct_answer: question.correct_answer || '',
    image_url: question.image_url || '',
    order: question.order,
    match_pairs: pairs,
    accept_answers: accepts,
  }
  questionModalError.value = ''
  showQuestionModal.value = true
}

function addMatchPair() {
  questionForm.value.match_pairs.push({ left: '', right: '', right_image: null })
}

const matchPairImageUploading = ref<number | null>(null)

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
      questionForm.value.match_pairs = questionForm.value.match_pairs.map((p, i) =>
        i === idx ? { ...p, right_image: url, right: p.right || '' } : p
      )
    }
  } catch (e: any) {
    questionModalError.value = e.response?.data?.message || '이미지 업로드에 실패했습니다.'
  } finally {
    matchPairImageUploading.value = null
    target.value = ''
  }
}

function removeMatchPairImage(idx: number) {
  questionForm.value.match_pairs = questionForm.value.match_pairs.map((p, i) =>
    i === idx ? { ...p, right_image: null } : p
  )
}

function removeMatchPair(idx: number) {
  if (questionForm.value.match_pairs.length > 1) {
    questionForm.value.match_pairs.splice(idx, 1)
  }
}

function addAcceptAnswer() {
  questionForm.value.accept_answers.push('')
}

function removeAcceptAnswer(idx: number) {
  if (questionForm.value.accept_answers.length > 1) {
    questionForm.value.accept_answers.splice(idx, 1)
  }
}

function getQuestionTypeLabel(type: string | null): string {
  if (!type) return '객관식'
  const found = QUESTION_TYPES.find(t => t.value === type)
  return found ? found.label : type
}

function closeQuestionModal() {
  showQuestionModal.value = false
  editingQuestion.value = null
  questionModalError.value = ''
}

function validateQuestionForm(): string | null {
  if (!questionForm.value.content.trim()) return '문항 내용을 입력해주세요.'

  const type = questionForm.value.question_type

  if (type === 'multiple_choice' || type === 'image_choice') {
    const filledOptions = questionForm.value.options.filter((o) => o.trim() !== '')
    if (filledOptions.length < 2) return '최소 2개의 보기를 입력해주세요.'
    if (!questionForm.value.correct_answer.trim()) return '정답을 선택해주세요.'
    if (!filledOptions.includes(questionForm.value.correct_answer)) return '정답은 보기 중 하나여야 합니다.'
    if (type === 'image_choice' && !questionForm.value.image_url) return '그림카드 유형은 이미지가 필수입니다.'
  } else if (type === 'matching') {
    const filledPairs = questionForm.value.match_pairs.filter(p => p.left.trim() && (p.right.trim() || p.right_image))
    if (filledPairs.length < 2) return '최소 2개의 매칭 쌍을 입력해주세요. (우측은 텍스트 또는 이미지 중 하나 필수)'
  } else if (type === 'image_text' || type === 'image_voice') {
    if (!questionForm.value.image_url) return '그림카드 유형은 이미지가 필수입니다.'
    const filledAnswers = questionForm.value.accept_answers.filter(a => a.trim() !== '')
    if (filledAnswers.length < 1) return '최소 1개의 허용 정답을 입력해주세요.'
  }

  return null
}

async function handleQuestionSubmit() {
  const validationError = validateQuestionForm()
  if (validationError) {
    questionModalError.value = validationError
    return
  }

  questionSaving.value = true
  questionModalError.value = ''

  const type = questionForm.value.question_type
  const filteredOptions = questionForm.value.options.filter((o) => o.trim() !== '')
  const filteredPairs = questionForm.value.match_pairs.filter(p => p.left.trim() && (p.right.trim() || p.right_image))
  const filteredAccepts = questionForm.value.accept_answers.filter(a => a.trim() !== '')

  const payload: Record<string, unknown> = {
    challenge_id: questionForm.value.challenge_id,
    content: questionForm.value.content.trim(),
    question_type: type,
    image_url: questionForm.value.image_url || null,
    order: questionForm.value.order,
    options: (type === 'multiple_choice' || type === 'image_choice') ? filteredOptions : null,
    correct_answer: (type === 'multiple_choice' || type === 'image_choice') ? questionForm.value.correct_answer.trim() : null,
    match_pairs: type === 'matching' ? filteredPairs : null,
    accept_answers: (type === 'image_text' || type === 'image_voice') ? filteredAccepts : null,
  }

  try {
    if (editingQuestion.value?.question_id) {
      await adminApi.updateQuestion(editingQuestion.value.question_id, payload)
    } else {
      await adminApi.createQuestion(payload)
    }
    closeQuestionModal()
    if (expandedChallengeId.value !== null) {
      await fetchQuestions(expandedChallengeId.value)
    }
  } catch (e: any) {
    questionModalError.value = e.response?.data?.message || '문항 저장에 실패했습니다.'
  } finally {
    questionSaving.value = false
  }
}

async function deleteQuestion(question: ChallengeQuestion) {
  if (!confirm(`문항 #${question.order}을(를) 정말 삭제하시겠습니까?`)) return
  try {
    await adminApi.deleteQuestion(question.question_id)
    if (expandedChallengeId.value !== null) {
      await fetchQuestions(expandedChallengeId.value)
    }
  } catch (e: any) {
    toast.error(e.response?.data?.message || '문항 삭제에 실패했습니다.')
  }
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
      questionForm.value.image_url = res.data.data.url
    }
  } catch (e: any) {
    questionModalError.value = e.response?.data?.message || '이미지 업로드에 실패했습니다.'
  } finally {
    imageUploading.value = false
    target.value = ''
  }
}

function removeImage() {
  questionForm.value.image_url = ''
}

function selectCorrectAnswer(optionText: string) {
  questionForm.value.correct_answer = optionText
}

onMounted(fetchData)
</script>

<template>
  <div class="p-6">
    <div class="max-w-[1200px] mx-auto">
      <!-- 상단 -->
      <div class="flex items-center justify-between mb-4">
        <p class="text-[14px] text-[#888]">챌린지를 관리합니다.</p>
        <button
          @click="openCreateModal"
          class="bg-[#4CAF50] hover:bg-[#388E3C] text-white rounded-[12px] px-5 py-2.5 text-[14px] font-medium active:scale-[0.98] transition-all"
        >
          + 챌린지 추가
        </button>
      </div>

      <!-- 로딩 -->
      <div v-if="loading" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center">
        <p class="text-[#888]">챌린지 목록을 불러오는 중...</p>
      </div>

      <!-- 에러 -->
      <div v-else-if="error" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center">
        <p class="text-red-500 mb-3">{{ error }}</p>
        <button
          @click="fetchData"
          class="bg-[#4CAF50] hover:bg-[#388E3C] text-white rounded-[12px] px-5 py-2.5 text-[14px] font-medium active:scale-[0.98] transition-all"
        >
          다시 시도
        </button>
      </div>

      <!-- 빈 상태 -->
      <div v-else-if="challenges.length === 0" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center">
        <p class="text-[#888]">등록된 챌린지가 없습니다.</p>
      </div>

      <!-- 테이블 -->
      <div v-else class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-[14px]">
            <thead>
              <tr class="border-b border-[#E8E8E8] bg-[#FAFAFA]">
                <th class="px-5 py-3 font-semibold text-[#555]">제목</th>
                <th class="px-5 py-3 font-semibold text-[#555]">카테고리</th>
                <th class="px-5 py-3 font-semibold text-[#555]">유형</th>
                <th class="px-5 py-3 font-semibold text-[#555]">난이도</th>
                <th class="px-5 py-3 font-semibold text-[#555]">액션</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="challenge in challenges" :key="challenge.challenge_id">
                <tr class="border-b border-[#F0F0F0] hover:bg-[#FAFAFA] transition-colors">
                  <td class="px-5 py-3.5 text-[#333] font-medium">{{ challenge.title }}</td>
                  <td class="px-5 py-3.5 text-[#555]">{{ challenge.category?.name || getCategoryName(challenge.category_id) }}</td>
                  <td class="px-5 py-3.5">
                    <span class="px-2 py-0.5 rounded-full text-[12px] font-medium bg-indigo-50 text-indigo-600">
                      {{ challenge.challenge_type }}
                    </span>
                  </td>
                  <td class="px-5 py-3.5 text-[#888] text-[13px]">
                    {{ difficultyStars(challenge.difficulty_level) }}
                  </td>
                  <td class="px-5 py-3.5">
                    <div class="flex items-center gap-3">
                      <button
                        @click="toggleQuestions(challenge.challenge_id)"
                        class="border border-[#4CAF50] text-[#4CAF50] hover:bg-[#4CAF50] hover:text-white rounded-[8px] px-3 py-1 text-[13px] font-medium transition-all"
                      >
                        {{ expandedChallengeId === challenge.challenge_id ? '문항 닫기' : '문항 관리' }}
                      </button>
                      <button
                        @click="openEditModal(challenge)"
                        class="text-[#4CAF50] hover:text-[#388E3C] text-[13px] font-medium transition-colors"
                      >
                        수정
                      </button>
                      <button
                        @click="deleteChallenge(challenge)"
                        class="text-red-500 hover:text-red-700 text-[13px] font-medium transition-colors"
                      >
                        삭제
                      </button>
                    </div>
                  </td>
                </tr>
                <!-- 문항 확장 영역 -->
                <tr v-if="expandedChallengeId === challenge.challenge_id">
                  <td colspan="5" class="px-5 pb-4 pt-0">
                    <div class="bg-[#F8F8F8] rounded-[12px] p-4 mt-2 mb-4">
                      <!-- 문항 헤더 -->
                      <div class="flex items-center justify-between mb-3">
                        <h3 class="text-[14px] font-bold text-[#333]">
                          문항 목록 ({{ questions.length }}개)
                        </h3>
                        <button
                          @click="openQuestionCreateModal(challenge.challenge_id)"
                          class="bg-[#4CAF50] hover:bg-[#388E3C] text-white rounded-[8px] px-3 py-1.5 text-[13px] font-medium active:scale-[0.98] transition-all"
                        >
                          + 문항 추가
                        </button>
                      </div>

                      <!-- 문항 로딩 -->
                      <div v-if="questionsLoading" class="py-6 text-center">
                        <p class="text-[13px] text-[#888]">문항을 불러오는 중...</p>
                      </div>

                      <!-- 문항 비어있음 -->
                      <div v-else-if="questions.length === 0" class="py-6 text-center">
                        <p class="text-[13px] text-[#888]">등록된 문항이 없습니다.</p>
                      </div>

                      <!-- 문항 리스트 -->
                      <div v-else class="flex flex-col gap-2">
                        <div
                          v-for="question in questions"
                          :key="question.question_id"
                          class="bg-white border border-[#E8E8E8] rounded-[12px] p-4"
                        >
                          <div class="flex items-start gap-3">
                            <!-- 순서 배지 -->
                            <span class="shrink-0 w-7 h-7 flex items-center justify-center bg-[#4CAF50] text-white text-[12px] font-bold rounded-full">
                              {{ question.order }}
                            </span>

                            <!-- 문항 내용 -->
                            <div class="flex-1 min-w-0">
                              <div class="flex items-center gap-2 mb-2">
                                <p class="text-[14px] text-[#333] font-medium whitespace-pre-wrap flex-1">{{ question.content }}</p>
                                <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-500">
                                  {{ getQuestionTypeLabel(question.question_type) }}
                                </span>
                              </div>

                              <!-- 객관식/그림카드택1 보기 -->
                              <div v-if="question.question_type === 'multiple_choice' || question.question_type === 'image_choice' || !question.question_type" class="flex flex-wrap gap-1.5 mb-2">
                                <span
                                  v-for="(opt, idx) in question.options"
                                  :key="idx"
                                  :class="[
                                    'inline-flex items-center px-2.5 py-1 rounded-full text-[12px] font-medium',
                                    opt === question.correct_answer
                                      ? 'bg-[#E8F5E9] text-[#4CAF50]'
                                      : 'bg-[#F5F5F5] text-[#888]'
                                  ]"
                                >
                                  <span v-if="opt === question.correct_answer" class="mr-1">&#10003;</span>
                                  {{ opt }}
                                </span>
                              </div>

                              <!-- 매칭 쌍 -->
                              <div v-if="question.question_type === 'matching' && question.match_pairs" class="flex flex-wrap gap-1.5 mb-2">
                                <span v-for="(pair, idx) in question.match_pairs" :key="idx" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[12px] font-medium bg-blue-50 text-blue-600">
                                  {{ pair.left }} =
                                  <img v-if="pair.right_image" :src="pair.right_image" class="w-5 h-5 rounded object-cover inline-block" />
                                  <template v-else>{{ pair.right }}</template>
                                </span>
                              </div>

                              <!-- 허용 정답 -->
                              <div v-if="(question.question_type === 'image_text' || question.question_type === 'image_voice') && question.accept_answers" class="flex flex-wrap gap-1.5 mb-2">
                                <span v-for="(ans, idx) in question.accept_answers" :key="idx" class="inline-flex items-center px-2.5 py-1 rounded-full text-[12px] font-medium bg-[#E8F5E9] text-[#4CAF50]">
                                  &#10003; {{ ans }}
                                </span>
                              </div>

                              <!-- 이미지 썸네일 -->
                              <div v-if="question.image_url" class="mt-1">
                                <img
                                  :src="question.image_url"
                                  alt="문항 이미지"
                                  class="w-16 h-16 rounded-[8px] object-cover border border-[#E8E8E8]"
                                />
                              </div>
                            </div>

                            <!-- 문항 액션 -->
                            <div class="shrink-0 flex items-center gap-2">
                              <button
                                @click="openQuestionEditModal(question)"
                                class="text-[#4CAF50] hover:text-[#388E3C] text-[12px] font-medium transition-colors"
                              >
                                수정
                              </button>
                              <button
                                @click="deleteQuestion(question)"
                                class="text-red-500 hover:text-red-700 text-[12px] font-medium transition-colors"
                              >
                                삭제
                              </button>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
        <div class="px-5 py-3 border-t border-[#F0F0F0] text-[13px] text-[#888]">
          총 {{ challenges.length }}개
        </div>
      </div>
    </div>

    <!-- 챌린지 모달 -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center"
      >
        <!-- 오버레이 -->
        <div class="absolute inset-0 bg-black/40" @click="closeModal"></div>

        <!-- 모달 컨텐츠 -->
        <div class="relative bg-white rounded-[16px] shadow-[0_0_30px_rgba(0,0,0,0.2)] w-full max-w-[520px] mx-4 p-6">
          <h2 class="text-[18px] font-bold text-[#333] mb-5">
            {{ modalMode === 'edit' ? '챌린지 수정' : '챌린지 추가' }}
          </h2>

          <!-- 모달 에러 -->
          <div v-if="modalError" class="mb-4 bg-red-50 border border-red-200 rounded-[12px] px-4 py-3 text-red-600 text-[14px]">
            {{ modalError }}
          </div>

          <form @submit.prevent="handleSubmit">
            <!-- 제목 -->
            <div class="mb-4">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">제목</label>
              <input
                v-model="form.title"
                type="text"
                placeholder="챌린지 제목"
                class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[15px] focus:border-[#4CAF50] focus:outline-none transition-colors"
              />
            </div>

            <!-- 카테고리 -->
            <div class="mb-4">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">카테고리</label>
              <select
                v-model="form.category_id"
                class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[15px] focus:border-[#4CAF50] focus:outline-none transition-colors"
              >
                <option value="" disabled>카테고리 선택</option>
                <option v-for="cat in categories" :key="cat.category_id" :value="cat.category_id">
                  {{ cat.name }}
                </option>
              </select>
            </div>

            <!-- 챌린지 유형 -->
            <div class="mb-4">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">챌린지 유형</label>
              <input
                v-model="form.challenge_type"
                type="text"
                placeholder="예: DAILY, WEEKLY, SPECIAL"
                class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[15px] focus:border-[#4CAF50] focus:outline-none transition-colors"
              />
            </div>

            <!-- 난이도 -->
            <div class="mb-5">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">
                난이도 ({{ form.difficulty_level }}/5)
              </label>
              <div class="flex items-center gap-3">
                <input
                  v-model.number="form.difficulty_level"
                  type="range"
                  min="1"
                  max="5"
                  step="1"
                  class="flex-1 accent-[#4CAF50]"
                />
                <span class="text-[14px] text-[#888] w-[100px] text-right">
                  {{ '★'.repeat(form.difficulty_level) }}{{ '☆'.repeat(5 - form.difficulty_level) }}
                </span>
              </div>
            </div>

            <!-- 버튼 -->
            <div class="flex items-center gap-3">
              <button
                type="submit"
                :disabled="saving"
                class="bg-[#4CAF50] hover:bg-[#388E3C] disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-[12px] px-6 py-3 text-[15px] font-medium active:scale-[0.98] transition-all"
              >
                {{ saving ? '저장 중...' : modalMode === 'edit' ? '수정하기' : '추가하기' }}
              </button>
              <button
                type="button"
                @click="closeModal"
                class="bg-[#F8F8F8] hover:bg-[#E8E8E8] text-[#555] rounded-[12px] px-6 py-3 text-[15px] font-medium active:scale-[0.98] transition-all"
              >
                취소
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- 문항 모달 -->
    <Teleport to="body">
      <div
        v-if="showQuestionModal"
        class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center"
      >
        <!-- 오버레이 -->
        <div class="absolute inset-0" @click="closeQuestionModal"></div>

        <!-- 모달 카드 -->
        <div class="relative bg-white rounded-[16px] p-6 w-full max-w-[500px] max-h-[90vh] overflow-y-auto mx-4 shadow-[0_0_30px_rgba(0,0,0,0.2)]">
          <h2 class="text-[18px] font-bold text-[#333] mb-5">
            {{ editingQuestion?.question_id ? '문항 수정' : '문항 추가' }}
          </h2>

          <!-- 모달 에러 -->
          <div v-if="questionModalError" class="mb-4 bg-red-50 border border-red-200 rounded-[12px] px-4 py-3 text-red-600 text-[14px]">
            {{ questionModalError }}
          </div>

          <form @submit.prevent="handleQuestionSubmit">
            <!-- 문항 유형 선택 -->
            <div class="mb-4">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">문항 유형</label>
              <select
                v-model="questionForm.question_type"
                class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[15px] focus:border-[#4CAF50] focus:outline-none transition-colors"
              >
                <option v-for="qt in QUESTION_TYPES" :key="qt.value" :value="qt.value">{{ qt.label }}</option>
              </select>
            </div>

            <!-- 문항 내용 -->
            <div class="mb-4">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">문항 내용</label>
              <textarea
                v-model="questionForm.content"
                rows="3"
                placeholder="문항 내용을 입력해주세요"
                class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[15px] focus:border-[#4CAF50] focus:outline-none transition-colors resize-y"
              ></textarea>
            </div>

            <!-- ========== 객관식 / 그림카드 택1: 보기 4개 ========== -->
            <div v-if="questionForm.question_type === 'multiple_choice' || questionForm.question_type === 'image_choice'" class="mb-4">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">보기</label>
              <div class="flex flex-col gap-2">
                <div
                  v-for="(_, idx) in questionForm.options"
                  :key="idx"
                  class="flex items-center gap-2"
                >
                  <label class="shrink-0 flex items-center justify-center cursor-pointer">
                    <input
                      type="radio"
                      name="correct_answer"
                      :checked="questionForm.correct_answer === questionForm.options[idx] && questionForm.options[idx]!.trim() !== ''"
                      @change="selectCorrectAnswer(questionForm.options[idx] ?? '')"
                      :disabled="!questionForm.options[idx] || questionForm.options[idx]!.trim() === ''"
                      class="w-4 h-4 accent-[#4CAF50]"
                    />
                  </label>
                  <input
                    v-model="questionForm.options[idx]"
                    type="text"
                    :placeholder="`보기 ${idx + 1}`"
                    class="flex-1 bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-3 py-2.5 text-[14px] focus:border-[#4CAF50] focus:outline-none transition-colors"
                  />
                </div>
              </div>
              <p class="mt-1.5 text-[12px] text-[#888]">라디오 버튼으로 정답을 선택해주세요.</p>
            </div>

            <!-- ========== 매칭 게임: 좌/우 쌍 ========== -->
            <div v-if="questionForm.question_type === 'matching'" class="mb-4">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">매칭 쌍</label>
              <div class="flex flex-col gap-3">
                <div v-for="(pair, idx) in questionForm.match_pairs" :key="idx" class="flex items-start gap-2 bg-[#FAFAFA] rounded-[12px] p-3">
                  <!-- 좌측: 텍스트 -->
                  <input
                    v-model="pair.left"
                    type="text"
                    :placeholder="`좌측 ${idx + 1} (멘트)`"
                    class="flex-1 bg-white border border-[#E8E8E8] rounded-[10px] px-3 py-2.5 text-[14px] focus:border-[#4CAF50] focus:outline-none transition-colors"
                  />
                  <span class="text-[#999] text-[14px] mt-2.5">=</span>
                  <!-- 우측: 텍스트 + 이미지 -->
                  <div class="flex-1 flex flex-col gap-1.5">
                    <input
                      v-model="pair.right"
                      type="text"
                      :placeholder="pair.right_image ? '(이미지 사용 중)' : `우측 ${idx + 1} (텍스트/이모지)`"
                      class="w-full bg-white border border-[#E8E8E8] rounded-[10px] px-3 py-2.5 text-[14px] focus:border-[#4CAF50] focus:outline-none transition-colors"
                    />
                    <!-- 이미지 미리보기 -->
                    <div v-if="pair.right_image" class="flex items-center gap-2">
                      <img :src="pair.right_image" class="w-14 h-14 rounded-[8px] object-cover border border-[#E8E8E8]" />
                      <button
                        type="button"
                        @click="removeMatchPairImage(idx)"
                        class="text-[12px] text-red-400 hover:text-red-600 hover:underline"
                      >삭제</button>
                    </div>
                    <!-- 이미지 업로드 버튼 -->
                    <label v-if="!pair.right_image" class="inline-flex items-center gap-1 cursor-pointer text-[12px] text-[#2196F3] hover:underline">
                      <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><path d="m21 15-5-5L5 21" /></svg>
                      <span v-if="matchPairImageUploading === idx">업로드 중...</span>
                      <span v-else>이미지 첨부</span>
                      <input
                        type="file"
                        accept="image/*"
                        class="hidden"
                        :disabled="matchPairImageUploading === idx"
                        @change="handleMatchPairImageUpload($event, idx)"
                      />
                    </label>
                  </div>
                  <!-- 삭제 버튼 -->
                  <button
                    type="button"
                    @click="removeMatchPair(idx)"
                    :disabled="questionForm.match_pairs.length <= 1"
                    class="shrink-0 w-7 h-7 mt-2 flex items-center justify-center rounded-full text-red-400 hover:bg-red-50 disabled:opacity-30 transition-colors"
                  >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12" /></svg>
                  </button>
                </div>
              </div>
              <button
                type="button"
                @click="addMatchPair"
                class="mt-2 text-[13px] text-[#4CAF50] font-medium hover:underline"
              >
                + 쌍 추가
              </button>
              <p class="mt-1 text-[12px] text-[#888]">좌측 멘트와 우측 답(텍스트/이모지 또는 이미지)을 짝지어 입력해주세요. (4~5개 권장)</p>
            </div>

            <!-- ========== 그림카드 텍스트/음성: 허용 정답 ========== -->
            <div v-if="questionForm.question_type === 'image_text' || questionForm.question_type === 'image_voice'" class="mb-4">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">허용 정답</label>
              <div class="flex flex-col gap-2">
                <div v-for="(_, idx) in questionForm.accept_answers" :key="idx" class="flex items-center gap-2">
                  <input
                    v-model="questionForm.accept_answers[idx]"
                    type="text"
                    :placeholder="`정답 ${idx + 1}`"
                    class="flex-1 bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-3 py-2.5 text-[14px] focus:border-[#4CAF50] focus:outline-none transition-colors"
                  />
                  <button
                    type="button"
                    @click="removeAcceptAnswer(idx)"
                    :disabled="questionForm.accept_answers.length <= 1"
                    class="shrink-0 w-7 h-7 flex items-center justify-center rounded-full text-red-400 hover:bg-red-50 disabled:opacity-30 transition-colors"
                  >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12" /></svg>
                  </button>
                </div>
              </div>
              <button
                type="button"
                @click="addAcceptAnswer"
                class="mt-2 text-[13px] text-[#4CAF50] font-medium hover:underline"
              >
                + 정답 추가
              </button>
              <p class="mt-1 text-[12px] text-[#888]">
                {{ questionForm.question_type === 'image_voice' ? '음성 인식 텍스트에 포함되면 정답으로 판정합니다.' : '정확히 일치하는 텍스트를 입력해주세요. (대소문자 무시)' }}
              </p>
            </div>

            <!-- 이미지 업로드 -->
            <div class="mb-4">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">
                이미지 {{ questionForm.question_type === 'multiple_choice' || questionForm.question_type === 'matching' ? '(선택)' : '(필수)' }}
              </label>
              <div v-if="questionForm.image_url" class="flex items-center gap-3 mb-2">
                <img
                  :src="questionForm.image_url"
                  alt="업로드된 이미지"
                  class="w-20 h-20 rounded-[8px] object-cover border border-[#E8E8E8]"
                />
                <button
                  type="button"
                  @click="removeImage"
                  class="text-red-500 hover:text-red-700 text-[13px] font-medium transition-colors"
                >
                  이미지 삭제
                </button>
              </div>
              <label
                v-if="!questionForm.image_url"
                class="flex flex-col items-center justify-center w-full h-24 border-2 border-dashed border-[#E8E8E8] rounded-[12px] cursor-pointer hover:border-[#4CAF50] transition-colors"
                :class="{ 'opacity-50 pointer-events-none': imageUploading }"
              >
                <span class="text-[13px] text-[#888]">
                  {{ imageUploading ? '업로드 중...' : '클릭하여 이미지 선택' }}
                </span>
                <input
                  type="file"
                  accept="image/*"
                  class="hidden"
                  @change="handleImageUpload"
                />
              </label>
            </div>

            <!-- 순서 -->
            <div class="mb-5">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">순서</label>
              <input
                v-model.number="questionForm.order"
                type="number"
                min="1"
                placeholder="1"
                class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[15px] focus:border-[#4CAF50] focus:outline-none transition-colors"
              />
            </div>

            <!-- 버튼 -->
            <div class="flex items-center gap-3">
              <button
                type="submit"
                :disabled="questionSaving || imageUploading"
                class="bg-[#4CAF50] hover:bg-[#388E3C] disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-[12px] px-6 py-3 text-[15px] font-medium active:scale-[0.98] transition-all"
              >
                {{ questionSaving ? '저장 중...' : editingQuestion?.question_id ? '수정하기' : '추가하기' }}
              </button>
              <button
                type="button"
                @click="closeQuestionModal"
                class="border border-[#E8E8E8] hover:bg-[#F8F8F8] text-[#555] rounded-[12px] px-6 py-3 text-[15px] font-medium active:scale-[0.98] transition-all"
              >
                취소
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>
