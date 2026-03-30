<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import api from '@shared/api'
import { contentAssignmentApi } from '@shared/api/contentAssignmentApi'
import { contentPackageApi } from '@shared/api/contentPackageApi'
import { adminApi } from '@shared/api/adminApi'
import type { Account, ApiResponse, ContentAssignment, ContentPackage, LearningContent, ScreeningTest, Challenge } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'

const toast = useToastStore()

const activeTab = ref<'learning_content' | 'screening_test' | 'challenge' | 'package'>('challenge')
const users = ref<Account[]>([])
const contents = ref<LearningContent[]>([])
const screenings = ref<ScreeningTest[]>([])
const challenges = ref<Challenge[]>([])
const packages = ref<ContentPackage[]>([])

const selectedProfileIds = ref<Set<number>>(new Set())
const selectedItemIds = ref<Set<number>>(new Set())
const selectedPackageIds = ref<Set<number>>(new Set())
const existingAssignments = ref<ContentAssignment[]>([])
const userSearch = ref('')
const loading = ref(true)
const assigning = ref(false)
const loadingAssignments = ref(false)

// Account별 profiles를 부모-자녀 그룹으로 정리
interface ProfileItem {
  profileId: number
  name: string
  username: string
}

interface AccountGroup {
  accountId: number
  username: string
  profiles: ProfileItem[]
}

const accountGroups = computed<AccountGroup[]>(() => {
  const result: AccountGroup[] = []
  for (const u of users.value) {
    if (u.role !== 'USER') continue
    const profiles = u.profile ? [u.profile] : []
    if (profiles.length === 0) continue

    const items: ProfileItem[] = profiles.map((p: import('@shared/types').UserProfile) => ({
      profileId: p.profile_id,
      name: (p as any).decrypted_name ?? p.name ?? u.username,
      username: u.username,
    }))

    result.push({ accountId: u.account_id, username: u.username, profiles: items })
  }
  return result
})

const filteredGroups = computed(() => {
  if (!userSearch.value.trim()) return accountGroups.value
  const q = userSearch.value.toLowerCase()
  return accountGroups.value.filter((g) =>
    g.username.toLowerCase().includes(q) ||
    g.profiles.some(p => p.name.toLowerCase().includes(q))
  )
})

// 현재 탭에서 선택된 사용자들에게 이미 할당된 아이템 ID set
const alreadyAssignedIds = computed(() => {
  const ids = new Set<number>()
  for (const a of existingAssignments.value) {
    if (a.assignable_type === activeTab.value) {
      ids.add(a.assignable_id)
    }
  }
  return ids
})

function toggleUser(profileId: number) {
  if (selectedProfileIds.value.has(profileId)) {
    selectedProfileIds.value.delete(profileId)
  } else {
    selectedProfileIds.value.add(profileId)
  }
  selectedProfileIds.value = new Set(selectedProfileIds.value)
}

function toggleItem(id: number) {
  if (selectedItemIds.value.has(id)) {
    selectedItemIds.value.delete(id)
  } else {
    selectedItemIds.value.add(id)
  }
  selectedItemIds.value = new Set(selectedItemIds.value)
}

// 선택된 사용자 변경 시 기존 할당 조회
watch(selectedProfileIds, async (ids) => {
  if (ids.size === 0) {
    existingAssignments.value = []
    return
  }
  loadingAssignments.value = true
  try {
    const allAssignments: ContentAssignment[] = []
    // 선택된 사용자별로 할당 조회
    const promises = Array.from(ids).map((profileId) =>
      contentAssignmentApi.getAssignments({ profile_id: profileId }),
    )
    const results = await Promise.all(promises)
    for (const res of results) {
      if (res.data.success) {
        allAssignments.push(...res.data.data)
      }
    }
    existingAssignments.value = allAssignments
  } catch (e: any) {
    toast.error(e.response?.data?.message || '데이터를 불러오지 못했습니다.')
    existingAssignments.value = []
  } finally {
    loadingAssignments.value = false
  }
})

function togglePackage(packageId: number) {
  if (selectedPackageIds.value.has(packageId)) {
    selectedPackageIds.value.delete(packageId)
  } else {
    selectedPackageIds.value.add(packageId)
  }
  selectedPackageIds.value = new Set(selectedPackageIds.value)
}

