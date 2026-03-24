<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@shared/api'
import { adminApi } from '@shared/api/adminApi'
import type { ScreeningTest, ScreeningQuestion, LearningCategory, ApiResponse, SubDomainDef } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'

const toast = useToastStore()

const tests = ref<ScreeningTest[]>([])
const categories = ref<LearningCategory[]>([])
const loading = ref(true)
const error = ref('')

// 테스트 모달 상태
const showModal = ref(false)
const modalMode = ref<'create' | 'edit'>('create')
const saving = ref(false)
const modalError = ref('')
const editingId = ref<number | null>(null)

const form = ref({
  title: '',
  description: '',
  test_type: 'MULTIPLE_CHOICE' as 'MULTIPLE_CHOICE' | 'LIKERT',
  sub_domains: [] as SubDomainDef[],
  category_id: '' as number | '',
  question_count: 10,
  time_limit: '' as number | '',
})

// 문항 관리 상태
const expandedTestId = ref<number | null>(null)
const questions = ref<ScreeningQuestion[]>([])
const questionsLoading = ref(false)
const showQuestionModal = ref(false)
const editingQuestion = ref<Partial<ScreeningQuestion> | null>(null)
const questionSaving = ref(false)
const questionModalError = ref('')

// 테스트별 하위영역 (동적)
const subDomainInput = ref('')
const subDomainDescInput = ref('')

const questionForm = ref({
  test_id: 0,
  content: '',
  question_type: 'multiple_choice',
  sub_domain: '' as string,
  options: ['', '', '', ''],
  correct_answer: '',
  order: 0,
})

function resetForm() {
  form.value = {
    title: '',
    description: '',
    test_type: 'MULTIPLE_CHOICE',
    sub_domains: [],
    category_id: '',
    question_count: 10,
    time_limit: '',
  }
  subDomainInput.value = ''
  subDomainDescInput.value = ''
  modalError.value = ''
  editingId.value = null
}

function resetQuestionForm(testId: number) {
  const test = tests.value.find(t => t.test_id === testId)
  questionForm.value = {
    test_id: testId,
    content: '',
    question_type: test?.test_type === 'LIKERT' ? 'likert' : 'multiple_choice',
    sub_domain: '',
    options: test?.test_type === 'LIKERT' ? ['매우그렇다', '그렇다', '보통', '아니다', '매우아니다'] : ['', '', '', ''],
    correct_answer: '',
    order: questions.value.length + 1,
  }
  questionModalError.value = ''
  editingQuestion.value = null
}

const expandedTestType = computed<'MULTIPLE_CHOICE' | 'LIKERT'>(() => {
  if (expandedTestId.value === null) return 'MULTIPLE_CHOICE'
  const test = tests.value.find(t => t.test_id === expandedTestId.value)
  return test?.test_type ?? 'MULTIPLE_CHOICE'
})

// 현재 펼쳐진 테스트의 하위영역 목록
const expandedTestSubDomains = computed<string[]>(() => {
  if (expandedTestId.value === null) return []
  const test = tests.value.find(t => t.test_id === expandedTestId.value)
  if (!Array.isArray(test?.sub_domains)) return []
  return test.sub_domains.map(d => d.name)
})

function addSubDomain() {
  const name = subDomainInput.value.trim()
  if (!name) return
  if (form.value.sub_domains.some(d => d.name === name)) {
    subDomainInput.value = ''
    return
  }
  form.value.sub_domains.push({ name, description: subDomainDescInput.value.trim() })
  subDomainInput.value = ''
  subDomainDescInput.value = ''
}

function removeSubDomain(index: number) {
  form.value.sub_domains.splice(index, 1)
}

const editingSubDomainIdx = ref<number | null>(null)

function startEditSubDomain(idx: number) {
  const d = form.value.sub_domains[idx]
  if (!d) return
  editingSubDomainIdx.value = idx
  subDomainInput.value = d.name
  subDomainDescInput.value = d.description
}

function saveEditSubDomain() {
  if (editingSubDomainIdx.value === null) return
  const d = form.value.sub_domains[editingSubDomainIdx.value]
  if (!d) return
  d.name = subDomainInput.value.trim()
  d.description = subDomainDescInput.value.trim()
  editingSubDomainIdx.value = null
  subDomainInput.value = ''
  subDomainDescInput.value = ''
}

function cancelEditSubDomain() {
  editingSubDomainIdx.value = null
  subDomainInput.value = ''
  subDomainDescInput.value = ''
}

