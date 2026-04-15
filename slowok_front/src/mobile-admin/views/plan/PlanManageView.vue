<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { featureApi, planApi, institutionPlanApi } from '@shared/api/featureApi'
import { adminApi } from '@shared/api/adminApi'
import type { Feature, Plan, InstitutionPlanData, Institution } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'
import BottomSheet from '../../components/BottomSheet.vue'

const router = useRouter()
const toast = useToastStore()

const activeTab = ref<'features' | 'plans' | 'assign'>('plans')

const features = ref<Feature[]>([])
const plans = ref<Plan[]>([])
const institutionPlans = ref<InstitutionPlanData[]>([])
const institutions = ref<Institution[]>([])
const loading = ref(true)

// 플랜 폼
const showPlanForm = ref(false)
const editingPlan = ref<Plan | null>(null)
const planForm = ref({ name: '', description: '', price: 0, sort_order: 0, feature_ids: [] as number[] })
const planSaving = ref(false)

// 할당 폼
const showAssignForm = ref(false)
const assignForm = ref({ institution_id: null as number | null, plan_id: null as number | null, expires_at: '' })
const assignSaving = ref(false)

const featuresByCategory = computed(() => {
  const map: Record<string, Feature[]> = {}
  for (const f of features.value) {
    if (!map[f.category]) map[f.category] = []
    map[f.category]!.push(f)
  }
  return map
})

async function fetchAll() {
  loading.value = true
  try {
    const [fRes, pRes, ipRes, instRes] = await Promise.all([
      featureApi.list(),
      planApi.list(),
      institutionPlanApi.list(),
      adminApi.getInstitutions(),
    ])
    if (fRes.data.success) features.value = fRes.data.data
    if (pRes.data.success) plans.value = pRes.data.data
    if (ipRes.data.success) institutionPlans.value = ipRes.data.data
    if (instRes.data.success) institutions.value = instRes.data.data
  } catch { toast.error('데이터를 불러올 수 없습니다.') }
  finally { loading.value = false }
}

onMounted(fetchAll)

// 플랜 CRUD
function openPlanCreate() {
  editingPlan.value = null
  planForm.value = { name: '', description: '', price: 0, sort_order: 0, feature_ids: [] }
  showPlanForm.value = true
}

function openPlanEdit(plan: Plan) {
  editingPlan.value = plan
  planForm.value = {
    name: plan.name,
    description: plan.description || '',
    price: plan.price || 0,
    sort_order: plan.sort_order || 0,
    feature_ids: plan.features?.map(f => f.feature_id) || [],
  }
  showPlanForm.value = true
}

function toggleFeatureId(id: number) {
  const idx = planForm.value.feature_ids.indexOf(id)
  if (idx >= 0) planForm.value.feature_ids.splice(idx, 1)
  else planForm.value.feature_ids.push(id)
}

async function savePlan() {
  if (!planForm.value.name.trim()) { toast.error('이름을 입력해주세요.'); return }
  planSaving.value = true
  try {
    if (editingPlan.value) {
      await planApi.update(editingPlan.value.plan_id, planForm.value)
    } else {
      await planApi.create(planForm.value)
    }
    showPlanForm.value = false
    const res = await planApi.list()
    if (res.data.success) plans.value = res.data.data
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '저장에 실패했습니다.')
  } finally {
    planSaving.value = false
  }
}

async function deletePlan(plan: Plan) {
  if (!confirm(`"${plan.name}" 플랜을 삭제하시겠습니까?`)) return
  try {
    await planApi.destroy(plan.plan_id)
    plans.value = plans.value.filter(p => p.plan_id !== plan.plan_id)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '삭제에 실패했습니다.')
  }
}

// 할당
function openAssign() {
  assignForm.value = { institution_id: null, plan_id: null, expires_at: '' }
  showAssignForm.value = true
}

