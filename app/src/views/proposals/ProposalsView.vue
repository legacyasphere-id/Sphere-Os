<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'

interface ProposalRow {
  id: number
  title: string
  status: string
  ai_generated: boolean
  client: { id: number; name: string } | null
  project: { id: number; name: string } | null
  updated_at: string
}

const router = useRouter()
const proposals = ref<ProposalRow[]>([])
const loading = ref(true)
const showModal = ref(false)
const saving = ref(false)
const error = ref('')
const filterStatus = ref('')
const clients = ref<{ id: number; name: string }[]>([])
const projects = ref<{ id: number; name: string }[]>([])

const form = reactive({
  title: '',
  client_id: '' as number | '',
  project_id: '' as number | '',
  content: '',
})

async function fetchProposals() {
  loading.value = true
  try {
    const params: Record<string, string> = {}
    if (filterStatus.value) params.status = filterStatus.value
    const res = await api.get('/proposals', { params })
    proposals.value = res.data.data ?? res.data
  } catch {
    error.value = 'Failed to load proposals.'
  } finally {
    loading.value = false
  }
}

async function fetchLookups() {
  const [c, p] = await Promise.all([
    api.get('/clients', { params: { per_page: 100 } }),
    api.get('/projects', { params: { per_page: 100 } }),
  ])
  clients.value = c.data.data ?? []
  projects.value = p.data.data ?? []
}

onMounted(() => {
  fetchProposals()
  fetchLookups()
})

async function saveProposal() {
  if (!form.title.trim()) return
  saving.value = true
  try {
    await api.post('/proposals', {
      title:      form.title,
      client_id:  form.client_id || null,
      project_id: form.project_id || null,
      content:    form.content || null,
      status:     'draft',
    })
    showModal.value = false
    Object.assign(form, { title: '', client_id: '', project_id: '', content: '' })
    await fetchProposals()
  } catch {
    error.value = 'Failed to create proposal.'
  } finally {
    saving.value = false
  }
}

const statusTabs = ['', 'draft', 'sent', 'accepted', 'rejected', 'converted']

function statusClass(status: string) {
  return {
    draft:     'bg-gray-100 text-gray-600',
    sent:      'bg-blue-100 text-blue-700',
    accepted:  'bg-green-100 text-green-700',
    rejected:  'bg-red-100 text-red-700',
    converted: 'bg-indigo-100 text-indigo-700',
  }[status] ?? 'bg-gray-100 text-gray-600'
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>

<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Proposals</h1>
        <p class="text-sm text-gray-500 mt-0.5">Track and manage client proposals</p>
      </div>
      <button @click="showModal = true" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
        + New Proposal
      </button>
    </div>

    <!-- Status filter tabs -->
    <div class="border-b border-gray-200 mb-6">
      <nav class="flex gap-1">
        <button
          v-for="tab in statusTabs"
          :key="tab"
          @click="filterStatus = tab; fetchProposals()"
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

    <div v-else-if="proposals.length === 0" class="bg-white rounded-xl border border-gray-200 py-16 text-center">
      <p class="text-gray-400 text-sm">No proposals yet.</p>
      <button @click="showModal = true" class="mt-3 text-sm text-indigo-600 hover:text-indigo-500 font-medium">Create your first proposal</button>
    </div>

    <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="w-full">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Title</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Client</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Project</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Updated</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr
            v-for="p in proposals"
            :key="p.id"
            @click="router.push(`/proposals/${p.id}`)"
            class="hover:bg-gray-50 cursor-pointer transition-colors"
          >
            <td class="px-6 py-4">
              <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-900">{{ p.title }}</span>
                <span v-if="p.ai_generated" class="text-xs text-indigo-500">AI</span>
              </div>
            </td>
            <td class="px-6 py-4 text-sm text-gray-600">{{ p.client?.name ?? '—' }}</td>
            <td class="px-6 py-4 text-sm text-gray-600">{{ p.project?.name ?? '—' }}</td>
            <td class="px-6 py-4">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize" :class="statusClass(p.status)">{{ p.status }}</span>
            </td>
            <td class="px-6 py-4 text-sm text-gray-400">{{ formatDate(p.updated_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create Proposal Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="showModal = false"/>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg p-6 z-10">
          <h2 class="text-lg font-semibold text-gray-900 mb-5">New Proposal</h2>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
              <input v-model="form.title" type="text" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
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
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Content (optional)</label>
              <textarea v-model="form.content" rows="4" placeholder="Proposal content in markdown..." class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none font-mono" />
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button @click="showModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
            <button @click="saveProposal" :disabled="!form.title.trim() || saving" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
              {{ saving ? 'Creating...' : 'Create Proposal' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
