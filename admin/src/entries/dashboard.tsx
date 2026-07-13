import { mountApp } from '@/lib/mount';
import { Toaster } from '@/components/ui/sonner';
import { DashboardPage } from '@/components/dashboard/DashboardPage';

mountApp(() => (
  <>
    <DashboardPage />
    <Toaster richColors position="top-right" />
  </>
));
