import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import PurchaseReturnFormDrawer from '@/Pages/Admin/PurchaseReturns/PurchaseReturnFormDrawer';
import { confirmDelete } from '@/lib/confirm';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

function money(value) {
    return Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

export default function Index({
    returns,
    filters,
    suppliers = [],
    purchases = [],
    selected_purchase: selectedPurchase = null,
    form_supplier_id: formSupplierId = null,
    form_open: formOpen = false,
    editing = null,
    branch,
}) {
    const { url } = usePage();
    const [showForm, setShowForm] = useState(false);
    const [editingReturn, setEditingReturn] = useState(null);
    const [q, setQ] = useState(filters.q || '');
    const [localFilters, setLocalFilters] = useState({
        supplier_id: filters.supplier_id || '',
        from: filters.from || '',
        to: filters.to || '',
    });

    useEffect(() => {
        if (editing) {
            setEditingReturn(editing);
            setShowForm(true);
            return;
        }
        if (formOpen) {
            setShowForm(true);
        }
    }, [formOpen, formSupplierId, selectedPurchase?.id, editing]);

    useEffect(() => {
        const params = new URLSearchParams(url.split('?')[1] || '');
        if (params.get('open') === '1' || params.get('edit')) {
            setShowForm(true);
        }
    }, [url]);

    const sort = filters.sort || 'id';
    const direction = filters.direction || 'desc';

    const listQuery = {
        q: filters.q || '',
        per_page: filters.per_page,
        sort,
        direction,
        supplier_id: filters.supplier_id || '',
        from: filters.from || '',
        to: filters.to || '',
    };

    const visitList = (overrides = {}) => {
        router.get(route('admin.returns.purchases.index'), { ...listQuery, ...overrides }, {
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
        setEditingReturn(null);
        setShowForm(true);
        visitList({
            open: 1,
            edit: undefined,
            form_supplier_id: undefined,
            purchase_id: undefined,
        });
    };

    const openEdit = (row) => {
        visitList({
            edit: row.id,
            open: undefined,
            form_supplier_id: undefined,
            purchase_id: undefined,
        });
    };

    const closeForm = () => {
        setShowForm(false);
        setEditingReturn(null);
        visitList({
            open: undefined,
            edit: undefined,
            form_supplier_id: undefined,
            purchase_id: undefined,
        });
    };

    const destroyReturn = async (row) => {
        const ok = await confirmDelete(row.number, 'purchase return');
        if (!ok) return;

        router.delete(route('admin.returns.purchases.destroy', row.id), {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout
            title="Purchase returns"
            description={
                branch?.name
                    ? `Return stock against purchases for ${branch.name}.`
                    : 'Return stock against a purchase.'
            }
            actions={
                <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" strokeWidth={2.25} />
                    New return
                </Button>
            }
        >
            <Head title="Purchase returns" />

            <div className="dp-card overflow-hidden">
                <div className="space-y-3 border-b border-theme-border px-4 py-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <PageLimitSelect
                            pageKey="purchase-returns"
                            routeName="admin.returns.purchases.index"
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
                                    placeholder="Search number, supplier…"
                                    className="h-9 w-52 rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-8 pr-3 text-sm outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
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
                                    column="return_date"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Purchase</th>
                                <th className="px-3 py-3 font-semibold">Supplier</th>
                                <SortableTh
                                    label="Total"
                                    column="total"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {returns.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-3 py-10 text-center text-theme-ink-muted"
                                    >
                                        No purchase returns yet.
                                    </td>
                                </tr>
                            )}
                            {returns.data.map((row, idx) => (
                                <tr key={row.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(returns.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 font-mono text-xs">{row.number}</td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {row.return_date}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {row.purchase?.number || '—'}
                                    </td>
                                    <td className="px-3 py-3">{row.supplier?.name || '—'}</td>
                                    <td className="px-3 py-3 tabular-nums font-medium">
                                        {money(row.total)}
                                    </td>
                                    <td className="px-3 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <Link
                                                href={route('admin.returns.purchases.show', row.id)}
                                                className="inline-flex rounded-lg p-2 text-theme-ink-muted hover:bg-theme-bg hover:text-theme-ink"
                                                title="View"
                                            >
                                                <Eye className="h-4 w-4" />
                                            </Link>
                                            <button
                                                type="button"
                                                onClick={() => openEdit(row)}
                                                className="inline-flex rounded-lg p-2 text-theme-ink-muted hover:bg-theme-bg hover:text-theme-ink"
                                                title="Edit"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => destroyReturn(row)}
                                                className="inline-flex rounded-lg p-2 text-theme-danger hover:bg-theme-danger/10"
                                                title="Delete"
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

                <Pagination paginator={returns} />
            </div>

            <PurchaseReturnFormDrawer
                open={showForm}
                purchaseReturn={editingReturn}
                suppliers={suppliers}
                purchases={purchases}
                selectedPurchase={selectedPurchase}
                formSupplierId={formSupplierId}
                listQuery={listQuery}
                onClose={closeForm}
            />
        </AdminLayout>
    );
}
