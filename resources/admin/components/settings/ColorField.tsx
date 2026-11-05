import { useEffect, useRef, useState } from 'react';
import { Check, Pipette, RotateCcw } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/** The indigo LicenseSender theme first, then a few common brand colors. */
export const THEME_COLORS: { value: string; name: string }[] = [
  { value: '#4f46e5', name: 'Indigo (theme)' },
  { value: '#6366f1', name: 'Indigo light' },
  { value: '#7c3aed', name: 'Violet' },
  { value: '#2563eb', name: 'Blue' },
  { value: '#0ea5e9', name: 'Sky' },
  { value: '#059669', name: 'Emerald' },
  { value: '#e11d48', name: 'Rose' },
  { value: '#f59e0b', name: 'Amber' },
  { value: '#0f172a', name: 'Slate' },
];

const HEX = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i;

function normalize(value: string) {
  const v = value.trim().toLowerCase();
  if (/^#[0-9a-f]{3}$/.test(v)) {
    return `#${v[1]}${v[1]}${v[2]}${v[2]}${v[3]}${v[3]}`;
  }
  return v;
}

interface ColorFieldProps {
  id: string;
  label: string;
  description?: string;
  value: string;
  /** Color used when the field is empty, and what "Reset" restores. */
  defaultValue?: string;
  /** @deprecated use defaultValue */
  fallback?: string;
  presets?: { value: string; name: string }[];
  /** Draw the field inside its own bordered card. */
  bordered?: boolean;
  onChange: (value: string) => void;
}

export function ColorField({
  id,
  label,
  description,
  value,
  defaultValue,
  fallback,
  presets = THEME_COLORS,
  bordered = true,
  onChange,
}: ColorFieldProps) {
  const base = normalize(defaultValue || fallback || '#4f46e5');
  const current = HEX.test(value || '') ? normalize(value) : base;
  const [text, setText] = useState(value ? value.replace(/^#/, '') : '');
  const pickerRef = useRef<HTMLInputElement>(null);

  // Keep the text box in sync when the value changes elsewhere (swatch, reset, discard).
  useEffect(() => {
    setText(value ? value.replace(/^#/, '') : '');
  }, [value]);

  const [focused, setFocused] = useState(false);
  const invalid = !focused && text !== '' && text.length !== 3 && text.length !== 6;

  // Commit full 6-digit colors while typing; 3-digit shorthand only on blur, so "4f4…" isn't expanded mid-typing.
  const commitText = (raw: string) => {
    const cleaned = raw.replace(/[^0-9a-f]/gi, '').slice(0, 6);
    setText(cleaned);
    if (cleaned === '') {
      onChange('');
    } else if (cleaned.length === 6) {
      onChange(`#${cleaned.toLowerCase()}`);
    }
  };

  const onBlur = () => {
    setFocused(false);
    if (text.length === 3) {
      onChange(normalize(`#${text}`));
    }
  };

  return (
    <div className={cn('space-y-3', bordered && 'rounded-xl border border-slate-200 bg-white p-4')}>
      <div className="flex items-start justify-between gap-3">
        <div>
          <Label htmlFor={id}>{label}</Label>
          {description ? <p className="mt-1 text-xs text-muted-foreground">{description}</p> : null}
        </div>
        {value && normalize(value) !== base ? (
          <button
            type="button"
            onClick={() => onChange(base)}
            className="inline-flex shrink-0 items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-800"
            title={`Reset to ${base}`}
          >
            <RotateCcw className="h-3 w-3" /> Reset
          </button>
        ) : null}
      </div>

      <div className="flex items-center gap-3">
        <button
          type="button"
          onClick={() => pickerRef.current?.click()}
          className="group relative h-11 w-11 shrink-0 overflow-hidden rounded-xl shadow-sm ring-1 ring-black/10 transition-transform hover:scale-105 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
          style={{ backgroundColor: current }}
          aria-label="Open color picker"
        >
          <Pipette className="absolute inset-0 m-auto h-4 w-4 text-white opacity-0 drop-shadow transition-opacity group-hover:opacity-100" />
          <input
            ref={pickerRef}
            type="color"
            tabIndex={-1}
            aria-hidden
            className="pointer-events-none absolute inset-0 h-full w-full opacity-0"
            value={current}
            onChange={(e) => onChange(normalize(e.target.value))}
          />
        </button>

        <div
          className={cn(
            'flex h-11 min-w-0 flex-1 items-center rounded-lg border bg-white shadow-sm transition-colors focus-within:ring-2',
            invalid ? 'border-rose-300 focus-within:ring-rose-200' : 'border-input focus-within:border-indigo-400 focus-within:ring-indigo-100'
          )}
        >
          <span className="select-none pl-3 font-mono text-sm text-slate-400">#</span>
          <input
            id={id}
            value={text}
            onChange={(e) => commitText(e.target.value)}
            onBlur={onBlur}
            onFocus={() => setFocused(true)}
            placeholder={base.replace('#', '')}
            spellCheck={false}
            autoComplete="off"
            maxLength={7}
            className="h-full min-w-0 flex-1 border-0 bg-transparent px-1 font-mono text-sm uppercase text-slate-900 shadow-none outline-none placeholder:normal-case placeholder:text-slate-400 focus:ring-0"
            aria-invalid={invalid}
          />
          {!value ? <span className="pr-3 text-xs text-slate-400">default</span> : null}
        </div>
      </div>

      {invalid ? <p className="text-xs text-rose-600">Use a hex color like 4F46E5.</p> : null}

      {presets.length ? (
        <div className="flex flex-wrap gap-2" role="radiogroup" aria-label={`${label} presets`}>
          {presets.map((preset) => {
            const active = normalize(preset.value) === current;
            return (
              <button
                key={preset.value}
                type="button"
                role="radio"
                aria-checked={active}
                title={`${preset.name} · ${preset.value.toUpperCase()}`}
                onClick={() => onChange(normalize(preset.value))}
                className={cn(
                  'relative h-7 w-7 rounded-full ring-1 ring-black/10 transition-transform hover:scale-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2',
                  active && 'ring-2 ring-slate-900 ring-offset-2'
                )}
                style={{ backgroundColor: preset.value }}
              >
                {active ? <Check className="absolute inset-0 m-auto h-3.5 w-3.5 text-white drop-shadow" /> : null}
                <span className="sr-only">{preset.name}</span>
              </button>
            );
          })}
        </div>
      ) : null}
    </div>
  );
}
