import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import BranchFormDrawer from '@/Pages/Admin/Branches/BranchFormDrawer';
import { confirmDelete } from '@/lib/confirm';
import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';

export default function Index({ branches, filters }) {
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [q, setQ] = useState(filters.q || '');

    const sort = filters.sort || 'name';
    const direction = filters.direction || 'asc';

    const listQuery = {
        q: filters.q || '',
        per_page: filters.per_page,
        sort,
        direction,
    };

    const visitList = (overrides = {}, options = {}) => {
        router.get(
            route('admin.branches.index'),
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

    const openEdit = (branch) => {
        setEditing(branch);
        setShowForm(true);
    };

    const closeForm = () => {
        setShowForm(false);
        setEditing(null);
    };

    const destroyBranch = async (branch) => {
        const ok = await confirmDelete(branch.name, 'branch');
        if (!ok) return;

        router.delete(route('admin.branches.destroy', branch.id), {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout
            title="Branches"
            description="Locations for stock, sales, and staff. Use the header switcher to change the working branch."
            actions={
                <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" strokeWidth={2.25} />
                    Add branch
                </Button>
            }
        >
            <Head title="Branches" />

            <div className="dp-card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-theme-border px-4 py-3">
                    <PageLimitSelect
                        pageKey="branches"
                        routeName="admin.branches.index"
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
                                placeholder="Search branches"
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
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="w-14 px-3 py-3 font-semibold">SN</th>
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
                                <th className="px-3 py-3 font-semibold">Phone</th>
                                <th className="px-3 py-3 font-semibold">Address</th>
                                <th className="w-24 px-3 py-3 font-semibold">Users</th>
                                <th className="w-24 px-3 py-3 font-semibold">Status</th>
                                <th className="w-28 px-3 py-3 text-right font-semibold">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {branches.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-3 py-10 text-center text-theme-ink-muted"
                                    >
                                        No branches yet.
                                    </td>
                                </tr>
                            )}
                            {branches.data.map((branch, idx) => (
                                <tr key={branch.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(branches.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 font-mono text-xs text-theme-ink-soft">
                                        {branch.code || '—'}
                                    </td>
                                    <td className="px-3 py-3 font-medium text-theme-ink">
                                        {branch.name}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {branch.phone || '—'}
                                    </td>
                                    <td className="max-w-[14rem] truncate px-3 py-3 text-theme-ink-soft">
                                        {branch.address || '—'}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {branch.users_count ?? 0}
                                    </td>
                                    <td className="px-3 py-3">
                                        {branch.is_active ? (
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
                                                onClick={() => openEdit(branch)}
                                                className="inline-flex rounded-md p-1.5 text-theme-primary hover:bg-theme-bg"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                title="Delete"
                                                aria-label="Delete"
                                                onClick={() => destroyBranch(branch)}
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

                <Pagination paginator={branches} />
            </div>

            <BranchFormDrawer open={showForm} branch={editing} onClose={closeForm} />
        </AdminLayout>
    );
}
