<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import api from '@/lib/api'

interface MonthData { month: string; label: string; revenue: number; expenses: number; profit: number }
interface CategoryData { category: string; total: string }
interface ClientData { id: number; name: string; total: string }

interface Summary {
  monthly: MonthData[]
  outstanding_count: number
  outstanding_total: number
  paid_ytd: number
  expense_breakdown: CategoryData[]
  top_clients: ClientData[]
}

const summary = ref<Summary | null>(null)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const res = await api.get('/finance/summary')
    summary.value = res.data
  } catch {
    error.value = 'Failed to load finance data.'
  } finally {
    loading.value = false
  }
})

const maxBarValue = computed(() => {
  if (!summary.value) return 1
  return Math.max(...summary.value.monthly.flatMap(m => [m.revenue, m.expenses]), 1)
})

function barHeight(value: number) {
  return Math.max(4, Math.round((value / maxBarValue.value) * 120))
}

function fmt(v: number | string) {
  return '$' + parseFloat(v as string).toFixed(2)
}

const totalExpenseBreakdown = computed(() =>
  summary.value?.expense_breakdown.reduce((s, e) => s + parseFloat(e.total), 0) ?? 1
)

const currentMonthProfit = computed(() => {
  if (!summary.value || !summary.value.monthly.length) return 0
  return summary.value.monthly.at(-1)?.profit ?? 0
})
</script>

<template>
  <div class="p-8">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Finance</h1>
      <p class="text-sm text-gray-500 mt-0.5">Revenue, expenses, and profit overview</p>
    </div>

    <div v-if="loading" class="flex justify-center py-24">
      <svg class="animate-spin h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
      </svg>
    </div>

    <template v-else-if="summary">
      <!-- Stat cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <p class="text-xs text-gray-500 uppercase tracking-wide">Outstanding</p>
          <p class="mt-1 text-2xl font-bold text-red-600">{{ fmt(summary.outstanding_total) }}</p>
          <p class="text-xs text-gray-400 mt-0.5">{{ summary.outstanding_count }} invoice{{ summary.outstanding_count !== 1 ? 's' : '' }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <p class="text-xs text-gray-500 uppercase tracking-wide">Paid YTD</p>
          <p class="mt-1 text-2xl font-bold text-green-600">{{ fmt(summary.paid_ytd) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <p class="text-xs text-gray-500 uppercase tracking-wide">Expenses This Month</p>
          <p class="mt-1 text-2xl font-bold text-gray-900">
            {{ summary.monthly.length ? fmt(summary.monthly.at(-1)!.expenses) : '$0.00' }}
          </p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <p class="text-xs text-gray-500 uppercase tracking-wide">Profit This Month</p>
          <p class="mt-1 text-2xl font-bold" :class="currentMonthProfit >= 0 ? 'text-green-600' : 'text-red-600'">
            {{ fmt(currentMonthProfit) }}
          </p>
        </div>
      </div>

      <!-- Monthly bar chart -->
      <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-4">Revenue vs Expenses (12 months)</h2>
        <div class="flex items-end gap-2 h-32">
          <div v-for="m in summary.monthly" :key="m.month" class="flex-1 flex flex-col items-center gap-0.5">
            <div class="w-full flex items-end justify-center gap-0.5">
              <div
                :style="{ height: barHeight(m.revenue) + 'px' }"
                class="flex-1 bg-green-400 rounded-t min-h-1"
                :title="`Revenue: ${fmt(m.revenue)}`"
              />
              <div
                :style="{ height: barHeight(m.expenses) + 'px' }"
                class="flex-1 bg-red-300 rounded-t min-h-1"
                :title="`Expenses: ${fmt(m.expenses)}`"
              />
            </div>
            <span class="text-xs text-gray-400 truncate w-full text-center">{{ m.label.slice(0, 3) }}</span>
          </div>
        </div>
        <div class="flex items-center gap-4 mt-3">
          <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded-sm bg-green-400"></div><span class="text-xs text-gray-500">Revenue</span></div>
          <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded-sm bg-red-300"></div><span class="text-xs text-gray-500">Expenses</span></div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Expense breakdown -->
        <div class="bg-white rounded-xl border border-gray-200 p-6">
          <h2 class="text-sm font-semibold text-gray-900 mb-4">Expenses This Month by Category</h2>
          <div v-if="summary.expense_breakdown.length === 0" class="py-8 text-center text-sm text-gray-400">No expenses this month.</div>
          <div v-else class="space-y-3">
            <div v-for="cat in summary.expense_breakdown" :key="cat.category">
              <div class="flex items-center justify-between mb-1">
                <span class="text-sm text-gray-700">{{ cat.category }}</span>
                <span class="text-sm font-medium text-gray-900">{{ fmt(cat.total) }}</span>
              </div>
              <div class="w-full bg-gray-100 rounded-full h-1.5">
                <div
                  class="bg-indigo-500 h-1.5 rounded-full"
                  :style="{ width: Math.round(parseFloat(cat.total) / totalExpenseBreakdown * 100) + '%' }"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Top clients -->
        <div class="bg-white rounded-xl border border-gray-200 p-6">
          <h2 class="text-sm font-semibold text-gray-900 mb-4">Top Clients by Revenue</h2>
          <div v-if="summary.top_clients.length === 0" class="py-8 text-center text-sm text-gray-400">No paid invoices yet.</div>
          <div v-else class="space-y-3">
            <div v-for="(client, i) in summary.top_clients" :key="client.id" class="flex items-center gap-3">
              <span class="w-5 h-5 flex-shrink-0 flex items-center justify-center rounded-full bg-indigo-100 text-xs font-medium text-indigo-700">{{ i + 1 }}</span>
              <router-link :to="`/clients/${client.id}`" class="text-sm font-medium text-gray-900 hover:text-indigo-600 flex-1">{{ client.name }}</router-link>
              <span class="text-sm font-semibold text-gray-900">{{ fmt(client.total) }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div v-else-if="error" class="rounded-md bg-red-50 border border-red-200 px-4 py-3">
      <p class="text-sm text-red-700">{{ error }}</p>
    </div>
  </div>
</template>
