/** @type {import('tailwindcss').Config} */
export default {
  content: ['./src/**/*.{astro,html,js,jsx,md,mdx,svelte,ts,tsx,vue}'],
  theme: {
    extend: {
      colors: {
        'ruta-red': '#DC2626',
        'ruta-orange': '#EA580C',
        'ruta-yellow': '#FACC15',
        'ruta-green': '#16A34A',
        'ruta-black': '#111111',
        'ruta-dark': '#1A1A1A',
        'ruta-white': '#FFFFFF',
        'ruta-gray': '#F5F5F5',
      },
      fontFamily: {
        'poppins': ['Poppins', 'sans-serif'],
        'montserrat': ['Montserrat', 'sans-serif']
      },
      animation: {
        'slow-pulse': 'pulse 8s cubic-bezier(0.4, 0, 0.6, 1) infinite',
      }
    },
  },
  plugins: [],
}