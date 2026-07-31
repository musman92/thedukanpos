/**
 * Format money using shared company settings from Inertia props.
 *
 * @param {number|string|null} amount
 * @param {{
 *   currency_symbol?: string,
 *   currency?: string,
 *   currency_position?: string,
 *   decimal_points?: number,
 * } | null} [config]
 */
export function formatMoney(amount, config = null) {
    const value = coerceMoneyNumber(amount);
    const decimals = coerceMoneyNumber(config?.decimal_points, 2);
    const dp = decimals >= 0 && decimals <= 6 ? Math.floor(decimals) : 2;

    const formatted = value.toLocaleString(undefined, {
        minimumFractionDigits: dp,
        maximumFractionDigits: dp,
    });

    const symbol = String(config?.currency_symbol || config?.currency || '').trim();
    if (!symbol) {
        return formatted;
    }
    if (config?.currency_position === 'right') {
        return `${formatted} ${symbol}`;
    }
    return `${symbol} ${formatted}`;
}

/**
 * Coerce chart ticks, proxied values, and plain inputs to a finite number.
 */
export function coerceMoneyNumber(input, fallback = 0) {
    if (typeof input === 'number') {
        return Number.isFinite(input) ? input : fallback;
    }
    if (typeof input === 'string' && input.trim() !== '') {
        const parsed = Number(input);
        return Number.isFinite(parsed) ? parsed : fallback;
    }
    if (input == null) {
        return fallback;
    }
    if (typeof input === 'object') {
        try {
            for (const key of ['value', 'amount', 'x', 'y', 'r']) {
                if (!(key in input)) {
                    continue;
                }
                const nested = input[key];
                if (typeof nested === 'number' || typeof nested === 'string') {
                    return coerceMoneyNumber(nested, fallback);
                }
            }
        } catch {
            return fallback;
        }
    }
    return fallback;
}
