import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronDown, Search, SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';

function money(value) {
    return Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function StatusBadge({ status }) {
    if (status === 'low') {
        return (
            <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">
                Low stock
            </span>
        );
    }

    return (
        <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
            In stock
        </span>
    );
}

const filterSelectClass =
    'dp-select-reset h-9 appearance-none rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-3 pr-8 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

export default function Stock({
    stocks,
    filters,
    categories = [],
    branch = null,
    listRoute = 'admin.inventory.stock',
    lowOnly = false,
}) {
    const [q, setQ] = useState(filters.q || '');
    const [categoryId, setCategoryId] = useState(
        filters.category_id ? String(filters.category_id) : '',
    );
    const [showUnitPrice, setShowUnitPrice] = useState(false);

    const sort = filters.sort || 'product';
    const direction = filters.direction || 'asc';
    const low = lowOnly || !!filters.low;

    const listQuery = {
        q: filters.q || '',
        category_id: filters.category_id || '',
        ...(lowOnly ? {} : { low: low ? 1 : undefined }),
        per_page: filters.per_page,
        sort,
        direction,
    };

    const visitList = (overrides = {}, options = {}) => {
        const nextLow = overrides.low !== undefined ? overrides.low : listQuery.low;
        const targetRoute =
            lowOnly && nextLow === undefined
                ? 'admin.inventory.stock'
                : !lowOnly && nextLow
                  ? 'admin.inventory.low-stock'
                  : listRoute;

        const params = { ...listQuery, ...overrides };
        if (targetRoute === 'admin.inventory.low-stock') {
            delete params.low;
        }

        router.get(route(targetRoute), params, { preserveState: true, ...options });
    };

    const toggleSort = (column) => {
        const nextDirection =
            sort === column ? (direction === 'asc' ? 'desc' : 'asc') : 'asc';
        visitList({ sort: column, direction: nextDirection });
    };

    const applyFilters = (e) => {
        e.preventDefault();
        visitList({
            q,
            category_id: categoryId || undefined,
        });
    };

    const clearFilters = () => {
        setQ('');
        setCategoryId('');
        visitList({
            q: '',
            category_id: undefined,
            ...(lowOnly ? {} : { low: undefined }),
        });
    };

    const pageTitle = lowOnly
        ? branch?.name
            ? `Low stock · ${branch.name}`
            : 'Low stock'
        : branch?.name
          ? `Stock · ${branch.name}`
          : 'Stock';
    const hasActiveFilters = !!(filters.q || filters.category_id || (!lowOnly && low));

    return (
        <AdminLayout
            title={pageTitle}
            description={
                lowOnly
                    ? 'Products at or below their low-qty alert for the selected branch.'
                    : 'Current inventory for the selected branch. Stock changes through purchases, sales, adjustments, and transfers.'
            }
            actions={
                <Link href={route('admin.inventory.adjustments', { open: 1 })}>
                    <Button variant="secondary">
                        <SlidersHorizontal className="h-4 w-4" strokeWidth={2.25} />
                        Adjust stock
                    </Button>
                </Link>
            }
        >
            <Head title={lowOnly ? 'Low stock' : 'Stock'} />

            <div className="dp-card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-theme-border px-4 py-3">
                    <PageLimitSelect
                        pageKey="stock"
                        routeName={listRoute}
                        current={filters.per_page}
                        companyDefault={filters.company_page_limit}
                        extraQuery={{
                            q: filters.q || '',
                            category_id: filters.category_id || '',
                            ...(lowOnly ? {} : { low: low ? 1 : undefined }),
                            sort,
                            direction,
                        }}
                    />

                    <form
                        onSubmit={applyFilters}
                        className="flex flex-wrap items-center gap-2"
                    >
                        <div className="relative">
                            <select
                                value={categoryId}
                                onChange={(e) => setCategoryId(e.target.value)}
                                className={filterSelectClass}
                            >
                                <option value="">All categories</option>
                                {categories.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                            <ChevronDown
                                className="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-theme-ink-muted"
                                strokeWidth={2}
                            />
                        </div>

                        <div className="relative w-full sm:w-auto">
                            <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-theme-ink-muted" />
                            <input
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="Name or codes, comma-separated"
                                className="h-9 w-full sm:w-56 rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-8 pr-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20 sm:w-72"
                            />
                        </div>

                        <Button type="submit" variant="secondary" size="sm">
                            Apply
                        </Button>

                        {!lowOnly && (
                            <Button
                                type="button"
                                variant={low ? 'primary' : 'secondary'}
                                size="sm"
                                onClick={() =>
                                    visitList({
                                        q,
                                        category_id: categoryId || undefined,
                                        low: low ? undefined : 1,
                                    })
                                }
                            >
                                Low stock
                            </Button>
                        )}

                        {hasActiveFilters && (
                            <button
                                type="button"
                                onClick={clearFilters}
                                className="text-sm text-theme-ink-muted hover:text-theme-ink hover:underline"
                            >
                                Clear
                            </button>
                        )}
                    </form>
                </div>

                <div className="flex items-center justify-end border-b border-theme-border px-4 py-2.5">
                    <label className="inline-flex cursor-pointer items-center gap-2 text-sm text-theme-ink">
                        <input
                            type="checkbox"
                            checked={showUnitPrice}
                            onChange={(e) => setShowUnitPrice(e.target.checked)}
                            className="rounded border-theme-border text-theme-primary focus:ring-theme-primary/20"
                        />
                        Show unit price
                    </label>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full table-fixed text-left text-sm">
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="w-14 px-3 py-3 font-semibold">SN</th>
                                <SortableTh
                                    label="Product"
                                    column="product"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Category</th>
                                <th className="px-3 py-3 font-semibold">Code</th>
                                <SortableTh
                                    label="Qty"
                                    column="quantity"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                    align="right"
                                />
                                <th className="px-3 py-3 font-semibold">Unit</th>
                                {showUnitPrice ? (
                                    <th className="px-3 py-3 text-right font-semibold">
                                        Unit price
                                    </th>
                                ) : (
                                    <SortableTh
                                        label="Avg cost"
                                        column="average_cost"
                                        sort={sort}
                                        direction={direction}
                                        onSort={toggleSort}
                                        align="right"
                                    />
                                )}
                                <th className="px-3 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {stocks.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-3 py-10 text-center text-theme-ink-muted"
                                    >
                                        No stock rows for this branch
                                        {hasActiveFilters ? ' with these filters' : ''}.
                                    </td>
                                </tr>
                            )}
                            {stocks.data.map((row, idx) => (
                                <tr
                                    key={row.id}
                                    className={`border-t border-theme-border ${
                                        row.is_low ? 'bg-red-50/70' : ''
                                    }`}
                                >
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(stocks.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 font-medium text-theme-ink">
                                        {row.variant?.name || '—'}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {row.category?.name || '—'}
                                    </td>
                                    <td className="px-3 py-3 font-mono text-xs text-theme-ink-soft">
                                        {row.variant?.short_code ||
                                            row.variant?.sku ||
                                            row.variant?.barcode ||
                                            '—'}
                                    </td>
                                    <td
                                        className={`px-3 py-3 text-right tabular-nums font-medium ${
                                            row.is_low
                                                ? 'text-red-700'
                                                : 'text-theme-ink'
                                        }`}
                                    >
                                        {money(row.quantity)}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {row.unit?.name || '—'}
                                    </td>
                                    <td className="px-3 py-3 text-right tabular-nums text-theme-ink-soft">
                                        {showUnitPrice
                                            ? money(row.variant?.sale_price)
                                            : money(row.average_cost)}
                                    </td>
                                    <td className="px-3 py-3">
                                        <StatusBadge status={row.status} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={stocks} />
            </div>
        </AdminLayout>
    );
}
