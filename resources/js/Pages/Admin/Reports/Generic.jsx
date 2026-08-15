import ReportsShell from '@/Components/Reports/ReportsShell';
import { formatAmount, formatQuantity } from '@/lib/money';

const MONEY_KEY_PATTERN =
    /(^|_)(amount|balance|cash|cost|discount|margin|paid|profit|revenue|tax|taxable|total|value)($|_)/;

const NUMERIC_FORMATS = ['money', 'qty', 'int'];

function isMoneyKey(key) {
    return MONEY_KEY_PATTERN.test(String(key)) && !String(key).endsWith('_pct');
}

/**
 * Columns declare their own format server-side so the screen, the CSV export and
 * the PDF render every value identically. Columns without a declared format fall
 * back to guessing from the key.
 */
function resolveFormat(column) {
    if (column.format) return column.format;

    return isMoneyKey(column.key) ? 'money' : null;
}

function cellValue(row, column) {
    const value = row?.[column.key];
    if (value == null || value === '') return '—';

    switch (resolveFormat(column)) {
        case 'money':
            return formatAmount(value);
        case 'qty':
            return formatQuantity(value);
        case 'int':
            return Number(value).toLocaleString();
        default:
            if (typeof value === 'object') return JSON.stringify(value);
            if (typeof value === 'number') {
                return Number(value).toLocaleString(undefined, {
                    maximumFractionDigits: 4,
                });
            }
            return String(value);
    }
}

function alignClass(column) {
    const align =
        column.align ||
        (NUMERIC_FORMATS.includes(resolveFormat(column)) ? 'right' : 'left');

    return align === 'right' ? 'text-right tabular-nums' : 'text-left';
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
                            isMoneyKey(key) &&
                            !key.endsWith('_pct');

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
                                            ? formatAmount(value)
                                            : value
                                        : String(value)}
                                </p>
                            </div>
                        );
                    })}
                </div>
            )}

            <div className="overflow-x-auto rounded-xl border border-theme-border">
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-theme-bg text-theme-ink-muted">
                        <tr>
                            {columns.map((col) => (
                                <th
                                    key={col.key}
                                    className={`px-4 py-3 text-xs font-semibold uppercase tracking-wide ${alignClass(col)}`}
                                >
                                    {col.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {list.map((row, idx) => (
                            <tr key={row.id ?? idx} className="border-t border-theme-border/80">
                                {columns.map((col) => (
                                    <td
                                        key={col.key}
                                        className={`px-4 py-2.5 text-theme-ink ${alignClass(col)}`}
                                    >
                                        {cellValue(row, col)}
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
