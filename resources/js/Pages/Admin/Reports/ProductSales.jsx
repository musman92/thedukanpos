import ReportsShell from '@/Components/Reports/ReportsShell';

export default function ProductSales({ rows = [], filters = {}, categories = [], branch }) {
    const list = Array.isArray(rows) ? rows : [];
    const csvRows = list.map((row) => ({
        product: `${row.product?.name || ''}${row.variant?.name ? ` — ${row.variant.name}` : ''}`,
        qty: row.qty,
        amount: row.amount,
    }));

    return (
        <ReportsShell
            activeKey="products"
            title="Sales by Item"
            branch={branch}
            filters={filters}
            categories={categories}
            csvColumns={[
                { key: 'product', label: 'Product' },
                { key: 'qty', label: 'Qty' },
                { key: 'amount', label: 'Amount' },
            ]}
            csvRows={csvRows}
        >
            <div className="overflow-x-auto rounded-xl border border-theme-border">
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-theme-bg text-theme-ink-muted">
                        <tr>
                            <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide">Product</th>
                            <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide">Qty sold</th>
                            <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        {list.map((row) => (
                            <tr
                                key={`${row.variant_id}-${row.product_id}`}
                                className="border-t border-theme-border/80"
                            >
                                <td className="px-4 py-2.5 text-theme-ink">
                                    {row.product?.name}
                                    {row.variant?.name ? ` — ${row.variant.name}` : ''}
                                    {row.variant?.short_code ? (
                                        <span className="ml-2 font-mono text-xs text-theme-ink-muted">
                                            {row.variant.short_code}
                                        </span>
                                    ) : null}
                                </td>
                                <td className="px-4 py-2.5 tabular-nums text-theme-ink">
                                    {Number(row.qty).toFixed(2)}
                                </td>
                                <td className="px-4 py-2.5 tabular-nums text-theme-ink">
                                    {Number(row.amount).toFixed(2)}
                                </td>
                            </tr>
                        ))}
                        {list.length === 0 && (
                            <tr>
                                <td colSpan={3} className="px-4 py-10 text-center text-theme-ink-muted">
                                    No item sales for this period
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </ReportsShell>
    );
}
