<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { contentPackageApi } from '@shared/api/contentPackageApi'
import { adminApi } from '@shared/api/adminApi'
import api from '@shared/api'
import type { ApiResponse, ContentPackage, ContentPackageItem, LearningContent, ScreeningTest, Challenge } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'

const toast = useToastStore()

const packages = ref<ContentPackage[]>([])
const loading = ref(true)
const showModal = ref(false)
const editingPackage = ref<ContentPackage | null>(null)

// 모달 폼 데이터
const formName = ref('')
const formDescription = ref('')
const formItems = ref<{ assignable_type: string; assignable_id: number; title: string }[]>([])

// 콘텐츠 선택용
const contents = ref<LearningContent[]>([])
const screenings = ref<ScreeningTest[]>([])
const challenges = ref<Challenge[]>([])
const selectTab = ref<'learning_content' | 'screening_test' | 'challenge'>('learning_content')

const saving = ref(false)
const deleteConfirmId = ref<number | null>(null)

function assignableTypeLabel(type: string): string {
  const map: Record<string, string> = {
    screening_test: '진단',
    learning_content: '학습',
    challenge: '챌린지',
  }
  return map[type] ?? type
}

const selectableItems = computed(() => {
  if (selectTab.value === 'learning_content') return contents.value.map((c) => ({ id: c.content_id, title: c.title, sub: c.content_type }))
  if (selectTab.value === 'screening_test') return screenings.value.map((s) => ({ id: s.test_id, title: s.title, sub: `${s.question_count}문항` }))
  return challenges.value.map((c) => ({ id: c.challenge_id, title: c.title, sub: c.challenge_type }))
})

const selectedIds = computed(() => {
  const ids = new Set<string>()
  for (const item of formItems.value) {
    ids.add(`${item.assignable_type}_${item.assignable_id}`)
  }
  return ids
})

function toggleSelectItem(id: number, title: string) {
  const key = `${selectTab.value}_${id}`
  if (selectedIds.value.has(key)) {
    formItems.value = formItems.value.filter(
      (item) => !(item.assignable_type === selectTab.value && item.assignable_id === id),
    )
  } else {
    formItems.value.push({ assignable_type: selectTab.value, assignable_id: id, title })
  }
}

function removeFormItem(index: number) {
  formItems.value.splice(index, 1)
}

