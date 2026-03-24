<script setup lang="ts">
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div class="min-h-screen bg-[#F5F5F5] flex items-center justify-center px-[20px]">
    <div class="bg-white rounded-[20px] shadow-[0_0_10px_rgba(0,0,0,0.1)] p-[40px] max-w-[480px] w-full text-center">
      <!-- 404 아이콘 -->
      <div class="w-[80px] h-[80px] rounded-full bg-[#F5F5F5] flex items-center justify-center mx-auto mb-[24px]">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-[40px] h-[40px] text-[#BDBDBD]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>

      <h1 class="text-[28px] font-bold text-[#333] mb-[8px]">404</h1>
      <p class="text-[16px] font-semibold text-[#333] mb-[8px]">페이지를 찾을 수 없습니다</p>
      <p class="text-[14px] text-[#888] mb-[32px]">요청하신 페이지가 존재하지 않거나 이동되었습니다.</p>

      <button
        @click="router.push({ name: 'dashboard' })"
        class="px-[32px] py-[12px] bg-[#4CAF50] text-white text-[14px] font-semibold rounded-[12px] hover:bg-[#388E3C] transition-colors"
      >
        대시보드로 이동
      </button>
    </div>
  </div>
</template>
