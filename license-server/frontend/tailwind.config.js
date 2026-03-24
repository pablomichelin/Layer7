/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,jsx}'],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#e8f1f8',
          100: '#c5dbef',
          200: '#9ec3e4',
          300: '#77abd9',
          400: '#5999d1',
          500: '#337ab7',
          600: '#2d6ca3',
          700: '#255a87',
          800: '#1e486c',
          900: '#163550',
        },
      },
    },
  },
  plugins: [],
};
