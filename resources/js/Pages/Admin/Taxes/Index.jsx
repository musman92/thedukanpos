import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import TaxFormDrawer from '@/Pages/Admin/Taxes/TaxFormDrawer';
import { confirmDelete } from '@/lib/confirm';
import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';

export default function Index({ taxes, filters }) {
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [q, setQ] = useState(filters.q || '');

    const sort = filters.sort || 'id';
    const direction = filters.direction || 'desc';

    const listQuery = {
        q: filters.q || '',
        per_page: filters.per_page,
        sort,
        direction,
    };

    const visitList = (overrides = {}, options = {}) => {
        router.get(
            route('admin.finance.taxes.index'),
            { ...listQuery, ...overrides },
            { preserveState: true, ...options },
        );
    };

    const toggleSort = (column) => {
        const nextDirection =
            sort === column ? (direction === 'asc' ? 'desc' : 'asc') : 'asc';

        visitList({ sort: column, direction: nextDirection });
    };

    const openCreate = () => {
        setEditing(null);
        setShowForm(true);
    };

    const openEdit = (tax) => {
        setEditing(tax);
        setShowForm(true);
    };

    const closeForm = () => {
        setShowForm(false);
        setEditing(null);
    };

    const destroyTax = async (tax) => {
        const ok = await confirmDelete(tax.name, 'tax');
        if (!ok) return;

        router.delete(route('admin.finance.taxes.destroy', tax.id), {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout
            title="Taxes"
            description="Tax rates for products and sales."
            actions={
                <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" strokeWidth={2.25} />
                    Add Tax
                </Button>
            }
        >
            <Head title="Taxes" />

            <div className="dp-card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-theme-border px-4 py-3">
                    <PageLimitSelect
                        pageKey="taxes"
                        routeName="admin.finance.taxes.index"
                        current={filters.per_page}
                        companyDefault={filters.company_page_limit}
                        extraQuery={{
                            q: filters.q || '',
                            sort,
                            direction,
                        }}
                    />
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            visitList({ q });
                        }}
                        className="flex items-center gap-2"
                    >
                        <div className="relative">
                            <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-theme-ink-muted" />
                            <input
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="Search taxes"
                                className="h-9 w-48 rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-8 pr-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                            />
                        </div>
                        <Button type="submit" variant="secondary" size="sm">
                            Search
                        </Button>
                    </form>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full table-fixed text-left text-sm">
                        <colgroup>
                            <col className="w-12" />
                            <col />
                            <col className="w-24" />
                            <col className="w-24" />
                            <col className="w-28" />
                            <col className="w-24" />
                            <col className="w-28" />
                        </colgroup>
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="px-3 py-3 font-semibold">SN</th>
                                <SortableTh
                                    label="Name"
                                    column="name"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <SortableTh
                                    label="Code"
                                    column="code"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <SortableTh
                                    label="Rate"
                                    column="rate"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Inclusive</th>
                                <SortableTh
                                    label="Status"
                                    column="is_active"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {taxes.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-3 py-10 text-center text-theme-ink-muted"
                                    >
                                        No taxes yet.
                                    </td>
                                </tr>
                            )}
                            {taxes.data.map((tax, idx) => (
                                <tr key={tax.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(taxes.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 font-medium text-theme-ink">
                                        {tax.name}
                                    </td>
                                    <td className="px-3 py-3 font-mono text-xs text-theme-ink-soft">
                                        {tax.code || '—'}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink">
                                        {Number(tax.rate).toLocaleString(undefined, {
                                            maximumFractionDigits: 4,
                                        })}
                                        %
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {tax.is_inclusive ? 'Yes' : 'No'}
                                    </td>
                                    <td className="px-3 py-3">
                                        {tax.is_active ? (
                                            <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                                                Active
                                            </span>
                                        ) : (
                                            <span className="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-semibold text-stone-600">
                                                Inactive
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                title="Edit"
                                                aria-label="Edit"
                                                onClick={() => openEdit(tax)}
                                                className="inline-flex rounded-md p-1.5 text-theme-primary hover:bg-theme-bg"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                title="Delete"
                                                aria-label="Delete"
                                                onClick={() => destroyTax(tax)}
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

                <Pagination paginator={taxes} />
            </div>

            <TaxFormDrawer open={showForm} tax={editing} onClose={closeForm} />
        </AdminLayout>
    );
}