async function saveAssign() {
  if (!assignForm.value.institution_id || !assignForm.value.plan_id) {
    toast.error('기관과 플랜을 선택해주세요.'); return
  }
  assignSaving.value = true
  try {
    await institutionPlanApi.assign({
      institution_id: assignForm.value.institution_id,
      plan_id: assignForm.value.plan_id,
      expires_at: assignForm.value.expires_at || undefined,
    })
    showAssignForm.value = false
    const res = await institutionPlanApi.list()
    if (res.data.success) institutionPlans.value = res.data.data
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '할당에 실패했습니다.')
  } finally {
    assignSaving.value = false
  }
}

async function removeAssign(id: number) {
  if (!confirm('할당을 해제하시겠습니까?')) return
  try {
    await institutionPlanApi.remove(id)
    institutionPlans.value = institutionPlans.value.filter(ip => ip.institution_plan_id !== id)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    toast.error(err.response?.data?.message || '해제에 실패했습니다.')
  }
}

function getInstName(id: number): string {
  return institutions.value.find(i => i.institution_id === id)?.name || '-'
}
function getPlanName(id: number): string {
  return plans.value.find(p => p.plan_id === id)?.name || '-'
}
</script>

<template>
  <div class="min-h-screen bg-[#FAFAFA]">
    <header class="sticky top-0 z-40 bg-white border-b border-[#E8E8E8] h-[56px] flex items-center px-4">
      <button @click="router.back()" class="w-10 h-10 flex items-center justify-center">
        <svg class="w-5 h-5 text-[#333]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <h1 class="flex-1 text-center text-[16px] font-bold text-[#333]">플랜/기능 관리</h1>
      <div class="w-10" />
    </header>

    <!-- 탭 -->
    <div class="sticky top-[56px] z-30 bg-white border-b border-[#E8E8E8] px-4 flex gap-1 py-2">
      <button @click="activeTab = 'features'" :class="activeTab === 'features' ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666]'" class="flex-1 py-2 rounded-[10px] text-[13px] font-medium">기능</button>
      <button @click="activeTab = 'plans'" :class="activeTab === 'plans' ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666]'" class="flex-1 py-2 rounded-[10px] text-[13px] font-medium">플랜</button>
      <button @click="activeTab = 'assign'; institutionPlans.length === 0 && fetchAll()" :class="activeTab === 'assign' ? 'bg-[#4CAF50] text-white' : 'bg-[#F0F0F0] text-[#666]'" class="flex-1 py-2 rounded-[10px] text-[13px] font-medium">할당</button>
    </div>

    <div v-if="loading" class="flex justify-center py-20">
      <div class="w-8 h-8 border-3 border-[#4CAF50] border-t-transparent rounded-full animate-spin" />
    </div>

    <!-- 기능 목록 -->
    <div v-else-if="activeTab === 'features'" class="px-4 py-4 space-y-4">
      <div v-for="(feats, cat) in featuresByCategory" :key="cat">
        <p class="text-[13px] font-semibold text-[#888] mb-2">{{ cat }}</p>
        <div class="space-y-2">
          <div v-for="f in feats" :key="f.feature_id" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4 flex items-center justify-between">
            <div>
              <p class="text-[14px] font-medium text-[#333]">{{ f.name }}</p>
              <p class="text-[11px] text-[#888] font-mono">{{ f.feature_key }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 플랜 목록 -->
    <div v-else-if="activeTab === 'plans'" class="px-4 py-4 space-y-3">
      <div class="flex justify-end mb-2">
        <button @click="openPlanCreate" class="text-[13px] font-medium text-[#4CAF50] active:opacity-70">+ 플랜 추가</button>
      </div>
      <div v-for="plan in plans" :key="plan.plan_id" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4">
        <p class="text-[15px] font-semibold text-[#333]">{{ plan.name }}</p>
        <p v-if="plan.description" class="text-[12px] text-[#888] mt-0.5">{{ plan.description }}</p>
        <div v-if="plan.features && plan.features.length > 0" class="flex flex-wrap gap-1 mt-2">
          <span v-for="f in plan.features" :key="f.feature_id" class="text-[10px] px-2 py-0.5 bg-[#F0F0F0] text-[#555] rounded-[6px]">{{ f.name }}</span>
        </div>
        <div class="flex gap-2 mt-3">
          <button @click="openPlanEdit(plan)" class="text-[12px] font-medium px-3 py-1.5 bg-[#E8F5E9] text-[#4CAF50] rounded-[8px] active:opacity-70">수정</button>
          <button @click="deletePlan(plan)" class="text-[12px] font-medium px-3 py-1.5 bg-[#FFF0F0] text-[#FF4444] rounded-[8px] active:opacity-70">삭제</button>
        </div>
      </div>
    </div>

    <!-- 할당 -->
    <div v-else class="px-4 py-4 space-y-3">
      <div class="flex justify-end mb-2">
        <button @click="openAssign" class="text-[13px] font-medium text-[#4CAF50] active:opacity-70">+ 할당</button>
      </div>
      <div v-for="ip in institutionPlans" :key="ip.institution_plan_id" class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] p-4 flex items-center justify-between">
        <div>
          <p class="text-[14px] font-medium text-[#333]">{{ ip.institution?.name || getInstName(ip.institution_id) }}</p>
          <p class="text-[12px] text-[#888]">{{ ip.plan?.name || getPlanName(ip.plan_id) }}</p>
        </div>
        <button @click="removeAssign(ip.institution_plan_id)" class="text-[12px] text-[#FF4444] font-medium active:opacity-70">해제</button>
      </div>
    </div>

    <!-- 플랜 생성/수정 -->
    <BottomSheet v-model="showPlanForm" :title="editingPlan ? '플랜 수정' : '플랜 추가'" max-height="100vh">
      <div class="space-y-4 pb-4">
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">이름 <span class="text-[#FF4444]">*</span></label>
          <input v-model="planForm.name" type="text" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">설명</label>
          <textarea v-model="planForm.description" rows="2" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50] resize-none" />
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-2">포함 기능</label>
          <div class="space-y-1 max-h-[200px] overflow-y-auto">
            <div
              v-for="f in features"
              :key="f.feature_id"
              @click="toggleFeatureId(f.feature_id)"
              class="flex items-center gap-2 px-3 py-2 rounded-[8px] active:bg-[#F0F0F0]"
              :class="planForm.feature_ids.includes(f.feature_id) ? 'bg-[#E8F5E9]' : ''"
            >
              <input type="checkbox" :checked="planForm.feature_ids.includes(f.feature_id)" class="w-4 h-4 accent-[#4CAF50] pointer-events-none" />
              <span class="text-[13px] text-[#333]">{{ f.name }}</span>
            </div>
          </div>
        </div>
        <button @click="savePlan" :disabled="planSaving" class="w-full py-3.5 bg-[#4CAF50] text-white rounded-[12px] text-[15px] font-semibold disabled:opacity-50 active:bg-[#388E3C]">
          {{ planSaving ? '저장 중...' : (editingPlan ? '수정' : '추가') }}
        </button>
      </div>
    </BottomSheet>

    <!-- 할당 폼 -->
    <BottomSheet v-model="showAssignForm" title="기관 플랜 할당">
      <div class="space-y-4 pb-4">
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">기관</label>
          <select v-model="assignForm.institution_id" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] bg-white focus:outline-none focus:border-[#4CAF50]">
            <option :value="null">선택</option>
            <option v-for="inst in institutions" :key="inst.institution_id" :value="inst.institution_id">{{ inst.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">플랜</label>
          <select v-model="assignForm.plan_id" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] bg-white focus:outline-none focus:border-[#4CAF50]">
            <option :value="null">선택</option>
            <option v-for="plan in plans" :key="plan.plan_id" :value="plan.plan_id">{{ plan.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-[13px] font-medium text-[#555] mb-1">만료일 (선택)</label>
          <input v-model="assignForm.expires_at" type="date" class="w-full px-4 py-3.5 border border-[#E8E8E8] rounded-[12px] text-[15px] focus:outline-none focus:border-[#4CAF50]" />
        </div>
        <button @click="saveAssign" :disabled="assignSaving" class="w-full py-3.5 bg-[#4CAF50] text-white rounded-[12px] text-[15px] font-semibold disabled:opacity-50 active:bg-[#388E3C]">
          {{ assignSaving ? '할당 중...' : '할당하기' }}
        </button>
      </div>
    </BottomSheet>
  </div>
</template>
