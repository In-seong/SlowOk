<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@shared/api'
import { contentAssignmentApi } from '@shared/api/contentAssignmentApi'
import { adminApi } from '@shared/api/adminApi'
import type { Account, ApiResponse, ScreeningTest, Challenge, LearningContent } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'

const router = useRouter()
const toast = useToastStore()

const step = ref(1)
const contentType = ref<'challenge' | 'screening_test' | 'learning_content'>('challenge')

// Step 2: 콘텐츠 선택
const challenges = ref<Challenge[]>([])
const screenings = ref<ScreeningTest[]>([])
const contents = ref<LearningContent[]>([])
const selectedItemIds = ref<Set<number>>(new Set())
const contentSearch = ref('')

// Step 3: 학습자 선택
const users = ref<Account[]>([])
const selectedProfileIds = ref<Set<number>>(new Set())
const userSearch = ref('')
const assigning = ref(false)

const loading = ref(true)

async function fetchData() {
  loading.value = true
  try {
    const [uRes, cRes, sRes, lRes] = await Promise.all([
      api.get<ApiResponse<Account[]>>('/admin/users'),
      api.get<ApiResponse<Challenge[]>>('/admin/challenges'),
      api.get<ApiResponse<ScreeningTest[]>>('/admin/screening-tests'),
      adminApi.getContents(),
    ])
    if (uRes.data.success) users.value = uRes.data.data.filter(u => u.role === 'USER')
    if (cRes.data.success) challenges.value = cRes.data.data
    if (sRes.data.success) screenings.value = sRes.data.data
    if (lRes.data.success) contents.value = lRes.data.data
  } catch { /* ignore */ }
  finally { loading.value = false }
}

onMounted(fetchData)

const currentItems = computed(() => {
  let list: { id: number; title: string }[] = []
  if (contentType.value === 'challenge') list = challenges.value.map(c => ({ id: c.challenge_id, title: c.title }))
  else if (contentType.value === 'screening_test') list = screenings.value.map(s => ({ id: s.test_id, title: s.title }))
  else list = contents.value.map(l => ({ id: l.content_id, title: l.title }))

  if (contentSearch.value.trim()) {
    const q = contentSearch.value.toLowerCase()
    list = list.filter(i => i.title.toLowerCase().includes(q))
  }
  return list
})

function toggleItem(id: number) {
  const s = new Set(selectedItemIds.value)
  if (s.has(id)) s.delete(id)
  else s.add(id)
  selectedItemIds.value = s
}

const filteredUsers = computed(() => {
  if (!userSearch.value.trim()) return users.value
  const q = userSearch.value.toLowerCase()
  return users.value.filter(u =>
    u.username.toLowerCase().includes(q) ||
    (u.profile?.name ?? '').toLowerCase().includes(q)
  )
})

function toggleProfile(profileId: number) {
  const s = new Set(selectedProfileIds.value)
  if (s.has(profileId)) s.delete(profileId)
  else s.add(profileId)
  selectedProfileIds.value = s
}

function selectAllUsers() {
  const profileIds = filteredUsers.value.filter(u => u.profile).map(u => u.profile!.profile_id)
  if (selectedProfileIds.value.size === profileIds.length) {
    selectedProfileIds.value = new Set()
  } else {
    selectedProfileIds.value = new Set(profileIds)
  }
}

function goStep2() { step.value = 2 }
function goStep3() {
  if (selectedItemIds.value.size === 0) { toast.error('콘텐츠를 선택해주세요.'); return }
  step.value = 3
}

async function handleAssign() {
  if (selectedProfileIds.value.size === 0) { toast.error('학습자를 선택해주세요.'); return }
  if (!confirm(`${selectedItemIds.value.size}개 콘텐츠를 ${selectedProfileIds.value.size}명에게 할당하시겠습니까?`)) return

  assigning.value = true
  try {
    await contentAssignmentApi.bulkAssign({
      profile_ids: Array.from(selectedProfileIds.value),
      assignments: Array.from(selectedItemIds.value).map(id => ({
        assignable_type: contentType.value,
        assignable_id: id,
      })),
    })
    toast.success('할당 완료!')
    router.back()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '할당에 실패했습니다.')
  } finally {
    assigning.value = false
  }
}

const typeOptions = [
  { value: 'challenge' as const, label: '챌린지', icon: '🏆' },
  { value: 'screening_test' as const, label: '진단', icon: '🔬' },
  { value: 'learning_content' as const, label: '학습 콘텐츠', icon: '📚' },
]

watch(contentType, () => { selectedItemIds.value = new Set() })
</script>

