import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface ToastMessage {
  id: number
  text: string
  type: 'success' | 'error' | 'warning'
}

let nextId = 0

export const useToastStore = defineStore('toast', () => {
  const messages = ref<ToastMessage[]>([])

  function addMessage(text: string, type: ToastMessage['type']) {
    const id = nextId++
    messages.value.push({ id, text, type })
    setTimeout(() => {
      remove(id)
    }, 3000)
  }

  function remove(id: number) {
    messages.value = messages.value.filter((m) => m.id !== id)
  }

  function success(text: string) {
    addMessage(text, 'success')
  }

  function error(text: string) {
    addMessage(text, 'error')
  }

  function warning(text: string) {
    addMessage(text, 'warning')
  }

  return { messages, success, error, warning, remove }
})
