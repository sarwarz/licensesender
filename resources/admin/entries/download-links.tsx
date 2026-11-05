import { mountApp } from '@/lib/mount';
import { Toaster } from '@/components/ui/sonner';
import { DownloadLinksPage } from '@/components/download-links/DownloadLinksPage';

mountApp(() => (
  <>
    <DownloadLinksPage />
    <Toaster richColors position="top-right" />
  </>
));
