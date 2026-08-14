import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import TransferFormDrawer from '@/Pages/Admin/Inventory/TransferFormDrawer';
import { confirmDelete } from '@/lib/confirm';
import { Head, router, usePage } from '@inertiajs/react';
import { Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

function formatQty(value) {
    const n = Number(value || 0);
    if (Math.abs(n - Math.round(n)) < 0.0001) {
        return n.toLocaleString(undefined, { maximumFractionDigits: 0 });
    }
    return n.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 4,
    });
}

export default function Transfers({
    transfers,
    filters,
    variants = [],
    branches = [],
    form_open: formOpen = false,
    branch = null,
}) {
    const { url } = usePage();
    const [showForm, setShowForm] = useState(false);
    const [q, setQ] = useState(filters.q || '');
    const [localFilters, setLocalFilters] = useState({
        to_branch_id: filters.to_branch_id || '',
        from: filters.from || '',
        to: filters.to || '',
    });

    useEffect(() => {
        if (formOpen) {
            setShowForm(true);
        }
    }, [formOpen]);

    useEffect(() => {
        const params = new URLSearchParams(url.split('?')[1] || '');
        if (params.get('open') === '1') {
            setShowForm(true);
        }
    }, [url]);

    const sort = filters.sort || 'created_at';
    const direction = filters.direction || 'desc';

    const listQuery = {
        q: filters.q || '',
        per_page: filters.per_page,
        sort,
        direction,
        to_branch_id: filters.to_branch_id || '',
        from: filters.from || '',
        to: filters.to || '',
    };

    const visitList = (overrides = {}, options = {}) => {
        router.get(
            route('admin.inventory.transfers'),
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
        setShowForm(true);
        visitList({ open: 1 });
    };

    const closeForm = () => {
        setShowForm(false);
        visitList({ open: undefined });
    };

    const destroyTransfer = async (row) => {
        const label = `${row.number} · ${row.from_branch?.name || '?'} → ${row.to_branch?.name || '?'}`;
        const ok = await confirmDelete(label, 'stock transfer');
        if (!ok) return;

        router.delete(route('admin.inventory.transfers.destroy', row.id), {
            preserveScroll: true,
        });
    };

    const title = branch?.name
        ? `Stock transfers · ${branch.name}`
        : 'Stock transfers';

    return (
        <AdminLayout
            title={title}
            description="Move stock from this branch to another. Deleting a transfer reverses the stock movement."
            actions={
                <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" strokeWidth={2.25} />
                    New transfer
                </Button>
            }
        >
            <Head title="Stock transfers" />

            <div className="dp-card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-theme-border px-4 py-3">
                    <PageLimitSelect
                        pageKey="stock-transfers"
                        routeName="admin.inventory.transfers"
                        current={filters.per_page}
                        companyDefault={filters.company_page_limit}
                        extraQuery={{
                            q: filters.q || '',
                            to_branch_id: filters.to_branch_id || '',
                            from: filters.from || '',
                            to: filters.to || '',
                            sort,
                            direction,
                        }}
                    />

                    <form
                        onSubmit={applyFilters}
                        className="flex flex-wrap items-center gap-2"
                    >
                        <div className="relative w-full sm:w-auto">
                            <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-theme-ink-muted" />
                            <input
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="Number, notes, product"
                                className="h-9 w-full sm:w-52 rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-8 pr-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                            />
                        </div>
                        <select
                            value={localFilters.to_branch_id}
                            onChange={(e) =>
                                setLocalFilters((f) => ({
                                    ...f,
                                    to_branch_id: e.target.value,
                                }))
                            }
                            className="dp-select-reset h-9 rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                            title="Destination branch"
                        >
                            <option value="">All destinations</option>
                            {branches.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.name}
                                </option>
                            ))}
                        </select>
                        <input
                            type="date"
                            value={localFilters.from}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, from: e.target.value }))
                            }
                            className="h-9 rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                            title="From date"
                        />
                        <input
                            type="date"
                            value={localFilters.to}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, to: e.target.value }))
                            }
                            className="h-9 rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
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
                                <th className="w-14 px-3 py-3 font-semibold">SN</th>
                                <SortableTh
                                    label="Date"
                                    column="created_at"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <SortableTh
                                    label="Number"
                                    column="number"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">From → To</th>
                                <th className="px-3 py-3 font-semibold">Direction</th>
                                <th className="px-3 py-3 font-semibold">Items</th>
                                <th className="px-3 py-3 text-right font-semibold">Qty</th>
                                <th className="px-3 py-3 font-semibold">By</th>
                                <th className="px-3 py-3 font-semibold">Notes</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {transfers.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={10}
                                        className="px-3 py-10 text-center text-theme-ink-muted"
                                    >
                                        No stock transfers yet.
                                    </td>
                                </tr>
                            )}
                            {transfers.data.map((row, idx) => (
                                <tr key={row.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(transfers.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {row.created_at}
                                    </td>
                                    <td className="px-3 py-3 font-mono text-xs text-theme-ink">
                                        {row.number}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink">
                                        <span className="font-medium">
                                            {row.from_branch?.name || '—'}
                                        </span>
                                        <span className="mx-1.5 text-theme-ink-muted">→</span>
                                        <span className="font-medium">
                                            {row.to_branch?.name || '—'}
                                        </span>
                                    </td>
                                    <td className="px-3 py-3">
                                        {row.direction === 'out' && (
                                            <span className="inline-flex rounded-md bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-800">
                                                Outbound
                                            </span>
                                        )}
                                        {row.direction === 'in' && (
                                            <span className="inline-flex rounded-md bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                                Inbound
                                            </span>
                                        )}
                                        {!row.direction && (
                                            <span className="text-theme-ink-muted">—</span>
                                        )}
                                    </td>
                                    <td className="max-w-[14rem] px-3 py-3">
                                        <div className="truncate text-theme-ink">
                                            {row.items?.[0]?.variant?.name ||
                                                `${row.items_count || 0} item(s)`}
                                        </div>
                                        {row.items_count > 1 && (
                                            <div className="text-xs text-theme-ink-muted">
                                                +{row.items_count - 1} more
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-3 py-3 text-right tabular-nums font-medium text-theme-ink">
                                        {formatQty(row.total_qty)}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {row.creator?.name || '—'}
                                    </td>
                                    <td className="max-w-[10rem] truncate px-3 py-3 text-theme-ink-soft">
                                        {row.notes || '—'}
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        {row.can_delete ? (
                                            <button
                                                type="button"
                                                title="Delete"
                                                aria-label="Delete"
                                                onClick={() => destroyTransfer(row)}
                                                className="inline-flex rounded-md p-1.5 text-theme-danger hover:bg-theme-danger/10"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        ) : (
                                            <span className="text-xs text-theme-ink-muted">—</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={transfers} />
            </div>

            <TransferFormDrawer
                open={showForm}
                branches={branches}
                variants={variants}
                branch={branch}
                onClose={closeForm}
            />
        </AdminLayout>
    );
}
