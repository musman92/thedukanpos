import { X } from 'lucide-react';
import { useEffect } from 'react';

/**
 * Right-side drawer. Default ~50% width on desktop, full width on small screens.
 */
export default function Drawer({
    open,
    onClose,
    title,
    description = null,
    children,
    width = 'half',
    bodyClassName = 'overflow-y-auto',
}) {
    useEffect(() => {
        if (!open) return undefined;
        const onKey = (e) => {
            if (e.key === 'Escape') onClose();
        };
        document.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        };
    }, [open, onClose]);

    if (!open) return null;

    const widthClass =
        width === 'sm'
            ? 'w-full sm:max-w-md'
            : width === 'wide'
              ? 'w-full sm:w-[94%] lg:w-[90%]'
              : width === 'xl'
                ? 'w-full sm:w-[88%] lg:w-[80%]'
                : width === 'lg'
                  ? 'w-full sm:w-[70%]'
                  : width === '3/4' || width === '75'
                    ? 'w-full sm:w-3/4'
                    : 'w-full sm:w-1/2';

    return (
        <div className="fixed inset-0 z-50 flex justify-end">
            <div
                className="absolute inset-0 bg-black/40 backdrop-blur-[1px]"
                aria-hidden="true"
            />
            <aside
                className={`relative flex h-full ${widthClass} animate-[dp-drawer-in_0.2s_ease-out] flex-col border-l border-theme-border bg-theme-surface shadow-card`}
                role="dialog"
                aria-modal="true"
                aria-label={title}
            >
                <div className="flex items-start justify-between gap-3 border-b border-theme-border px-5 py-4">
                    <div className="min-w-0">
                        <h2 className="text-lg font-semibold text-theme-ink">{title}</h2>
                        {description && (
                            <p className="mt-0.5 text-sm text-theme-ink-soft">{description}</p>
                        )}
                    </div>
                    <button type="button" className="dp-icon-btn shrink-0" onClick={onClose}>
                        <X className="h-4 w-4" />
                    </button>
                </div>
                <div className={`min-h-0 flex-1 px-5 py-5 ${bodyClassName}`}>{children}</div>
            </aside>
        </div>
    );
}
