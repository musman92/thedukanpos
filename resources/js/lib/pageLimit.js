const ALLOWED = [10, 15, 25, 50];

export function allowedPageLimits() {
    return ALLOWED;
}

export function normalizePageLimit(value, fallback = 15) {
    const n = Number(value);
    if (ALLOWED.includes(n)) {
        return n;
    }

    if (fallback === null || fallback === undefined) {
        return null;
    }

    const fb = Number(fallback);

    return ALLOWED.includes(fb) ? fb : 15;
}

function storageKey(userId, pageKey) {
    return `dukanpos.pageLimit.${userId || 'guest'}.${pageKey}`;
}

export function getStoredPageLimit(userId, pageKey) {
    try {
        const raw = localStorage.getItem(storageKey(userId, pageKey));
        if (raw == null) return null;
        return normalizePageLimit(raw, null);
    } catch {
        return null;
    }
}

export function setStoredPageLimit(userId, pageKey, limit) {
    try {
        localStorage.setItem(storageKey(userId, pageKey), String(normalizePageLimit(limit)));
    } catch {
        // ignore
    }
}

/**
 * Resolve page limit: request wins if present; else stored preference; else company default.
 */
export function resolveClientPageLimit({ userId, pageKey, companyDefault, requestLimit }) {
    const company = normalizePageLimit(companyDefault, 15);
    if (requestLimit != null && requestLimit !== '') {
        return normalizePageLimit(requestLimit, company);
    }
    const stored = getStoredPageLimit(userId, pageKey);
    return stored ?? company;
}
