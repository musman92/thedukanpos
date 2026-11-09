import PlatformLayout from '@/Layouts/PlatformLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SortableTh from '@/Components/Ui/SortableTh';
import PlanFormDrawer from '@/Pages/Platform/Plans/PlanFormDrawer';
import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

function money(n) {
    return Number(n || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function limitText(value) {
    if (value === null || value === undefined || value === '') return 'No cap';
    return String(value);
}

export default function Index({ plans, filters, form_open: formOpen = false }) {
    const [showForm, setShowForm] = useState(!!formOpen);
    const [editing, setEditing] = useState(null);
    const [q, setQ] = useState(filters.q || '');

    const sort = filters.sort || 'sort_order';
    const direction = filters.direction || 'asc';
    const listQuery = {
        q: filters.q || '',
        per_page: filters.per_page,
        sort,
        direction,
    };

    useEffect(() => {
        setShowForm(!!formOpen);
    }, [formOpen]);

    const visitList = (overrides = {}, options = {}) => {
        router.get(route('platform.plans.index'), { ...listQuery, ...overrides }, {
            preserveState: true,
            ...options,
        });
    };

    const toggleSort = (column) => {
        const nextDirection = sort === column ? (direction === 'asc' ? 'desc' : 'asc') : 'asc';
        visitList({ sort: column, direction: nextDirection });
    };

    const openCreate = () => {
        setEditing(null);
        setShowForm(true);
    };

    const openEdit = (plan) => {
        setEditing(plan);
        setShowForm(true);
    };

    const remove = (plan) => {
        const ok = window.confirm(
            plan.tenants_count > 0
                ? `${plan.name} is assigned to ${plan.tenants_count} companies. Move them before deleting.`
                : `Delete the ${plan.name} plan?`,
        );
        if (!ok) return;
        router.delete(route('platform.plans.destroy', plan.id), { preserveScroll: true });
    };

    return (
        <PlatformLayout
            title="Plans"
            description="Product, user, and branch limits, with a price for monthly, quarterly, and yearly billing."
            actions={
                <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" strokeWidth={2.25} />
                    Add plan
                </Button>
            }
        >
            <Head title="Platform · Plans" />

            <div className="dp-card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-theme-border px-4 py-3">
                    <PageLimitSelect
                        pageKey="platform-plans"
                        routeName="platform.plans.index"
                        current={filters.per_page}
                        companyDefault={filters.company_page_limit}
                        extraQuery={{ q: filters.q || '', sort, direction }}
                    />
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            visitList({ q });
                        }}
                        className="flex flex-wrap items-center gap-2"
                    >
                        <div className="relative w-full sm:w-auto">
                            <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-theme-ink-muted" />
                            <input
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="Search plans"
                                className="h-9 w-full rounded-lg border border-theme-border bg-theme-surface py-1.5 pl-8 pr-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20 sm:w-56"
                            />
                        </div>
                        <Button type="submit" variant="secondary" size="sm">
                            Search
                        </Button>
                    </form>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <SortableTh
                                    label="Plan"
                                    column="name"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Limits</th>
                                <SortableTh
                                    label="Monthly"
                                    column="monthly_price"
                                    sort={sort}
                                    direction={direction}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-3 font-semibold">Quarterly</th>
                                <th className="px-3 py-3 font-semibold">Yearly</th>
                                <th className="px-3 py-3 font-semibold">Companies</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(plans?.data || []).length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-3 py-10 text-center text-theme-ink-muted">
                                        No plans yet.
                                    </td>
                                </tr>
                            )}
                            {(plans?.data || []).map((plan) => (
                                <tr key={plan.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3">
                                        <p className="font-medium text-theme-ink">{plan.name}</p>
                                        <p className="font-mono text-[11px] text-theme-ink-muted">{plan.code}</p>
                                        {!plan.is_active && (
                                            <p className="text-[11px] font-semibold text-theme-ink-muted">Inactive</p>
                                        )}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {limitText(plan.product_limit)} products
                                        <span className="text-theme-ink-muted"> · </span>
                                        {limitText(plan.user_limit)} users
                                        <span className="text-theme-ink-muted"> · </span>
                                        {limitText(plan.branch_limit)} branches
                                        {plan.ads_enabled ? ' · ads' : ''}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink">{money(plan.monthly_price)}</td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink">{money(plan.quarterly_price)}</td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink">{money(plan.yearly_price)}</td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink">{plan.tenants_count}</td>
                                    <td className="px-3 py-3 text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                title="Edit"
                                                aria-label={`Edit ${plan.name}`}
                                                onClick={() => openEdit(plan)}
                                                className="inline-flex rounded-md p-1.5 text-theme-primary hover:bg-theme-bg"
                                            >
                                                <Pencil className="h-4 w-4" strokeWidth={1.75} />
                                            </button>
                                            <button
                                                type="button"
                                                title="Delete"
                                                aria-label={`Delete ${plan.name}`}
                                                onClick={() => remove(plan)}
                                                className="inline-flex rounded-md p-1.5 text-rose-600 hover:bg-theme-bg"
                                            >
                                                <Trash2 className="h-4 w-4" strokeWidth={1.75} />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={plans} />
            </div>

            <PlanFormDrawer
                open={showForm}
                plan={editing}
                onClose={() => {
                    setShowForm(false);
                    setEditing(null);
                }}
            />
        </PlatformLayout>
    );
}
