import { router, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useEffect, useRef } from 'react';
import {
    allowedPageLimits,
    getStoredPageLimit,
    normalizePageLimit,
    setStoredPageLimit,
} from '@/lib/pageLimit';

/**
 * Page limit selector. Saves choice per user + page in localStorage.
 * Falls back to company settings default when nothing stored.
 */
export default function PageLimitSelect({
    pageKey,
    routeName,
    current,
    companyDefault = 15,
    routeParams = {},
    extraQuery = {},
}) {
    const { auth } = usePage().props;
    const userId = auth?.user?.id;
    const appliedRef = useRef(false);
    const options = allowedPageLimits();
    const fallback = normalizePageLimit(companyDefault, 15);
    const value = normalizePageLimit(current, fallback);

    useEffect(() => {
        if (appliedRef.current) return;
        appliedRef.current = true;

        const stored = getStoredPageLimit(userId, pageKey);
        if (stored && stored !== value) {
            router.get(
                route(routeName, routeParams),
                { ...extraQuery, per_page: stored },
                { preserveState: true, replace: true },
            );
        }
        // Apply stored preference once on mount for this listing.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const onChange = (e) => {
        const next = normalizePageLimit(e.target.value, fallback);
        setStoredPageLimit(userId, pageKey, next);
        router.get(
            route(routeName, routeParams),
            { ...extraQuery, per_page: next },
            { preserveState: true },
        );
    };

    return (
        <div className="flex items-center gap-2 text-sm text-theme-ink-soft">
            <label htmlFor={`page-limit-${pageKey}`} className="whitespace-nowrap">
                Page limit
            </label>
            <div className="relative">
                <select
                    id={`page-limit-${pageKey}`}
                    value={value}
                    onChange={onChange}
                    className="dp-select-reset h-9 min-w-[4.5rem] rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-3 pr-8 text-sm font-medium text-theme-ink outline-none transition focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                >
                    {options.map((n) => (
                        <option key={n} value={n}>
                            {n}
                        </option>
                    ))}
                </select>
                <ChevronDown
                    className="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-theme-ink-muted"
                    strokeWidth={2}
                />
            </div>
        </div>
    );
}
