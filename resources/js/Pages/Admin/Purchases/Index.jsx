import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import PurchaseFormDrawer from '@/Pages/Admin/Purchases/PurchaseFormDrawer';
import { confirmDelete } from '@/lib/confirm';
import { formatAmount as money } from '@/lib/money';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

function PaymentBadge({ status }) {
    if (status === 'paid') {
        return (
            <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                Paid
            </span>
        );
    }
    if (status === 'partial') {
        return (
            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                Partial
            </span>
        );
    }
    return (
        <span className="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-semibold text-stone-600">
            Pending
        </span>
    );
}

export default function Index({
    purchases,
    filters,
    suppliers = [],
    variants = [],
    money_sources: moneySources = [],
    branch,
    editing = null,
}) {
    const { url } = usePage();
    const [showForm, setShowForm] = useState(false);
    const [editingPurchase, setEditingPurchase] = useState(null);
    const [q, setQ] = useState(filters.q || '');
    const [localFilters, setLocalFilters] = useState({
        supplier_id: filters.supplier_id || '',
        payment_status: filters.payment_status || '',
        from: filters.from || '',
        to: filters.to || '',
    });

    useEffect(() => {
        const params = new URLSearchParams(url.split('?')[1] || '');
        if (params.get('open') === '1') {
            setEditingPurchase(null);
            setShowForm(true);
        }
    }, [url]);

    useEffect(() => {
        if (editing) {
            setEditingPurchase(editing);
            setShowForm(true);
        }
    }, [editing]);

    const sort = filters.sort || 'id';
    const direction = filters.direction || 'desc';

    const listQuery = {
        q: filters.q || '',
        per_page: filters.per_page,
        sort,
        direction,
        supplier_id: filters.supplier_id || '',
        payment_status: filters.payment_status || '',
        from: filters.from || '',
        to: filters.to || '',
    };

    const visitList = (overrides = {}) => {
        router.get(route('admin.purchases.index'), { ...listQuery, ...overrides }, {
            preserveState: true,
        });
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
        setEditingPurchase(null);
        setShowForm(true);
    };

    const openEdit = (row) => {
        visitList({ edit: row.id });
    };

    const closeForm = () => {
        setShowForm(false);
        setEditingPurchase(null);
        const params = new URLSearchParams(url.split('?')[1] || '');
        if (params.get('edit') || params.get('open')) {
            visitList({ edit: undefined, open: undefined });
        }
    };

    const destroyPurchase = async (row) => {
        const ok = await confirmDelete(row.number, 'purchase');
        if (!ok) return;

        router.delete(route('admin.purchases.destroy', row.id), {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout
            title="Purchases"
            description={
                branch?.name
                    ? `Receive stock and record supplier purchases for ${branch.name}.`
                    : 'Receive stock and record supplier purchases.'
            }
            actions={
                <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" strokeWidth={2.25} />
                    New purchase
                </Button>
            }
        >
            <Head title="Purchases" />

            <div className="dp-card overflow-hidden">
                <div className="space-y-3 border-b border-theme-border px-4 py-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <PageLimitSelect
                            pageKey="purchases"
                            routeName="admin.purchases.index"
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
                                    placeholder="Search number, supplier…"
                                    className="h-9 w-full sm:w-52 rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-8 pr-3 text-sm outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                />
                            </div>
                            <Button type="submit" variant="secondary" size="sm">
                                Search
                            </Button>
                        </form>
                    </div>

                    <form onSubmit={applyFilters} className="flex flex-wrap items-center gap-2">
                        <select
                            value={localFilters.supplier_id}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, supplier_id: e.target.value }))
                            }
                            className="h-9 w-40 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All suppliers</option>
                            {suppliers.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={localFilters.payment_status}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, payment_status: e.target.value }))
                            }
                            className="h-9 w-36 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All payments</option>
                            <option value="pending">Pending</option>
                            <option value="partial">Partial</option>
                            <option value="paid">Paid</option>
                        </select>
                        <input
                            type="date"
                            value={localFilters.from}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, from: e.target.value }))
                            }
                            className="h-9 w-36 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        />
                        <input
                            type="date"
                            value={localFilters.to}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, to: e.target.value }))
                            }
                            className="h-9 w-36 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        />
                        <Button type="submit" variant="secondary" size="sm" className="shrink-0">
                            Filter
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
                                <SortableTh
                                    label="Date"
                                    column="purchase_date"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Supplier</th>
                                <SortableTh
                                    label="Total"
                                    column="total"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <SortableTh
                                    label="Payment"
                                    column="payment_status"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {purchases.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-3 py-10 text-center text-theme-ink-muted"
                                    >
                                        No purchases yet.
                                    </td>
                                </tr>
                            )}
                            {purchases.data.map((row, idx) => (
                                <tr key={row.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(purchases.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 font-mono text-xs text-theme-ink">
                                        {row.number}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {row.purchase_date}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink">
                                        {row.supplier?.name || '—'}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums font-medium text-theme-ink">
                                        {money(row.total)}
                                    </td>
                                    <td className="px-3 py-3">
                                        <PaymentBadge status={row.payment_status} />
                                    </td>
                                    <td className="px-3 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <Link
                                                href={route('admin.purchases.show', row.id)}
                                                className="inline-flex rounded-lg p-2 text-theme-ink-muted hover:bg-theme-bg hover:text-theme-ink"
                                                title="View"
                                            >
                                                <Eye className="h-4 w-4" />
                                            </Link>
                                            {row.can_edit !== false && (
                                                <button
                                                    type="button"
                                                    onClick={() => openEdit(row)}
                                                    className="inline-flex rounded-lg p-2 text-theme-ink-muted hover:bg-theme-bg hover:text-theme-ink"
                                                    title="Edit"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </button>
                                            )}
                                            {row.can_delete !== false && (
                                                <button
                                                    type="button"
                                                    onClick={() => destroyPurchase(row)}
                                                    className="inline-flex rounded-lg p-2 text-theme-danger hover:bg-theme-danger/10"
                                                    title="Delete"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={purchases} />
            </div>

            <PurchaseFormDrawer
                open={showForm}
                purchase={editingPurchase}
                suppliers={suppliers}
                variants={variants}
                moneySources={moneySources}
                onClose={closeForm}
            />
        </AdminLayout>
    );
}
