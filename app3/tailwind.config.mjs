/** @type {import('tailwindcss').Config} */
export default {
	content: ['./src/**/*.{astro,html,js,jsx,md,mdx,svelte,ts,tsx,vue}'],
	theme: {
		extend: {
			keyframes: {
				fill: {
					'0%': { width: '0%' },
					'100%': { width: '100%' }
				}
			},
			animation: {
				fill: 'fill 1.5s ease-in-out forwards'
			}
		},
	},
	plugins: [],
}
