import AdminLayout from '@/Layouts/AdminLayout';
import { formatAmount } from '@/lib/money';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import { Head, Link } from '@inertiajs/react';
import { CircleX, Eye, FileText, Plus } from 'lucide-react';

function money(value) {
    if (value == null || value === '') return '—';
    return formatAmount(value);
}

function StatusBadge({ status }) {
    if (status === 'active') {
        return (
            <span className="rounded-full bg-emerald-600 px-2 py-0.5 text-xs font-semibold text-white dark:bg-emerald-500 dark:text-emerald-950">
                Active
            </span>
        );
    }

    return (
        <span className="rounded-full border border-theme-border bg-theme-bg px-2 py-0.5 text-xs font-semibold text-theme-ink-soft">
            Closed
        </span>
    );
}

function CashDiff({ value }) {
    return (
        <span
            className={`tabular-nums font-medium ${
                value == null
                    ? 'text-theme-ink-muted'
                    : value >= 0
                      ? 'text-theme-success'
                      : 'text-theme-danger'
            }`}
        >
            {value == null ? '—' : money(value)}
        </span>
    );
}

function ShiftActions({ shift }) {
    return (
        <div className="flex items-center justify-end gap-1.5">
            <Link
                href={route('admin.shifts.show', shift.id)}
                className="dp-icon-btn min-h-11 min-w-11 text-theme-primary md:min-h-0 md:min-w-0"
                title="View"
            >
                <Eye className="h-[18px] w-[18px]" strokeWidth={1.75} />
            </Link>
            <Link
                href={route('admin.shifts.show', shift.id)}
                className="dp-icon-btn min-h-11 min-w-11 text-theme-primary md:min-h-0 md:min-w-0"
                title="Z report"
            >
                <FileText className="h-[18px] w-[18px]" strokeWidth={1.75} />
            </Link>
            {shift.status === 'active' && (
                <Link
                    href={route('admin.shifts.show', shift.id)}
                    className="dp-icon-btn min-h-11 min-w-11 text-theme-danger md:min-h-0 md:min-w-0"
                    title="Close shift"
                >
                    <CircleX className="h-[18px] w-[18px]" strokeWidth={1.75} />
                </Link>
            )}
        </div>
    );
}

function Cell({ label, children }) {
    return (
        <div className="min-w-0">
            <p className="text-[10px] font-medium uppercase tracking-wide text-theme-ink-muted">
                {label}
            </p>
            <p className="truncate text-[13px] text-theme-ink">{children}</p>
        </div>
    );
}

export default function Index({ shifts, filters }) {
    return (
        <AdminLayout
            title="Shifts"
            description="Each cashier starts a shift at a branch. Open one shift per branch for POS."
            actions={
                <Link href={route('admin.shifts.create')}>
                    <Button>
                        <Plus className="h-4 w-4" strokeWidth={2.25} />
                        Start New Shift
                    </Button>
                </Link>
            }
            mobileFab={[
                {
                    key: 'start-shift',
                    label: 'Start New Shift',
                    icon: Plus,
                    href: route('admin.shifts.create'),
                },
            ]}
        >
            <Head title="Shifts" />

            <div className="dp-card overflow-hidden">
                {/* Mobile cards */}
                <div className="divide-y divide-theme-border md:hidden">
                    {shifts.data.length === 0 && (
                        <p className="px-4 py-10 text-center text-sm text-theme-ink-muted">
                            No shifts yet. Start a new shift to begin.
                        </p>
                    )}
                    {shifts.data.map((s, idx) => (
                        <article key={s.id} className="space-y-2 px-4 py-3">
                            <div className="flex items-center justify-between gap-2">
                                <h3 className="truncate text-sm font-semibold text-theme-ink">
                                    {s.branch}
                                </h3>
                                <StatusBadge status={s.status} />
                            </div>

                            <p className="text-xs text-theme-ink-muted">
                                #{(shifts.from || 1) + idx} · {s.shift_date} · {s.opened_at}
                            </p>

                            <div className="grid grid-cols-2 gap-x-3 gap-y-1.5">
                                <Cell label="Opened by">{s.opened_by}</Cell>
                                <Cell label="Closed by">{s.closed_by || '—'}</Cell>
                            </div>

                            <div className="flex items-center justify-between gap-2 pt-0.5">
                                <p className="text-[13px]">
                                    <span className="text-[10px] font-medium uppercase tracking-wide text-theme-ink-muted">
                                        Cash diff{' '}
                                    </span>
                                    <CashDiff value={s.cash_difference} />
                                </p>
                                <ShiftActions shift={s} />
                            </div>
                        </article>
                    ))}
                </div>

                {/* Desktop table */}
                <div className="hidden overflow-x-auto md:block">
                    <table data-mobile-table="manual" className="min-w-full text-left text-sm">
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="px-3 py-3 font-semibold">SN</th>
                                <th className="px-3 py-3 font-semibold">Branch</th>
                                <th className="px-3 py-3 font-semibold">Date</th>
                                <th className="px-3 py-3 font-semibold">Opened By</th>
                                <th className="px-3 py-3 font-semibold">Opened At</th>
                                <th className="px-3 py-3 font-semibold">Closed By</th>
                                <th className="px-3 py-3 font-semibold">Status</th>
                                <th className="px-3 py-3 text-right font-semibold">Cash Diff</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {shifts.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={9}
                                        className="px-3 py-10 text-center text-theme-ink-muted"
                                    >
                                        No shifts yet. Start a new shift to begin.
                                    </td>
                                </tr>
                            )}
                            {shifts.data.map((s, idx) => (
                                <tr key={s.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(shifts.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 font-medium text-theme-ink">
                                        {s.branch}
                                    </td>
                                    <td className="px-3 py-3 text-theme-ink-soft">{s.shift_date}</td>
                                    <td className="px-3 py-3 text-theme-ink">{s.opened_by}</td>
                                    <td className="px-3 py-3 text-theme-ink-soft">{s.opened_at}</td>
                                    <td className="px-3 py-3 text-theme-ink-soft">
                                        {s.closed_by || '—'}
                                    </td>
                                    <td className="px-3 py-3">
                                        <StatusBadge status={s.status} />
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        <CashDiff value={s.cash_difference} />
                                    </td>
                                    <td className="px-3 py-3">
                                        <ShiftActions shift={s} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination
                    paginator={shifts}
                    pageLimit={
                        <PageLimitSelect
                            pageKey="shifts"
                            routeName="admin.shifts.index"
                            current={filters.per_page}
                            companyDefault={filters.company_page_limit}
                        />
                    }
                />
            </div>
        </AdminLayout>
    );
}
