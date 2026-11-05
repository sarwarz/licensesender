import { useRef, useState } from 'react';
import { PauseCircle } from 'lucide-react';
import { cn, t } from '../support/lib';
import { brandStyle, useSiteDark, useWide } from '../support/shell';
import type { ThemeMode } from '../support/types';
import { Card, EmptyState } from '../support/ui';
import { ApplyView } from './ApplyView';
import { config } from './lib';
import { PortalView } from './PortalView';
import type { WsApplication } from './types';

/**
 * Approved buyers get the ordering portal; everyone else the application (form or status).
 * The apply shortcode shows approved buyers a short "you're in" card instead.
 */
export function WholesaleApp({ host, theme }: { host: HTMLElement; theme: ThemeMode }) {
  const cfg = config();
  const rootRef = useRef<HTMLDivElement>(null);
  const dark = useSiteDark(host, theme);
  const wide = useWide(rootRef, 640);
  // Application screens sit beside an intro column, so they need more room.
  const roomy = useWide(rootRef, 860);
  const [application, setApplication] = useState<WsApplication | null>(cfg.application);

  let body;
  if (!cfg.enabled) {
    body = (
      <Card>
        <EmptyState icon={PauseCircle} title={t('disabledTitle', 'Wholesale is paused')}>
          {t('disabledText', 'Wholesale ordering is currently unavailable. Please check back later or contact us.')}
        </EmptyState>
      </Card>
    );
  } else if (cfg.wholesale && cfg.view === 'portal') {
    body = <PortalView wide={wide} />;
  } else {
    body = (
      <ApplyView
        wide={roomy}
        application={application}
        onSubmitted={(next, approved) => {
          // Auto-approved: reload so the portal gets its catalog.
          if (approved) window.location.reload();
          else setApplication(next);
        }}
      />
    );
  }

  return (
    <div ref={rootRef} className={cn('ls-root', dark && 'dark')} style={brandStyle()}>
      {body}
    </div>
  );
}