<template>
  <div class="min-h-screen bg-[#FAFAFA]">
    <header class="sticky top-0 z-40 bg-white border-b border-[#E8E8E8] h-[56px] flex items-center px-4">
      <button @click="step > 1 ? step-- : router.back()" class="w-10 h-10 flex items-center justify-center">
        <svg class="w-5 h-5 text-[#333]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <h1 class="flex-1 text-center text-[16px] font-bold text-[#333]">콘텐츠 할당</h1>
      <span class="text-[13px] text-[#888] w-10 text-right">{{ step }}/3</span>
    </header>

    <div v-if="loading" class="flex justify-center py-20">
      <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin" />
    </div>

    <!-- Step 1: 유형 선택 -->
    <div v-else-if="step === 1" class="px-4 py-5 space-y-4">
      <p class="text-[15px] font-semibold text-[#333]">할당할 콘텐츠 유형을 선택하세요</p>
      <div class="space-y-3">
        <button
          v-for="t in typeOptions"
          :key="t.value"
          @click="contentType = t.value; goStep2()"
          class="w-full flex items-center gap-4 p-5 bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] active:scale-[0.98] transition-transform"
        >
          <span class="text-[32px]">{{ t.icon }}</span>
          <span class="text-[16px] font-semibold text-[#333]">{{ t.label }}</span>
        </button>
      </div>
    </div>

    <!-- Step 2: 콘텐츠 선택 -->
    <div v-else-if="step === 2" class="px-4 py-4 space-y-3">
      <input v-model="contentSearch" type="text" placeholder="검색..." class="w-full bg-white border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[14px] focus:outline-none focus:border-[#4CAF50]" />

      <div class="space-y-2 max-h-[calc(100vh-240px)] overflow-y-auto">
        <div
          v-for="item in currentItems"
          :key="item.id"
          @click="toggleItem(item.id)"
          class="flex items-center gap-3 p-4 bg-white rounded-[16px] shadow-sm border active:bg-[#FAFAFA]"
          :class="selectedItemIds.has(item.id) ? 'border-[#4CAF50] bg-[#E8F5E9]' : 'border-[#E8E8E8]'"
        >
          <input type="checkbox" :checked="selectedItemIds.has(item.id)" class="w-5 h-5 accent-[#4CAF50] pointer-events-none" />
          <span class="text-[14px] text-[#333]">{{ item.title }}</span>
        </div>
      </div>

      <div class="sticky bottom-0 bg-[#FAFAFA] pt-3 pb-[max(16px,env(safe-area-inset-bottom))]">
        <button
          @click="goStep3"
          :disabled="selectedItemIds.size === 0"
          class="w-full py-3.5 bg-[#4CAF50] text-white rounded-[12px] text-[15px] font-semibold disabled:opacity-50 active:bg-[#388E3C]"
        >다음 ({{ selectedItemIds.size }}개 선택)</button>
      </div>
    </div>

    <!-- Step 3: 학습자 선택 -->
    <div v-else-if="step === 3" class="px-4 py-4 space-y-3">
      <div class="flex items-center gap-2">
        <input v-model="userSearch" type="text" placeholder="학습자 검색..." class="flex-1 bg-white border border-[#E8E8E8] rounded-[12px] px-4 py-3 text-[14px] focus:outline-none focus:border-[#4CAF50]" />
        <button @click="selectAllUsers" class="text-[12px] text-[#4CAF50] font-medium shrink-0">
          {{ selectedProfileIds.size === filteredUsers.filter(u => u.profile).length ? '해제' : '전체' }}
        </button>
      </div>

      <div class="space-y-2 max-h-[calc(100vh-320px)] overflow-y-auto">
        <div
          v-for="u in filteredUsers"
          :key="u.account_id"
          @click="u.profile && toggleProfile(u.profile.profile_id)"
          class="flex items-center gap-3 p-4 bg-white rounded-[16px] shadow-sm border active:bg-[#FAFAFA]"
          :class="u.profile && selectedProfileIds.has(u.profile.profile_id) ? 'border-[#4CAF50] bg-[#E8F5E9]' : 'border-[#E8E8E8]'"
        >
          <input v-if="u.profile" type="checkbox" :checked="selectedProfileIds.has(u.profile.profile_id)" class="w-5 h-5 accent-[#4CAF50] pointer-events-none" />
          <div>
            <p class="text-[14px] font-medium text-[#333]">{{ u.profile?.name ?? u.username }}</p>
            <p class="text-[12px] text-[#888]">{{ u.username }}</p>
          </div>
        </div>
      </div>

      <div class="sticky bottom-0 bg-[#FAFAFA] pt-3 pb-[max(16px,env(safe-area-inset-bottom))] space-y-2">
        <div class="bg-white rounded-[12px] border border-[#E8E8E8] px-4 py-3 text-[13px] text-[#555]">
          {{ typeOptions.find(t => t.value === contentType)?.label }} {{ selectedItemIds.size }}개 → 학습자 {{ selectedProfileIds.size }}명
        </div>
        <button
          @click="handleAssign"
          :disabled="assigning || selectedProfileIds.size === 0"
          class="w-full py-3.5 bg-[#4CAF50] text-white rounded-[12px] text-[15px] font-semibold disabled:opacity-50 active:bg-[#388E3C] flex items-center justify-center gap-2"
        >
          <div v-if="assigning" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
          {{ assigning ? '할당 중...' : '할당하기' }}
        </button>
      </div>
    </div>
  </div>
</template>
