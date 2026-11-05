import type { BaseConfig, Message, SupportConfig } from './types';

declare global {
  interface Window {
    LSSupportApp?: SupportConfig;
    LSSupportBooted?: boolean;
  }
}

// Each storefront bundle (support, my-keys) registers its own settings object.
let activeConfig: BaseConfig | null = null;

export function setConfig(config: BaseConfig) {
  activeConfig = config;
}

export function getConfig<T extends BaseConfig = SupportConfig>(): T {
  const config = activeConfig ?? window.LSSupportApp;
  if (!config) {
    throw new Error('App configuration is missing.');
  }
  return config as T;
}

/** Translated string with sequential %s / %d placeholders. */
export function t(key: string, fallback: string, ...args: Array<string | number>): string {
  let text = getConfig<BaseConfig>().i18n[key] || fallback;
  for (const arg of args) {
    text = text.replace(/%[sd]/, String(arg));
  }
  return text;
}

export function cn(...classes: Array<string | false | null | undefined>): string {
  return classes.filter(Boolean).join(' ');
}

export class ApiError extends Error {
  /** The `data` the server sent with the error (e.g. per-item problems). */
  data?: unknown;
}

/** POST to admin-ajax. Returns `data` from wp_send_json_success, throws the server message otherwise. */
export async function ajax<T>(action: string, data: Record<string, string | number | Blob | Blob[]> = {}): Promise<T> {
  const cfg = getConfig<BaseConfig>();
  const body = new FormData();
  body.append('action', action);
  // Callers can pass an action-specific nonce; otherwise the app's default one is used.
  if (!('nonce' in data)) body.append('nonce', cfg.nonce);
  for (const [key, value] of Object.entries(data)) {
    if (Array.isArray(value)) {
      value.forEach((file) => body.append(`${key}[]`, file, (file as File).name));
    } else {
      body.append(key, value instanceof Blob ? value : String(value));
    }
  }

  const controller = new AbortController();
  const timer = window.setTimeout(() => controller.abort(), 60000);
  let response: Response;
  try {
    response = await fetch(cfg.ajaxUrl, { method: 'POST', body, credentials: 'same-origin', signal: controller.signal });
  } catch {
    throw new ApiError(t('networkError', 'Could not reach the server. Check your connection and try again.'));
  } finally {
    window.clearTimeout(timer);
  }

  let json: { success?: boolean; data?: unknown } | null = null;
  try {
    json = await response.json();
  } catch {
    // Non-JSON (e.g. "-1" for an expired nonce, or a PHP error page).
  }

  if (!json) {
    throw new ApiError(
      response.status === 403 || response.status === 400
        ? t('sessionExpired', 'Your session has expired. Please reload the page.')
        : t('error', 'Something went wrong. Please try again.'),
    );
  }
  if (!json.success) {
    const message = (json.data as { message?: string } | undefined)?.message;
    const error = new ApiError(message || t('error', 'Something went wrong. Please try again.'));
    error.data = json.data;
    throw error;
  }
  return json.data as T;
}

export function parseDate(value?: string): Date | null {
  if (!value) return null;
  const direct = new Date(value);
  if (!Number.isNaN(direct.getTime())) return direct;
  const normalized = new Date(String(value).replace(' ', 'T'));
  return Number.isNaN(normalized.getTime()) ? null : normalized;
}

const locale = document.documentElement.lang || undefined;

export function relativeTime(value?: string): string {
  const date = parseDate(value);
  if (!date) return value || '—';
  const seconds = Math.round((date.getTime() - Date.now()) / 1000);
  const abs = Math.abs(seconds);
  const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
  if (abs < 45) return rtf.format(0, 'second');
  if (abs < 3600) return rtf.format(Math.round(seconds / 60), 'minute');
  if (abs < 86400) return rtf.format(Math.round(seconds / 3600), 'hour');
  if (abs < 86400 * 30) return rtf.format(Math.round(seconds / 86400), 'day');
  return date.toLocaleDateString(locale, { dateStyle: 'medium' });
}

export function fullDate(value?: string): string {
  const date = parseDate(value);
  return date ? date.toLocaleString(locale, { dateStyle: 'medium', timeStyle: 'short' }) : value || '—';
}

