<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/lib/api'

interface Proposal {
  id: number
  title: string
  status: 'draft' | 'sent' | 'accepted' | 'rejected' | 'converted'
  ai_generated: boolean
  content: string | null
  notes: string | null
  sent_at: string | null
  responded_at: string | null
  client: { id: number; name: string; company?: string } | null
  project: { id: number; name: string } | null
  invoice: { id: number; invoice_number: string; status: string; total: string } | null
  updated_at: string
}

const route = useRoute()
const router = useRouter()
const proposal = ref<Proposal | null>(null)
const loading = ref(true)
const saving = ref(false)
const converting = ref(false)
const error = ref('')
const editContent = ref('')
const editTitle = ref('')

async function fetchProposal() {
  loading.value = true
  try {
    const res = await api.get(`/proposals/${route.params.id}`)
    proposal.value = res.data
    editContent.value = res.data.content ?? ''
    editTitle.value   = res.data.title
  } catch {
    error.value = 'Failed to load proposal.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchProposal)

async function save() {
  if (!proposal.value) return
  saving.value = true
  try {
    const res = await api.put(`/proposals/${proposal.value.id}`, {
      title:   editTitle.value,
      content: editContent.value,
    })
    proposal.value = res.data
  } catch {
    error.value = 'Failed to save.'
  } finally {
    saving.value = false
  }
}

async function setStatus(status: Proposal['status']) {
  if (!proposal.value) return
  const res = await api.put(`/proposals/${proposal.value.id}`, { status })
  proposal.value = { ...proposal.value, ...res.data }
}

async function convert() {
  if (!proposal.value || converting.value) return
  converting.value = true
  try {
    const res = await api.post(`/proposals/${proposal.value.id}/convert-to-invoice`)
    router.push(`/invoices/${res.data.id}`)
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Failed to convert.'
    converting.value = false
  }
}

function statusClass(status: string) {
  return {
    draft:     'bg-gray-100 text-gray-600',
    sent:      'bg-blue-100 text-blue-700',
    accepted:  'bg-green-100 text-green-700',
    rejected:  'bg-red-100 text-red-700',
    converted: 'bg-indigo-100 text-indigo-700',
  }[status] ?? 'bg-gray-100 text-gray-600'
}

function formatDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>

<template>
  <div class="p-8">
    <button @click="router.push('/proposals')" class="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-6">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
      </svg>
      Proposals
    </button>

    <div v-if="loading" class="flex justify-center py-24">
      <svg class="animate-spin h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
      </svg>
    </div>

    <template v-else-if="proposal">
      <!-- Header -->
      <div class="flex items-start justify-between mb-6 gap-4">
        <div class="flex-1 min-w-0">
          <input
            v-model="editTitle"
            class="text-2xl font-bold text-gray-900 w-full bg-transparent border-0 border-b border-transparent hover:border-gray-300 focus:border-indigo-500 focus:outline-none pb-1 transition-colors"
          />
          <div class="flex items-center gap-3 mt-2 flex-wrap">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize" :class="statusClass(proposal.status)">{{ proposal.status }}</span>
            <span v-if="proposal.ai_generated" class="text-xs text-indigo-500 font-medium">AI Generated</span>
            <router-link v-if="proposal.client" :to="`/clients/${proposal.client.id}`" class="text-sm text-indigo-600 hover:underline">{{ proposal.client.name }}</router-link>
            <router-link v-if="proposal.project" :to="`/projects/${proposal.project.id}`" class="text-sm text-gray-500 hover:text-gray-700">{{ proposal.project.name }}</router-link>
          </div>
        </div>

        <!-- Action buttons -->
        <div class="flex items-center gap-2 flex-shrink-0 flex-wrap justify-end">
          <button v-if="proposal.status === 'draft'" @click="setStatus('sent')" class="px-3 py-2 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100">Mark Sent</button>
          <button v-if="proposal.status === 'sent'" @click="setStatus('accepted')" class="px-3 py-2 text-sm font-medium text-green-700 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100">Accept</button>
          <button v-if="proposal.status === 'sent'" @click="setStatus('rejected')" class="px-3 py-2 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100">Reject</button>
          <button
            v-if="['accepted', 'draft'].includes(proposal.status) && !proposal.invoice"
            @click="convert"
            :disabled="converting"
            class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50"
          >{{ converting ? 'Converting...' : 'Convert to Invoice' }}</button>
          <button @click="save" :disabled="saving" class="px-3 py-2 text-sm font-medium text-white bg-gray-800 rounded-lg hover:bg-gray-700 disabled:opacity-50">
            {{ saving ? 'Saving...' : 'Save' }}
          </button>
        </div>
      </div>

      <!-- Invoice link if converted -->
      <div v-if="proposal.invoice" class="mb-4 flex items-center gap-3 px-4 py-3 bg-indigo-50 rounded-lg border border-indigo-100">
        <span class="text-sm text-indigo-700">Converted to invoice:</span>
        <router-link :to="`/invoices/${proposal.invoice.id}`" class="text-sm font-medium text-indigo-700 hover:underline">
          {{ proposal.invoice.invoice_number }}
        </router-link>
        <span class="text-xs text-indigo-500 capitalize">({{ proposal.invoice.status }})</span>
      </div>

      <div v-if="error" class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ error }}</div>

      <!-- Content editor -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-4 py-2 bg-gray-50 border-b border-gray-200 flex items-center gap-2">
          <span class="text-xs text-gray-500 font-medium">Content</span>
          <span class="text-xs text-gray-400">· Markdown supported</span>
        </div>
        <textarea
          v-model="editContent"
          rows="24"
          placeholder="Write your proposal in markdown..."
          class="w-full px-4 py-4 text-sm text-gray-800 font-mono leading-relaxed resize-none focus:outline-none"
        />
      </div>

      <div class="flex justify-end mt-4">
        <button @click="save" :disabled="saving" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
          {{ saving ? 'Saving...' : 'Save Changes' }}
        </button>
      </div>
    </template>
  </div>
</template>
