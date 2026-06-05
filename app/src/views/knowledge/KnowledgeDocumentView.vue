<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/lib/api'

interface KnowledgeDocument {
  id: number
  title: string
  type: string
  tags: string[] | null
  is_pinned: boolean
  content: string | null
  client: { id: number; name: string } | null
  project: { id: number; name: string } | null
  client_id: number | null
  project_id: number | null
  updated_at: string
}

const route  = useRoute()
const router = useRouter()
const doc     = ref<KnowledgeDocument | null>(null)
const loading = ref(true)
const saving  = ref(false)
const error   = ref('')
const saved   = ref(false)

const clients  = ref<{ id: number; name: string }[]>([])
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

async function fetchDoc() {
  loading.value = true
  try {
    const res = await api.get(`/knowledge/${route.params.id}`)
    doc.value = res.data
    Object.assign(form, {
      title:      res.data.title,
      type:       res.data.type,
      tags:       res.data.tags?.join(', ') ?? '',
      client_id:  res.data.client_id ?? '',
      project_id: res.data.project_id ?? '',
      content:    res.data.content ?? '',
      is_pinned:  res.data.is_pinned,
    })
  } catch {
    error.value = 'Failed to load document.'
  } finally {
    loading.value = false
  }
}

async function fetchLookups() {
  const [c, p] = await Promise.all([
    api.get('/clients',  { params: { per_page: 100 } }),
    api.get('/projects', { params: { per_page: 100 } }),
  ])
  clients.value  = c.data.data ?? []
  projects.value = p.data.data ?? []
}

onMounted(() => { fetchDoc(); fetchLookups() })

async function save() {
  if (!form.title.trim()) return
  saving.value = true
  saved.value  = false
  try {
    const tags = form.tags ? form.tags.split(',').map(t => t.trim()).filter(Boolean) : []
    const res  = await api.put(`/knowledge/${route.params.id}`, {
      title:      form.title,
      type:       form.type,
      tags:       tags.length ? tags : null,
      client_id:  form.client_id || null,
      project_id: form.project_id || null,
      content:    form.content || null,
      is_pinned:  form.is_pinned,
    })
    doc.value   = res.data
    saved.value = true
    setTimeout(() => { saved.value = false }, 2000)
  } catch {
    error.value = 'Failed to save document.'
  } finally {
    saving.value = false
  }
}

async function togglePin() {
  if (!doc.value) return
  await api.post(`/knowledge/${doc.value.id}/toggle-pin`)
  doc.value.is_pinned = !doc.value.is_pinned
  form.is_pinned      = doc.value.is_pinned
}

async function remove() {
  if (!confirm('Delete this document?')) return
  await api.delete(`/knowledge/${route.params.id}`)
  router.push('/knowledge')
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
</script>

<template>
  <div class="p-8">
    <button @click="router.push('/knowledge')" class="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-6">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
      </svg>
      Knowledge Base
    </button>

    <div v-if="loading" class="flex justify-center py-24">
      <svg class="animate-spin h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
      </svg>
    </div>

    <template v-else-if="doc">
      <!-- Header -->
      <div class="flex items-start justify-between mb-6 gap-4">
        <div class="flex-1 min-w-0">
          <input
            v-model="form.title"
            type="text"
            class="w-full text-2xl font-bold text-gray-900 bg-transparent border-0 border-b-2 border-transparent hover:border-gray-300 focus:border-indigo-500 focus:outline-none pb-1 mb-2"
          />
          <div class="flex items-center gap-3 flex-wrap text-sm text-gray-500">
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium capitalize" :class="typeClass(doc.type)">{{ doc.type }}</span>
            <span v-if="doc.tags?.length" class="text-xs text-gray-400">{{ doc.tags.join(', ') }}</span>
            <span v-if="doc.client">{{ doc.client.name }}</span>
            <span v-else-if="doc.project">{{ doc.project.name }}</span>
            <span class="text-gray-400">Updated {{ formatDate(doc.updated_at) }}</span>
          </div>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <button
            @click="togglePin"
            class="px-3 py-2 text-sm font-medium rounded-lg border transition-colors"
            :class="doc.is_pinned ? 'text-indigo-700 bg-indigo-50 border-indigo-200 hover:bg-indigo-100' : 'text-gray-700 bg-white border-gray-300 hover:bg-gray-50'"
          >
            {{ doc.is_pinned ? 'Unpin' : 'Pin' }}
          </button>
          <button @click="remove" class="px-3 py-2 text-sm font-medium text-red-600 bg-white border border-gray-300 rounded-lg hover:bg-red-50">Delete</button>
        </div>
      </div>

      <div v-if="error" class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ error }}</div>

      <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Content area -->
        <div class="lg:col-span-3">
          <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-3 border-b border-gray-100 flex items-center justify-between">
              <span class="text-sm font-semibold text-gray-900">Content</span>
              <span v-if="saved" class="text-xs text-green-600 font-medium">Saved</span>
            </div>
            <textarea
              v-model="form.content"
              rows="24"
              placeholder="Write your document content here..."
              class="w-full px-6 py-4 text-sm text-gray-800 font-mono bg-transparent border-0 focus:outline-none resize-none"
            />
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-4">
          <!-- Document properties -->
          <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-4">
            <div>
              <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1.5">Type</label>
              <select v-model="form.type" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="note">Note</option>
                <option value="template">Template</option>
                <option value="sop">SOP</option>
                <option value="reference">Reference</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1.5">Tags</label>
              <input v-model="form.tags" type="text" placeholder="onboarding, legal" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              <p class="text-xs text-gray-400 mt-1">Comma-separated</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1.5">Client</label>
              <select v-model="form.client_id" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">None</option>
                <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1.5">Project</label>
              <select v-model="form.project_id" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">None</option>
                <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>
            <div class="flex items-center gap-2">
              <input v-model="form.is_pinned" type="checkbox" id="is-pinned" class="h-4 w-4 text-indigo-600 rounded border-gray-300" />
              <label for="is-pinned" class="text-sm text-gray-700">Pin document</label>
            </div>
          </div>

          <button
            @click="save"
            :disabled="!form.title.trim() || saving"
            class="w-full px-4 py-2.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
          >
            {{ saving ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </div>
    </template>

    <div v-else-if="error" class="rounded-md bg-red-50 border border-red-200 px-4 py-3">
      <p class="text-sm text-red-700">{{ error }}</p>
    </div>
  </div>
</template>
