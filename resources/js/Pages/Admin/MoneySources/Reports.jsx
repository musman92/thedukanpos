import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import MoneySourcesShell, { money } from '@/Pages/Admin/MoneySources/MoneySourcesShell';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Reports({
    filters: initialFilters,
    rows = [],
    summary,
    branches = [],
    sources = [],
    active_nav: activeNav = 'reports',
}) {
    const [filters, setFilters] = useState({
        movement_kind: initialFilters.movement_kind || 'all',
        branch_id: initialFilters.branch_id || '',
        from_money_source_id: initialFilters.from_money_source_id || '',
        to_money_source_id: initialFilters.to_money_source_id || '',
        from: initialFilters.from || '',
        to: initialFilters.to || '',
    });

    const apply = (e) => {
        e.preventDefault();
        router.get(route('admin.finance.money-sources.reports'), filters, {
            preserveState: true,
        });
    };

    return (
        <AdminLayout title="Money source reports">
            <Head title="Reports — Money sources" />

            <MoneySourcesShell
                activeNav={activeNav}
                title="Reports"
                description="Internal transfers and owner withdrawals ledger"
            >
                <form
                    onSubmit={apply}
                    className="mb-4 grid gap-3 rounded-xl border border-theme-border bg-theme-bg p-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <label className="text-sm">
                        <span className="mb-1 block text-xs font-medium text-theme-ink-muted">
                            Movement type
                        </span>
                        <select
                            value={filters.movement_kind}
                            onChange={(e) =>
                                setFilters((f) => ({ ...f, movement_kind: e.target.value }))
                            }
                            className="h-9 w-full rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="all">All</option>
                            <option value="internal_transfer">Internal transfer</option>
                            <option value="owner_withdrawal">Owner withdrawal</option>
                        </select>
                    </label>

                    <label className="text-sm">
                        <span className="mb-1 block text-xs font-medium text-theme-ink-muted">
                            Branch
                        </span>
                        <select
                            value={filters.branch_id}
                            onChange={(e) =>
                                setFilters((f) => ({ ...f, branch_id: e.target.value }))
                            }
                            className="h-9 w-full rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All branches</option>
                            {branches.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.name}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="text-sm">
                        <span className="mb-1 block text-xs font-medium text-theme-ink-muted">
                            From source
                        </span>
                        <select
                            value={filters.from_money_source_id}
                            onChange={(e) =>
                                setFilters((f) => ({
                                    ...f,
                                    from_money_source_id: e.target.value,
                                }))
                            }
                            className="h-9 w-full rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">Any</option>
                            {sources.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="text-sm">
                        <span className="mb-1 block text-xs font-medium text-theme-ink-muted">
                            To source
                        </span>
                        <select
                            value={filters.to_money_source_id}
                            onChange={(e) =>
                                setFilters((f) => ({
                                    ...f,
                                    to_money_source_id: e.target.value,
                                }))
                            }
                            className="h-9 w-full rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">Any</option>
                            {sources.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="text-sm">
                        <span className="mb-1 block text-xs font-medium text-theme-ink-muted">
                            From date
                        </span>
                        <input
                            type="date"
                            value={filters.from}
                            onChange={(e) => setFilters((f) => ({ ...f, from: e.target.value }))}
                            className="h-9 w-full rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        />
                    </label>

                    <label className="text-sm">
                        <span className="mb-1 block text-xs font-medium text-theme-ink-muted">
                            To date
                        </span>
                        <input
                            type="date"
                            value={filters.to}
                            onChange={(e) => setFilters((f) => ({ ...f, to: e.target.value }))}
                            className="h-9 w-full rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        />
                    </label>

                    <div className="flex items-end sm:col-span-2 lg:col-span-3">
                        <Button type="submit" variant="secondary" size="sm">
                            Apply filters
                        </Button>
                    </div>
                </form>

                <div className="mb-4 flex flex-wrap gap-4 text-sm">
                    <div className="rounded-lg border border-theme-border px-3 py-2">
                        <span className="text-theme-ink-muted">Transfers: </span>
                        <strong className="tabular-nums">{money(summary?.internal_total)}</strong>
                    </div>
                    <div className="rounded-lg border border-theme-border px-3 py-2">
                        <span className="text-theme-ink-muted">Owner withdrawals: </span>
                        <strong className="tabular-nums">
                            {money(summary?.owner_withdrawal_total)}
                        </strong>
                    </div>
                    <div className="rounded-lg border border-theme-border px-3 py-2">
                        <span className="text-theme-ink-muted">Rows: </span>
                        <strong>{summary?.total ?? 0}</strong>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border border-theme-border">
                    <table className="min-w-full text-left text-sm">
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="px-3 py-3 font-semibold">Date</th>
                                <th className="px-3 py-3 font-semibold">Type</th>
                                <th className="px-3 py-3 font-semibold">From</th>
                                <th className="px-3 py-3 font-semibold">To</th>
                                <th className="px-3 py-3 text-right font-semibold">Amount</th>
                                <th className="px-3 py-3 font-semibold">Branch</th>
                                <th className="px-3 py-3 font-semibold">Notes</th>
                                <th className="px-3 py-3 font-semibold">By</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-3 py-10 text-center text-theme-ink-muted"
                                    >
                                        No movements found.
                                    </td>
                                </tr>
                            )}
                            {rows.map((row) => (
                                <tr key={row.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {row.date}
                                    </td>
                                    <td className="px-3 py-3">{row.movement_label}</td>
                                    <td className="px-3 py-3 font-medium text-theme-ink">
                                        {row.from_name}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink">{row.to_name}</td>
                                    <td className="px-3 py-3 text-right tabular-nums font-medium">
                                        {money(row.amount)}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {row.branch_name}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {row.notes || '—'}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {row.created_by}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </MoneySourcesShell>
        </AdminLayout>
    );
}
