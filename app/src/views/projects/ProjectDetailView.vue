<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/lib/api'
import { aiEnabled, useBreakTasks, useGenerateProposal } from '@/composables/useAI'

interface Task {
  id: number
  title: string
  description: string | null
  status: 'todo' | 'in_progress' | 'done'
  priority: 'low' | 'medium' | 'high'
  due_date: string | null
}

interface Project {
  id: number
  name: string
  description: string | null
  status: 'active' | 'completed' | 'archived'
  due_date: string | null
  client: { id: number; name: string; company: string | null } | null
  tasks: Task[]
}

const route = useRoute()
const router = useRouter()
const project = ref<Project | null>(null)
const loading = ref(true)
const error = ref('')
const showEditModal = ref(false)
const showTaskModal = ref(false)
const saving = ref(false)
const editingTask = ref<Task | null>(null)

// AI
const showBreakTasksModal = ref(false)
const breakTasksFocus = ref('')
const breakTasksSelectedIds = ref<number[]>([])
const breakTasksAdding = ref(false)
const breakTasks = useBreakTasks()

const showProposalPanel = ref(false)
const proposalTone = ref<'professional' | 'casual' | 'formal'>('professional')
const proposal = useGenerateProposal()
const savingDraft = ref(false)
const savedDraftId = ref<number | null>(null)

const editForm = reactive({
  name: '',
  description: '',
  status: 'active',
  due_date: '',
})

const taskForm = reactive({
  title: '',
  description: '',
  status: 'todo' as string,
  priority: 'medium' as string,
  due_date: '',
})

