import {
  forwardRef,
  useCallback,
  useEffect,
  useId,
  useRef,
  useState,
  type ButtonHTMLAttributes,
  type InputHTMLAttributes,
  type KeyboardEvent,
  type ReactNode,
  type TextareaHTMLAttributes,
} from 'react';
import type { LucideIcon } from 'lucide-react';
import { AlertCircle, Check, CheckCircle2, ChevronDown, Info, Loader2, X } from 'lucide-react';
import { cn, initials, t } from './lib';
import type { PriorityClass, StatusClass } from './types';

/* ---------- Button ---------- */

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';
type Size = 'sm' | 'md' | 'icon' | 'icon-sm';

const variants: Record<Variant, string> = {
  primary: 'bg-brand text-brand-fg shadow-sm hover:bg-brand/90 active:bg-brand/80',
  secondary: 'border border-line bg-surface text-fg shadow-sm hover:bg-subtle',
  ghost: 'text-muted hover:bg-fg/5 hover:text-fg',
  danger: 'text-red-600 hover:bg-red-500/10 dark:text-red-400',
};

const sizes: Record<Size, string> = {
  sm: 'h-8 gap-1.5 px-3 text-[13px]',
  md: 'h-10 gap-2 px-4 text-sm',
  icon: 'h-9 w-9',
  'icon-sm': 'h-8 w-8',
};

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  size?: Size;
  icon?: LucideIcon;
  loading?: boolean;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
  { variant = 'secondary', size = 'md', icon: Icon, loading, className, children, disabled, type = 'button', ...props },
  ref,
) {
  return (
    <button
      ref={ref}
      type={type}
      disabled={disabled || loading}
      className={cn(
        'inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-ctl font-medium transition-colors disabled:pointer-events-none disabled:opacity-50',
        variants[variant],
        sizes[size],
        className,
      )}
      {...props}
    >
      {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : Icon ? <Icon className="h-4 w-4" /> : null}
      {children}
    </button>
  );
});

/* ---------- Badges ---------- */

const statusTones: Record<StatusClass, string> = {
  open: 'bg-sky-500/10 text-sky-700 ring-sky-500/25 dark:text-sky-300',
  'awaiting-staff': 'bg-violet-500/10 text-violet-700 ring-violet-500/25 dark:text-violet-300',
  'awaiting-customer': 'bg-amber-500/15 text-amber-800 ring-amber-500/30 dark:text-amber-300',
  resolved: 'bg-emerald-500/10 text-emerald-700 ring-emerald-500/25 dark:text-emerald-300',
  closed: 'bg-fg/5 text-muted ring-line',
  default: 'bg-fg/5 text-muted ring-line',
};

const statusDots: Record<StatusClass, string> = {
  open: 'bg-sky-500',
  'awaiting-staff': 'bg-violet-500',
  'awaiting-customer': 'bg-amber-500',
  resolved: 'bg-emerald-500',
  closed: 'bg-faint',
  default: 'bg-faint',
};

export function statusText(statusClass: StatusClass | undefined, label: string | undefined): string {
  if (statusClass === 'awaiting-customer') return t('statusAwaitingYou', 'Awaiting your reply');
  if (statusClass === 'awaiting-staff') return t('statusAwaitingSupport', 'Awaiting support');
  return label || t('statusOpen', 'Open');
}

export function StatusBadge({ statusClass = 'default', label }: { statusClass?: StatusClass; label?: string }) {
  const tone = statusTones[statusClass] ? statusClass : 'default';
  return (
    <span className={cn('inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset', statusTones[tone])}>
      <span className={cn('h-1.5 w-1.5 rounded-full', statusDots[tone])} />
      {statusText(tone, label)}
    </span>
  );
}

