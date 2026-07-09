import { mountApp } from '@/lib/mount';
import { Toaster } from '@/components/ui/sonner';
import { ActivationGuidesPage } from '@/components/activation-guides/ActivationGuidesPage';

mountApp(() => (
  <>
    <ActivationGuidesPage />
    <Toaster richColors position="top-right" />
  </>
));
