<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/lib/api'

interface InvoiceItem {
  id?: number
  description: string
  quantity: number
  unit_price: number
  amount: number
  sort_order: number
}

interface Invoice {
  id: number
  invoice_number: string
  status: 'draft' | 'sent' | 'paid' | 'overdue' | 'cancelled'
  issue_date: string
  due_date: string | null
  subtotal: string
  tax_rate: string
  tax_amount: string
  total: string
  currency: string
  notes: string | null
  paid_at: string | null
  client: { id: number; name: string; company?: string } | null
  project: { id: number; name: string } | null
  proposal: { id: number; title: string } | null
  items: InvoiceItem[]
}

const route  = useRoute()
const router = useRouter()
const invoice = ref<Invoice | null>(null)
const loading = ref(true)
const saving  = ref(false)
const error   = ref('')

interface EditItem { description: string; quantity: number; unit_price: number }
const editItems = ref<EditItem[]>([])
const itemsEdited = ref(false)

async function fetchInvoice() {
  loading.value = true
  try {
    const res = await api.get(`/invoices/${route.params.id}`)
    invoice.value = res.data
    editItems.value = res.data.items.map((i: InvoiceItem) => ({
      description: i.description,
      quantity:    i.quantity,
      unit_price:  i.unit_price,
    }))
  } catch {
    error.value = 'Failed to load invoice.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchInvoice)

function addItem() {
  editItems.value.push({ description: '', quantity: 1, unit_price: 0 })
  itemsEdited.value = true
}

function removeItem(i: number) {
  if (editItems.value.length > 1) {
    editItems.value.splice(i, 1)
    itemsEdited.value = true
  }
}

async function saveItems() {
  if (!invoice.value) return
  saving.value = true
  try {
    const res = await api.put(`/invoices/${invoice.value.id}/items`, {
      items: editItems.value.map((item, i) => ({ ...item, sort_order: i })),
    })
    invoice.value = res.data
    editItems.value = res.data.items.map((i: InvoiceItem) => ({
      description: i.description,
      quantity:    i.quantity,
      unit_price:  i.unit_price,
    }))
    itemsEdited.value = false
  } catch {
    error.value = 'Failed to save items.'
  } finally {
    saving.value = false
  }
}

async function markSent() {
  if (!invoice.value) return
  const res = await api.post(`/invoices/${invoice.value.id}/mark-sent`)
  invoice.value = res.data
}

async function markPaid() {
  if (!invoice.value) return
  const res = await api.post(`/invoices/${invoice.value.id}/mark-paid`)
  invoice.value = res.data
}

const subtotal = computed(() =>
  editItems.value.reduce((s, i) => s + i.quantity * i.unit_price, 0)
)

const taxAmount = computed(() =>
  invoice.value ? subtotal.value * parseFloat(invoice.value.tax_rate) / 100 : 0
)

const total = computed(() => subtotal.value + taxAmount.value)

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

function fmt(v: number | string) {
  return '$' + parseFloat(v as string).toFixed(2)
}

const printPage = () => window.print()
</script>

<template>
  <div class="p-8">
    <button @click="router.push('/invoices')" class="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-6 no-print">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
      </svg>
      Invoices
    </button>

    <div v-if="loading" class="flex justify-center py-24">
      <svg class="animate-spin h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
      </svg>
    </div>

    <template v-else-if="invoice">
      <!-- Header -->
      <div class="flex items-start justify-between mb-6 gap-4">
        <div>
          <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-2xl font-bold text-gray-900">{{ invoice.invoice_number }}</h1>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize" :class="statusClass(invoice.status)">{{ invoice.status }}</span>
          </div>
          <div class="flex items-center gap-4 mt-2 flex-wrap text-sm text-gray-500">
            <span v-if="invoice.client">
              <router-link :to="`/clients/${invoice.client.id}`" class="text-indigo-600 hover:underline">{{ invoice.client.name }}</router-link>
              <span v-if="invoice.client.company"> · {{ invoice.client.company }}</span>
            </span>
            <span>Issued {{ formatDate(invoice.issue_date) }}</span>
            <span v-if="invoice.due_date">Due {{ formatDate(invoice.due_date) }}</span>
            <span v-if="invoice.paid_at" class="text-green-600">Paid {{ formatDate(invoice.paid_at) }}</span>
          </div>
          <div v-if="invoice.proposal" class="mt-1.5">
            <router-link :to="`/proposals/${invoice.proposal.id}`" class="text-xs text-indigo-500 hover:underline">From proposal: {{ invoice.proposal.title }}</router-link>
          </div>
        </div>

        <div class="flex items-center gap-2 flex-shrink-0 no-print">
          <button v-if="invoice.status === 'draft'" @click="markSent" class="px-3 py-2 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100">Mark Sent</button>
          <button v-if="['sent', 'overdue'].includes(invoice.status)" @click="markPaid" class="px-3 py-2 text-sm font-medium text-green-700 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100">Mark Paid</button>
          <button @click="printPage" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Print</button>
        </div>
      </div>

      <div v-if="error" class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ error }}</div>

      <!-- Line items -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between no-print">
          <h2 class="text-sm font-semibold text-gray-900">Line Items</h2>
          <button @click="addItem" class="text-xs text-indigo-600 hover:text-indigo-500 font-medium">+ Add item</button>
        </div>
        <table class="w-full">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50">
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Description</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">Qty</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">Unit Price</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">Amount</th>
              <th class="px-6 py-3 w-8 no-print"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="(item, i) in editItems" :key="i">
              <td class="px-6 py-3">
                <input v-model="item.description" @input="itemsEdited = true" class="w-full text-sm text-gray-900 bg-transparent border-0 border-b border-transparent hover:border-gray-300 focus:border-indigo-500 focus:outline-none py-0.5" />
              </td>
              <td class="px-6 py-3 w-24">
                <input v-model.number="item.quantity" @input="itemsEdited = true" type="number" min="0.01" step="0.01" class="w-full text-sm text-right text-gray-900 bg-transparent border-0 border-b border-transparent hover:border-gray-300 focus:border-indigo-500 focus:outline-none py-0.5" />
              </td>
              <td class="px-6 py-3 w-32">
                <input v-model.number="item.unit_price" @input="itemsEdited = true" type="number" min="0" step="0.01" class="w-full text-sm text-right text-gray-900 bg-transparent border-0 border-b border-transparent hover:border-gray-300 focus:border-indigo-500 focus:outline-none py-0.5" />
              </td>
              <td class="px-6 py-3 text-right text-sm font-medium text-gray-900">
                {{ fmt(item.quantity * item.unit_price) }}
              </td>
              <td class="px-6 py-3 no-print">
                <button @click="removeItem(i)" :disabled="editItems.length === 1" class="p-1 text-gray-400 hover:text-red-500 disabled:opacity-30">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Totals -->
        <div class="border-t border-gray-100 px-6 py-4 space-y-1.5">
          <div class="flex justify-end gap-8 text-sm text-gray-600">
            <span>Subtotal</span>
            <span class="w-28 text-right">{{ fmt(subtotal) }}</span>
          </div>
          <div v-if="parseFloat(invoice.tax_rate) > 0" class="flex justify-end gap-8 text-sm text-gray-600">
            <span>Tax ({{ invoice.tax_rate }}%)</span>
            <span class="w-28 text-right">{{ fmt(taxAmount) }}</span>
          </div>
          <div class="flex justify-end gap-8 text-base font-bold text-gray-900 border-t border-gray-200 pt-2 mt-2">
            <span>Total</span>
            <span class="w-28 text-right">{{ fmt(total) }}</span>
          </div>
        </div>
      </div>

      <!-- Save items button -->
      <div v-if="itemsEdited" class="flex justify-end mb-6 no-print">
        <button @click="saveItems" :disabled="saving" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
          {{ saving ? 'Saving...' : 'Save Line Items' }}
        </button>
      </div>

      <!-- Notes -->
      <div v-if="invoice.notes" class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-2">Notes</h3>
        <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ invoice.notes }}</p>
      </div>
    </template>
  </div>
</template>

<style>
@media print {
  aside, nav, .no-print { display: none !important; }
  body { background: white; }
  .p-8 { padding: 0; }
}
</style>
