import PlatformLayout from '@/Layouts/PlatformLayout';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Boxes, Building2, Puzzle } from 'lucide-react';

function VersionBadge({ version }) {
    return (
        <span className="rounded-full bg-theme-bg px-2 py-0.5 text-[11px] font-semibold text-theme-ink-muted ring-1 ring-theme-border">
            v{version}
        </span>
    );
}

export default function Index({ addons = [], total = 0 }) {
    return (
        <PlatformLayout
            title="Addons"
            description="Catalog of optional modules you can enable per company. Use these pages when explaining upsells to customers."
        >
            <Head title="Addons" />

            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm text-theme-ink-muted">
                <span className="inline-flex items-center gap-1.5">
                    <Puzzle className="h-4 w-4" strokeWidth={1.75} />
                    {total} available
                </span>
                <span className="hidden sm:inline">·</span>
                <span>Install/remove on a company from Tenants → company → Addons tab.</span>
            </div>

            {addons.length === 0 ? (
                <div className="dp-card p-8 text-center text-sm text-theme-ink-muted">
                    No addons found under <code className="text-xs">addons/</code>.
                </div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {addons.map((addon) => (
                        <Link
                            key={addon.slug}
                            href={addon.url}
                            className="dp-card group flex flex-col p-5 transition hover:border-theme-primary/35 hover:shadow-sm"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <h2 className="truncate text-base font-semibold text-theme-ink group-hover:text-theme-primary">
                                        {addon.name}
                                    </h2>
                                    <p className="mt-1 font-mono text-[11px] text-theme-ink-muted">
                                        {addon.slug}
                                    </p>
                                </div>
                                <VersionBadge version={addon.version} />
                            </div>

                            <p className="mt-3 line-clamp-3 flex-1 text-sm leading-relaxed text-theme-ink-soft">
                                {addon.description}
                            </p>

                            {addon.highlights?.length > 0 && (
                                <ul className="mt-3 space-y-1 text-xs text-theme-ink-muted">
                                    {addon.highlights.map((item) => (
                                        <li key={item} className="flex gap-2">
                                            <span className="text-theme-primary">•</span>
                                            <span className="line-clamp-2">{item}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}

                            <div className="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-theme-border pt-4 text-xs text-theme-ink-muted">
                                <span className="inline-flex items-center gap-1">
                                    <Building2 className="h-3.5 w-3.5" strokeWidth={1.75} />
                                    {addon.active_tenant_count} active
                                </span>
                                <span className="inline-flex items-center gap-1">
                                    <Boxes className="h-3.5 w-3.5" strokeWidth={1.75} />
                                    {addon.screen_count} screen{addon.screen_count === 1 ? '' : 's'}
                                </span>
                                <span className="inline-flex items-center gap-1 font-semibold text-theme-primary">
                                    View details
                                    <ArrowRight className="h-3.5 w-3.5 transition group-hover:translate-x-0.5" />
                                </span>
                            </div>
                        </Link>
                    ))}
                </div>
            )}
        </PlatformLayout>
    );
}
