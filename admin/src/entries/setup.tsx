import { mountApp } from '@/lib/mount';
import { Toaster } from '@/components/ui/sonner';
import { SetupWizardPage } from '@/components/setup/SetupWizardPage';

mountApp(() => (
  <>
    <SetupWizardPage />
    <Toaster richColors position="top-right" />
  </>
));
