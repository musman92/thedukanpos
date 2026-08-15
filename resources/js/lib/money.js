let defaultMoneyConfig = null;

/**
 * Keep money formatting in sync with the company settings shared by Inertia.
 */
export function setDefaultMoneyConfig(config) {
    defaultMoneyConfig = config || null;
}

export function moneyDecimalPlaces(config = null) {
    const decimals = coerceMoneyNumber(
        config?.decimal_points ?? defaultMoneyConfig?.decimal_points,
        2,
    );

    return decimals >= 0 && decimals <= 4 ? Math.floor(decimals) : 2;
}

/**
 * Format an amount using the configured decimal places, without a currency symbol.
 */
export function formatAmount(amount, config = null) {
    const value = coerceMoneyNumber(amount);
    const dp = moneyDecimalPlaces(config);

    return value.toLocaleString(undefined, {
        minimumFractionDigits: dp,
        maximumFractionDigits: dp,
    });
}

/**
 * Format an amount for a numeric form field (no grouping separators).
 */
export function formatAmountInput(amount, config = null) {
    return coerceMoneyNumber(amount).toFixed(moneyDecimalPlaces(config));
}

/**
 * Quantities are not money: they keep up to 4 decimals but drop trailing
 * zeros, so whole units read as "12" rather than "12.0000".
 */
export function formatQuantity(quantity) {
    const value = coerceMoneyNumber(quantity);

    return value.toLocaleString(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 4,
    });
}

export function moneySymbol(config = null) {
    const resolvedConfig = config || defaultMoneyConfig;

    return String(
        resolvedConfig?.currency_symbol || resolvedConfig?.currency || '',
    ).trim();
}

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
    const resolvedConfig = config || defaultMoneyConfig;
    const formatted = formatAmount(amount, resolvedConfig);

    const symbol = moneySymbol(resolvedConfig);
    if (!symbol) {
        return formatted;
    }
    if (resolvedConfig?.currency_position === 'right') {
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
