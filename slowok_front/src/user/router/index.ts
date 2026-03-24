import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
      meta: { requiresAuth: true },
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue'),
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('../views/RegisterView.vue'),
    },
    {
      path: '/profile-select',
      name: 'profile-select',
      component: () => import('../views/ProfileSelectView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/add-child',
      name: 'add-child',
      component: () => import('../views/AddChildProfileView.vue'),
      meta: { requiresAuth: true, parentOnly: true },
    },
    {
      path: '/parent-dashboard',
      name: 'parent-dashboard',
      component: () => import('../views/ParentDashboardView.vue'),
      meta: { requiresAuth: true, parentOnly: true },
    },
    {
      path: '/screening',
      name: 'screening-list',
      component: () => import('../views/screening/ScreeningListView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/screening/results',
      name: 'screening-results',
      component: () => import('../views/screening/ScreeningResultsView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/screening/result/:resultId',
      name: 'screening-result-detail',
      component: () => import('../views/screening/ScreeningResultDetailView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/screening/:id',
      name: 'screening-test',
      component: () => import('../views/screening/ScreeningTestView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/learning',
      name: 'learning-list',
      component: () => import('../views/learning/LearningListView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/learning/:id',
      name: 'learning-content',
      component: () => import('../views/learning/LearningContentView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/challenges',
      name: 'challenge-list',
      component: () => import('../views/challenge/ChallengeListView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/challenges/:id',
      name: 'challenge-play',
      component: () => import('../views/challenge/ChallengePlayView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/assessments',
      name: 'assessment-list',
      component: () => import('../views/assessment/AssessmentListView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/mypage',
      name: 'mypage',
      component: () => import('../views/mypage/MyPageView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/edit-profile',
      name: 'edit-profile',
      component: () => import('../views/mypage/EditProfileView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/change-password',
      name: 'change-password',
      component: () => import('../views/mypage/ChangePasswordView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/edit-child-profile/:id',
      name: 'edit-child-profile',
      component: () => import('../views/mypage/EditChildProfileView.vue'),
      meta: { requiresAuth: true, parentOnly: true },
    },
    {
      path: '/child-recordings/:id',
      name: 'child-recordings',
      component: () => import('../views/ChildRecordingsView.vue'),
      meta: { requiresAuth: true, parentOnly: true },
    },
    {
      path: '/notifications',
      name: 'notifications',
      component: () => import('../views/mypage/NotificationsView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/reports',
      name: 'reports',
      component: () => import('../views/mypage/ReportsView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/how-to-use',
      name: 'how-to-use',
      component: () => import('../views/HowToUseView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('../views/NotFoundView.vue'),
    },
  ],
})

router.beforeEach(async (to, _from, next) => {
  const isLoggedIn = localStorage.getItem('userIsLoggedIn') === 'true'
  const activeProfileId = localStorage.getItem('activeProfileId')

  if (to.meta.requiresAuth && !isLoggedIn) {
    next({ name: 'login' })
    return
  }

  if (to.name === 'login' && isLoggedIn) {
    next({ name: 'home' })
    return
  }

  // 프로필 선택 화면 자체와 add-child는 예외 처리
  const profileExemptRoutes = ['profile-select', 'add-child', 'edit-child-profile']
  if (
    isLoggedIn &&
    !activeProfileId &&
    to.meta.requiresAuth &&
    !profileExemptRoutes.includes(to.name as string)
  ) {
    // activeProfileId가 없으면 프로필 선택 화면으로 리다이렉트
    // 단, profiles가 1개인 경우는 authStore에서 자동 설정되므로 여기서는 패스
    next({ name: 'profile-select' })
    return
  }

  // parentOnly 가드: PARENT 프로필이 아닌 경우 홈으로 리다이렉트
  if (to.meta.parentOnly && activeProfileId) {
    const { useAuthStore } = await import('../stores/authStore')
    const authStore = useAuthStore()
    if (authStore.activeProfile && authStore.activeProfile.user_type !== 'PARENT') {
      next({ name: 'home' })
      return
    }
  }

  next()
})

export default router
