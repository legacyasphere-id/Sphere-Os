<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'
import { aiEnabled } from '@/composables/useAI'

interface Task {
  id: number
  title: string
  project: { id: number; name: string }
  priority: 'low' | 'medium' | 'high'
  due_date: string | null
  status: string
}

interface UpcomingAction {
  id: number
  name: string
  company: string | null
  next_action_date: string | null
  next_action_note: string | null
}

interface RecentClient {
  id: number
  name: string
  company: string | null
  status: string
}

interface DashboardStats {
  clients: number
  active_projects: number
  open_tasks: number
  overdue_tasks: number
}

interface DashboardData {
  stats: DashboardStats
  upcoming_tasks: Task[]
  upcoming_actions: UpcomingAction[]
  recent_clients: RecentClient[]
}

interface AIUsageDay { date: string; calls: number; cost_usd: number }
interface AIUsageFeature { calls: number; cost_usd: number }
interface AIUsage {
  period: string
  total_calls: number
  total_tokens: number
  total_cost_usd: number
  by_feature: Record<string, AIUsageFeature>
  daily: AIUsageDay[]
}

const data = ref<DashboardData | null>(null)
const loading = ref(true)
const error = ref('')
const aiUsage = ref<AIUsage | null>(null)

onMounted(async () => {
  try {
    const res = await api.get('/dashboard')
    data.value = res.data
  } catch (e: any) {
    error.value = 'Failed to load dashboard data.'
  } finally {
    loading.value = false
  }

  if (aiEnabled) {
    try {
      const res = await api.get('/ai/usage')
      aiUsage.value = res.data
    } catch {
      // AI usage is optional — fail silently
    }
  }
})

