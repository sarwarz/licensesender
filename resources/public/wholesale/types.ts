import type { BaseConfig } from '../support/types';

export interface Tier {
  min_qty: number;
  price: number;
}

export interface WsProduct {
  sku: string;
  name: string;
  image: string;
  /** Price per key from each quantity, lowest quantity first (already the buyer's group prices). */
  tiers: Tier[];
  base: number;
  minQty: number;
  /** 0 = no limit. */
  maxQty: number;
  stock: number;
  /** False when no WooCommerce product sells this SKU yet. */
  buyable: boolean;
}

export interface WsGroup {
  name: string;
  discount: number;
  minQty: number;
  minValue: number;
}

export interface WsOrder {
  id: number;
  number: string;
  date: string;
  status: string;
  total: string;
  url: string;
  /** Set while the order still needs paying. */
  payUrl: string;
  lines: Array<{ sku: string; name: string; qty: number }>;
}

export interface WsCurrency {
  code: string;
  symbol: string;
  position: 'left' | 'right' | 'left_space' | 'right_space' | string;
  decimals: number;
  decSep: string;
  thouSep: string;
  /** Base currency → display currency. */
  rate: number;
}

export interface WsApplication {
  status: 'pending' | 'approved' | 'rejected';
  note: string;
  submitted: string;
}

export interface WholesaleConfig extends BaseConfig {
  applyNonce: string;
  view: 'portal' | 'apply' | 'guest';
  enabled: boolean;
  wholesale: boolean;
  application: WsApplication | null;
  user: { name: string; email: string };
  /** Store login / register links for visitors (guest view only). */
  auth?: { login: string; register: string } | null;
  links: { catalog: string; apply: string; cart: string; checkout: string; orders: string };
  catalog?: { ok: boolean; message: string; products: WsProduct[] };
  group?: WsGroup | null;
  orders?: WsOrder[];
  currency?: WsCurrency;
  billing?: { name: string; company: string; email: string; country: string; editUrl: string };
  wallet?: { available: boolean; balance: number };
  settings?: { perPage: number; lowStock: number; minOrderQty: number; enforceStock: boolean };
}

export interface PlaceOrderResponse {
  order_id: number;
  number: string;
  paid: boolean;
  redirect: string;
}
