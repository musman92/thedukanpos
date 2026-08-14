import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import BrandExportMenu from '@/Pages/Admin/Brands/BrandExportMenu';
import BrandFormDrawer from '@/Pages/Admin/Brands/BrandFormDrawer';
import BrandImportDrawer from '@/Pages/Admin/Brands/BrandImportDrawer';
import { confirmDelete } from '@/lib/confirm';
import { Head, router, usePage } from '@inertiajs/react';
import { ImagePlus, Pencil, Plus, Search, Trash2, Upload } from 'lucide-react';
import { useEffect, useState } from 'react';

export default function Index({ brands, filters }) {
    const { flash } = usePage().props;
    const importResult =
        flash?.import_result?.entity === 'brands' ||
        (flash?.import_result && !flash.import_result.entity)
            ? flash.import_result
            : null;

    const [showForm, setShowForm] = useState(false);
    const [showImport, setShowImport] = useState(!!importResult);
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

    useEffect(() => {
        if (importResult) {
            setShowImport(true);
        }
    }, [importResult]);

    const visitList = (overrides = {}, options = {}) => {
        router.get(
            route('admin.brands.index'),
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

    const openEdit = (brand) => {
        setEditing(brand);
        setShowForm(true);
    };

    const closeForm = () => {
        setShowForm(false);
        setEditing(null);
    };

    const destroyBrand = async (brand) => {
        const ok = await confirmDelete(brand.name, 'brand');
        if (!ok) return;

        router.delete(route('admin.brands.destroy', brand.id), {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout
            title="Brands"
            description="Product brands used across your catalog."
            actions={
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="secondary" onClick={() => setShowImport(true)}>
                        <Upload className="h-4 w-4" strokeWidth={2.25} />
                        Import
                    </Button>
                    <BrandExportMenu />
                    <Button onClick={openCreate}>
                        <Plus className="h-4 w-4" strokeWidth={2.25} />
                        Add Brand
                    </Button>
                </div>
            }
        >
            <Head title="Brands" />

            <div className="dp-card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-theme-border px-4 py-3">
                    <PageLimitSelect
                        pageKey="brands"
                        routeName="admin.brands.index"
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
                        className="flex w-full items-center gap-2 sm:w-auto"
                    >
                        <div className="relative w-full sm:w-auto">
                            <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-theme-ink-muted" />
                            <input
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="Search brands"
                                className="h-9 w-full sm:w-48 rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-8 pr-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
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
                            <col className="w-14" />
                            <col className="w-28" />
                            <col />
                            <col className="w-24" />
                            <col className="w-24" />
                            <col className="w-32" />
                        </colgroup>
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="px-3 py-3 font-semibold">SN</th>
                                <th className="px-3 py-3 font-semibold">Image</th>
                                <SortableTh
                                    label="Code"
                                    column="code"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <SortableTh
                                    label="Name"
                                    column="name"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Products</th>
                                <th className="px-3 py-3 font-semibold">Status</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {brands.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-3 py-10 text-center text-theme-ink-muted">
                                        No brands yet.
                                    </td>
                                </tr>
                            )}
                            {brands.data.map((brand, idx) => (
                                <tr key={brand.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(brands.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3">
                                        {brand.image_url ? (
                                            <img
                                                src={brand.image_url}
                                                alt=""
                                                className="h-9 w-9 rounded-md object-cover ring-1 ring-theme-border"
                                            />
                                        ) : (
                                            <span className="flex h-9 w-9 items-center justify-center rounded-md bg-theme-bg text-theme-ink-muted ring-1 ring-theme-border">
                                                <ImagePlus className="h-3.5 w-3.5" />
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">{brand.code || '—'}</td>
                                    <td className="px-3 py-3 font-medium text-theme-ink">{brand.name}</td>
                                    <td className="px-3 py-3 text-theme-ink-soft">{brand.products_count}</td>
                                    <td className="px-3 py-3">
                                        {brand.is_active ? (
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
                                            <button
                                                type="button"
                                                title="Edit"
                                                aria-label="Edit"
                                                onClick={() => openEdit(brand)}
                                                className="inline-flex rounded-md p-1.5 text-theme-primary hover:bg-theme-bg"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                title="Delete"
                                                aria-label="Delete"
                                                onClick={() => destroyBrand(brand)}
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

                <Pagination paginator={brands} />
            </div>

            <BrandFormDrawer open={showForm} brand={editing} onClose={closeForm} />
            <BrandImportDrawer
                open={showImport}
                onClose={() => setShowImport(false)}
                result={importResult}
            />
        </AdminLayout>
    );
}
