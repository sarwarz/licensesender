export interface LsAdminBootstrap {
  restUrl: string;
  nonce: string;
  page: string;
  tab?: string;
  brandColor: string;
  activationNonce?: string;
  ajaxUrl?: string;
  licenseId?: number;
  adminUrl: string;
  i18n: Record<string, string>;
}

declare global {
  interface Window {
    lsAdmin?: LsAdminBootstrap;
  }
}

export function getBootstrap(): LsAdminBootstrap {
  if (!window.lsAdmin) {
    throw new Error('License Shipper admin bootstrap is missing.');
  }
  return window.lsAdmin;
}
