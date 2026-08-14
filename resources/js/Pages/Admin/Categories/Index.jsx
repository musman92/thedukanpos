import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import CategoryExportMenu from '@/Pages/Admin/Categories/CategoryExportMenu';
import CategoryFormDrawer from '@/Pages/Admin/Categories/CategoryFormDrawer';
import CategoryImportDrawer from '@/Pages/Admin/Categories/CategoryImportDrawer';
import { confirmDelete } from '@/lib/confirm';
import { Head, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2, Upload } from 'lucide-react';
import { useEffect, useState } from 'react';

export default function Index({ categories, filters, parentOptions, taxes }) {
    const { flash } = usePage().props;
    const importResult =
        flash?.import_result?.entity === 'categories' ? flash.import_result : null;

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
            route('admin.categories.index'),
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

    const openEdit = (category) => {
        setEditing(category);
        setShowForm(true);
    };

    const closeForm = () => {
        setShowForm(false);
        setEditing(null);
    };

    const destroyCategory = async (category) => {
        const ok = await confirmDelete(category.name, 'category');
        if (!ok) return;

        router.delete(route('admin.categories.destroy', category.id), {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout
            title="Categories"
            description="Product categories, optional parent nesting, and default tax."
            actions={
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="secondary" onClick={() => setShowImport(true)}>
                        <Upload className="h-4 w-4" strokeWidth={2.25} />
                        Import
                    </Button>
                    <CategoryExportMenu />
                    <Button onClick={openCreate}>
                        <Plus className="h-4 w-4" strokeWidth={2.25} />
                        Add Category
                    </Button>
                </div>
            }
        >
            <Head title="Categories" />

            <div className="dp-card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-theme-border px-4 py-3">
                    <PageLimitSelect
                        pageKey="categories"
                        routeName="admin.categories.index"
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
                                placeholder="Search categories"
                                className="h-9 w-full sm:w-52 rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-8 pr-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
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
                            <col className="w-24" />
                            <col />
                            <col className="w-36" />
                            <col className="w-36" />
                            <col className="w-24" />
                            <col className="w-24" />
                            <col className="w-32" />
                        </colgroup>
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="px-3 py-3 font-semibold">SN</th>
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
                                <th className="px-3 py-3 font-semibold">Parent</th>
                                <th className="px-3 py-3 font-semibold">Default tax</th>
                                <th className="px-3 py-3 font-semibold">Products</th>
                                <th className="px-3 py-3 font-semibold">Status</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {categories.data.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="px-3 py-10 text-center text-theme-ink-muted">
                                        No categories yet.
                                    </td>
                                </tr>
                            )}
                            {categories.data.map((category, idx) => (
                                <tr key={category.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(categories.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">{category.code || '—'}</td>
                                    <td className="px-3 py-3 font-medium text-theme-ink">{category.name}</td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {category.parent?.name || '—'}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {category.default_tax
                                            ? `${category.default_tax.name}${category.default_tax.rate != null ? ` (${category.default_tax.rate}%)` : ''}`
                                            : '—'}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">{category.products_count}</td>
                                    <td className="px-3 py-3">
                                        {category.is_active ? (
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
                                                onClick={() => openEdit(category)}
                                                className="inline-flex rounded-md p-1.5 text-theme-primary hover:bg-theme-bg"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            {!category.is_system && (
                                                <button
                                                    type="button"
                                                    title="Delete"
                                                    aria-label="Delete"
                                                    onClick={() => destroyCategory(category)}
                                                    className="inline-flex rounded-md p-1.5 text-theme-danger hover:bg-theme-danger/10"
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

                <Pagination paginator={categories} />
            </div>

            <CategoryFormDrawer
                open={showForm}
                category={editing}
                parentOptions={parentOptions}
                taxes={taxes}
                onClose={closeForm}
            />
            <CategoryImportDrawer
                open={showImport}
                onClose={() => setShowImport(false)}
                result={importResult}
            />
        </AdminLayout>
    );
}
