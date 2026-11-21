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
          navy: '#1e3a8a',
          ink: '#0f2557',
          blue: '#2f80ed',
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