function openCreateModal() {
  resetForm()
  modalMode.value = 'create'
  showModal.value = true
}

function openEditModal(test: ScreeningTest) {
  modalMode.value = 'edit'
  editingId.value = test.test_id
  form.value = {
    title: test.title,
    description: test.description || '',
    test_type: test.test_type ?? 'MULTIPLE_CHOICE',
    sub_domains: Array.isArray(test.sub_domains) ? test.sub_domains.map(d => ({ ...d })) : [],
    category_id: test.category_id,
    question_count: test.question_count,
    time_limit: test.time_limit ?? '',
  }
  subDomainInput.value = ''
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
    const [testsRes, catsRes] = await Promise.all([
      api.get<ApiResponse<ScreeningTest[]>>('/admin/screening-tests'),
      api.get<ApiResponse<LearningCategory[]>>('/admin/learning-categories'),
    ])
    if (testsRes.data.success) tests.value = testsRes.data.data
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
  if (form.value.question_count < 1) return '문항수는 1 이상이어야 합니다.'
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
    description: form.value.description.trim() || null,
    test_type: form.value.test_type,
    sub_domains: form.value.test_type === 'LIKERT' ? form.value.sub_domains : null,
    category_id: Number(form.value.category_id),
    question_count: form.value.question_count,
    time_limit: form.value.time_limit !== '' ? Number(form.value.time_limit) : null,
  }

  try {
    if (modalMode.value === 'edit' && editingId.value) {
      const res = await api.put<ApiResponse<ScreeningTest>>(`/admin/screening-tests/${editingId.value}`, payload)
      if (res.data.success) {
        const idx = tests.value.findIndex((t) => t.test_id === editingId.value)
        if (idx !== -1) tests.value[idx] = res.data.data
      }
    } else {
      const res = await api.post<ApiResponse<ScreeningTest>>('/admin/screening-tests', payload)
      if (res.data.success) tests.value.push(res.data.data)
    }
    closeModal()
  } catch (e: any) {
    modalError.value = e.response?.data?.message || '저장에 실패했습니다.'
  } finally {
    saving.value = false
  }
}

async function deleteTest(test: ScreeningTest) {
  if (!confirm(`"${test.title}" 진단 테스트를 정말 삭제하시겠습니까?\n이 작업은 되돌릴 수 없습니다.`)) return
  try {
    await api.delete(`/admin/screening-tests/${test.test_id}`)
    tests.value = tests.value.filter((t) => t.test_id !== test.test_id)
    if (expandedTestId.value === test.test_id) {
      expandedTestId.value = null
      questions.value = []
    }
  } catch (e: any) {
    toast.error(e.response?.data?.message || '삭제에 실패했습니다.')
  }
}

function getCategoryName(categoryId: number): string {
  return categories.value.find((c) => c.category_id === categoryId)?.name || '-'
}

// 문항 관리 함수들
async function toggleQuestions(testId: number) {
  if (expandedTestId.value === testId) {
    expandedTestId.value = null
    questions.value = []
    return
  }
  expandedTestId.value = testId
  await fetchQuestions(testId)
}

async function fetchQuestions(testId: number) {
  questionsLoading.value = true
  try {
    const res = await adminApi.getScreeningQuestions(testId)
    if (res.data.success) {
      questions.value = res.data.data
    }
  } catch (e: any) {
    toast.error(e.response?.data?.message || '문항을 불러오지 못했습니다.')
  } finally {
    questionsLoading.value = false
  }
}

function openQuestionCreateModal(testId: number) {
  resetQuestionForm(testId)
  showQuestionModal.value = true
}

function openQuestionEditModal(question: ScreeningQuestion) {
  editingQuestion.value = question
  const testType = expandedTestType.value
  const opts = testType === 'LIKERT'
    ? ['매우그렇다', '그렇다', '보통', '아니다', '매우아니다']
    : Array.isArray(question.options) ? [...question.options] : ['', '', '', '']
  if (testType !== 'LIKERT') {
    while (opts.length < 4) opts.push('')
  }
  questionForm.value = {
    test_id: question.test_id,
    content: question.content,
    question_type: question.question_type || (testType === 'LIKERT' ? 'likert' : 'multiple_choice'),
    sub_domain: question.sub_domain || '',
    options: opts,
    correct_answer: question.correct_answer || '',
    order: question.order,
  }
  questionModalError.value = ''
  showQuestionModal.value = true
}

function closeQuestionModal() {
  showQuestionModal.value = false
  editingQuestion.value = null
  questionModalError.value = ''
}

