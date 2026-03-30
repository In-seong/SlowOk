import { createRouter, createWebHashHistory } from 'vue-router'
import DashboardView from '../views/DashboardView.vue'

const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    {
      path: '/',
      name: 'dashboard',
      component: DashboardView,
      meta: { requiresAuth: true },
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue'),
    },
    {
      path: '/users',
      name: 'users',
      component: () => import('../views/users/UserListView.vue'),
      meta: { requiresAuth: true, permissionKey: 'user_manage' },
    },
    {
      path: '/users/:id',
      name: 'user-detail',
      component: () => import('../views/users/UserDetailView.vue'),
      meta: { requiresAuth: true, permissionKey: 'user_manage' },
    },
    // [미사용] 사용자 앱에서 학습 콘텐츠 진입 경로 없음 — 추후 활성화 시 복원
    // {
    //   path: '/content',
    //   name: 'content',
    //   component: () => import('../views/content/ContentListView.vue'),
    //   meta: { requiresAuth: true, permissionKey: 'content_manage' },
    // },
    // {
    //   path: '/content/create',
    //   name: 'content-create',
    //   component: () => import('../views/content/ContentFormView.vue'),
    //   meta: { requiresAuth: true, permissionKey: 'content_manage' },
    // },
    // {
    //   path: '/content/:id/edit',
    //   name: 'content-edit',
    //   component: () => import('../views/content/ContentFormView.vue'),
    //   meta: { requiresAuth: true, permissionKey: 'content_manage' },
    // },
    {
      path: '/screening',
      name: 'screening',
      component: () => import('../views/screening/ScreeningManageView.vue'),
      meta: { requiresAuth: true, permissionKey: 'screening_manage' },
    },
    {
      path: '/screening-results',
      name: 'screening-results',
      component: () => import('../views/screening/ScreeningResultsView.vue'),
      meta: { requiresAuth: true, permissionKey: 'screening_result_view' },
    },
    {
      path: '/categories',
      name: 'categories',
      component: () => import('../views/category/CategoryManageView.vue'),
      meta: { requiresAuth: true, permissionKey: 'category_manage' },
    },
    {
      path: '/challenges',
      name: 'challenges',
      component: () => import('../views/challenge/ChallengeManageView.vue'),
      meta: { requiresAuth: true, permissionKey: 'challenge_manage' },
    },
    {
      path: '/challenges/:id/edit',
      name: 'challenge-question-edit',
      component: () => import('../views/challenge/ChallengeQuestionEditView.vue'),
      meta: { requiresAuth: true, permissionKey: 'challenge_manage' },
    },
    // [미사용] 보상카드 부여 로직 미구현 — 추후 완성 시 활성화
    // {
    //   path: '/reward-cards',
    //   name: 'reward-cards',
    //   component: () => import('../views/reward/RewardCardManageView.vue'),
    //   meta: { requiresAuth: true, permissionKey: 'reward_manage' },
    // },
    {
      path: '/institutions',
      name: 'institutions',
      component: () => import('../views/institution/InstitutionManageView.vue'),
      meta: { requiresAuth: true, permissionKey: 'institution_manage' },
    },
    // [미사용] 사용자 앱에서 구독 기능 미사용 — 추후 과금 모델 도입 시 활성화
    // {
    //   path: '/subscriptions',
    //   name: 'subscriptions',
    //   component: () => import('../views/subscription/SubscriptionManageView.vue'),
    //   meta: { requiresAuth: true, permissionKey: 'subscription_manage' },
    // },
    // [미사용] 리포트 생성 로직 미구현 — 추후 완성 시 활성화
    // {
    //   path: '/reports',
    //   name: 'reports',
    //   component: () => import('../views/report/ReportListView.vue'),
    //   meta: { requiresAuth: true, permissionKey: 'report_view' },
    // },
    {
      path: '/content-assignments',
      name: 'content-assignments',
      component: () => import('../views/assignment/ContentAssignmentView.vue'),
      meta: { requiresAuth: true, permissionKey: 'content_assign' },
    },
    // [미사용] 사용자 앱에서 패키지 기능 미사용 — 추후 필요 시 활성화
    // {
    //   path: '/content-packages',
    //   name: 'content-packages',
    //   component: () => import('../views/package/ContentPackageManageView.vue'),
    //   meta: { requiresAuth: true, permissionKey: 'package_manage' },
    // },
    {
      path: '/ai-content',
      name: 'ai-content',
      component: () => import('../views/ai/AiContentGenerateView.vue'),
      meta: { requiresAuth: true, permissionKey: 'content_manage' },
    },
    // [미사용] 추천 규칙 — 추후 활성화 시 복원
    // {
    //   path: '/recommendation-rules',
    //   name: 'recommendation-rules',
    //   component: () => import('../views/recommendation/RecommendationRuleView.vue'),
    //   meta: { requiresAuth: true, permissionKey: 'recommendation_manage' },
    // },
    // [미사용] 음성 기능 비활성화 — 추후 활성화 시 복원
    // {
    //   path: '/voice-recordings',
    //   name: 'voice-recordings',
    //   component: () => import('../views/voice-recording/VoiceRecordingManageView.vue'),
    //   meta: { requiresAuth: true, permissionKey: 'user_manage' },
    // },
    {
      path: '/plan-manage',
      name: 'plan-manage',
      component: () => import('../views/plan/PlanManageView.vue'),
      meta: { requiresAuth: true, masterOnly: true },
    },
    {
      path: '/admin-management',
      name: 'admin-management',
      component: () => import('../views/admin-management/AdminManageView.vue'),
      meta: { requiresAuth: true, masterOnly: true },
    },
    {
      path: '/admin-management/permissions/:id',
      name: 'admin-permissions',
      component: () => import('../views/admin-management/AdminPermissionView.vue'),
      meta: { requiresAuth: true, masterOnly: true },
    },
    {
      path: '/how-to-use',
      name: 'how-to-use',
      component: () => import('../views/HowToUseView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/dev/stt-test',
      name: 'stt-test',
      component: () => import('../views/dev/SttTestView.vue'),
      meta: { requiresAuth: true, masterOnly: true },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('../views/NotFoundView.vue'),
    },
  ],
})

router.beforeEach(async (to) => {
  const isLoggedIn = localStorage.getItem('adminIsLoggedIn') === 'true'

  if (to.meta.requiresAuth && !isLoggedIn) {
    return { name: 'login' }
  }

  if (to.name === 'login' && isLoggedIn) {
    return { name: 'dashboard' }
  }

  // 권한 가드: 로그인 상태에서만 체크
  if (isLoggedIn && (to.meta.masterOnly || to.meta.permissionKey)) {
    const { useAdminAuthStore } = await import('../stores/adminAuthStore')
    const adminAuthStore = useAdminAuthStore()

    // user 정보가 아직 로딩되지 않은 경우 fetchUser 호출
    if (!adminAuthStore.user) {
      await adminAuthStore.fetchUser()
    }

    if (to.meta.masterOnly && !adminAuthStore.isMaster) {
      return { name: 'dashboard' }
    }

    if (to.meta.permissionKey && !adminAuthStore.hasPermission(to.meta.permissionKey as string)) {
      return { name: 'dashboard' }
    }
  }
})

export default router
