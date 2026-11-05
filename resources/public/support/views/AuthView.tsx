import type { LucideIcon } from 'lucide-react';
import { BellRing, LifeBuoy, ListChecks, LogIn, Paperclip, ShieldCheck, UserPlus } from 'lucide-react';
import { t } from '../lib';
import type { InstanceProps } from '../types';
import { Card } from '../ui';

function Benefit({ icon: Icon, children }: { icon: LucideIcon; children: string }) {
  return (
    <li className="flex items-center gap-3 text-[13px] text-muted">
      <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand">
        <Icon className="h-3.5 w-3.5" />
      </span>
      {children}
    </li>
  );
}

/** Logged-out screen: links to the store's My Account page (where captcha and store security apply). */
export function AuthView({ auth }: { auth: InstanceProps['auth'] }) {
  return (
    <div className="flex justify-center py-6">
      <Card className="w-full max-w-[440px] overflow-hidden">
        <div className="px-6 pb-6 pt-8 text-center sm:px-8">
          <span className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-brand/10 text-brand ring-8 ring-brand/[0.04]">
            <LifeBuoy className="h-6 w-6" />
          </span>
          <h2 className="mt-5 text-xl font-semibold tracking-tight text-fg">{auth.title || t('signInTitle', 'Customer support')}</h2>
          <p className="mx-auto mt-1.5 max-w-[320px] text-sm leading-relaxed text-muted">
            {auth.lead || t('signInLead', 'Log in to open a ticket and follow the conversation with our team.')}
          </p>

          <ul className="mx-auto mt-6 max-w-[300px] space-y-2.5 text-left">
            <Benefit icon={ListChecks}>{t('benefitTrack', 'Track all your tickets in one place')}</Benefit>
            <Benefit icon={BellRing}>{t('benefitReplies', 'Get replies here and by email')}</Benefit>
            <Benefit icon={Paperclip}>{t('benefitScreens', 'Attach screenshots of the problem')}</Benefit>
          </ul>

          <div className="mt-7 space-y-2.5">
            <a
              href={auth.login}
              className="flex h-11 w-full items-center justify-center gap-2 rounded-ctl bg-brand text-sm font-semibold text-brand-fg no-underline shadow-sm transition-colors hover:bg-brand/90"
            >
              <LogIn className="h-4 w-4" />
              {t('logIn', 'Log in')}
            </a>
            {auth.register ? (
              <a
                href={auth.register}
                className="flex h-11 w-full items-center justify-center gap-2 rounded-ctl border border-line bg-surface text-sm font-semibold text-fg no-underline shadow-sm transition-colors hover:bg-subtle"
              >
                <UserPlus className="h-4 w-4" />
                {t('createAccount', 'Create an account')}
              </a>
            ) : null}
          </div>
        </div>

        <div className="flex items-start gap-2.5 border-t border-line bg-subtle px-6 py-3.5 text-left text-xs leading-relaxed text-muted sm:px-8">
          <ShieldCheck className="mt-px h-4 w-4 shrink-0 text-emerald-500" />
          <span>{t('signInNote', "You'll sign in securely on our account page, then come straight back here.")}</span>
        </div>
      </Card>
    </div>
  );
}
