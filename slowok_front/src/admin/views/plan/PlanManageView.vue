<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { featureApi, planApi, institutionPlanApi } from '@shared/api/featureApi'
import { adminApi } from '@shared/api/adminApi'
import type { Feature, Plan, InstitutionPlanData, Institution } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'

const toast = useToastStore()

// 탭: features | plans | assign
type TabKey = 'features' | 'plans' | 'assign'
const activeTab = ref<TabKey>('plans')

// === 기능 목록 ===
const features = ref<Feature[]>([])
const featureLoading = ref(true)
const showFeatureModal = ref(false)
const editingFeature = ref<Feature | null>(null)
const featureForm = ref({ feature_key: '', name: '', description: '', category: '', sort_order: 0 })

// === 플랜 목록 ===
const plans = ref<Plan[]>([])
const planLoading = ref(true)
const showPlanModal = ref(false)
const editingPlan = ref<Plan | null>(null)
const planForm = ref({ name: '', description: '', price: 0, sort_order: 0, feature_ids: [] as number[] })

// === 기관 플랜 배정 ===
const institutionPlans = ref<InstitutionPlanData[]>([])
const institutions = ref<Institution[]>([])
const assignLoading = ref(true)
const showAssignModal = ref(false)
const assignForm = ref({ institution_id: null as number | null, plan_id: null as number | null, expires_at: '' })

const featureCategories = computed(() => {
  const cats = new Set(features.value.map(f => f.category))
  return Array.from(cats).sort()
})

// 카테고리별 기능 그룹
const featuresByCategory = computed(() => {
  const map: Record<string, Feature[]> = {}
  for (const f of features.value) {
    if (!map[f.category]) map[f.category] = []
    map[f.category]!.push(f)
  }
  return map
})

async function fetchFeatures() {
  featureLoading.value = true
  try {
    const res = await featureApi.list()
    if (res.data.success) features.value = res.data.data
  } catch { toast.error('기능 목록을 불러올 수 없습니다.') }
  finally { featureLoading.value = false }
}

async function fetchPlans() {
  planLoading.value = true
  try {
    const res = await planApi.list()
    if (res.data.success) plans.value = res.data.data
  } catch { toast.error('플랜 목록을 불러올 수 없습니다.') }
  finally { planLoading.value = false }
}

async function fetchInstitutionPlans() {
  assignLoading.value = true
  try {
    const [ipRes, instRes] = await Promise.all([
      institutionPlanApi.list(),
      adminApi.getInstitutions(),
    ])
    if (ipRes.data.success) institutionPlans.value = ipRes.data.data
    if (instRes.data.success) institutions.value = instRes.data.data
  } catch { toast.error('기관 플랜 목록을 불러올 수 없습니다.') }
  finally { assignLoading.value = false }
}

onMounted(async () => {
  await Promise.all([fetchFeatures(), fetchPlans()])
})

// === 기능 CRUD ===
function openFeatureModal(feature?: Feature) {
  editingFeature.value = feature ?? null
  featureForm.value = feature
    ? { feature_key: feature.feature_key, name: feature.name, description: feature.description ?? '', category: feature.category, sort_order: feature.sort_order ?? 0 }
    : { feature_key: '', name: '', description: '', category: '', sort_order: 0 }
  showFeatureModal.value = true
}

async function saveFeature() {
  try {
    if (editingFeature.value) {
      await featureApi.update(editingFeature.value.feature_id, featureForm.value)
      toast.success('기능이 수정되었습니다.')
    } else {
      await featureApi.create(featureForm.value)
      toast.success('기능이 등록되었습니다.')
    }
    showFeatureModal.value = false
    await fetchFeatures()
  } catch (e: any) {
    toast.error(e.response?.data?.message || '저장에 실패했습니다.')
  }
}

async function deleteFeature(id: number) {
  if (!confirm('이 기능을 삭제하시겠습니까?')) return
  try {
    await featureApi.destroy(id)
    toast.success('기능이 삭제되었습니다.')
    await fetchFeatures()
  } catch (e: any) {
    toast.error(e.response?.data?.message || '삭제에 실패했습니다.')
  }
}

