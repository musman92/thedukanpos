import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import AdjustmentFormDrawer from '@/Pages/Admin/Hr/Adjustments/AdjustmentFormDrawer';
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

export default function Index({ adjustments, filters, employees = [] }) {
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [q, setQ] = useState(filters.q || '');
    const [localFilters, setLocalFilters] = useState({
        type: filters.type || '',
        status: filters.status || '',
        user_id: filters.user_id || '',
        from: filters.from || '',
        to: filters.to || '',
    });

    const sort = filters.sort || 'id';
    const direction = filters.direction || 'desc';

    const listQuery = {
        q: filters.q || '',
        per_page: filters.per_page,
        sort,
        direction,
        type: filters.type || '',
        status: filters.status || '',
        user_id: filters.user_id || '',
        from: filters.from || '',
        to: filters.to || '',
    };

    const visitList = (overrides = {}, options = {}) => {
        router.get(
            route('admin.hr.adjustments.index'),
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

    const openEdit = (row) => {
        if (!row.can_edit) return;
        setEditing(row);
        setShowForm(true);
    };

    const closeForm = () => {
        setShowForm(false);
        setEditing(null);
    };

    const destroyRow = async (row) => {
        if (!row.can_edit) return;
        const label = `${row.user?.name || 'Item'} · ${money(row.amount)}`;
        const ok = await confirmDelete(label, 'bonus / deduction');
        if (!ok) return;

        router.delete(route('admin.hr.adjustments.destroy', row.id), {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout
            title="Bonuses / deductions"
            description="Pending items are included when you generate payroll for matching dates."
            actions={
                <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" strokeWidth={2.25} />
                    Add
                </Button>
            }
        >
            <Head title="Bonuses / deductions" />

            <div className="dp-card overflow-hidden">
                <div className="space-y-3 border-b border-theme-border px-4 py-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <PageLimitSelect
                            pageKey="payroll-adjustments"
                            routeName="admin.hr.adjustments.index"
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
                                    placeholder="Search employee, notes…"
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
                            value={localFilters.type}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, type: e.target.value }))
                            }
                            className="h-9 w-32 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All types</option>
                            <option value="bonus">Bonus</option>
                            <option value="deduction">Deduction</option>
                        </select>
                        <select
                            value={localFilters.status}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, status: e.target.value }))
                            }
                            className="h-9 w-32 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All statuses</option>
                            <option value="pending">Pending</option>
                            <option value="applied">Applied</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <select
                            value={localFilters.user_id}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, user_id: e.target.value }))
                            }
                            className="h-9 w-40 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All employees</option>
                            {employees.map((e) => (
                                <option key={e.id} value={e.id}>
                                    {e.name}
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
                                <th className="px-3 py-3 font-semibold">Employee</th>
                                <SortableTh
                                    label="Type"
                                    column="type"
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
                                    label="Effective"
                                    column="effective_date"
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
                                <th className="px-3 py-3 font-semibold">Notes</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {adjustments.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-3 py-10 text-center text-theme-ink-muted"
                                    >
                                        No bonuses or deductions yet.
                                    </td>
                                </tr>
                            )}
                            {adjustments.data.map((row, idx) => (
                                <tr key={row.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(adjustments.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 font-medium text-theme-ink">
                                        {row.user?.name || '—'}
                                    </td>
                                    <td className="px-3 py-3 capitalize text-theme-ink-soft">
                                        {row.type}
                                    </td>
                                    <td className="px-3 py-3 text-right tabular-nums font-medium text-theme-ink">
                                        {money(row.amount)}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {row.effective_date}
                                    </td>
                                    <td className="px-3 py-3">
                                        {row.status === 'pending' ? (
                                            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                                                Pending
                                            </span>
                                        ) : row.status === 'applied' ? (
                                            <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                                                Applied
                                            </span>
                                        ) : (
                                            <span className="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-semibold text-stone-600 capitalize">
                                                {row.status}
                                            </span>
                                        )}
                                    </td>
                                    <td className="max-w-[10rem] truncate px-3 py-3 text-theme-ink-muted">
                                        {row.notes || '—'}
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        {row.can_edit ? (
                                            <div className="flex items-center justify-end gap-1">
                                                <button
                                                    type="button"
                                                    title="Edit"
                                                    aria-label="Edit"
                                                    onClick={() => openEdit(row)}
                                                    className="inline-flex rounded-md p-1.5 text-theme-primary hover:bg-theme-bg"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    title="Delete"
                                                    aria-label="Delete"
                                                    onClick={() => destroyRow(row)}
                                                    className="inline-flex rounded-md p-1.5 text-theme-danger hover:bg-theme-danger/10"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        ) : (
                                            <span className="text-theme-ink-muted">—</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={adjustments} />
            </div>

            <AdjustmentFormDrawer
                open={showForm}
                adjustment={editing}
                employees={employees}
                onClose={closeForm}
            />
        </AdminLayout>
    );
}