function validateQuestionForm(): string | null {
  if (!questionForm.value.content.trim()) return '문항 내용을 입력해주세요.'
  const isLikert = expandedTestType.value === 'LIKERT'
  if (isLikert) {
    if (!questionForm.value.sub_domain) return '하위영역을 선택해주세요.'
  } else {
    const filledOptions = questionForm.value.options.filter((o) => o.trim() !== '')
    if (filledOptions.length < 2) return '최소 2개의 보기를 입력해주세요.'
    if (!questionForm.value.correct_answer.trim()) return '정답을 선택해주세요.'
    if (!filledOptions.includes(questionForm.value.correct_answer)) return '정답은 보기 중 하나여야 합니다.'
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

  const isLikert = expandedTestType.value === 'LIKERT'
  const filteredOptions = questionForm.value.options.filter((o) => o.trim() !== '')

  const payload: Record<string, any> = {
    test_id: questionForm.value.test_id,
    content: questionForm.value.content.trim(),
    question_type: questionForm.value.question_type,
    sub_domain: isLikert ? questionForm.value.sub_domain : null,
    options: filteredOptions,
    correct_answer: isLikert ? null : questionForm.value.correct_answer.trim(),
    order: questionForm.value.order,
  }

  try {
    if (editingQuestion.value?.question_id) {
      await adminApi.updateScreeningQuestion(editingQuestion.value.question_id, payload)
    } else {
      await adminApi.createScreeningQuestion(payload)
    }
    closeQuestionModal()
    if (expandedTestId.value !== null) {
      await fetchQuestions(expandedTestId.value)
    }
  } catch (e: any) {
    questionModalError.value = e.response?.data?.message || '문항 저장에 실패했습니다.'
  } finally {
    questionSaving.value = false
  }
}

async function deleteQuestion(question: ScreeningQuestion) {
  if (!confirm(`문항 #${question.order}을(를) 정말 삭제하시겠습니까?`)) return
  try {
    await adminApi.deleteScreeningQuestion(question.question_id)
    if (expandedTestId.value !== null) {
      await fetchQuestions(expandedTestId.value)
    }
  } catch (e: any) {
    toast.error(e.response?.data?.message || '문항 삭제에 실패했습니다.')
  }
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
        <p class="text-[14px] text-[#888]">진단 테스트를 관리합니다.</p>
        <button
          @click="openCreateModal"
          class="bg-[#4CAF50] hover:bg-[#388E3C] text-white rounded-[12px] px-5 py-2.5 text-[14px] font-medium active:scale-[0.98] transition-all"
        >
          + 테스트 추가
        </button>
      </div>

      <!-- 로딩 -->
      <div v-if="loading" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center">
        <p class="text-[#888]">진단 테스트 목록을 불러오는 중...</p>
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
      <div v-else-if="tests.length === 0" class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center">
        <p class="text-[#888]">등록된 진단 테스트가 없습니다.</p>
      </div>

      <!-- 테이블 -->
      <div v-else class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-[14px]">
            <thead>
              <tr class="border-b border-[#E8E8E8] bg-[#FAFAFA]">
                <th class="px-5 py-3 font-semibold text-[#555]">제목</th>
                <th class="px-5 py-3 font-semibold text-[#555]">유형</th>
                <th class="px-5 py-3 font-semibold text-[#555]">카테고리</th>
                <th class="px-5 py-3 font-semibold text-[#555]">문항수</th>
                <th class="px-5 py-3 font-semibold text-[#555]">시간제한</th>
                <th class="px-5 py-3 font-semibold text-[#555]">액션</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="test in tests" :key="test.test_id">
                <tr class="border-b border-[#F0F0F0] hover:bg-[#FAFAFA] transition-colors">
                  <td class="px-5 py-3.5">
                    <div>
                      <p class="text-[#333] font-medium">{{ test.title }}</p>
                      <p v-if="test.description" class="text-[12px] text-[#888] mt-0.5 line-clamp-1">{{ test.description }}</p>
                    </div>
                  </td>
                  <td class="px-5 py-3.5">
                    <span
                      :class="test.test_type === 'LIKERT' ? 'bg-[#E3F2FD] text-[#2196F3]' : 'bg-[#F3E5F5] text-[#9C27B0]'"
                      class="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold"
                    >
                      {{ test.test_type === 'LIKERT' ? '리커트' : '객관식' }}
                    </span>
                  </td>
                  <td class="px-5 py-3.5 text-[#555]">{{ test.category?.name || getCategoryName(test.category_id) }}</td>
                  <td class="px-5 py-3.5 text-[#555]">{{ test.question_count }}문항</td>
                  <td class="px-5 py-3.5 text-[#888]">{{ test.time_limit ? `${test.time_limit}분` : '제한 없음' }}</td>
                  <td class="px-5 py-3.5">
                    <div class="flex items-center gap-3">
                      <button
                        @click="toggleQuestions(test.test_id)"
                        class="border border-[#4CAF50] text-[#4CAF50] hover:bg-[#4CAF50] hover:text-white rounded-[8px] px-3 py-1 text-[13px] font-medium transition-all"
                      >
                        {{ expandedTestId === test.test_id ? '문항 닫기' : '문항 관리' }}
                      </button>
                      <button
                        @click="openEditModal(test)"
                        class="text-[#4CAF50] hover:text-[#388E3C] text-[13px] font-medium transition-colors"
                      >
                        수정
                      </button>
                      <button
                        @click="deleteTest(test)"
                        class="text-red-500 hover:text-red-700 text-[13px] font-medium transition-colors"
                      >
                        삭제
                      </button>
                    </div>
                  </td>
                </tr>
                <!-- 문항 확장 영역 -->
                <tr v-if="expandedTestId === test.test_id">
                  <td colspan="6" class="px-5 pb-4 pt-0">
                    <div class="bg-[#F8F8F8] rounded-[12px] p-4 mt-2 mb-4">
                      <!-- 문항 헤더 -->
                      <div class="flex items-center justify-between mb-3">
                        <h3 class="text-[14px] font-bold text-[#333]">
                          문항 목록 ({{ questions.length }}개)
                        </h3>
                        <button
                          @click="openQuestionCreateModal(test.test_id)"
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
                                <p class="text-[14px] text-[#333] font-medium whitespace-pre-wrap">{{ question.content }}</p>
                                <span v-if="question.sub_domain" class="shrink-0 inline-block bg-[#E3F2FD] text-[#2196F3] px-2 py-0.5 rounded-full text-[11px] font-semibold">
                                  {{ question.sub_domain }}
                                </span>
                              </div>

                              <!-- 보기 옵션 (객관식) -->
                              <div v-if="expandedTestType !== 'LIKERT'" class="flex flex-wrap gap-1.5 mb-2">
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
                              <!-- 리커트 척도 표시 -->
                              <div v-else class="flex flex-wrap gap-1.5 mb-2">
                                <span class="text-[12px] text-[#888]">5점 척도: 매우그렇다(5) ~ 매우아니다(1)</span>
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
          총 {{ tests.length }}개
        </div>
      </div>
    </div>

    <!-- 테스트 모달 -->
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
            {{ modalMode === 'edit' ? '테스트 수정' : '테스트 추가' }}
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
                placeholder="테스트 제목"
                class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[15px] focus:border-[#4CAF50] focus:outline-none transition-colors"
              />
            </div>

            <!-- 설명 -->
            <div class="mb-4">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">설명</label>
              <textarea
                v-model="form.description"
                rows="3"
                placeholder="테스트에 대한 설명 (선택)"
                class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[15px] focus:border-[#4CAF50] focus:outline-none transition-colors resize-y"
              ></textarea>
            </div>

            <!-- 검사 유형 -->
            <div class="mb-4">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">검사 유형</label>
              <div class="flex gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" v-model="form.test_type" value="MULTIPLE_CHOICE" class="w-4 h-4 accent-[#4CAF50]" />
                  <span class="text-[14px] text-[#333]">객관식 (정답고르기)</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" v-model="form.test_type" value="LIKERT" class="w-4 h-4 accent-[#4CAF50]" />
                  <span class="text-[14px] text-[#333]">리커트 척도 (5점)</span>
                </label>
              </div>
              <p v-if="form.test_type === 'LIKERT'" class="mt-1.5 text-[12px] text-[#888]">
                매우그렇다(5) ~ 매우아니다(1) 척도로 응답하는 방식입니다.
              </p>
            </div>

            <!-- 하위영역 (리커트만) -->
            <div v-if="form.test_type === 'LIKERT'" class="mb-4">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">하위영역</label>
              <!-- 입력 폼 -->
              <div class="bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] p-3 mb-2">
                <input
                  v-model="subDomainInput"
                  type="text"
                  placeholder="하위영역명 (예: 자기표현)"
                  class="w-full bg-white border border-[#E8E8E8] rounded-[8px] px-3 py-2 text-[14px] focus:border-[#4CAF50] focus:outline-none transition-colors mb-2"
                />
                <textarea
                  v-model="subDomainDescInput"
                  rows="2"
                  placeholder="설명 (선택) - 이 영역이 무엇을 측정하는지 설명해주세요"
                  class="w-full bg-white border border-[#E8E8E8] rounded-[8px] px-3 py-2 text-[13px] focus:border-[#4CAF50] focus:outline-none transition-colors resize-y"
                ></textarea>
                <div class="flex justify-end gap-2 mt-2">
                  <button
                    v-if="editingSubDomainIdx !== null"
                    type="button"
                    @click="cancelEditSubDomain"
                    class="text-[#888] hover:text-[#555] text-[13px] font-medium px-3 py-1.5 transition-colors"
                  >
                    취소
                  </button>
                  <button
                    type="button"
                    @click="editingSubDomainIdx !== null ? saveEditSubDomain() : addSubDomain()"
                    :disabled="!subDomainInput.trim()"
                    class="bg-[#4CAF50] hover:bg-[#388E3C] disabled:opacity-40 text-white rounded-[8px] px-4 py-1.5 text-[13px] font-medium shrink-0 transition-colors"
                  >
                    {{ editingSubDomainIdx !== null ? '수정' : '추가' }}
                  </button>
                </div>
              </div>
              <!-- 등록된 하위영역 목록 -->
              <div v-if="form.sub_domains.length > 0" class="space-y-2">
                <div
                  v-for="(domain, idx) in form.sub_domains"
                  :key="idx"
                  class="flex items-start gap-2 bg-white border border-[#E8E8E8] rounded-[10px] px-3 py-2.5"
                >
                  <div class="flex-1 min-w-0">
                    <p class="text-[13px] font-semibold text-[#2196F3]">{{ domain.name }}</p>
                    <p v-if="domain.description" class="text-[12px] text-[#888] mt-0.5 leading-relaxed">{{ domain.description }}</p>
                    <p v-else class="text-[12px] text-[#CCC] mt-0.5 italic">설명 없음</p>
                  </div>
                  <div class="flex items-center gap-1 shrink-0">
                    <button
                      type="button"
                      @click="startEditSubDomain(idx)"
                      class="text-[#4CAF50] hover:text-[#388E3C] text-[12px] font-medium transition-colors"
                    >
                      수정
                    </button>
                    <button
                      type="button"
                      @click="removeSubDomain(idx)"
                      class="text-red-400 hover:text-red-600 text-[12px] font-medium transition-colors"
                    >
                      삭제
                    </button>
                  </div>
                </div>
              </div>
              <p v-else class="text-[12px] text-[#888] mt-1">하위영역을 추가해주세요. (예: 상호작용, 자기조절, 자기표현 등)</p>
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

            <!-- 문항수 / 시간제한 -->
            <div class="grid grid-cols-2 gap-4 mb-5">
              <div>
                <label class="block text-[14px] font-semibold text-[#333] mb-1.5">문항수</label>
                <input
                  v-model.number="form.question_count"
                  type="number"
                  min="1"
                  placeholder="10"
                  class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[15px] focus:border-[#4CAF50] focus:outline-none transition-colors"
                />
              </div>
              <div>
                <label class="block text-[14px] font-semibold text-[#333] mb-1.5">시간제한 (분)</label>
                <input
                  v-model.number="form.time_limit"
                  type="number"
                  min="1"
                  placeholder="제한 없음"
                  class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[15px] focus:border-[#4CAF50] focus:outline-none transition-colors"
                />
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

            <!-- 하위영역 (리커트만) -->
            <div v-if="expandedTestType === 'LIKERT'" class="mb-4">
              <label class="block text-[14px] font-semibold text-[#333] mb-1.5">하위영역</label>
              <select
                v-model="questionForm.sub_domain"
                class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[15px] focus:border-[#4CAF50] focus:outline-none transition-colors"
              >
                <option value="" disabled>하위영역 선택</option>
                <option v-for="domain in expandedTestSubDomains" :key="domain" :value="domain">{{ domain }}</option>
              </select>
              <p class="mt-1.5 text-[12px] text-[#888]">응답 척도: 매우그렇다(5) / 그렇다(4) / 보통(3) / 아니다(2) / 매우아니다(1)</p>
            </div>

            <!-- 보기 4개 (객관식만) -->
            <div v-if="expandedTestType !== 'LIKERT'" class="mb-4">
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
                :disabled="questionSaving"
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
