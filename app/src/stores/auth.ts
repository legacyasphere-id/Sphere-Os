import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/lib/api'
import axios from 'axios'

export interface User {
  id: number
  name: string
  email: string
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('token'))

  const isAuthenticated = computed(() => !!user.value)

  if (token.value) {
    api.defaults.headers.common['Authorization'] = `Bearer ${token.value}`
  }

  async function login(email: string, password: string) {
    const { data } = await api.post('/auth/login', { email, password })
    setSession(data.user, data.token)
  }

  async function register(name: string, email: string, password: string, password_confirmation: string) {
    const { data } = await api.post('/auth/register', { name, email, password, password_confirmation })
    setSession(data.user, data.token)
  }

  async function logout() {
    await api.post('/auth/logout').catch(() => {})
    clearSession()
  }

  async function fetchUser() {
    if (!token.value) return
    const { data } = await api.get('/auth/me')
    user.value = data
  }

  function setSession(u: User, t: string) {
    user.value = u
    token.value = t
    localStorage.setItem('token', t)
    api.defaults.headers.common['Authorization'] = `Bearer ${t}`
  }

  function clearSession() {
    user.value = null
    token.value = null
    localStorage.removeItem('token')
    delete api.defaults.headers.common['Authorization']
  }

  return { user, token, isAuthenticated, login, register, logout, fetchUser }
})
