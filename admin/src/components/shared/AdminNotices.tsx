import { useEffect, useState } from 'react';
import { AlertCircle, CheckCircle2, Info, X, XCircle } from 'lucide-react';
import { getBootstrap } from '@/lib/bootstrap';
import { cn } from '@/lib/utils';

type NoticeType = 'success' | 'error' | 'warning' | 'info';

interface AdminNotice {
  message: string;
  type: NoticeType;
}

const noticeStyles: Record<NoticeType, string> = {
  success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
  error: 'border-red-200 bg-red-50 text-red-900',
  warning: 'border-amber-200 bg-amber-50 text-amber-900',
  info: 'border-sky-200 bg-sky-50 text-sky-900',
};

const noticeIcons: Record<NoticeType, typeof Info> = {
  success: CheckCircle2,
  error: XCircle,
  warning: AlertCircle,
  info: Info,
};

function normalizeType(type: string): NoticeType {
  if (type === 'success' || type === 'error' || type === 'warning' || type === 'info') {
    return type;
  }
  return 'info';
}

function getForeignNoticeHost(): HTMLElement | null {
  return document.getElementById('ls-admin-foreign-notices');
}

function isOurNotice(node: Element): boolean {
  return (
    node.classList.contains('ls-plugin-notice') ||
    node.classList.contains('ls-admin-notice-area') ||
    Boolean(node.closest('#ls-admin-foreign-notices')) ||
    Boolean(node.closest('.ls-admin-notice-area'))
  );
}

/**
 * Move stray WordPress / other-plugin notices into the reserved tray
 * so they cannot overflow into or break the LicenseSender layout.
 */
function relocateForeignNotices() {
  const host = getForeignNoticeHost();
  if (!host) {
    return;
  }

  const selectors = [
    '#wpbody-content > .notice',
    '#wpbody-content > .updated',
    '#wpbody-content > .error',
    '#wpbody-content > .update-nag',
    '.ls-admin-wrap > .notice',
    '.ls-admin-wrap > .updated',
    '.ls-admin-wrap > .error',
    '.ls-admin-wrap > .update-nag',
    '.ls-admin-app .notice:not(.ls-plugin-notice)',
    '.ls-admin-app .updated:not(.ls-plugin-notice)',
    '.ls-admin-app .error:not(.ls-plugin-notice)',
  ];

  selectors.forEach((selector) => {
    document.querySelectorAll(selector).forEach((node) => {
      if (!(node instanceof HTMLElement) || isOurNotice(node) || host.contains(node)) {
        return;
      }
      host.appendChild(node);
    });
  });
}

export function AdminNotices() {
  const bootstrap = getBootstrap();
  const [notices, setNotices] = useState<AdminNotice[]>(
    () =>
      (bootstrap.notices ?? []).map((notice) => ({
        message: notice.message,
        type: normalizeType(notice.type),
      })),
  );

  useEffect(() => {
    relocateForeignNotices();

    const host = getForeignNoticeHost();
    const body = document.getElementById('wpbody-content') || document.body;
    const observer = new MutationObserver(() => {
      relocateForeignNotices();
    });

    observer.observe(body, { childList: true, subtree: true });
    if (host) {
      observer.observe(host, { childList: true });
    }

    // Catch late-printed notices after other scripts finish.
    const timers = [0, 250, 1000].map((ms) => window.setTimeout(relocateForeignNotices, ms));

    return () => {
      observer.disconnect();
      timers.forEach((id) => window.clearTimeout(id));
    };
  }, []);

  const dismiss = (index: number) => {
    setNotices((current) => current.filter((_, i) => i !== index));
  };

  if (notices.length === 0) {
    return null;
  }

  return (
    <div className="ls-admin-notice-area mx-auto mb-4 max-w-[1400px] space-y-3" role="region" aria-label="Plugin notices">
      {notices.map((notice, index) => {
        const type = normalizeType(notice.type);
        const Icon = noticeIcons[type];

        return (
          <div
            key={`${notice.message}-${index}`}
            className={cn(
              'ls-plugin-notice notice flex items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-sm',
              noticeStyles[type],
            )}
          >
            <Icon className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
            <p className="flex-1 leading-relaxed">{notice.message}</p>
            <button
              type="button"
              className="rounded-md p-1 opacity-70 transition hover:bg-black/5 hover:opacity-100"
              onClick={() => dismiss(index)}
              aria-label="Dismiss notice"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        );
      })}
    </div>
  );
}
