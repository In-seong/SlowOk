<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAdminAuthStore } from '@admin/stores/adminAuthStore'

const router = useRouter()
const authStore = useAdminAuthStore()

const activeSection = ref<number | null>(null)

function toggle(idx: number) {
  activeSection.value = activeSection.value === idx ? null : idx
}

const sections = [
  {
    title: '홈 화면',
    content: '바로가기 그리드를 통해 자주 사용하는 기능에 빠르게 접근할 수 있습니다. "편집" 버튼으로 바로가기를 추가/제거할 수 있습니다.',
  },
  {
    title: '학습자 관리',
    content: '학습자 목록에서 사용자를 검색하고, 상세 페이지에서 기본정보/할당/진단/학습/챌린지/메모를 확인할 수 있습니다.',
  },
  {
    title: '진행 현황',
    content: '학습자별 챌린지 진행 상태를 한눈에 확인할 수 있습니다. 아코디언을 펼쳐 개별 학습자의 상세 현황을 볼 수 있습니다.',
  },
  {
    title: '콘텐츠 관리',
    content: '카테고리, 챌린지, 진단검사를 관리할 수 있습니다. 더보기 메뉴에서 각 관리 화면으로 이동하세요.',
  },
  {
    title: 'AI 콘텐츠 생성',
    content: 'AI를 활용하여 챌린지 문항이나 진단검사를 자동으로 생성할 수 있습니다. 프롬프트를 입력하고 생성 결과를 확인 후 저장하세요.',
  },
  {
    title: '콘텐츠 할당',
    content: '3단계 위저드를 통해 콘텐츠를 학습자에게 할당합니다. 콘텐츠 유형 선택 → 콘텐츠 선택 → 학습자 선택 순서로 진행됩니다.',
  },
  {
    title: '푸시 알림',
    content: '학습자에게 푸시 알림을 발송할 수 있습니다. 전체 발송 또는 특정 사용자를 선택하여 발송할 수 있습니다.',
  },
]

const masterSections = [
  {
    title: '기관 관리',
    content: '기관을 추가/수정/삭제할 수 있습니다. 초대코드를 생성하여 학부모 가입에 사용할 수 있습니다.',
  },
  {
    title: '관리자 관리',
    content: '관리자 계정을 생성하고 권한을 설정할 수 있습니다.',
  },
  {
    title: '플랜/기능 관리',
    content: '기능을 정의하고 플랜을 구성하여 기관별로 할당할 수 있습니다.',
  },
]
</script>

<template>
  <div class="min-h-screen bg-[#FAFAFA]">
    <header class="sticky top-0 z-40 bg-white border-b border-[#E8E8E8] h-[56px] flex items-center px-4">
      <button @click="router.back()" class="w-10 h-10 flex items-center justify-center">
        <svg class="w-5 h-5 text-[#333]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <h1 class="flex-1 text-center text-[16px] font-bold text-[#333]">사용 가이드</h1>
      <div class="w-10" />
    </header>

    <div class="px-4 py-5 space-y-3">
      <p class="text-[13px] text-[#888] mb-2">각 항목을 탭하여 상세 안내를 확인하세요.</p>

      <div
        v-for="(s, idx) in sections"
        :key="idx"
        class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] overflow-hidden"
      >
        <button
          class="w-full px-4 py-3.5 flex items-center justify-between text-left"
          @click="toggle(idx)"
        >
          <span class="text-[15px] font-semibold text-[#333]">{{ s.title }}</span>
          <svg
            class="w-4 h-4 text-[#888] transition-transform"
            :class="activeSection === idx ? 'rotate-180' : ''"
            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
          ><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <div v-if="activeSection === idx" class="px-4 pb-4">
          <p class="text-[13px] text-[#555] leading-relaxed">{{ s.content }}</p>
        </div>
      </div>

      <template v-if="authStore.isMaster">
        <p class="text-[13px] font-semibold text-[#4CAF50] mt-6 mb-2">MASTER 전용</p>
        <div
          v-for="(s, idx) in masterSections"
          :key="'m' + idx"
          class="bg-white rounded-[16px] shadow-sm border border-[#E8E8E8] overflow-hidden"
        >
          <button
            class="w-full px-4 py-3.5 flex items-center justify-between text-left"
            @click="toggle(100 + idx)"
          >
            <span class="text-[15px] font-semibold text-[#333]">{{ s.title }}</span>
            <svg
              class="w-4 h-4 text-[#888] transition-transform"
              :class="activeSection === 100 + idx ? 'rotate-180' : ''"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            ><path d="M6 9l6 6 6-6"/></svg>
          </button>
          <div v-if="activeSection === 100 + idx" class="px-4 pb-4">
            <p class="text-[13px] text-[#555] leading-relaxed">{{ s.content }}</p>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
