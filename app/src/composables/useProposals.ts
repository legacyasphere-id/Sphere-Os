import { ref } from 'vue'
import api from '@/lib/api'

export interface Proposal {
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
  created_at: string
  updated_at: string
}

export function useProposals() {
  const loading = ref(false)
  const error = ref('')

  async function saveAsDraft(projectId: number, clientId: number | null, title: string, content: string): Promise<Proposal> {
    const res = await api.post('/proposals', {
      project_id:   projectId,
      client_id:    clientId,
      title,
      content,
      status:       'draft',
      ai_generated: true,
    })
    return res.data
  }

  async function updateStatus(id: number, status: Proposal['status']): Promise<Proposal> {
    const res = await api.put(`/proposals/${id}`, { status })
    return res.data
  }

  async function convertToInvoice(id: number): Promise<any> {
    const res = await api.post(`/proposals/${id}/convert-to-invoice`)
    return res.data
  }

  async function remove(id: number): Promise<void> {
    await api.delete(`/proposals/${id}`)
  }

  return { loading, error, saveAsDraft, updateStatus, convertToInvoice, remove }
}
