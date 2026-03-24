<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'

const router = useRouter()
const authStore = useAuthStore()

const profiles = computed(() => authStore.profiles)
const hasParentProfile = computed(() => profiles.value.some(p => p.user_type === 'PARENT'))

function selectProfile(profileId: number): void {
  authStore.switchProfile(profileId)
  router.push({ name: 'home' })
}

function goToAddChild(): void {
  router.push({ name: 'add-child' })
}

function goToEditChild(profileId: number): void {
  router.push({ name: 'edit-child-profile', params: { id: profileId } })
}

function getInitial(profile: { name: string; decrypted_name?: string }): string {
  const name = profile.decrypted_name || profile.name
  return name.charAt(0)
}

function getProfileColor(index: number): string {
  const colors = ['bg-[#4CAF50]', 'bg-[#2196F3]', 'bg-[#FF9800]', 'bg-[#9C27B0]', 'bg-[#F44336]']
  const color = colors[index % colors.length]
  return color ?? 'bg-[#4CAF50]'
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-b from-[#E8F5E9] to-white flex flex-col items-center justify-center px-5">
    <!-- Title -->
    <div class="text-center mb-10">
      <h1 class="text-[22px] font-bold text-[#333] mb-2">프로필을 선택하세요</h1>
      <p class="text-[14px] text-[#888]">사용할 프로필을 선택해주세요</p>
    </div>

    <!-- Profile Grid -->
    <div class="flex flex-wrap justify-center gap-6 mb-10">
      <div
        v-for="(profile, index) in profiles"
        :key="profile.profile_id"
        class="flex flex-col items-center gap-3 relative"
      >
        <button
          class="flex flex-col items-center gap-3 group"
          @click="selectProfile(profile.profile_id)"
        >
          <!-- Avatar -->
          <div
            class="w-20 h-20 rounded-full flex items-center justify-center transition-transform group-hover:scale-110 group-active:scale-95 shadow-lg"
            :class="getProfileColor(index)"
          >
            <span class="text-[28px] font-bold text-white">{{ getInitial(profile) }}</span>
          </div>
          <!-- Name -->
          <span class="text-[14px] font-semibold text-[#333]">{{ profile.decrypted_name || profile.name }}</span>
          <!-- Type Badge -->
          <span
            class="text-[11px] px-2.5 py-0.5 rounded-full font-medium"
            :class="profile.user_type === 'PARENT'
              ? 'bg-[#E3F2FD] text-[#1976D2]'
              : 'bg-[#E8F5E9] text-[#4CAF50]'"
          >
            {{ profile.user_type === 'PARENT' ? '학부모' : '학습자' }}
          </span>
        </button>
        <!-- Edit Button (자녀 프로필만) -->
        <button
          v-if="profile.parent_profile_id && hasParentProfile"
          class="absolute top-0 right-0 w-7 h-7 rounded-full bg-white shadow-md flex items-center justify-center hover:bg-[#F5F5F5] transition-colors"
          @click.stop="goToEditChild(profile.profile_id)"
        >
          <svg class="w-3.5 h-3.5 text-[#888]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
          </svg>
        </button>
      </div>

      <!-- Add Child Button (학부모가 있을 때만) -->
      <button
        v-if="hasParentProfile"
        class="flex flex-col items-center gap-3 group"
        @click="goToAddChild"
      >
        <div class="w-20 h-20 rounded-full flex items-center justify-center bg-[#F5F5F5] border-2 border-dashed border-[#CCC] transition-transform group-hover:scale-110 group-active:scale-95">
          <svg class="w-8 h-8 text-[#999]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19" />
            <line x1="5" y1="12" x2="19" y2="12" />
          </svg>
        </div>
        <span class="text-[14px] font-semibold text-[#999]">자녀 추가</span>
        <span class="text-[11px] px-2.5 py-0.5 rounded-full font-medium bg-transparent text-transparent">-</span>
      </button>
    </div>
  </div>
</template>
