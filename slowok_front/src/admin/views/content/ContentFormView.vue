<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { adminApi } from '@shared/api/adminApi'
import type { LearningCategory } from '@shared/types'
import FileUploadArea from '@shared/components/form/FileUploadArea.vue'
import { useToastStore } from '@shared/stores/toastStore'

// ── Router ──────────────────────────────────────────
const route = useRoute()
const router = useRouter()

const toast = useToastStore()
const editId = computed(() => route.params.id ? Number(route.params.id) : null)
const isEdit = computed(() => editId.value !== null)
const pageTitle = computed(() => isEdit.value ? '콘텐츠 수정' : '콘텐츠 등록')

// ── State ───────────────────────────────────────────
const categories = ref<LearningCategory[]>([])
const loading = ref(false)
const saving = ref(false)
const errorMsg = ref('')

// ── Common form fields ──────────────────────────────
const title = ref('')
const categoryId = ref<number | null>(null)
const contentType = ref<'VIDEO' | 'QUIZ' | 'READING' | 'GAME'>('VIDEO')
const difficultyLevel = ref(5)

const contentTypes = [
  { value: 'VIDEO' as const, label: '영상' },
  { value: 'QUIZ' as const, label: '퀴즈' },
  { value: 'READING' as const, label: '읽기' },
  { value: 'GAME' as const, label: '게임' },
]

// ── VIDEO form ──────────────────────────────────────
const videoType = ref<'youtube' | 'upload'>('youtube')
const videoUrl = ref('')
const thumbnailUrl = ref('')
const videoDuration = ref('')
const videoDescription = ref('')

function extractYoutubeId(url: string): string | null {
  const patterns = [
    /youtu\.be\/([^?&]+)/,
    /youtube\.com\/watch\?v=([^&]+)/,
    /youtube\.com\/embed\/([^?&]+)/,
    /youtube\.com\/shorts\/([^?&]+)/,
  ]
  for (const p of patterns) {
    const m = url.match(p)
    if (m?.[1]) return m[1]
  }
  return null
}

const youtubeId = computed(() => extractYoutubeId(videoUrl.value))
const youtubeEmbedUrl = computed(() =>
  youtubeId.value ? `https://www.youtube.com/embed/${youtubeId.value}` : null
)

// ── QUIZ form ───────────────────────────────────────
interface QuizQuestion {
  question: string
  image_url: string
  options: string[]
  correct_index: number
}

const quizDescription = ref('')
const quizTimeLimit = ref(60)
const quizPassScore = ref(70)
const quizQuestions = ref<QuizQuestion[]>([])

function addQuizQuestion(): void {
  quizQuestions.value.push({
    question: '',
    image_url: '',
    options: ['', '', '', ''],
    correct_index: 0,
  })
}

function removeQuizQuestion(index: number): void {
  if (index >= 0 && index < quizQuestions.value.length) {
    quizQuestions.value.splice(index, 1)
  }
}

function updateQuizOption(qIndex: number, oIndex: number, value: string): void {
  const q = quizQuestions.value[qIndex]
  if (q && oIndex >= 0 && oIndex < q.options.length) {
    q.options[oIndex] = value
  }
}

// ── READING form ────────────────────────────────────
const readingTextContent = ref('')
const readingAudioUrl = ref('')
const readingImages = ref<string[]>([])
const readingEstimatedTime = ref('')

function addReadingImage(): void {
  readingImages.value.push('')
}

function removeReadingImage(index: number): void {
  if (index >= 0 && index < readingImages.value.length) {
    readingImages.value.splice(index, 1)
  }
}

function updateReadingImage(index: number, value: string): void {
  if (index >= 0 && index < readingImages.value.length) {
    readingImages.value[index] = value
  }
}

// ── GAME form ───────────────────────────────────────
interface GameItem {
  question: string
  answer: string
  image_url: string
}

const gameType = ref<'matching' | 'sequence' | 'puzzle'>('matching')
const gameDescription = ref('')
const gameTimeLimit = ref(120)
const gameItems = ref<GameItem[]>([])

function addGameItem(): void {
  gameItems.value.push({
    question: '',
    answer: '',
    image_url: '',
  })
}

