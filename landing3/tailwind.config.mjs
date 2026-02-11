/** @type {import('tailwindcss').Config} */
export default {
  content: ['./src/**/*.{astro,html,js,jsx,md,mdx,svelte,ts,tsx,vue}'],
  theme: {
    extend: {
      colors: {
        'ruta-red': '#DC2626',
        'ruta-orange': '#EA580C',
        'ruta-brown': '#92400E',
        'ruta-light-brown': '#D97706',
        'ruta-black': '#1F2937',
        'ruta-white': '#FFFFFF'
      },
      fontFamily: {
        'poppins': ['Poppins', 'sans-serif']
      }
    },
  },
  plugins: [],
}