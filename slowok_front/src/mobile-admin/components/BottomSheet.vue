<script setup lang="ts">
import { watch } from 'vue'

interface Props {
  modelValue: boolean
  title?: string
  maxHeight?: string
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  maxHeight: '80vh',
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

function close() {
  emit('update:modelValue', false)
}

// body 스크롤 잠금
watch(() => props.modelValue, (open) => {
  if (open) {
    document.body.style.overflow = 'hidden'
  } else {
    document.body.style.overflow = ''
  }
})
</script>

<template>
  <Teleport to="body">
    <Transition name="overlay">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-[100] bg-black/40"
        @click="close"
      />
    </Transition>
    <Transition name="sheet">
      <div
        v-if="modelValue"
        class="fixed bottom-0 left-0 right-0 z-[101] bg-white rounded-t-[20px]"
        :style="{ maxHeight: maxHeight }"
      >
        <div class="flex justify-center pt-3 pb-1">
          <div class="w-[40px] h-[6px] rounded-full bg-[#DDD]" />
        </div>
        <div v-if="title" class="flex items-center justify-between px-5 pb-3">
          <h3 class="text-[17px] font-bold text-[#333]">{{ title }}</h3>
          <button class="w-[44px] h-[44px] flex items-center justify-center -mr-2" @click="close">
            <svg class="w-5 h-5 text-[#888]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        </div>
        <div class="overflow-y-auto px-5 pb-[max(20px,env(safe-area-inset-bottom))]" :style="{ maxHeight: `calc(${maxHeight} - 80px)` }">
          <slot />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
