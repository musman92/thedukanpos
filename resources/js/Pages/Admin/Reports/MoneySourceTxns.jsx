import ReportsShell from '@/Components/Reports/ReportsShell';
import Pagination from '@/Components/Ui/Pagination';
import { router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';

function money(n) {
    return Number(n || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

const fieldClass =
    'mt-1 block h-9 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

export default function MoneySourceTxns({
    filters: initial = {},
    summary = {},
    by_source: bySource = [],
    rows,
    money_sources: moneySources = [],
    branch,
}) {
    const [from, setFrom] = useState(initial.from || '');
    const [to, setTo] = useState(initial.to || '');
    const [moneySourceId, setMoneySourceId] = useState(
        initial.money_source_id != null ? String(initial.money_source_id) : '',
    );
    const [direction, setDirection] = useState(initial.direction || 'all');
    const [includeTransfers, setIncludeTransfers] = useState(
        initial.include_transfers || 'include',
    );

    useEffect(() => {
        setFrom(initial.from || '');
        setTo(initial.to || '');
        setMoneySourceId(
            initial.money_source_id != null ? String(initial.money_source_id) : '',
        );
        setDirection(initial.direction || 'all');
        setIncludeTransfers(initial.include_transfers || 'include');
    }, [
        initial.from,
        initial.to,
        initial.money_source_id,
        initial.direction,
        initial.include_transfers,
    ]);

    const apply = (e) => {
        e.preventDefault();
        router.get(
            route('admin.reports.money-source-txns'),
            {
                from,
                to,
                money_source_id: moneySourceId || undefined,
                direction,
                include_transfers: includeTransfers,
                per_page: initial.per_page || 25,
            },
            { preserveState: true },
        );
    };

    const list = rows?.data || [];
    const csvRows = [
        ...bySource.map((r) => ({
            section: 'Totals',
            date: '',
            money_source: r.money_source,
            direction: '',
            amount_in: r.in,
            amount_out: r.out,
            net: r.net,
            reference: '',
            type: '',
            branch: '',
        })),
        ...list.map((r) => ({
            section: 'Transactions',
            date: r.date,
            money_source: r.money_source,
            direction: r.direction,
            amount_in: r.direction === 'in' ? r.amount : '',
            amount_out: r.direction === 'out' ? r.amount : '',
            net: '',
            reference: r.reference,
            type: r.type,
            branch: r.branch || '',
        })),
    ];

    return (
        <ReportsShell
            activeKey="money-source-txns"
            title="Transactions by Money Source"
            branch={branch}
            filters={initial}
            suppressFilters
            filterBar={
                <form onSubmit={apply} className="flex flex-wrap items-end gap-3">
                    <div className="min-w-[10rem]">
                        <label className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                            Branch
                        </label>
                        <div className={`${fieldClass} flex items-center`}>
                            {branch?.name || 'Current'}
                        </div>
                    </div>
                    <div>
                        <label className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                            From
                        </label>
                        <input
                            type="date"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                            className={fieldClass}
                        />
                    </div>
                    <div>
                        <label className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                            To
                        </label>
                        <input
                            type="date"
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                            className={fieldClass}
                        />
                    </div>
                    <div className="min-w-[11rem]">
                        <label className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                            Money sources
                        </label>
                        <select
                            value={moneySourceId}
                            onChange={(e) => setMoneySourceId(e.target.value)}
                            className={`dp-select-reset ${fieldClass}`}
                        >
                            <option value="">All sources</option>
                            {moneySources.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="min-w-[8rem]">
                        <label className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                            In / Out
                        </label>
                        <select
                            value={direction}
                            onChange={(e) => setDirection(e.target.value)}
                            className={`dp-select-reset ${fieldClass}`}
                        >
                            <option value="all">All</option>
                            <option value="in">In</option>
                            <option value="out">Out</option>
                        </select>
                    </div>
                    <div className="min-w-[9rem]">
                        <label className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                            Internal transfers
                        </label>
                        <select
                            value={includeTransfers}
                            onChange={(e) => setIncludeTransfers(e.target.value)}
                            className={`dp-select-reset ${fieldClass}`}
                        >
                            <option value="include">Include</option>
                            <option value="exclude">Exclude</option>
                        </select>
                    </div>
                    <button
                        type="submit"
                        className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[var(--color-primary)] px-4 text-sm font-semibold text-[var(--color-on-primary)] transition hover:bg-[var(--color-primary-hover)]"
                    >
                        <RefreshCw className="h-3.5 w-3.5" />
                        Apply
                    </button>
                </form>
            }
            csvColumns={[
                { key: 'section', label: 'Section' },
                { key: 'date', label: 'Date' },
                { key: 'money_source', label: 'Money source' },
                { key: 'direction', label: 'Type' },
                { key: 'amount_in', label: 'In' },
                { key: 'amount_out', label: 'Out' },
                { key: 'net', label: 'Net' },
                { key: 'reference', label: 'Reference' },
                { key: 'type', label: 'Txn type' },
                { key: 'branch', label: 'Branch' },
            ]}
            csvRows={csvRows}
        >
            <div className="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-xl border border-theme-border bg-theme-bg px-4 py-3">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                        Transactions
                    </p>
                    <p className="mt-1 text-xl font-semibold tabular-nums text-theme-ink">
                        {summary.transactions ?? 0}
                    </p>
                </div>
                <div className="rounded-xl border border-theme-border bg-theme-bg px-4 py-3">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                        Total in
                    </p>
                    <p className="mt-1 text-xl font-semibold tabular-nums text-emerald-600">
                        {money(summary.total_in)}
                    </p>
                </div>
                <div className="rounded-xl border border-theme-border bg-theme-bg px-4 py-3">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                        Total out
                    </p>
                    <p className="mt-1 text-xl font-semibold tabular-nums text-rose-600">
                        {money(summary.total_out)}
                    </p>
                </div>
                <div className="rounded-xl border border-theme-border bg-theme-bg px-4 py-3">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                        Net
                    </p>
                    <p
                        className={`mt-1 text-xl font-semibold tabular-nums ${
                            Number(summary.net || 0) >= 0
                                ? 'text-theme-primary'
                                : 'text-rose-600'
                        }`}
                    >
                        {money(summary.net)}
                    </p>
                </div>
            </div>

            <div className="mb-6 overflow-x-auto rounded-xl border border-theme-border">
                <div className="border-b border-theme-border bg-theme-bg px-4 py-2.5">
                    <h3 className="text-sm font-semibold text-theme-ink">Totals by money source</h3>
                </div>
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-theme-bg/70 text-theme-ink-muted">
                        <tr>
                            <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                Money source
                            </th>
                            <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide">
                                In
                            </th>
                            <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide">
                                Out
                            </th>
                            <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide">
                                Net
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {bySource.map((row) => (
                            <tr key={row.money_source} className="border-t border-theme-border/80">
                                <td className="px-4 py-2.5 font-medium text-theme-ink">
                                    {row.money_source}
                                </td>
                                <td className="px-4 py-2.5 text-right tabular-nums text-emerald-600">
                                    {money(row.in)}
                                </td>
                                <td className="px-4 py-2.5 text-right tabular-nums text-rose-600">
                                    {money(row.out)}
                                </td>
                                <td className="px-4 py-2.5 text-right font-semibold tabular-nums text-theme-ink">
                                    {money(row.net)}
                                </td>
                            </tr>
                        ))}
                        {bySource.length === 0 && (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="px-4 py-8 text-center text-theme-ink-muted"
                                >
                                    No totals for this period
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <div className="overflow-x-auto rounded-xl border border-theme-border">
                <div className="border-b border-theme-border bg-theme-bg px-4 py-2.5">
                    <h3 className="text-sm font-semibold text-theme-ink">Transactions</h3>
                </div>
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-theme-bg/70 text-theme-ink-muted">
                        <tr>
                            <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                Date
                            </th>
                            <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                Money source
                            </th>
                            <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                Type
                            </th>
                            <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide">
                                Amount
                            </th>
                            <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                Reference
                            </th>
                            <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                Branch
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {list.map((row, idx) => (
                            <tr key={`${row.sort_key || idx}`} className="border-t border-theme-border/80">
                                <td className="px-4 py-2.5 text-theme-ink-soft">{row.date}</td>
                                <td className="px-4 py-2.5 font-medium text-theme-ink">
                                    {row.money_source}
                                </td>
                                <td className="px-4 py-2.5">
                                    <span
                                        className={`inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase ${
                                            row.direction === 'in'
                                                ? 'bg-emerald-500/15 text-emerald-700'
                                                : 'bg-rose-500/15 text-rose-700'
                                        }`}
                                    >
                                        {row.direction}
                                    </span>
                                </td>
                                <td className="px-4 py-2.5 text-right font-semibold tabular-nums text-theme-ink">
                                    {money(row.amount)}
                                </td>
                                <td className="px-4 py-2.5 text-theme-ink-soft">
                                    <span className="block">{row.reference}</span>
                                    <span className="text-[11px] text-theme-ink-muted">{row.type}</span>
                                </td>
                                <td className="px-4 py-2.5 text-theme-ink-muted">
                                    {row.branch || branch?.name || '—'}
                                </td>
                            </tr>
                        ))}
                        {list.length === 0 && (
                            <tr>
                                <td
                                    colSpan={6}
                                    className="px-4 py-10 text-center text-theme-ink-muted"
                                >
                                    No transactions for this period
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {rows && (
                <div className="mt-4 print:hidden">
                    <Pagination paginator={rows} />
                </div>
            )}
        </ReportsShell>
    );
}