async function fetchData() {
  loading.value = true
  try {
    const [usersRes, contentsRes, screeningsRes, challengesRes, packagesRes] = await Promise.all([
      api.get<ApiResponse<Account[]>>('/admin/users'),
      adminApi.getContents(),
      api.get<ApiResponse<ScreeningTest[]>>('/admin/screening-tests'),
      adminApi.getChallenges(),
      contentPackageApi.getPackages(),
    ])
    users.value = usersRes.data.data
    contents.value = contentsRes.data.data
    screenings.value = screeningsRes.data.data
    challenges.value = challengesRes.data.data
    packages.value = packagesRes.data.data
  } catch (e: any) {
    toast.error(e.response?.data?.message || '데이터를 불러오지 못했습니다.')
  } finally {
    loading.value = false
  }
}

async function handleAssign() {
  if (activeTab.value === 'package') {
    if (selectedProfileIds.value.size === 0 || selectedPackageIds.value.size === 0) {
      toast.warning('사용자와 패키지를 각각 1개 이상 선택해주세요.')
      return
    }
    assigning.value = true
    try {
      const profileIds = Array.from(selectedProfileIds.value)
      for (const packageId of selectedPackageIds.value) {
        await contentPackageApi.assignPackage(packageId, { profile_ids: profileIds })
      }
      toast.success('패키지 할당이 완료되었습니다.')
      selectedPackageIds.value = new Set()
    } catch (e: any) {
      toast.error(e.response?.data?.message || '할당에 실패했습니다.')
    } finally {
      assigning.value = false
    }
    return
  }

  if (selectedProfileIds.value.size === 0 || selectedItemIds.value.size === 0) {
    toast.warning('사용자와 콘텐츠를 각각 1개 이상 선택해주세요.')
    return
  }
  assigning.value = true
  try {
    await contentAssignmentApi.bulkAssign({
      profile_ids: Array.from(selectedProfileIds.value),
      assignments: Array.from(selectedItemIds.value).map((id) => ({
        assignable_type: activeTab.value,
        assignable_id: id,
      })),
    })
    toast.success('할당이 완료되었습니다.')
    selectedItemIds.value = new Set()
    // 할당 후 기존 할당 목록 새로고침
    const ids = selectedProfileIds.value
    if (ids.size > 0) {
      const allAssignments: ContentAssignment[] = []
      const promises = Array.from(ids).map((profileId) =>
        contentAssignmentApi.getAssignments({ profile_id: profileId }),
      )
      const results = await Promise.all(promises)
      for (const res of results) {
        if (res.data.success) {
          allAssignments.push(...res.data.data)
        }
      }
      existingAssignments.value = allAssignments
    }
  } catch (e: any) {
    toast.error(e.response?.data?.message || '할당에 실패했습니다.')
  } finally {
    assigning.value = false
  }
}

async function handleRemoveAssignment(assignmentId: number) {
  if (!confirm('할당을 해제하시겠습니까?')) return
  try {
    await contentAssignmentApi.deleteAssignment(assignmentId)
    existingAssignments.value = existingAssignments.value.filter((a) => a.assignment_id !== assignmentId)
  } catch (e: any) {
    toast.error(e.response?.data?.message || '해제에 실패했습니다.')
  }
}

const currentItems = computed(() => {
  if (activeTab.value === 'learning_content') return contents.value.map((c) => ({ id: c.content_id, title: c.title, sub: c.content_type }))
  if (activeTab.value === 'screening_test') return screenings.value.map((s) => ({ id: s.test_id, title: s.title, sub: `${s.question_count}문항` }))
  return challenges.value.map((c) => ({ id: c.challenge_id, title: c.title, sub: c.challenge_type }))
})

// 현재 탭의 기존 할당 목록 (해제용)
const currentTabAssignments = computed(() => {
  return existingAssignments.value.filter((a) => a.assignable_type === activeTab.value)
})

type TabKey = 'learning_content' | 'screening_test' | 'challenge' | 'package'

const tabOptions: { key: TabKey; label: string }[] = [
  // { key: 'learning_content', label: '학습 콘텐츠' }, // [미사용]
  { key: 'screening_test', label: '진단' },
  { key: 'challenge', label: '챌린지' },
  // { key: 'package', label: '패키지' }, // [미사용]
]

