<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'

interface InvoiceRow {
  id: number
  invoice_number: string
  status: string
  issue_date: string
  due_date: string | null
  total: string
  client: { id: number; name: string } | null
  project: { id: number; name: string } | null
}

const router = useRouter()
const invoices = ref<InvoiceRow[]>([])
const loading = ref(true)
const showModal = ref(false)
const saving = ref(false)
const error = ref('')
const filterStatus = ref('')
const outstandingTotal = ref(0)
const paidThisMonth = ref(0)
const clients = ref<{ id: number; name: string }[]>([])
const projects = ref<{ id: number; name: string }[]>([])

interface LineItemForm { description: string; quantity: number; unit_price: number }

const form = reactive({
  client_id:  '' as number | '',
  project_id: '' as number | '',
  issue_date: new Date().toISOString().slice(0, 10),
  due_date:   '',
  tax_rate:   0,
  notes:      '',
  items:      [{ description: '', quantity: 1, unit_price: 0 }] as LineItemForm[],
})

async function fetchInvoices() {
  loading.value = true
  try {
    const params: Record<string, string> = {}
    if (filterStatus.value) params.status = filterStatus.value
    const res = await api.get('/invoices', { params })
    invoices.value = res.data.data ?? []
    outstandingTotal.value = res.data.meta?.outstanding_total ?? 0
    paidThisMonth.value    = res.data.meta?.paid_this_month ?? 0
  } catch {
    error.value = 'Failed to load invoices.'
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

onMounted(() => { fetchInvoices(); fetchLookups() })

function addItem() {
  form.items.push({ description: '', quantity: 1, unit_price: 0 })
}

function removeItem(i: number) {
  if (form.items.length > 1) form.items.splice(i, 1)
}

function lineTotal(item: LineItemForm) {
  return (item.quantity * item.unit_price).toFixed(2)
}

function formSubtotal() {
  return form.items.reduce((s, i) => s + i.quantity * i.unit_price, 0)
}

function formTotal() {
  const sub = formSubtotal()
  return (sub + sub * form.tax_rate / 100).toFixed(2)
}

async function createInvoice() {
  if (form.items.some(i => !i.description.trim())) return
  saving.value = true
  try {
    const res = await api.post('/invoices', {
      client_id:  form.client_id || null,
      project_id: form.project_id || null,
      issue_date: form.issue_date,
      due_date:   form.due_date || null,
      tax_rate:   form.tax_rate,
      notes:      form.notes || null,
      items:      form.items.map((item, i) => ({ ...item, sort_order: i })),
    })
    router.push(`/invoices/${res.data.id}`)
  } catch {
    error.value = 'Failed to create invoice.'
    saving.value = false
  }
}

const statusTabs = ['', 'draft', 'sent', 'paid', 'overdue', 'cancelled']

function statusClass(status: string) {
  return {
    draft:     'bg-gray-100 text-gray-600',
    sent:      'bg-blue-100 text-blue-700',
    paid:      'bg-green-100 text-green-700',
    overdue:   'bg-red-100 text-red-700',
    cancelled: 'bg-gray-100 text-gray-400',
  }[status] ?? 'bg-gray-100 text-gray-600'
}

function formatDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function formatCurrency(v: string | number) {
  return '$' + parseFloat(v as string).toFixed(2)
}
</script>

<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Invoices</h1>
        <p class="text-sm text-gray-500 mt-0.5">Manage billing and payments</p>
      </div>
      <button @click="showModal = true" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
        + New Invoice
      </button>
    </div>

    <!-- Summary bar -->
    <div class="grid grid-cols-2 gap-4 mb-6">
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Outstanding</p>
        <p class="text-2xl font-bold text-red-600 mt-1">{{ formatCurrency(outstandingTotal) }}</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Paid This Month</p>
        <p class="text-2xl font-bold text-green-600 mt-1">{{ formatCurrency(paidThisMonth) }}</p>
      </div>
    </div>

    <!-- Status tabs -->
    <div class="border-b border-gray-200 mb-6">
      <nav class="flex gap-1">
        <button
          v-for="tab in statusTabs"
          :key="tab"
          @click="filterStatus = tab; fetchInvoices()"
          class="px-4 py-2 text-sm font-medium capitalize rounded-t border-b-2 transition-colors"
          :class="filterStatus === tab ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
        >{{ tab === '' ? 'All' : tab }}</button>
      </nav>
    </div>

    <div v-if="loading" class="flex justify-center py-20">
      <svg class="animate-spin h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
      </svg>
    </div>

    <div v-else-if="invoices.length === 0" class="bg-white rounded-xl border border-gray-200 py-16 text-center">
      <p class="text-gray-400 text-sm">No invoices found.</p>
      <button @click="showModal = true" class="mt-3 text-sm text-indigo-600 hover:text-indigo-500 font-medium">Create your first invoice</button>
    </div>

    <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="w-full">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Number</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Client</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Total</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Issue Date</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Due Date</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr
            v-for="inv in invoices"
            :key="inv.id"
            @click="router.push(`/invoices/${inv.id}`)"
            class="hover:bg-gray-50 cursor-pointer transition-colors"
          >
            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ inv.invoice_number }}</td>
            <td class="px-6 py-4 text-sm text-gray-600">{{ inv.client?.name ?? '—' }}</td>
            <td class="px-6 py-4">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize" :class="statusClass(inv.status)">{{ inv.status }}</span>
            </td>
            <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ formatCurrency(inv.total) }}</td>
            <td class="px-6 py-4 text-sm text-gray-400">{{ formatDate(inv.issue_date) }}</td>
            <td class="px-6 py-4 text-sm text-gray-400">{{ formatDate(inv.due_date) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create Invoice Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="showModal = false"/>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl p-6 z-10 max-h-[90vh] overflow-y-auto">
          <h2 class="text-lg font-semibold text-gray-900 mb-5">New Invoice</h2>
          <div class="space-y-4">
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
            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Issue Date <span class="text-red-500">*</span></label>
                <input v-model="form.issue_date" type="date" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                <input v-model="form.due_date" type="date" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tax Rate (%)</label>
                <input v-model.number="form.tax_rate" type="number" min="0" max="100" step="0.1" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>

            <!-- Line items -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <label class="text-sm font-medium text-gray-700">Line Items <span class="text-red-500">*</span></label>
                <button @click="addItem" class="text-xs text-indigo-600 hover:text-indigo-500 font-medium">+ Add item</button>
              </div>
              <div class="space-y-2">
                <div v-for="(item, i) in form.items" :key="i" class="grid grid-cols-12 gap-2 items-center">
                  <input v-model="item.description" placeholder="Description" class="col-span-6 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  <input v-model.number="item.quantity" type="number" min="0.01" step="0.01" placeholder="Qty" class="col-span-2 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  <input v-model.number="item.unit_price" type="number" min="0" step="0.01" placeholder="Price" class="col-span-2 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  <span class="col-span-1 text-xs text-gray-500 text-right">${{ lineTotal(item) }}</span>
                  <button @click="removeItem(i)" :disabled="form.items.length === 1" class="col-span-1 p-1 text-gray-400 hover:text-red-500 disabled:opacity-30 text-center">
                    <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                  </button>
                </div>
              </div>
              <div class="flex justify-end mt-3 text-sm font-semibold text-gray-900">
                Total: ${{ formTotal() }}
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
              <textarea v-model="form.notes" rows="2" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button @click="showModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
            <button @click="createInvoice" :disabled="saving || form.items.some(i => !i.description.trim())" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
              {{ saving ? 'Creating...' : 'Create Invoice' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
