import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import UnitExportMenu from '@/Pages/Admin/Units/UnitExportMenu';
import UnitFormDrawer from '@/Pages/Admin/Units/UnitFormDrawer';
import UnitImportDrawer from '@/Pages/Admin/Units/UnitImportDrawer';
import { confirmDelete } from '@/lib/confirm';
import { Head, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2, Upload } from 'lucide-react';
import { useEffect, useState } from 'react';

export default function Index({ units, filters }) {
    const { flash } = usePage().props;
    const importResult =
        flash?.import_result?.entity === 'units' ? flash.import_result : null;

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
            route('admin.units.index'),
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

    const openEdit = (unit) => {
        setEditing(unit);
        setShowForm(true);
    };

    const closeForm = () => {
        setShowForm(false);
        setEditing(null);
    };

    const destroyUnit = async (unit) => {
        const ok = await confirmDelete(unit.name, 'unit');
        if (!ok) return;

        router.delete(route('admin.units.destroy', unit.id), {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout
            title="Units"
            description="Units of measure for purchase and sale quantities."
            actions={
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="secondary" onClick={() => setShowImport(true)}>
                        <Upload className="h-4 w-4" strokeWidth={2.25} />
                        Import
                    </Button>
                    <UnitExportMenu />
                    <Button onClick={openCreate}>
                        <Plus className="h-4 w-4" strokeWidth={2.25} />
                        Add Unit
                    </Button>
                </div>
            }
        >
            <Head title="Units" />

            <div className="dp-card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-theme-border px-4 py-3">
                    <PageLimitSelect
                        pageKey="units"
                        routeName="admin.units.index"
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
                                placeholder="Search units"
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
                            <col className="w-28" />
                            <col />
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
                                <th className="px-3 py-3 font-semibold">In use</th>
                                <th className="px-3 py-3 font-semibold">Status</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {units.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-3 py-10 text-center text-theme-ink-muted">
                                        No units yet.
                                    </td>
                                </tr>
                            )}
                            {units.data.map((unit, idx) => (
                                <tr key={unit.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(units.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">{unit.code || '—'}</td>
                                    <td className="px-3 py-3 font-medium text-theme-ink">{unit.name}</td>
                                    <td className="px-3 py-3 text-theme-ink-soft">{unit.usage_count ?? 0}</td>
                                    <td className="px-3 py-3">
                                        {unit.is_active ? (
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
                                                onClick={() => openEdit(unit)}
                                                className="inline-flex rounded-md p-1.5 text-theme-primary hover:bg-theme-bg"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                title="Delete"
                                                aria-label="Delete"
                                                onClick={() => destroyUnit(unit)}
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

                <Pagination paginator={units} />
            </div>

            <UnitFormDrawer open={showForm} unit={editing} onClose={closeForm} />
            <UnitImportDrawer
                open={showImport}
                onClose={() => setShowImport(false)}
                result={importResult}
            />
        </AdminLayout>
    );
}
