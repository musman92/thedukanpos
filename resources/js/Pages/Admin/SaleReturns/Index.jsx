import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import SaleReturnFormDrawer from '@/Pages/Admin/SaleReturns/SaleReturnFormDrawer';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, Plus, Search } from 'lucide-react';
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
    customers = [],
    sales = [],
    selected_sale: selectedSale = null,
    form_open: formOpen = false,
    branch,
}) {
    const { url } = usePage();
    const [showForm, setShowForm] = useState(false);
    const [q, setQ] = useState(filters.q || '');
    const [localFilters, setLocalFilters] = useState({
        customer_id: filters.customer_id || '',
        from: filters.from || '',
        to: filters.to || '',
    });

    useEffect(() => {
        if (formOpen) {
            setShowForm(true);
        }
    }, [formOpen, selectedSale?.id]);

    useEffect(() => {
        const params = new URLSearchParams(url.split('?')[1] || '');
        if (params.get('open') === '1') {
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
        customer_id: filters.customer_id || '',
        from: filters.from || '',
        to: filters.to || '',
    };

    const visitList = (overrides = {}) => {
        router.get(route('admin.returns.sales.index'), { ...listQuery, ...overrides }, {
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
        setShowForm(true);
        visitList({
            open: 1,
            sale_id: undefined,
        });
    };

    const closeForm = () => {
        setShowForm(false);
        visitList({
            open: undefined,
            sale_id: undefined,
        });
    };

    return (
        <AdminLayout
            title="Refund orders"
            description={
                branch?.name
                    ? `Refund completed sales and restock items for ${branch.name}.`
                    : 'Refund items against a completed sale.'
            }
            actions={
                <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" strokeWidth={2.25} />
                    New refund
                </Button>
            }
        >
            <Head title="Refund orders" />

            <div className="dp-card overflow-hidden">
                <div className="space-y-3 border-b border-theme-border px-4 py-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <PageLimitSelect
                            pageKey="sale-returns"
                            routeName="admin.returns.sales.index"
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
                                    placeholder="Search number, customer…"
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
                            value={localFilters.customer_id}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, customer_id: e.target.value }))
                            }
                            className="h-9 w-40 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All customers</option>
                            {customers.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
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
                                    label="When"
                                    column="return_date"
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
                                <th className="px-3 py-3 font-semibold">Sale #</th>
                                <th className="px-3 py-3 font-semibold">Customer</th>
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
                                        No refund orders yet.
                                    </td>
                                </tr>
                            )}
                            {returns.data.map((row, idx) => (
                                <tr key={row.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(returns.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {row.return_date}
                                    </td>
                                    <td className="px-3 py-3 font-mono text-xs">{row.number}</td>
                                    <td className="px-3 py-3 font-mono text-xs text-theme-ink-soft">
                                        {row.sale?.number || '—'}
                                    </td>
                                    <td className="px-3 py-3">{row.customer?.name || '—'}</td>
                                    <td className="px-3 py-3 tabular-nums font-medium">
                                        {money(row.total)}
                                    </td>
                                    <td className="px-3 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <Link
                                                href={route('admin.returns.sales.show', row.id)}
                                                className="inline-flex rounded-lg p-2 text-theme-ink-muted hover:bg-theme-bg hover:text-theme-ink"
                                                title="View"
                                            >
                                                <Eye className="h-4 w-4" />
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={returns} />
            </div>

            <SaleReturnFormDrawer
                open={showForm}
                sales={sales}
                selectedSale={selectedSale}
                listQuery={listQuery}
                onClose={closeForm}
            />
        </AdminLayout>
    );
}
