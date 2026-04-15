<script setup lang="ts">
import { ref } from 'vue'
import { useAdminAuthStore } from '@admin/stores/adminAuthStore'
import MobileHeader from './MobileHeader.vue'
import MobileBottomNav from './MobileBottomNav.vue'
import BottomSheet from './BottomSheet.vue'

const authStore = useAdminAuthStore()
const showInstitutionSheet = ref(false)

function selectInstitution(id: number | null) {
  authStore.selectInstitution(id)
  showInstitutionSheet.value = false
}
</script>

<template>
  <div class="min-h-screen flex flex-col">
    <MobileHeader
      :show-institution-selector="authStore.isMaster"
      @institution-click="showInstitutionSheet = true"
    />
    <main class="flex-1 pb-[76px] overflow-y-auto">
      <router-view />
    </main>
    <MobileBottomNav />

    <!-- MASTER 기관 선택 바텀시트 -->
    <BottomSheet v-model="showInstitutionSheet" title="기관 선택">
      <div class="space-y-1">
        <button
          class="w-full text-left px-4 py-3 rounded-[12px] text-[15px] active:bg-[#F0F0F0] transition-colors"
          :class="authStore.selectedInstitutionId === null ? 'bg-[#E8F5E9] text-[#4CAF50] font-semibold' : 'text-[#333]'"
          @click="selectInstitution(null)"
        >
          전체 기관
        </button>
        <button
          v-for="inst in authStore.institutions"
          :key="inst.institution_id"
          class="w-full text-left px-4 py-3 rounded-[12px] text-[15px] active:bg-[#F0F0F0] transition-colors"
          :class="authStore.selectedInstitutionId === inst.institution_id ? 'bg-[#E8F5E9] text-[#4CAF50] font-semibold' : 'text-[#333]'"
          @click="selectInstitution(inst.institution_id)"
        >
          {{ inst.name }}
        </button>
      </div>
    </BottomSheet>
  </div>
</template>
