import { getConfig } from '../support/lib';
import type { Tier, WholesaleConfig, WsProduct } from './types';

export const config = () => getConfig<WholesaleConfig>();

/** Price in the shop's display currency, formatted like WooCommerce does. */
export function money(amount: number): string {
  const cur = config().currency;
  if (!cur) return amount.toFixed(2);
  const value = amount * (cur.rate || 1);
  const [whole, frac] = Math.abs(value).toFixed(cur.decimals).split('.');
  const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, cur.thouSep);
  const number = (value < 0 ? '-' : '') + grouped + (frac ? cur.decSep + frac : '');
  switch (cur.position) {
    case 'right':
      return number + cur.symbol;
    case 'left_space':
      return `${cur.symbol} ${number}`;
    case 'right_space':
      return `${number} ${cur.symbol}`;
    default:
      return cur.symbol + number;
  }
}

/** Index of the tier a quantity falls in (−1 below the first one or for 0). */
export function tierIndex(tiers: Tier[], qty: number): number {
  let index = -1;
  tiers.forEach((tier, i) => {
    if (qty >= tier.min_qty) index = i;
  });
  return qty > 0 && index === -1 && tiers.length ? 0 : index;
}

/** Price per key at a quantity (below the first tier the first price applies). */
export function unitPrice(product: WsProduct, qty: number): number {
  if (!product.tiers.length) return product.base;
  return product.tiers[Math.max(0, tierIndex(product.tiers, qty))].price;
}

/** How many more keys of a product can be added, given what's already in the cart. */
export function addable(product: WsProduct, inCart: number): number {
  const enforce = config().settings?.enforceStock ?? true;
  const caps = [Number.POSITIVE_INFINITY];
  if (product.maxQty > 0) caps.push(product.maxQty - inCart);
  if (enforce) caps.push(product.stock - inCart);
  return Math.max(0, Math.min(...caps));
}

export interface ParsedLine {
  sku: string;
  qty: number;
}

/**
 * "SKU, qty" per line. Accepts commas, semicolons, tabs or spaces; skips headers and
 * blank lines; repeats of the same SKU add up.
 */
export function parseOrderText(text: string): { lines: ParsedLine[]; invalid: string[] } {
  const totals = new Map<string, number>();
  const invalid: string[] = [];
  for (const raw of text.split(/\r?\n/)) {
    const line = raw.trim();
    if (!line) continue;
    const parts = line.split(/[,;\t]|\s+/).map((p) => p.trim().replace(/^"|"$/g, '')).filter(Boolean);
    const qty = Number(parts[parts.length - 1]);
    const sku = parts[0];
    if (parts.length < 2 || !Number.isInteger(qty) || qty <= 0) {
      // A header row ("sku,quantity") is not worth an error.
      if (!/^(sku|product|code)\b/i.test(line)) invalid.push(line);
      continue;
    }
    totals.set(sku, (totals.get(sku) ?? 0) + qty);
  }
  return { lines: [...totals].map(([sku, qty]) => ({ sku, qty })), invalid };
}
