/**
 * Shared catalog picker matching (name / short code / barcode).
 */

export function optionSearchText(option) {
    return [option?.label, option?.meta, option?.keywords].filter(Boolean).join(' ');
}

export function filterOptions(options, query) {
    const q = String(query || '')
        .trim()
        .toLowerCase();
    if (!q) return options;
    return options.filter((o) => optionSearchText(o).toLowerCase().includes(q));
}

export function keywordTokens(option) {
    return String(option?.keywords || '')
        .split(/\s+/)
        .map((t) => t.trim().toLowerCase())
        .filter(Boolean);
}

export function pickExactOption(options, query) {
    const q = String(query || '')
        .trim()
        .toLowerCase();
    if (!q || !options?.length) return null;

    const exactKeyword = options.find((o) => keywordTokens(o).includes(q));
    if (exactKeyword) return exactKeyword;

    const exactLabel = options.find(
        (o) =>
            String(o.label || '')
                .trim()
                .toLowerCase() === q,
    );
    if (exactLabel) return exactLabel;

    const filtered = filterOptions(options, q);
    if (filtered.length === 1) return filtered[0];

    return null;
}

export function catalogKeywords(...values) {
    return [...new Set(values.map((v) => String(v || '').trim()).filter(Boolean))].join(
        ' ',
    );
}

export function lineMatchesQuery(line, query) {
    const q = String(query || '')
        .trim()
        .toLowerCase();
    if (!q) return true;
    return [line?.product_name, line?.variant_name, line?.short_code, line?.barcode]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()
        .includes(q);
}

export function pickExactLine(lines, query, getMeta = (line) => line) {
    const q = String(query || '')
        .trim()
        .toLowerCase();
    if (!q || !lines?.length) return -1;

    const exact = lines.findIndex((line) => {
        const meta = getMeta(line) || {};
        return [meta.barcode, meta.short_code].some(
            (v) =>
                String(v || '')
                    .trim()
                    .toLowerCase() === q,
        );
    });
    if (exact >= 0) return exact;

    const filtered = lines
        .map((line, index) => ({ line, index }))
        .filter(({ line }) => lineMatchesQuery(getMeta(line), q));
    if (filtered.length === 1) return filtered[0].index;

    return -1;
}
