<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { adminApi } from '@shared/api/adminApi'
import type { AdminPermission } from '@shared/types'
import { useToastStore } from '@shared/stores/toastStore'

const route = useRoute()
const router = useRouter()
const toast = useToastStore()
const adminId = computed(() => Number(route.params.id))

const allPermissions = ref<AdminPermission[]>([])
const grantedIds = ref<Set<number>>(new Set())
const loading = ref(true)
const saving = ref(false)
const adminName = ref('')

const categories = computed(() => {
  const map = new Map<string, AdminPermission[]>()
  for (const p of allPermissions.value) {
    const list = map.get(p.category) || []
    list.push(p)
    map.set(p.category, list)
  }
  return map
})

const categoryLabels: Record<string, string> = {
  system: '시스템',
  user: '사용자',
  content: '콘텐츠',
  general: '일반',
}

function togglePermission(id: number) {
  if (grantedIds.value.has(id)) {
    grantedIds.value.delete(id)
  } else {
    grantedIds.value.add(id)
  }
  grantedIds.value = new Set(grantedIds.value)
}

function toggleAll(perms: AdminPermission[]) {
  const allGranted = perms.every((p) => grantedIds.value.has(p.permission_id))
  for (const p of perms) {
    if (allGranted) {
      grantedIds.value.delete(p.permission_id)
    } else {
      grantedIds.value.add(p.permission_id)
    }
  }
  grantedIds.value = new Set(grantedIds.value)
}

async function fetchData() {
  loading.value = true
  try {
    const [permRes, grantRes, adminsRes] = await Promise.all([
      adminApi.getPermissions(),
      adminApi.getAdminPermissions(adminId.value),
      adminApi.getAdmins(),
    ])
    allPermissions.value = permRes.data.data
    grantedIds.value = new Set(grantRes.data.data.permission_ids)
    const admin = adminsRes.data.data.find((a) => a.account_id === adminId.value)
    adminName.value = admin?.profile?.name ?? admin?.username ?? ''
  } catch (e: any) {
    toast.error(e.response?.data?.message || '데이터를 불러오지 못했습니다.')
    router.push({ name: 'admin-management' })
  } finally {
    loading.value = false
  }
}

async function handleSave() {
  saving.value = true
  try {
    await adminApi.updateAdminPermissions(adminId.value, Array.from(grantedIds.value))
    toast.success('권한이 저장되었습니다.')
    router.push({ name: 'admin-management' })
  } catch (e: any) {
    toast.error(e.response?.data?.message || '저장에 실패했습니다.')
  } finally {
    saving.value = false
  }
}

onMounted(fetchData)
</script>

<template>
  <div class="p-6">
    <div class="flex items-center gap-3 mb-6">
      <button @click="router.push({ name: 'admin-management' })" class="text-[#888] hover:text-[#333]">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
        </svg>
      </button>
      <h2 class="text-xl font-bold text-[#333]">권한 관리 - {{ adminName }}</h2>
    </div>

    <div v-if="loading" class="text-center py-10 text-[#888]">불러오는 중...</div>
    <div v-else>
      <div class="space-y-4">
        <div v-for="[category, perms] in categories" :key="category" class="bg-white rounded-[16px] border border-[#E8E8E8] p-4">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-[15px] font-semibold text-[#333]">{{ categoryLabels[category] || category }}</h3>
            <button
              @click="toggleAll(perms)"
              class="text-[12px] text-[#4CAF50] hover:underline"
            >
              {{ perms.every((p) => grantedIds.has(p.permission_id)) ? '전체 해제' : '전체 선택' }}
            </button>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
            <label
              v-for="perm in perms"
              :key="perm.permission_id"
              class="flex items-center gap-2 px-3 py-2 rounded-[10px] cursor-pointer transition-colors"
              :class="grantedIds.has(perm.permission_id) ? 'bg-[#E8F5E9]' : 'bg-[#F8F8F8] hover:bg-[#F0F0F0]'"
            >
              <input
                type="checkbox"
                :checked="grantedIds.has(perm.permission_id)"
                @change="togglePermission(perm.permission_id)"
                class="w-4 h-4 rounded accent-[#4CAF50]"
              />
              <span class="text-[14px] text-[#333]">{{ perm.permission_name }}</span>
            </label>
          </div>
        </div>
      </div>

      <div class="flex gap-2 mt-6">
        <button
          @click="router.push({ name: 'admin-management' })"
          class="px-6 py-2.5 border border-[#E8E8E8] rounded-[10px] text-[14px] text-[#666] hover:bg-[#F5F5F5] transition-colors"
        >
          취소
        </button>
        <button
          @click="handleSave"
          :disabled="saving"
          class="px-6 py-2.5 bg-[#4CAF50] text-white rounded-[10px] text-[14px] font-medium hover:bg-[#43A047] transition-colors disabled:opacity-50"
        >
          {{ saving ? '저장 중...' : '권한 저장' }}
        </button>
      </div>
    </div>
  </div>
</template>
