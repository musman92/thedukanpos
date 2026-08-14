import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';

export default function Index({
    logs,
    filters,
    actions = [],
    users = [],
    logging_enabled: loggingEnabled = true,
}) {
    const [q, setQ] = useState(filters.q || '');
    const [localFilters, setLocalFilters] = useState({
        from: filters.from || '',
        to: filters.to || '',
        action: filters.action || '',
        user_id: filters.user_id || '',
    });
    const [detail, setDetail] = useState(null);

    const sort = filters.sort || 'id';
    const direction = filters.direction || 'desc';

    const listQuery = {
        q: filters.q || '',
        per_page: filters.per_page,
        sort,
        direction,
        from: filters.from || '',
        to: filters.to || '',
        action: filters.action || '',
        user_id: filters.user_id || '',
    };

    const visitList = (overrides = {}, options = {}) => {
        router.get(route('admin.activity.index'), { ...listQuery, ...overrides }, {
            preserveState: true,
            ...options,
        });
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

    const toggleLogging = () => {
        router.post(
            route('admin.activity.toggle'),
            { enabled: loggingEnabled ? 0 : 1 },
            { preserveScroll: true },
        );
    };

    const detailJson = useMemo(() => {
        if (!detail?.properties) return null;
        try {
            return JSON.stringify(detail.properties, null, 2);
        } catch {
            return String(detail.properties);
        }
    }, [detail]);

    return (
        <AdminLayout
            title="Activity log"
            description="Trail of sales, purchases, returns, and imports for this company."
            actions={
                <div className="flex flex-wrap items-center gap-2">
                    {loggingEnabled ? (
                        <span className="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">
                            Logging is ON
                        </span>
                    ) : (
                        <span className="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800">
                            Logging is OFF — nothing is recorded
                        </span>
                    )}
                    <Button
                        variant={loggingEnabled ? 'secondary' : 'primary'}
                        size="sm"
                        onClick={toggleLogging}
                    >
                        {loggingEnabled ? 'Turn off' : 'Turn on now'}
                    </Button>
                </div>
            }
        >
            <Head title="Activity log" />

            {!loggingEnabled && (
                <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Activity logging is currently <strong>off</strong>, so sales, purchases, and
                    imports will not appear here. Click <strong>Turn on now</strong>, then perform
                    an action to verify.
                </div>
            )}

            <div className="dp-card overflow-hidden">
                <div className="space-y-3 border-b border-theme-border px-4 py-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <PageLimitSelect
                            pageKey="activity"
                            routeName="admin.activity.index"
                            current={filters.per_page}
                            companyDefault={filters.company_page_limit}
                            extraQuery={listQuery}
                        />
                        <form onSubmit={applyFilters} className="flex w-full items-center gap-2 sm:w-auto">
                            <div className="relative w-full sm:w-auto">
                                <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-theme-ink-muted" />
                                <input
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    placeholder="Description or action…"
                                    className="h-9 w-full sm:w-56 rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-8 pr-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
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
                        <select
                            value={localFilters.action}
                            onChange={(e) =>
                                setLocalFilters((f) => ({ ...f, action: e.target.value }))
                            }
                            className="h-9 w-44 shrink-0 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm"
                        >
                            <option value="">All actions</option>
                            {actions.map((action) => (
                                <option key={action} value={action}>
                                    {action}
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
                            <option value="">All users</option>
                            {users.map((user) => (
                                <option key={user.id} value={user.id}>
                                    {user.name}
                                </option>
                            ))}
                        </select>
                        <Button type="submit" variant="secondary" size="sm">
                            Apply
                        </Button>
                    </form>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full table-fixed text-left text-sm">
                        <thead className="border-b border-theme-border bg-theme-bg text-xs uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <SortableTh
                                    label="When"
                                    column="created_at"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                    className="w-[16%]"
                                />
                                <SortableTh
                                    label="Action"
                                    column="action"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                    className="w-[16%]"
                                />
                                <th className="px-4 py-3 font-medium">Description</th>
                                <th className="w-[14%] px-4 py-3 font-medium">User</th>
                                <th className="w-[10%] px-4 py-3 font-medium">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-theme-ink-muted"
                                    >
                                        No activity logs found.
                                    </td>
                                </tr>
                            )}
                            {logs.data.map((log) => (
                                <tr
                                    key={log.id}
                                    className="border-t border-theme-border align-top hover:bg-theme-bg/60"
                                >
                                    <td className="whitespace-nowrap px-4 py-3 text-theme-ink-muted">
                                        {log.created_at}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-theme-primary">
                                        {log.action}
                                    </td>
                                    <td className="px-4 py-3 text-theme-ink">
                                        {log.description || '—'}
                                    </td>
                                    <td className="px-4 py-3 text-theme-ink-soft">
                                        {log.user?.name || '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {log.properties &&
                                        Object.keys(log.properties).length > 0 ? (
                                            <button
                                                type="button"
                                                onClick={() => setDetail(log)}
                                                className="text-sm font-medium text-theme-primary hover:underline"
                                            >
                                                View
                                            </button>
                                        ) : (
                                            <span className="text-theme-ink-muted">—</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={logs} />
            </div>

            <Drawer
                open={!!detail}
                onClose={() => setDetail(null)}
                title={detail?.action || 'Details'}
                description={detail?.description || null}
                width="sm"
            >
                {detail && (
                    <div className="space-y-4 p-5">
                        <dl className="grid gap-3 text-sm">
                            <div>
                                <dt className="text-xs font-medium uppercase tracking-wide text-theme-ink-muted">
                                    When
                                </dt>
                                <dd className="mt-0.5 text-theme-ink">{detail.created_at}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium uppercase tracking-wide text-theme-ink-muted">
                                    User
                                </dt>
                                <dd className="mt-0.5 text-theme-ink">
                                    {detail.user?.name || '—'}
                                    {detail.user?.username
                                        ? ` (@${detail.user.username})`
                                        : ''}
                                </dd>
                            </div>
                            {detail.ip_address && (
                                <div>
                                    <dt className="text-xs font-medium uppercase tracking-wide text-theme-ink-muted">
                                        IP address
                                    </dt>
                                    <dd className="mt-0.5 font-mono text-theme-ink">
                                        {detail.ip_address}
                                    </dd>
                                </div>
                            )}
                        </dl>
                        {detailJson && (
                            <div>
                                <p className="mb-2 text-xs font-medium uppercase tracking-wide text-theme-ink-muted">
                                    Properties
                                </p>
                                <pre className="overflow-x-auto rounded-lg border border-theme-border bg-theme-bg p-3 text-xs leading-relaxed text-theme-ink">
                                    {detailJson}
                                </pre>
                            </div>
                        )}
                    </div>
                )}
            </Drawer>
        </AdminLayout>
    );
}
