export interface LsAdminNotice {
  message: string;
  type: 'success' | 'error' | 'warning' | 'info' | string;
}

export interface LsAdminBootstrap {
  restUrl: string;
  nonce: string;
  page: string;
  tab?: string;
  brandColor: string;
  pluginVersion?: string;
  logoUrl?: string;
  activationNonce?: string;
  ajaxUrl?: string;
  licenseId?: number;
  adminUrl: string;
  notices?: LsAdminNotice[];
  i18n: Record<string, string>;
  setup?: SetupStatus | null;
}

export interface SetupStatus {
  complete: boolean;
  api_key: string;
  autocomplete: boolean;
  send_email_redeem: boolean;
  manage_downloads: boolean;
  manage_guides: boolean;
  site_name: string;
  admin_email: string;
  dashboard_url: string;
  settings_url: string;
  products_url: string;
  account_url: string;
  docs_url: string;
  plugin_version: string;
}

declare global {
  interface Window {
    lsAdmin?: LsAdminBootstrap;
  }
}

export function getBootstrap(): LsAdminBootstrap {
  if (!window.lsAdmin) {
    throw new Error('licensesender admin bootstrap is missing.');
  }
  return window.lsAdmin;
}
