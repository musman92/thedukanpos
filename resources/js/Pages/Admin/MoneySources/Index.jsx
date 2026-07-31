import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import MoneySourceFormDrawer from '@/Pages/Admin/MoneySources/MoneySourceFormDrawer';
import MoneySourcesShell, {
    money,
    typeBadgeClass,
    typeLabel,
} from '@/Pages/Admin/MoneySources/MoneySourcesShell';
import { confirmDelete } from '@/lib/confirm';
import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';

export default function Index({
    money_sources: moneySources,
    system_sources: systemSources = [],
    filters,
    branches = [],
    branch = null,
    active_nav: activeNav = 'sources',
}) {
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
            route('admin.finance.money-sources.index'),
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

    const openEdit = (source) => {
        setEditing(source);
        setShowForm(true);
    };

    const closeForm = () => {
        setShowForm(false);
        setEditing(null);
    };

    const destroySource = async (source) => {
        if (source.is_system) return;
        const ok = await confirmDelete(source.name, 'money source');
        if (!ok) return;
        router.delete(route('admin.finance.money-sources.destroy', source.id), {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout title="Money sources">
            <Head title="Money sources" />

            <MoneySourcesShell
                activeNav={activeNav}
                title="Money sources"
                description="Manage your payment sources (Cash, Bank, App)"
                actions={
                    <Button onClick={openCreate}>
                        <Plus className="h-4 w-4" strokeWidth={2.25} />
                        Add Money Source
                    </Button>
                }
            >
                <p className="mb-4 text-xs text-theme-ink-muted">
                    Operational sources are used for POS, shifts, and payments. Balances update from
                    transactions and fund movements.
                </p>

                <div className="overflow-hidden rounded-xl border border-theme-border">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-theme-border px-4 py-3">
                        <PageLimitSelect
                            pageKey="money-sources"
                            routeName="admin.finance.money-sources.index"
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
                                    placeholder="Search sources"
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
                                    <th className="px-3 py-3 font-semibold">SN</th>
                                    <SortableTh
                                        label="Name"
                                        column="name"
                                        sort={sort}
                                        direction={direction}
                                        onSort={toggleSort}
                                    />
                                    <SortableTh
                                        label="Type"
                                        column="type"
                                        sort={sort}
                                        direction={direction}
                                        onSort={toggleSort}
                                    />
                                    <SortableTh
                                        label="Opening"
                                        column="opening_balance"
                                        sort={sort}
                                        direction={direction}
                                        onSort={toggleSort}
                                    />
                                    <SortableTh
                                        label="Balance"
                                        column="balance"
                                        sort={sort}
                                        direction={direction}
                                        onSort={toggleSort}
                                    />
                                    <SortableTh
                                        label="Status"
                                        column="is_active"
                                        sort={sort}
                                        direction={direction}
                                        onSort={toggleSort}
                                    />
                                    <th className="px-3 py-3 font-semibold">Branches</th>
                                    <th className="px-3 py-3 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {moneySources.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="px-3 py-10 text-center text-theme-ink-muted"
                                        >
                                            No money sources found.
                                        </td>
                                    </tr>
                                )}
                                {moneySources.data.map((source, idx) => (
                                    <tr key={source.id} className="border-t border-theme-border">
                                        <td className="px-3 py-3 text-theme-ink-muted">
                                            {(moneySources.from || 1) + idx}
                                        </td>
                                        <td className="px-3 py-3 font-medium text-theme-ink">
                                            {source.name}
                                        </td>
                                        <td className="px-3 py-3">
                                            <span
                                                className={`rounded-full px-2 py-0.5 text-xs font-semibold ${typeBadgeClass(source.type)}`}
                                            >
                                                {typeLabel(source.type)}
                                            </span>
                                        </td>
                                        <td className="px-3 py-3 text-right tabular-nums text-theme-ink-soft">
                                            {money(source.opening_balance)}
                                        </td>
                                        <td
                                            className={`px-3 py-3 text-right tabular-nums font-medium ${
                                                Number(source.balance) >= 0
                                                    ? 'text-emerald-700'
                                                    : 'text-theme-danger'
                                            }`}
                                        >
                                            {money(source.balance)}
                                        </td>
                                        <td className="px-3 py-3">
                                            {source.is_active ? (
                                                <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                                                    Active
                                                </span>
                                            ) : (
                                                <span className="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-semibold text-stone-600">
                                                    Inactive
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 py-3 text-theme-ink-soft">
                                            {source.branches_count}{' '}
                                            {source.branches_count === 1 ? 'branch' : 'branches'}
                                        </td>
                                        <td className="px-3 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <button
                                                    type="button"
                                                    title="Edit"
                                                    aria-label="Edit"
                                                    onClick={() => openEdit(source)}
                                                    className="inline-flex rounded-md p-1.5 text-theme-primary hover:bg-theme-bg"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    title="Delete"
                                                    aria-label="Delete"
                                                    onClick={() => destroySource(source)}
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

                    <Pagination paginator={moneySources} />
                </div>

                {systemSources.length > 0 && (
                    <div className="mt-6 overflow-hidden rounded-xl border border-theme-border">
                        <div className="border-b border-theme-border px-4 py-3">
                            <h3 className="text-sm font-semibold text-theme-ink">System buckets</h3>
                            <p className="text-xs text-theme-ink-muted">
                                Not used for POS or payments — track cumulative owner withdrawals
                            </p>
                        </div>
                        <table className="min-w-full text-left text-sm">
                            <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                                <tr>
                                    <th className="px-3 py-3 font-semibold">Name</th>
                                    <th className="px-3 py-3 font-semibold">Type</th>
                                    <th className="px-3 py-3 text-right font-semibold">
                                        Total withdrawn
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {systemSources.map((source) => (
                                    <tr key={source.id} className="border-t border-theme-border">
                                        <td className="px-3 py-3 font-medium text-theme-ink">
                                            {source.name}
                                        </td>
                                        <td className="px-3 py-3">
                                            <span
                                                className={`rounded-full px-2 py-0.5 text-xs font-semibold ${typeBadgeClass(source.type)}`}
                                            >
                                                {typeLabel(source.type)}
                                            </span>
                                        </td>
                                        <td className="px-3 py-3 text-right tabular-nums text-theme-ink">
                                            {money(source.balance)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </MoneySourcesShell>

            <MoneySourceFormDrawer
                open={showForm}
                source={editing}
                branches={branches}
                activeBranchId={branch?.id}
                onClose={closeForm}
            />
        </AdminLayout>
    );
}
