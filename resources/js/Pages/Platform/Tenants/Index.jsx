import PlatformLayout from '@/Layouts/PlatformLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import TenantFormDrawer from '@/Pages/Platform/Tenants/TenantFormDrawer';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, Search, Database } from 'lucide-react';
import { useEffect, useState } from 'react';

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

function BillingBadge({ status }) {
    const styles = {
        trial: 'bg-amber-500/15 text-amber-700',
        active: 'bg-emerald-500/15 text-emerald-700',
        past_due: 'bg-rose-500/15 text-rose-700',
        cancelled: 'bg-theme-bg text-theme-ink-muted ring-1 ring-theme-border',
    };

    return (
        <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${styles[status] || styles.cancelled}`}>
            {status || '—'}
        </span>
    );
}

const filterSelectClass =
    'h-9 rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

export default function Index({
    tenants,
    filters,
    form_open: formOpen = false,
    edit_tenant_id: editTenantId = null,
    form_meta: formMeta = {},
}) {
    const [showCreate, setShowCreate] = useState(!!formOpen);
    const [editing, setEditing] = useState(null);
    const [q, setQ] = useState(filters.q || '');
    const [status, setStatus] = useState(filters.status || 'all');
    const [billingStatus, setBillingStatus] = useState(filters.billing_status || 'all');

    const sort = filters.sort || 'code';
    const direction = filters.direction || 'asc';

    const listQuery = {
        q: filters.q || '',
        status: filters.status || 'all',
        billing_status: filters.billing_status || 'all',
        per_page: filters.per_page,
        sort,
        direction,
    };

    useEffect(() => {
        setShowCreate(!!formOpen);
    }, [formOpen]);

    useEffect(() => {
        if (!editTenantId) return;
        const found = (tenants?.data || []).find((t) => String(t.id) === String(editTenantId));
        if (found) setEditing(found);
    }, [editTenantId, tenants]);

    const visitList = (overrides = {}, options = {}) => {
        router.get(
            route('platform.tenants.index'),
            { ...listQuery, ...overrides },
            { preserveState: true, ...options },
        );
    };

    const toggleSort = (column) => {
        const nextDirection =
            sort === column ? (direction === 'asc' ? 'desc' : 'asc') : 'asc';
        visitList({ sort: column, direction: nextDirection });
    };

    const applyFilters = (e) => {
        e.preventDefault();
        visitList({ q, status, billing_status: billingStatus });
    };

    const closeForm = () => {
        setShowCreate(false);
        setEditing(null);
    };

    return (
        <PlatformLayout
            title="Tenants"
            description="Shops on this platform — create, bill, and manage access."
            actions={
                <Button
                    onClick={() => {
                        setEditing(null);
                        setShowCreate(true);
                    }}
                >
                    <Plus className="h-4 w-4" strokeWidth={2.25} />
                    Add tenant
                </Button>
            }
        >
            <Head title="Platform · Tenants" />

            <div className="dp-card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-theme-border px-4 py-3">
                    <PageLimitSelect
                        pageKey="platform-tenants"
                        routeName="platform.tenants.index"
                        current={filters.per_page}
                        companyDefault={filters.company_page_limit}
                        extraQuery={{
                            q: filters.q || '',
                            status: filters.status || 'all',
                            billing_status: filters.billing_status || 'all',
                            sort,
                            direction,
                        }}
                    />
                    <form onSubmit={applyFilters} className="flex flex-wrap items-center gap-2">
                        <select
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                            className={filterSelectClass}
                        >
                            <option value="all">All status</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                        </select>
                        <select
                            value={billingStatus}
                            onChange={(e) => setBillingStatus(e.target.value)}
                            className={filterSelectClass}
                        >
                            <option value="all">All billing</option>
                            <option value="trial">Trial</option>
                            <option value="active">Active</option>
                            <option value="past_due">Past due</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <div className="relative w-full sm:w-auto">
                            <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-theme-ink-muted" />
                            <input
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="Search code or name"
                                className="h-9 w-full sm:w-52 rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-8 pr-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                            />
                        </div>
                        <Button type="submit" variant="secondary" size="sm">
                            Search
                        </Button>
                    </form>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="px-3 py-3 font-semibold">SN</th>
                                <SortableTh
                                    label="Code"
                                    column="code"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <SortableTh
                                    label="Name"
                                    column="name"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <SortableTh
                                    label="Plan"
                                    column="plan"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <SortableTh
                                    label="Billing"
                                    column="billing_status"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <SortableTh
                                    label="Fee"
                                    column="monthly_fee"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Status</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {tenants.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-3 py-10 text-center text-theme-ink-muted"
                                    >
                                        No tenants yet.
                                    </td>
                                </tr>
                            )}
                            {tenants.data.map((tenant, idx) => (
                                <tr key={tenant.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(tenants.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 font-mono text-xs text-theme-ink">
                                        {tenant.code}
                                    </td>
                                    <td className="px-3 py-3 font-medium text-theme-ink">
                                        <span className="inline-flex flex-wrap items-center gap-2">
                                            {tenant.name}
                                            {tenant.is_demo && (
                                                <span className="rounded-full bg-sky-500/15 px-2 py-0.5 text-[11px] font-semibold text-sky-700">
                                                    Demo
                                                </span>
                                            )}
                                        </span>
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">{tenant.plan}</td>
                                    <td className="px-3 py-3">
                                        <BillingBadge status={tenant.billing_status} />
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink">
                                        {Number(tenant.monthly_fee || 0).toFixed(2)}
                                    </td>
                                    <td className="px-3 py-3">
                                        <StatusBadge active={!!tenant.is_active} />
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            <Link
                                                href={route('platform.tenants.show', tenant.id)}
                                                title="View"
                                                aria-label="View"
                                                className="inline-flex rounded-md p-1.5 text-theme-primary hover:bg-theme-bg"
                                            >
                                                <Eye className="h-4 w-4" />
                                            </Link>
                                            <button
                                                type="button"
                                                title="Edit"
                                                aria-label="Edit"
                                                onClick={() => {
                                                    setShowCreate(false);
                                                    setEditing(tenant);
                                                }}
                                                className="inline-flex rounded-md p-1.5 text-theme-primary hover:bg-theme-bg"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            {tenant.is_demo && (
                                                <Link
                                                    href={route('platform.tenants.show', tenant.id)}
                                                    title="Seed demo data"
                                                    aria-label="Seed demo data"
                                                    className="inline-flex rounded-md p-1.5 text-sky-700 hover:bg-sky-500/10"
                                                >
                                                    <Database className="h-4 w-4" />
                                                </Link>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={tenants} />
            </div>

            <TenantFormDrawer
                open={showCreate || !!editing}
                tenant={editing}
                formMeta={formMeta}
                onClose={closeForm}
            />
        </PlatformLayout>
    );
}