export function PriorityBadge({ priorityClass, label, priority }: { priorityClass?: PriorityClass; label?: string; priority?: string }) {
  if (priorityClass !== 'high') return null;
  const urgent = priority === 'urgent';
  return (
    <span
      className={cn(
        'inline-flex items-center whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ring-1 ring-inset',
        urgent ? 'bg-red-500/10 text-red-700 ring-red-500/25 dark:text-red-300' : 'bg-orange-500/10 text-orange-700 ring-orange-500/25 dark:text-orange-300',
      )}
    >
      {label}
    </span>
  );
}

/* ---------- Form controls ---------- */

const controlBase =
  'w-full rounded-ctl border border-line bg-surface text-sm text-fg shadow-sm transition-colors placeholder:text-faint hover:border-faint/70 focus:border-brand focus:outline-none focus:ring-4 focus:ring-brand/15 disabled:cursor-not-allowed disabled:opacity-60';

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(function Input({ className, ...props }, ref) {
  return <input ref={ref} className={cn(controlBase, 'h-10 px-3', className)} {...props} />;
});

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaHTMLAttributes<HTMLTextAreaElement>>(function Textarea({ className, ...props }, ref) {
  return <textarea ref={ref} className={cn(controlBase, 'block min-h-[120px] resize-y px-3 py-2.5 leading-relaxed', className)} {...props} />;
});

export interface SelectOption {
  value: string;
  label: string;
  description?: string;
}

interface SelectProps {
  id?: string;
  value: string;
  onChange: (value: string) => void;
  options: SelectOption[];
  placeholder?: string;
  disabled?: boolean;
  loading?: boolean;
  icon?: LucideIcon;
  className?: string;
  'aria-label'?: string;
}

/**
 * Styled dropdown (native <select> menus can't be themed and look different on every OS).
 * The menu is position: fixed so cards with overflow: hidden never clip it.
 */
