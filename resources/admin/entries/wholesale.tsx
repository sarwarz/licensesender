import { mountApp } from '@/lib/mount';
import { Toaster } from '@/components/ui/sonner';
import { WholesalePage } from '@/components/wholesale/WholesalePage';

mountApp(() => (
  <>
    <WholesalePage />
    <Toaster richColors position="top-right" />
  </>
));
