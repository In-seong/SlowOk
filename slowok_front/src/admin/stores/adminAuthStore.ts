import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@shared/api'
import type { Account, Institution } from '@shared/types'

export const useAdminAuthStore = defineStore('adminAuth', () => {
  const user = ref<Account | null>(null)
  const token = ref<string | null>(localStorage.getItem('adminToken'))
  const loading = ref(false)
  const error = ref<string | null>(null)
  const isLoggedIn = ref(localStorage.getItem('adminIsLoggedIn') === 'true')
  const permissions = ref<string[]>([])

  // 기관 선택 (MASTER용)
  const selectedInstitutionId = ref<number | null>(
    localStorage.getItem('selectedInstitutionId')
      ? Number(localStorage.getItem('selectedInstitutionId'))
      : null,
  )
  const institutions = ref<Institution[]>([])

  const isMaster = computed(() => user.value?.role === 'MASTER')
  const isTest = computed(() => user.value?.role === 'TEST')

  // 현재 기관 정보
  const currentInstitution = computed<Institution | null>(() => {
    if (isMaster.value) {
      if (selectedInstitutionId.value === null) return null
      return institutions.value.find((i) => i.institution_id === selectedInstitutionId.value) ?? null
    }
    return user.value?.institution ?? null
  })

  // 현재 기관명 (표시용)
  const currentInstitutionName = computed(() => {
    if (isMaster.value && selectedInstitutionId.value === null) return '전체 기관'
    return currentInstitution.value?.name ?? '소속 없음'
  })

  function hasPermission(key: string): boolean {
    if (user.value?.role === 'MASTER') return true
    return permissions.value.includes(key)
  }

  function selectInstitution(id: number | null) {
    selectedInstitutionId.value = id
    if (id === null) {
      localStorage.removeItem('selectedInstitutionId')
    } else {
      localStorage.setItem('selectedInstitutionId', String(id))
    }
  }

  async function fetchInstitutions(): Promise<void> {
    if (!isMaster.value) return
    try {
      const response = await api.get('/admin/institutions')
      if (response.data.success) {
        institutions.value = response.data.data
      }
    } catch {
      // ignore
    }
  }

  async function login(username: string, password: string): Promise<boolean> {
    loading.value = true
    error.value = null
    try {
      const response = await api.post('/auth/login', { username, password, role: 'ADMIN' })
      if (response.data.success) {
        user.value = response.data.data.account
        token.value = response.data.data.token
        isLoggedIn.value = true
        permissions.value = response.data.data.account.permissions || []
        localStorage.setItem('adminToken', response.data.data.token)
        localStorage.setItem('adminIsLoggedIn', 'true')

        // ADMIN은 본인 기관 고정, MASTER는 기존 선택값 유지
        if (!response.data.data.account.institution_id && response.data.data.account.role !== 'MASTER') {
          localStorage.removeItem('selectedInstitutionId')
          selectedInstitutionId.value = null
        }

        return true
      }
      error.value = response.data.message || '로그인에 실패했습니다.'
      return false
    } catch (e: any) {
      error.value = e.response?.data?.message || '로그인에 실패했습니다.'
      return false
    } finally {
      loading.value = false
    }
  }

  async function logout(): Promise<void> {
    try {
      await api.post('/auth/logout')
    } catch {
      // Ignore
    } finally {
      user.value = null
      token.value = null
      isLoggedIn.value = false
      permissions.value = []
      institutions.value = []
      selectedInstitutionId.value = null
      localStorage.removeItem('adminToken')
      localStorage.removeItem('adminIsLoggedIn')
      localStorage.removeItem('selectedInstitutionId')
    }
  }

  async function fetchUser(): Promise<void> {
    if (!isLoggedIn.value) return
    loading.value = true
    try {
      const response = await api.get('/auth/me')
      if (response.data.success) {
        user.value = response.data.data
        permissions.value = response.data.data.permissions || []

        // MASTER인 경우 기관 목록 로드
        if (response.data.data.role === 'MASTER') {
          await fetchInstitutions()
        }
      }
    } catch { await logout() }
    finally { loading.value = false }
  }

  return {
    user, token, loading, error, isLoggedIn, permissions,
    isMaster, isTest, hasPermission,
    selectedInstitutionId, institutions, currentInstitution, currentInstitutionName,
    selectInstitution, fetchInstitutions,
    login, logout, fetchUser,
  }
})
