export default function PageHeader({ title, description = null, actions = null }) {
    if (!title && !actions) return null;

    return (
        <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
            <div className="min-w-0">
                {title && (
                    <h1 className="text-2xl font-bold tracking-tight text-theme-ink">{title}</h1>
                )}
                {description && (
                    <p className="mt-1 text-sm text-theme-ink-soft">{description}</p>
                )}
            </div>
            {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}
