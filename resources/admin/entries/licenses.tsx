import { mountApp } from '@/lib/mount';
import { Toaster } from '@/components/ui/sonner';
import { LicensesPage } from '@/components/licenses/LicensesPage';

mountApp(() => (
  <>
    <LicensesPage />
    <Toaster richColors position="top-right" />
  </>
));
