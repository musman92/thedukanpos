import ReportsShell from '@/Components/Reports/ReportsShell';
import SearchableSelect from '@/Components/Ui/SearchableSelect';
import { Link, router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

function money(n) {
    return Number(n || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

const fieldClass =
    'mt-1 block h-9 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

const TYPES = [
    { value: 'customer', label: 'Customer' },
    { value: 'supplier', label: 'Supplier' },
    { value: 'employee', label: 'Employee' },
];

export default function AccountStatement({
    filters: initial = {},
    type = 'customer',
    type_label: typeLabel = 'Customer',
    party = null,
    party_balance: partyBalance = 0,
    party_balance_hint: partyBalanceHint = '',
    statement = null,
    parties = {},
    branches = [],
    branch = null,
}) {
    const [branchId, setBranchId] = useState(
        initial.branch_id != null ? String(initial.branch_id) : '',
    );
    const [partyType, setPartyType] = useState(initial.type || type || 'customer');
    const [from, setFrom] = useState(initial.from || '');
    const [to, setTo] = useState(initial.to || '');
    const [partyId, setPartyId] = useState(
        initial.party_id != null ? String(initial.party_id) : '',
    );

    useEffect(() => {
        setBranchId(initial.branch_id != null ? String(initial.branch_id) : '');
        setPartyType(initial.type || 'customer');
        setFrom(initial.from || '');
        setTo(initial.to || '');
        setPartyId(initial.party_id != null ? String(initial.party_id) : '');
    }, [initial.branch_id, initial.type, initial.from, initial.to, initial.party_id]);

    const partyOptions = useMemo(
        () => parties[partyType] || [],
        [parties, partyType],
    );

    const visit = (overrides = {}) => {
        router.get(
            route('admin.reports.account-statement'),
            {
                branch_id: branchId || undefined,
                type: partyType,
                from,
                to,
                party_id: partyId || undefined,
                ...overrides,
            },
            { preserveState: true },
        );
    };

    const apply = (e) => {
        e.preventDefault();
        visit();
    };

    const onTypeChange = (next) => {
        setPartyType(next);
        setPartyId('');
        router.get(
            route('admin.reports.account-statement'),
            {
                branch_id: branchId || undefined,
                type: next,
                from,
                to,
            },
            { preserveState: true },
        );
    };

    const lines = statement?.lines || [];
    const csvColumns = [
        { key: 'date_display', label: 'Date' },
        { key: 'label', label: 'Particulars' },
        { key: 'reference', label: 'Reference' },
        { key: 'money_source', label: 'Money source' },
        { key: 'debit', label: 'Debit' },
        { key: 'credit', label: 'Credit' },
        { key: 'balance', label: 'Balance' },
    ];

    return (
        <ReportsShell
            activeKey="account-statement"
            title="Account Statement"
            branch={branch}
            filters={initial}
            suppressFilters
            csvColumns={csvColumns}
            csvRows={lines}
            filterBar={
                <form
                    onSubmit={apply}
                    className="relative z-20 flex flex-wrap items-end gap-3"
                >
                    <div className="w-44">
                        <label className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                            Branch
                        </label>
                        <select
                            value={branchId}
                            onChange={(e) => setBranchId(e.target.value)}
                            className={fieldClass}
                        >
                            {branches.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.name}
                                </option>
                            ))}
                        </select>
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

                    <div className="w-40">
                        <label className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                            Type
                        </label>
                        <select
                            value={partyType}
                            onChange={(e) => onTypeChange(e.target.value)}
                            className={fieldClass}
                        >
                            {TYPES.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {t.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="min-w-[16rem] flex-1">
                        <label className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                            {TYPES.find((t) => t.value === partyType)?.label || 'Party'}
                        </label>
                        <div className="mt-1">
                            <SearchableSelect
                                options={partyOptions}
                                value={partyId || null}
                                onChange={(value) => {
                                    const next = value ? String(value) : '';
                                    setPartyId(next);
                                    router.get(
                                        route('admin.reports.account-statement'),
                                        {
                                            branch_id: branchId || undefined,
                                            type: partyType,
                                            from,
                                            to,
                                            party_id: next || undefined,
                                        },
                                        { preserveState: true },
                                    );
                                }}
                                placeholder={`Search ${partyType}…`}
                                size="sm"
                            />
                        </div>
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
        >
            {!party && (
                <div className="rounded-xl border border-dashed border-theme-border bg-theme-bg px-4 py-14 text-center">
                    <p className="text-sm font-medium text-theme-ink">
                        Select a {partyType}
                    </p>
                    <p className="mt-1 text-sm text-theme-ink-muted">
                        Choose type, dates, and search for a person to view their ledger.
                    </p>
                </div>
            )}

            {party && statement && (
                <>
                    <div className="mb-5 flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-lg font-semibold text-theme-ink">{party.name}</p>
                            {party.phone && (
                                <p className="text-sm text-theme-ink-muted">{party.phone}</p>
                            )}
                            <p className="mt-1 text-xs text-theme-ink-muted">
                                {typeLabel} ledger · {branch?.name || 'Branch'}
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                                Current balance
                            </p>
                            <p className="mt-0.5 text-xl font-semibold tabular-nums text-theme-ink">
                                {money(partyBalance)}
                            </p>
                            {partyBalanceHint && (
                                <p className="mt-0.5 text-xs text-theme-ink-muted">
                                    {partyBalanceHint}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="mb-5 grid gap-3 sm:grid-cols-2">
                        <div className="rounded-xl border border-theme-border bg-theme-bg px-4 py-3">
                            <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                                Opening
                            </p>
                            <p className="mt-1 text-xl font-semibold tabular-nums text-theme-ink">
                                {money(statement.opening_balance)}
                            </p>
                        </div>
                        <div className="rounded-xl border border-theme-border bg-theme-bg px-4 py-3">
                            <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                                Closing
                            </p>
                            <p className="mt-1 text-xl font-semibold tabular-nums text-theme-ink">
                                {money(statement.closing_balance)}
                            </p>
                        </div>
                    </div>

                    <div className="overflow-visible rounded-xl border border-theme-border">
                        <table className="min-w-full text-left text-sm">
                            <thead className="bg-theme-bg/70 text-theme-ink-muted">
                                <tr>
                                    <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                        Date
                                    </th>
                                    <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                        Particulars
                                    </th>
                                    <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                        Reference
                                    </th>
                                    <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide">
                                        Debit
                                    </th>
                                    <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide">
                                        Credit
                                    </th>
                                    <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide">
                                        Balance
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {lines.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-10 text-center text-sm text-theme-ink-muted"
                                        >
                                            No ledger entries in this date range.
                                        </td>
                                    </tr>
                                )}
                                {lines.map((row, index) => (
                                    <tr
                                        key={`${row.type}-${row.reference}-${index}`}
                                        className={`border-t border-theme-border/80 ${
                                            row.type === 'opening_balance' ? 'bg-theme-bg/50' : ''
                                        }`}
                                    >
                                        <td className="whitespace-nowrap px-4 py-2.5 text-theme-ink-soft">
                                            {row.date_display}
                                        </td>
                                        <td className="px-4 py-2.5 text-theme-ink">
                                            <span className="font-medium">{row.label}</span>
                                            {row.money_source && (
                                                <span className="mt-0.5 block text-xs text-theme-ink-muted">
                                                    {row.money_source}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-2.5 font-mono text-xs text-theme-ink-soft">
                                            {row.url ? (
                                                <Link
                                                    href={row.url}
                                                    className="text-theme-primary hover:underline"
                                                >
                                                    {row.reference}
                                                </Link>
                                            ) : (
                                                row.reference || '—'
                                            )}
                                        </td>
                                        <td className="px-4 py-2.5 text-right tabular-nums text-theme-ink">
                                            {row.debit > 0 ? money(row.debit) : '—'}
                                        </td>
                                        <td className="px-4 py-2.5 text-right tabular-nums text-theme-ink">
                                            {row.credit > 0 ? money(row.credit) : '—'}
                                        </td>
                                        <td className="px-4 py-2.5 text-right font-semibold tabular-nums text-theme-ink">
                                            {money(row.balance)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </ReportsShell>
    );
}
