import { mountApp } from '@/lib/mount';
import { Toaster } from '@/components/ui/sonner';
import { SettingsPage } from '@/components/settings/SettingsPage';

mountApp(() => (
  <>
    <SettingsPage />
    <Toaster richColors position="top-right" />
  </>
));
