<script setup lang="ts">
defineEmits<{
  (e: 'select', type: string): void
}>()

const types = [
  { value: 'multiple_choice', label: '객관식 (4지선다)', icon: '📝', desc: '4개 보기 중 정답 1개 선택' },
  { value: 'matching', label: '매칭 게임', icon: '🔗', desc: '좌측 멘트와 우측 답을 연결' },
  { value: 'image_choice', label: '그림카드 택1', icon: '🖼️', desc: '이미지 보고 보기 중 선택' },
  { value: 'image_text', label: '그림카드 텍스트', icon: '✏️', desc: '이미지 보고 텍스트 입력' },
  // { value: 'image_voice', label: '그림카드 음성', icon: '🎤', desc: '이미지 보고 음성 답변' }, // [미사용] 음성 기능 비활성화
] as const
</script>

<template>
  <div>
    <h3 class="text-[15px] font-bold text-[#333] mb-4">문항 유형 선택</h3>
    <div class="grid grid-cols-1 gap-2">
      <button
        v-for="t in types"
        :key="t.value"
        @click="$emit('select', t.value)"
        class="flex items-center gap-3 p-3.5 rounded-[12px] border-2 border-[#E8E8E8] bg-white hover:border-[#4CAF50] hover:bg-[#F8FFF8] transition-all text-left active:scale-[0.98]"
      >
        <span class="text-[24px] shrink-0">{{ t.icon }}</span>
        <div class="min-w-0">
          <p class="text-[14px] font-semibold text-[#333]">{{ t.label }}</p>
          <p class="text-[12px] text-[#888]">{{ t.desc }}</p>
        </div>
      </button>
    </div>
  </div>
</template>
