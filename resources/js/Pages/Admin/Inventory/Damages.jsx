import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import DamageFormDrawer from '@/Pages/Admin/Inventory/DamageFormDrawer';
import { confirmDelete } from '@/lib/confirm';
import { Head, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
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

function money(value) {
    return Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

export default function Damages({
    damages,
    filters,
    variants = [],
    reasons = [],
    form_open: formOpen = false,
    editing_damage: editingDamage = null,
    branch = null,
}) {
    const { url } = usePage();
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [q, setQ] = useState(filters.q || '');
    const [localFilters, setLocalFilters] = useState({
        reason: filters.reason || '',
        from: filters.from || '',
        to: filters.to || '',
    });

    useEffect(() => {
        if (editingDamage) {
            setEditing(editingDamage);
            setShowForm(true);
        } else if (formOpen) {
            setEditing(null);
            setShowForm(true);
        }
    }, [formOpen, editingDamage]);

    useEffect(() => {
        const params = new URLSearchParams(url.split('?')[1] || '');
        if (params.get('open') === '1' && !params.get('form_damage_id')) {
            setEditing(null);
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
        reason: filters.reason || '',
        from: filters.from || '',
        to: filters.to || '',
    };

    const visitList = (overrides = {}, options = {}) => {
        router.get(
            route('admin.inventory.damages'),
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
        visitList({ open: 1, form_damage_id: undefined });
    };

    const openEdit = (row) => {
        setEditing(row);
        setShowForm(true);
        visitList({ open: 1, form_damage_id: row.id });
    };

    const closeForm = () => {
        setShowForm(false);
        setEditing(null);
        visitList({ open: undefined, form_damage_id: undefined });
    };

    const destroyDamage = async (row) => {
        const label = `${row.number} · ${row.reason_label || row.reason}`;
        const ok = await confirmDelete(label, 'damage record');
        if (!ok) return;

        router.delete(route('admin.inventory.damages.destroy', row.id), {
            preserveScroll: true,
        });
    };

    const title = branch?.name ? `Damage · ${branch.name}` : 'Damage';

    return (
        <AdminLayout
            title={title}
            description="Record stock lost to expiry, breakage, leakage, or product fault. Editing or deleting restores stock."
            actions={
                <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" strokeWidth={2.25} />
                    Record damage
                </Button>
            }
        >
            <Head title="Damage" />

            <div className="dp-card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-theme-border px-4 py-3">
                    <PageLimitSelect
                        pageKey="stock-damages"
                        routeName="admin.inventory.damages"
                        current={filters.per_page}
                        companyDefault={filters.company_page_limit}
                        extraQuery={{
                            q: filters.q || '',
                            reason: filters.reason || '',
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
                        <div className="relative">
                            <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-theme-ink-muted" />
                            <input
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="Number, notes, product"
                                className="h-9 w-52 rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-8 pr-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                            />
                        </div>
                        <select
                            value={localFilters.reason}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, reason: e.target.value }))
                            }
                            className="dp-select-reset h-9 rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                            title="Reason"
                        >
                            <option value="">All reasons</option>
                            {reasons.map((r) => (
                                <option key={r.value} value={r.value}>
                                    {r.label}
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
                                <SortableTh
                                    label="Reason"
                                    column="reason"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Items</th>
                                <th className="px-3 py-3 text-right font-semibold">Qty</th>
                                <th className="px-3 py-3 text-right font-semibold">Loss</th>
                                <th className="px-3 py-3 font-semibold">By</th>
                                <th className="px-3 py-3 font-semibold">Notes</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {damages.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={10}
                                        className="px-3 py-10 text-center text-theme-ink-muted"
                                    >
                                        No damage records yet.
                                    </td>
                                </tr>
                            )}
                            {damages.data.map((row, idx) => (
                                <tr key={row.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(damages.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {row.created_at}
                                    </td>
                                    <td className="px-3 py-3 font-mono text-xs text-theme-ink">
                                        {row.number}
                                    </td>
                                    <td className="px-3 py-3">
                                        <span className="inline-flex rounded-md bg-theme-danger/10 px-2 py-0.5 text-xs font-medium text-theme-danger">
                                            {row.reason_label || row.reason}
                                        </span>
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
                                    <td className="px-3 py-3 text-right tabular-nums text-theme-ink-soft">
                                        {money(row.total_cost)}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {row.creator?.name || '—'}
                                    </td>
                                    <td className="max-w-[10rem] truncate px-3 py-3 text-theme-ink-soft">
                                        {row.notes || '—'}
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        <div className="inline-flex items-center gap-0.5">
                                            <button
                                                type="button"
                                                title="Edit"
                                                aria-label="Edit"
                                                onClick={() => openEdit(row)}
                                                className="inline-flex rounded-md p-1.5 text-theme-ink-muted hover:bg-theme-bg hover:text-theme-ink"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                title="Delete"
                                                aria-label="Delete"
                                                onClick={() => destroyDamage(row)}
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

                <Pagination paginator={damages} />
            </div>

            <DamageFormDrawer
                open={showForm}
                damage={editing}
                variants={variants}
                reasons={reasons}
                branch={branch}
                onClose={closeForm}
            />
        </AdminLayout>
    );
}