async function fetchProject() {
  loading.value = true
  try {
    const res = await api.get(`/projects/${route.params.id}`)
    project.value = res.data
  } catch {
    error.value = 'Failed to load project.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchProject)

function openEditProject() {
  if (!project.value) return
  Object.assign(editForm, {
    name: project.value.name,
    description: project.value.description ?? '',
    status: project.value.status,
    due_date: project.value.due_date ?? '',
  })
  showEditModal.value = true
}

async function saveProject() {
  saving.value = true
  try {
    const res = await api.put(`/projects/${route.params.id}`, {
      ...editForm,
      due_date: editForm.due_date || null,
    })
    project.value = { ...project.value!, ...res.data }
    showEditModal.value = false
  } catch {
    error.value = 'Failed to update project.'
  } finally {
    saving.value = false
  }
}

function openAddTask() {
  editingTask.value = null
  Object.assign(taskForm, { title: '', description: '', status: 'todo', priority: 'medium', due_date: '' })
  showTaskModal.value = true
}

function openEditTask(task: Task) {
  editingTask.value = task
  Object.assign(taskForm, {
    title: task.title,
    description: task.description ?? '',
    status: task.status,
    priority: task.priority,
    due_date: task.due_date ?? '',
  })
  showTaskModal.value = true
}

async function saveTask() {
  if (!taskForm.title.trim()) return
  saving.value = true
  try {
    const payload = {
      title: taskForm.title,
      description: taskForm.description || null,
      status: taskForm.status,
      priority: taskForm.priority,
      due_date: taskForm.due_date || null,
    }
    if (editingTask.value) {
      const res = await api.put(`/tasks/${editingTask.value.id}`, payload)
      const idx = project.value!.tasks.findIndex(t => t.id === editingTask.value!.id)
      if (idx !== -1) project.value!.tasks[idx] = res.data
    } else {
      const res = await api.post(`/projects/${route.params.id}/tasks`, payload)
      project.value!.tasks.push(res.data)
    }
    showTaskModal.value = false
  } catch {
    error.value = 'Failed to save task.'
  } finally {
    saving.value = false
  }
}

async function cycleStatus(task: Task) {
  const next: Record<string, Task['status']> = { todo: 'in_progress', in_progress: 'done', done: 'todo' }
  const newStatus = next[task.status]
  const res = await api.put(`/tasks/${task.id}`, { status: newStatus })
  const idx = project.value!.tasks.findIndex(t => t.id === task.id)
  if (idx !== -1) project.value!.tasks[idx] = res.data
}

async function deleteTask(id: number) {
  if (!confirm('Delete this task?')) return
  await api.delete(`/tasks/${id}`)
  project.value!.tasks = project.value!.tasks.filter(t => t.id !== id)
}

const todoTasks = computed(() => project.value?.tasks.filter(t => t.status === 'todo') ?? [])
const inProgressTasks = computed(() => project.value?.tasks.filter(t => t.status === 'in_progress') ?? [])
const doneTasks = computed(() => project.value?.tasks.filter(t => t.status === 'done') ?? [])

const completionPercent = computed(() => {
  const tasks = project.value?.tasks
  if (!tasks?.length) return 0
  return Math.round((tasks.filter(t => t.status === 'done').length / tasks.length) * 100)
})

function taskStatusClass(status: string) {
  return {
    todo: 'bg-gray-100 text-gray-600 hover:bg-gray-200',
    in_progress: 'bg-blue-100 text-blue-700 hover:bg-blue-200',
    done: 'bg-green-100 text-green-700 hover:bg-green-200',
  }[status] ?? 'bg-gray-100 text-gray-600'
}

function taskStatusLabel(status: string) {
  return { todo: 'To Do', in_progress: 'In Progress', done: 'Done' }[status] ?? status
}

function priorityClass(p: string) {
  return { high: 'bg-red-100 text-red-700', medium: 'bg-yellow-100 text-yellow-700', low: 'bg-gray-100 text-gray-500' }[p] ?? 'bg-gray-100 text-gray-500'
}

function projectStatusClass(status: string) {
  return { active: 'bg-indigo-100 text-indigo-700', completed: 'bg-green-100 text-green-700', archived: 'bg-gray-100 text-gray-600' }[status] ?? 'bg-gray-100 text-gray-600'
}

function formatDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function isOverdue(d: string | null, status: string) {
  if (!d || status === 'done') return false
  return new Date(d) < new Date()
}

function openBreakTasksModal() {
  breakTasksFocus.value = ''
  breakTasks.reset()
  breakTasksSelectedIds.value = []
  showBreakTasksModal.value = true
}

async function runBreakTasks() {
  if (!project.value) return
  await breakTasks.generate(project.value.id, breakTasksFocus.value)
  breakTasksSelectedIds.value = breakTasks.tasks.value.map((_, i) => i)
}

async function addSelectedTasks() {
  if (!project.value || breakTasksAdding.value) return
  breakTasksAdding.value = true
  const selected = breakTasks.tasks.value.filter((_, i) => breakTasksSelectedIds.value.includes(i))
  try {
    for (const t of selected) {
      const res = await api.post(`/projects/${project.value!.id}/tasks`, {
        title: t.title,
        description: t.description,
        priority: t.priority,
        status: 'todo',
        ai_generated: true,
      })
      project.value!.tasks.push(res.data)
    }
    showBreakTasksModal.value = false
  } catch {
    error.value = 'Failed to add tasks.'
  } finally {
    breakTasksAdding.value = false
  }
}

function toggleBreakTaskSelection(i: number) {
  const idx = breakTasksSelectedIds.value.indexOf(i)
  if (idx !== -1) {
    breakTasksSelectedIds.value.splice(idx, 1)
  } else {
    breakTasksSelectedIds.value.push(i)
  }
}

function openProposalPanel() {
  proposal.reset()
  proposalTone.value = 'professional'
  showProposalPanel.value = true
}

async function runGenerateProposal() {
  if (!project.value) return
  savedDraftId.value = null
  await proposal.generate(project.value.id, proposalTone.value)
}

async function saveProposalAsDraft() {
  if (!project.value || !proposal.proposal.value) return
  savingDraft.value = true
  try {
    const res = await api.post('/proposals', {
      title:        `Proposal — ${project.value.name}`,
      project_id:   project.value.id,
      client_id:    project.value.client?.id ?? null,
      content:      proposal.proposal.value,
      ai_generated: true,
    })
    savedDraftId.value = res.data.id
  } catch {
    error.value = 'Failed to save proposal.'
  } finally {
    savingDraft.value = false
  }
}
</script>

<template>
  <div class="p-8">
    <button @click="router.push('/projects')" class="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-6">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
      </svg>
      Projects
    </button>

    <div v-if="loading" class="flex items-center justify-center py-24">
      <svg class="animate-spin h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
    </div>

    <template v-else-if="project">
      <!-- Header -->
      <div class="flex items-start justify-between mb-6">
        <div>
          <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-2xl font-bold text-gray-900">{{ project.name }}</h1>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize" :class="projectStatusClass(project.status)">
              {{ project.status }}
            </span>
            <router-link
              v-if="project.client"
              :to="`/clients/${project.client.id}`"
              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 hover:bg-gray-200"
            >
              {{ project.client.name }}
            </router-link>
          </div>
          <div class="flex items-center gap-4 mt-2">
            <p v-if="project.description" class="text-sm text-gray-500">{{ project.description }}</p>
            <span v-if="project.due_date" class="text-sm text-gray-400">Due {{ formatDate(project.due_date) }}</span>
          </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <button @click="openEditProject" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Edit</button>
          <template v-if="aiEnabled">
            <button @click="openBreakTasksModal" class="px-3 py-2 text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100">Break Down Tasks</button>
            <button @click="openProposalPanel" class="px-3 py-2 text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100">Generate Proposal</button>
          </template>
          <button @click="openAddTask" class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">+ Add Task</button>
        </div>
      </div>

      <!-- Progress bar -->
      <div v-if="project.tasks.length > 0" class="mb-6 bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium text-gray-700">Progress</span>
          <span class="text-sm font-semibold text-gray-900">{{ completionPercent }}%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
          <div class="bg-indigo-500 h-2 rounded-full transition-all duration-300" :style="{ width: completionPercent + '%' }" />
        </div>
        <div class="flex gap-4 mt-3 text-xs text-gray-500">
          <span>{{ todoTasks.length }} to do</span>
          <span>{{ inProgressTasks.length }} in progress</span>
          <span>{{ doneTasks.length }} done</span>
        </div>
      </div>

      <!-- Task board (three columns) -->
      <div v-if="project.tasks.length === 0" class="bg-white rounded-xl border border-gray-200 py-16 text-center">
        <p class="text-gray-400 text-sm">No tasks yet.</p>
        <button @click="openAddTask" class="mt-3 text-sm text-indigo-600 hover:text-indigo-500 font-medium">Add your first task</button>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- To Do -->
        <div class="bg-gray-50 rounded-xl p-4">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">To Do ({{ todoTasks.length }})</h3>
          <div class="space-y-2">
            <div
              v-for="task in todoTasks"
              :key="task.id"
              class="bg-white rounded-lg border border-gray-200 p-3 group"
            >
              <div class="flex items-start justify-between gap-2">
                <p class="text-sm font-medium text-gray-900 flex-1">{{ task.title }}</p>
                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0">
                  <button @click="openEditTask(task)" class="p-1 text-gray-400 hover:text-indigo-500 rounded">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>
                  <button @click="deleteTask(task.id)" class="p-1 text-gray-400 hover:text-red-500 rounded">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>
              <div class="flex items-center gap-2 mt-2">
                <button @click="cycleStatus(task)" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium transition-colors cursor-pointer" :class="taskStatusClass(task.status)">
                  {{ taskStatusLabel(task.status) }}
                </button>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium capitalize" :class="priorityClass(task.priority)">{{ task.priority }}</span>
              </div>
              <p v-if="task.due_date" class="text-xs mt-1.5" :class="isOverdue(task.due_date, task.status) ? 'text-red-500 font-medium' : 'text-gray-400'">
                {{ isOverdue(task.due_date, task.status) ? 'Overdue · ' : '' }}{{ formatDate(task.due_date) }}
              </p>
            </div>
          </div>
        </div>

        <!-- In Progress -->
        <div class="bg-blue-50 rounded-xl p-4">
          <h3 class="text-xs font-semibold text-blue-600 uppercase tracking-wide mb-3">In Progress ({{ inProgressTasks.length }})</h3>
          <div class="space-y-2">
            <div
              v-for="task in inProgressTasks"
              :key="task.id"
              class="bg-white rounded-lg border border-gray-200 p-3 group"
            >
              <div class="flex items-start justify-between gap-2">
                <p class="text-sm font-medium text-gray-900 flex-1">{{ task.title }}</p>
                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0">
                  <button @click="openEditTask(task)" class="p-1 text-gray-400 hover:text-indigo-500 rounded">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>
                  <button @click="deleteTask(task.id)" class="p-1 text-gray-400 hover:text-red-500 rounded">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>
              <div class="flex items-center gap-2 mt-2">
                <button @click="cycleStatus(task)" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium transition-colors cursor-pointer" :class="taskStatusClass(task.status)">
                  {{ taskStatusLabel(task.status) }}
                </button>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium capitalize" :class="priorityClass(task.priority)">{{ task.priority }}</span>
              </div>
              <p v-if="task.due_date" class="text-xs mt-1.5" :class="isOverdue(task.due_date, task.status) ? 'text-red-500 font-medium' : 'text-gray-400'">
                {{ isOverdue(task.due_date, task.status) ? 'Overdue · ' : '' }}{{ formatDate(task.due_date) }}
              </p>
            </div>
          </div>
        </div>

        <!-- Done -->
        <div class="bg-green-50 rounded-xl p-4">
          <h3 class="text-xs font-semibold text-green-600 uppercase tracking-wide mb-3">Done ({{ doneTasks.length }})</h3>
          <div class="space-y-2">
            <div
              v-for="task in doneTasks"
              :key="task.id"
              class="bg-white rounded-lg border border-gray-200 p-3 group opacity-75"
            >
              <div class="flex items-start justify-between gap-2">
                <p class="text-sm font-medium text-gray-500 line-through flex-1">{{ task.title }}</p>
                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0">
                  <button @click="cycleStatus(task)" class="p-1 text-gray-400 hover:text-indigo-500 rounded" title="Move back to To Do">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                    </svg>
                  </button>
                  <button @click="deleteTask(task.id)" class="p-1 text-gray-400 hover:text-red-500 rounded">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>
              <button @click="cycleStatus(task)" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium transition-colors cursor-pointer mt-2" :class="taskStatusClass(task.status)">
                {{ taskStatusLabel(task.status) }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Edit Project Modal -->
    <Teleport to="body">
      <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="showEditModal = false" />
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6 z-10">
          <h2 class="text-lg font-semibold text-gray-900 mb-5">Edit Project</h2>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
              <input v-model="editForm.name" type="text" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea v-model="editForm.description" rows="2" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select v-model="editForm.status" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option value="active">Active</option>
                  <option value="completed">Completed</option>
                  <option value="archived">Archived</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                <input v-model="editForm.due_date" type="date" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button @click="showEditModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
            <button @click="saveProject" :disabled="saving" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
              {{ saving ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Break Down Tasks Modal -->
    <Teleport to="body">
      <div v-if="showBreakTasksModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="showBreakTasksModal = false" />
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl p-6 z-10 max-h-[90vh] overflow-y-auto">
          <h2 class="text-lg font-semibold text-gray-900 mb-1">Break Down Tasks</h2>
          <p class="text-sm text-gray-500 mb-5">AI will suggest actionable tasks for this project.</p>

          <div v-if="!breakTasks.tasks.value.length" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Focus area (optional)</label>
              <input v-model="breakTasksFocus" type="text" placeholder="e.g. backend API, design system" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div v-if="breakTasks.error.value" class="text-sm text-red-600 bg-red-50 rounded-lg px-4 py-3">{{ breakTasks.error.value }}</div>
            <div class="flex justify-end gap-3">
              <button @click="showBreakTasksModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
              <button @click="runBreakTasks" :disabled="breakTasks.loading.value" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                {{ breakTasks.loading.value ? 'Generating...' : 'Generate Tasks' }}
              </button>
            </div>
          </div>

          <div v-else class="space-y-3">
            <p class="text-sm text-gray-600 font-medium mb-2">Select tasks to add:</p>
            <div v-for="(task, i) in breakTasks.tasks.value" :key="i" class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors" :class="breakTasksSelectedIds.includes(i) ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 bg-white'" @click="toggleBreakTaskSelection(i)">
              <input type="checkbox" :checked="breakTasksSelectedIds.includes(i)" class="mt-0.5 h-4 w-4 text-indigo-600 rounded border-gray-300 cursor-pointer" @click.stop="toggleBreakTaskSelection(i)" />
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900">{{ task.title }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ task.description }}</p>
                <div class="flex items-center gap-2 mt-1.5">
                  <span class="text-xs px-1.5 py-0.5 rounded font-medium capitalize" :class="{ high: 'bg-red-100 text-red-700', medium: 'bg-yellow-100 text-yellow-700', low: 'bg-gray-100 text-gray-500' }[task.priority]">{{ task.priority }}</span>
                  <span class="text-xs text-gray-400">~{{ task.estimated_days }}d</span>
                </div>
              </div>
            </div>

            <div v-if="breakTasks.meta.value" class="text-xs text-gray-400 pt-1">
              {{ breakTasks.tasks.value.length }} tasks · {{ breakTasks.meta.value.tokens_used }} tokens · ${{ breakTasks.meta.value.cost_usd.toFixed(4) }}
            </div>

            <div class="flex justify-between items-center pt-2">
              <button @click="breakTasks.reset(); breakTasksSelectedIds = []" class="text-sm text-gray-500 hover:text-gray-700">Try again</button>
              <div class="flex gap-3">
                <button @click="showBreakTasksModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Discard</button>
                <button @click="addSelectedTasks" :disabled="breakTasksSelectedIds.length === 0 || breakTasksAdding" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                  {{ breakTasksAdding ? 'Adding...' : `Add ${breakTasksSelectedIds.length} Task${breakTasksSelectedIds.length !== 1 ? 's' : ''}` }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Generate Proposal Panel -->
    <Teleport to="body">
      <div v-if="showProposalPanel" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="showProposalPanel = false" />
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl p-6 z-10 max-h-[90vh] overflow-y-auto">
          <h2 class="text-lg font-semibold text-gray-900 mb-1">Generate Proposal</h2>
          <p class="text-sm text-gray-500 mb-5">AI will write a project proposal based on your project details.</p>

          <div v-if="!proposal.proposal.value" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Tone</label>
              <select v-model="proposalTone" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="professional">Professional</option>
                <option value="casual">Casual</option>
                <option value="formal">Formal</option>
              </select>
            </div>
            <div v-if="proposal.error.value" class="text-sm text-red-600 bg-red-50 rounded-lg px-4 py-3">{{ proposal.error.value }}</div>
            <div class="flex justify-end gap-3">
              <button @click="showProposalPanel = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
              <button @click="runGenerateProposal" :disabled="proposal.loading.value" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                {{ proposal.loading.value ? 'Generating...' : 'Generate Proposal' }}
              </button>
            </div>
          </div>

          <div v-else class="space-y-4">
            <div v-if="proposal.meta.value" class="text-xs text-gray-400">
              {{ proposal.meta.value.model }} · {{ proposal.meta.value.tokens_used }} tokens · ${{ proposal.meta.value.cost_usd.toFixed(4) }} · {{ proposal.meta.value.latency_ms }}ms
            </div>
            <div class="bg-gray-50 rounded-lg border border-gray-200 p-4">
              <pre class="text-sm text-gray-800 whitespace-pre-wrap font-sans">{{ proposal.proposal.value }}</pre>
            </div>
            <div v-if="savedDraftId" class="flex items-center gap-2 text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-4 py-2.5">
              <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
              </svg>
              <span>Saved as draft. </span>
              <router-link :to="`/proposals/${savedDraftId}`" class="font-medium underline hover:text-green-800">View proposal →</router-link>
            </div>
            <div class="flex justify-between items-center">
              <button @click="proposal.reset(); savedDraftId = null" class="text-sm text-gray-500 hover:text-gray-700">Regenerate</button>
              <div class="flex gap-3">
                <button
                  v-if="!savedDraftId"
                  @click="saveProposalAsDraft"
                  :disabled="savingDraft"
                  class="px-4 py-2 text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 disabled:opacity-50"
                >
                  {{ savingDraft ? 'Saving...' : 'Save as Draft' }}
                </button>
                <button @click="showProposalPanel = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Close</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Add/Edit Task Modal -->
    <Teleport to="body">
      <div v-if="showTaskModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="showTaskModal = false" />
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6 z-10">
          <h2 class="text-lg font-semibold text-gray-900 mb-5">{{ editingTask ? 'Edit Task' : 'Add Task' }}</h2>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
              <input v-model="taskForm.title" type="text" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea v-model="taskForm.description" rows="2" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
            </div>
            <div class="grid grid-cols-3 gap-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select v-model="taskForm.status" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option value="todo">To Do</option>
                  <option value="in_progress">In Progress</option>
                  <option value="done">Done</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                <select v-model="taskForm.priority" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                <input v-model="taskForm.due_date" type="date" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button @click="showTaskModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
            <button @click="saveTask" :disabled="!taskForm.title.trim() || saving" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
              {{ saving ? 'Saving...' : editingTask ? 'Save Changes' : 'Add Task' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
