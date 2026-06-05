<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'

interface Client {
  id: number
  name: string
  company: string | null
  email: string | null
  phone: string | null
  status: 'lead' | 'prospect' | 'client' | 'lost'
  next_action_date: string | null
  next_action_note: string | null
  notes: string | null
  projects_count?: number
}

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const router = useRouter()

const clients = ref<Client[]>([])
const loading = ref(true)
const error = ref('')
const searchQuery = ref('')
const activeFilter = ref<string>('all')
const pagination = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
const currentPage = ref(1)

// Modal state
const showCreateModal = ref(false)
const createLoading = ref(false)
const createError = ref('')
const createForm = reactive({
  name: '',
  company: '',
  email: '',
  phone: '',
  status: 'lead' as Client['status'],
  next_action_date: '',
  next_action_note: '',
  notes: '',
})

// Delete state
const deleteId = ref<number | null>(null)
const deleteLoading = ref(false)
const showDeleteConfirm = ref(false)

const filterTabs = [
  { key: 'all', label: 'All' },
  { key: 'lead', label: 'Lead' },
  { key: 'prospect', label: 'Prospect' },
  { key: 'client', label: 'Client' },
  { key: 'lost', label: 'Lost' },
]

async function fetchClients() {
  loading.value = true
  error.value = ''
  try {
    const params: Record<string, any> = { page: currentPage.value }
    if (activeFilter.value !== 'all') params.status = activeFilter.value
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim()
    const res = await api.get('/clients', { params })
    // Handle both paginated and plain array responses
    if (Array.isArray(res.data)) {
      clients.value = res.data
      pagination.value = { current_page: 1, last_page: 1, per_page: res.data.length, total: res.data.length }
    } else {
      clients.value = res.data.data ?? res.data
      if (res.data.meta) pagination.value = res.data.meta
      else if (res.data.current_page) pagination.value = res.data
    }
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Failed to load clients.'
  } finally {
    loading.value = false
  }
}

function setFilter(key: string) {
  activeFilter.value = key
  currentPage.value = 1
  fetchClients()
}

let searchTimer: ReturnType<typeof setTimeout>
function onSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    currentPage.value = 1
    fetchClients()
  }, 300)
}

function goToPage(page: number) {
  currentPage.value = page
  fetchClients()
}

function openCreate() {
  createForm.name = ''
  createForm.company = ''
  createForm.email = ''
  createForm.phone = ''
  createForm.status = 'lead'
  createForm.next_action_date = ''
  createForm.next_action_note = ''
  createForm.notes = ''
  createError.value = ''
  showCreateModal.value = true
}

async function submitCreate() {
  if (!createForm.name.trim()) {
    createError.value = 'Name is required.'
    return
  }
  createError.value = ''
  createLoading.value = true
  try {
    const payload: Record<string, any> = { name: createForm.name.trim(), status: createForm.status }
    if (createForm.company.trim()) payload.company = createForm.company.trim()
    if (createForm.email.trim()) payload.email = createForm.email.trim()
    if (createForm.phone.trim()) payload.phone = createForm.phone.trim()
    if (createForm.next_action_date) payload.next_action_date = createForm.next_action_date
    if (createForm.next_action_note.trim()) payload.next_action_note = createForm.next_action_note.trim()
    if (createForm.notes.trim()) payload.notes = createForm.notes.trim()
    await api.post('/clients', payload)
    showCreateModal.value = false
    fetchClients()
  } catch (e: any) {
    const data = e?.response?.data
    if (data?.errors) {
      const firstKey = Object.keys(data.errors)[0] as string
      createError.value = data.errors[firstKey]?.[0] ?? 'Failed to create client.'
    } else {
      createError.value = data?.message ?? 'Failed to create client.'
    }
  } finally {
    createLoading.value = false
  }
}

function confirmDelete(id: number) {
  deleteId.value = id
  showDeleteConfirm.value = true
}

