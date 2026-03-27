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
      redirect: { name: 'home' },
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

  if (to.meta.requiresAuth && !isLoggedIn) {
    next({ name: 'login' })
    return
  }

  if (to.name === 'login' && isLoggedIn) {
    next({ name: 'home' })
    return
  }

  next()
})

export default router
