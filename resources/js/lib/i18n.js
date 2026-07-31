/**
 * Lightweight client i18n. Dictionaries live in /lang/*.json (shared with Laravel).
 */

import en from '../../../lang/en.json';
import ur from '../../../lang/ur.json';

const dictionaries = {
    en,
    ur,
};

let currentLocale = 'en';
let currentDir = 'ltr';

/**
 * @param {string|null|undefined} locale
 * @param {{ rtl?: boolean } | null} [meta]
 */
export function applyDocumentLocale(locale, meta = null) {
    const next = normalizeLocale(locale);
    currentLocale = next;
    currentDir = meta?.rtl === true || meta?.dir === 'rtl' ? 'rtl' : 'ltr';

    if (typeof document === 'undefined') {
        return { locale: currentLocale, dir: currentDir };
    }

    document.documentElement.lang = next === 'ur' ? 'ur' : 'en';
    document.documentElement.dir = currentDir;
    document.documentElement.setAttribute('data-locale', next);
    document.documentElement.setAttribute('data-rtl', currentDir === 'rtl' ? '1' : '0');

    return { locale: currentLocale, dir: currentDir };
}

/**
 * Sync from Inertia shared props.
 * @param {{ locale?: string, dir?: string, rtl?: boolean } | null | undefined} i18n
 */
export function syncLocaleFromPage(i18n) {
    return applyDocumentLocale(i18n?.locale || 'en', {
        rtl: i18n?.rtl === true,
        dir: i18n?.dir,
    });
}

export function getLocale() {
    return currentLocale;
}

export function getDir() {
    return currentDir;
}

export function isRtl() {
    return currentDir === 'rtl';
}

/**
 * Translate a key. Falls back to English, then the key itself.
 * Supports :param replacements: t('hello', { name: 'Ali' }) for "Hello :name"
 *
 * @param {string} key
 * @param {Record<string, string|number>|null} [replace]
 */
export function t(key, replace = null) {
    const dict = dictionaries[currentLocale] || dictionaries.en;
    let text = dict[key] ?? dictionaries.en[key] ?? key;

    if (replace && typeof text === 'string') {
        Object.entries(replace).forEach(([name, value]) => {
            text = text.replaceAll(`:${name}`, String(value));
        });
    }

    return text;
}

function normalizeLocale(locale) {
    const value = String(locale || 'en').toLowerCase().trim();
    return dictionaries[value] ? value : 'en';
}
