import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@shared/api'
import type { Account, UserProfile } from '@shared/types'
import { initFcmBridge, removeFcmToken } from '@shared/utils/fcmBridge'

export const useAuthStore = defineStore('userAuth', () => {
  const user = ref<Account | null>(null)
  const token = ref<string | null>(localStorage.getItem('userToken'))
  const loading = ref(false)
  const error = ref<string | null>(null)
  const isLoggedIn = ref(localStorage.getItem('userIsLoggedIn') === 'true')

  const profiles = ref<UserProfile[]>([])
  const activeProfile = ref<UserProfile | null>(null)

  const isParent = computed(() => activeProfile.value?.user_type === 'PARENT')
  const isLearner = computed(() => activeProfile.value?.user_type === 'LEARNER')
  const needsProfileSelect = computed(() => profiles.value.length > 1 && !activeProfile.value)

  function setProfiles(profileList: UserProfile[]): void {
    profiles.value = profileList
    // 저장된 activeProfileId 복원
    const savedId = localStorage.getItem('activeProfileId')
    if (savedId) {
      const found = profileList.find(p => p.profile_id === Number(savedId))
      if (found) {
        activeProfile.value = found
        return
      }
    }
    // 프로필이 1개면 자동 선택
    if (profileList.length === 1) {
      const single = profileList[0]
      if (single) {
        activeProfile.value = single
        localStorage.setItem('activeProfileId', String(single.profile_id))
      }
    }
  }

  function switchProfile(profileId: number): void {
    const found = profiles.value.find(p => p.profile_id === profileId)
    if (found) {
      activeProfile.value = found
      localStorage.setItem('activeProfileId', String(profileId))
    }
  }

  async function login(username: string, password: string): Promise<boolean> {
    loading.value = true
    error.value = null
    try {
      const response = await api.post('/auth/login', { username, password, role: 'USER' })
      if (response.data.success) {
        user.value = response.data.data.account
        token.value = response.data.data.token
        isLoggedIn.value = true
        localStorage.setItem('userToken', response.data.data.token)
        localStorage.setItem('userIsLoggedIn', 'true')

        const accountData = response.data.data.account as Account
        if (accountData.profiles) {
          setProfiles(accountData.profiles)
        }
        initFcmBridge()
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

  async function register(data: {
    username: string
    password: string
    password_confirmation: string
    name: string
    phone?: string
    email?: string
    user_type?: string
    invite_code?: string
  }): Promise<boolean> {
    loading.value = true
    error.value = null
    try {
      const response = await api.post('/auth/register', data)
      if (response.data.success) {
        user.value = response.data.data.account
        token.value = response.data.data.token
        isLoggedIn.value = true
        localStorage.setItem('userToken', response.data.data.token)
        localStorage.setItem('userIsLoggedIn', 'true')

        const accountData = response.data.data.account as Account
        if (accountData.profiles) {
          setProfiles(accountData.profiles)
        }
        return true
      }
      error.value = response.data.message || '회원가입에 실패했습니다.'
      return false
    } catch (e: any) {
      error.value = e.response?.data?.message || '회원가입에 실패했습니다.'
      return false
    } finally {
      loading.value = false
    }
  }

  async function logout(): Promise<void> {
    try {
      await removeFcmToken()
      await api.post('/auth/logout')
    } catch {
      // Ignore logout errors
    } finally {
      user.value = null
      token.value = null
      isLoggedIn.value = false
      profiles.value = []
      activeProfile.value = null
      localStorage.removeItem('userToken')
      localStorage.removeItem('userIsLoggedIn')
      localStorage.removeItem('activeProfileId')
    }
  }

  async function fetchUser(): Promise<void> {
    if (!isLoggedIn.value) return
    loading.value = true
    try {
      const response = await api.get('/auth/me')
      if (response.data.success) {
        user.value = response.data.data
        const accountData = response.data.data as Account
        if (accountData.profiles) {
          setProfiles(accountData.profiles)
        }
      }
    } catch {
      await logout()
    } finally {
      loading.value = false
    }
  }

  async function fetchProfiles(): Promise<void> {
    try {
      const response = await api.get('/user/profiles')
      if (response.data.success) {
        setProfiles(response.data.data)
      }
    } catch {
      // ignore
    }
  }

  async function addChildProfile(data: { name: string; birth_date?: string; phone?: string }): Promise<boolean> {
    loading.value = true
    error.value = null
    try {
      const response = await api.post('/user/children', data)
      if (response.data.success) {
        await fetchProfiles()
        return true
      }
      error.value = response.data.message || '자녀 추가에 실패했습니다.'
      return false
    } catch (e: any) {
      error.value = e.response?.data?.message || '자녀 추가에 실패했습니다.'
      return false
    } finally {
      loading.value = false
    }
  }

  async function removeChildProfile(id: number): Promise<boolean> {
    loading.value = true
    error.value = null
    try {
      const response = await api.delete(`/user/children/${id}`)
      if (response.data.success) {
        await fetchProfiles()
        return true
      }
      error.value = response.data.message || '자녀 삭제에 실패했습니다.'
      return false
    } catch (e: any) {
      error.value = e.response?.data?.message || '자녀 삭제에 실패했습니다.'
      return false
    } finally {
      loading.value = false
    }
  }

  async function updateProfile(data: { name?: string; phone?: string; email?: string }): Promise<boolean> {
    loading.value = true
    error.value = null
    try {
      const response = await api.put('/user/profile', data)
      if (response.data.success) {
        // activeProfile 갱신
        if (activeProfile.value) {
          Object.assign(activeProfile.value, response.data.data)
        }
        await fetchProfiles()
        return true
      }
      error.value = response.data.message || '프로필 수정에 실패했습니다.'
      return false
    } catch (e: any) {
      error.value = e.response?.data?.message || '프로필 수정에 실패했습니다.'
      return false
    } finally {
      loading.value = false
    }
  }

  async function updateChildProfile(id: number, data: { name: string; birth_date?: string; phone?: string }): Promise<boolean> {
    loading.value = true
    error.value = null
    try {
      const response = await api.put(`/user/children/${id}`, data)
      if (response.data.success) {
        await fetchProfiles()
        return true
      }
      error.value = response.data.message || '자녀 프로필 수정에 실패했습니다.'
      return false
    } catch (e: any) {
      error.value = e.response?.data?.message || '자녀 프로필 수정에 실패했습니다.'
      return false
    } finally {
      loading.value = false
    }
  }

  async function changePassword(currentPassword: string, newPassword: string, newPasswordConfirmation: string): Promise<boolean> {
    loading.value = true
    error.value = null
    try {
      const response = await api.post('/user/change-password', {
        current_password: currentPassword,
        new_password: newPassword,
        new_password_confirmation: newPasswordConfirmation,
      })
      if (response.data.success) {
        return true
      }
      error.value = response.data.message || '비밀번호 변경에 실패했습니다.'
      return false
    } catch (e: any) {
      error.value = e.response?.data?.errors?.current_password?.[0]
        || e.response?.data?.errors?.new_password?.[0]
        || e.response?.data?.message
        || '비밀번호 변경에 실패했습니다.'
      return false
    } finally {
      loading.value = false
    }
  }

  async function deleteAccount(password: string): Promise<boolean> {
    loading.value = true
    error.value = null
    try {
      const response = await api.post('/user/delete-account', { password })
      if (response.data.success) {
        // 로그아웃 처리
        user.value = null
        token.value = null
        isLoggedIn.value = false
        profiles.value = []
        activeProfile.value = null
        localStorage.removeItem('userToken')
        localStorage.removeItem('userIsLoggedIn')
        localStorage.removeItem('activeProfileId')
        return true
      }
      error.value = response.data.message || '회원 탈퇴에 실패했습니다.'
      return false
    } catch (e: any) {
      error.value = e.response?.data?.errors?.password?.[0]
        || e.response?.data?.message
        || '회원 탈퇴에 실패했습니다.'
      return false
    } finally {
      loading.value = false
    }
  }

  const hasInstitution = computed(() => !!user.value?.institution_id)

  async function joinInstitution(inviteCode: string): Promise<boolean> {
    loading.value = true
    error.value = null
    try {
      const response = await api.post('/user/join-institution', { invite_code: inviteCode })
      if (response.data.success) {
        await fetchUser()
        return true
      }
      error.value = response.data.message || '기관 연결에 실패했습니다.'
      return false
    } catch (e: any) {
      const msg = e.response?.data?.errors?.invite_code?.[0] || e.response?.data?.message || '기관 연결에 실패했습니다.'
      error.value = msg
      return false
    } finally {
      loading.value = false
    }
  }

  return {
    user, token, loading, error, isLoggedIn,
    profiles, activeProfile, isParent, isLearner, needsProfileSelect, hasInstitution,
    login, register, logout, fetchUser,
    switchProfile, fetchProfiles, addChildProfile, removeChildProfile, joinInstitution,
    updateProfile, updateChildProfile, changePassword, deleteAccount,
  }
})
