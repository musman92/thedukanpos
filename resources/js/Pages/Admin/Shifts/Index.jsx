import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import { Head, Link } from '@inertiajs/react';
import { Eye, FileText, Plus, XCircle } from 'lucide-react';

function money(value) {
    if (value == null || value === '') return '—';
    const n = Number(value);
    const prefix = n < 0 ? '-' : '';
    return `${prefix}${Math.abs(n).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
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
        >
            <Head title="Shifts" />

            <div className="dp-card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-theme-border px-4 py-3">
                    <PageLimitSelect
                        pageKey="shifts"
                        routeName="admin.shifts.index"
                        current={filters.per_page}
                        companyDefault={filters.company_page_limit}
                    />
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
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
                                    <td colSpan={9} className="px-3 py-10 text-center text-theme-ink-muted">
                                        No shifts yet. Start a new shift to begin.
                                    </td>
                                </tr>
                            )}
                            {shifts.data.map((s, idx) => (
                                <tr key={s.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 text-theme-ink-muted">
                                        {(shifts.from || 1) + idx}
                                    </td>
                                    <td className="px-3 py-3 font-medium text-theme-ink">{s.branch}</td>
                                    <td className="px-3 py-3 text-theme-ink-soft">{s.shift_date}</td>
                                    <td className="px-3 py-3 text-theme-ink">{s.opened_by}</td>
                                    <td className="px-3 py-3 text-theme-ink-soft">{s.opened_at}</td>
                                    <td className="px-3 py-3 text-theme-ink-soft">{s.closed_by || '—'}</td>
                                    <td className="px-3 py-3">
                                        {s.status === 'active' ? (
                                            <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">
                                                Active
                                            </span>
                                        ) : (
                                            <span className="rounded-full bg-theme-bg px-2 py-0.5 text-xs font-semibold text-theme-ink-soft">
                                                Closed
                                            </span>
                                        )}
                                    </td>
                                    <td
                                        className={`px-3 py-3 text-right tabular-nums font-medium ${
                                            s.cash_difference == null
                                                ? 'text-theme-ink-muted'
                                                : s.cash_difference >= 0
                                                  ? 'text-theme-success'
                                                  : 'text-theme-danger'
                                        }`}
                                    >
                                        {s.cash_difference == null ? '—' : money(s.cash_difference)}
                                    </td>
                                    <td className="px-3 py-3">
                                        <div className="flex items-center justify-end gap-1.5">
                                            <Link
                                                href={route('admin.shifts.show', s.id)}
                                                className="dp-icon-btn text-theme-primary"
                                                title="View"
                                            >
                                                <Eye className="h-4 w-4" />
                                            </Link>
                                            <Link
                                                href={route('admin.shifts.show', s.id)}
                                                className="dp-icon-btn text-theme-primary"
                                                title="Z report"
                                            >
                                                <FileText className="h-4 w-4" />
                                            </Link>
                                            {s.status === 'active' && (
                                                <Link
                                                    href={route('admin.shifts.show', s.id)}
                                                    className="dp-icon-btn text-theme-danger"
                                                    title="Close shift"
                                                >
                                                    <XCircle className="h-4 w-4" />
                                                </Link>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={shifts} />
            </div>
        </AdminLayout>
    );
}
