import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { guest: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/auth/RegisterView.vue'),
      meta: { guest: true },
    },
    {
      path: '/',
      component: () => import('@/components/layout/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        { path: '', redirect: '/dashboard' },
        { path: 'dashboard', name: 'dashboard', component: () => import('@/views/DashboardView.vue') },
        { path: 'workflows', name: 'workflows', component: () => import('@/views/workflows/WorkflowListView.vue') },
        { path: 'workflows/new', name: 'workflow-create', component: () => import('@/views/workflows/WorkflowEditorView.vue'), meta: { requiresEditor: true } },
        { path: 'workflows/:id', name: 'workflow-detail', component: () => import('@/views/workflows/WorkflowDetailView.vue') },
        { path: 'workflows/:id/edit', name: 'workflow-edit', component: () => import('@/views/workflows/WorkflowEditorView.vue'), meta: { requiresEditor: true } },
        { path: 'runs/:id', name: 'run-detail', component: () => import('@/views/runs/RunDetailView.vue') },
        { path: 'ai-builder', name: 'ai-builder', component: () => import('@/views/AIBuilderView.vue'), meta: { requiresEditor: true } },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

// Fix: use return instead of next() — Vue Router 4 style
router.beforeEach(async (to) => {
  const authStore = useAuthStore()

  if (authStore.token && !authStore.user) {
    await authStore.fetchMe()
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guest && authStore.isAuthenticated) {
    return { name: 'dashboard' }
  }

  if (to.meta.requiresEditor && !authStore.isEditor) {
    return { name: 'dashboard' }
  }

  return true
})

export default router
