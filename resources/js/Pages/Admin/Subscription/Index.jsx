import AdminLayout from '@/Layouts/AdminLayout';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import { formatMoney } from '@/lib/money';
import { Head, router } from '@inertiajs/react';
import { ChevronDown, Search } from 'lucide-react';
import { useState } from 'react';

function BillingBadge({ status }) {
    const styles = {
        trial: 'bg-amber-500/15 text-amber-700',
        active: 'bg-emerald-500/15 text-emerald-700',
        past_due: 'bg-rose-500/15 text-rose-700',
        cancelled: 'bg-theme-bg text-theme-ink-muted ring-1 ring-theme-border',
    };

    return (
        <span
            className={`rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize ${
                styles[status] || styles.cancelled
            }`}
        >
            {(status || '—').replace(/_/g, ' ')}
        </span>
    );
}

function InvoiceStatusBadge({ status }) {
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

function Info({ label, children }) {
    return (
        <div>
            <dt className="text-sm font-medium text-theme-ink-muted">{label}</dt>
            <dd className="mt-1 text-sm text-theme-ink">{children || '—'}</dd>
        </div>
    );
}

export default function Index({ subscription, invoices, filters }) {
    const [q, setQ] = useState(filters.q || '');
    const [status, setStatus] = useState(filters.status || 'all');

    const sort = filters.sort || 'invoice_date';
    const direction = filters.direction || 'desc';

    const moneyCfg = {
        currency: subscription?.currency,
        currency_symbol: subscription?.currency,
        decimal_points: 2,
    };

    const listQuery = {
        q: filters.q || '',
        status: filters.status || 'all',
        per_page: filters.per_page,
        sort,
        direction,
    };

    const visitList = (overrides = {}, options = {}) => {
        router.get(route('admin.subscription.index'), { ...listQuery, ...overrides }, {
            preserveState: true,
            ...options,
        });
    };

    const toggleSort = (column) => {
        const nextDirection =
            sort === column ? (direction === 'asc' ? 'desc' : 'asc') : 'asc';
        visitList({ sort: column, direction: nextDirection });
    };

    const applyFilters = (e) => {
        e.preventDefault();
        visitList({ q, status });
    };

    return (
        <AdminLayout
            title="Subscription"
            description="Your plan, billing status, and platform invoice history."
        >
            <Head title="Subscription" />

            <div className="space-y-5">
                <div className="dp-card overflow-hidden">
                    <div className="border-b border-theme-border px-4 py-3">
                        <h2 className="text-base font-semibold text-theme-ink">Subscription details</h2>
                    </div>
                    <dl className="grid grid-cols-1 gap-6 p-4 sm:grid-cols-2 xl:grid-cols-3">
                        <Info label="Company">
                            <span className="font-medium">{subscription?.name}</span>
                            <span className="ml-2 font-mono text-xs text-theme-ink-muted">
                                {subscription?.code}
                            </span>
                        </Info>
                        <Info label="Plan">
                            <span className="capitalize">{subscription?.plan || '—'}</span>
                        </Info>
                        <Info label="Billing status">
                            <BillingBadge status={subscription?.billing_status} />
                        </Info>
                        <Info label="Monthly fee">
                            {formatMoney(subscription?.monthly_fee, moneyCfg)}
                        </Info>
                        <Info label="Trial ends">{subscription?.trial_ends_at}</Info>
                        <Info label="Open invoices">
                            {subscription?.open_invoice_count || 0}
                            {(subscription?.open_invoice_count || 0) > 0 && (
                                <span className="ml-2 tabular-nums text-theme-ink-muted">
                                    · {formatMoney(subscription?.open_invoice_total, moneyCfg)}
                                </span>
                            )}
                        </Info>
                        {subscription?.billing_notes ? (
                            <div className="sm:col-span-2 xl:col-span-3">
                                <Info label="Billing notes">{subscription.billing_notes}</Info>
                            </div>
                        ) : null}
                    </dl>
                </div>

                <div className="dp-card overflow-hidden">
                    <div className="space-y-3 border-b border-theme-border px-4 py-3">
                        <div>
                            <h2 className="text-base font-semibold text-theme-ink">Invoice history</h2>
                            <p className="mt-0.5 text-sm text-theme-ink-muted">
                                Platform billing invoices for this company.
                            </p>
                        </div>
                        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                            <PageLimitSelect
                                pageKey="admin-subscription"
                                routeName="admin.subscription.index"
                                current={filters.per_page}
                                companyDefault={filters.company_page_limit}
                                extraQuery={{
                                    q: filters.q || '',
                                    status: filters.status || 'all',
                                    sort,
                                    direction,
                                }}
                            />
                            <form
                                onSubmit={applyFilters}
                                className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center"
                            >
                                <div className="relative shrink-0">
                                    <select
                                        value={status}
                                        onChange={(e) => setStatus(e.target.value)}
                                        className="dp-select-reset h-9 min-w-[9.5rem] rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-3 pr-8 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                    >
                                        <option value="all">All statuses</option>
                                        <option value="open">Open</option>
                                        <option value="paid">Paid</option>
                                        <option value="void">Void</option>
                                    </select>
                                    <ChevronDown
                                        className="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-theme-ink-muted"
                                        strokeWidth={2}
                                    />
                                </div>
                                <div className="relative min-w-0 flex-1 sm:flex-none">
                                    <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-theme-ink-muted" />
                                    <input
                                        value={q}
                                        onChange={(e) => setQ(e.target.value)}
                                        placeholder="Search invoices…"
                                        className="h-9 w-full rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-8 pr-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20 sm:w-52"
                                    />
                                </div>
                                <button
                                    type="submit"
                                    className="dp-btn-primary inline-flex h-9 shrink-0 items-center justify-center px-3 text-sm"
                                >
                                    Apply
                                </button>
                            </form>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                                <tr>
                                    <SortableTh
                                        column="number"
                                        label="Invoice"
                                        sort={sort}
                                        direction={direction}
                                        onSort={toggleSort}
                                    />
                                    <SortableTh
                                        column="invoice_date"
                                        label="Date"
                                        sort={sort}
                                        direction={direction}
                                        onSort={toggleSort}
                                    />
                                    <SortableTh
                                        column="due_date"
                                        label="Due"
                                        sort={sort}
                                        direction={direction}
                                        onSort={toggleSort}
                                    />
                                    <SortableTh
                                        column="amount"
                                        label="Amount"
                                        sort={sort}
                                        direction={direction}
                                        onSort={toggleSort}
                                        align="right"
                                    />
                                    <SortableTh
                                        column="status"
                                        label="Status"
                                        sort={sort}
                                        direction={direction}
                                        onSort={toggleSort}
                                    />
                                    <SortableTh
                                        column="paid_at"
                                        label="Paid"
                                        sort={sort}
                                        direction={direction}
                                        onSort={toggleSort}
                                    />
                                    <th className="px-3 py-2 text-left font-semibold">Notes</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-theme-border">
                                {(invoices?.data || []).length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-3 py-10 text-center text-theme-ink-muted"
                                        >
                                            No invoices yet.
                                        </td>
                                    </tr>
                                ) : (
                                    invoices.data.map((inv) => (
                                        <tr key={inv.id}>
                                            <td className="px-3 py-3 font-mono text-xs font-medium text-theme-ink">
                                                {inv.number}
                                            </td>
                                            <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                                {inv.invoice_date || '—'}
                                            </td>
                                            <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                                {inv.due_date || '—'}
                                            </td>
                                            <td className="px-3 py-3 text-right tabular-nums font-medium text-theme-ink">
                                                {formatMoney(inv.amount, moneyCfg)}
                                            </td>
                                            <td className="px-3 py-3">
                                                <InvoiceStatusBadge status={inv.status} />
                                            </td>
                                            <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                                {inv.paid_at || '—'}
                                            </td>
                                            <td className="max-w-[14rem] truncate px-3 py-3 text-theme-ink-muted">
                                                {inv.notes || '—'}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="border-t border-theme-border px-4 py-3">
                        <Pagination paginator={invoices} />
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
