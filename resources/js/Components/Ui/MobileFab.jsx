import { Link } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * Phone-only floating action button.
 *
 * One action renders a single button; multiple actions expand into a labelled
 * stack so page-header buttons can be dropped from the mobile layout.
 */
export default function MobileFab({ actions = [], label = 'Actions' }) {
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (!open) return undefined;

        const closeOnEscape = (e) => {
            if (e.key === 'Escape') setOpen(false);
        };
        document.addEventListener('keydown', closeOnEscape);

        return () => document.removeEventListener('keydown', closeOnEscape);
    }, [open]);

    if (actions.length === 0) return null;

    const fabClass =
        'inline-flex h-14 w-14 items-center justify-center rounded-full bg-[var(--color-primary)] text-[var(--color-on-primary)] shadow-lg shadow-black/20 transition active:scale-95';
    const anchorClass =
        'fixed bottom-[calc(4.75rem+env(safe-area-inset-bottom))] end-4 z-40 sm:hidden';

    if (actions.length === 1) {
        const [action] = actions;
        const Icon = action.icon || Plus;

        if (action.href) {
            return (
                <Link href={action.href} className={anchorClass} aria-label={action.label}>
                    <span className={fabClass}>
                        <Icon className="h-6 w-6" strokeWidth={2.25} />
                    </span>
                </Link>
            );
        }

        return (
            <div className={anchorClass}>
                <button
                    type="button"
                    onClick={action.onClick}
                    aria-label={action.label}
                    className={fabClass}
                >
                    <Icon className="h-6 w-6" strokeWidth={2.25} />
                </button>
            </div>
        );
    }

    return (
        <>
            {open && (
                <button
                    type="button"
                    aria-label="Close actions"
                    onClick={() => setOpen(false)}
                    className="fixed inset-0 z-30 bg-black/40 backdrop-blur-[1px] sm:hidden"
                />
            )}

            <div className={anchorClass}>
                {open && (
                    <div className="mb-3 flex flex-col items-end gap-2">
                        {actions.map((action) => {
                            const Icon = action.icon || Plus;
                            const content = (
                                <>
                                    <span className="rounded-lg bg-theme-surface px-2.5 py-1 text-sm font-medium text-theme-ink shadow">
                                        {action.label}
                                    </span>
                                    <span className="inline-flex h-11 w-11 items-center justify-center rounded-full bg-theme-surface text-theme-primary shadow-lg">
                                        <Icon className="h-5 w-5" strokeWidth={2} />
                                    </span>
                                </>
                            );

                            if (action.href) {
                                return (
                                    <Link
                                        key={action.key || action.label}
                                        href={action.href}
                                        onClick={() => setOpen(false)}
                                        className="flex items-center gap-2"
                                    >
                                        {content}
                                    </Link>
                                );
                            }

                            return (
                                <button
                                    key={action.key || action.label}
                                    type="button"
                                    onClick={() => {
                                        setOpen(false);
                                        action.onClick?.();
                                    }}
                                    className="flex items-center gap-2"
                                >
                                    {content}
                                </button>
                            );
                        })}
                    </div>
                )}

                <button
                    type="button"
                    onClick={() => setOpen((v) => !v)}
                    aria-expanded={open}
                    aria-label={label}
                    className={fabClass}
                >
                    {open ? (
                        <X className="h-6 w-6" strokeWidth={2.25} />
                    ) : (
                        <Plus className="h-6 w-6" strokeWidth={2.25} />
                    )}
                </button>
            </div>
        </>
    );
}
