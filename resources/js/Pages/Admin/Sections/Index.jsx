import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import SectionExportMenu from '@/Pages/Admin/Sections/SectionExportMenu';
import SectionFormDrawer from '@/Pages/Admin/Sections/SectionFormDrawer';
import SectionImportDrawer from '@/Pages/Admin/Sections/SectionImportDrawer';
import { confirmDelete } from '@/lib/confirm';
import { Head, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2, Upload } from 'lucide-react';
import { useEffect, useState } from 'react';

export default function Index({ sections, filters }) {
    const { flash } = usePage().props;
    const importResult =
        flash?.import_result?.entity === 'sections' ? flash.import_result : null;

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
            route('admin.sections.index'),
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

    const openEdit = (section) => {
        setEditing(section);
        setShowForm(true);
    };

    const closeForm = () => {
        setShowForm(false);
        setEditing(null);
    };

    const destroySection = async (section) => {
        const ok = await confirmDelete(section.name, 'section');
        if (!ok) return;

        router.delete(route('admin.sections.destroy', section.id), {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout
            title="Sections"
            description="Warehouse/shop sections and racks for product locations."
            actions={
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="secondary" onClick={() => setShowImport(true)}>
                        <Upload className="h-4 w-4" strokeWidth={2.25} />
                        Import
                    </Button>
                    <SectionExportMenu />
                    <Button onClick={openCreate}>
                        <Plus className="h-4 w-4" strokeWidth={2.25} />
                        Add Section
                    </Button>
                </div>
            }
        >
            <Head title="Sections" />

            <div className="dp-card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-theme-border px-4 py-3">
                    <PageLimitSelect
                        pageKey="sections"
                        routeName="admin.sections.index"
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
                                placeholder="Search sections"
                                className="h-9 w-52 rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-8 pr-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
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
                            <col className="w-40" />
                            <col />
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
                                <th className="px-3 py-3 font-semibold">Racks</th>
                                <th className="px-3 py-3 font-semibold">Status</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sections.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-3 py-10 text-center text-theme-ink-muted">
                                        No sections yet.
                                    </td>
                                </tr>
                            )}
                            {sections.data.map((section, idx) => (
                                <tr key={section.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(sections.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">{section.code || '—'}</td>
                                    <td className="px-3 py-3 font-medium text-theme-ink">{section.name}</td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {section.racks?.length
                                            ? section.racks.map((r) => r.name).join(', ')
                                            : '—'}
                                    </td>
                                    <td className="px-3 py-3">
                                        {section.is_active ? (
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
                                                onClick={() => openEdit(section)}
                                                className="inline-flex rounded-md p-1.5 text-theme-primary hover:bg-theme-bg"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                title="Delete"
                                                aria-label="Delete"
                                                onClick={() => destroySection(section)}
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

                <Pagination paginator={sections} />
            </div>

            <SectionFormDrawer open={showForm} section={editing} onClose={closeForm} />
            <SectionImportDrawer
                open={showImport}
                onClose={() => setShowImport(false)}
                result={importResult}
            />
        </AdminLayout>
    );
}
