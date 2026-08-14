import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import ExpenseFormDrawer from '@/Pages/Admin/Expenses/ExpenseFormDrawer';
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

export default function Index({
    expenses,
    filters,
    accounts = [],
    money_sources: moneySources = [],
    branch = null,
}) {
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [q, setQ] = useState(filters.q || '');
    const [localFilters, setLocalFilters] = useState({
        account_id: filters.account_id || '',
        money_source_id: filters.money_source_id || '',
        from: filters.from || '',
        to: filters.to || '',
    });

    const sort = filters.sort || 'txn_date';
    const direction = filters.direction || 'desc';

    const listQuery = {
        q: filters.q || '',
        per_page: filters.per_page,
        sort,
        direction,
        account_id: filters.account_id || '',
        money_source_id: filters.money_source_id || '',
        from: filters.from || '',
        to: filters.to || '',
    };

    const visitList = (overrides = {}, options = {}) => {
        router.get(
            route('admin.finance.expenses.index'),
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
        visitList({ q, ...localFilters });
    };

    const openCreate = () => {
        setEditing(null);
        setShowForm(true);
    };

    const openEdit = (expense) => {
        setEditing(expense);
        setShowForm(true);
    };

    const closeForm = () => {
        setShowForm(false);
        setEditing(null);
    };

    const destroyExpense = async (expense) => {
        const label = `${expense.account?.name || 'Expense'} · ${money(expense.amount)}`;
        const ok = await confirmDelete(label, 'expense');
        if (!ok) return;

        router.delete(route('admin.finance.expenses.destroy', expense.id), {
            preserveScroll: true,
        });
    };

    const title = branch?.name ? `Expenses · ${branch.name}` : 'Expenses';

    return (
        <AdminLayout
            title={title}
            description="Record shop expenses like rent, utilities, and maintenance."
            actions={
                <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" strokeWidth={2.25} />
                    Record Expense
                </Button>
            }
        >
            <Head title="Expenses" />

            <div className="dp-card overflow-hidden">
                <div className="space-y-3 border-b border-theme-border px-4 py-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <PageLimitSelect
                            pageKey="expenses"
                            routeName="admin.finance.expenses.index"
                            current={filters.per_page}
                            companyDefault={filters.company_page_limit}
                            extraQuery={listQuery}
                        />
                        <form onSubmit={applyFilters} className="flex w-full items-center gap-2 sm:w-auto">
                            <div className="relative w-full sm:w-auto">
                                <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-theme-ink-muted" />
                                <input
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    placeholder="Search notes, account…"
                                    className="h-9 w-full sm:w-52 rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-8 pr-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                />
                            </div>
                            <Button type="submit" variant="secondary" size="sm">
                                Search
                            </Button>
                        </form>
                    </div>

                    <form
                        onSubmit={applyFilters}
                        className="grid gap-2 sm:grid-cols-2 lg:grid-cols-5"
                    >
                        <select
                            value={localFilters.account_id}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, account_id: e.target.value }))
                            }
                            className="h-9 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
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
                            className="h-9 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All sources</option>
                            {moneySources.map((m) => (
                                <option key={m.id} value={m.id}>
                                    {m.name}
                                </option>
                            ))}
                        </select>
                        <input
                            type="date"
                            value={localFilters.from}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, from: e.target.value }))
                            }
                            className="h-9 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                            title="From date"
                        />
                        <input
                            type="date"
                            value={localFilters.to}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, to: e.target.value }))
                            }
                            className="h-9 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                            title="To date"
                        />
                        <Button type="submit" variant="secondary" size="sm">
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
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Account</th>
                                <SortableTh
                                    label="Amount"
                                    column="amount"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Paid from</th>
                                <th className="px-3 py-3 font-semibold">Notes</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {expenses.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-3 py-10 text-center text-theme-ink-muted"
                                    >
                                        No expenses yet.
                                    </td>
                                </tr>
                            )}
                            {expenses.data.map((expense, idx) => (
                                <tr key={expense.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(expenses.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {expense.expense_date}
                                    </td>
                                    <td className="px-3 py-3 font-medium text-theme-ink">
                                        {expense.account?.name || '—'}
                                    </td>
                                    <td className="px-3 py-3 text-right tabular-nums font-medium text-theme-ink">
                                        {money(expense.amount)}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {expense.money_source?.name || '—'}
                                    </td>
                                    <td className="max-w-[12rem] truncate px-3 py-3 text-theme-ink-muted">
                                        {expense.notes || '—'}
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                title="Edit"
                                                aria-label="Edit"
                                                onClick={() => openEdit(expense)}
                                                className="inline-flex rounded-md p-1.5 text-theme-primary hover:bg-theme-bg"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                title="Delete"
                                                aria-label="Delete"
                                                onClick={() => destroyExpense(expense)}
                                                className="inline-flex rounded-md p-1.5 text-theme-danger hover:bg-theme-danger/10"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={expenses} />
            </div>

            <ExpenseFormDrawer
                open={showForm}
                expense={editing}
                accounts={accounts}
                moneySources={moneySources}
                onClose={closeForm}
            />
        </AdminLayout>
    );
}
