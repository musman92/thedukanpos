import ReportsShell from '@/Components/Reports/ReportsShell';

export default function Sales({ sales, summary, filters, categories = [], branch }) {
    const rows = sales?.data || [];

    return (
        <ReportsShell
            activeKey="sales"
            title="Sales"
            branch={branch}
            filters={filters}
            categories={categories}
            exportHref={route('admin.reports.sales.export', {
                from: filters.from,
                to: filters.to,
            })}
            csvColumns={[
                { key: 'number', label: 'Number' },
                { key: 'created_at', label: 'Date' },
                { key: 'cashier', label: 'Cashier' },
                { key: 'total', label: 'Total' },
                { key: 'paid_total', label: 'Paid' },
            ]}
            csvRows={rows.map((s) => ({
                number: s.number,
                created_at: s.created_at,
                cashier: s.cashier?.name || '',
                total: s.total,
                paid_total: s.paid_total,
            }))}
        >
            <div className="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div className="rounded-xl border border-theme-border bg-theme-bg px-4 py-3">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                        Orders
                    </p>
                    <p className="mt-1 text-xl font-semibold tabular-nums text-theme-ink">
                        {summary.count}
                    </p>
                </div>
                <div className="rounded-xl border border-theme-border bg-theme-bg px-4 py-3">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                        Total sale
                    </p>
                    <p className="mt-1 text-xl font-semibold tabular-nums text-theme-primary">
                        {Number(summary.total).toFixed(2)}
                    </p>
                </div>
                <div className="rounded-xl border border-theme-border bg-theme-bg px-4 py-3">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                        Paid
                    </p>
                    <p className="mt-1 text-xl font-semibold tabular-nums text-theme-ink">
                        {Number(summary.paid ?? 0).toFixed(2)}
                    </p>
                </div>
                <div className="rounded-xl border border-theme-border bg-theme-bg px-4 py-3">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                        Discount
                    </p>
                    <p className="mt-1 text-xl font-semibold tabular-nums text-theme-ink">
                        {Number(summary.discount ?? 0).toFixed(2)}
                    </p>
                </div>
                <div className="rounded-xl border border-theme-border bg-theme-bg px-4 py-3">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                        Tax
                    </p>
                    <p className="mt-1 text-xl font-semibold tabular-nums text-theme-ink">
                        {Number(summary.tax).toFixed(2)}
                    </p>
                </div>
            </div>

            <div className="overflow-hidden rounded-xl border border-theme-border">
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-theme-bg text-theme-ink-muted">
                        <tr>
                            <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide">Number</th>
                            <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide">Date</th>
                            <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide">Cashier</th>
                            <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide">Total</th>
                            <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide">Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((s) => (
                            <tr key={s.id} className="border-t border-theme-border/80">
                                <td className="px-4 py-2.5 font-mono text-xs text-theme-ink">{s.number}</td>
                                <td className="px-4 py-2.5 text-theme-ink">{s.created_at}</td>
                                <td className="px-4 py-2.5 text-theme-ink">{s.cashier?.name || '—'}</td>
                                <td className="px-4 py-2.5 tabular-nums text-theme-ink">
                                    {Number(s.total).toFixed(2)}
                                </td>
                                <td className="px-4 py-2.5 tabular-nums text-theme-ink">
                                    {Number(s.paid_total).toFixed(2)}
                                </td>
                            </tr>
                        ))}
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={5} className="px-4 py-10 text-center text-theme-ink-muted">
                                    No sales for this period
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </ReportsShell>
    );
}
