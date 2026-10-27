import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  // tailwindcss() is the official Tailwind v4 plugin for Vite
  plugins: [react(), tailwindcss()],

  server: {
    // Inside Docker, file changes on your computer are not always detected.
    // Polling checks for changes regularly, so hot reloading keeps working.
    watch: {
      usePolling: true,
    },
  },
})
