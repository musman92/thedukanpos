import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import UserFormDrawer from '@/Pages/Admin/Users/UserFormDrawer';
import { confirmDelete } from '@/lib/confirm';
import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';

export default function Index({ users, filters, roles = [], branches = [] }) {
    const [showForm, setShowForm] = useState(false);
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

    const visitList = (overrides = {}, options = {}) => {
        router.get(
            route('admin.users.index'),
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

    const openEdit = (person) => {
        setEditing(person);
        setShowForm(true);
    };

    const closeForm = () => {
        setShowForm(false);
        setEditing(null);
    };

    const destroyUser = async (person) => {
        const ok = await confirmDelete(person.name, 'user');
        if (!ok) return;

        router.delete(route('admin.users.destroy', person.id), {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout
            title="Users"
            description="Users and employees in one place — login access and HR details together."
            actions={
                <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" strokeWidth={2.25} />
                    Add User
                </Button>
            }
        >
            <Head title="Users" />

            <div className="dp-card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-theme-border px-4 py-3">
                    <PageLimitSelect
                        pageKey="users"
                        routeName="admin.users.index"
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
                                placeholder="Search users"
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
                            <col />
                            <col className="w-28" />
                            <col className="w-32" />
                            <col className="w-28" />
                            <col className="w-28" />
                            <col className="w-24" />
                            <col className="w-28" />
                        </colgroup>
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="px-3 py-3 font-semibold">SN</th>
                                <SortableTh label="Name" column="name" sort={sort} direction={direction} onSort={toggleSort} />
                                <SortableTh label="Username" column="username" sort={sort} direction={direction} onSort={toggleSort} />
                                <th className="px-3 py-3 font-semibold">Role</th>
                                <th className="px-3 py-3 font-semibold">Employee</th>
                                <th className="px-3 py-3 font-semibold">Login</th>
                                <th className="px-3 py-3 font-semibold">Status</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="px-3 py-10 text-center text-theme-ink-muted">
                                        No users yet.
                                    </td>
                                </tr>
                            )}
                            {users.data.map((person, idx) => (
                                <tr key={person.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(users.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 font-medium text-theme-ink">
                                        <div>{person.name}</div>
                                        {person.phone && (
                                            <div className="text-xs font-normal text-theme-ink-muted">{person.phone}</div>
                                        )}
                                    </td>
                                    <td className="px-3 py-3 font-mono text-xs text-theme-ink-soft">
                                        {person.can_login ? person.username : '—'}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {(person.roles || []).join(', ') || '—'}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {person.is_employee
                                            ? (person.employee_profile?.employee_number || 'Yes')
                                            : '—'}
                                    </td>
                                    <td className="px-3 py-3">
                                        {person.can_login ? (
                                            <span className="rounded-full bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-800">
                                                Yes
                                            </span>
                                        ) : (
                                            <span className="rounded-full bg-theme-bg px-2 py-0.5 text-xs font-semibold text-theme-ink-soft">
                                                No
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-3">
                                        {person.is_active ? (
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
                                                onClick={() => openEdit(person)}
                                                className="inline-flex rounded-md p-1.5 text-theme-primary hover:bg-theme-bg"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                title="Delete"
                                                aria-label="Delete"
                                                onClick={() => destroyUser(person)}
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

                <Pagination paginator={users} />
            </div>

            <UserFormDrawer
                open={showForm}
                person={editing}
                onClose={closeForm}
                roles={roles}
                branches={branches}
            />
        </AdminLayout>
    );
}
