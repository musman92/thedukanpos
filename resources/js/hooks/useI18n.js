import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { getDir, getLocale, isRtl, t as translate } from '@/lib/i18n';

/**
 * React hook that re-renders translations when Inertia locale changes.
 */
export function useI18n() {
    const { i18n } = usePage().props;
    const locale = i18n?.locale || getLocale();
    const dir = i18n?.dir || getDir();

    return useMemo(
        () => ({
            t: translate,
            locale,
            dir,
            rtl: i18n?.rtl ?? isRtl(),
        }),
        [locale, dir, i18n?.rtl],
    );
}

export function t(key, replace = null) {
    return translate(key, replace);
}
