<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const error = ref('')
const loading = ref(false)

async function handleRegister() {
  if (!name.value || !email.value || !password.value || !passwordConfirmation.value) {
    error.value = 'Please fill in all fields.'
    return
  }
  if (password.value !== passwordConfirmation.value) {
    error.value = 'Passwords do not match.'
    return
  }
  if (password.value.length < 8) {
    error.value = 'Password must be at least 8 characters.'
    return
  }
  error.value = ''
  loading.value = true
  try {
    await auth.register(name.value, email.value, password.value, passwordConfirmation.value)
    router.push({ name: 'dashboard' })
  } catch (e: any) {
    const data = e?.response?.data
    if (data?.errors) {
      const firstKey = Object.keys(data.errors)[0] as string
      error.value = data.errors[firstKey]?.[0] ?? 'Registration failed.'
    } else {
      error.value = data?.message ?? 'Registration failed. Please try again.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4">
    <div class="w-full max-w-md">
      <!-- Logo -->
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900">
          Founder<span class="text-indigo-600">OS</span>
        </h1>
        <p class="mt-2 text-sm text-gray-500">Create your account</p>
      </div>

      <!-- Card -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
        <form @submit.prevent="handleRegister" class="space-y-5">
          <!-- Error message -->
          <div v-if="error" class="rounded-md bg-red-50 border border-red-200 px-4 py-3">
            <p class="text-sm text-red-700">{{ error }}</p>
          </div>

          <!-- Name -->
          <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
            <input
              id="name"
              v-model="name"
              type="text"
              autocomplete="name"
              required
              placeholder="Jane Smith"
              class="w-full px-3 py-2 text-sm border rounded-md ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
            />
          </div>

          <!-- Email -->
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input
              id="email"
              v-model="email"
              type="email"
              autocomplete="email"
              required
              placeholder="you@example.com"
              class="w-full px-3 py-2 text-sm border rounded-md ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
            />
          </div>

          <!-- Password -->
          <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input
              id="password"
              v-model="password"
              type="password"
              autocomplete="new-password"
              required
              placeholder="Min. 8 characters"
              class="w-full px-3 py-2 text-sm border rounded-md ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
            />
          </div>

          <!-- Confirm Password -->
          <div>
            <label for="password-confirm" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
            <input
              id="password-confirm"
              v-model="passwordConfirmation"
              type="password"
              autocomplete="new-password"
              required
              placeholder="••••••••"
              class="w-full px-3 py-2 text-sm border rounded-md ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
            />
          </div>

          <!-- Submit -->
          <button
            type="submit"
            :disabled="loading"
            class="w-full flex justify-center py-2.5 px-4 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
          >
            <svg v-if="loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            {{ loading ? 'Creating account…' : 'Create account' }}
          </button>
        </form>
      </div>

      <!-- Login link -->
      <p class="mt-6 text-center text-sm text-gray-500">
        Already have an account?
        <RouterLink to="/login" class="font-medium text-indigo-600 hover:text-indigo-500">Sign in</RouterLink>
      </p>
    </div>
  </div>
</template>
