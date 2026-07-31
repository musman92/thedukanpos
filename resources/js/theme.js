const STORAGE_KEY = 'dukanpos.theme';
export const THEME_LIGHT = 'light';
export const THEME_DARK = 'dark';

export function getStoredTheme() {
    try {
        const value = localStorage.getItem(STORAGE_KEY);
        if (value === THEME_DARK || value === THEME_LIGHT) {
            return value;
        }
    } catch {
        // ignore
    }

    return THEME_LIGHT;
}

export function applyTheme(theme) {
    const next = theme === THEME_DARK ? THEME_DARK : THEME_LIGHT;
    document.documentElement.setAttribute('data-theme', next);
    return next;
}

export function persistTheme(theme) {
    const next = applyTheme(theme);
    try {
        localStorage.setItem(STORAGE_KEY, next);
    } catch {
        // ignore
    }
    return next;
}

export function initTheme() {
    return applyTheme(getStoredTheme());
}

export function toggleTheme(current) {
    const next = current === THEME_DARK ? THEME_LIGHT : THEME_DARK;
    return persistTheme(next);
}
