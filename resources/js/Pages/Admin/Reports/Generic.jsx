import ReportsShell from '@/Components/Reports/ReportsShell';

function cellValue(row, key) {
    const value = row?.[key];
    if (value == null) return '—';
    if (typeof value === 'number') {
        return Number.isInteger(value) ? value : Number(value).toFixed(2);
    }
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
}

export default function Generic({
    reportKey,
    title,
    filters = {},
    rows = [],
    columns = [],
    summary,
    categories = [],
    branch,
}) {
    const list = Array.isArray(rows) ? rows : rows?.data || [];

    return (
        <ReportsShell
            activeKey={reportKey}
            title={title}
            branch={branch}
            filters={filters}
            categories={categories}
            csvColumns={columns}
            csvRows={list}
        >
            {summary && typeof summary === 'object' && (
                <div className="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {Object.entries(summary).map(([key, value]) => {
                        const labels = {
                            orders: 'Orders with discount',
                            total_discount: 'Total discount',
                        };
                        const label =
                            labels[key] ||
                            key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
                        const isMoney =
                            typeof value === 'number' &&
                            (key.includes('discount') ||
                                key.includes('total') ||
                                key.includes('amount') ||
                                key.includes('value'));

                        return (
                            <div
                                key={key}
                                className="rounded-xl border border-theme-border bg-theme-bg px-4 py-3"
                            >
                                <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                                    {label}
                                </p>
                                <p
                                    className={`mt-1 text-xl font-semibold tabular-nums ${
                                        key === 'total_discount'
                                            ? 'text-theme-primary'
                                            : 'text-theme-ink'
                                    }`}
                                >
                                    {typeof value === 'number'
                                        ? isMoney && key !== 'orders'
                                            ? Number(value).toFixed(2)
                                            : value
                                        : String(value)}
                                </p>
                            </div>
                        );
                    })}
                </div>
            )}

            <div className="overflow-hidden rounded-xl border border-theme-border">
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-theme-bg text-theme-ink-muted">
                        <tr>
                            {columns.map((col) => (
                                <th key={col.key} className="px-4 py-3 text-xs font-semibold uppercase tracking-wide">
                                    {col.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {list.map((row, idx) => (
                            <tr key={row.id ?? idx} className="border-t border-theme-border/80">
                                {columns.map((col) => (
                                    <td key={col.key} className="px-4 py-2.5 text-theme-ink">
                                        {cellValue(row, col.key)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                        {list.length === 0 && (
                            <tr>
                                <td
                                    colSpan={Math.max(columns.length, 1)}
                                    className="px-4 py-10 text-center text-theme-ink-muted"
                                >
                                    No rows for this period
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </ReportsShell>
    );
}
