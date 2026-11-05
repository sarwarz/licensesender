import type { Config } from 'tailwindcss';

/**
 * Storefront apps render inside a Shadow DOM, so theme CSS cannot reach them and
 * Tailwind's preflight only resets the inside of the shadow root.
 * Colours come from CSS variables set on .ls-root (light) and .ls-root.dark.
 */
const config: Config = {
  darkMode: ['class'],
  content: ['./resources/public/**/*.{ts,tsx}'],
  theme: {
    extend: {
      fontFamily: {
        sans: [
          'Inter',
          'ui-sans-serif',
          'system-ui',
          '-apple-system',
          'Segoe UI',
          'Roboto',
          'Helvetica Neue',
          'Arial',
          'sans-serif',
        ],
        mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Consolas', 'monospace'],
      },
      colors: {
        bg: 'rgb(var(--ls-bg) / <alpha-value>)',
        surface: 'rgb(var(--ls-surface) / <alpha-value>)',
        subtle: 'rgb(var(--ls-subtle) / <alpha-value>)',
        line: 'rgb(var(--ls-line) / <alpha-value>)',
        fg: 'rgb(var(--ls-fg) / <alpha-value>)',
        muted: 'rgb(var(--ls-muted) / <alpha-value>)',
        faint: 'rgb(var(--ls-faint) / <alpha-value>)',
        brand: {
          DEFAULT: 'rgb(var(--ls-brand) / <alpha-value>)',
          fg: 'rgb(var(--ls-brand-fg) / <alpha-value>)',
          soft: 'rgb(var(--ls-brand) / 0.1)',
        },
      },
      borderRadius: {
        card: 'var(--ls-radius-card)',
        ctl: 'var(--ls-radius-ctl)',
      },
      boxShadow: {
        card: '0 1px 2px rgb(15 23 42 / 0.04), 0 1px 3px rgb(15 23 42 / 0.06)',
        pop: '0 12px 32px -8px rgb(15 23 42 / 0.25)',
      },
      keyframes: {
        'ls-fade-in': { from: { opacity: '0', transform: 'translateY(4px)' }, to: { opacity: '1', transform: 'none' } },
        'ls-shimmer': { '100%': { transform: 'translateX(100%)' } },
      },
      animation: {
        'fade-in': 'ls-fade-in 180ms ease-out both',
        shimmer: 'ls-shimmer 1.4s infinite',
      },
    },
  },
  plugins: [],
};

export default config;
