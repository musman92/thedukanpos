import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import LeaveFormDrawer from '@/Pages/Admin/Hr/Leaves/LeaveFormDrawer';
import LeaveReviewDrawer from '@/Pages/Admin/Hr/Leaves/LeaveReviewDrawer';
import { confirmAction } from '@/lib/confirm';
import { Head, router } from '@inertiajs/react';
import { Check, Plus, Search, X } from 'lucide-react';
import { useState } from 'react';

function StatusBadge({ status }) {
    if (status === 'pending') {
        return (
            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                Pending
            </span>
        );
    }
    if (status === 'approved') {
        return (
            <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                Approved
            </span>
        );
    }
    if (status === 'rejected') {
        return (
            <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">
                Rejected
            </span>
        );
    }
    return (
        <span className="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-semibold capitalize text-stone-600">
            {status}
        </span>
    );
}

export default function Index({
    leaves,
    filters,
    employees = [],
    leave_types: leaveTypes = [],
    branch,
}) {
    const [showForm, setShowForm] = useState(false);
    const [reviewing, setReviewing] = useState(null);
    const [q, setQ] = useState(filters.q || '');
    const [localFilters, setLocalFilters] = useState({
        status: filters.status || '',
        leave_type: filters.leave_type || '',
        user_id: filters.user_id || '',
        from: filters.from || '',
        to: filters.to || '',
    });

    const sort = filters.sort || 'id';
    const direction = filters.direction || 'desc';

    const listQuery = {
        q: filters.q || '',
        per_page: filters.per_page,
        sort,
        direction,
        status: filters.status || '',
        leave_type: filters.leave_type || '',
        user_id: filters.user_id || '',
        from: filters.from || '',
        to: filters.to || '',
    };

    const visitList = (overrides = {}, options = {}) => {
        router.get(
            route('admin.hr.leaves.index'),
            { ...listQuery, ...overrides },
            { preserveState: true, ...options },
        );
    };

    const toggleSort = (column) => {
        const nextDirection =
            sort === column ? (direction === 'asc' ? 'desc' : 'asc') : 'asc';
        visitList({ sort: column, direction: nextDirection });
    };

    const applyFilters = (e) => {
        e.preventDefault();
        visitList({ q, ...localFilters });
    };

    const cancelLeave = async (row) => {
        if (!row.can_cancel) return;
        const label = `${row.user?.name || 'Leave'} · ${row.start_date} → ${row.end_date}`;
        const ok = await confirmAction({
            title: `Cancel ${label}?`,
            text: 'This leave request will be marked cancelled.',
            confirmText: 'Yes, cancel it',
            icon: 'warning',
        });
        if (!ok) return;

        router.delete(route('admin.hr.leaves.destroy', row.id), {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout
            title="Leaves"
            description={
                branch?.name
                    ? `Leave requests for ${branch.name}.`
                    : 'Request, review, and cancel employee leave.'
            }
            actions={
                <Button onClick={() => setShowForm(true)}>
                    <Plus className="h-4 w-4" strokeWidth={2.25} />
                    Request leave
                </Button>
            }
        >
            <Head title="Leaves" />

            <div className="dp-card overflow-hidden">
                <div className="space-y-3 border-b border-theme-border px-4 py-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <PageLimitSelect
                            pageKey="leaves"
                            routeName="admin.hr.leaves.index"
                            current={filters.per_page}
                            companyDefault={filters.company_page_limit}
                            extraQuery={listQuery}
                        />
                        <form onSubmit={applyFilters} className="flex items-center gap-2">
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-theme-ink-muted" />
                                <input
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    placeholder="Search employee, reason…"
                                    className="h-9 w-52 rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-8 pr-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                />
                            </div>
                            <Button type="submit" variant="secondary" size="sm">
                                Search
                            </Button>
                        </form>
                    </div>

                    <form
                        onSubmit={applyFilters}
                        className="flex flex-wrap items-center gap-2"
                    >
                        <select
                            value={localFilters.status}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, status: e.target.value }))
                            }
                            className="h-9 w-32 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <select
                            value={localFilters.leave_type}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, leave_type: e.target.value }))
                            }
                            className="h-9 w-32 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All types</option>
                            {leaveTypes.map((t) => (
                                <option key={t} value={t}>
                                    {t.replace(/_/g, ' ')}
                                </option>
                            ))}
                        </select>
                        <select
                            value={localFilters.user_id}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, user_id: e.target.value }))
                            }
                            className="h-9 w-40 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All employees</option>
                            {employees.map((e) => (
                                <option key={e.id} value={e.id}>
                                    {e.name}
                                </option>
                            ))}
                        </select>
                        <input
                            type="date"
                            value={localFilters.from}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, from: e.target.value }))
                            }
                            className="h-9 w-36 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                            title="From"
                        />
                        <input
                            type="date"
                            value={localFilters.to}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, to: e.target.value }))
                            }
                            className="h-9 w-36 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                            title="To"
                        />
                        <Button type="submit" variant="secondary" size="sm" className="shrink-0">
                            Filter
                        </Button>
                    </form>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full table-fixed text-left text-sm">
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="px-3 py-3 font-semibold">SN</th>
                                <th className="px-3 py-3 font-semibold">Employee</th>
                                <SortableTh
                                    label="Type"
                                    column="leave_type"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <SortableTh
                                    label="From"
                                    column="start_date"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <SortableTh
                                    label="To"
                                    column="end_date"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <SortableTh
                                    label="Days"
                                    column="days"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <SortableTh
                                    label="Status"
                                    column="status"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Reason</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {leaves.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={9}
                                        className="px-3 py-10 text-center text-theme-ink-muted"
                                    >
                                        No leave requests yet.
                                    </td>
                                </tr>
                            )}
                            {leaves.data.map((row, idx) => (
                                <tr key={row.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(leaves.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 font-medium text-theme-ink">
                                        {row.user?.name || '—'}
                                    </td>
                                    <td className="px-3 py-3 capitalize text-theme-ink-soft">
                                        {String(row.leave_type || '').replace(/_/g, ' ')}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {row.start_date}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {row.end_date}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {row.days}
                                    </td>
                                    <td className="px-3 py-3">
                                        <StatusBadge status={row.status} />
                                    </td>
                                    <td className="max-w-[10rem] truncate px-3 py-3 text-theme-ink-muted">
                                        {row.reason || '—'}
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        {row.can_review ? (
                                            <div className="flex items-center justify-end gap-1">
                                                <button
                                                    type="button"
                                                    title="Review"
                                                    aria-label="Review"
                                                    onClick={() => setReviewing(row)}
                                                    className="inline-flex rounded-md p-1.5 text-theme-primary hover:bg-theme-bg"
                                                >
                                                    <Check className="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    title="Cancel request"
                                                    aria-label="Cancel request"
                                                    onClick={() => cancelLeave(row)}
                                                    className="inline-flex rounded-md p-1.5 text-theme-danger hover:bg-theme-danger/10"
                                                >
                                                    <X className="h-4 w-4" />
                                                </button>
                                            </div>
                                        ) : (
                                            <span className="text-theme-ink-muted">—</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={leaves} />
            </div>

            <LeaveFormDrawer
                open={showForm}
                employees={employees}
                leaveTypes={leaveTypes}
                onClose={() => setShowForm(false)}
            />

            <LeaveReviewDrawer
                open={!!reviewing}
                leave={reviewing}
                onClose={() => setReviewing(null)}
            />
        </AdminLayout>
    );
}
