/** @type {import('tailwindcss').Config} */
export default {
  content: ['./src/**/*.{astro,html,js,jsx,md,mdx,svelte,ts,tsx,vue}'],
  theme: {
    extend: {
      colors: {
        'ruta-red': '#B91C1C',
        'ruta-orange': '#C24100',
        'ruta-yellow': '#FACC15',
        'ruta-brown': '#78350F',
        'ruta-black': '#0F172A',
        'ruta-dark': '#020617',
        'ruta-white': '#F8FAFC'
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