async function executeDelete() {
  if (!deleteId.value) return
  deleteLoading.value = true
  try {
    await api.delete(`/clients/${deleteId.value}`)
    showDeleteConfirm.value = false
    deleteId.value = null
    fetchClients()
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Failed to delete client.'
    showDeleteConfirm.value = false
  } finally {
    deleteLoading.value = false
  }
}

function navigateToClient(id: number) {
  router.push({ name: 'client-detail', params: { id } })
}

function formatDate(date: string | null): string {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function statusClass(status: string): string {
  const map: Record<string, string> = {
    lead: 'bg-gray-100 text-gray-600',
    prospect: 'bg-blue-100 text-blue-700',
    client: 'bg-green-100 text-green-700',
    lost: 'bg-red-100 text-red-700',
  }
  return map[status] ?? 'bg-gray-100 text-gray-600'
}

const pages = computed(() => {
  const total = pagination.value.last_page
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)
  const cur = pagination.value.current_page
  const result: (number | '...')[] = [1]
  if (cur > 3) result.push('...')
  for (let i = Math.max(2, cur - 1); i <= Math.min(total - 1, cur + 1); i++) result.push(i)
  if (cur < total - 2) result.push('...')
  result.push(total)
  return result
})

onMounted(fetchClients)
</script>

