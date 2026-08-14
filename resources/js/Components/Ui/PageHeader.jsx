import { Info } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export default function PageHeader({
    title,
    description = null,
    actions = null,
    hideOnMobile = false,
}) {
    const [infoOpen, setInfoOpen] = useState(false);
    const infoRef = useRef(null);

    useEffect(() => {
        if (!infoOpen) return undefined;

        const closeOnOutside = (e) => {
            if (infoRef.current && !infoRef.current.contains(e.target)) {
                setInfoOpen(false);
            }
        };
        const closeOnEscape = (e) => {
            if (e.key === 'Escape') setInfoOpen(false);
        };

        document.addEventListener('mousedown', closeOnOutside);
        document.addEventListener('touchstart', closeOnOutside);
        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.removeEventListener('mousedown', closeOnOutside);
            document.removeEventListener('touchstart', closeOnOutside);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [infoOpen]);

    if (!title && !actions) return null;

    return (
        <div
            className={`mb-4 flex-wrap items-center justify-between gap-2 sm:mb-5 sm:flex sm:items-start sm:gap-3 ${
                hideOnMobile ? 'hidden' : 'flex'
            }`}
        >
            <div className="flex min-w-0 flex-1 items-center gap-1.5 sm:block">
                {title && (
                    <h1 className="truncate text-lg font-bold tracking-tight text-theme-ink sm:overflow-visible sm:whitespace-normal sm:text-2xl">
                        {title}
                    </h1>
                )}

                {description && (
                    <div ref={infoRef} className="relative shrink-0 sm:hidden">
                        <button
                            type="button"
                            onClick={() => setInfoOpen((open) => !open)}
                            aria-expanded={infoOpen}
                            aria-label="About this page"
                            className="inline-flex h-8 w-8 items-center justify-center rounded-full text-theme-ink-muted transition hover:bg-theme-bg hover:text-theme-ink"
                        >
                            <Info className="h-4 w-4" strokeWidth={2} />
                        </button>

                        {infoOpen && (
                            <div
                                role="dialog"
                                className="dp-card absolute start-0 top-full z-30 mt-1 w-64 p-3 text-sm leading-5 text-theme-ink-soft"
                            >
                                {description}
                            </div>
                        )}
                    </div>
                )}

                {description && (
                    <p className="mt-1 hidden text-sm leading-5 text-theme-ink-soft sm:block">
                        {description}
                    </p>
                )}
            </div>

            {actions && (
                <div className="dp-page-actions flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
                    {actions}
                </div>
            )}
        </div>
    );
}
