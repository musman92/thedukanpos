/**
 * Cart line tax/total helpers for POS.
 */

export function lineGross(line) {
    return Number(line.quantity || 0) * Number(line.unit_price || 0) - Number(line.discount || 0);
}

/**
 * @param {'fixed'|'percent'} [discountMode]
 */
export function resolveCartDiscount(subtotal, tax, discountValue = 0, discountMode = 'fixed') {
    const base = Math.max(0, Number(subtotal || 0) + Number(tax || 0));
    const raw = Math.max(0, Number(discountValue || 0));

    if (discountMode === 'percent') {
        return Math.min(base, (base * raw) / 100);
    }

    return Math.min(base, raw);
}

/**
 * @param {'fixed'|'percent'} [discountMode]
 */
export function cartTotals(cart, discountValue = 0, discountMode = 'fixed') {
    let subtotal = 0;
    let tax = 0;
    const rates = new Set();

    for (const line of cart) {
        const gross = lineGross(line);
        const rate = line.tax?.rate ? Number(line.tax.rate) : 0;
        if (rate > 0) rates.add(rate);
        if (line.tax?.is_inclusive && rate) {
            const net = gross / (1 + rate / 100);
            tax += gross - net;
            subtotal += net;
        } else {
            tax += gross * (rate / 100);
            subtotal += gross;
        }
    }

    const discount = resolveCartDiscount(subtotal, tax, discountValue, discountMode);
    const total = Math.max(0, subtotal + tax - discount);

    let taxRateLabel = null;
    if (rates.size === 1) {
        taxRateLabel = [...rates][0];
    }

    return {
        subtotal,
        tax,
        discount,
        total,
        taxRateLabel,
    };
}

export function pickExactProduct(results, query) {
    const q = String(query || '').trim();
    if (!q || !results?.length) return null;

    const exactBarcode = results.find(
        (p) => String(p.barcode || '').trim().toLowerCase() === q.toLowerCase(),
    );
    if (exactBarcode) return exactBarcode;

    const exactCode = results.find(
        (p) => String(p.short_code || '').trim().toLowerCase() === q.toLowerCase(),
    );
    if (exactCode) return exactCode;

    if (results.length === 1) return results[0];

    return null;
}