function removeGameItem(index: number): void {
  if (index >= 0 && index < gameItems.value.length) {
    gameItems.value.splice(index, 1)
  }
}

// ── Collect content_data ────────────────────────────
function buildContentData(): Record<string, any> {
  switch (contentType.value) {
    case 'VIDEO':
      return {
        video_type: videoType.value,
        video_url: videoUrl.value,
        thumbnail_url: thumbnailUrl.value,
        duration: videoDuration.value,
        description: videoDescription.value,
      }
    case 'QUIZ':
      return {
        description: quizDescription.value,
        time_limit: quizTimeLimit.value,
        pass_score: quizPassScore.value,
        questions: quizQuestions.value.map((q) => ({
          question: q.question,
          image_url: q.image_url,
          options: [...q.options],
          correct_index: q.correct_index,
        })),
      }
    case 'READING':
      return {
        text_content: readingTextContent.value,
        audio_url: readingAudioUrl.value,
        images: readingImages.value.filter((url) => url.length > 0),
        estimated_time: readingEstimatedTime.value,
      }
    case 'GAME':
      return {
        game_type: gameType.value,
        description: gameDescription.value,
        time_limit: gameTimeLimit.value,
        items: gameItems.value.map((item) => ({
          question: item.question,
          answer: item.answer,
          image_url: item.image_url,
        })),
      }
    default:
      return {}
  }
}

// ── Parse content_data into form fields ─────────────
function parseContentData(type: string, data: Record<string, any> | null): void {
  if (!data) return

  switch (type) {
    case 'VIDEO':
      videoType.value = data.video_type || 'youtube'
      videoUrl.value = data.video_url || ''
      thumbnailUrl.value = data.thumbnail_url || ''
      videoDuration.value = data.duration || ''
      videoDescription.value = data.description || ''
      break
    case 'QUIZ':
      quizDescription.value = data.description || ''
      quizTimeLimit.value = data.time_limit ?? 60
      quizPassScore.value = data.pass_score ?? 70
      if (Array.isArray(data.questions)) {
        quizQuestions.value = data.questions.map((q: any) => ({
          question: q.question || '',
          image_url: q.image_url || '',
          options: Array.isArray(q.options)
            ? [...q.options, '', '', '', ''].slice(0, 4)
            : ['', '', '', ''],
          correct_index: q.correct_index ?? 0,
        }))
      }
      break
    case 'READING':
      readingTextContent.value = data.text_content || ''
      readingAudioUrl.value = data.audio_url || ''
      readingImages.value = Array.isArray(data.images) ? [...data.images] : []
      readingEstimatedTime.value = data.estimated_time || ''
      break
    case 'GAME':
      gameType.value = data.game_type || 'matching'
      gameDescription.value = data.description || ''
      gameTimeLimit.value = data.time_limit ?? 120
      if (Array.isArray(data.items)) {
        gameItems.value = data.items.map((item: any) => ({
          question: item.question || '',
          answer: item.answer || '',
          image_url: item.image_url || '',
        }))
      }
      break
  }
}

// ── Validation ──────────────────────────────────────
function validateForm(): string | null {
  if (!title.value.trim()) return '제목을 입력해주세요.'
  if (!categoryId.value) return '카테고리를 선택해주세요.'

  if (contentType.value === 'VIDEO') {
    if (videoType.value === 'youtube' && !videoUrl.value.trim()) {
      return 'YouTube URL을 입력해주세요.'
    }
    if (videoType.value === 'upload' && !videoUrl.value) {
      return '동영상 파일을 업로드해주세요.'
    }
  }

  if (contentType.value === 'QUIZ') {
    if (quizQuestions.value.length === 0) {
      return '최소 1개의 문제를 추가해주세요.'
    }
    for (let i = 0; i < quizQuestions.value.length; i++) {
      const q = quizQuestions.value[i]
      if (!q) continue
      if (!q.question.trim()) {
        return `${i + 1}번 문제의 질문을 입력해주세요.`
      }
      const hasEmptyOption = q.options.some((opt) => !opt.trim())
      if (hasEmptyOption) {
        return `${i + 1}번 문제의 모든 선택지를 입력해주세요.`
      }
    }
  }

  if (contentType.value === 'READING') {
    if (!readingTextContent.value.trim()) {
      return '텍스트 내용을 입력해주세요.'
    }
  }

  if (contentType.value === 'GAME') {
    if (gameItems.value.length === 0) {
      return '최소 1개의 항목을 추가해주세요.'
    }
    for (let i = 0; i < gameItems.value.length; i++) {
      const item = gameItems.value[i]
      if (!item) continue
      if (!item.question.trim() || !item.answer.trim()) {
        return `${i + 1}번 항목의 질문과 답을 모두 입력해주세요.`
      }
    }
  }

  return null
}

