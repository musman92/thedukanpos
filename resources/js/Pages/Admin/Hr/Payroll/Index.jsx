import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import Pagination from '@/Components/Ui/Pagination';
import GeneratePayrollDrawer from '@/Pages/Admin/Hr/Payroll/GeneratePayrollDrawer';
import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

function money(value) {
    return Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function StatusBadge({ status }) {
    if (status === 'draft') {
        return (
            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                Draft
            </span>
        );
    }
    if (status === 'finalized') {
        return (
            <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                Finalized
            </span>
        );
    }
    return (
        <span className="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-semibold capitalize text-stone-600">
            {status}
        </span>
    );
}

export default function Index({ runs, branch }) {
    const [showGenerate, setShowGenerate] = useState(false);
    const rows = runs?.data || [];

    return (
        <AdminLayout
            title="Payroll"
            description={
                branch?.name
                    ? `Payroll runs for ${branch.name}. Finalize, then pay from Employee payments.`
                    : 'Generate and finalize payroll runs.'
            }
            actions={
                <Button onClick={() => setShowGenerate(true)}>
                    <Plus className="h-4 w-4" strokeWidth={2.25} />
                    Generate
                </Button>
            }
        >
            <Head title="Payroll" />

            <div className="dp-card overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="px-3 py-3 font-semibold">Number</th>
                                <th className="px-3 py-3 font-semibold">Period</th>
                                <th className="px-3 py-3 font-semibold">Employees</th>
                                <th className="px-3 py-3 font-semibold">Net total</th>
                                <th className="px-3 py-3 font-semibold">Status</th>
                                <th className="px-3 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-3 py-10 text-center text-theme-ink-muted"
                                    >
                                        No payroll runs yet.
                                    </td>
                                </tr>
                            )}
                            {rows.map((r) => (
                                <tr key={r.id} className="border-t border-theme-border">
                                    <td className="px-3 py-3 font-mono text-xs text-theme-ink">
                                        {r.number}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {r.period_start} → {r.period_end}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                        {r.employee_count}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums font-medium text-theme-ink">
                                        {money(r.net_total)}
                                    </td>
                                    <td className="px-3 py-3">
                                        <StatusBadge status={r.status} />
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        <Link
                                            href={route('admin.hr.payroll.show', r.id)}
                                            className="text-sm font-medium text-theme-primary hover:underline"
                                        >
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={runs} />
            </div>

            <GeneratePayrollDrawer
                open={showGenerate}
                onClose={() => setShowGenerate(false)}
            />
        </AdminLayout>
    );
}