// === 플랜 CRUD ===
function openPlanModal(plan?: Plan) {
  editingPlan.value = plan ?? null
  planForm.value = plan
    ? { name: plan.name, description: plan.description ?? '', price: plan.price ?? 0, sort_order: plan.sort_order ?? 0, feature_ids: plan.features?.map(f => f.feature_id) ?? [] }
    : { name: '', description: '', price: 0, sort_order: 0, feature_ids: [] }
  showPlanModal.value = true
}

async function savePlan() {
  if (planForm.value.feature_ids.length === 0) {
    toast.warning('최소 1개 이상의 기능을 선택해주세요.')
    return
  }
  try {
    if (editingPlan.value) {
      await planApi.update(editingPlan.value.plan_id, planForm.value)
      toast.success('플랜이 수정되었습니다.')
    } else {
      await planApi.create(planForm.value)
      toast.success('플랜이 생성되었습니다.')
    }
    showPlanModal.value = false
    await fetchPlans()
  } catch (e: any) {
    toast.error(e.response?.data?.message || '저장에 실패했습니다.')
  }
}

async function deletePlan(id: number) {
  if (!confirm('이 플랜을 삭제하시겠습니까?')) return
  try {
    await planApi.destroy(id)
    toast.success('플랜이 삭제되었습니다.')
    await fetchPlans()
  } catch (e: any) {
    toast.error(e.response?.data?.message || '삭제에 실패했습니다.')
  }
}

function toggleFeatureInPlan(featureId: number) {
  const idx = planForm.value.feature_ids.indexOf(featureId)
  if (idx === -1) {
    planForm.value.feature_ids.push(featureId)
  } else {
    planForm.value.feature_ids.splice(idx, 1)
  }
}

// === 기관 플랜 배정 ===
function openAssignModal() {
  assignForm.value = { institution_id: null, plan_id: null, expires_at: '' }
  showAssignModal.value = true
}

async function saveAssign() {
  if (!assignForm.value.institution_id || !assignForm.value.plan_id) {
    toast.warning('기관과 플랜을 선택해주세요.')
    return
  }
  try {
    await institutionPlanApi.assign({
      institution_id: assignForm.value.institution_id,
      plan_id: assignForm.value.plan_id,
      expires_at: assignForm.value.expires_at || undefined,
    })
    toast.success('기관 플랜이 배정되었습니다.')
    showAssignModal.value = false
    await fetchInstitutionPlans()
  } catch (e: any) {
    toast.error(e.response?.data?.message || '배정에 실패했습니다.')
  }
}

async function removeAssign(id: number) {
  if (!confirm('기관 플랜을 해제하시겠습니까?')) return
  try {
    await institutionPlanApi.remove(id)
    toast.success('기관 플랜이 해제되었습니다.')
    await fetchInstitutionPlans()
  } catch (e: any) {
    toast.error(e.response?.data?.message || '해제에 실패했습니다.')
  }
}

function switchTab(tab: TabKey) {
  activeTab.value = tab
  if (tab === 'assign' && institutions.value.length === 0) {
    fetchInstitutionPlans()
  }
}

function getInstitutionPlan(instId: number): InstitutionPlanData | undefined {
  return institutionPlans.value.find(ip => ip.institution_id === instId)
}

