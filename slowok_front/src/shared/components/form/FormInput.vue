<script setup lang="ts">
interface Props {
  modelValue: string
  label?: string
  placeholder?: string
  type?: string
  readonly?: boolean
}

withDefaults(defineProps<Props>(), {
  label: '',
  placeholder: '',
  type: 'text',
  readonly: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

function onInput(event: Event): void {
  const target = event.target as HTMLInputElement
  emit('update:modelValue', target.value)
}
</script>

<template>
  <div>
    <label v-if="label" class="block text-[13px] font-medium text-[#555] mb-1.5">
      {{ label }}
    </label>
    <input
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :readonly="readonly"
      class="bg-[#F8F8F8] border border-[#E8E8E8] rounded-[12px] px-4 py-3.5 text-[15px] w-full focus:border-[#4CAF50] focus:outline-none transition-colors placeholder-[#B0B0B0]"
      :class="readonly ? 'text-[#999] cursor-default' : 'text-[#333]'"
      @input="onInput"
    />
  </div>
</template>
