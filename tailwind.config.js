/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './app/fashion/views/**/*.php',
    './public/assets/**/*.js',
  ],
  safelist: [
    'bg-sky-50',
    'border-sky-200',
    'text-sky-900',
    'text-slate-600',
    'font-semibold',
    'tracking-tight',
    'gap-3',
    'grid',
    'md:grid-cols-2',
  ],
  theme: {
    extend: {
      colors: {
        gmingo: {
          navy: '#1a3a8a',
          ink: '#0c1f44',
          blue: '#2b74e8',
          deep: '#071633',
        },
      },
      fontFamily: {
        display: ['Outfit', 'Segoe UI', 'sans-serif'],
      },
    },
  },
  corePlugins: {
    preflight: false,
  },
  plugins: [],
};
