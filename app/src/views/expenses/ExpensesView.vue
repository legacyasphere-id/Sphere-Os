<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import api from '@/lib/api'

interface ExpenseRow {
  id: number
  title: string
  amount: string
  category: string
  expense_date: string
  client: { id: number; name: string } | null
  project: { id: number; name: string } | null
}

const expenses = ref<ExpenseRow[]>([])
const loading = ref(true)
const showModal = ref(false)
const saving = ref(false)
const error = ref('')
const totalAmount = ref(0)
const filterCategory = ref('')
const categories = ref<string[]>([])
const clients = ref<{ id: number; name: string }[]>([])
const projects = ref<{ id: number; name: string }[]>([])

const form = reactive({
  title:        '',
  amount:       '' as number | '',
  category:     '',
  expense_date: new Date().toISOString().slice(0, 10),
  client_id:    '' as number | '',
  project_id:   '' as number | '',
  notes:        '',
})

const suggestedCategories = ['Tools & Software', 'Hosting', 'Freelancer', 'Travel', 'Marketing', 'Equipment', 'Other']

async function fetchExpenses() {
  loading.value = true
  try {
    const params: Record<string, string> = {}
    if (filterCategory.value) params.category = filterCategory.value
    const res = await api.get('/expenses', { params })
    expenses.value   = res.data.data ?? []
    totalAmount.value = res.data.meta?.total_amount ?? 0
    const seen = new Set<string>()
    expenses.value.forEach(e => seen.add(e.category))
    categories.value = Array.from(seen).sort()
  } catch {
    error.value = 'Failed to load expenses.'
  } finally {
    loading.value = false
  }
}

async function fetchLookups() {
  const [c, p] = await Promise.all([
    api.get('/clients', { params: { per_page: 100 } }),
    api.get('/projects', { params: { per_page: 100 } }),
  ])
  clients.value  = c.data.data ?? []
  projects.value = p.data.data ?? []
}

onMounted(() => { fetchExpenses(); fetchLookups() })

async function saveExpense() {
  if (!form.title.trim() || !form.amount || !form.category.trim()) return
  saving.value = true
  try {
    await api.post('/expenses', {
      title:        form.title,
      amount:       form.amount,
      category:     form.category,
      expense_date: form.expense_date,
      client_id:    form.client_id || null,
      project_id:   form.project_id || null,
      notes:        form.notes || null,
    })
    showModal.value = false
    Object.assign(form, { title: '', amount: '', category: '', expense_date: new Date().toISOString().slice(0, 10), client_id: '', project_id: '', notes: '' })
    await fetchExpenses()
  } catch {
    error.value = 'Failed to save expense.'
  } finally {
    saving.value = false
  }
}

async function remove(id: number) {
  if (!confirm('Delete this expense?')) return
  await api.delete(`/expenses/${id}`)
  expenses.value = expenses.value.filter(e => e.id !== id)
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function fmt(v: string | number) {
  return '$' + parseFloat(v as string).toFixed(2)
}
</script>

<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Expenses</h1>
        <p class="text-sm text-gray-500 mt-0.5">Track your business spending</p>
      </div>
      <button @click="showModal = true" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
        + Add Expense
      </button>
    </div>

    <!-- Summary + filter -->
    <div class="flex items-center justify-between mb-6 gap-4">
      <div class="bg-white rounded-xl border border-gray-200 px-5 py-3 flex items-center gap-3">
        <span class="text-xs text-gray-500 uppercase tracking-wide">Total (filtered)</span>
        <span class="text-lg font-bold text-gray-900">{{ fmt(totalAmount) }}</span>
      </div>
      <select v-model="filterCategory" @change="fetchExpenses()" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
        <option value="">All Categories</option>
        <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
      </select>
    </div>

    <div v-if="loading" class="flex justify-center py-20">
      <svg class="animate-spin h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
      </svg>
    </div>

    <div v-else-if="expenses.length === 0" class="bg-white rounded-xl border border-gray-200 py-16 text-center">
      <p class="text-gray-400 text-sm">No expenses found.</p>
      <button @click="showModal = true" class="mt-3 text-sm text-indigo-600 hover:text-indigo-500 font-medium">Add your first expense</button>
    </div>

    <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="w-full">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Title</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Category</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Amount</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Date</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Linked to</th>
            <th class="px-6 py-3 w-12"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="exp in expenses" :key="exp.id" class="group">
            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ exp.title }}</td>
            <td class="px-6 py-4">
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">{{ exp.category }}</span>
            </td>
            <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ fmt(exp.amount) }}</td>
            <td class="px-6 py-4 text-sm text-gray-400">{{ formatDate(exp.expense_date) }}</td>
            <td class="px-6 py-4 text-sm text-gray-500">
              <span v-if="exp.client">{{ exp.client.name }}</span>
              <span v-else-if="exp.project">{{ exp.project.name }}</span>
              <span v-else class="text-gray-300">—</span>
            </td>
            <td class="px-6 py-4">
              <button @click="remove(exp.id)" class="opacity-0 group-hover:opacity-100 p-1 text-gray-400 hover:text-red-500 transition-opacity">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add Expense Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="showModal = false"/>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6 z-10">
          <h2 class="text-lg font-semibold text-gray-900 mb-5">Add Expense</h2>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
              <input v-model="form.title" type="text" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Amount <span class="text-red-500">*</span></label>
                <input v-model.number="form.amount" type="number" min="0" step="0.01" placeholder="0.00" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-500">*</span></label>
                <input v-model="form.expense_date" type="date" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Category <span class="text-red-500">*</span></label>
              <input v-model="form.category" type="text" list="cat-suggestions" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              <datalist id="cat-suggestions">
                <option v-for="c in suggestedCategories" :key="c" :value="c"/>
              </datalist>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Client</label>
                <select v-model="form.client_id" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option value="">None</option>
                  <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Project</label>
                <select v-model="form.project_id" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option value="">None</option>
                  <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
              </div>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button @click="showModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
            <button @click="saveExpense" :disabled="!form.title.trim() || !form.amount || !form.category.trim() || saving" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
              {{ saving ? 'Saving...' : 'Add Expense' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
