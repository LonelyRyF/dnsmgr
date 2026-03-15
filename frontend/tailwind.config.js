/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
      },
      colors: {
        // Console dark mode palette
        bg: {
          DEFAULT: '#09090b',   // zinc-950
          subtle: '#18181b',     // zinc-900
          hover: '#27272a',      // zinc-800
          active: '#3f3f46',     // zinc-700
        },
        border: {
          DEFAULT: '#27272a',    // zinc-800
          subtle: '#3f3f46',     // zinc-700
        },
        text: {
          DEFAULT: '#fafafa',    // zinc-50
          muted: '#a1a1aa',      // zinc-400
          disabled: '#52525b',   // zinc-600
        },
        accent: {
          DEFAULT: '#3b82f6',    // blue-500
          hover: '#2563eb',      // blue-600
          muted: '#1d4ed8',      // blue-700
          subtle: 'rgba(59, 130, 246, 0.15)',
        },
        success: {
          DEFAULT: '#22c55e',
          subtle: 'rgba(34, 197, 94, 0.15)',
        },
        danger: {
          DEFAULT: '#ef4444',
          subtle: 'rgba(239, 68, 68, 0.15)',
        },
        warn: {
          DEFAULT: '#f59e0b',
          subtle: 'rgba(245, 158, 11, 0.15)',
        },
        info: {
          DEFAULT: '#06b6d4',
          subtle: 'rgba(6, 182, 212, 0.15)',
        },
      },
      spacing: {
        '18': '4.5rem',
        '72': '18rem',
        '80': '20rem',
        '88': '22rem',
        '240': '60rem',
      },
      borderRadius: {
        'xl': '0.75rem',
        '2xl': '1rem',
      },
      boxShadow: {
        'glow-accent': '0 0 0 3px rgba(59, 130, 246, 0.2)',
        'glow-success': '0 0 0 3px rgba(34, 197, 94, 0.2)',
        'glow-danger': '0 0 0 3px rgba(239, 68, 68, 0.2)',
        'panel': '0 4px 24px rgba(0,0,0,0.4)',
      },
      animation: {
        'fade-in': 'fadeIn 0.2s ease-out',
        'slide-in-right': 'slideInRight 0.3s cubic-bezier(0.32, 0.72, 0, 1)',
        'slide-out-right': 'slideOutRight 0.3s cubic-bezier(0.32, 0.72, 0, 1)',
        'pulse-soft': 'pulseSoft 1.8s ease-in-out infinite',
      },
      keyframes: {
        fadeIn: {
          from: { opacity: '0', transform: 'translateY(-4px)' },
          to: { opacity: '1', transform: 'translateY(0)' },
        },
        slideInRight: {
          from: { transform: 'translateX(100%)' },
          to: { transform: 'translateX(0)' },
        },
        slideOutRight: {
          from: { transform: 'translateX(0)' },
          to: { transform: 'translateX(100%)' },
        },
        pulseSoft: {
          '0%, 100%': { opacity: '0.4' },
          '50%': { opacity: '0.8' },
        },
      },
    },
  },
  plugins: [],
}