<template>
  <div class="p-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Clients</h1>
        <p class="mt-1 text-sm text-gray-500">Manage your clients and leads</p>
      </div>
      <button
        @click="openCreate"
        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Add Client
      </button>
    </div>

    <!-- Filters & Search -->
    <div class="flex flex-col sm:flex-row gap-4 mb-6">
      <!-- Filter tabs -->
      <div class="flex items-center gap-1 bg-gray-100 p-1 rounded-lg">
        <button
          v-for="tab in filterTabs"
          :key="tab.key"
          @click="setFilter(tab.key)"
          :class="[
            'px-3 py-1.5 text-sm font-medium rounded-md transition-colors',
            activeFilter === tab.key
              ? 'bg-white text-gray-900 shadow-sm'
              : 'text-gray-500 hover:text-gray-700'
          ]"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Search -->
      <div class="flex-1 relative">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input
          v-model="searchQuery"
          @input="onSearch"
          type="text"
          placeholder="Search clients..."
          class="w-full pl-10 pr-4 py-2 text-sm border rounded-md ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
        />
      </div>
    </div>

    <!-- Error -->
    <div v-if="error" class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3">
      <p class="text-sm text-red-700">{{ error }}</p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-24">
      <svg class="animate-spin h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
    </div>

    <!-- Table -->
    <div v-else class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div v-if="clients.length === 0" class="px-6 py-16 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <p class="mt-3 text-sm text-gray-400">No clients found</p>
        <button @click="openCreate" class="mt-3 text-sm text-indigo-600 hover:text-indigo-500 font-medium">Add your first client</button>
      </div>
      <table v-else class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Company</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Next Action</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Projects</th>
            <th class="px-6 py-3 relative"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-100">
          <tr
            v-for="client in clients"
            :key="client.id"
            @click="navigateToClient(client.id)"
            class="hover:bg-gray-50 cursor-pointer transition-colors"
          >
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-semibold text-indigo-700 flex-shrink-0">
                  {{ client.name.charAt(0).toUpperCase() }}
                </div>
                <span class="text-sm font-medium text-gray-900">{{ client.name }}</span>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ client.company ?? '—' }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ client.email ?? '—' }}</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                :class="statusClass(client.status)"
              >
                {{ client.status }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(client.next_action_date) }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ client.projects_count ?? '—' }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-right">
              <button
                @click.stop="confirmDelete(client.id)"
                class="p-1.5 text-gray-400 hover:text-red-600 rounded transition-colors"
                title="Delete client"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
        <p class="text-xs text-gray-500">
          Page {{ pagination.current_page }} of {{ pagination.last_page }} &middot; {{ pagination.total }} total
        </p>
        <div class="flex items-center gap-1">
          <button
            @click="goToPage(currentPage - 1)"
            :disabled="currentPage === 1"
            class="px-2 py-1 text-xs rounded border border-gray-300 disabled:opacity-40 hover:bg-gray-50 transition-colors"
          >Prev</button>
          <template v-for="(page, idx) in pages" :key="idx">
            <span v-if="page === '...'" class="px-2 py-1 text-xs text-gray-400">…</span>
            <button
              v-else
              @click="goToPage(page as number)"
              :class="[
                'px-2.5 py-1 text-xs rounded border transition-colors',
                page === currentPage
                  ? 'bg-indigo-600 text-white border-indigo-600'
                  : 'border-gray-300 hover:bg-gray-50'
              ]"
            >{{ page }}</button>
          </template>
          <button
            @click="goToPage(currentPage + 1)"
            :disabled="currentPage === pagination.last_page"
            class="px-2 py-1 text-xs rounded border border-gray-300 disabled:opacity-40 hover:bg-gray-50 transition-colors"
          >Next</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Create Client Modal -->
  <Teleport to="body">
    <div
      v-if="showCreateModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4"
      @click.self="showCreateModal = false"
    >
      <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showCreateModal = false" />
      <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-900">Add Client</h2>
          <button @click="showCreateModal = false" class="p-1 rounded text-gray-400 hover:text-gray-600 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="px-6 py-5 space-y-4">
          <div v-if="createError" class="rounded-md bg-red-50 border border-red-200 px-4 py-3">
            <p class="text-sm text-red-700">{{ createError }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
            <input
              v-model="createForm.name"
              type="text"
              placeholder="Full name"
              class="w-full px-3 py-2 text-sm border rounded-md ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
            />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Company</label>
              <input
                v-model="createForm.company"
                type="text"
                placeholder="Acme Inc."
                class="w-full px-3 py-2 text-sm border rounded-md ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <select
                v-model="createForm.status"
                class="w-full px-3 py-2 text-sm border rounded-md ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
              >
                <option value="lead">Lead</option>
                <option value="prospect">Prospect</option>
                <option value="client">Client</option>
                <option value="lost">Lost</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
              <input
                v-model="createForm.email"
                type="email"
                placeholder="email@example.com"
                class="w-full px-3 py-2 text-sm border rounded-md ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
              <input
                v-model="createForm.phone"
                type="text"
                placeholder="+1 555 000 0000"
                class="w-full px-3 py-2 text-sm border rounded-md ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Next Action Date</label>
              <input
                v-model="createForm.next_action_date"
                type="date"
                class="w-full px-3 py-2 text-sm border rounded-md ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Next Action Note</label>
              <input
                v-model="createForm.next_action_note"
                type="text"
                placeholder="Follow up call"
                class="w-full px-3 py-2 text-sm border rounded-md ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
              />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea
              v-model="createForm.notes"
              rows="3"
              placeholder="Additional notes..."
              class="w-full px-3 py-2 text-sm border rounded-md ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition resize-none"
            />
          </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
          <button
            @click="showCreateModal = false"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
          >
            Cancel
          </button>
          <button
            @click="submitCreate"
            :disabled="createLoading"
            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
          >
            <svg v-if="createLoading" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            {{ createLoading ? 'Saving…' : 'Add Client' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Delete Confirm Modal -->
  <Teleport to="body">
    <div
      v-if="showDeleteConfirm"
      class="fixed inset-0 z-50 flex items-center justify-center p-4"
    >
      <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showDeleteConfirm = false" />
      <div class="relative bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900">Delete Client</h3>
        <p class="mt-2 text-sm text-gray-500">Are you sure you want to delete this client? This action cannot be undone.</p>
        <div class="mt-5 flex justify-end gap-3">
          <button
            @click="showDeleteConfirm = false"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
          >
            Cancel
          </button>
          <button
            @click="executeDelete"
            :disabled="deleteLoading"
            class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-md hover:bg-red-700 disabled:opacity-60 transition-colors"
          >
            <svg v-if="deleteLoading" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            Delete
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