export function Select({ id, value, onChange, options, placeholder, disabled, loading, icon: Icon, className, ...aria }: SelectProps) {
  const [open, setOpen] = useState(false);
  const [active, setActiveState] = useState(0);
  // Mirror in a ref so fast key presses never act on a stale highlight.
  const activeRef = useRef(0);
  const setActive = (next: number | ((current: number) => number)) => {
    activeRef.current = typeof next === 'function' ? next(activeRef.current) : next;
    setActiveState(activeRef.current);
  };
  const [pos, setPos] = useState<{ top: number; left: number; width: number; maxHeight: number; up: boolean } | null>(null);
  const trigger = useRef<HTMLButtonElement>(null);
  const menu = useRef<HTMLUListElement>(null);
  const typed = useRef({ text: '', at: 0 });
  const listId = useId();
  const selected = options.find((o) => o.value === value);

  const place = useCallback(() => {
    const rect = trigger.current?.getBoundingClientRect();
    if (!rect) return;
    const below = window.innerHeight - rect.bottom - 12;
    const above = rect.top - 12;
    const up = below < 240 && above > below;
    setPos({
      top: up ? rect.top - 6 : rect.bottom + 6,
      left: rect.left,
      width: Math.max(rect.width, 200),
      maxHeight: Math.min(320, up ? above : below),
      up,
    });
  }, []);

  const openMenu = () => {
    if (disabled || loading) return;
    place();
    setActive(Math.max(0, options.findIndex((o) => o.value === value)));
    setOpen(true);
  };

  const choose = (index: number) => {
    const option = options[index];
    if (option) onChange(option.value);
    setOpen(false);
    trigger.current?.focus();
  };

  useEffect(() => {
    if (!open) return undefined;
    const onDown = (event: PointerEvent) => {
      const path = event.composedPath();
      if (!path.includes(trigger.current as EventTarget) && !path.includes(menu.current as EventTarget)) setOpen(false);
    };
    window.addEventListener('pointerdown', onDown);
    window.addEventListener('scroll', place, true);
    window.addEventListener('resize', place);
    return () => {
      window.removeEventListener('pointerdown', onDown);
      window.removeEventListener('scroll', place, true);
      window.removeEventListener('resize', place);
    };
  }, [open, place]);

  useEffect(() => {
    if (open) menu.current?.querySelector<HTMLElement>(`[data-index="${active}"]`)?.scrollIntoView({ block: 'nearest' });
  }, [open, active]);

  const onKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
    if (!open) {
      if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
        event.preventDefault();
        openMenu();
      }
      return;
    }
    const last = options.length - 1;
    if (event.key === 'ArrowDown') setActive((i) => Math.min(last, i + 1));
    else if (event.key === 'ArrowUp') setActive((i) => Math.max(0, i - 1));
    else if (event.key === 'Home') setActive(0);
    else if (event.key === 'End') setActive(last);
    else if (event.key === 'Enter' || event.key === ' ') choose(activeRef.current);
    else if (event.key === 'Escape') setOpen(false);
    else if (event.key === 'Tab') {
      setOpen(false);
      return;
    } else if (event.key.length === 1) {
      // Type-ahead: jump to the first option starting with the typed text.
      const now = Date.now();
      typed.current = { text: (now - typed.current.at < 600 ? typed.current.text : '') + event.key.toLowerCase(), at: now };
      const match = options.findIndex((o) => o.label.toLowerCase().startsWith(typed.current.text));
      if (match >= 0) setActive(match);
      return;
    } else return;
    event.preventDefault();
  };

  return (
    <div className={cn('relative', className)}>
      <button
        ref={trigger}
        id={id}
        type="button"
        disabled={disabled}
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-controls={listId}
        aria-activedescendant={open ? `${listId}-${active}` : undefined}
        onClick={() => (open ? setOpen(false) : openMenu())}
        onKeyDown={onKeyDown}
        className={cn(
          controlBase,
          'flex h-10 items-center gap-2 text-left',
          Icon ? 'pl-9' : 'pl-3',
          'pr-9',
          open && 'border-brand ring-4 ring-brand/15',
        )}
        {...aria}
      >
        {Icon ? <Icon className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" /> : null}
        <span className={cn('min-w-0 flex-1 truncate', !selected && 'text-faint')}>{selected ? selected.label : placeholder}</span>
        {loading ? (
          <Loader2 className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-faint" />
        ) : (
          <ChevronDown className={cn('pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint transition-transform', open && 'rotate-180')} />
        )}
      </button>

      {open && pos ? (
        <ul
          ref={menu}
          id={listId}
          role="listbox"
          aria-labelledby={id}
          style={{
            position: 'fixed',
            left: pos.left,
            width: pos.width,
            maxHeight: pos.maxHeight,
            ...(pos.up ? { bottom: window.innerHeight - pos.top } : { top: pos.top }),
          }}
          className="z-[100000] animate-fade-in overflow-y-auto overscroll-contain rounded-ctl border border-line bg-surface p-1 shadow-pop"
        >
          {options.map((option, index) => {
            const isSelected = option.value === value;
            return (
              <li
                key={option.value || `empty-${index}`}
                id={`${listId}-${index}`}
                data-index={index}
                role="option"
                aria-selected={isSelected}
                onPointerMove={() => setActive(index)}
                onClick={() => choose(index)}
                className={cn(
                  'flex cursor-pointer items-center gap-2.5 rounded-[calc(var(--ls-radius-ctl)-4px)] px-2.5 py-2 text-sm',
                  index === active ? 'bg-brand/10 text-fg' : 'text-fg',
                )}
              >
                <span className="min-w-0 flex-1">
                  <span className={cn('block truncate', isSelected && 'font-medium')}>{option.label}</span>
                  {option.description ? <span className="block truncate text-xs text-muted">{option.description}</span> : null}
                </span>
                {isSelected ? <Check className="h-4 w-4 shrink-0 text-brand" /> : null}
              </li>
            );
          })}
        </ul>
      ) : null}
    </div>
  );
}

