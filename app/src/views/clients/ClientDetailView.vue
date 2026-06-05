<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/lib/api'
import { aiEnabled, useSummarizeClient } from '@/composables/useAI'

interface Contact {
  id: number
  name: string
  email: string | null
  phone: string | null
  role: string | null
  notes: string | null
}

interface Project {
  id: number
  name: string
  status: string
  due_date: string | null
}

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
  contacts: Contact[]
  projects: Project[]
}

const route = useRoute()
const router = useRouter()
const client = ref<Client | null>(null)
const loading = ref(true)
const error = ref('')
const activeTab = ref<'overview' | 'contacts' | 'projects'>('overview')
const showEditModal = ref(false)
const showContactModal = ref(false)
const saving = ref(false)

const editForm = reactive<Partial<Client>>({})
const contactForm = reactive({ name: '', email: '', phone: '', role: '', notes: '' })

// AI
const showSummaryModal = ref(false)
const summarize = useSummarizeClient()

async function fetchClient() {
  loading.value = true
  try {
    const res = await api.get(`/clients/${route.params.id}`)
    client.value = res.data
  } catch {
    error.value = 'Failed to load client.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchClient)

function openEdit() {
  if (!client.value) return
  Object.assign(editForm, {
    name: client.value.name,
    company: client.value.company ?? '',
    email: client.value.email ?? '',
    phone: client.value.phone ?? '',
    status: client.value.status,
    next_action_date: client.value.next_action_date ?? '',
    next_action_note: client.value.next_action_note ?? '',
    notes: client.value.notes ?? '',
  })
  showEditModal.value = true
}

async function saveEdit() {
  saving.value = true
  try {
    const res = await api.put(`/clients/${route.params.id}`, {
      ...editForm,
      next_action_date: editForm.next_action_date || null,
    })
    client.value = { ...client.value!, ...res.data }
    showEditModal.value = false
  } catch {
    error.value = 'Failed to update client.'
  } finally {
    saving.value = false
  }
}

function openContactModal() {
  Object.assign(contactForm, { name: '', email: '', phone: '', role: '', notes: '' })
  showContactModal.value = true
}

async function saveContact() {
  if (!contactForm.name.trim()) return
  saving.value = true
  try {
    const res = await api.post(`/clients/${route.params.id}/contacts`, contactForm)
    client.value!.contacts.push(res.data)
    showContactModal.value = false
  } catch {
    error.value = 'Failed to save contact.'
  } finally {
    saving.value = false
  }
}

async function deleteContact(id: number) {
  if (!confirm('Delete this contact?')) return
  await api.delete(`/contacts/${id}`)
  client.value!.contacts = client.value!.contacts.filter(c => c.id !== id)
}

function statusClass(status: string) {
  return {
    lead: 'bg-gray-100 text-gray-600',
    prospect: 'bg-blue-100 text-blue-700',
    client: 'bg-green-100 text-green-700',
    lost: 'bg-red-100 text-red-700',
  }[status] ?? 'bg-gray-100 text-gray-600'
}

function projectStatusClass(status: string) {
  return {
    active: 'bg-indigo-100 text-indigo-700',
    completed: 'bg-green-100 text-green-700',
    archived: 'bg-gray-100 text-gray-600',
  }[status] ?? 'bg-gray-100 text-gray-600'
}

function formatDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

async function openSummaryModal() {
  summarize.reset()
  showSummaryModal.value = true
  if (client.value) {
    await summarize.generate(client.value.id)
  }
}
</script>

<template>
  <div class="p-8">
    <!-- Back -->
    <button @click="router.push('/clients')" class="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-6">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
      </svg>
      Clients
    </button>

    <div v-if="loading" class="flex items-center justify-center py-24">
      <svg class="animate-spin h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
    </div>

    <template v-else-if="client">
      <!-- Header -->
      <div class="flex items-start justify-between mb-6">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center text-lg font-bold text-indigo-700">
            {{ client.name.charAt(0).toUpperCase() }}
          </div>
          <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ client.name }}</h1>
            <p v-if="client.company" class="text-sm text-gray-500">{{ client.company }}</p>
          </div>
          <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium capitalize" :class="statusClass(client.status)">
            {{ client.status }}
          </span>
        </div>
        <div class="flex items-center gap-2">
          <button v-if="aiEnabled" @click="openSummaryModal" class="px-3 py-2 text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100">Summarize Client</button>
          <button @click="openEdit" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">Edit</button>
        </div>
      </div>

      <!-- Tabs -->
      <div class="border-b border-gray-200 mb-6">
        <nav class="flex gap-6">
          <button
            v-for="tab in (['overview', 'contacts', 'projects'] as const)"
            :key="tab"
            @click="activeTab = tab"
            class="pb-3 text-sm font-medium capitalize border-b-2 transition-colors"
            :class="activeTab === tab ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
          >{{ tab }}</button>
        </nav>
      </div>

      <!-- Overview Tab -->
      <div v-if="activeTab === 'overview'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
          <h3 class="text-sm font-semibold text-gray-900">Contact Info</h3>
          <div class="space-y-3">
            <div>
              <p class="text-xs text-gray-500">Email</p>
              <p class="text-sm text-gray-900">{{ client.email ?? '—' }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Phone</p>
              <p class="text-sm text-gray-900">{{ client.phone ?? '—' }}</p>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
          <h3 class="text-sm font-semibold text-gray-900">Next Action</h3>
          <div class="space-y-3">
            <div>
              <p class="text-xs text-gray-500">Date</p>
              <p class="text-sm text-gray-900">{{ formatDate(client.next_action_date) }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Note</p>
              <p class="text-sm text-gray-900">{{ client.next_action_note ?? '—' }}</p>
            </div>
          </div>
        </div>
        <div v-if="client.notes" class="md:col-span-2 bg-white rounded-xl border border-gray-200 p-6">
          <h3 class="text-sm font-semibold text-gray-900 mb-2">Notes</h3>
          <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ client.notes }}</p>
        </div>
      </div>

      <!-- Contacts Tab -->
      <div v-if="activeTab === 'contacts'">
        <div class="flex justify-between items-center mb-4">
          <p class="text-sm text-gray-500">{{ client.contacts.length }} contact{{ client.contacts.length !== 1 ? 's' : '' }}</p>
          <button @click="openContactModal" class="px-3 py-1.5 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
            + Add Contact
          </button>
        </div>
        <div v-if="client.contacts.length === 0" class="bg-white rounded-xl border border-gray-200 py-12 text-center">
          <p class="text-sm text-gray-400">No contacts yet.</p>
        </div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="contact in client.contacts"
            :key="contact.id"
            class="bg-white rounded-xl border border-gray-200 p-4 relative group"
          >
            <div class="flex items-start justify-between">
              <div>
                <p class="font-medium text-gray-900 text-sm">{{ contact.name }}</p>
                <p v-if="contact.role" class="text-xs text-indigo-600 mt-0.5">{{ contact.role }}</p>
              </div>
              <button @click="deleteContact(contact.id)" class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-red-500 transition-all p-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <div class="mt-3 space-y-1">
              <p v-if="contact.email" class="text-xs text-gray-500">{{ contact.email }}</p>
              <p v-if="contact.phone" class="text-xs text-gray-500">{{ contact.phone }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Projects Tab -->
      <div v-if="activeTab === 'projects'">
        <div class="flex justify-between items-center mb-4">
          <p class="text-sm text-gray-500">{{ client.projects.length }} project{{ client.projects.length !== 1 ? 's' : '' }}</p>
          <button @click="router.push({ name: 'projects', query: { client_id: client.id } })" class="px-3 py-1.5 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
            + New Project
          </button>
        </div>
        <div v-if="client.projects.length === 0" class="bg-white rounded-xl border border-gray-200 py-12 text-center">
          <p class="text-sm text-gray-400">No projects linked.</p>
        </div>
        <div v-else class="space-y-3">
          <div
            v-for="project in client.projects"
            :key="project.id"
            @click="router.push(`/projects/${project.id}`)"
            class="bg-white rounded-xl border border-gray-200 p-4 flex items-center justify-between hover:border-indigo-300 cursor-pointer transition-colors"
          >
            <p class="font-medium text-sm text-gray-900">{{ project.name }}</p>
            <div class="flex items-center gap-3">
              <span v-if="project.due_date" class="text-xs text-gray-400">Due {{ formatDate(project.due_date) }}</span>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize" :class="projectStatusClass(project.status)">
                {{ project.status }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- AI Summarize Modal -->
    <Teleport to="body">
      <div v-if="showSummaryModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="showSummaryModal = false" />
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg p-6 z-10">
          <h2 class="text-lg font-semibold text-gray-900 mb-1">Client Summary</h2>
          <p class="text-sm text-gray-500 mb-5">AI-generated snapshot of your client relationship.</p>

          <div v-if="summarize.loading.value" class="flex items-center gap-3 py-6 text-gray-500">
            <svg class="animate-spin h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <span class="text-sm">Generating summary...</span>
          </div>

          <div v-else-if="summarize.error.value" class="text-sm text-red-600 bg-red-50 rounded-lg px-4 py-3 mb-4">
            {{ summarize.error.value }}
          </div>

          <div v-else-if="summarize.summary.value" class="space-y-4">
            <div class="bg-gray-50 rounded-lg border border-gray-200 p-4">
              <p class="text-sm text-gray-800 leading-relaxed">{{ summarize.summary.value }}</p>
            </div>
            <div v-if="summarize.meta.value" class="text-xs text-gray-400">
              {{ summarize.meta.value.model }} · {{ summarize.meta.value.tokens_used }} tokens · ${{ summarize.meta.value.cost_usd.toFixed(4) }}
            </div>
          </div>

          <div class="flex justify-end gap-3 mt-6">
            <button v-if="summarize.summary.value && !summarize.loading.value" @click="summarize.generate(client!.id)" class="text-sm text-gray-500 hover:text-gray-700 mr-auto">Regenerate</button>
            <button @click="showSummaryModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Close</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Edit Client Modal -->
    <Teleport to="body">
      <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="showEditModal = false" />
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg p-6 z-10">
          <h2 class="text-lg font-semibold text-gray-900 mb-5">Edit Client</h2>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
              <input v-model="editForm.name" type="text" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Company</label>
                <input v-model="editForm.company" type="text" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select v-model="editForm.status" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
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
                <input v-model="editForm.email" type="email" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                <input v-model="editForm.phone" type="tel" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Next Action Date</label>
                <input v-model="editForm.next_action_date" type="date" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Action Note</label>
                <input v-model="editForm.next_action_note" type="text" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
              <textarea v-model="editForm.notes" rows="3" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button @click="showEditModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
            <button @click="saveEdit" :disabled="saving" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
              {{ saving ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Add Contact Modal -->
    <Teleport to="body">
      <div v-if="showContactModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="showContactModal = false" />
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6 z-10">
          <h2 class="text-lg font-semibold text-gray-900 mb-5">Add Contact</h2>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
              <input v-model="contactForm.name" type="text" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input v-model="contactForm.email" type="email" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                <input v-model="contactForm.phone" type="tel" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Role / Title</label>
              <input v-model="contactForm.role" type="text" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button @click="showContactModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
            <button @click="saveContact" :disabled="!contactForm.name.trim() || saving" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
              {{ saving ? 'Saving...' : 'Add Contact' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
