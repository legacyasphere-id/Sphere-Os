import { ref } from 'vue'
import api from '@/lib/api'

export interface InvoiceItem {
  id?: number
  description: string
  quantity: number
  unit_price: number
  amount: number
  sort_order: number
}

export interface Invoice {
  id: number
  invoice_number: string
  status: 'draft' | 'sent' | 'paid' | 'overdue' | 'cancelled'
  issue_date: string
  due_date: string | null
  subtotal: string
  tax_rate: string
  tax_amount: string
  total: string
  currency: string
  notes: string | null
  paid_at: string | null
  client: { id: number; name: string; company?: string } | null
  project: { id: number; name: string } | null
  proposal: { id: number; title: string } | null
  items: InvoiceItem[]
  created_at: string
}

export function useInvoices() {
  const saving = ref(false)
  const error = ref('')

  async function markSent(id: number): Promise<Invoice> {
    const res = await api.post(`/invoices/${id}/mark-sent`)
    return res.data
  }

  async function markPaid(id: number): Promise<Invoice> {
    const res = await api.post(`/invoices/${id}/mark-paid`)
    return res.data
  }

  async function updateItems(id: number, items: Omit<InvoiceItem, 'id' | 'amount'>[]): Promise<Invoice> {
    const res = await api.put(`/invoices/${id}/items`, { items })
    return res.data
  }

  async function remove(id: number): Promise<void> {
    await api.delete(`/invoices/${id}`)
  }

  return { saving, error, markSent, markPaid, updateItems, remove }
}
