import { useEffect, useMemo, useRef, useState } from 'react';
import { format, isValid, parse } from 'date-fns';
import { Calendar as CalendarIcon, ChevronDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { cn } from '@/lib/utils';

type DatePickerProps = {
  id?: string;
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  className?: string;
};

function parseIsoDate(value: string): Date | undefined {
  if (!value) return undefined;
  const parsed = parse(value, 'yyyy-MM-dd', new Date());
  return isValid(parsed) ? parsed : undefined;
}

export function DatePicker({
  id,
  value,
  onChange,
  placeholder = 'Select a date',
  className,
}: DatePickerProps) {
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);
  const selected = useMemo(() => parseIsoDate(value), [value]);

  useEffect(() => {
    if (!open) return;

    const onPointerDown = (event: MouseEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) {
        setOpen(false);
      }
    };

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpen(false);
      }
    };

    document.addEventListener('mousedown', onPointerDown);
    document.addEventListener('keydown', onKeyDown);
    return () => {
      document.removeEventListener('mousedown', onPointerDown);
      document.removeEventListener('keydown', onKeyDown);
    };
  }, [open]);

  return (
    <div ref={rootRef} className={cn('relative w-full max-w-xs', className)}>
      <Button
        id={id}
        type="button"
        variant="outline"
        aria-expanded={open}
        aria-haspopup="dialog"
        className={cn(
          'h-10 w-full justify-between gap-2 rounded-lg border-slate-200 bg-white px-3 text-left font-normal shadow-sm transition-colors hover:bg-slate-50',
          open && 'border-slate-300 ring-2 ring-slate-900/10',
          !selected && 'text-muted-foreground'
        )}
        onClick={() => setOpen((prev) => !prev)}
      >
        <span className="flex min-w-0 items-center gap-2">
          <CalendarIcon className="h-4 w-4 shrink-0 text-slate-500" aria-hidden />
          <span className="truncate">{selected ? format(selected, 'MMM d, yyyy') : placeholder}</span>
        </span>
        <ChevronDown
          className={cn('h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200', open && 'rotate-180')}
          aria-hidden
        />
      </Button>

      <div
        className={cn(
          'grid transition-[grid-template-rows,opacity] duration-200 ease-out',
          open ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'
        )}
      >
        <div className="overflow-hidden">
          <div
            className={cn(
              'mt-2 origin-top rounded-xl border border-slate-200 bg-white shadow-sm transition-transform duration-200 ease-out',
              open ? 'translate-y-0' : '-translate-y-1'
            )}
            role="dialog"
            aria-label="Choose date"
          >
            <Calendar
              mode="single"
              selected={selected}
              defaultMonth={selected}
              onSelect={(date) => {
                if (!date) return;
                onChange(format(date, 'yyyy-MM-dd'));
                setOpen(false);
              }}
            />
          </div>
        </div>
      </div>
    </div>
  );
}
