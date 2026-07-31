import PlatformLayout from '@/Layouts/PlatformLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import InvoiceFormDrawer from '@/Pages/Platform/Invoices/InvoiceFormDrawer';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

function money(n) {
    return Number(n || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function StatusBadge({ status }) {
    const styles = {
        open: 'bg-amber-500/15 text-amber-700',
        paid: 'bg-emerald-500/15 text-emerald-700',
        void: 'bg-theme-bg text-theme-ink-muted ring-1 ring-theme-border',
    };

    return (
        <span
            className={`rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize ${
                styles[status] || styles.void
            }`}
        >
            {status || '—'}
        </span>
    );
}

const filterSelectClass =
    'h-9 rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

export default function Index({
    invoices,
    tenants = [],
    filters,
    form_open: formOpen = false,
}) {
    const [showForm, setShowForm] = useState(!!formOpen);
    const [q, setQ] = useState(filters.q || '');
    const [status, setStatus] = useState(filters.status || 'all');
    const [tenantId, setTenantId] = useState(filters.tenant_id || '');

    const sort = filters.sort || 'id';
    const direction = filters.direction || 'desc';

    const listQuery = {
        q: filters.q || '',
        status: filters.status || 'all',
        tenant_id: filters.tenant_id || '',
        per_page: filters.per_page,
        sort,
        direction,
    };

    useEffect(() => {
        setShowForm(!!formOpen);
    }, [formOpen]);

    const visitList = (overrides = {}, options = {}) => {
        router.get(
            route('platform.invoices.index'),
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
        visitList({
            q,
            status,
            tenant_id: tenantId || undefined,
        });
    };

    return (
        <PlatformLayout
            title="Invoices"
            description="Platform billing invoices for tenants."
            actions={
                <Button onClick={() => setShowForm(true)}>
                    <Plus className="h-4 w-4" strokeWidth={2.25} />
                    New invoice
                </Button>
            }
        >
            <Head title="Platform · Invoices" />

            <div className="dp-card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-theme-border px-4 py-3">
                    <PageLimitSelect
                        pageKey="platform-invoices"
                        routeName="platform.invoices.index"
                        current={filters.per_page}
                        companyDefault={filters.company_page_limit}
                        extraQuery={{
                            q: filters.q || '',
                            status: filters.status || 'all',
                            tenant_id: filters.tenant_id || '',
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
                            <option value="open">Open</option>
                            <option value="paid">Paid</option>
                            <option value="void">Void</option>
                        </select>
                        <select
                            value={tenantId}
                            onChange={(e) => setTenantId(e.target.value)}
                            className={`${filterSelectClass} max-w-[12rem]`}
                        >
                            <option value="">All tenants</option>
                            {tenants.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {t.meta || t.label}
                                </option>
                            ))}
                        </select>
                        <div className="relative">
                            <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-theme-ink-muted" />
                            <input
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="Search invoice or tenant"
                                className="h-9 w-56 rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-8 pr-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
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
                                    label="Number"
                                    column="number"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Tenant</th>
                                <SortableTh
                                    label="Date"
                                    column="invoice_date"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <SortableTh
                                    label="Amount"
                                    column="amount"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <SortableTh
                                    label="Status"
                                    column="status"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {invoices.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-3 py-10 text-center text-theme-ink-muted"
                                    >
                                        No invoices yet.
                                    </td>
                                </tr>
                            )}
                            {invoices.data.map((inv, idx) => (
                                <tr key={inv.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(invoices.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 font-mono text-xs text-theme-ink">
                                        {inv.number}
                                    </td>
                                    <td className="px-3 py-3">
                                        <p className="font-medium text-theme-ink">
                                            {inv.tenant_name || '—'}
                                        </p>
                                        <p className="font-mono text-[11px] text-theme-ink-muted">
                                            {inv.tenant_code}
                                        </p>
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {inv.invoice_date || '—'}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink">
                                        {money(inv.amount)}
                                    </td>
                                    <td className="px-3 py-3">
                                        <StatusBadge status={inv.status} />
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        {inv.status !== 'paid' && inv.status !== 'void' && (
                                            <Link
                                                href={route('platform.invoices.paid', inv.id)}
                                                method="post"
                                                as="button"
                                                className="text-sm font-semibold text-theme-primary hover:underline"
                                            >
                                                Mark paid
                                            </Link>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={invoices} />
            </div>

            <InvoiceFormDrawer
                open={showForm}
                onClose={() => setShowForm(false)}
                tenants={tenants}
            />
        </PlatformLayout>
    );
}