function formatPrice(price: number | null): string {
  if (!price) return '무료'
  return price.toLocaleString() + '원'
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold text-gray-900">플랜 및 기능 관리</h1>
    </div>

    <!-- 탭 -->
    <div class="flex gap-1 mb-6 bg-gray-100 rounded-lg p-1">
      <button
        v-for="tab in [
          { key: 'plans' as TabKey, label: '플랜 관리' },
          { key: 'features' as TabKey, label: '기능 관리' },
          { key: 'assign' as TabKey, label: '기관 배정' },
        ]"
        :key="tab.key"
        @click="switchTab(tab.key)"
        class="flex-1 py-2 px-3 text-sm font-medium rounded-md transition-colors"
        :class="activeTab === tab.key ? 'bg-white text-[#7C3AED] shadow-sm' : 'text-gray-500 hover:text-gray-700'"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- 플랜 관리 탭 -->
    <div v-if="activeTab === 'plans'">
      <div class="flex justify-end mb-4">
        <button @click="openPlanModal()" class="bg-[#7C3AED] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#6D28D9]">
          플랜 추가
        </button>
      </div>
      <div v-if="planLoading" class="text-center py-10 text-gray-400">불러오는 중...</div>
      <div v-else-if="plans.length === 0" class="text-center py-10 text-gray-400">등록된 플랜이 없습니다.</div>
      <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="plan in plans" :key="plan.plan_id" class="bg-white rounded-xl border border-gray-200 p-5">
          <div class="flex items-start justify-between mb-3">
            <div>
              <h3 class="font-bold text-gray-900">{{ plan.name }}</h3>
              <p class="text-lg font-bold text-[#7C3AED] mt-1">{{ formatPrice(plan.price) }}</p>
            </div>
            <div class="flex gap-1">
              <button @click="openPlanModal(plan)" class="text-gray-400 hover:text-[#7C3AED] p-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </button>
              <button @click="deletePlan(plan.plan_id)" class="text-gray-400 hover:text-red-500 p-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              </button>
            </div>
          </div>
          <p v-if="plan.description" class="text-sm text-gray-500 mb-3">{{ plan.description }}</p>
          <div class="flex flex-wrap gap-1">
            <span v-for="f in plan.features" :key="f.feature_id" class="inline-block bg-[#F3E8FF] text-[#7C3AED] text-xs px-2 py-0.5 rounded-full">
              {{ f.name }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- 기능 관리 탭 -->
    <div v-if="activeTab === 'features'">
      <div class="flex justify-end mb-4">
        <button @click="openFeatureModal()" class="bg-[#7C3AED] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#6D28D9]">
          기능 추가
        </button>
      </div>
      <div v-if="featureLoading" class="text-center py-10 text-gray-400">불러오는 중...</div>
      <div v-else-if="features.length === 0" class="text-center py-10 text-gray-400">등록된 기능이 없습니다.</div>
      <div v-else>
        <div v-for="category in featureCategories" :key="category" class="mb-6">
          <h3 class="text-sm font-bold text-gray-700 mb-2 uppercase">{{ category }}</h3>
          <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            <div v-for="feature in featuresByCategory[category]" :key="feature.feature_id" class="flex items-center justify-between px-4 py-3">
              <div>
                <span class="font-medium text-gray-900">{{ feature.name }}</span>
                <span class="ml-2 text-xs text-gray-400 font-mono">{{ feature.feature_key }}</span>
                <p v-if="feature.description" class="text-sm text-gray-500 mt-0.5">{{ feature.description }}</p>
              </div>
              <div class="flex gap-1">
                <button @click="openFeatureModal(feature)" class="text-gray-400 hover:text-[#7C3AED] p-1">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button @click="deleteFeature(feature.feature_id)" class="text-gray-400 hover:text-red-500 p-1">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 기관 배정 탭 -->
    <div v-if="activeTab === 'assign'">
      <div class="flex justify-end mb-4">
        <button @click="openAssignModal()" class="bg-[#7C3AED] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#6D28D9]">
          플랜 배정
        </button>
      </div>
      <div v-if="assignLoading" class="text-center py-10 text-gray-400">불러오는 중...</div>
      <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="text-left px-4 py-3 font-medium text-gray-600">기관</th>
              <th class="text-left px-4 py-3 font-medium text-gray-600">플랜</th>
              <th class="text-left px-4 py-3 font-medium text-gray-600">만료일</th>
              <th class="text-right px-4 py-3 font-medium text-gray-600">관리</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="ip in institutionPlans" :key="ip.institution_plan_id">
              <td class="px-4 py-3 font-medium">{{ ip.institution?.name }}</td>
              <td class="px-4 py-3">
                <span class="bg-[#F3E8FF] text-[#7C3AED] text-xs px-2 py-0.5 rounded-full font-medium">
                  {{ ip.plan?.name }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-500">{{ ip.expires_at ? ip.expires_at.slice(0, 10) : '무제한' }}</td>
              <td class="px-4 py-3 text-right">
                <button @click="removeAssign(ip.institution_plan_id)" class="text-red-500 hover:text-red-700 text-xs">해제</button>
              </td>
            </tr>
            <tr v-if="institutionPlans.length === 0">
              <td colspan="4" class="px-4 py-8 text-center text-gray-400">배정된 플랜이 없습니다.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 기능 모달 -->
    <Teleport to="body">
      <div v-if="showFeatureModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="showFeatureModal = false">
        <div class="bg-white rounded-2xl w-full max-w-md p-6">
          <h2 class="text-lg font-bold mb-4">{{ editingFeature ? '기능 수정' : '기능 추가' }}</h2>
          <div class="space-y-3">
            <div v-if="!editingFeature">
              <label class="block text-sm font-medium text-gray-700 mb-1">기능 키</label>
              <input v-model="featureForm.feature_key" type="text" placeholder="screening, challenge, parent_report ..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">기능명</label>
              <input v-model="featureForm.name" type="text" placeholder="진단 검사" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">카테고리</label>
              <input v-model="featureForm.category" type="text" placeholder="학습, 진단, 운영 ..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">설명</label>
              <input v-model="featureForm.description" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
          </div>
          <div class="flex gap-2 mt-5">
            <button @click="showFeatureModal = false" class="flex-1 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium">취소</button>
            <button @click="saveFeature" class="flex-1 py-2 bg-[#7C3AED] text-white rounded-lg text-sm font-medium hover:bg-[#6D28D9]">저장</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- 플랜 모달 -->
    <Teleport to="body">
      <div v-if="showPlanModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="showPlanModal = false">
        <div class="bg-white rounded-2xl w-full max-w-lg p-6 max-h-[80vh] overflow-y-auto">
          <h2 class="text-lg font-bold mb-4">{{ editingPlan ? '플랜 수정' : '플랜 추가' }}</h2>
          <div class="space-y-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">플랜명</label>
              <input v-model="planForm.name" type="text" placeholder="Basic / Pro / Max" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">설명</label>
              <input v-model="planForm.description" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">가격 (원/월)</label>
              <input v-model.number="planForm.price" type="number" min="0" step="10000" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">포함 기능</label>
              <div v-for="category in featureCategories" :key="category" class="mb-3">
                <p class="text-xs font-bold text-gray-500 uppercase mb-1">{{ category }}</p>
                <div class="space-y-1">
                  <label v-for="f in featuresByCategory[category]" :key="f.feature_id" class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input
                      type="checkbox"
                      :checked="planForm.feature_ids.includes(f.feature_id)"
                      @change="toggleFeatureInPlan(f.feature_id)"
                      class="rounded border-gray-300 text-[#7C3AED] focus:ring-[#7C3AED]"
                    />
                    <span class="text-sm">{{ f.name }}</span>
                    <span class="text-xs text-gray-400 font-mono">{{ f.feature_key }}</span>
                  </label>
                </div>
              </div>
              <p v-if="features.length === 0" class="text-sm text-gray-400">먼저 기능을 등록해주세요.</p>
            </div>
          </div>
          <div class="flex gap-2 mt-5">
            <button @click="showPlanModal = false" class="flex-1 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium">취소</button>
            <button @click="savePlan" class="flex-1 py-2 bg-[#7C3AED] text-white rounded-lg text-sm font-medium hover:bg-[#6D28D9]">저장</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- 기관 배정 모달 -->
    <Teleport to="body">
      <div v-if="showAssignModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="showAssignModal = false">
        <div class="bg-white rounded-2xl w-full max-w-md p-6">
          <h2 class="text-lg font-bold mb-4">기관 플랜 배정</h2>
          <div class="space-y-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">기관</label>
              <select v-model="assignForm.institution_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option :value="null" disabled>기관 선택</option>
                <option v-for="inst in institutions" :key="inst.institution_id" :value="inst.institution_id">
                  {{ inst.name }}
                  <template v-if="getInstitutionPlan(inst.institution_id)"> (현재: {{ getInstitutionPlan(inst.institution_id)?.plan?.name }})</template>
                </option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">플랜</label>
              <select v-model="assignForm.plan_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option :value="null" disabled>플랜 선택</option>
                <option v-for="plan in plans" :key="plan.plan_id" :value="plan.plan_id">
                  {{ plan.name }} ({{ formatPrice(plan.price) }})
                </option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">만료일 (선택)</label>
              <input v-model="assignForm.expires_at" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
              <p class="text-xs text-gray-400 mt-1">비워두면 무제한</p>
            </div>
          </div>
          <div class="flex gap-2 mt-5">
            <button @click="showAssignModal = false" class="flex-1 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium">취소</button>
            <button @click="saveAssign" class="flex-1 py-2 bg-[#7C3AED] text-white rounded-lg text-sm font-medium hover:bg-[#6D28D9]">배정</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
