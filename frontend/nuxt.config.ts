import tailwindcss from '@tailwindcss/vite'

export default defineNuxtConfig({
  compatibilityDate: '2025-01-01',
  devtools: { enabled: true },
  css: ['~/assets/css/main.css'],
  components: [{ path: '~/components', pathPrefix: false }],
  modules: ['@pinia/nuxt'],
  runtimeConfig: {
    public: {
      apiBase:
        (globalThis as { process?: { env?: Record<string, string | undefined> } }).process?.env?.API_BASE_URL ||
        'http://127.0.0.1:8000/api',
    },
  },
  vite: {
    plugins: [tailwindcss()],
  },
})