function formatDate(date: string | null): string {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function priorityClass(priority: string): string {
  return {
    high: 'bg-red-100 text-red-700',
    medium: 'bg-yellow-100 text-yellow-700',
    low: 'bg-gray-100 text-gray-600',
  }[priority] ?? 'bg-gray-100 text-gray-600'
}

function clientStatusClass(status: string): string {
  return {
    lead: 'bg-gray-100 text-gray-600',
    prospect: 'bg-blue-100 text-blue-700',
    client: 'bg-green-100 text-green-700',
    lost: 'bg-red-100 text-red-700',
  }[status] ?? 'bg-gray-100 text-gray-600'
}
</script>

<template>
  <div class="p-8">
    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
      <p class="mt-1 text-sm text-gray-500">Welcome back. Here's what's happening.</p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-24">
      <svg class="animate-spin h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="rounded-md bg-red-50 border border-red-200 px-4 py-3">
      <p class="text-sm text-red-700">{{ error }}</p>
    </div>

    <template v-else-if="data">
      <!-- Stat cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
          <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Clients</p>
          <p class="mt-2 text-3xl font-bold text-gray-900">{{ data.stats.clients }}</p>
          <div class="mt-3 flex items-center">
            <RouterLink to="/clients" class="text-xs text-indigo-600 hover:text-indigo-500 font-medium">View all</RouterLink>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
          <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Active Projects</p>
          <p class="mt-2 text-3xl font-bold text-gray-900">{{ data.stats.active_projects }}</p>
          <div class="mt-3 flex items-center">
            <RouterLink to="/projects" class="text-xs text-indigo-600 hover:text-indigo-500 font-medium">View all</RouterLink>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
          <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Open Tasks</p>
          <p class="mt-2 text-3xl font-bold text-gray-900">{{ data.stats.open_tasks }}</p>
          <div class="mt-3 flex items-center">
            <span class="text-xs text-gray-400">Across all projects</span>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
          <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Overdue Tasks</p>
          <p class="mt-2 text-3xl font-bold" :class="data.stats.overdue_tasks > 0 ? 'text-red-600' : 'text-gray-900'">
            {{ data.stats.overdue_tasks }}
          </p>
          <div class="mt-3 flex items-center">
            <span class="text-xs" :class="data.stats.overdue_tasks > 0 ? 'text-red-500' : 'text-gray-400'">
              {{ data.stats.overdue_tasks > 0 ? 'Needs attention' : 'All on track' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Two-column grid -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Upcoming Tasks -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
          <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Upcoming Tasks</h2>
          </div>
          <div v-if="data.upcoming_tasks.length === 0" class="px-6 py-8 text-center">
            <p class="text-sm text-gray-400">No upcoming tasks</p>
          </div>
          <div v-else class="divide-y divide-gray-50">
            <div
              v-for="task in data.upcoming_tasks"
              :key="task.id"
              class="px-6 py-3 flex items-center justify-between gap-4"
            >
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-900 truncate">{{ task.title }}</p>
                <RouterLink
                  :to="`/projects/${task.project?.id}`"
                  class="text-xs text-indigo-600 hover:text-indigo-500 truncate"
                >
                  {{ task.project?.name }}
                </RouterLink>
              </div>
              <div class="flex items-center gap-2 flex-shrink-0">
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium capitalize"
                  :class="priorityClass(task.priority)"
                >
                  {{ task.priority }}
                </span>
                <span class="text-xs text-gray-400 whitespace-nowrap">{{ formatDate(task.due_date) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Upcoming Client Actions -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
          <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Upcoming Client Actions</h2>
          </div>
          <div v-if="data.upcoming_actions.length === 0" class="px-6 py-8 text-center">
            <p class="text-sm text-gray-400">No upcoming actions</p>
          </div>
          <div v-else class="divide-y divide-gray-50">
            <div
              v-for="action in data.upcoming_actions"
              :key="action.id"
              class="px-6 py-3 flex items-center justify-between gap-4"
            >
              <div class="min-w-0 flex-1">
                <RouterLink
                  :to="`/clients/${action.id}`"
                  class="text-sm font-medium text-gray-900 hover:text-indigo-600 truncate block"
                >
                  {{ action.name }}
                </RouterLink>
                <p class="text-xs text-gray-500 truncate">{{ action.company ?? '—' }}</p>
                <p v-if="action.next_action_note" class="text-xs text-gray-400 truncate">{{ action.next_action_note }}</p>
              </div>
              <span class="text-xs text-gray-400 whitespace-nowrap flex-shrink-0">{{ formatDate(action.next_action_date) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Clients -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-gray-900">Recent Clients</h2>
          <RouterLink to="/clients" class="text-xs text-indigo-600 hover:text-indigo-500 font-medium">View all</RouterLink>
        </div>
        <div v-if="!data.recent_clients || data.recent_clients.length === 0" class="px-6 py-8 text-center">
          <p class="text-sm text-gray-400">No clients yet</p>
        </div>
        <div v-else class="divide-y divide-gray-50">
          <RouterLink
            v-for="client in data.recent_clients"
            :key="client.id"
            :to="`/clients/${client.id}`"
            class="px-6 py-3 flex items-center justify-between gap-4 hover:bg-gray-50 transition-colors"
          >
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-semibold text-indigo-700 flex-shrink-0">
                {{ client.name.charAt(0).toUpperCase() }}
              </div>
              <div class="min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">{{ client.name }}</p>
                <p class="text-xs text-gray-500 truncate">{{ client.company ?? '—' }}</p>
              </div>
            </div>
            <span
              class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium capitalize flex-shrink-0"
              :class="clientStatusClass(client.status)"
            >
              {{ client.status }}
            </span>
          </RouterLink>
        </div>
      </div>
      <!-- AI Usage Card -->
      <div v-if="aiEnabled && aiUsage" class="mt-6 bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-gray-900">AI Usage — {{ aiUsage.period }}</h2>
          <span class="text-xs text-gray-400">This month</span>
        </div>
        <div class="px-6 py-4 grid grid-cols-3 gap-6">
          <div>
            <p class="text-xs text-gray-500 uppercase tracking-wide">Total Calls</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ aiUsage.total_calls }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500 uppercase tracking-wide">Tokens Used</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ aiUsage.total_tokens.toLocaleString() }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500 uppercase tracking-wide">Total Cost</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">${{ aiUsage.total_cost_usd.toFixed(4) }}</p>
          </div>
        </div>
        <div v-if="Object.keys(aiUsage.by_feature).length" class="px-6 pb-4 flex flex-wrap gap-3">
          <div v-for="(feat, key) in aiUsage.by_feature" :key="key" class="text-xs bg-indigo-50 text-indigo-700 rounded-full px-3 py-1 capitalize">
            {{ key }}: {{ feat.calls }} calls · ${{ feat.cost_usd.toFixed(4) }}
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
