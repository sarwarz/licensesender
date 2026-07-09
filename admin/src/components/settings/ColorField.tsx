import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface ColorFieldProps {
  id: string;
  label: string;
  description?: string;
  value: string;
  fallback?: string;
  onChange: (value: string) => void;
}

export function ColorField({ id, label, description, value, fallback = '#4f46e5', onChange }: ColorFieldProps) {
  const colorValue = value || fallback;

  return (
    <div className="space-y-2 rounded-lg border border-slate-200 bg-white p-4">
      <Label htmlFor={id}>{label}</Label>
      {description ? <p className="text-xs text-muted-foreground">{description}</p> : null}
      <div className="flex gap-2">
        <Input
          id={id}
          type="color"
          className="h-10 w-14 shrink-0 cursor-pointer p-1"
          value={colorValue}
          onChange={(e) => onChange(e.target.value)}
        />
        <Input value={value} placeholder={fallback} onChange={(e) => onChange(e.target.value)} />
      </div>
    </div>
  );
}
