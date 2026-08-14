export default function PageHeader({ title, description = null, actions = null }) {
    if (!title && !actions) return null;

    return (
        <div className="mb-4 flex flex-col gap-3 sm:mb-5 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
            <div className="min-w-0">
                {title && (
                    <h1 className="text-xl font-bold tracking-tight text-theme-ink sm:text-2xl">
                        {title}
                    </h1>
                )}
                {description && (
                    <p className="mt-1 text-sm leading-5 text-theme-ink-soft">{description}</p>
                )}
            </div>
            {actions && (
                <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:shrink-0 sm:justify-end">
                    {actions}
                </div>
            )}
        </div>
    );
}