// ── Submit ───────────────────────────────────────────
async function handleSubmit(): Promise<void> {
  const validationError = validateForm()
  if (validationError) {
    errorMsg.value = validationError
    return
  }

  saving.value = true
  errorMsg.value = ''

  const payload = {
    title: title.value.trim(),
    category_id: categoryId.value!,
    content_type: contentType.value,
    content_data: buildContentData(),
    difficulty_level: difficultyLevel.value,
  }

  try {
    if (isEdit.value && editId.value !== null) {
      await adminApi.updateContent(editId.value, payload)
    } else {
      await adminApi.createContent(payload)
    }
    router.push({ name: 'content' })
  } catch (e: any) {
    errorMsg.value = e.response?.data?.message || '저장에 실패했습니다.'
  } finally {
    saving.value = false
  }
}

// ── Data fetching ───────────────────────────────────
async function fetchCategories(): Promise<void> {
  try {
    const res = await adminApi.getCategories()
    if (res.data.success) {
      categories.value = res.data.data
    }
  } catch (e: any) {
    toast.error(e.response?.data?.message || '데이터를 불러오지 못했습니다.')
  }
}

async function fetchContent(): Promise<void> {
  if (!editId.value) return
  loading.value = true
  try {
    const res = await adminApi.getContent(editId.value)
    if (res.data.success) {
      const data = res.data.data
      title.value = data.title
      categoryId.value = data.category_id
      contentType.value = data.content_type
      difficultyLevel.value = data.difficulty_level
      parseContentData(data.content_type, data.content_data)
    }
  } catch (e: any) {
    errorMsg.value = e.response?.data?.message || '콘텐츠를 불러오지 못했습니다.'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await fetchCategories()
  if (isEdit.value) {
    await fetchContent()
  }
})

// Reset type-specific fields when content_type changes (only for new content)
watch(contentType, () => {
  if (isEdit.value) return
  // VIDEO
  videoType.value = 'youtube'
  videoUrl.value = ''
  thumbnailUrl.value = ''
  videoDuration.value = ''
  videoDescription.value = ''
  // QUIZ
  quizDescription.value = ''
  quizTimeLimit.value = 60
  quizPassScore.value = 70
  quizQuestions.value = []
  // READING
  readingTextContent.value = ''
  readingAudioUrl.value = ''
  readingImages.value = []
  readingEstimatedTime.value = ''
  // GAME
  gameType.value = 'matching'
  gameDescription.value = ''
  gameTimeLimit.value = 120
  gameItems.value = []
})
</script>

