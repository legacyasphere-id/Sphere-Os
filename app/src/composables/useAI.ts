import { ref } from 'vue'
import api from '@/lib/api'

export const aiEnabled = import.meta.env.VITE_FEATURE_AI === 'true'

export interface AIMeta {
  model: string
  tokens_used: number
  cost_usd: number
  latency_ms: number
}

export interface AITask {
  title: string
  description: string
  priority: 'low' | 'medium' | 'high'
  estimated_days: number
}

export function useBreakTasks() {
  const loading = ref(false)
  const error = ref('')
  const tasks = ref<AITask[]>([])
  const meta = ref<AIMeta | null>(null)

  async function generate(projectId: number, focus = '') {
    loading.value = true
    error.value = ''
    tasks.value = []
    meta.value = null

    try {
      const res = await api.post('/ai/break-tasks', { project_id: projectId, focus: focus || undefined })
      tasks.value = Array.isArray(res.data.tasks) ? res.data.tasks : []
      meta.value = res.data.meta ?? null
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'AI service unavailable. Please try again.'
    } finally {
      loading.value = false
    }
  }

  function reset() {
    tasks.value = []
    error.value = ''
    meta.value = null
  }

  return { loading, error, tasks, meta, generate, reset }
}

export function useGenerateProposal() {
  const loading = ref(false)
  const error = ref('')
  const proposal = ref('')
  const meta = ref<AIMeta | null>(null)

  async function generate(projectId: number, tone = 'professional') {
    loading.value = true
    error.value = ''
    proposal.value = ''
    meta.value = null

    try {
      const res = await api.post('/ai/generate-proposal', { project_id: projectId, tone })
      proposal.value = res.data.proposal ?? ''
      meta.value = res.data.meta ?? null
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'AI service unavailable. Please try again.'
    } finally {
      loading.value = false
    }
  }

  function reset() {
    proposal.value = ''
    error.value = ''
    meta.value = null
  }

  return { loading, error, proposal, meta, generate, reset }
}

export function useSummarizeClient() {
  const loading = ref(false)
  const error = ref('')
  const summary = ref('')
  const meta = ref<AIMeta | null>(null)

  async function generate(clientId: number) {
    loading.value = true
    error.value = ''
    summary.value = ''
    meta.value = null

    try {
      const res = await api.post('/ai/summarize', { client_id: clientId })
      summary.value = res.data.summary ?? ''
      meta.value = res.data.meta ?? null
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'AI service unavailable. Please try again.'
    } finally {
      loading.value = false
    }
  }

  function reset() {
    summary.value = ''
    error.value = ''
    meta.value = null
  }

  return { loading, error, summary, meta, generate, reset }
}
