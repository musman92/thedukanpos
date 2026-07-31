/**
 * Per-tab branch context.
 *
 * PHP sessions are shared across tabs; sessionStorage is not.
 * Each tab stores its branch and sends X-Branch-Id on every request.
 */

export const BRANCH_HEADER = 'X-Branch-Id';
const STORAGE_KEY = 'dukanpos.current_branch_id';

export function getTabBranchId() {
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        if (!raw) return null;
        const id = Number(raw);
        return Number.isFinite(id) && id > 0 ? id : null;
    } catch {
        return null;
    }
}

export function setTabBranchId(branchId) {
    const id = Number(branchId);
    if (!Number.isFinite(id) || id <= 0) return;

    try {
        sessionStorage.setItem(STORAGE_KEY, String(id));
    } catch {
        // Ignore private-mode / blocked storage.
    }

    if (typeof window !== 'undefined' && window.axios) {
        window.axios.defaults.headers.common[BRANCH_HEADER] = String(id);
    }
}

export function clearTabBranchId() {
    try {
        sessionStorage.removeItem(STORAGE_KEY);
    } catch {
        //
    }

    if (typeof window !== 'undefined' && window.axios?.defaults?.headers?.common) {
        delete window.axios.defaults.headers.common[BRANCH_HEADER];
    }
}

export function branchHeaders(extra = {}) {
    const id = getTabBranchId();
    if (!id) return { ...extra };

    return {
        ...extra,
        [BRANCH_HEADER]: String(id),
    };
}

/**
 * Wire axios + Inertia so every request carries this tab's branch.
 */
export function installBranchTabTransport(router) {
    const id = getTabBranchId();
    if (id && window.axios) {
        window.axios.defaults.headers.common[BRANCH_HEADER] = String(id);
    }

    if (window.axios) {
        window.axios.interceptors.request.use((config) => {
            const branchId = getTabBranchId();
            if (branchId) {
                config.headers = config.headers || {};
                config.headers[BRANCH_HEADER] = String(branchId);
            }
            return config;
        });
    }

    if (router?.on) {
        router.on('before', (event) => {
            const branchId = getTabBranchId();
            if (!branchId) return;

            event.detail.visit.headers = {
                ...event.detail.visit.headers,
                [BRANCH_HEADER]: String(branchId),
            };
        });
    }
}

/**
 * Align tab storage with the branch the server rendered.
 * If this tab already had a different branch, reload once with the header.
 */
export function syncTabBranchFromPage(pageBranch, router) {
    if (!pageBranch?.id) return;

    const tabId = getTabBranchId();

    if (!tabId) {
        setTabBranchId(pageBranch.id);
        return;
    }

    if (Number(tabId) === Number(pageBranch.id)) {
        setTabBranchId(tabId);
        return;
    }

    // Hard refresh rendered session branch, but this tab wants another.
    const flagKey = 'dukanpos.branch_resync';
    try {
        if (sessionStorage.getItem(flagKey) === String(tabId)) {
            sessionStorage.removeItem(flagKey);
            setTabBranchId(tabId);
            return;
        }
        sessionStorage.setItem(flagKey, String(tabId));
    } catch {
        setTabBranchId(tabId);
        return;
    }

    setTabBranchId(tabId);
    router.reload({
        headers: { [BRANCH_HEADER]: String(tabId) },
        replace: true,
    });
}
