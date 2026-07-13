import { getBootstrap } from '@/lib/bootstrap';
import { cn } from '@/lib/utils';

interface PageHeaderProps {
  title: string;
  subtitle?: string;
  actions?: React.ReactNode;
  badge?: React.ReactNode;
  className?: string;
}

export function PageHeader({ title, subtitle, actions, badge, className }: PageHeaderProps) {
  const { logoUrl } = getBootstrap();

  return (
    <div
      className={cn(
        'mb-6 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm',
        className
      )}
    >
      <div className="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:py-5">
        <div className="flex min-w-0 items-center gap-3.5">
          {logoUrl ? (
            <img
              src={logoUrl}
              alt="LicenseSender"
              width={52}
              height={52}
              className="h-[52px] w-[52px] shrink-0 self-center rounded-xl object-cover shadow-sm ring-1 ring-slate-200/80"
            />
          ) : null}
          <div className="flex min-w-0 flex-col justify-center gap-0.5">
            <div className="flex flex-wrap items-center gap-2.5">
              <h1 className="text-xl font-semibold leading-4 tracking-tight text-slate-900 sm:text-2xl sm:leading-4">
                {title}
              </h1>
              {badge}
            </div>
            {subtitle ? (
              <p className="text-sm leading-snug text-slate-500">{subtitle}</p>
            ) : null}
          </div>
        </div>
        {actions ? <div className="flex shrink-0 flex-wrap items-center gap-2 sm:pl-2">{actions}</div> : null}
      </div>
    </div>
  );
}
