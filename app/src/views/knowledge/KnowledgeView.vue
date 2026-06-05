<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'

interface DocRow {
  id: number
  title: string
  type: string
  tags: string[] | null
  is_pinned: boolean
  client: { id: number; name: string } | null
  project: { id: number; name: string } | null
  updated_at: string
}

const router = useRouter()
const docs = ref<DocRow[]>([])
const loading = ref(true)
const showModal = ref(false)
const saving = ref(false)
const error = ref('')
const search = ref('')
const filterType = ref('')
const searchTimer = ref<ReturnType<typeof setTimeout>>()

const clients = ref<{ id: number; name: string }[]>([])
const projects = ref<{ id: number; name: string }[]>([])

const form = reactive({
  title:      '',
  type:       'note',
  tags:       '',
  client_id:  '' as number | '',
  project_id: '' as number | '',
  content:    '',
  is_pinned:  false,
})

async function fetchDocs() {
  loading.value = true
  try {
    const params: Record<string, string> = {}
    if (filterType.value) params.type   = filterType.value
    if (search.value)     params.search = search.value
    const res = await api.get('/knowledge', { params })
    docs.value = res.data.data ?? res.data
  } catch {
    error.value = 'Failed to load documents.'
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

onMounted(() => { fetchDocs(); fetchLookups() })

function onSearchInput() {
  clearTimeout(searchTimer.value)
  searchTimer.value = setTimeout(fetchDocs, 300)
}

async function saveDoc() {
  if (!form.title.trim()) return
  saving.value = true
  try {
    const tags = form.tags ? form.tags.split(',').map(t => t.trim()).filter(Boolean) : []
    const res = await api.post('/knowledge', {
      title:      form.title,
      type:       form.type,
      tags:       tags.length ? tags : null,
      client_id:  form.client_id || null,
      project_id: form.project_id || null,
      content:    form.content || null,
      is_pinned:  form.is_pinned,
    })
    showModal.value = false
    Object.assign(form, { title: '', type: 'note', tags: '', client_id: '', project_id: '', content: '', is_pinned: false })
    router.push(`/knowledge/${res.data.id}`)
  } catch {
    error.value = 'Failed to create document.'
  } finally {
    saving.value = false
  }
}

async function togglePin(doc: DocRow) {
  await api.post(`/knowledge/${doc.id}/toggle-pin`)
  doc.is_pinned = !doc.is_pinned
}

function typeClass(type: string) {
  return {
    note:      'bg-gray-100 text-gray-600',
    template:  'bg-blue-100 text-blue-700',
    sop:       'bg-yellow-100 text-yellow-700',
    reference: 'bg-purple-100 text-purple-700',
  }[type] ?? 'bg-gray-100 text-gray-600'
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

const pinnedDocs = () => docs.value.filter(d => d.is_pinned)
const unpinnedDocs = () => docs.value.filter(d => !d.is_pinned)
</script>

<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Knowledge Base</h1>
        <p class="text-sm text-gray-500 mt-0.5">Notes, templates, and reference documents</p>
      </div>
      <button @click="showModal = true" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
        + New Document
      </button>
    </div>

    <!-- Search + filter -->
    <div class="flex items-center gap-3 mb-6">
      <div class="relative flex-1 max-w-sm">
        <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
        </svg>
        <input v-model="search" @input="onSearchInput" type="text" placeholder="Search documents..." class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>
      <select v-model="filterType" @change="fetchDocs()" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
        <option value="">All Types</option>
        <option value="note">Note</option>
        <option value="template">Template</option>
        <option value="sop">SOP</option>
        <option value="reference">Reference</option>
      </select>
    </div>

    <!-- Pinned section -->
    <div v-if="!search && !filterType && pinnedDocs().length" class="mb-6">
      <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Pinned</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        <div
          v-for="doc in pinnedDocs()"
          :key="doc.id"
          @click="router.push(`/knowledge/${doc.id}`)"
          class="bg-white rounded-xl border border-indigo-100 p-4 cursor-pointer hover:border-indigo-300 transition-colors relative group"
        >
          <button @click.stop="togglePin(doc)" class="absolute top-3 right-3 text-indigo-400 hover:text-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity" title="Unpin">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M16 3H8l1 5H7l-2 3h6v8l1 1 1-1v-8h6l-2-3h-2l1-5z"/></svg>
          </button>
          <p class="text-sm font-medium text-gray-900 pr-6">{{ doc.title }}</p>
          <div class="flex items-center gap-2 mt-2">
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium capitalize" :class="typeClass(doc.type)">{{ doc.type }}</span>
            <span v-if="doc.tags?.length" class="text-xs text-gray-400">{{ doc.tags.slice(0, 2).join(', ') }}</span>
          </div>
        </div>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-20">
      <svg class="animate-spin h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
      </svg>
    </div>

    <div v-else-if="docs.length === 0" class="bg-white rounded-xl border border-gray-200 py-16 text-center">
      <p class="text-gray-400 text-sm">{{ search ? 'No documents match your search.' : 'No documents yet.' }}</p>
      <button v-if="!search" @click="showModal = true" class="mt-3 text-sm text-indigo-600 hover:text-indigo-500 font-medium">Create your first document</button>
    </div>

    <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="w-full">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Title</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Type</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Tags</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Linked to</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Updated</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr
            v-for="doc in docs"
            :key="doc.id"
            @click="router.push(`/knowledge/${doc.id}`)"
            class="hover:bg-gray-50 cursor-pointer transition-colors"
          >
            <td class="px-6 py-4">
              <div class="flex items-center gap-2">
                <svg v-if="doc.is_pinned" class="w-3.5 h-3.5 text-indigo-400 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M16 3H8l1 5H7l-2 3h6v8l1 1 1-1v-8h6l-2-3h-2l1-5z"/></svg>
                <span class="text-sm font-medium text-gray-900">{{ doc.title }}</span>
              </div>
            </td>
            <td class="px-6 py-4">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium capitalize" :class="typeClass(doc.type)">{{ doc.type }}</span>
            </td>
            <td class="px-6 py-4">
              <div v-if="doc.tags?.length" class="flex gap-1 flex-wrap">
                <span v-for="tag in doc.tags.slice(0, 3)" :key="tag" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ tag }}</span>
              </div>
              <span v-else class="text-gray-300 text-sm">—</span>
            </td>
            <td class="px-6 py-4 text-sm text-gray-500">
              <span v-if="doc.client">{{ doc.client.name }}</span>
              <span v-else-if="doc.project">{{ doc.project.name }}</span>
              <span v-else class="text-gray-300">—</span>
            </td>
            <td class="px-6 py-4 text-sm text-gray-400">{{ formatDate(doc.updated_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create Document Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="showModal = false"/>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg p-6 z-10">
          <h2 class="text-lg font-semibold text-gray-900 mb-5">New Document</h2>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
              <input v-model="form.title" type="text" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <select v-model="form.type" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option value="note">Note</option>
                  <option value="template">Template</option>
                  <option value="sop">SOP</option>
                  <option value="reference">Reference</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tags (comma-separated)</label>
                <input v-model="form.tags" type="text" placeholder="onboarding, legal" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
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
            <div class="flex items-center gap-2">
              <input v-model="form.is_pinned" type="checkbox" id="pin-doc" class="h-4 w-4 text-indigo-600 rounded border-gray-300" />
              <label for="pin-doc" class="text-sm text-gray-700">Pin this document</label>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button @click="showModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
            <button @click="saveDoc" :disabled="!form.title.trim() || saving" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
              {{ saving ? 'Creating...' : 'Create' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
