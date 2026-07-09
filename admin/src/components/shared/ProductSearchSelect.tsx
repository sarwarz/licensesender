import { useCallback, useEffect, useId, useRef, useState } from 'react';
import { Check, ChevronDown, Loader2, Search, X } from 'lucide-react';
import { apiRequest } from '@/api/client';
import { cn } from '@/lib/utils';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export interface ProductOption {
  id: number;
  name: string;
}

interface ProductSearchSelectProps {
  label?: string;
  value: number | null;
  selectedName?: string;
  onChange: (product: ProductOption | null) => void;
  placeholder?: string;
  disabled?: boolean;
}

export function ProductSearchSelect({
  label = 'Product',
  value,
  selectedName = '',
  onChange,
  placeholder = 'Search for a product…',
  disabled = false,
}: ProductSearchSelectProps) {
  const listId = useId();
  const rootRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState(selectedName);
  const [options, setOptions] = useState<ProductOption[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    setQuery(selectedName);
  }, [selectedName, value]);

  const fetchProducts = useCallback(async (q: string) => {
    if (q.trim().length < 2) {
      setOptions([]);
      return;
    }

    setLoading(true);
    try {
      const data = await apiRequest<{ items: ProductOption[] }>(
        `products/search?q=${encodeURIComponent(q.trim())}`
      );
      setOptions(data.items);
    } catch {
      setOptions([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!open) {
      return;
    }

    const timer = window.setTimeout(() => {
      fetchProducts(query);
    }, 300);

    return () => window.clearTimeout(timer);
  }, [query, open, fetchProducts]);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) {
        setOpen(false);
        if (value && selectedName) {
          setQuery(selectedName);
        }
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [value, selectedName]);

  const handleSelect = (product: ProductOption) => {
    onChange(product);
    setQuery(product.name);
    setOpen(false);
    setOptions([]);
  };

  const handleClear = () => {
    onChange(null);
    setQuery('');
    setOptions([]);
    inputRef.current?.focus();
  };

  const showDropdown = open && (loading || options.length > 0 || query.trim().length >= 2);

  return (
    <div className="space-y-2" ref={rootRef}>
      <Label htmlFor={listId}>{label}</Label>
      <div className="relative">
        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          ref={inputRef}
          id={listId}
          role="combobox"
          aria-expanded={open}
          aria-controls={`${listId}-listbox`}
          aria-autocomplete="list"
          disabled={disabled}
          value={query}
          placeholder={placeholder}
          className="bg-white pl-9 pr-16"
          onFocus={() => setOpen(true)}
          onChange={(e) => {
            setQuery(e.target.value);
            setOpen(true);
            if (value) {
              onChange(null);
            }
          }}
        />
        <div className="absolute right-2 top-1/2 flex -translate-y-1/2 items-center gap-1">
          {value ? (
            <button
              type="button"
              className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
              onClick={handleClear}
              aria-label="Clear product"
            >
              <X className="h-4 w-4" />
            </button>
          ) : null}
          <ChevronDown className="h-4 w-4 text-muted-foreground" />
        </div>

        {showDropdown ? (
          <div
            id={`${listId}-listbox`}
            role="listbox"
            className="absolute left-0 right-0 top-full z-[100002] mt-1 max-h-48 overflow-auto rounded-md border border-slate-200 bg-white shadow-lg"
          >
            {loading ? (
              <div className="flex items-center gap-2 px-3 py-2.5 text-sm text-muted-foreground">
                <Loader2 className="h-4 w-4 animate-spin" />
                Searching…
              </div>
            ) : options.length > 0 ? (
              options.map((product) => (
                <button
                  key={product.id}
                  type="button"
                  role="option"
                  aria-selected={value === product.id}
                  className={cn(
                    'flex w-full items-center justify-between px-3 py-2.5 text-left text-sm hover:bg-slate-50',
                    value === product.id && 'bg-slate-50'
                  )}
                  onMouseDown={(e) => e.preventDefault()}
                  onClick={() => handleSelect(product)}
                >
                  <span>{product.name}</span>
                  {value === product.id ? <Check className="h-4 w-4 text-primary" /> : null}
                </button>
              ))
            ) : (
              <div className="px-3 py-2.5 text-sm text-muted-foreground">
                {query.trim().length < 2 ? 'Type at least 2 characters to search.' : 'No products found.'}
              </div>
            )}
          </div>
        ) : null}
      </div>
    </div>
  );
}
