import { ref, watch } from 'vue'

export interface Shortcut {
  key: string
  label: string
  icon: string
  color: string
}

const STORAGE_KEY = 'mobileAdminShortcuts'

const DEFAULT_SHORTCUTS: Shortcut[] = [
  { key: 'ai-content', label: 'AI 생성', icon: '🤖', color: '#E3F2FD' },
  { key: 'challenges', label: '챌린지 관리', icon: '🏆', color: '#FFF3E0' },
  { key: 'content-assignments', label: '콘텐츠 할당', icon: '📋', color: '#E8F5E9' },
  { key: 'screening', label: '진단 관리', icon: '🔬', color: '#F3E5F5' },
  { key: 'screening-results', label: '진단 결과', icon: '📊', color: '#FFF8E1' },
  { key: 'users', label: '사용자 관리', icon: '👥', color: '#E0F7FA' },
]

const ALL_MENU_ITEMS: Shortcut[] = [
  ...DEFAULT_SHORTCUTS,
  { key: 'dashboard', label: '대시보드', icon: '📈', color: '#F1F8E9' },
  { key: 'categories', label: '카테고리 관리', icon: '📁', color: '#EFEBE9' },
  { key: 'push', label: '푸시 알림', icon: '🔔', color: '#FBE9E7' },
  { key: 'institutions', label: '기관 관리', icon: '🏢', color: '#E8EAF6' },
  { key: 'admin-management', label: '관리자 관리', icon: '👤', color: '#FCE4EC' },
  { key: 'plan-manage', label: '플랜 관리', icon: '⚙️', color: '#F3E5F5' },
]

function loadShortcuts(): Shortcut[] {
  try {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (saved) {
      const parsed = JSON.parse(saved) as Shortcut[]
      if (Array.isArray(parsed) && parsed.length >= 2) return parsed
    }
  } catch {
    // ignore
  }
  return [...DEFAULT_SHORTCUTS]
}

export function useShortcuts() {
  const shortcuts = ref<Shortcut[]>(loadShortcuts())

  watch(shortcuts, (val) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(val))
  }, { deep: true })

  function addShortcut(item: Shortcut) {
    if (shortcuts.value.length >= 8) return
    if (shortcuts.value.some(s => s.key === item.key)) return
    shortcuts.value.push(item)
  }

  function removeShortcut(key: string) {
    if (shortcuts.value.length <= 2) return
    shortcuts.value = shortcuts.value.filter(s => s.key !== key)
  }

  function resetToDefault() {
    shortcuts.value = [...DEFAULT_SHORTCUTS]
  }

  function getAvailableItems(): Shortcut[] {
    const currentKeys = new Set(shortcuts.value.map(s => s.key))
    return ALL_MENU_ITEMS.filter(item => !currentKeys.has(item.key))
  }

  return {
    shortcuts,
    addShortcut,
    removeShortcut,
    resetToDefault,
    getAvailableItems,
  }
}
