import ReportsShell from '@/Components/Reports/ReportsShell';
import Pagination from '@/Components/Ui/Pagination';

function money(n) {
    return Number(n || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

export default function Foc({ sales, summary = {}, filters = {}, branch }) {
    const rows = sales?.data || [];

    return (
        <ReportsShell
            activeKey="foc"
            title="FOC"
            branch={branch}
            filters={filters}
            csvColumns={[
                { key: 'number', label: 'Sale' },
                { key: 'created_at', label: 'Date' },
                { key: 'customer', label: 'Customer' },
                { key: 'cashier', label: 'Cashier' },
                { key: 'item_count', label: 'Items' },
                { key: 'foc_value', label: 'FOC value' },
                { key: 'total', label: 'Total' },
                { key: 'paid', label: 'Paid' },
            ]}
            csvRows={rows}
        >
            <div className="mb-5 grid gap-3 sm:grid-cols-2">
                <div className="rounded-xl border border-theme-border bg-theme-bg px-4 py-3">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                        FOC orders
                    </p>
                    <p className="mt-1 text-xl font-semibold tabular-nums text-theme-ink">
                        {summary.orders ?? 0}
                    </p>
                </div>
                <div className="rounded-xl border border-theme-border bg-theme-bg px-4 py-3">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                        Total FOC value
                    </p>
                    <p className="mt-1 text-xl font-semibold tabular-nums text-theme-primary">
                        {money(summary.foc_value)}
                    </p>
                </div>
            </div>

            <div className="overflow-x-auto rounded-xl border border-theme-border">
                <div className="border-b border-theme-border bg-theme-bg px-4 py-2.5">
                    <h3 className="text-sm font-semibold text-theme-ink">FOC orders</h3>
                </div>
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-theme-bg/70 text-theme-ink-muted">
                        <tr>
                            <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                Sale
                            </th>
                            <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                Date
                            </th>
                            <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                Customer
                            </th>
                            <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                Cashier
                            </th>
                            <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide">
                                Items
                            </th>
                            <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide">
                                FOC value
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((s) => (
                            <tr key={s.id} className="border-t border-theme-border/80">
                                <td className="px-4 py-2.5 font-mono text-xs text-theme-ink">
                                    {s.number}
                                </td>
                                <td className="px-4 py-2.5 text-theme-ink-soft">{s.created_at}</td>
                                <td className="px-4 py-2.5 text-theme-ink">{s.customer}</td>
                                <td className="px-4 py-2.5 text-theme-ink">{s.cashier || '—'}</td>
                                <td className="px-4 py-2.5 text-right tabular-nums text-theme-ink">
                                    {s.item_count}
                                </td>
                                <td className="px-4 py-2.5 text-right font-semibold tabular-nums text-theme-primary">
                                    {money(s.foc_value)}
                                </td>
                            </tr>
                        ))}
                        {rows.length === 0 && (
                            <tr>
                                <td
                                    colSpan={6}
                                    className="px-4 py-10 text-center text-theme-ink-muted"
                                >
                                    No FOC orders for this period
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {sales && (
                <div className="mt-4 print:hidden">
                    <Pagination paginator={sales} />
                </div>
            )}
        </ReportsShell>
    );
}