export function fileSize(bytes: number): string {
  if (!bytes) return '';
  if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1)} MB`;
  if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${bytes} B`;
}

export function initials(name: string): string {
  // Only words that start with a letter or digit ("Daniel — Support" → "DS").
  const parts = name.trim().split(/\s+/).filter((part) => /^[\p{L}\p{N}]/u.test(part));
  if (!parts.length) return '?';
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

export function isCustomerMessage(message: Message): boolean {
  const author = String(message.author_type || message.sender_type || message.role || '').toLowerCase();
  return author.includes('customer') || author.includes('user');
}

export function messageTime(message: Message): string {
  return String(message.created_at || message.sent_at || '');
}

/** Plain textarea text → the small HTML subset the help desk expects. */
export function textToHtml(text: string): string {
  const escape = (value: string) =>
    value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  return text
    .trim()
    .split(/\n{2,}/)
    .map((block) => `<p>${escape(block).replace(/\n/g, '<br>')}</p>`)
    .join('');
}

/** The help desk limits the stored HTML, so line breaks and escaped characters count too. */
export function messageTooLong(text: string): boolean {
  return textToHtml(text).length > getConfig().limits.maxMessage;
}

export function htmlToText(html: string): string {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  return (doc.body.textContent || '').replace(/\s+/g, ' ').trim();
}

/** Validates attachments with the same limits the server enforces. */
export function validateFiles(current: File[], incoming: File[]): { files: File[]; error: string } {
  const { limits } = getConfig();
  const files = [...current];
  for (const file of incoming) {
    const ext = file.name.split('.').pop()?.toLowerCase() || '';
    if (!limits.extensions.includes(ext)) {
      return { files, error: t('fileType', '"%s" is not an image. You can attach PNG, JPG, GIF or WEBP files.', file.name) };
    }
    if (file.size > limits.maxFileMb * 1048576) {
      return { files, error: t('fileTooLarge', '"%s" is larger than %d MB.', file.name, limits.maxFileMb) };
    }
    if (files.length >= limits.maxFiles) {
      return { files, error: t('tooManyFiles', 'You can attach up to %d files.', limits.maxFiles) };
    }
    if (files.reduce((sum, f) => sum + f.size, 0) + file.size > limits.maxTotalMb * 1048576) {
      return { files, error: t('totalTooLarge', 'Attachments can be %d MB in total.', limits.maxTotalMb) };
    }
    if (!files.some((f) => f.name === file.name && f.size === file.size)) {
      files.push(file);
    }
  }
  return { files, error: '' };
}

/* ---------- Theme ---------- */

function parseRgb(color: string): [number, number, number, number] | null {
  const match = color.match(/rgba?\(([^)]+)\)/);
  if (!match) return null;
  const parts = match[1].split(/[\s,/]+/).filter(Boolean).map(Number);
  return [parts[0], parts[1], parts[2], parts.length > 3 ? parts[3] : 1];
}

/**
 * Dark mode follows the WordPress theme: if the theme paints a dark background behind
 * the app we go dark, otherwise we stay light. The visitor's OS setting is not used, so
 * a light theme always gets the light app.
 */
export function detectSiteDark(host: Element): boolean {
  let el: Element | null = host;
  while (el) {
    const rgb = parseRgb(getComputedStyle(el).backgroundColor);
    if (rgb && rgb[3] > 0.5) {
      const [r, g, b] = rgb.map((c) => c / 255);
      const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b;
      return luminance < 0.4;
    }
    el = el.parentElement;
  }
  return false;
}

export function hexToRgbTriplet(hex: string): string | null {
  const clean = hex.replace('#', '');
  const full = clean.length === 3 ? clean.split('').map((c) => c + c).join('') : clean;
  if (!/^[0-9a-f]{6}$/i.test(full)) return null;
  const n = parseInt(full, 16);
  return `${(n >> 16) & 255} ${(n >> 8) & 255} ${n & 255}`;
}

/** Black or white text, whichever reads better on the brand colour. */
export function readableOn(triplet: string): string {
  const [r, g, b] = triplet.split(' ').map((c) => Number(c) / 255);
  const lin = (c: number) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);
  const luminance = 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
  return luminance > 0.45 ? '15 23 42' : '255 255 255';
}
