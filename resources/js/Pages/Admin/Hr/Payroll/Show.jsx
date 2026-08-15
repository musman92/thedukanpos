import AdminLayout from '@/Layouts/AdminLayout';
import { formatAmount as money, formatAmountInput } from '@/lib/money';
import Button from '@/Components/Ui/Button';
import { Head, Link, router } from '@inertiajs/react';

export default function Show({ run }) {
    const finalize = () => {
        router.post(route('admin.hr.payroll.finalize', run.id), {}, { preserveScroll: true });
    };

    const canPay = run.status === 'finalized';
    const periodStart = String(run.period_start || '').slice(0, 10);
    const periodEnd = String(run.period_end || '').slice(0, 10);

    return (
        <AdminLayout
            title={`Payroll ${run.number}`}
            description={`${periodStart} → ${periodEnd}${run.branch?.name ? ` · ${run.branch.name}` : ''}`}
            actions={
                <div className="flex items-center gap-2">
                    <Link
                        href={route('admin.hr.payroll.index')}
                        className="inline-flex h-9 items-center rounded-lg border border-theme-border bg-theme-surface px-3 text-sm font-medium text-theme-ink-soft hover:bg-theme-bg"
                    >
                        Back
                    </Link>
                    {run.status === 'draft' && (
                        <Button type="button" onClick={finalize}>
                            Finalize
                        </Button>
                    )}
                </div>
            }
        >
            <Head title={`Payroll ${run.number}`} />

            <div className="mb-4 flex flex-wrap gap-4 rounded-xl border border-theme-border bg-theme-surface px-4 py-3 text-sm">
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Status</p>
                    <p className="mt-0.5 capitalize font-medium text-theme-ink">{run.status}</p>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Employees</p>
                    <p className="mt-0.5 font-medium tabular-nums text-theme-ink">
                        {run.employee_count}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Gross</p>
                    <p className="mt-0.5 font-medium tabular-nums text-theme-ink">
                        {money(run.gross_total)}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Deductions</p>
                    <p className="mt-0.5 font-medium tabular-nums text-theme-ink">
                        {money(run.deduction_total)}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-theme-ink-muted">Net</p>
                    <p className="mt-0.5 font-medium tabular-nums text-theme-ink">
                        {money(run.net_total)}
                    </p>
                </div>
            </div>

            <div className="dp-card overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="px-3 py-3 font-semibold">Employee</th>
                                <th className="px-3 py-3 font-semibold">Rate</th>
                                <th className="px-3 py-3 font-semibold">Bonus</th>
                                <th className="px-3 py-3 font-semibold">Deduction</th>
                                <th className="px-3 py-3 font-semibold">Gross</th>
                                <th className="px-3 py-3 font-semibold">Net</th>
                                <th className="px-3 py-3 font-semibold">Paid</th>
                                <th className="px-3 py-3 font-semibold">Status</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(run.items || []).length === 0 && (
                                <tr>
                                    <td
                                        colSpan={9}
                                        className="px-3 py-10 text-center text-theme-ink-muted"
                                    >
                                        No employees in this run.
                                    </td>
                                </tr>
                            )}
                            {(run.items || []).map((item) => {
                                const remaining = Math.max(
                                    0,
                                    Number(item.net_pay || 0) - Number(item.paid_amount || 0),
                                );
                                const showPay =
                                    canPay &&
                                    ['finalized', 'partial'].includes(item.status) &&
                                    remaining > 0.0001;

                                return (
                                    <tr key={item.id} className="border-t border-theme-border">
                                        <td className="px-3 py-3 font-medium text-theme-ink">
                                            {item.user?.name || '—'}
                                        </td>
                                        <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                            {money(item.pay_rate)}
                                        </td>
                                        <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                            {money(item.bonus_amount)}
                                        </td>
                                        <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                            {money(item.deduction_amount)}
                                        </td>
                                        <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                            {money(item.gross_pay)}
                                        </td>
                                        <td className="px-3 py-3 tabular-nums font-medium text-theme-ink">
                                            {money(item.net_pay)}
                                        </td>
                                        <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                            {money(item.paid_amount || 0)}
                                        </td>
                                        <td className="px-3 py-3 capitalize text-theme-ink-soft">
                                            {item.status}
                                        </td>
                                        <td className="px-3 py-3 text-right">
                                            {showPay ? (
                                                <Link
                                                    href={route(
                                                        'admin.finance.employee-payments.index',
                                                        {
                                                            open: 1,
                                                            kind: 'payroll',
                                                            user_id: item.user_id,
                                                            payroll_item_id: item.id,
                                                            amount: formatAmountInput(remaining),
                                                        },
                                                    )}
                                                    className="text-sm font-medium text-theme-primary hover:underline"
                                                >
                                                    Pay
                                                </Link>
                                            ) : (
                                                <span className="text-theme-ink-muted">—</span>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminLayout>
    );
}
