import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import ProductExportMenu from '@/Pages/Admin/Products/ProductExportMenu';
import ProductImportDrawer from '@/Pages/Admin/Products/ProductImportDrawer';
import { confirmDelete } from '@/lib/confirm';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Copy, ImagePlus, Pencil, Plus, Search, Trash2, Upload } from 'lucide-react';
import { useEffect, useState } from 'react';

export default function Index({ products, filters }) {
    const { flash } = usePage().props;
    const importResult =
        flash?.import_result?.entity === 'products' ? flash.import_result : null;

    const [showImport, setShowImport] = useState(!!importResult);
    const [q, setQ] = useState(filters.q || '');

    const sort = filters.sort || 'id';
    const direction = filters.direction || 'desc';

    const listQuery = {
        q: filters.q || '',
        per_page: filters.per_page,
        sort,
        direction,
    };

    useEffect(() => {
        if (importResult) {
            setShowImport(true);
        }
    }, [importResult]);

    const visitList = (overrides = {}, options = {}) => {
        router.get(
            route('admin.products.index'),
            { ...listQuery, ...overrides },
            { preserveState: true, ...options },
        );
    };

    const toggleSort = (column) => {
        const nextDirection =
            sort === column ? (direction === 'asc' ? 'desc' : 'asc') : 'asc';

        visitList({ sort: column, direction: nextDirection });
    };

    const destroyProduct = async (product) => {
        const ok = await confirmDelete(product.name, 'product');
        if (!ok) return;

        router.delete(route('admin.products.destroy', product.id), {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout
            title="Products"
            description="Catalog products with variants, units, tax, and branch locations."
            actions={
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="secondary" onClick={() => setShowImport(true)}>
                        <Upload className="h-4 w-4" strokeWidth={2.25} />
                        Import
                    </Button>
                    <ProductExportMenu />
                    <Link href={route('admin.products.create')}>
                        <Button>
                            <Plus className="h-4 w-4" strokeWidth={2.25} />
                            Add Product
                        </Button>
                    </Link>
                </div>
            }
        >
            <Head title="Products" />

            <div className="dp-card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-theme-border px-4 py-3">
                    <PageLimitSelect
                        pageKey="products"
                        routeName="admin.products.index"
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
                                placeholder="Search products"
                                className="h-9 w-56 rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-8 pr-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                            />
                        </div>
                        <Button type="submit" variant="secondary" size="sm">
                            Search
                        </Button>
                    </form>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="px-3 py-3 font-semibold">SN</th>
                                <th className="px-3 py-3 font-semibold">Image</th>
                                <SortableTh
                                    label="Name"
                                    column="name"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <SortableTh
                                    label="Code"
                                    column="short_code"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Brand</th>
                                <th className="px-3 py-3 font-semibold">Variants</th>
                                <SortableTh
                                    label="Price"
                                    column="sale_price"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Status</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {products.data.length === 0 && (
                                <tr>
                                    <td colSpan={9} className="px-3 py-10 text-center text-theme-ink-muted">
                                        No products yet.
                                    </td>
                                </tr>
                            )}
                            {products.data.map((product, idx) => (
                                <tr key={product.id} className="border-t border-theme-border align-top">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(products.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3">
                                        {product.image_url ? (
                                            <img
                                                src={product.image_url}
                                                alt=""
                                                className="h-9 w-9 rounded-md object-cover ring-1 ring-theme-border"
                                            />
                                        ) : (
                                            <span className="flex h-9 w-9 items-center justify-center rounded-md bg-theme-bg text-theme-ink-muted ring-1 ring-theme-border">
                                                <ImagePlus className="h-3.5 w-3.5" />
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-3">
                                        <p className="font-medium text-theme-ink">{product.name}</p>
                                        <p className="mt-0.5 text-xs text-theme-ink-muted">
                                            {(product.type || 'single') === 'variant' ? 'Variant' : 'Single'}
                                            {product.tax?.name ? ` · Tax: ${product.tax.name}` : ' · Tax exempt'}
                                        </p>
                                        {(product.variants || []).length > 0 && (
                                            <div className="mt-2 space-y-1 text-xs text-theme-ink-soft">
                                                {product.variants.slice(0, 3).map((v) => {
                                                    const loc = v.locations?.[0];
                                                    const stock = v.stocks?.[0];
                                                    return (
                                                        <div key={v.id} className="flex flex-wrap gap-x-2">
                                                            <span className="font-mono">{v.short_code}</span>
                                                            <span>{v.name || 'Standard'}</span>
                                                            <span>
                                                                Stock {stock ? Number(stock.quantity).toFixed(2) : '0'}
                                                            </span>
                                                            <span>
                                                                {loc
                                                                    ? [loc.section?.name, loc.rack?.name]
                                                                          .filter(Boolean)
                                                                          .join(' → ') || '—'
                                                                    : '—'}
                                                            </span>
                                                        </div>
                                                    );
                                                })}
                                                {product.variants.length > 3 && (
                                                    <p>+{product.variants.length - 3} more</p>
                                                )}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-3 py-3 font-mono text-xs text-theme-ink-soft">
                                        {product.short_code || '—'}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {product.brand?.name || '—'}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {product.variants_count ?? product.variants?.length ?? 0}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {Number(product.sale_price || 0).toFixed(2)}
                                    </td>
                                    <td className="px-3 py-3">
                                        {product.is_active ? (
                                            <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                                                Active
                                            </span>
                                        ) : (
                                            <span className="rounded-full bg-theme-bg px-2 py-0.5 text-xs font-semibold text-theme-ink-soft">
                                                Inactive
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            <Link
                                                href={route('admin.products.edit', product.id)}
                                                title="Edit"
                                                aria-label="Edit"
                                                className="inline-flex rounded-md p-1.5 text-theme-primary hover:bg-theme-bg"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </Link>
                                            <button
                                                type="button"
                                                title="Duplicate"
                                                aria-label="Duplicate"
                                                onClick={() => {
                                                    router.post(
                                                        route('admin.products.duplicate', product.id),
                                                        {},
                                                        { preserveScroll: true },
                                                    );
                                                }}
                                                className="inline-flex rounded-md p-1.5 text-theme-ink-soft hover:bg-theme-bg"
                                            >
                                                <Copy className="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                title="Delete"
                                                aria-label="Delete"
                                                onClick={() => destroyProduct(product)}
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

                <Pagination paginator={products} />
            </div>

            <ProductImportDrawer
                open={showImport}
                onClose={() => setShowImport(false)}
                result={importResult}
            />
        </AdminLayout>
    );
}
