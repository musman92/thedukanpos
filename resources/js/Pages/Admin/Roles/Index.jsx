import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import RoleFormDrawer from '@/Pages/Admin/Roles/RoleFormDrawer';
import { confirmDelete } from '@/lib/confirm';
import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';

export default function Index({
    roles,
    filters,
    permission_groups: permissionGroups = [],
}) {
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
            route('admin.roles.index'),
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

    const openEdit = (role) => {
        setEditing(role);
        setShowForm(true);
    };

    const closeForm = () => {
        setShowForm(false);
        setEditing(null);
    };

    const destroyRole = async (role) => {
        if (role.is_protected) return;

        const ok = await confirmDelete(role.name, 'role');
        if (!ok) return;

        router.delete(route('admin.roles.destroy', role.id), {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout
            title="Roles & permissions"
            description="Create roles and assign module permissions for user accounts."
            actions={
                <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" strokeWidth={2.25} />
                    Add Role
                </Button>
            }
        >
            <Head title="Roles & permissions" />

            <div className="dp-card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-theme-border px-4 py-3">
                    <PageLimitSelect
                        pageKey="roles"
                        routeName="admin.roles.index"
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
                                placeholder="Search roles"
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
                            <col className="w-28" />
                            <col className="w-28" />
                            <col className="w-28" />
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
                                <th className="px-3 py-3 font-semibold">Type</th>
                                <th className="px-3 py-3 font-semibold">Permissions</th>
                                <th className="px-3 py-3 font-semibold">Users</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {roles.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-3 py-10 text-center text-theme-ink-muted">
                                        No roles yet.
                                    </td>
                                </tr>
                            )}
                            {roles.data.map((role, idx) => (
                                <tr key={role.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(roles.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 font-medium text-theme-ink">{role.name}</td>
                                    <td className="px-3 py-3">
                                        {role.is_protected ? (
                                            <span className="rounded-full bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-800">
                                                System
                                            </span>
                                        ) : (
                                            <span className="rounded-full bg-theme-bg px-2 py-0.5 text-xs font-semibold text-theme-ink-soft">
                                                Custom
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {role.permissions_count}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {role.users_count}
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                title="Edit"
                                                aria-label="Edit"
                                                onClick={() => openEdit(role)}
                                                className="inline-flex rounded-md p-1.5 text-theme-primary hover:bg-theme-bg"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            {!role.is_protected && (
                                                <button
                                                    type="button"
                                                    title="Delete"
                                                    aria-label="Delete"
                                                    onClick={() => destroyRole(role)}
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

                <Pagination paginator={roles} />
            </div>

            <RoleFormDrawer
                open={showForm}
                role={editing}
                onClose={closeForm}
                permissionGroups={permissionGroups}
            />
        </AdminLayout>
    );
}