export function Field({
  label,
  htmlFor,
  hint,
  optional,
  error,
  children,
  aside,
}: {
  label: string;
  htmlFor?: string;
  hint?: ReactNode;
  optional?: boolean;
  error?: string;
  children: ReactNode;
  aside?: ReactNode;
}) {
  return (
    <div className="space-y-1.5">
      <div className="flex items-baseline justify-between gap-3">
        <label htmlFor={htmlFor} className="text-[13px] font-medium text-fg">
          {label}
          {optional ? <span className="ml-1.5 font-normal text-faint">{t('optional', 'Optional')}</span> : null}
        </label>
        {aside}
      </div>
      {children}
      {error ? (
        <p className="flex items-center gap-1.5 text-[13px] text-red-600 dark:text-red-400">
          <AlertCircle className="h-3.5 w-3.5 shrink-0" />
          {error}
        </p>
      ) : hint ? (
        <p className="text-[13px] text-muted">{hint}</p>
      ) : null}
    </div>
  );
}

/* ---------- Surfaces & feedback ---------- */

export function Card({ className, children }: { className?: string; children: ReactNode }) {
  return <div className={cn('rounded-card border border-line bg-surface shadow-card', className)}>{children}</div>;
}

export function Skeleton({ className }: { className?: string }) {
  return (
    // Default rounding only when the caller didn't choose one (cn() doesn't merge conflicting classes).
    <div className={cn('relative overflow-hidden bg-fg/[0.06]', !/\brounded/.test(className ?? '') && 'rounded-md', className)}>
      <div className="absolute inset-0 -translate-x-full animate-shimmer bg-gradient-to-r from-transparent via-fg/[0.05] to-transparent" />
    </div>
  );
}

const alertTones = {
  success: { icon: CheckCircle2, cls: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200' },
  error: { icon: AlertCircle, cls: 'border-red-500/30 bg-red-500/10 text-red-800 dark:text-red-200' },
  info: { icon: Info, cls: 'border-line bg-subtle text-muted' },
  warning: { icon: AlertCircle, cls: 'border-amber-500/30 bg-amber-500/10 text-amber-900 dark:text-amber-200' },
};

export function Alert({
  tone = 'info',
  title,
  children,
  onClose,
  action,
}: {
  tone?: keyof typeof alertTones;
  title?: string;
  children?: ReactNode;
  onClose?: () => void;
  action?: ReactNode;
}) {
  const { icon: Icon, cls } = alertTones[tone];
  return (
    <div role={tone === 'error' ? 'alert' : 'status'} className={cn('flex animate-fade-in items-start gap-3 rounded-ctl border px-4 py-3 text-sm', cls)}>
      <Icon className="mt-0.5 h-4 w-4 shrink-0" />
      <div className="min-w-0 flex-1">
        {title ? <p className="font-semibold">{title}</p> : null}
        {children ? <div className={cn(title && 'mt-0.5', 'opacity-90')}>{children}</div> : null}
        {action ? <div className="mt-2">{action}</div> : null}
      </div>
      {onClose ? (
        <button type="button" onClick={onClose} className="-m-1 rounded p-1 opacity-60 hover:opacity-100" aria-label={t('dismiss', 'Dismiss')}>
          <X className="h-4 w-4" />
        </button>
      ) : null}
    </div>
  );
}

export function EmptyState({ icon: Icon, title, children, action }: { icon: LucideIcon; title: string; children?: ReactNode; action?: ReactNode }) {
  return (
    <div className="flex flex-col items-center px-6 py-14 text-center">
      <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand/10 text-brand">
        <Icon className="h-6 w-6" />
      </div>
      <p className="mt-4 text-base font-semibold text-fg">{title}</p>
      {children ? <p className="mt-1 max-w-sm text-sm text-muted">{children}</p> : null}
      {action ? <div className="mt-5">{action}</div> : null}
    </div>
  );
}

export function Avatar({ name, tone }: { name: string; tone: 'brand' | 'neutral' }) {
  return (
    <span
      aria-hidden="true"
      className={cn(
        'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold',
        tone === 'brand' ? 'bg-brand text-brand-fg' : 'bg-fg/10 text-fg',
      )}
    >
      {initials(name)}
    </span>
  );
}
