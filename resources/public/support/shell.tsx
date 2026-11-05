import { useEffect, useLayoutEffect, useState, type CSSProperties, type RefObject } from 'react';
import { detectSiteDark, getConfig, hexToRgbTriplet, readableOn } from './lib';
import type { BaseConfig, ThemeMode } from './types';

/**
 * Shared by every storefront app: dark mode follows the WordPress theme's background
 * (re-checked when the theme toggles its own dark mode), unless a shortcode forces it.
 */
export function useSiteDark(host: HTMLElement, theme: ThemeMode): boolean {
  const [siteDark, setSiteDark] = useState(() => detectSiteDark(host.parentElement ?? host));

  useEffect(() => {
    if (theme !== 'auto') return undefined;
    const update = () => setSiteDark(detectSiteDark(host.parentElement ?? host));
    // Themes with their own dark-mode switch flip a class or style on <html>/<body>.
    const observer = new MutationObserver(update);
    const opts = { attributes: true, attributeFilter: ['class', 'style', 'data-theme'] };
    observer.observe(document.documentElement, opts);
    if (document.body) observer.observe(document.body, opts);
    const media = window.matchMedia?.('(prefers-color-scheme: dark)');
    media?.addEventListener?.('change', update);
    return () => {
      observer.disconnect();
      media?.removeEventListener?.('change', update);
    };
  }, [host, theme]);

  return theme === 'dark' || (theme === 'auto' && siteDark);
}

/** Container width, not viewport: the app may sit in a narrow column (e.g. My Account). */
export function useWide(ref: RefObject<HTMLElement>, breakpoint = 720): boolean {
  const [wide, setWide] = useState(true);
  useLayoutEffect(() => {
    const el = ref.current;
    if (!el) return undefined;
    const observer = new ResizeObserver(([entry]) => setWide(entry.contentRect.width >= breakpoint));
    observer.observe(el);
    return () => observer.disconnect();
  }, [ref, breakpoint]);
  return wide;
}

/** Brand colour and radii from the plugin's design settings, as CSS variables. */
export function brandStyle(): CSSProperties {
  const cfg = getConfig<BaseConfig>();
  const brand = hexToRgbTriplet(cfg.brand || '') ?? '79 70 229';
  return {
    '--ls-brand': brand,
    '--ls-brand-fg': readableOn(brand),
    '--ls-radius-card': cfg.radiusCard || '14px',
    '--ls-radius-ctl': cfg.radiusControl || '10px',
  } as CSSProperties;
}
