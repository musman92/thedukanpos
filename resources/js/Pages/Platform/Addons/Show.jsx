import PlatformLayout from '@/Layouts/PlatformLayout';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    CheckCircle2,
    KeyRound,
    LayoutGrid,
    Puzzle,
    Shield,
} from 'lucide-react';

function Section({ title, icon: Icon, children }) {
    return (
        <section className="dp-card p-5">
            <h2 className="flex items-center gap-2 text-sm font-semibold text-theme-ink">
                {Icon && <Icon className="h-4 w-4 text-theme-primary" strokeWidth={1.75} />}
                {title}
            </h2>
            <div className="mt-3">{children}</div>
        </section>
    );
}

function groupLabel(group) {
    if (!group) return 'General';
    return group.charAt(0).toUpperCase() + group.slice(1);
}

export default function Show({ addon, tenants = [] }) {
    return (
        <PlatformLayout
            title={addon.name}
            description={`Addon catalog · ${addon.slug}`}
            actions={
                <Link
                    href={route('platform.addons.index')}
                    className="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-theme-border bg-theme-surface px-3 text-sm font-medium text-theme-ink-soft transition hover:border-theme-primary/35 hover:text-theme-ink"
                >
                    <ArrowLeft className="h-4 w-4" strokeWidth={1.75} />
                    All addons
                </Link>
            }
        >
            <Head title={`${addon.name} addon`} />

            <div className="space-y-4">
                <div className="dp-card p-5">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                                Sales brief
                            </p>
                            <h1 className="mt-1 text-2xl font-bold text-theme-ink">{addon.name}</h1>
                            <p className="mt-2 max-w-3xl text-sm leading-relaxed text-theme-ink-soft">
                                {addon.description}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <span className="rounded-full bg-theme-bg px-2.5 py-1 text-xs font-semibold text-theme-ink-muted ring-1 ring-theme-border">
                                v{addon.version}
                            </span>
                            {addon.requires_core && (
                                <span className="rounded-full bg-sky-500/10 px-2.5 py-1 text-xs font-semibold text-sky-800">
                                    Core {addon.requires_core}
                                </span>
                            )}
                            <span className="rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-800">
                                {addon.active_tenant_count} active tenant
                                {addon.active_tenant_count === 1 ? '' : 's'}
                            </span>
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    {addon.highlights?.length > 0 && (
                        <Section title="Key benefits" icon={CheckCircle2}>
                            <ul className="space-y-2 text-sm text-theme-ink-soft">
                                {addon.highlights.map((item) => (
                                    <li key={item} className="flex gap-2">
                                        <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" strokeWidth={1.75} />
                                        <span>{item}</span>
                                    </li>
                                ))}
                            </ul>
                        </Section>
                    )}

                    {addon.nav?.length > 0 && (
                        <Section title="What the customer gets" icon={LayoutGrid}>
                            <p className="mb-3 text-sm text-theme-ink-muted">
                                Screens and menu items added to the company admin when this addon is installed.
                            </p>
                            <ul className="space-y-2">
                                {addon.nav.map((item) => (
                                    <li
                                        key={`${item.route}-${item.label}`}
                                        className="rounded-lg border border-theme-border bg-theme-bg/60 px-3 py-2"
                                    >
                                        <p className="text-sm font-medium text-theme-ink">{item.label}</p>
                                        <p className="mt-0.5 text-xs text-theme-ink-muted">
                                            {groupLabel(item.group)} · route {item.route}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </Section>
                    )}
                </div>

                {addon.readme_excerpt && (
                    <Section title="More detail" icon={Puzzle}>
                        <p className="whitespace-pre-wrap text-sm leading-relaxed text-theme-ink-soft">
                            {addon.readme_excerpt}
                        </p>
                    </Section>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    {addon.permissions?.length > 0 && (
                        <Section title="Permissions" icon={KeyRound}>
                            <p className="mb-3 text-sm text-theme-ink-muted">
                                Access keys merged into roles when the addon is active.
                            </p>
                            <ul className="flex flex-wrap gap-2">
                                {addon.permissions.map((perm) => (
                                    <li
                                        key={perm}
                                        className="rounded-md bg-theme-bg px-2 py-1 font-mono text-xs text-theme-ink-soft ring-1 ring-theme-border"
                                    >
                                        {perm}
                                    </li>
                                ))}
                            </ul>
                        </Section>
                    )}

                    <Section title="How to enable" icon={Shield}>
                        <ol className="list-decimal space-y-2 ps-4 text-sm text-theme-ink-soft">
                            <li>Open Platform → Tenants and pick the company.</li>
                            <li>Go to the Addons tab on the company page.</li>
                            <li>Click Install for <strong>{addon.name}</strong>.</li>
                            <li>The company admin sees new screens immediately (no self-service install).</li>
                        </ol>
                        {addon.has_provider && (
                            <p className="mt-3 text-xs text-theme-ink-muted">
                                Includes a service provider for routes and migrations when runtime loading is wired.
                            </p>
                        )}
                    </Section>
                </div>

                <Section title="Companies using this addon" icon={Building2}>
                    {tenants.length === 0 ? (
                        <p className="text-sm text-theme-ink-muted">
                            Not installed on any company yet.
                        </p>
                    ) : (
                        <ul className="divide-y divide-theme-border">
                            {tenants.map((tenant) => (
                                <li key={tenant.id} className="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium text-theme-ink">{tenant.name}</p>
                                        <p className="truncate font-mono text-xs text-theme-ink-muted">{tenant.code}</p>
                                    </div>
                                    <Link
                                        href={tenant.url}
                                        className="shrink-0 text-xs font-semibold text-theme-primary hover:underline"
                                    >
                                        View tenant
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>
            </div>
        </PlatformLayout>
    );
}
