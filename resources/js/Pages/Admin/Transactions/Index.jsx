import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import TransactionFormDrawer from '@/Pages/Admin/Transactions/TransactionFormDrawer';
import { confirmDelete } from '@/lib/confirm';
import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';

function money(value) {
    return Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function refLabel(type) {
    if (!type) return '—';
    return String(type).replace(/_/g, ' ');
}

export default function Index({
    transactions,
    filters,
    accounts = [],
    money_sources: moneySources = [],
    reference_types: referenceTypes = [],
    form_reference_types: formReferenceTypes = [],
    branch = null,
}) {
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [q, setQ] = useState(filters.q || '');
    const [localFilters, setLocalFilters] = useState({
        direction: filters.direction || '',
        account_id: filters.account_id || '',
        money_source_id: filters.money_source_id || '',
        from: filters.from || '',
        to: filters.to || '',
        reference_type: filters.reference_type || '',
    });

    const sort = filters.sort || 'txn_date';
    const sortDirection = filters.sort_direction || 'desc';

    const listQuery = {
        q: filters.q || '',
        per_page: filters.per_page,
        sort,
        sort_direction: sortDirection,
        direction: filters.direction || '',
        account_id: filters.account_id || '',
        money_source_id: filters.money_source_id || '',
        from: filters.from || '',
        to: filters.to || '',
        reference_type: filters.reference_type || '',
    };

    const visitList = (overrides = {}, options = {}) => {
        router.get(
            route('admin.finance.transactions.index'),
            { ...listQuery, ...overrides },
            { preserveState: true, ...options },
        );
    };

    const toggleSort = (column) => {
        const nextDirection =
            sort === column ? (sortDirection === 'asc' ? 'desc' : 'asc') : 'asc';
        visitList({ sort: column, sort_direction: nextDirection });
    };

    const applyFilters = (e) => {
        e.preventDefault();
        visitList({ q, ...localFilters });
    };

    const openCreate = () => {
        setEditing(null);
        setShowForm(true);
    };

    const openEdit = (txn) => {
        if (!txn.is_manual) return;
        setEditing(txn);
        setShowForm(true);
    };

    const closeForm = () => {
        setShowForm(false);
        setEditing(null);
    };

    const destroyTxn = async (txn) => {
        if (!txn.is_manual) return;
        const label = `${txn.account?.name || 'Transaction'} · ${money(txn.amount)}`;
        const ok = await confirmDelete(label, 'transaction');
        if (!ok) return;
        router.delete(route('admin.finance.transactions.destroy', txn.id), {
            preserveScroll: true,
        });
    };

    const title = branch?.name ? `Transactions · ${branch.name}` : 'Transactions';

    return (
        <AdminLayout
            title={title}
            description="Income and expense ledger entries for the current branch."
            actions={
                <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" strokeWidth={2.25} />
                    Record Transaction
                </Button>
            }
        >
            <Head title="Transactions" />

            <div className="dp-card overflow-hidden">
                <div className="space-y-3 border-b border-theme-border px-4 py-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <PageLimitSelect
                            pageKey="transactions"
                            routeName="admin.finance.transactions.index"
                            current={filters.per_page}
                            companyDefault={filters.company_page_limit}
                            extraQuery={listQuery}
                        />
                        <form onSubmit={applyFilters} className="flex items-center gap-2">
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-theme-ink-muted" />
                                <input
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    placeholder="Search notes, account…"
                                    className="h-9 w-52 rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-8 pr-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                />
                            </div>
                            <Button type="submit" variant="secondary" size="sm">
                                Search
                            </Button>
                        </form>
                    </div>

                    <form
                        onSubmit={applyFilters}
                        className="flex flex-wrap items-center gap-2"
                    >
                        <select
                            value={localFilters.direction}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, direction: e.target.value }))
                            }
                            className="h-9 w-24 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All types</option>
                            <option value="in">In</option>
                            <option value="out">Out</option>
                        </select>
                        <select
                            value={localFilters.account_id}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, account_id: e.target.value }))
                            }
                            className="h-9 w-36 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All accounts</option>
                            {accounts.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={localFilters.money_source_id}
                            onChange={(e) =>
                                setLocalFilters((f) => ({
                                    ...f,
                                    money_source_id: e.target.value,
                                }))
                            }
                            className="h-9 w-36 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All sources</option>
                            {moneySources.map((m) => (
                                <option key={m.id} value={m.id}>
                                    {m.name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={localFilters.reference_type}
                            onChange={(e) =>
                                setLocalFilters((f) => ({
                                    ...f,
                                    reference_type: e.target.value,
                                }))
                            }
                            className="h-9 w-36 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All references</option>
                            {referenceTypes.map((r) => (
                                <option key={r} value={r}>
                                    {refLabel(r)}
                                </option>
                            ))}
                        </select>
                        <input
                            type="date"
                            value={localFilters.from}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, from: e.target.value }))
                            }
                            className="h-9 w-36 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                            title="From date"
                        />
                        <input
                            type="date"
                            value={localFilters.to}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, to: e.target.value }))
                            }
                            className="h-9 w-36 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                            title="To date"
                        />
                        <Button type="submit" variant="secondary" size="sm" className="shrink-0">
                            Filter
                        </Button>
                    </form>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full table-fixed text-left text-sm">
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="px-3 py-3 font-semibold">SN</th>
                                <SortableTh
                                    label="Date"
                                    column="txn_date"
                                    sort={sort}
                                    direction={sortDirection}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Account</th>
                                <SortableTh
                                    label="Type"
                                    column="direction"
                                    sort={sort}
                                    direction={sortDirection}
                                    onSort={toggleSort}
                                />
                                <SortableTh
                                    label="Amount"
                                    column="amount"
                                    sort={sort}
                                    direction={sortDirection}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Source</th>
                                <th className="px-3 py-3 font-semibold">Reference</th>
                                <th className="px-3 py-3 font-semibold">Notes</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {transactions.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={9}
                                        className="px-3 py-10 text-center text-theme-ink-muted"
                                    >
                                        No transactions found.
                                    </td>
                                </tr>
                            )}
                            {transactions.data.map((txn, idx) => (
                                <tr key={txn.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(transactions.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {txn.txn_date}
                                    </td>
                                    <td className="px-3 py-3">
                                        <div className="font-medium text-theme-ink">
                                            {txn.account?.name || '—'}
                                        </div>
                                        <div className="text-xs capitalize text-theme-ink-muted">
                                            {txn.account?.type}
                                        </div>
                                    </td>
                                    <td className="px-3 py-3">
                                        <span
                                            className={`rounded-full px-2 py-0.5 text-xs font-semibold uppercase ${
                                                txn.direction === 'in'
                                                    ? 'bg-emerald-100 text-emerald-800'
                                                    : 'bg-rose-100 text-rose-800'
                                            }`}
                                        >
                                            {txn.direction}
                                        </span>
                                    </td>
                                    <td
                                        className={`px-3 py-3 text-right tabular-nums font-medium ${
                                            txn.direction === 'in'
                                                ? 'text-emerald-700'
                                                : 'text-rose-700'
                                        }`}
                                    >
                                        {txn.direction === 'in' ? '+' : '−'}
                                        {money(txn.amount)}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {txn.money_source?.name || '—'}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {txn.reference_type ? (
                                            <div>
                                                <span className="capitalize">{refLabel(txn.reference_type)}</span>
                                                {txn.reference_id ? (
                                                    <span className="ml-1 tabular-nums text-theme-ink-soft">
                                                        #{txn.reference_id}
                                                    </span>
                                                ) : null}
                                                {txn.is_manual && (
                                                    <span className="ml-1 rounded-full bg-theme-bg px-1.5 py-0.5 text-[10px] font-semibold uppercase text-theme-ink-soft">
                                                        Manual
                                                    </span>
                                                )}
                                            </div>
                                        ) : txn.is_manual ? (
                                            <span className="rounded-full bg-theme-bg px-2 py-0.5 text-xs font-semibold text-theme-ink-soft">
                                                Manual
                                            </span>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="max-w-[10rem] truncate px-3 py-3 text-theme-ink-muted">
                                        {txn.notes || '—'}
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        {txn.is_manual ? (
                                            <div className="flex items-center justify-end gap-1">
                                                <button
                                                    type="button"
                                                    title="Edit"
                                                    aria-label="Edit"
                                                    onClick={() => openEdit(txn)}
                                                    className="inline-flex rounded-md p-1.5 text-theme-primary hover:bg-theme-bg"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    title="Delete"
                                                    aria-label="Delete"
                                                    onClick={() => destroyTxn(txn)}
                                                    className="inline-flex rounded-md p-1.5 text-theme-danger hover:bg-theme-danger/10"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        ) : (
                                            <span className="text-xs text-theme-ink-muted">—</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={transactions} />
            </div>

            <TransactionFormDrawer
                open={showForm}
                transaction={editing}
                accounts={accounts}
                moneySources={moneySources}
                formReferenceTypes={formReferenceTypes}
                onClose={closeForm}
            />
        </AdminLayout>
    );
}
