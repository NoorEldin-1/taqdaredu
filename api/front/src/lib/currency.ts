import { useSiteSettings } from "@/hooks/useApi";

/**
 * Price formatting for the whole app.
 *
 * Prices used to be written as `${amount}` in a dozen places, which hardcoded
 * US dollars into an Arabic platform whose currency is an admin setting. This
 * reads `currency_symbol` from site settings instead, so changing the currency
 * in the admin panel changes it everywhere.
 *
 * Digits stay Western (`ar` numbering is `latn` by default in most Arabic
 * locales for prices) and are rendered with `tabular-nums` at the call sites so
 * figures line up in columns.
 */

/** Fallback only until site settings resolve — never a hardcoded currency. */
const PENDING_SYMBOL = "";

export const formatPrice = (
  amount: number | string | null | undefined,
  symbol: string
): string => {
  const value = typeof amount === "string" ? parseFloat(amount) : amount;
  if (value == null || Number.isNaN(value)) return "—";

  // Trim a trailing .00 so whole prices read as "120" not "120.00", but keep
  // real fractions intact.
  const shown = Number.isInteger(value) ? String(value) : value.toFixed(2);
  return symbol ? `${shown} ${symbol}` : shown;
};

export const useCurrency = () => {
  const { data } = useSiteSettings();
  const symbol = data?.data?.currency_symbol || PENDING_SYMBOL;

  return {
    symbol,
    /** Format an amount, or return "مجاناً" when it is free. */
    format: (amount: number | string | null | undefined) => formatPrice(amount, symbol),
    formatFree: (amount: number | string | null | undefined, isFree?: boolean) =>
      isFree || Number(amount) === 0 ? "مجاناً" : formatPrice(amount, symbol),
  };
};