<template>
  <div class="p-6 pb-12">
    <div class="max-w-[1200px] mx-auto">
      <!-- Page title -->
      <div class="flex items-center gap-3 mb-6">
        <button
          @click="router.push({ name: 'content' })"
          class="text-[#888] hover:text-[#4CAF50] transition-colors"
          aria-label="콘텐츠 목록으로 돌아가기"
        >
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M15 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
        <h1 class="text-lg font-bold text-[#333]">{{ pageTitle }}</h1>
      </div>
      <!-- Initial loading -->
      <div
        v-if="loading"
        class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-8 text-center"
      >
        <p class="text-[#888]">콘텐츠 정보를 불러오는 중...</p>
      </div>

      <form v-else @submit.prevent="handleSubmit" class="space-y-5">
        <!-- Error banner -->
        <div
          v-if="errorMsg"
          class="bg-red-50 border border-red-200 rounded-[12px] px-4 py-3 text-red-600 text-[14px]"
          role="alert"
        >
          {{ errorMsg }}
        </div>

        <!-- ============================== -->
        <!-- SECTION: Basic Info             -->
        <!-- ============================== -->
        <section class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-6">
          <h2 class="text-[15px] font-bold text-[#333] mb-4">기본 정보</h2>

          <!-- Title -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">제목</label>
            <input
              v-model="title"
              type="text"
              placeholder="콘텐츠 제목을 입력하세요"
              class="w-full px-3 py-2 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors"
            />
          </div>

          <!-- Category -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">카테고리</label>
            <select
              v-model="categoryId"
              class="w-full px-3 py-2 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors bg-white"
            >
              <option :value="null" disabled>카테고리를 선택하세요</option>
              <option
                v-for="cat in categories"
                :key="cat.category_id"
                :value="cat.category_id"
              >
                {{ cat.name }}
              </option>
            </select>
          </div>

          <!-- Content type -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">콘텐츠 유형</label>
            <div class="flex gap-2 flex-wrap">
              <label
                v-for="ct in contentTypes"
                :key="ct.value"
                class="flex items-center gap-2 px-4 py-2 rounded-[12px] border cursor-pointer transition-all text-[14px]"
                :class="contentType === ct.value
                  ? 'border-[#4CAF50] bg-green-50 text-[#4CAF50] font-medium'
                  : 'border-[#E8E8E8] bg-white text-[#555] hover:border-[#CCC]'"
              >
                <input
                  type="radio"
                  :value="ct.value"
                  v-model="contentType"
                  class="sr-only"
                />
                {{ ct.label }}
              </label>
            </div>
          </div>

          <!-- Difficulty slider -->
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1">
              난이도: {{ difficultyLevel }} / 10
            </label>
            <div class="flex items-center gap-3">
              <span class="text-[12px] text-[#B0B0B0]">1</span>
              <input
                v-model.number="difficultyLevel"
                type="range"
                min="1"
                max="10"
                step="1"
                class="flex-1 accent-[#4CAF50]"
              />
              <span class="text-[12px] text-[#B0B0B0]">10</span>
            </div>
          </div>
        </section>

        <!-- Divider -->
        <div class="border-t border-[#E8E8E8]" />

        <!-- ============================== -->
        <!-- SECTION: VIDEO                 -->
        <!-- ============================== -->
        <section
          v-if="contentType === 'VIDEO'"
          class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-6"
        >
          <h2 class="text-[15px] font-bold text-[#333] mb-4">영상 정보</h2>

          <!-- Video source toggle -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">영상 소스</label>
            <div class="flex gap-2">
              <label
                class="flex items-center gap-2 px-4 py-2 rounded-[12px] border cursor-pointer transition-all text-[14px]"
                :class="videoType === 'youtube'
                  ? 'border-[#4CAF50] bg-green-50 text-[#4CAF50] font-medium'
                  : 'border-[#E8E8E8] bg-white text-[#555] hover:border-[#CCC]'"
              >
                <input type="radio" value="youtube" v-model="videoType" class="sr-only" />
                YouTube
              </label>
              <label
                class="flex items-center gap-2 px-4 py-2 rounded-[12px] border cursor-pointer transition-all text-[14px]"
                :class="videoType === 'upload'
                  ? 'border-[#4CAF50] bg-green-50 text-[#4CAF50] font-medium'
                  : 'border-[#E8E8E8] bg-white text-[#555] hover:border-[#CCC]'"
              >
                <input type="radio" value="upload" v-model="videoType" class="sr-only" />
                직접 업로드
              </label>
            </div>
          </div>

          <!-- YouTube URL input -->
          <div v-if="videoType === 'youtube'" class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">YouTube URL</label>
            <input
              v-model="videoUrl"
              type="text"
              placeholder="https://www.youtube.com/watch?v=..."
              class="w-full px-3 py-2 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors"
            />
            <!-- YouTube preview -->
            <div
              v-if="youtubeEmbedUrl"
              class="mt-3 rounded-[12px] overflow-hidden bg-black aspect-video"
            >
              <iframe
                :src="youtubeEmbedUrl"
                class="w-full h-full"
                frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
                title="YouTube 미리보기"
              />
            </div>
            <p v-else-if="videoUrl.trim().length > 0" class="text-[12px] text-[#F44336] mt-1">
              유효한 YouTube URL을 입력해주세요.
            </p>
          </div>

          <!-- Video file upload -->
          <div v-else class="mb-4">
            <FileUploadArea
              :model-value="videoUrl"
              @update:model-value="videoUrl = $event"
              accept="video/*"
              upload-type="video"
              :max-size-m-b="50"
              label="동영상 파일"
            />
          </div>

          <!-- Thumbnail -->
          <div class="mb-4">
            <FileUploadArea
              :model-value="thumbnailUrl"
              @update:model-value="thumbnailUrl = $event"
              accept="image/*"
              upload-type="thumbnail"
              :max-size-m-b="2"
              label="썸네일 이미지"
            />
          </div>

          <!-- Duration -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">재생 시간 (분)</label>
            <input
              v-model="videoDuration"
              type="text"
              placeholder="예: 15"
              class="w-full px-3 py-2 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors"
            />
          </div>

          <!-- Description -->
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1">설명</label>
            <textarea
              v-model="videoDescription"
              rows="4"
              placeholder="영상에 대한 설명을 입력하세요"
              class="w-full px-3 py-2 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors resize-y"
            />
          </div>
        </section>

        <!-- ============================== -->
        <!-- SECTION: QUIZ                  -->
        <!-- ============================== -->
        <section
          v-if="contentType === 'QUIZ'"
          class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-6"
        >
          <h2 class="text-[15px] font-bold text-[#333] mb-4">퀴즈 설정</h2>

          <!-- Description -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">퀴즈 설명</label>
            <textarea
              v-model="quizDescription"
              rows="3"
              placeholder="퀴즈에 대한 설명을 입력하세요"
              class="w-full px-3 py-2 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors resize-y"
            />
          </div>

          <!-- Time limit & pass score -->
          <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block text-[13px] font-medium text-[#555] mb-1">제한 시간 (초)</label>
              <input
                v-model.number="quizTimeLimit"
                type="number"
                min="0"
                placeholder="60"
                class="w-full px-3 py-2 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors"
              />
            </div>
            <div>
              <label class="block text-[13px] font-medium text-[#555] mb-1">통과 점수 (%)</label>
              <input
                v-model.number="quizPassScore"
                type="number"
                min="0"
                max="100"
                placeholder="70"
                class="w-full px-3 py-2 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors"
              />
            </div>
          </div>

          <!-- Divider -->
          <div class="border-t border-[#E8E8E8] my-5" />

          <!-- Questions -->
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-[14px] font-semibold text-[#333]">
              문제 목록 ({{ quizQuestions.length }}개)
            </h3>
            <button
              type="button"
              @click="addQuizQuestion"
              class="text-[#4CAF50] hover:text-[#388E3C] text-[13px] font-medium transition-colors"
            >
              + 문제 추가
            </button>
          </div>

          <div
            v-if="quizQuestions.length === 0"
            class="text-center py-6 text-[#B0B0B0] text-[14px]"
          >
            문제를 추가해주세요.
          </div>

          <div
            v-for="(q, qIdx) in quizQuestions"
            :key="qIdx"
            class="border border-[#E8E8E8] rounded-[12px] p-4 mb-3"
          >
            <div class="flex items-center justify-between mb-3">
              <span class="text-[14px] font-semibold text-[#4CAF50]">
                {{ qIdx + 1 }}번 문제
              </span>
              <button
                type="button"
                @click="removeQuizQuestion(qIdx)"
                class="text-[#F44336] hover:text-red-700 text-[13px] font-medium transition-colors"
              >
                삭제
              </button>
            </div>

            <!-- Question text -->
            <div class="mb-3">
              <label class="block text-[13px] font-medium text-[#555] mb-1">질문</label>
              <textarea
                v-model="q.question"
                rows="2"
                placeholder="질문을 입력하세요"
                class="w-full px-3 py-2 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors resize-y"
              />
            </div>

            <!-- Question image (optional) -->
            <div class="mb-3">
              <FileUploadArea
                :model-value="q.image_url"
                @update:model-value="q.image_url = $event"
                accept="image/*"
                upload-type="image"
                :max-size-m-b="2"
                label="문제 이미지 (선택)"
              />
            </div>

            <!-- Options -->
            <div class="mb-2">
              <label class="block text-[13px] font-medium text-[#555] mb-1">선택지</label>
              <div class="space-y-2">
                <div
                  v-for="(_, oIdx) in q.options"
                  :key="oIdx"
                  class="flex items-center gap-2"
                >
                  <label
                    class="flex items-center justify-center w-8 h-8 rounded-full border-2 cursor-pointer transition-colors flex-shrink-0"
                    :class="q.correct_index === oIdx
                      ? 'border-[#4CAF50] bg-[#4CAF50] text-white'
                      : 'border-[#E8E8E8] text-[#888] hover:border-[#4CAF50]'"
                  >
                    <input
                      type="radio"
                      :name="`q_${qIdx}_correct`"
                      :value="oIdx"
                      v-model="q.correct_index"
                      class="sr-only"
                    />
                    <span class="text-[12px] font-semibold">{{ oIdx + 1 }}</span>
                  </label>
                  <input
                    :value="q.options[oIdx] ?? ''"
                    @input="updateQuizOption(qIdx, oIdx, ($event.target as HTMLInputElement).value)"
                    type="text"
                    :placeholder="`선택지 ${oIdx + 1}`"
                    class="flex-1 px-3 py-2 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors"
                  />
                </div>
              </div>
              <p class="text-[12px] text-[#B0B0B0] mt-1">
                정답 번호를 클릭하여 선택하세요.
              </p>
            </div>
          </div>
        </section>

        <!-- ============================== -->
        <!-- SECTION: READING               -->
        <!-- ============================== -->
        <section
          v-if="contentType === 'READING'"
          class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-6"
        >
          <h2 class="text-[15px] font-bold text-[#333] mb-4">읽기 콘텐츠</h2>

          <!-- Text content -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">텍스트 내용</label>
            <textarea
              v-model="readingTextContent"
              rows="10"
              placeholder="읽기 콘텐츠의 텍스트를 입력하세요"
              class="w-full px-3 py-2 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors resize-y"
            />
          </div>

          <!-- Audio -->
          <div class="mb-4">
            <FileUploadArea
              :model-value="readingAudioUrl"
              @update:model-value="readingAudioUrl = $event"
              accept="audio/*"
              upload-type="audio"
              :max-size-m-b="10"
              label="오디오 파일 (선택)"
            />
          </div>

          <!-- Images -->
          <div class="mb-4">
            <div class="flex items-center justify-between mb-2">
              <label class="text-[13px] font-medium text-[#555]">
                이미지 ({{ readingImages.length }}개)
              </label>
              <button
                type="button"
                @click="addReadingImage"
                class="text-[#4CAF50] hover:text-[#388E3C] text-[13px] font-medium transition-colors"
              >
                + 이미지 추가
              </button>
            </div>

            <div
              v-if="readingImages.length === 0"
              class="text-center py-4 text-[#B0B0B0] text-[13px]"
            >
              이미지가 없습니다.
            </div>

            <div
              v-for="(_imgUrl, imgIdx) in readingImages"
              :key="imgIdx"
              class="border border-[#E8E8E8] rounded-[12px] p-3 mb-3"
            >
              <div class="flex items-center justify-between mb-2">
                <span class="text-[13px] font-medium text-[#555]">이미지 {{ imgIdx + 1 }}</span>
                <button
                  type="button"
                  @click="removeReadingImage(imgIdx)"
                  class="text-[#F44336] hover:text-red-700 text-[12px] font-medium transition-colors"
                >
                  삭제
                </button>
              </div>
              <FileUploadArea
                :model-value="readingImages[imgIdx] ?? ''"
                @update:model-value="updateReadingImage(imgIdx, $event)"
                accept="image/*"
                upload-type="image"
                :max-size-m-b="5"
              />
            </div>
          </div>

          <!-- Estimated time -->
          <div>
            <label class="block text-[13px] font-medium text-[#555] mb-1">예상 소요 시간 (분)</label>
            <input
              v-model="readingEstimatedTime"
              type="text"
              placeholder="예: 5"
              class="w-full px-3 py-2 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors"
            />
          </div>
        </section>

        <!-- ============================== -->
        <!-- SECTION: GAME                  -->
        <!-- ============================== -->
        <section
          v-if="contentType === 'GAME'"
          class="bg-white rounded-[16px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-6"
        >
          <h2 class="text-[15px] font-bold text-[#333] mb-4">게임 설정</h2>

          <!-- Game type -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">게임 유형</label>
            <select
              v-model="gameType"
              class="w-full px-3 py-2 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors bg-white"
            >
              <option value="matching">매칭</option>
              <option value="sequence">순서 맞추기</option>
              <option value="puzzle">퍼즐</option>
            </select>
          </div>

          <!-- Description -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">게임 설명</label>
            <textarea
              v-model="gameDescription"
              rows="3"
              placeholder="게임에 대한 설명을 입력하세요"
              class="w-full px-3 py-2 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors resize-y"
            />
          </div>

          <!-- Time limit -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">제한 시간 (초)</label>
            <input
              v-model.number="gameTimeLimit"
              type="number"
              min="0"
              placeholder="120"
              class="w-full px-3 py-2 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors"
            />
          </div>

          <!-- Divider -->
          <div class="border-t border-[#E8E8E8] my-5" />

          <!-- Items -->
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-[14px] font-semibold text-[#333]">
              항목 목록 ({{ gameItems.length }}개)
            </h3>
            <button
              type="button"
              @click="addGameItem"
              class="text-[#4CAF50] hover:text-[#388E3C] text-[13px] font-medium transition-colors"
            >
              + 항목 추가
            </button>
          </div>

          <div
            v-if="gameItems.length === 0"
            class="text-center py-6 text-[#B0B0B0] text-[14px]"
          >
            항목을 추가해주세요.
          </div>

          <div
            v-for="(item, itemIdx) in gameItems"
            :key="itemIdx"
            class="border border-[#E8E8E8] rounded-[12px] p-4 mb-3"
          >
            <div class="flex items-center justify-between mb-3">
              <span class="text-[14px] font-semibold text-[#4CAF50]">
                {{ itemIdx + 1 }}번 항목
              </span>
              <button
                type="button"
                @click="removeGameItem(itemIdx)"
                class="text-[#F44336] hover:text-red-700 text-[13px] font-medium transition-colors"
              >
                삭제
              </button>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-3">
              <div>
                <label class="block text-[13px] font-medium text-[#555] mb-1">질문</label>
                <input
                  v-model="item.question"
                  type="text"
                  placeholder="질문을 입력하세요"
                  class="w-full px-3 py-2 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors"
                />
              </div>
              <div>
                <label class="block text-[13px] font-medium text-[#555] mb-1">답</label>
                <input
                  v-model="item.answer"
                  type="text"
                  placeholder="답을 입력하세요"
                  class="w-full px-3 py-2 border border-[#E8E8E8] rounded-[12px] text-[14px] focus:outline-none focus:border-[#4CAF50] transition-colors"
                />
              </div>
            </div>

            <FileUploadArea
              :model-value="item.image_url"
              @update:model-value="item.image_url = $event"
              accept="image/*"
              upload-type="image"
              :max-size-m-b="5"
              label="항목 이미지 (선택)"
            />
          </div>
        </section>

        <!-- ============================== -->
        <!-- SECTION: Submit                -->
        <!-- ============================== -->
        <div class="pt-2">
          <button
            type="submit"
            :disabled="saving"
            class="w-full bg-[#4CAF50] hover:bg-[#388E3C] disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-[12px] py-3 font-semibold text-[15px] active:scale-[0.99] transition-all"
          >
            {{ saving ? '저장 중...' : isEdit ? '수정하기' : '등록하기' }}
          </button>
          <button
            type="button"
            @click="router.push({ name: 'content' })"
            class="w-full mt-2 bg-white hover:bg-[#F8F8F8] border border-[#E8E8E8] text-[#555] rounded-[12px] py-3 font-medium text-[15px] active:scale-[0.99] transition-all"
          >
            취소
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
