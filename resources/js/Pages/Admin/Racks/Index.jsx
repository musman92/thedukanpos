import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import RackExportMenu from '@/Pages/Admin/Racks/RackExportMenu';
import RackFormDrawer from '@/Pages/Admin/Racks/RackFormDrawer';
import RackImportDrawer from '@/Pages/Admin/Racks/RackImportDrawer';
import { confirmDelete } from '@/lib/confirm';
import { Head, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2, Upload } from 'lucide-react';
import { useEffect, useState } from 'react';

export default function Index({ racks, filters, sections }) {
    const { flash } = usePage().props;
    const importResult =
        flash?.import_result?.entity === 'racks' ? flash.import_result : null;

    const [showForm, setShowForm] = useState(false);
    const [showImport, setShowImport] = useState(!!importResult);
    const [editing, setEditing] = useState(null);
    const [q, setQ] = useState(filters.q || '');
    const [sectionId, setSectionId] = useState(filters.section_id || '');

    const sort = filters.sort || 'id';
    const direction = filters.direction || 'desc';

    const listQuery = {
        q: filters.q || '',
        section_id: filters.section_id || '',
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
            route('admin.racks.index'),
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

    const openEdit = (rack) => {
        setEditing(rack);
        setShowForm(true);
    };

    const closeForm = () => {
        setShowForm(false);
        setEditing(null);
    };

    const destroyRack = async (rack) => {
        const ok = await confirmDelete(rack.name, 'rack');
        if (!ok) return;

        router.delete(route('admin.racks.destroy', rack.id), {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout
            title="Racks"
            description="Racks within sections for product locations."
            actions={
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="secondary" onClick={() => setShowImport(true)}>
                        <Upload className="h-4 w-4" strokeWidth={2.25} />
                        Import
                    </Button>
                    <RackExportMenu />
                    <Button onClick={openCreate}>
                        <Plus className="h-4 w-4" strokeWidth={2.25} />
                        Add Rack
                    </Button>
                </div>
            }
        >
            <Head title="Racks" />

            <div className="dp-card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-theme-border px-4 py-3">
                    <div className="flex flex-wrap items-center gap-2">
                        <PageLimitSelect
                            pageKey="racks"
                            routeName="admin.racks.index"
                            current={filters.per_page}
                            companyDefault={filters.company_page_limit}
                            extraQuery={{
                                q: filters.q || '',
                                section_id: filters.section_id || '',
                                sort,
                                direction,
                            }}
                        />
                        <select
                            value={sectionId}
                            onChange={(e) => {
                                const value = e.target.value;
                                setSectionId(value);
                                visitList({ section_id: value });
                            }}
                            className="h-9 rounded-lg border border-theme-border bg-theme-surface px-2.5 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                        >
                            <option value="">All sections</option>
                            {sections.map((section) => (
                                <option key={section.id} value={section.id}>
                                    {section.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            visitList({ q, section_id: sectionId });
                        }}
                        className="flex w-full items-center gap-2 sm:w-auto"
                    >
                        <div className="relative w-full sm:w-auto">
                            <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-theme-ink-muted" />
                            <input
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="Search racks"
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
                            <col className="w-24" />
                            <col />
                            <col className="w-40" />
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
                                <th className="px-3 py-3 font-semibold">Section</th>
                                <th className="px-3 py-3 font-semibold">In use</th>
                                <th className="px-3 py-3 font-semibold">Status</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {racks.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-3 py-10 text-center text-theme-ink-muted">
                                        No racks yet.
                                    </td>
                                </tr>
                            )}
                            {racks.data.map((rack, idx) => (
                                <tr key={rack.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(racks.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">{rack.code || '—'}</td>
                                    <td className="px-3 py-3 font-medium text-theme-ink">{rack.name}</td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {rack.section?.name || '—'}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {rack.locations_count ?? 0}
                                    </td>
                                    <td className="px-3 py-3">
                                        {rack.is_active ? (
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
                                                onClick={() => openEdit(rack)}
                                                className="inline-flex rounded-md p-1.5 text-theme-primary hover:bg-theme-bg"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                title="Delete"
                                                aria-label="Delete"
                                                onClick={() => destroyRack(rack)}
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

                <Pagination paginator={racks} />
            </div>

            <RackFormDrawer
                open={showForm}
                rack={editing}
                sections={sections}
                onClose={closeForm}
            />
            <RackImportDrawer
                open={showImport}
                onClose={() => setShowImport(false)}
                result={importResult}
            />
        </AdminLayout>
    );
}
