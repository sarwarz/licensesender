interface SettingsSectionProps {
  title: string;
  description?: string;
  children: React.ReactNode;
  singleColumn?: boolean;
}

export function SettingsSection({ title, description, children, singleColumn = false }: SettingsSectionProps) {
  return (
    <section className="space-y-4">
      <div>
        <h3 className="text-sm font-semibold text-foreground">{title}</h3>
        {description ? <p className="mt-1 text-xs text-muted-foreground">{description}</p> : null}
      </div>
      <div className={singleColumn ? 'space-y-4' : 'grid gap-4 md:grid-cols-2'}>{children}</div>
    </section>
  );
}