async function fetchData() {
  loading.value = true
  try {
    const [packagesRes, contentsRes, screeningsRes, challengesRes] = await Promise.all([
      contentPackageApi.getPackages(),
      adminApi.getContents(),
      api.get<ApiResponse<ScreeningTest[]>>('/admin/screening-tests'),
      adminApi.getChallenges(),
    ])
    packages.value = packagesRes.data.data
    contents.value = contentsRes.data.data
    screenings.value = screeningsRes.data.data
    challenges.value = challengesRes.data.data
  } catch (e: any) {
    toast.error(e.response?.data?.message || '데이터를 불러오지 못했습니다.')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingPackage.value = null
  formName.value = ''
  formDescription.value = ''
  formItems.value = []
  selectTab.value = 'learning_content'
  showModal.value = true
}

function openEdit(pkg: ContentPackage) {
  editingPackage.value = pkg
  formName.value = pkg.name
  formDescription.value = pkg.description ?? ''
  formItems.value = (pkg.items ?? []).map((item: ContentPackageItem) => ({
    assignable_type: item.assignable_type,
    assignable_id: item.assignable_id,
    title: item.assignable_title ?? `ID: ${item.assignable_id}`,
  }))
  selectTab.value = 'learning_content'
  showModal.value = true
}

async function handleSave() {
  if (!formName.value.trim()) {
    toast.warning('패키지 이름을 입력해주세요.')
    return
  }
  if (formItems.value.length === 0) {
    toast.warning('콘텐츠를 1개 이상 선택해주세요.')
    return
  }

  saving.value = true
  try {
    const payload = {
      name: formName.value,
      description: formDescription.value || undefined,
      items: formItems.value.map((item) => ({
        assignable_type: item.assignable_type,
        assignable_id: item.assignable_id,
      })),
    }

    if (editingPackage.value) {
      await contentPackageApi.updatePackage(editingPackage.value.package_id, payload)
    } else {
      await contentPackageApi.createPackage(payload)
    }

    showModal.value = false
    await fetchData()
  } catch (e: any) {
    toast.error(e.response?.data?.message || '저장에 실패했습니다.')
  } finally {
    saving.value = false
  }
}

async function handleDelete(id: number) {
  try {
    await contentPackageApi.deletePackage(id)
    deleteConfirmId.value = null
    await fetchData()
  } catch (e: any) {
    toast.error(e.response?.data?.message || '삭제에 실패했습니다.')
  }
}

onMounted(fetchData)
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold text-[#333]">패키지 관리</h2>
      <button
        @click="openCreate"
        class="px-4 py-2 bg-[#4CAF50] text-white rounded-[10px] text-[14px] font-medium hover:bg-[#43A047] transition-colors"
      >
        패키지 생성
      </button>
    </div>

    <div v-if="loading" class="text-center py-10 text-[#888]">불러오는 중...</div>

    <div v-else-if="packages.length === 0" class="text-center py-10 text-[#888]">등록된 패키지가 없습니다.</div>

    <div v-else class="bg-white rounded-[16px] border border-[#E8E8E8] overflow-hidden">
      <table class="w-full text-[14px]">
        <thead>
          <tr class="bg-[#F8F8F8] text-[#666]">
            <th class="text-left px-4 py-3 font-medium">ID</th>
            <th class="text-left px-4 py-3 font-medium">이름</th>
            <th class="text-left px-4 py-3 font-medium">포함 콘텐츠</th>
            <th class="text-left px-4 py-3 font-medium">생성일</th>
            <th class="text-center px-4 py-3 font-medium">활성</th>
            <th class="text-center px-4 py-3 font-medium">관리</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="pkg in packages" :key="pkg.package_id" class="border-t border-[#F0F0F0]">
            <td class="px-4 py-3">{{ pkg.package_id }}</td>
            <td class="px-4 py-3 font-medium">{{ pkg.name }}</td>
            <td class="px-4 py-3">
              <div class="flex flex-wrap gap-1">
                <span
                  v-for="item in (pkg.items ?? [])"
                  :key="item.item_id"
                  class="px-2 py-0.5 rounded-full text-[11px]"
                  :class="item.assignable_type === 'learning_content' ? 'bg-green-50 text-green-600' : item.assignable_type === 'screening_test' ? 'bg-blue-50 text-blue-600' : 'bg-purple-50 text-purple-600'"
                >
                  {{ assignableTypeLabel(item.assignable_type) }}: {{ item.assignable_title ?? item.assignable_id }}
                </span>
              </div>
            </td>
            <td class="px-4 py-3">{{ pkg.created_at?.slice(0, 10) ?? '-' }}</td>
            <td class="px-4 py-3 text-center">
              <span
                class="px-2 py-0.5 rounded-full text-[12px]"
                :class="pkg.is_active ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-500'"
              >
                {{ pkg.is_active ? '활성' : '비활성' }}
              </span>
            </td>
            <td class="px-4 py-3 text-center">
              <div class="flex items-center justify-center gap-2">
                <button @click="openEdit(pkg)" class="text-blue-500 hover:underline text-[13px]">수정</button>
                <button
                  v-if="deleteConfirmId !== pkg.package_id"
                  @click="deleteConfirmId = pkg.package_id"
                  class="text-red-500 hover:underline text-[13px]"
                >
                  삭제
                </button>
                <template v-else>
                  <button @click="handleDelete(pkg.package_id)" class="text-red-600 font-medium text-[13px]">확인</button>
                  <button @click="deleteConfirmId = null" class="text-[#888] text-[13px]">취소</button>
                </template>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 생성/수정 모달 -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="showModal = false">
        <div class="bg-white rounded-[16px] w-full max-w-[700px] max-h-[90vh] overflow-y-auto p-6">
          <h3 class="text-[17px] font-bold text-[#333] mb-4">
            {{ editingPackage ? '패키지 수정' : '패키지 생성' }}
          </h3>

          <!-- 이름 -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">이름 *</label>
            <input
              v-model="formName"
              type="text"
              class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[10px] px-3 py-2 text-[14px] focus:border-[#4CAF50] focus:outline-none"
              placeholder="패키지 이름 입력"
            />
          </div>

          <!-- 설명 -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">설명</label>
            <textarea
              v-model="formDescription"
              rows="2"
              class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[10px] px-3 py-2 text-[14px] focus:border-[#4CAF50] focus:outline-none resize-none"
              placeholder="패키지 설명 (선택)"
            />
          </div>

          <!-- 선택된 항목 -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-2">선택된 콘텐츠 ({{ formItems.length }}개)</label>
            <div v-if="formItems.length > 0" class="flex flex-wrap gap-2">
              <span
                v-for="(item, index) in formItems"
                :key="`${item.assignable_type}_${item.assignable_id}`"
                class="inline-flex items-center gap-1 px-2 py-1 rounded-[8px] text-[12px]"
                :class="item.assignable_type === 'learning_content' ? 'bg-green-50 text-green-700' : item.assignable_type === 'screening_test' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700'"
              >
                {{ assignableTypeLabel(item.assignable_type) }}: {{ item.title }}
                <button @click="removeFormItem(index)" class="ml-1 hover:opacity-70">&times;</button>
              </span>
            </div>
            <p v-else class="text-[13px] text-[#999]">아래에서 콘텐츠를 선택하세요.</p>
          </div>

          <!-- 콘텐츠 선택 -->
          <div class="border border-[#E8E8E8] rounded-[12px] p-3">
            <div class="flex gap-1 mb-3">
              <button
                v-for="tab in [{ key: 'learning_content', label: '학습 콘텐츠' }, { key: 'screening_test', label: '진단' }, { key: 'challenge', label: '챌린지' }]"
                :key="tab.key"
                @click="selectTab = tab.key as any"
                class="px-3 py-1.5 rounded-[8px] text-[13px] transition-colors"
                :class="selectTab === tab.key ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666] hover:bg-[#E0E0E0]'"
              >
                {{ tab.label }}
              </button>
            </div>
            <div class="max-h-[250px] overflow-y-auto space-y-1">
              <label
                v-for="item in selectableItems"
                :key="item.id"
                class="flex items-center gap-2 px-3 py-2 rounded-[8px] cursor-pointer transition-colors"
                :class="selectedIds.has(`${selectTab}_${item.id}`) ? 'bg-[#E8F5E9]' : 'hover:bg-[#F5F5F5]'"
              >
                <input
                  type="checkbox"
                  :checked="selectedIds.has(`${selectTab}_${item.id}`)"
                  @change="toggleSelectItem(item.id, item.title)"
                  class="w-4 h-4 accent-[#4CAF50]"
                />
                <span class="text-[14px]">{{ item.title }}</span>
                <span class="text-[12px] text-[#888]">({{ item.sub }})</span>
              </label>
            </div>
          </div>

          <!-- 버튼 -->
          <div class="flex justify-end gap-2 mt-5">
            <button
              @click="showModal = false"
              class="px-4 py-2 bg-[#F0F0F0] text-[#555] rounded-[10px] text-[14px] hover:bg-[#E0E0E0] transition-colors"
            >
              취소
            </button>
            <button
              @click="handleSave"
              :disabled="saving"
              class="px-4 py-2 bg-[#4CAF50] text-white rounded-[10px] text-[14px] font-medium hover:bg-[#43A047] transition-colors disabled:opacity-50"
            >
              {{ saving ? '저장 중...' : editingPackage ? '수정' : '생성' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
