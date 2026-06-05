<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)
const showPassword = ref(false)

async function handleLogin() {
  if (!email.value || !password.value) {
    error.value = 'Please fill in all fields.'
    return
  }
  error.value = ''
  loading.value = true
  try {
    await auth.login(email.value, password.value)
    router.push({ name: 'dashboard' })
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Login failed. Please check your credentials.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-bg min-h-screen flex items-center justify-center px-4 py-12">

    <div class="w-full max-w-6xl mx-auto">
      <div class="auth-container rounded-3xl overflow-hidden flex flex-col lg:flex-row min-h-[600px]">

        <!-- LEFT — BRANDING -->
        <div class="relative flex flex-col justify-between p-10 lg:p-14 lg:w-[55%] overflow-hidden branding-side">

          <!-- Background glow blobs -->
          <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="blob-1 absolute -top-24 -left-24 w-96 h-96 rounded-full bg-violet-500/10 blur-3xl" />
            <div class="blob-2 absolute top-1/2 -right-32 w-80 h-80 rounded-full bg-indigo-500/10 blur-3xl" />
            <div class="blob-3 absolute -bottom-20 left-1/3 w-72 h-72 rounded-full bg-purple-500/8 blur-3xl" />
          </div>

          <!-- Top: logo + badge -->
          <div class="relative z-10">
            <div class="flex items-center gap-3 mb-2">
              <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-violet-600 to-indigo-600 flex items-center justify-center shadow-lg shadow-violet-500/25">
                <span class="text-white font-bold text-sm">S</span>
              </div>
              <span class="text-slate-800 font-semibold text-lg tracking-tight">SphereOS</span>
            </div>
            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-violet-600 bg-violet-50 border border-violet-100 rounded-full px-3 py-1 mt-1">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 inline-block"></span>
              Your Founder Operating System
            </span>
          </div>

          <!-- Headline -->
          <div class="relative z-10 my-8 lg:my-0">
            <h1 class="text-4xl lg:text-5xl font-bold text-slate-900 leading-[1.1] tracking-tight mb-6">
              Build.<br>
              Manage.<br>
              Scale.<br>
              <span class="bg-gradient-to-r from-violet-600 to-indigo-500 bg-clip-text text-transparent">All in One OS.</span>
            </h1>
            <p class="text-slate-500 text-base leading-relaxed max-w-sm">
              SphereOS helps founders organize projects, clients, tasks, invoices, and AI workflows in one place.
            </p>

            <!-- Features -->
            <ul class="mt-8 space-y-4">
              <li class="flex items-start gap-3">
                <span class="text-xl leading-none mt-0.5">🚀</span>
                <div>
                  <p class="text-sm font-semibold text-slate-800">All-in-One Workspace</p>
                  <p class="text-xs text-slate-500 mt-0.5">Projects, tasks, clients, and invoices.</p>
                </div>
              </li>
              <li class="flex items-start gap-3">
                <span class="text-xl leading-none mt-0.5">✨</span>
                <div>
                  <p class="text-sm font-semibold text-slate-800">AI Copilot</p>
                  <p class="text-xs text-slate-500 mt-0.5">Work smarter with contextual AI assistance.</p>
                </div>
              </li>
              <li class="flex items-start gap-3">
                <span class="text-xl leading-none mt-0.5">🔒</span>
                <div>
                  <p class="text-sm font-semibold text-slate-800">Secure & Private</p>
                  <p class="text-xs text-slate-500 mt-0.5">Your data stays protected.</p>
                </div>
              </li>
            </ul>
          </div>

          <!-- Orb illustration -->
          <div class="relative z-10 flex justify-center lg:justify-start mt-8 lg:mt-0">
            <div class="orb-float relative w-52 h-52">

              <!-- Glow -->
              <div class="orb-glow absolute inset-0 rounded-full bg-gradient-to-br from-violet-500/30 to-indigo-500/20 blur-2xl scale-110" />

              <!-- Sphere body -->
              <div class="orb-sphere absolute inset-0 rounded-full bg-gradient-to-br from-violet-500 via-indigo-500 to-purple-600 shadow-2xl shadow-violet-500/40 overflow-hidden">
                <!-- Inner light -->
                <div class="absolute top-4 left-6 w-16 h-16 rounded-full bg-white/20 blur-xl" />
                <div class="absolute bottom-8 right-6 w-10 h-10 rounded-full bg-indigo-300/20 blur-lg" />
                <!-- S logo -->
                <div class="s-drift absolute inset-0 flex items-center justify-center">
                  <span class="text-6xl font-black text-white/90 select-none" style="text-shadow: 0 4px 24px rgba(255,255,255,0.3)">S</span>
                </div>
              </div>

              <!-- Orbit ring -->
              <div class="orbit-ring absolute inset-[-18px] rounded-full border-2 border-violet-300/30"
                style="transform: rotateX(70deg);" />
              <div class="orbit-ring absolute inset-[-18px] rounded-full border border-indigo-200/20"
                style="transform: rotateX(70deg) rotateZ(30deg);" />

              <!-- Particles -->
              <div class="particle particle-1 absolute w-2 h-2 rounded-full bg-violet-400/70 shadow-sm shadow-violet-400/50" style="top: 10%; right: -4%" />
              <div class="particle particle-2 absolute w-1.5 h-1.5 rounded-full bg-indigo-300/80 shadow-sm shadow-indigo-300/50" style="bottom: 20%; left: -6%" />
              <div class="particle particle-3 absolute w-1 h-1 rounded-full bg-purple-400/60" style="top: 60%; right: -10%" />
              <div class="particle particle-4 absolute w-2.5 h-2.5 rounded-full bg-violet-300/50 shadow-sm" style="bottom: -4%; left: 30%" />
              <div class="particle particle-5 absolute w-1 h-1 rounded-full bg-indigo-400/70" style="top: -2%; left: 40%" />
            </div>
          </div>
        </div>

        <!-- RIGHT — LOGIN CARD -->
        <div class="flex items-center justify-center p-8 lg:p-14 lg:w-[45%] login-side">
          <div class="w-full max-w-sm">

            <!-- Card icon -->
            <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-violet-600 to-indigo-600 flex items-center justify-center shadow-lg shadow-violet-500/30 mb-7">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </div>

            <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Welcome back</h2>
            <p class="text-slate-500 text-sm mt-1 mb-8">Sign in to continue to SphereOS</p>

            <!-- Error -->
            <div v-if="error"
              class="mb-5 flex items-start gap-2.5 rounded-xl bg-red-50 border border-red-100 px-4 py-3">
              <svg class="w-4 h-4 text-red-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
              </svg>
              <p class="text-sm text-red-600">{{ error }}</p>
            </div>

            <form @submit.prevent="handleLogin" class="space-y-4" novalidate>

              <!-- Email -->
              <div>
                <label for="email" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">
                  Email Address
                </label>
                <div class="relative">
                  <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                    </svg>
                  </div>
                  <input
                    id="email"
                    v-model="email"
                    type="email"
                    autocomplete="email"
                    required
                    placeholder="you@example.com"
                    class="input-field w-full pl-10 pr-4 py-3 text-sm text-slate-800 bg-slate-50 border border-slate-200 rounded-xl placeholder-slate-400 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-violet-500/30 focus:border-violet-400 focus:bg-white hover:border-slate-300"
                  />
                </div>
              </div>

              <!-- Password -->
              <div>
                <label for="password" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">
                  Password
                </label>
                <div class="relative">
                  <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                  </div>
                  <input
                    id="password"
                    v-model="password"
                    :type="showPassword ? 'text' : 'password'"
                    autocomplete="current-password"
                    required
                    placeholder="••••••••"
                    class="input-field w-full pl-10 pr-12 py-3 text-sm text-slate-800 bg-slate-50 border border-slate-200 rounded-xl placeholder-slate-400 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-violet-500/30 focus:border-violet-400 focus:bg-white hover:border-slate-300"
                  />
                  <button
                    type="button"
                    @click="showPassword = !showPassword"
                    class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600 transition-colors"
                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                  >
                    <svg v-if="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Submit -->
              <button
                type="submit"
                :disabled="loading"
                class="btn-primary relative w-full py-3 px-4 rounded-xl text-sm font-semibold text-white transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed overflow-hidden mt-2"
              >
                <span class="relative z-10 flex items-center justify-center gap-2">
                  <svg v-if="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                  </svg>
                  {{ loading ? 'Signing in…' : 'Sign In' }}
                </span>
              </button>

            </form>

            <!-- Divider -->
            <div class="flex items-center gap-3 my-6">
              <div class="flex-1 h-px bg-slate-200" />
              <span class="text-xs text-slate-400 font-medium">or continue with</span>
              <div class="flex-1 h-px bg-slate-200" />
            </div>

            <!-- Social buttons -->
            <div class="grid grid-cols-2 gap-3">
              <button type="button"
                class="social-btn flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-slate-300">
                <svg class="w-4 h-4" viewBox="0 0 24 24">
                  <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                  <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                  <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                  <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                </svg>
                Google
              </button>
              <button type="button"
                class="social-btn flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-slate-300">
                <svg class="w-4 h-4 text-slate-800" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z" />
                </svg>
                GitHub
              </button>
            </div>

            <!-- Footer -->
            <p class="mt-7 text-center text-sm text-slate-500">
              Don't have an account?
              <RouterLink to="/register"
                class="font-semibold text-violet-600 hover:text-violet-700 transition-colors ml-1">
                Create one
              </RouterLink>
            </p>

          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
/* Background */
.auth-bg {
  background: linear-gradient(135deg, #f8f7ff 0%, #f1f0ff 40%, #eef2ff 100%);
}

/* Container */
.auth-container {
  background: rgba(255, 255, 255, 0.7);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.8);
  box-shadow:
    0 0 0 1px rgba(139, 92, 246, 0.05),
    0 4px 6px -1px rgba(0, 0, 0, 0.05),
    0 20px 60px -10px rgba(109, 40, 217, 0.08),
    0 40px 80px -20px rgba(0, 0, 0, 0.06);
}

/* Branding side */
.branding-side {
  background: linear-gradient(145deg, rgba(245, 243, 255, 0.9) 0%, rgba(238, 242, 255, 0.8) 100%);
  border-right: 1px solid rgba(139, 92, 246, 0.08);
}

/* Login side */
.login-side {
  background: rgba(255, 255, 255, 0.95);
}

/* ─── ORB ANIMATIONS ─── */

/* Float up/down */
@keyframes orbFloat {
  0%, 100% { transform: translateY(0px); }
  50%       { transform: translateY(-8px); }
}

/* Glow pulse */
@keyframes glowPulse {
  0%, 100% { opacity: 0.6; transform: scale(1.1); }
  50%       { opacity: 1;   transform: scale(1.2); }
}

/* Orbit ring rotation */
@keyframes orbitRotate {
  from { transform: rotateX(70deg) rotateZ(0deg); }
  to   { transform: rotateX(70deg) rotateZ(360deg); }
}

/* S drift */
@keyframes sDrift {
  0%, 100% { transform: translate(0px, 0px); }
  50%       { transform: translate(2px, -3px); }
}

/* Particle floats */
@keyframes p1 {
  0%, 100% { transform: translate(0, 0) scale(1); opacity: 0.7; }
  50%       { transform: translate(4px, -6px) scale(1.1); opacity: 1; }
}
@keyframes p2 {
  0%, 100% { transform: translate(0, 0); opacity: 0.6; }
  50%       { transform: translate(-5px, 4px); opacity: 0.9; }
}
@keyframes p3 {
  0%, 100% { transform: translate(0, 0); opacity: 0.5; }
  50%       { transform: translate(3px, 5px); opacity: 0.8; }
}
@keyframes p4 {
  0%, 100% { transform: translate(0, 0) scale(1); opacity: 0.4; }
  50%       { transform: translate(-3px, -4px) scale(1.2); opacity: 0.7; }
}
@keyframes p5 {
  0%, 100% { transform: translate(0, 0); opacity: 0.6; }
  50%       { transform: translate(5px, 3px); opacity: 1; }
}

/* Apply animations */
.orb-float   { animation: orbFloat 6s ease-in-out infinite; }
.orb-glow    { animation: glowPulse 3s ease-in-out infinite; }
.orbit-ring  { animation: orbitRotate 25s linear infinite; }
.s-drift     { animation: sDrift 4s ease-in-out infinite alternate; }
.particle-1  { animation: p1 5s ease-in-out infinite; }
.particle-2  { animation: p2 7s ease-in-out infinite; }
.particle-3  { animation: p3 6s ease-in-out infinite 1s; }
.particle-4  { animation: p4 8s ease-in-out infinite 0.5s; }
.particle-5  { animation: p5 5.5s ease-in-out infinite 1.5s; }

/* Primary button */
.btn-primary {
  background: linear-gradient(135deg, #7c3aed 0%, #6366f1 100%);
  box-shadow: 0 4px 15px -3px rgba(124, 58, 237, 0.4);
}
.btn-primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 8px 20px -4px rgba(124, 58, 237, 0.5);
  background: linear-gradient(135deg, #6d28d9 0%, #4f46e5 100%);
}
.btn-primary:active:not(:disabled) {
  transform: translateY(0);
}

/* Social buttons */
.social-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.06);
}

/* Input field */
.input-field {
  caret-color: #7c3aed;
}

/* GPU hint for animated elements */
.orb-float,
.orbit-ring,
.particle-1,
.particle-2,
.particle-3,
.particle-4,
.particle-5 {
  will-change: transform, opacity;
}
</style>
