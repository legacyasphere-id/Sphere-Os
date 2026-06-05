import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
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
      component: () => import('@/layouts/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/views/DashboardView.vue'),
        },
        {
          path: 'clients',
          name: 'clients',
          component: () => import('@/views/clients/ClientsView.vue'),
        },
        {
          path: 'clients/:id',
          name: 'client-detail',
          component: () => import('@/views/clients/ClientDetailView.vue'),
        },
        {
          path: 'projects',
          name: 'projects',
          component: () => import('@/views/projects/ProjectsView.vue'),
        },
        {
          path: 'projects/:id',
          name: 'project-detail',
          component: () => import('@/views/projects/ProjectDetailView.vue'),
        },
        {
          path: 'proposals',
          name: 'proposals',
          component: () => import('@/views/proposals/ProposalsView.vue'),
        },
        {
          path: 'proposals/:id',
          name: 'proposal-detail',
          component: () => import('@/views/proposals/ProposalDetailView.vue'),
        },
        {
          path: 'invoices',
          name: 'invoices',
          component: () => import('@/views/invoices/InvoicesView.vue'),
        },
        {
          path: 'invoices/:id',
          name: 'invoice-detail',
          component: () => import('@/views/invoices/InvoiceDetailView.vue'),
        },
        {
          path: 'expenses',
          name: 'expenses',
          component: () => import('@/views/expenses/ExpensesView.vue'),
        },
        {
          path: 'finance',
          name: 'finance',
          component: () => import('@/views/finance/FinanceDashboardView.vue'),
        },
        {
          path: 'knowledge',
          name: 'knowledge',
          component: () => import('@/views/knowledge/KnowledgeView.vue'),
        },
        {
          path: 'knowledge/:id',
          name: 'knowledge-detail',
          component: () => import('@/views/knowledge/KnowledgeDocumentView.vue'),
        },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    await auth.fetchUser().catch(() => {})
    if (!auth.isAuthenticated) return { name: 'login' }
  }

  if (to.meta.guest && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
})

export default router
