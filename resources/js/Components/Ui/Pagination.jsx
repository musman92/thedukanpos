import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

function pageWindow(current, last) {
    if (last <= 7) {
        return Array.from({ length: last }, (_, i) => i + 1);
    }

    const pages = new Set([1, last, current, current - 1, current + 1]);
    if (current <= 3) {
        pages.add(2);
        pages.add(3);
        pages.add(4);
    }
    if (current >= last - 2) {
        pages.add(last - 1);
        pages.add(last - 2);
        pages.add(last - 3);
    }

    return [...pages].filter((p) => p >= 1 && p <= last).sort((a, b) => a - b);
}

function PaginationLinks({ paginator }) {
    if (!paginator || paginator.last_page <= 1) {
        return null;
    }

    const current = paginator.current_page;
    const last = paginator.last_page;
    const pages = pageWindow(current, last);

    const go = (url) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    const pageUrl = (page) => {
        const link = (paginator.links || []).find((l) => String(l.label) === String(page));
        return link?.url || null;
    };

    return (
        <div className="flex w-full items-center justify-between gap-1 sm:w-auto sm:justify-end">
            <button
                type="button"
                disabled={!paginator.prev_page_url}
                onClick={() => go(paginator.prev_page_url)}
                className="inline-flex min-h-11 items-center gap-1 rounded-md px-2.5 py-1.5 text-sm text-theme-ink-soft hover:bg-theme-bg disabled:cursor-not-allowed disabled:opacity-40"
            >
                <ChevronLeft className="h-4 w-4" strokeWidth={2} />
                <span className="hidden sm:inline">Previous</span>
            </button>

            {pages.map((page, idx) => {
                const prev = pages[idx - 1];
                const showEllipsis = idx > 0 && page - prev > 1;
                return (
                    <span
                        key={page}
                        className={`${page === current ? 'inline-flex' : 'hidden sm:inline-flex'} items-center gap-1`}
                    >
                        {showEllipsis && (
                            <span className="hidden px-1 text-theme-ink-muted sm:inline">…</span>
                        )}
                        <button
                            type="button"
                            onClick={() => go(pageUrl(page))}
                            disabled={page === current || !pageUrl(page)}
                            className={`min-h-11 min-w-11 rounded-md px-2 py-1.5 text-sm ${
                                page === current
                                    ? 'bg-theme-primary font-semibold text-[var(--color-on-primary)]'
                                    : 'text-theme-ink-soft hover:bg-theme-bg'
                            }`}
                        >
                            {page}
                        </button>
                    </span>
                );
            })}

            <button
                type="button"
                disabled={!paginator.next_page_url}
                onClick={() => go(paginator.next_page_url)}
                className="inline-flex min-h-11 items-center gap-1 rounded-md px-2.5 py-1.5 text-sm text-theme-ink-soft hover:bg-theme-bg disabled:cursor-not-allowed disabled:opacity-40"
            >
                <span className="hidden sm:inline">Next</span>
                <ChevronRight className="h-4 w-4" strokeWidth={2} />
            </button>
        </div>
    );
}

/**
 * Table footer: "Showing x–y of z" on the left, page links on the right.
 * Pass `pageLimit` to keep the rows-per-page control beside the count.
 */
export default function Pagination({ paginator, pageLimit = null }) {
    if (!paginator) return null;

    return (
        <div className="flex flex-col gap-2 border-t border-theme-border px-3 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-4">
            <div className="flex w-full items-center justify-between gap-3 sm:w-auto sm:justify-start sm:gap-4">
                <p className="text-xs text-theme-ink-muted sm:text-sm">
                    Showing {paginator.from || 0}-{paginator.to || 0} of {paginator.total}
                </p>
                {pageLimit}
            </div>
            <PaginationLinks paginator={paginator} />
        </div>
    );
}
