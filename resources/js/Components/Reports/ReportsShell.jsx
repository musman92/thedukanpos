import AdminLayout from '@/Layouts/AdminLayout';
import { REPORT_CATALOG, findReport } from '@/lib/reportCatalog';
import { Head, router } from '@inertiajs/react';
import {
    Download,
    FileSpreadsheet,
    Printer,
    RefreshCw,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

function downloadCsv(filename, columns, rows) {
    const escape = (v) => {
        const s = v == null ? '' : String(v);
        if (/[",\n]/.test(s)) return `"${s.replace(/"/g, '""')}"`;
        return s;
    };
    const header = columns.map((c) => escape(c.label)).join(',');
    const body = rows
        .map((row) =>
            columns
                .map((c) => {
                    const value = row?.[c.key];
                    if (value == null) return '';
                    if (typeof value === 'object') return escape(JSON.stringify(value));
                    return escape(value);
                })
                .join(','),
        )
        .join('\n');
    const blob = new Blob([`${header}\n${body}`], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

/**
 * FoodPOS-style operational reporting chrome:
 * report pills + shared filters + print/export actions.
 */
export default function ReportsShell({
    activeKey,
    title,
    branch,
    filters = {},
    categories = [],
    children,
    csvColumns = null,
    csvRows = null,
    exportHref = null,
    suppressFilters = false,
    filterBar = null,
}) {
    const report = findReport(activeKey) || REPORT_CATALOG[0];
    const needed = report?.filters || [];

    const [from, setFrom] = useState(filters.from || '');
    const [to, setTo] = useState(filters.to || '');
    const [q, setQ] = useState(filters.q || '');
    const [categoryId, setCategoryId] = useState(
        filters.category_id != null && filters.category_id !== ''
            ? String(filters.category_id)
            : '',
    );

    useEffect(() => {
        setFrom(filters.from || '');
        setTo(filters.to || '');
        setQ(filters.q || '');
        setCategoryId(
            filters.category_id != null && filters.category_id !== ''
                ? String(filters.category_id)
                : '',
        );
    }, [filters.from, filters.to, filters.q, filters.category_id]);

    const showDates = !suppressFilters && needed.includes('dates');
    const showSearch = !suppressFilters && needed.includes('search');
    const showCategory = !suppressFilters && needed.includes('category');
    const showFilters = Boolean(filterBar) || showDates || showSearch || showCategory;

    const subtitle = useMemo(() => report?.label || title || 'Reports', [report, title]);

    const goReport = (item) => {
        try {
            router.get(route(item.route), {}, { preserveState: false });
        } catch {
            // ignore missing route during rollout
        }
    };

    const apply = (e) => {
        e.preventDefault();
        const params = {};
        if (showDates) {
            params.from = from;
            params.to = to;
        }
        if (showSearch) params.q = q;
        if (showCategory) params.category_id = categoryId || undefined;
        try {
            router.get(route(report.route), params, { preserveState: true });
        } catch {
            router.get(window.location.pathname, params, { preserveState: true });
        }
    };

    const onPrint = () => window.print();

    const onExcel = () => {
        if (exportHref) {
            window.location.assign(exportHref);
            return;
        }
        if (report?.exportRoute) {
            const params = {};
            if (showDates) {
                params.from = from;
                params.to = to;
            }
            try {
                window.location.assign(route(report.exportRoute, params));
                return;
            } catch {
                // fall through to client CSV
            }
        }
        if (csvColumns?.length && Array.isArray(csvRows)) {
            downloadCsv(
                `${report.key || 'report'}-${from || 'all'}-${to || 'all'}.csv`,
                csvColumns,
                csvRows,
            );
        }
    };

    const onPdf = () => window.print();

    return (
        <AdminLayout title={branch?.name ? `Reports · ${branch.name}` : 'Reports'}>
            <Head title={`Reports · ${subtitle}`} />

            <div className="dp-card overflow-visible print:border-0 print:shadow-none">
                <div className="flex flex-col gap-3 border-b border-theme-border px-3 py-4 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between sm:px-5 print:border-0">
                    <div>
                        <h1 className="font-display text-2xl tracking-tight text-theme-ink">
                            Operational Reporting
                        </h1>
                        <p className="mt-0.5 text-sm text-theme-ink-muted">{subtitle}</p>
                        {branch?.name ? (
                            <p className="mt-1 text-xs text-theme-ink-muted print:block">
                                Branch: {branch.name}
                            </p>
                        ) : null}
                    </div>

                    <div className="grid grid-cols-3 gap-2 sm:flex sm:flex-wrap sm:items-center print:hidden">
                        <button
                            type="button"
                            onClick={onPrint}
                            className="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-lg border border-theme-border bg-theme-surface px-2 text-xs font-semibold text-theme-ink-soft transition hover:border-theme-primary/40 hover:text-theme-ink sm:min-h-0 sm:px-3 sm:py-2"
                        >
                            <Printer className="h-3.5 w-3.5" />
                            Print
                        </button>
                        <button
                            type="button"
                            onClick={onExcel}
                            className="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-lg border border-theme-border bg-theme-surface px-2 text-xs font-semibold text-theme-ink-soft transition hover:border-theme-primary/40 hover:text-theme-ink sm:min-h-0 sm:px-3 sm:py-2"
                        >
                            <FileSpreadsheet className="h-3.5 w-3.5 text-emerald-600" />
                            <span className="sm:hidden">Excel</span>
                            <span className="hidden sm:inline">Export Excel</span>
                        </button>
                        <button
                            type="button"
                            onClick={onPdf}
                            className="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-lg border border-theme-border bg-theme-surface px-2 text-xs font-semibold text-theme-ink-soft transition hover:border-theme-primary/40 hover:text-theme-ink sm:min-h-0 sm:px-3 sm:py-2"
                        >
                            <Download className="h-3.5 w-3.5 text-rose-600" />
                            <span className="sm:hidden">PDF</span>
                            <span className="hidden sm:inline">Download PDF</span>
                        </button>
                    </div>
                </div>

                <div className="flex snap-x snap-mandatory gap-2 overflow-x-auto border-b border-theme-border px-3 py-3 sm:flex-wrap sm:overflow-visible sm:px-5 sm:py-4 print:hidden">
                    {REPORT_CATALOG.map((item) => {
                        const Icon = item.icon;
                        const active = item.key === report.key;
                        return (
                            <button
                                key={item.key}
                                type="button"
                                onClick={() => goReport(item)}
                                className={`inline-flex min-h-10 shrink-0 snap-start items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition ${
                                    active
                                        ? 'border-transparent bg-[var(--color-primary)] text-[var(--color-on-primary)] shadow-sm'
                                        : 'border-theme-border bg-theme-surface text-theme-ink-soft hover:border-theme-primary/35 hover:text-theme-ink'
                                }`}
                            >
                                <Icon className="h-3.5 w-3.5" strokeWidth={2} />
                                {item.label}
                            </button>
                        );
                    })}
                </div>

                {showFilters && (
                    filterBar ? (
                        <div className="border-b border-theme-border bg-theme-bg/50 px-3 py-3.5 sm:px-5 print:hidden">
                            {filterBar}
                        </div>
                    ) : (
                    <form
                        onSubmit={apply}
                        className="grid grid-cols-1 gap-3 border-b border-theme-border bg-theme-bg/50 px-3 py-3.5 sm:flex sm:flex-wrap sm:items-end sm:px-5 print:hidden"
                    >
                        <div className="min-w-[10rem]">
                            <label className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                                Branch
                            </label>
                            <div className="mt-1 flex h-9 items-center rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink">
                                {branch?.name || 'Current'}
                            </div>
                        </div>

                        {showDates && (
                            <>
                                <div>
                                    <label className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                                        From
                                    </label>
                                    <input
                                        type="date"
                                        value={from}
                                        onChange={(e) => setFrom(e.target.value)}
                                        className="mt-1 block h-9 rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
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
                                        className="mt-1 block h-9 rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                    />
                                </div>
                            </>
                        )}

                        {showCategory && (
                            <div className="min-w-[12rem]">
                                <label className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                                    Category
                                </label>
                                <select
                                    value={categoryId}
                                    onChange={(e) => setCategoryId(e.target.value)}
                                    className="dp-select-reset mt-1 block h-9 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                >
                                    <option value="">All categories</option>
                                    {categories.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}

                        {showSearch && (
                            <div>
                                <label className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                                    Search
                                </label>
                                <input
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    placeholder="Product / code"
                                    className="mt-1 block h-9 w-48 rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                />
                            </div>
                        )}

                        <button
                            type="submit"
                        className="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-lg bg-[var(--color-primary)] px-4 text-sm font-semibold text-[var(--color-on-primary)] transition hover:bg-[var(--color-primary-hover)] sm:min-h-9"
                        >
                            <RefreshCw className="h-3.5 w-3.5" />
                            Apply
                        </button>
                    </form>
                    )
                )}

                <div className="p-3 sm:p-5">{children}</div>
            </div>
        </AdminLayout>
    );
}
