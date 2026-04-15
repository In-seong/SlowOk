import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '../views/LoginView.vue'
import MobileAdminLayout from '../components/MobileAdminLayout.vue'
import HomeView from '../views/HomeView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { public: true },
    },
    {
      path: '/',
      component: MobileAdminLayout,
      meta: { requiresAuth: true },
      children: [
        { path: '', name: 'home', component: HomeView },
        { path: 'users', name: 'users', component: () => import('../views/users/UserListView.vue') },
        { path: 'progress', name: 'progress', component: () => import('../views/progress/ProgressView.vue') },
        { path: 'more', name: 'more', component: () => import('../views/more/MoreMenuView.vue') },
      ],
    },
    // 하위 페이지 (하단탭 숨김, BackHeader 사용)
    { path: '/users/:id', name: 'user-detail', meta: { requiresAuth: true }, component: () => import('../views/users/UserDetailView.vue') },
    { path: '/dashboard', name: 'dashboard', meta: { requiresAuth: true }, component: () => import('../views/dashboard/DashboardView.vue') },
    { path: '/categories', name: 'categories', meta: { requiresAuth: true }, component: () => import('../views/category/CategoryManageView.vue') },
    { path: '/challenges', name: 'challenges', meta: { requiresAuth: true }, component: () => import('../views/challenge/ChallengeListView.vue') },
    { path: '/challenges/:id/questions', name: 'challenge-questions', meta: { requiresAuth: true }, component: () => import('../views/challenge/ChallengeQuestionEditView.vue') },
    { path: '/ai-content', name: 'ai-content', meta: { requiresAuth: true }, component: () => import('../views/ai/AiContentGenerateView.vue') },
    { path: '/screening', name: 'screening', meta: { requiresAuth: true }, component: () => import('../views/screening/ScreeningManageView.vue') },
    { path: '/screening-results', name: 'screening-results', meta: { requiresAuth: true }, component: () => import('../views/screening/ScreeningResultsView.vue') },
    { path: '/content-assignments', name: 'content-assignments', meta: { requiresAuth: true }, component: () => import('../views/assignment/ContentAssignmentView.vue') },
    { path: '/push', name: 'push', meta: { requiresAuth: true }, component: () => import('../views/push/PushSendView.vue') },
    { path: '/institutions', name: 'institutions', meta: { requiresAuth: true }, component: () => import('../views/institution/InstitutionManageView.vue') },
    { path: '/admin-management', name: 'admin-management', meta: { requiresAuth: true }, component: () => import('../views/admin-management/AdminManageView.vue') },
    { path: '/plan-manage', name: 'plan-manage', meta: { requiresAuth: true }, component: () => import('../views/plan/PlanManageView.vue') },
    { path: '/how-to-use', name: 'how-to-use', meta: { requiresAuth: true }, component: () => import('../views/guide/HowToUseView.vue') },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      redirect: { name: 'home' },
    },
  ],
})

router.beforeEach((to) => {
  const isLoggedIn = localStorage.getItem('adminIsLoggedIn') === 'true'

  // public이 아닌 모든 라우트는 인증 필요
  if (!to.meta.public && !isLoggedIn) {
    return { name: 'login' }
  }

  // 이미 로그인된 상태에서 로그인 페이지 접근 시 홈으로 리다이렉트
  if (to.name === 'login' && isLoggedIn) {
    return { name: 'home' }
  }
})

export default router
