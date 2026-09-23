import colors from 'tailwindcss/colors'
/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        ...Object.fromEntries(['blue','emerald','amber','orange','rose','red','green','purple','yellow','teal'].map(hue => [hue, Object.fromEntries(Object.entries(colors[hue]).map(([shade, hex]) => [shade, `var(--${hue}-${shade}, ${hex})`]))])),
        white: 'rgb(var(--surface) / <alpha-value>)',
        gray: Object.fromEntries([50,100,200,300,400,500,600,700,800,900,950].map(n => [n, `rgb(var(--gray-${n}) / <alpha-value>)`])),
        indigo: Object.fromEntries([50,100,200,300,400,500,600,700,800,900].map(n => [n, `rgb(var(--accent-${n}) / <alpha-value>)`])),
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [require('@tailwindcss/forms')],
}
