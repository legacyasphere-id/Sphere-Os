<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/lib/api'

interface Client {
  id: number
  name: string
  company: string | null
}

interface Project {
  id: number
  name: string
  description: string | null
  status: 'active' | 'completed' | 'archived'
  due_date: string | null
  tasks_count: number
  completed_tasks_count: number
  client: Client | null
}

const route = useRoute()
const router = useRouter()
const projects = ref<Project[]>([])
const clients = ref<Client[]>([])
const loading = ref(true)
const error = ref('')
const activeStatus = ref<string>('all')
const showModal = ref(false)
const saving = ref(false)

const form = reactive({
  name: '',
  client_id: '' as string | number,
  description: '',
  status: 'active',
  due_date: '',
})

const statuses = ['all', 'active', 'completed', 'archived']

async function fetchProjects() {
  loading.value = true
  try {
    const params: Record<string, string> = {}
    if (activeStatus.value !== 'all') params.status = activeStatus.value
    const res = await api.get('/projects', { params })
    projects.value = res.data.data ?? res.data
  } catch {
    error.value = 'Failed to load projects.'
  } finally {
    loading.value = false
  }
}

async function fetchClients() {
  try {
    const res = await api.get('/clients', { params: { per_page: 100 } })
    clients.value = res.data.data ?? res.data
  } catch {}
}

onMounted(() => {
  fetchProjects()
  fetchClients()
})

function openModal() {
  Object.assign(form, {
    name: '',
    client_id: route.query.client_id ?? '',
    description: '',
    status: 'active',
    due_date: '',
  })
  showModal.value = true
}

async function saveProject() {
  if (!form.name.trim()) return
  saving.value = true
  try {
    await api.post('/projects', {
      name: form.name,
      client_id: form.client_id || null,
      description: form.description || null,
      status: form.status,
      due_date: form.due_date || null,
    })
    showModal.value = false
    fetchProjects()
  } catch {
    error.value = 'Failed to save project.'
  } finally {
    saving.value = false
  }
}

async function deleteProject(id: number) {
  if (!confirm('Delete this project and all its tasks?')) return
  await api.delete(`/projects/${id}`)
  projects.value = projects.value.filter(p => p.id !== id)
}

function progress(p: Project) {
  if (!p.tasks_count) return 0
  return Math.round((p.completed_tasks_count / p.tasks_count) * 100)
}

function statusClass(status: string) {
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
</script>

<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Projects</h1>
        <p class="text-sm text-gray-500 mt-1">Manage your active work</p>
      </div>
      <button @click="openModal" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
        + New Project
      </button>
    </div>

    <!-- Filter tabs -->
    <div class="flex gap-1 mb-5">
      <button
        v-for="s in statuses"
        :key="s"
        @click="activeStatus = s; fetchProjects()"
        class="px-3 py-1.5 text-sm font-medium rounded-lg transition-colors capitalize"
        :class="activeStatus === s ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100'"
      >{{ s }}</button>
    </div>

    <div v-if="error" class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg">
      <p class="text-sm text-red-700">{{ error }}</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div v-if="loading" class="flex items-center justify-center py-16">
        <svg class="animate-spin h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      </div>

      <div v-else-if="projects.length === 0" class="py-16 text-center">
        <p class="text-gray-400 text-sm">No projects found.</p>
        <button @click="openModal" class="mt-3 text-sm text-indigo-600 hover:text-indigo-500 font-medium">Create your first project</button>
      </div>

      <table v-else class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200">
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Project</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Client</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Progress</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Due Date</th>
            <th class="px-6 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr
            v-for="project in projects"
            :key="project.id"
            @click="router.push(`/projects/${project.id}`)"
            class="hover:bg-gray-50 cursor-pointer transition-colors"
          >
            <td class="px-6 py-4">
              <p class="font-medium text-gray-900">{{ project.name }}</p>
              <p v-if="project.description" class="text-xs text-gray-400 mt-0.5 truncate max-w-xs">{{ project.description }}</p>
            </td>
            <td class="px-6 py-4">
              <span v-if="project.client" class="text-gray-600">{{ project.client.name }}</span>
              <span v-else class="text-gray-300">—</span>
            </td>
            <td class="px-6 py-4">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize" :class="statusClass(project.status)">
                {{ project.status }}
              </span>
            </td>
            <td class="px-6 py-4">
              <div v-if="project.tasks_count > 0" class="flex items-center gap-2">
                <div class="w-24 bg-gray-200 rounded-full h-1.5">
                  <div class="bg-indigo-500 h-1.5 rounded-full" :style="{ width: progress(project) + '%' }" />
                </div>
                <span class="text-xs text-gray-500">{{ project.completed_tasks_count }}/{{ project.tasks_count }}</span>
              </div>
              <span v-else class="text-xs text-gray-300">No tasks</span>
            </td>
            <td class="px-6 py-4 text-gray-500">{{ formatDate(project.due_date) }}</td>
            <td class="px-6 py-4 text-right">
              <button
                @click.stop="deleteProject(project.id)"
                class="text-gray-400 hover:text-red-500 transition-colors p-1 rounded"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- New Project Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="showModal = false" />
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6 z-10">
          <h2 class="text-lg font-semibold text-gray-900 mb-5">New Project</h2>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Project Name <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g. Website Redesign" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Client</label>
              <select v-model="form.client_id" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">No client</option>
                <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}{{ c.company ? ` — ${c.company}` : '' }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea v-model="form.description" rows="2" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select v-model="form.status" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option value="active">Active</option>
                  <option value="completed">Completed</option>
                  <option value="archived">Archived</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                <input v-model="form.due_date" type="date" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button @click="showModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
            <button @click="saveProject" :disabled="!form.name.trim() || saving" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
              {{ saving ? 'Saving...' : 'Create Project' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
