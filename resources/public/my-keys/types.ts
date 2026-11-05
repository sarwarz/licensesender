import type { BaseConfig } from '../support/types';

/**
 * ready   – keys are saved; "View keys"
 * fetch   – order is eligible; "Get keys" fetches them from LicenseSender
 * pending – order not completed yet
 * blocked – order can't receive LicenseSender keys (e.g. completed before delivery started)
 */
export type KeyState = 'ready' | 'fetch' | 'pending' | 'blocked';

export interface KeyItem {
  order_id: number;
  order_number: string;
  order_url: string;
  order_date: string;
  product_id: number;
  product_name: string;
  qty: number;
  thumb: string;
  state: KeyState;
  key_count: number;
  note: string;
}

export interface KeyPage {
  items: KeyItem[];
  page: number;
  total_pages: number;
}

export interface LicenseKey {
  id: number;
  key_value: string;
  download_link: string;
  activation_guide: string;
  sku: string;
  email: string;
  guide_url: string;
}

export interface KeysPayload {
  count: number;
  /** Keys the order should have (Get keys only); fewer than this means a partial delivery. */
  expected?: number;
  keys: LicenseKey[];
  meta: {
    download_link: string;
    activation_guide: string;
    sku: string;
    email: string;
    guide_url: string;
  };
}

/** Order details / thank-you License Keys section (owner, or guest with order key + email). */
export interface OrderKeysConfig extends BaseConfig {
  orderId: number;
  orderKey: string;
  email: string;
  exportUrl: string;
  canFetch: boolean;
  blockMessage: string;
  items: KeyItem[];
}

export interface MyKeysConfig extends BaseConfig {
  viewNonce: string;
  getNonce: string;
  shopUrl: string;
  initial: KeyPage;
}
