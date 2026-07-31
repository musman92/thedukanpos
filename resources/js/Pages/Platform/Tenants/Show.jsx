import PlatformLayout from '@/Layouts/PlatformLayout';
import Button from '@/Components/Ui/Button';
import TenantFormDrawer from '@/Pages/Platform/Tenants/TenantFormDrawer';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Database, Pencil } from 'lucide-react';
import { useState } from 'react';

function Info({ label, children }) {
    return (
        <div>
            <dt className="text-sm font-medium text-theme-ink-muted">{label}</dt>
            <dd className="mt-1 text-sm text-theme-ink">{children || '—'}</dd>
        </div>
    );
}

function StatusBadge({ active }) {
    return active ? (
        <span className="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">
            Active
        </span>
    ) : (
        <span className="rounded-full bg-rose-500/15 px-2 py-0.5 text-[11px] font-semibold text-rose-700">
            Suspended
        </span>
    );
}

function seedTone(status) {
    if (status === 'completed') return 'border-emerald-200 bg-emerald-50 text-emerald-800';
    if (status === 'failed') return 'border-rose-200 bg-rose-50 text-rose-800';
    if (status === 'queued' || status === 'running') return 'border-amber-200 bg-amber-50 text-amber-900';
    return 'border-theme-border bg-theme-bg text-theme-ink-soft';
}

export default function Show({
    tenant,
    form_meta: formMeta = {},
    demo_seed: demoSeed = {},
}) {
    const [editing, setEditing] = useState(false);
    const { errors } = usePage().props;
    const seedBusy = ['queued', 'running'].includes(demoSeed?.status);

    const startSeed = () => {
        if (
            !window.confirm(
                'Wipe transactional data and seed ~60 days of realistic demo data across 2 branches? This runs in the background.',
            )
        ) {
            return;
        }
        router.post(route('platform.tenants.seed-demo', tenant.id), {}, { preserveScroll: true });
    };

    return (
        <PlatformLayout
            title={tenant.name}
            description="Company details"
            actions={
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="secondary" onClick={() => setEditing(true)}>
                        <Pencil className="h-4 w-4" strokeWidth={2.25} />
                        Edit
                    </Button>
                    {tenant.is_demo && (
                        <Button
                            variant="secondary"
                            disabled={seedBusy || !tenant.is_active}
                            onClick={startSeed}
                        >
                            <Database className="h-4 w-4" strokeWidth={2.25} />
                            {seedBusy ? 'Seeding…' : 'Seed demo data'}
                        </Button>
                    )}
                    <Button
                        variant="secondary"
                        disabled={!tenant.is_active}
                        onClick={() =>
                            router.post(route('platform.tenants.support-login', tenant.id), {}, {
                                preserveScroll: true,
                            })
                        }
                    >
                        Support login (15m)
                    </Button>
                    <Link
                        href={route('platform.tenants.index')}
                        className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-theme-border bg-theme-surface px-3 text-sm font-semibold text-theme-ink hover:bg-theme-bg"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back
                    </Link>
                </div>
            }
        >
            <Head title={`Platform · ${tenant.name}`} />

            {errors?.tenant && (
                <div className="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    {errors.tenant}
                </div>
            )}

            {tenant.is_demo && demoSeed?.status && (
                <div className={`mb-4 rounded-lg border px-4 py-3 text-sm ${seedTone(demoSeed.status)}`}>
                    <div className="font-semibold">
                        Demo seed: {String(demoSeed.status).replace('_', ' ')}
                    </div>
                    {demoSeed.message && <div className="mt-1">{demoSeed.message}</div>}
                    {demoSeed.updated_at && (
                        <div className="mt-1 text-xs opacity-80">Updated {demoSeed.updated_at}</div>
                    )}
                    {seedBusy && (
                        <div className="mt-2 space-y-1 text-xs">
                            <div>Refresh this page every 15–30s to update status.</div>
                            <div>
                                If it stays on <strong>queued</strong>, run in a terminal:
                            </div>
                            <code className="block rounded bg-black/5 px-2 py-1 text-[11px]">
                                php artisan dukan:seed-demo {tenant.code}
                            </code>
                        </div>
                    )}
                    {demoSeed.status === 'queued' && (
                        <div className="mt-3">
                            <button
                                type="button"
                                className="text-xs font-semibold underline"
                                onClick={() =>
                                    router.post(
                                        route('platform.tenants.seed-demo', tenant.id),
                                        { force: true },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Retry seed now
                            </button>
                        </div>
                    )}
                </div>
            )}

            <div className="dp-card overflow-hidden">
                <div className="border-b border-theme-border px-4 py-3">
                    <h2 className="text-base font-semibold text-theme-ink">Company information</h2>
                </div>
                <dl className="grid grid-cols-1 gap-6 p-4 md:grid-cols-2">
                    <Info label="Company name">
                        <span className="inline-flex flex-wrap items-center gap-2">
                            {tenant.name}
                            {tenant.is_demo && (
                                <span className="rounded-full bg-sky-500/15 px-2 py-0.5 text-[11px] font-semibold text-sky-700">
                                    Demo
                                </span>
                            )}
                        </span>
                    </Info>
                    <Info label="Slug / code">
                        <span className="font-mono text-xs">{tenant.code}</span>
                    </Info>
                    <Info label="Email">{tenant.email}</Info>
                    <Info label="Phone">{tenant.phone}</Info>
                    <Info label="Address">{tenant.address}</Info>
                    <Info label="Tax ID">{tenant.tax_id}</Info>
                    <Info label="Currency">{tenant.currency}</Info>
                    <Info label="Timezone">{tenant.timezone}</Info>
                    <Info label="Status">
                        <StatusBadge active={!!tenant.is_active} />
                    </Info>
                    <Info label="Created">{tenant.created_at}</Info>
                </dl>
            </div>

            <div className="dp-card mt-4 overflow-hidden">
                <div className="border-b border-theme-border px-4 py-3">
                    <h2 className="text-base font-semibold text-theme-ink">Subscription & billing</h2>
                </div>
                <dl className="grid grid-cols-1 gap-6 p-4 md:grid-cols-2">
                    <Info label="Plan">{tenant.plan}</Info>
                    <Info label="Billing status">{tenant.billing_status}</Info>
                    <Info label="Monthly fee">
                        {Number(tenant.monthly_fee || 0).toFixed(2)}
                    </Info>
                    <Info label="Trial ends">{tenant.trial_ends_at}</Info>
                    <Info label="Billing notes">{tenant.billing_notes}</Info>
                    <Info label="Login hint">
                        <span className="font-mono text-xs">admin@{tenant.code}</span>
                    </Info>
                </dl>
            </div>

            <TenantFormDrawer
                open={editing}
                tenant={tenant}
                formMeta={formMeta}
                onClose={() => setEditing(false)}
            />
        </PlatformLayout>
    );
}