function switchTab(tab: TabKey) {
  activeTab.value = tab
  selectedItemIds.value = new Set()
  selectedPackageIds.value = new Set()
}

function assignableTypeLabel(type: string): string {
  const map: Record<string, string> = {
    screening_test: '진단',
    learning_content: '학습 콘텐츠',
    challenge: '챌린지',
  }
  return map[type] ?? type
}

function getAssignableName(assignment: ContentAssignment): string {
  const type = assignment.assignable_type
  const id = assignment.assignable_id
  if (type === 'challenge') {
    return challenges.value.find(c => c.challenge_id === id)?.title ?? `챌린지 #${id}`
  }
  if (type === 'screening_test') {
    return screenings.value.find(s => s.test_id === id)?.title ?? `진단 #${id}`
  }
  if (type === 'learning_content') {
    return contents.value.find(c => c.content_id === id)?.title ?? `콘텐츠 #${id}`
  }
  return `#${id}`
}

onMounted(fetchData)
</script>

<template>
  <div class="p-6">
    <h2 class="text-xl font-bold text-[#333] mb-6">콘텐츠 할당</h2>

    <div v-if="loading" class="text-center py-10 text-[#888]">불러오는 중...</div>
    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <!-- 좌측: 사용자 선택 -->
      <div class="bg-white rounded-[16px] border border-[#E8E8E8] p-4">
        <h3 class="text-[15px] font-semibold text-[#333] mb-3">사용자 선택</h3>
        <input
          v-model="userSearch"
          type="text"
          placeholder="이름 또는 아이디 검색..."
          class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[10px] px-3 py-2 text-[14px] focus:border-[#4CAF50] focus:outline-none mb-3"
        />
        <div class="max-h-[400px] overflow-y-auto space-y-0.5">
          <template v-for="group in filteredGroups" :key="group.accountId">
            <div
              v-for="p in group.profiles"
              :key="p.profileId"
              class="flex items-center gap-2 px-3 py-2 rounded-[8px] cursor-pointer transition-colors"
              :class="selectedProfileIds.has(p.profileId) ? 'bg-[#E8F5E9]' : 'hover:bg-[#F5F5F5]'"
              @click="toggleUser(p.profileId)"
            >
              <input
                type="checkbox"
                :checked="selectedProfileIds.has(p.profileId)"
                class="w-4 h-4 accent-[#4CAF50] pointer-events-none"
              />
              <span class="text-[14px]">{{ p.name }}</span>
              <span class="text-[12px] text-[#888]">({{ p.username }})</span>
            </div>
          </template>
        </div>
        <p class="text-[12px] text-[#888] mt-2">{{ selectedProfileIds.size }}명 선택됨</p>
      </div>

      <!-- 우측: 콘텐츠 선택 -->
      <div class="bg-white rounded-[16px] border border-[#E8E8E8] p-4">
        <div class="flex gap-1 mb-3">
          <button
            v-for="tab in tabOptions"
            :key="tab.key"
            @click="switchTab(tab.key)"
            class="px-3 py-1.5 rounded-[8px] text-[13px] transition-colors"
            :class="activeTab === tab.key ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666] hover:bg-[#E0E0E0]'"
          >
            {{ tab.label }}
          </button>
        </div>

        <div v-if="loadingAssignments" class="text-center py-3 text-[13px] text-[#888]">할당 정보 조회중...</div>

        <!-- 패키지 탭 -->
        <template v-if="activeTab === 'package'">
          <div class="max-h-[400px] overflow-y-auto space-y-1">
            <label
              v-for="pkg in packages"
              :key="pkg.package_id"
              class="flex items-center gap-2 px-3 py-2 rounded-[8px] cursor-pointer transition-colors"
              :class="selectedPackageIds.has(pkg.package_id) ? 'bg-[#E8F5E9]' : 'hover:bg-[#F5F5F5]'"
            >
              <input
                type="checkbox"
                :checked="selectedPackageIds.has(pkg.package_id)"
                @change="togglePackage(pkg.package_id)"
                class="w-4 h-4 accent-[#4CAF50]"
              />
              <span class="text-[14px]">{{ pkg.name }}</span>
              <span class="text-[12px] text-[#888]">({{ pkg.items?.length ?? 0 }}개 항목)</span>
            </label>
          </div>
          <p class="text-[12px] text-[#888] mt-2">{{ selectedPackageIds.size }}개 패키지 선택</p>
        </template>

        <!-- 개별 콘텐츠 탭 -->
        <template v-else>
          <div class="max-h-[400px] overflow-y-auto space-y-1">
            <label
              v-for="item in currentItems"
              :key="item.id"
              class="flex items-center gap-2 px-3 py-2 rounded-[8px] cursor-pointer transition-colors"
              :class="alreadyAssignedIds.has(item.id) ? 'bg-blue-50' : selectedItemIds.has(item.id) ? 'bg-[#E8F5E9]' : 'hover:bg-[#F5F5F5]'"
            >
              <input
                type="checkbox"
                :checked="selectedItemIds.has(item.id) || alreadyAssignedIds.has(item.id)"
                :disabled="alreadyAssignedIds.has(item.id)"
                @change="toggleItem(item.id)"
                class="w-4 h-4 accent-[#4CAF50]"
              />
              <span class="text-[14px]" :class="alreadyAssignedIds.has(item.id) ? 'text-[#888]' : ''">{{ item.title }}</span>
              <span class="text-[12px] text-[#888]">({{ item.sub }})</span>
              <span v-if="alreadyAssignedIds.has(item.id)" class="ml-auto text-[11px] text-blue-500 font-medium">할당됨</span>
            </label>
          </div>
          <p class="text-[12px] text-[#888] mt-2">
            {{ selectedItemIds.size }}개 새로 선택
            <span v-if="alreadyAssignedIds.size > 0" class="text-blue-500">({{ alreadyAssignedIds.size }}개 이미 할당됨)</span>
          </p>
        </template>
      </div>
    </div>

    <!-- 할당 버튼 -->
    <div class="mt-4 flex items-center gap-3">
      <button
        @click="handleAssign"
        :disabled="assigning || selectedProfileIds.size === 0 || (activeTab === 'package' ? selectedPackageIds.size === 0 : selectedItemIds.size === 0)"
        class="px-6 py-2.5 bg-[#4CAF50] text-white rounded-[10px] text-[14px] font-medium hover:bg-[#43A047] transition-colors disabled:opacity-50"
      >
        {{ assigning ? '할당 중...' : activeTab === 'package' ? `선택한 사용자(${selectedProfileIds.size}명)에게 패키지(${selectedPackageIds.size}개) 할당` : `선택한 사용자(${selectedProfileIds.size}명)에게 콘텐츠(${selectedItemIds.size}개) 할당` }}
      </button>
    </div>

    <!-- 기존 할당 목록 -->
    <div v-if="activeTab !== 'package' && selectedProfileIds.size > 0 && currentTabAssignments.length > 0" class="mt-6">
      <h3 class="text-[15px] font-semibold text-[#333] mb-3">현재 {{ assignableTypeLabel(activeTab) }} 할당 목록</h3>
      <div class="bg-white rounded-[16px] border border-[#E8E8E8] overflow-hidden">
        <table class="w-full text-[14px]">
          <thead>
            <tr class="bg-[#F8F8F8] text-[#666]">
              <th class="text-left px-4 py-3 font-medium">이름</th>
              <th class="text-left px-4 py-3 font-medium">상태</th>
              <th class="text-left px-4 py-3 font-medium">할당일</th>
              <th class="text-center px-4 py-3 font-medium">관리</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="a in currentTabAssignments" :key="a.assignment_id" class="border-t border-[#F0F0F0]">
              <td class="px-4 py-3 font-medium text-[#333]">{{ getAssignableName(a) }}</td>
              <td class="px-4 py-3">
                <span
                  class="px-2 py-0.5 rounded-full text-[12px] font-medium"
                  :class="a.status === 'COMPLETED' ? 'bg-green-50 text-green-600' : a.status === 'IN_PROGRESS' ? 'bg-blue-50 text-blue-600' : 'bg-gray-100 text-gray-600'"
                >
                  {{ a.status === 'COMPLETED' ? '완료' : a.status === 'IN_PROGRESS' ? '진행중' : '할당됨' }}
                </span>
              </td>
              <td class="px-4 py-3">{{ a.assigned_at?.slice(0, 10) ?? '-' }}</td>
              <td class="px-4 py-3 text-center">
                <button @click="handleRemoveAssignment(a.assignment_id)" class="text-red-500 hover:underline text-[13px]">해제</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
