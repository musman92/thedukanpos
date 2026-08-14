import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import Input, { Field } from '@/Components/Ui/Input';
import { Head, Link, useForm } from '@inertiajs/react';

function money(value) {
    if (value == null || value === '') return '—';
    return Number(value).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function Cell({ label, children }) {
    return (
        <div className="min-w-0">
            <p className="text-[10px] font-medium uppercase tracking-wide text-theme-ink-muted">
                {label}
            </p>
            <p className="truncate text-[13px] tabular-nums text-theme-ink">{children}</p>
        </div>
    );
}

export default function Show({ shift }) {
    const closeForm = useForm({ closing_cash: shift.opening_cash || 0, notes: '' });

    return (
        <AdminLayout
            title={`Shift #${shift.id}`}
            actions={
                <Link href={route('admin.shifts.index')}>
                    <Button variant="secondary">Back to list</Button>
                </Link>
            }
        >
            <Head title={`Shift #${shift.id}`} />

            <div className="mb-4 grid grid-cols-2 gap-2 sm:gap-4 lg:grid-cols-4">
                {[
                    ['Branch', shift.branch],
                    ['Date', shift.shift_date],
                    ['Status', shift.status === 'active' ? 'Active' : 'Closed'],
                    ['Opened by', shift.opened_by],
                ].map(([label, value]) => (
                    <div key={label} className="dp-card p-3 sm:p-4">
                        <p className="text-[10px] uppercase tracking-wide text-theme-ink-muted sm:text-xs">
                            {label}
                        </p>
                        <p className="mt-0.5 truncate text-sm font-semibold text-theme-ink sm:mt-1 sm:text-base">
                            {value || '—'}
                        </p>
                    </div>
                ))}
            </div>

            <div className="dp-card mb-5 overflow-hidden">
                <div className="border-b border-theme-border px-4 py-3">
                    <h2 className="font-semibold text-theme-ink">Money sources</h2>
                </div>

                {/* Mobile cards */}
                <div className="divide-y divide-theme-border md:hidden">
                    {shift.money_sources.length === 0 && (
                        <p className="px-4 py-6 text-center text-sm text-theme-ink-muted">
                            No money source rows (legacy shift).
                        </p>
                    )}
                    {shift.money_sources.map((row) => (
                        <article key={row.id} className="space-y-2 px-4 py-3">
                            <div className="flex items-center justify-between gap-2">
                                <h3 className="truncate text-sm font-semibold text-theme-ink">
                                    {row.name}
                                </h3>
                                <span className="shrink-0 text-[10px] font-medium uppercase tracking-wide text-theme-ink-muted">
                                    {row.type}
                                </span>
                            </div>
                            <div className="grid grid-cols-2 gap-x-3 gap-y-1.5 sm:grid-cols-4">
                                <Cell label="Opening">{money(row.opening_balance)}</Cell>
                                <Cell label="Expected">{money(row.expected_balance)}</Cell>
                                <Cell label="Closing">
                                    {row.closing_balance == null ? '—' : money(row.closing_balance)}
                                </Cell>
                                <Cell label="Diff">
                                    {row.closing_balance == null ? '—' : money(row.difference)}
                                </Cell>
                            </div>
                        </article>
                    ))}
                </div>

                {/* Desktop table */}
                <div className="hidden overflow-x-auto md:block">
                    <table data-mobile-table="manual" className="min-w-full text-sm">
                        <thead className="bg-theme-bg text-[11px] uppercase text-theme-ink-muted">
                            <tr>
                                <th className="px-4 py-2 text-left">Source</th>
                                <th className="px-4 py-2 text-left">Type</th>
                                <th className="px-4 py-2 text-right">Opening</th>
                                <th className="px-4 py-2 text-right">Expected</th>
                                <th className="px-4 py-2 text-right">Closing</th>
                                <th className="px-4 py-2 text-right">Diff</th>
                            </tr>
                        </thead>
                        <tbody>
                            {shift.money_sources.map((row) => (
                                <tr key={row.id} className="border-t border-theme-border">
                                    <td className="px-4 py-2.5 font-medium text-theme-ink">
                                        {row.name}
                                    </td>
                                    <td className="px-4 py-2.5 uppercase text-theme-ink-muted">
                                        {row.type}
                                    </td>
                                    <td className="px-4 py-2.5 text-right tabular-nums">
                                        {money(row.opening_balance)}
                                    </td>
                                    <td className="px-4 py-2.5 text-right tabular-nums">
                                        {money(row.expected_balance)}
                                    </td>
                                    <td className="px-4 py-2.5 text-right tabular-nums">
                                        {row.closing_balance == null
                                            ? '—'
                                            : money(row.closing_balance)}
                                    </td>
                                    <td className="px-4 py-2.5 text-right tabular-nums">
                                        {row.closing_balance == null ? '—' : money(row.difference)}
                                    </td>
                                </tr>
                            ))}
                            {shift.money_sources.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-6 text-center text-theme-ink-muted"
                                    >
                                        No money source rows (legacy shift).
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="dp-card mb-4 grid grid-cols-3 gap-2 p-3 sm:gap-3 sm:p-4">
                <div className="min-w-0">
                    <p className="text-[10px] text-theme-ink-muted sm:text-xs">Opening cash</p>
                    <p className="truncate text-sm font-semibold tabular-nums text-theme-ink sm:text-base">
                        {money(shift.opening_cash)}
                    </p>
                </div>
                <div className="min-w-0">
                    <p className="text-[10px] text-theme-ink-muted sm:text-xs">Expected cash</p>
                    <p className="truncate text-sm font-semibold tabular-nums text-theme-ink sm:text-base">
                        {money(shift.expected_cash)}
                    </p>
                </div>
                <div className="min-w-0">
                    <p className="text-[10px] text-theme-ink-muted sm:text-xs">Cash difference</p>
                    <p
                        className={`truncate text-sm font-semibold tabular-nums sm:text-base ${
                            shift.cash_difference == null
                                ? 'text-theme-ink-muted'
                                : shift.cash_difference >= 0
                                  ? 'text-theme-success'
                                  : 'text-theme-danger'
                        }`}
                    >
                        {shift.cash_difference == null ? '—' : money(shift.cash_difference)}
                    </p>
                </div>
            </div>

            {shift.notes && (
                <div className="dp-card mb-5 p-4">
                    <p className="text-xs uppercase text-theme-ink-muted">Notes</p>
                    <p className="mt-1 whitespace-pre-wrap text-sm text-theme-ink">{shift.notes}</p>
                </div>
            )}

            {shift.status === 'active' && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        closeForm.post(route('admin.shifts.close', shift.id));
                    }}
                    className="dp-card space-y-4 p-5"
                >
                    <h2 className="font-semibold text-theme-ink">Close shift (Z)</h2>
                    <Field label="Closing cash" required>
                        <Input
                            type="number"
                            min="0"
                            step="0.01"
                            value={closeForm.data.closing_cash}
                            onChange={(e) => closeForm.setData('closing_cash', e.target.value)}
                        />
                    </Field>
                    <Field label="Notes (optional)">
                        <Input
                            value={closeForm.data.notes}
                            onChange={(e) => closeForm.setData('notes', e.target.value)}
                        />
                    </Field>
                    <Button type="submit" variant="danger" disabled={closeForm.processing}>
                        End Shift
                    </Button>
                </form>
            )}
        </AdminLayout>
    );
}
