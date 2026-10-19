import colors from 'tailwindcss/colors'

/**
 * FraisZen design system — "Flat & trustworthy".
 *
 * Every colour is a CSS variable defined in `src/assets/main.css` so that
 * light/dark themes are driven by `[data-theme]` without touching components.
 * Components must use semantic tokens (brand, ink, surface, line, success…)
 * instead of raw hex values.
 */
const scale = (prefix, shades) =>
  Object.fromEntries(shades.map(n => [n, `rgb(var(--${prefix}-${n}) / <alpha-value>)`]))

const brandShades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900]
const grayShades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]

/** Legacy Tailwind hues still used by expense categories; remapped in dark mode via CSS vars. */
const legacyHues = ['blue', 'emerald', 'amber', 'orange', 'rose', 'red', 'green', 'purple', 'yellow', 'teal']

/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        ...Object.fromEntries(
          legacyHues.map(hue => [
            hue,
            Object.fromEntries(Object.entries(colors[hue]).map(([shade, hex]) => [shade, `var(--${hue}-${shade}, ${hex})`])),
          ]),
        ),
        white: 'rgb(var(--surface) / <alpha-value>)',
        gray: scale('gray', grayShades),
        // `indigo` is kept as an alias of the brand scale so legacy utilities stay on-brand.
        indigo: scale('accent', brandShades),
        brand: { ...scale('accent', brandShades), DEFAULT: 'rgb(var(--accent-600) / <alpha-value>)' },
        // Semantic tokens
        surface: {
          DEFAULT: 'rgb(var(--surface) / <alpha-value>)',
          muted: 'rgb(var(--surface-muted) / <alpha-value>)',
          sunken: 'rgb(var(--surface-sunken) / <alpha-value>)',
          inverse: 'rgb(var(--surface-inverse) / <alpha-value>)',
        },
        ink: {
          DEFAULT: 'rgb(var(--ink) / <alpha-value>)',
          muted: 'rgb(var(--ink-muted) / <alpha-value>)',
          subtle: 'rgb(var(--ink-subtle) / <alpha-value>)',
          inverse: 'rgb(var(--ink-inverse) / <alpha-value>)',
        },
        line: {
          DEFAULT: 'rgb(var(--line) / <alpha-value>)',
          strong: 'rgb(var(--line-strong) / <alpha-value>)',
        },
        success: {
          DEFAULT: 'rgb(var(--success) / <alpha-value>)',
          soft: 'rgb(var(--success-soft) / <alpha-value>)',
          ink: 'rgb(var(--success-ink) / <alpha-value>)',
        },
        warning: {
          DEFAULT: 'rgb(var(--warning) / <alpha-value>)',
          soft: 'rgb(var(--warning-soft) / <alpha-value>)',
          ink: 'rgb(var(--warning-ink) / <alpha-value>)',
        },
        danger: {
          DEFAULT: 'rgb(var(--danger) / <alpha-value>)',
          soft: 'rgb(var(--danger-soft) / <alpha-value>)',
          ink: 'rgb(var(--danger-ink) / <alpha-value>)',
        },
        info: {
          DEFAULT: 'rgb(var(--info) / <alpha-value>)',
          soft: 'rgb(var(--info-soft) / <alpha-value>)',
          ink: 'rgb(var(--info-ink) / <alpha-value>)',
        },
      },
      fontFamily: {
        sans: ['"Source Sans 3"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        display: ['Lexend', '"Source Sans 3"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'ui-monospace', 'SFMono-Regular', 'monospace'],
      },
      fontSize: {
        // Type scale (min 12px). Line-heights tuned for UI density.
        '2xs': ['0.75rem', { lineHeight: '1rem' }],
        xs: ['0.8125rem', { lineHeight: '1.125rem' }],
        sm: ['0.875rem', { lineHeight: '1.25rem' }],
        base: ['1rem', { lineHeight: '1.5rem' }],
        lg: ['1.125rem', { lineHeight: '1.625rem' }],
        xl: ['1.25rem', { lineHeight: '1.75rem' }],
        '2xl': ['1.5rem', { lineHeight: '2rem' }],
        '3xl': ['1.875rem', { lineHeight: '2.25rem' }],
        '4xl': ['2.25rem', { lineHeight: '2.5rem' }],
      },
      borderRadius: {
        sm: '0.375rem',
        DEFAULT: '0.5rem',
        md: '0.625rem',
        lg: '0.75rem',
        xl: '1rem',
        '2xl': '1.25rem',
        '3xl': '1.5rem',
      },
      boxShadow: {
        // Flat design: one soft elevation for floating surfaces only (menus, dialogs, toasts).
        card: '0 1px 2px rgb(var(--shadow-color) / 0.04)',
        float: '0 12px 32px -8px rgb(var(--shadow-color) / 0.18), 0 2px 6px rgb(var(--shadow-color) / 0.06)',
        focus: '0 0 0 3px rgb(var(--accent-500) / 0.35)',
      },
      spacing: {
        touch: '2.75rem', // 44px — minimum touch target
        4.5: '1.125rem',
        13: '3.25rem',
        18: '4.5rem',
      },
      minHeight: { touch: '2.75rem' },
      minWidth: { touch: '2.75rem' },
      maxWidth: { content: '80rem' },
      transitionDuration: { fast: '150ms', base: '200ms', slow: '300ms' },
      transitionTimingFunction: { out: 'cubic-bezier(0.16, 1, 0.3, 1)' },
      keyframes: {
        'fade-in': { from: { opacity: '0' }, to: { opacity: '1' } },
        'slide-up': { from: { opacity: '0', transform: 'translateY(8px)' }, to: { opacity: '1', transform: 'translateY(0)' } },
        shimmer: { from: { backgroundPosition: '200% 0' }, to: { backgroundPosition: '-200% 0' } },
      },
      animation: {
        'fade-in': 'fade-in 200ms ease-out both',
        'slide-up': 'slide-up 250ms cubic-bezier(0.16, 1, 0.3, 1) both',
        shimmer: 'shimmer 1.6s linear infinite',
      },
    },
  },
  plugins: [require('@tailwindcss/forms')],
}
