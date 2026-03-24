<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { recommendationRuleApi } from '@shared/api/recommendationRuleApi'
import { adminApi } from '@shared/api/adminApi'
import { contentPackageApi } from '@shared/api/contentPackageApi'
import type { RecommendationRule, LearningCategory, ContentPackage } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'

const toast = useToastStore()

const rules = ref<RecommendationRule[]>([])
const categories = ref<LearningCategory[]>([])
const packages = ref<ContentPackage[]>([])
const loading = ref(true)

const showModal = ref(false)
const editingRule = ref<RecommendationRule | null>(null)
const saving = ref(false)
const deleteConfirmId = ref<number | null>(null)

// 폼 데이터
const formCategoryId = ref<number | null>(null)
const formScoreMin = ref(0)
const formScoreMax = ref(100)
const formPackageId = ref<number | null>(null)

async function fetchData() {
  loading.value = true
  try {
    const [rulesRes, categoriesRes, packagesRes] = await Promise.all([
      recommendationRuleApi.getRules(),
      adminApi.getCategories(),
      contentPackageApi.getPackages(),
    ])
    rules.value = rulesRes.data.data
    categories.value = categoriesRes.data.data
    packages.value = packagesRes.data.data.filter((p: ContentPackage) => p.is_active)
  } catch (e: any) {
    toast.error(e.response?.data?.message || '데이터를 불러오지 못했습니다.')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingRule.value = null
  formCategoryId.value = null
  formScoreMin.value = 0
  formScoreMax.value = 100
  formPackageId.value = null
  showModal.value = true
}

function openEdit(rule: RecommendationRule) {
  editingRule.value = rule
  formCategoryId.value = rule.category_id
  formScoreMin.value = rule.score_min
  formScoreMax.value = rule.score_max
  formPackageId.value = rule.package_id
  showModal.value = true
}

async function handleSave() {
  if (!formCategoryId.value) {
    toast.warning('카테고리를 선택해주세요.')
    return
  }
  if (!formPackageId.value) {
    toast.warning('콘텐츠 패키지를 선택해주세요.')
    return
  }
  if (formScoreMin.value > formScoreMax.value) {
    toast.warning('최소 점수가 최대 점수보다 클 수 없습니다.')
    return
  }

  saving.value = true
  try {
    const payload = {
      category_id: formCategoryId.value,
      score_min: formScoreMin.value,
      score_max: formScoreMax.value,
      package_id: formPackageId.value,
    }

    if (editingRule.value) {
      await recommendationRuleApi.updateRule(editingRule.value.rule_id, payload)
      toast.success('추천 규칙이 수정되었습니다.')
    } else {
      await recommendationRuleApi.createRule(payload)
      toast.success('추천 규칙이 생성되었습니다.')
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
    await recommendationRuleApi.deleteRule(id)
    deleteConfirmId.value = null
    toast.success('추천 규칙이 삭제되었습니다.')
    await fetchData()
  } catch (e: any) {
    toast.error(e.response?.data?.message || '삭제에 실패했습니다.')
  }
}

function getCategoryName(id: number): string {
  return categories.value.find((c) => c.category_id === id)?.name ?? '-'
}

function getPackageName(id: number): string {
  return packages.value.find((p) => p.package_id === id)?.name ?? '-'
}

function scoreRangeLabel(min: number, max: number): string {
  return `${min}점 ~ ${max}점`
}

onMounted(fetchData)
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-xl font-bold text-[#333]">추천 규칙 관리</h2>
        <p class="text-[13px] text-[#888] mt-1">진단 결과 점수에 따라 자동으로 콘텐츠 패키지를 할당합니다.</p>
      </div>
      <button
        @click="openCreate"
        class="px-4 py-2 bg-[#4CAF50] text-white rounded-[10px] text-[14px] font-medium hover:bg-[#43A047] transition-colors"
      >
        규칙 추가
      </button>
    </div>

    <div v-if="loading" class="text-center py-10 text-[#888]">불러오는 중...</div>

    <div v-else-if="rules.length === 0" class="text-center py-10 text-[#888]">
      <p>등록된 추천 규칙이 없습니다.</p>
      <p class="text-[12px] mt-1">규칙을 추가하면 진단 완료 시 자동으로 콘텐츠가 할당됩니다.</p>
    </div>

    <div v-else class="bg-white rounded-[16px] border border-[#E8E8E8] overflow-hidden">
      <table class="w-full text-[14px]">
        <thead>
          <tr class="bg-[#F8F8F8] text-[#666]">
            <th class="text-left px-4 py-3 font-medium">카테고리</th>
            <th class="text-left px-4 py-3 font-medium">점수 범위</th>
            <th class="text-left px-4 py-3 font-medium">할당 패키지</th>
            <th class="text-center px-4 py-3 font-medium">관리</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="rule in rules" :key="rule.rule_id" class="border-t border-[#F0F0F0]">
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 bg-blue-50 text-blue-600 rounded-full text-[12px]">
                {{ rule.category?.name ?? getCategoryName(rule.category_id) }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span class="font-medium text-[#333]">{{ scoreRangeLabel(rule.score_min, rule.score_max) }}</span>
            </td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 bg-green-50 text-green-600 rounded-full text-[12px]">
                {{ rule.package?.name ?? getPackageName(rule.package_id) }}
              </span>
            </td>
            <td class="px-4 py-3 text-center">
              <div class="flex items-center justify-center gap-2">
                <button @click="openEdit(rule)" class="text-blue-500 hover:underline text-[13px]">수정</button>
                <button
                  v-if="deleteConfirmId !== rule.rule_id"
                  @click="deleteConfirmId = rule.rule_id"
                  class="text-red-500 hover:underline text-[13px]"
                >
                  삭제
                </button>
                <template v-else>
                  <button @click="handleDelete(rule.rule_id)" class="text-red-600 font-medium text-[13px]">확인</button>
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
        <div class="bg-white rounded-[16px] w-full max-w-[480px] p-6">
          <h3 class="text-[17px] font-bold text-[#333] mb-5">
            {{ editingRule ? '추천 규칙 수정' : '추천 규칙 추가' }}
          </h3>

          <!-- 카테고리 -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">카테고리 *</label>
            <select
              v-model="formCategoryId"
              class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[10px] px-3 py-2 text-[14px] focus:border-[#4CAF50] focus:outline-none"
            >
              <option :value="null" disabled>카테고리 선택</option>
              <option v-for="cat in categories" :key="cat.category_id" :value="cat.category_id">
                {{ cat.name }}
              </option>
            </select>
          </div>

          <!-- 점수 범위 -->
          <div class="mb-4">
            <label class="block text-[13px] font-medium text-[#555] mb-1">점수 범위 *</label>
            <div class="flex items-center gap-2">
              <input
                v-model.number="formScoreMin"
                type="number"
                min="0"
                max="100"
                class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[10px] px-3 py-2 text-[14px] focus:border-[#4CAF50] focus:outline-none"
                placeholder="최소"
              />
              <span class="text-[#888] shrink-0">~</span>
              <input
                v-model.number="formScoreMax"
                type="number"
                min="0"
                max="100"
                class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[10px] px-3 py-2 text-[14px] focus:border-[#4CAF50] focus:outline-none"
                placeholder="최대"
              />
              <span class="text-[#888] text-[13px] shrink-0">점</span>
            </div>
            <p class="text-[12px] text-[#999] mt-1">진단 점수가 이 범위에 해당하면 아래 패키지가 자동 할당됩니다.</p>
          </div>

          <!-- 패키지 선택 -->
          <div class="mb-5">
            <label class="block text-[13px] font-medium text-[#555] mb-1">콘텐츠 패키지 *</label>
            <select
              v-model="formPackageId"
              class="w-full bg-[#F8F8F8] border border-[#E8E8E8] rounded-[10px] px-3 py-2 text-[14px] focus:border-[#4CAF50] focus:outline-none"
            >
              <option :value="null" disabled>패키지 선택</option>
              <option v-for="pkg in packages" :key="pkg.package_id" :value="pkg.package_id">
                {{ pkg.name }} ({{ pkg.items?.length ?? 0 }}개 콘텐츠)
              </option>
            </select>
          </div>

          <!-- 버튼 -->
          <div class="flex justify-end gap-2">
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
              {{ saving ? '저장 중...' : editingRule ? '수정' : '추가' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
