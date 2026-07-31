import AdminLayout from '@/Layouts/AdminLayout';
import { coerceMoneyNumber, formatMoney } from '@/lib/money';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeftRight,
    Boxes,
    CalendarDays,
    ChartColumn,
    Clock3,
    HandCoins,
    Package,
    Receipt,
    ShoppingCart,
    Sun,
    TrendingUp,
    Truck,
    Users,
    Activity,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    Chart,
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    DoughnutController,
    Filler,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    DoughnutController,
    Filler,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
);

const OPERATIONAL_ROWS = {
    purchases: { icon: ShoppingCart, tone: 'sky' },
    sales: { icon: TrendingUp, tone: 'indigo' },
    expenses: { icon: Receipt, tone: 'amber' },
    supplier_payments: { icon: Truck, tone: 'rose' },
    customer_received: { icon: HandCoins, tone: 'emerald' },
};

const OPERATIONAL_TONES = {
    sky: 'text-sky-500 bg-sky-500/15',
    indigo: 'text-indigo-400 bg-indigo-500/15',
    amber: 'text-amber-500 bg-amber-500/15',
    rose: 'text-rose-400 bg-rose-500/15',
    emerald: 'text-emerald-500 bg-emerald-500/15',
    gray: 'text-theme-ink-soft bg-theme-bg',
};

const OPERATIONAL_PALETTE = [
    { start: 'rgba(14, 165, 233, 0.95)', end: 'rgba(56, 189, 248, 0.55)' },
    { start: 'rgba(129, 140, 248, 0.95)', end: 'rgba(165, 180, 252, 0.55)' },
    { start: 'rgba(245, 158, 11, 0.95)', end: 'rgba(251, 191, 36, 0.55)' },
    { start: 'rgba(244, 63, 94, 0.95)', end: 'rgba(251, 113, 133, 0.55)' },
    { start: 'rgba(16, 185, 129, 0.95)', end: 'rgba(52, 211, 153, 0.55)' },
];

function readThemeColors() {
    if (typeof document === 'undefined') {
        return {
            ink: '#1f2937',
            inkSoft: '#6b7280',
            inkMuted: '#9ca3af',
            border: '#e5e7eb',
            isDark: false,
        };
    }

    const styles = getComputedStyle(document.documentElement);

    return {
        ink: styles.getPropertyValue('--color-ink').trim() || '#1f2937',
        inkSoft: styles.getPropertyValue('--color-ink-soft').trim() || '#6b7280',
        inkMuted: styles.getPropertyValue('--color-ink-muted').trim() || '#9ca3af',
        border: styles.getPropertyValue('--color-border').trim() || '#e5e7eb',
        isDark: document.documentElement.getAttribute('data-theme') === 'dark',
    };
}

const TONE = {
    indigo: { border: 'border-indigo-200', icon: 'text-indigo-500', bg: 'bg-indigo-50' },
    orange: { border: 'border-orange-200', icon: 'text-orange-500', bg: 'bg-orange-50' },
    emerald: { border: 'border-emerald-200', icon: 'text-emerald-500', bg: 'bg-emerald-50' },
    sky: { border: 'border-sky-200', icon: 'text-sky-500', bg: 'bg-sky-50' },
    violet: { border: 'border-violet-200', icon: 'text-violet-500', bg: 'bg-violet-50' },
    amber: { border: 'border-amber-200', icon: 'text-amber-500', bg: 'bg-amber-50' },
};

const CATEGORY_COLORS = [
    '#0f766e',
    '#ea580c',
    '#2563eb',
    '#7c3aed',
    '#db2777',
    '#0891b2',
    '#65a30d',
    '#ca8a04',
];

function money(amount, company) {
    return formatMoney(amount, company);
}

function toChartNumber(value) {
    return coerceMoneyNumber(value);
}

function aggregateDailySeries(dailyData, valueKey, granularity) {
    const dates = dailyData?.dates || [];
    const values = dailyData?.[valueKey] || [];

    if (granularity === 'day') {
        return {
            labels: dailyData?.labels || [],
            values,
        };
    }

    const buckets = {};
    dates.forEach((dateStr, index) => {
        const d = new Date(`${dateStr}T12:00:00`);
        let key;
        let label;
        if (granularity === 'week') {
            const weekStart = new Date(d);
            const day = (weekStart.getDay() + 6) % 7;
            weekStart.setDate(weekStart.getDate() - day);
            key = weekStart.toISOString().slice(0, 10);
            label = `W/C ${weekStart.toLocaleDateString(undefined, { day: 'numeric', month: 'short' })}`;
        } else {
            key = dateStr.slice(0, 7);
            label = d.toLocaleDateString(undefined, { month: 'short', year: 'numeric' });
        }
        if (!buckets[key]) {
            buckets[key] = { label, total: 0 };
        }
        buckets[key].total += Number(values[index] || 0);
    });

    const keys = Object.keys(buckets).sort();
    return {
        labels: keys.map((k) => buckets[k].label),
        values: keys.map((k) => Math.round(buckets[k].total * 100) / 100),
    };
}

function StatCard({ label, value, icon: Icon, tone = 'indigo', highlight = false, onClick = null }) {
    const colors = TONE[tone] || TONE.indigo;
    const className = [
        'relative w-full rounded-xl border bg-theme-surface px-3 py-2.5 text-left shadow-sm',
        highlight ? 'border-theme-primary/40 ring-1 ring-theme-primary/20' : 'border-theme-border',
        onClick
            ? 'cursor-pointer transition hover:border-emerald-300 hover:ring-1 hover:ring-emerald-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500'
            : '',
    ].join(' ');

    const body = (
        <>
            <div
                className={`pointer-events-none absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-md ${colors.bg}`}
            >
                <Icon className={`h-3 w-3 ${colors.icon}`} strokeWidth={2} />
            </div>
            <p className="pr-7 text-[10px] font-semibold uppercase leading-none tracking-wide text-theme-ink-muted">
                {label}
                {onClick && (
                    <span className="ml-1 font-normal normal-case tracking-normal text-emerald-600">
                        Details
                    </span>
                )}
            </p>
            <p className="mt-1 text-base font-bold tabular-nums leading-tight text-theme-ink sm:text-lg" title={value}>
                {value}
            </p>
        </>
    );

    if (onClick) {
        return (
            <button type="button" className={className} onClick={onClick}>
                {body}
            </button>
        );
    }

    return <div className={className}>{body}</div>;
}

function GranularityToggle({ value, onChange, activeClass }) {
    return (
        <div className="inline-flex rounded-lg border border-theme-border bg-theme-bg p-1">
            {['day', 'week', 'month'].map((mode) => {
                const active = value === mode;
                return (
                    <button
                        key={mode}
                        type="button"
                        onClick={() => onChange(mode)}
                        className={`rounded-md px-3 py-1.5 text-sm font-medium capitalize ${
                            active
                                ? `${activeClass} text-white`
                                : 'text-theme-ink-muted hover:text-theme-ink'
                        }`}
                    >
                        {mode}
                    </button>
                );
            })}
        </div>
    );
}

function useLineChart(canvasRef, dailyData, valueKey, granularity, options) {
    const chartRef = useRef(null);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas || !dailyData) return undefined;

        const series = aggregateDailySeries(dailyData, valueKey, granularity);
        if (chartRef.current) {
            chartRef.current.destroy();
        }

        chartRef.current = new Chart(canvas, {
            type: 'line',
            data: {
                labels: series.labels,
                datasets: [
                    {
                        label: options.label,
                        data: series.values,
                        borderColor: options.borderColor,
                        backgroundColor: options.backgroundColor,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 2,
                        pointHoverRadius: 5,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => options.formatAmount(toChartNumber(ctx.parsed?.y ?? ctx.raw)),
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 0, autoSkipPadding: 12 },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (v) => options.formatAmount(toChartNumber(v)),
                        },
                    },
                },
            },
        });

        return () => {
            chartRef.current?.destroy();
            chartRef.current = null;
        };
    }, [canvasRef, dailyData, valueKey, granularity, options]);
}

function PeriodLineChart({ dailyData, valueKey, title, activeClass, borderColor, backgroundColor, formatAmount }) {
    const [granularity, setGranularity] = useState('day');
    const canvasRef = useRef(null);
    const options = useMemo(
        () => ({ label: title, borderColor, backgroundColor, formatAmount }),
        [title, borderColor, backgroundColor, formatAmount],
    );

    useLineChart(canvasRef, dailyData, valueKey, granularity, options);

    return (
        <div className="dp-card p-5">
            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 className="text-lg font-semibold text-theme-ink">{title}</h2>
                <GranularityToggle
                    value={granularity}
                    onChange={setGranularity}
                    activeClass={activeClass}
                />
            </div>
            <div className="h-72">
                <canvas ref={canvasRef} />
            </div>
        </div>
    );
}

function CategoryChart({ breakdown, formatAmount }) {
    const canvasRef = useRef(null);
    const chartRef = useRef(null);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return undefined;

        if (chartRef.current) {
            chartRef.current.destroy();
        }

        if (!breakdown?.labels?.length) {
            return undefined;
        }

        chartRef.current = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: breakdown.labels,
                datasets: [
                    {
                        data: breakdown.amounts,
                        backgroundColor: breakdown.labels.map(
                            (_, i) => CATEGORY_COLORS[i % CATEGORY_COLORS.length],
                        ),
                        borderWidth: 0,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } },
                    tooltip: {
                        callbacks: {
                            label: (ctx) =>
                                `${ctx.label}: ${formatAmount(toChartNumber(ctx.raw ?? ctx.parsed))}`,
                        },
                    },
                },
            },
        });

        return () => {
            chartRef.current?.destroy();
            chartRef.current = null;
        };
    }, [breakdown, formatAmount]);

    return (
        <div className="h-56 sm:h-64">
            {(breakdown?.total ?? 0) > 0 ? (
                <canvas ref={canvasRef} />
            ) : (
                <p className="py-12 text-center text-sm text-theme-ink-muted">
                    No sales for this period.
                </p>
            )}
        </div>
    );
}

function OperationalChart({ comparison, periodLabel, formatAmount }) {
    const canvasRef = useRef(null);
    const chartRef = useRef(null);
    const [themeTick, setThemeTick] = useState(0);
    const inflow = Number(comparison?.cash_inflow || 0);
    const outflow = Number(comparison?.cash_outflow || 0);
    const netFlow = Number(comparison?.net_flow ?? inflow - outflow);
    const labels = comparison?.labels || [];
    const values = comparison?.values || [];
    const keys = comparison?.keys || [];

    useEffect(() => {
        const onTheme = () => setThemeTick((n) => n + 1);
        window.addEventListener('dukanpos:theme', onTheme);
        const observer = new MutationObserver(onTheme);
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-theme'],
        });
        return () => {
            window.removeEventListener('dukanpos:theme', onTheme);
            observer.disconnect();
        };
    }, []);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas || !comparison) return undefined;

        if (chartRef.current) {
            chartRef.current.destroy();
        }

        const theme = readThemeColors();

        const barGradient = (context) => {
            const { chart, dataIndex } = context;
            const palette = OPERATIONAL_PALETTE[dataIndex % OPERATIONAL_PALETTE.length];
            const area = chart.chartArea;
            if (!area) {
                return palette.start;
            }
            const gradient = chart.ctx.createLinearGradient(area.left, 0, area.right, 0);
            gradient.addColorStop(0, palette.end);
            gradient.addColorStop(1, palette.start);
            return gradient;
        };

        chartRef.current = new Chart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        data: values,
                        backgroundColor: barGradient,
                        borderRadius: 10,
                        borderSkipped: false,
                        barThickness: 22,
                        maxBarThickness: 26,
                    },
                ],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: { right: 48 },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: theme.isDark
                            ? 'rgba(15, 20, 25, 0.95)'
                            : 'rgba(17, 24, 39, 0.92)',
                        titleFont: { size: 13, weight: '600' },
                        bodyFont: { size: 12 },
                        padding: 12,
                        cornerRadius: 10,
                        displayColors: false,
                        callbacks: {
                            label: (ctx) => formatAmount(toChartNumber(ctx.raw)),
                        },
                    },
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: {
                            color: theme.isDark
                                ? 'rgba(36, 48, 65, 0.9)'
                                : 'rgba(229, 231, 235, 0.9)',
                            drawTicks: false,
                        },
                        ticks: {
                            color: theme.inkMuted,
                            font: { size: 11 },
                            maxTicksLimit: 5,
                            callback: (value) => formatAmount(toChartNumber(value)),
                        },
                    },
                    y: {
                        border: { display: false },
                        grid: { display: false },
                        ticks: {
                            color: theme.inkSoft,
                            font: { size: 12, weight: '500' },
                            padding: 8,
                        },
                    },
                },
            },
            plugins: [
                {
                    id: 'operationalValueLabels',
                    afterDatasetsDraw(chart) {
                        const ctx = chart.ctx;
                        const dataset = chart.data.datasets[0];
                        const meta = chart.getDatasetMeta(0);
                        meta.data.forEach((bar, index) => {
                            const value = toChartNumber(dataset.data[index]);
                            if (!value) {
                                return;
                            }
                            const props = bar.getProps(['x', 'y', 'base'], true);
                            const labelX = Math.max(props.x + 8, props.base + 8);
                            ctx.save();
                            ctx.fillStyle = theme.ink;
                            ctx.font = '600 11px ui-sans-serif, system-ui, sans-serif';
                            ctx.textAlign = 'left';
                            ctx.textBaseline = 'middle';
                            ctx.fillText(formatAmount(value), labelX, props.y);
                            ctx.restore();
                        });
                    },
                },
            ],
        });

        return () => {
            chartRef.current?.destroy();
            chartRef.current = null;
        };
    }, [comparison, labels, values, formatAmount, themeTick]);

    return (
        <div className="dp-card p-5 sm:p-6">
            <div className="mb-5 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <span className="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-violet-500/15 text-violet-400">
                            <ChartColumn className="h-4 w-4" strokeWidth={2} />
                        </span>
                        <h2 className="text-lg font-semibold text-theme-ink">Operational Comparison</h2>
                    </div>
                    <p className="ml-11 mt-2 text-sm text-theme-ink-muted">
                        Cash movement for {periodLabel}
                    </p>
                </div>

                <div className="grid grid-cols-3 gap-3 lg:min-w-[24rem]">
                    <div className="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-3 py-2.5">
                        <p className="text-[11px] font-semibold uppercase tracking-wide text-emerald-500">
                            Inflow
                        </p>
                        <p className="mt-0.5 text-lg font-bold tabular-nums text-theme-ink">
                            {formatAmount(inflow)}
                        </p>
                    </div>
                    <div className="rounded-xl border border-rose-500/20 bg-rose-500/10 px-3 py-2.5">
                        <p className="text-[11px] font-semibold uppercase tracking-wide text-rose-400">
                            Outflow
                        </p>
                        <p className="mt-0.5 text-lg font-bold tabular-nums text-theme-ink">
                            {formatAmount(outflow)}
                        </p>
                    </div>
                    <div className="rounded-xl border border-theme-border bg-theme-bg px-3 py-2.5">
                        <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                            Net
                        </p>
                        <p
                            className={`mt-0.5 text-lg font-bold tabular-nums ${
                                netFlow >= 0 ? 'text-theme-ink' : 'text-rose-400'
                            }`}
                        >
                            {formatAmount(netFlow)}
                        </p>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-5 xl:grid-cols-12">
                <div className="relative min-h-[20rem] rounded-xl border border-theme-border bg-theme-bg p-3 sm:p-4 xl:col-span-8">
                    <canvas ref={canvasRef} />
                </div>

                <div className="flex flex-col gap-2 xl:col-span-4">
                    {labels.map((label, index) => {
                        const key = keys[index] || '';
                        const style = OPERATIONAL_ROWS[key] || { icon: Activity, tone: 'gray' };
                        const Icon = style.icon;
                        const tone = OPERATIONAL_TONES[style.tone] || OPERATIONAL_TONES.gray;
                        const value = Number(values[index] || 0);

                        return (
                            <div
                                key={`${key}-${label}`}
                                className="flex items-center justify-between gap-3 rounded-xl border border-theme-border bg-theme-surface px-3 py-3"
                            >
                                <div className="flex min-w-0 items-center gap-2">
                                    <span
                                        className={`inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${tone}`}
                                    >
                                        <Icon className="h-3.5 w-3.5" strokeWidth={2} />
                                    </span>
                                    <span className="truncate text-sm font-medium text-theme-ink-soft">
                                        {label}
                                    </span>
                                </div>
                                <span className="shrink-0 text-sm font-bold tabular-nums text-theme-ink">
                                    {formatAmount(value)}
                                </span>
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}

function OutstandingTable({ title, report, emptyLabel, href }) {
    const { company } = usePage().props;

    return (
        <div className="dp-card overflow-hidden">
            <div className="flex items-center justify-between gap-3 border-b border-theme-border px-5 py-4">
                <div>
                    <h2 className="text-lg font-semibold text-theme-ink">{title}</h2>
                    <p className="mt-0.5 text-sm text-theme-ink-muted">
                        {report?.party_count || 0} parties · {money(report?.total, company)}
                    </p>
                </div>
                {href && (
                    <Link href={href} className="text-sm font-semibold text-theme-primary hover:underline">
                        View all
                    </Link>
                )}
            </div>
            <div className="max-h-80 overflow-auto">
                {(report?.rows || []).length === 0 ? (
                    <p className="px-5 py-10 text-center text-sm text-theme-ink-muted">{emptyLabel}</p>
                ) : (
                    <table className="min-w-full text-sm">
                        <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                            <tr>
                                <th className="px-4 py-2 text-left font-semibold">Name</th>
                                <th className="px-4 py-2 text-left font-semibold">Contact</th>
                                <th className="px-4 py-2 text-right font-semibold">Balance</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-theme-border">
                            {report.rows.map((row) => (
                                <tr key={row.id}>
                                    <td className="px-4 py-2.5 font-medium text-theme-ink">{row.name}</td>
                                    <td className="px-4 py-2.5 text-theme-ink-muted">
                                        {row.contact || '—'}
                                    </td>
                                    <td className="px-4 py-2.5 text-right tabular-nums text-theme-ink">
                                        {money(row.balance, company)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
}

function NetProfitModal({ open, onClose, mode, todayStats, periodStats }) {
    const { company } = usePage().props;
    const breakdown =
        mode === 'today'
            ? todayStats?.net_profit_breakdown
            : periodStats?.net_profit_breakdown;
    const title = mode === 'today' ? 'Today net profit' : 'Period net profit';

    useEffect(() => {
        if (!open) return undefined;
        const onKey = (e) => {
            if (e.key === 'Escape') onClose();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [open, onClose]);

    if (!open || !breakdown) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 pt-16 sm:pt-24">
            <button type="button" className="absolute inset-0 cursor-default" aria-label="Close" onClick={onClose} />
            <div
                role="dialog"
                aria-modal="true"
                className="relative z-10 w-full max-w-2xl rounded-xl border border-theme-border bg-theme-surface shadow-xl"
            >
                <div className="flex items-start justify-between gap-3 border-b border-theme-border px-5 py-4">
                    <div>
                        <h2 className="text-lg font-semibold text-theme-ink">{title}</h2>
                        <p className="mt-1 text-sm text-theme-ink-muted">
                            Sales − COGS − expenses − other payouts
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg px-2 py-1 text-sm text-theme-ink-muted hover:bg-theme-bg hover:text-theme-ink"
                    >
                        Close
                    </button>
                </div>
                <div className="space-y-4 px-5 py-4">
                    <div className="grid gap-2 sm:grid-cols-2">
                        {[
                            ['Total sale', breakdown.total_sale],
                            ['Cost of goods', breakdown.cogs],
                            ['Expenses', breakdown.expenses_total],
                            ['Other payouts', breakdown.payouts_total],
                        ].map(([label, amount]) => (
                            <div
                                key={label}
                                className="flex items-center justify-between rounded-lg border border-theme-border bg-theme-bg px-3 py-2 text-sm"
                            >
                                <span className="text-theme-ink-muted">{label}</span>
                                <span className="font-semibold tabular-nums text-theme-ink">
                                    {money(amount, company)}
                                </span>
                            </div>
                        ))}
                    </div>
                    <div className="flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3">
                        <span className="font-semibold text-emerald-900">Net profit</span>
                        <span className="text-lg font-bold tabular-nums text-emerald-800">
                            {money(breakdown.net_profit, company)}
                        </span>
                    </div>

                    {(breakdown.expenses || []).length > 0 && (
                        <div>
                            <h3 className="mb-2 text-sm font-semibold text-theme-ink">Expenses</h3>
                            <ul className="max-h-40 space-y-1 overflow-auto text-sm">
                                {breakdown.expenses.map((row, i) => (
                                    <li
                                        key={`e-${i}`}
                                        className="flex items-start justify-between gap-3 border-b border-theme-border/60 py-1.5"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium text-theme-ink">{row.label}</p>
                                            <p className="truncate text-xs text-theme-ink-muted">
                                                {row.date}
                                                {row.detail ? ` · ${row.detail}` : ''}
                                            </p>
                                        </div>
                                        <span className="shrink-0 tabular-nums text-theme-ink">
                                            {money(row.amount, company)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {(breakdown.payout_groups || []).length > 0 && (
                        <div>
                            <h3 className="mb-2 text-sm font-semibold text-theme-ink">Other payouts</h3>
                            <div className="max-h-48 space-y-3 overflow-auto">
                                {breakdown.payout_groups.map((group) => (
                                    <div key={group.label}>
                                        <div className="mb-1 flex justify-between text-sm">
                                            <span className="font-medium text-theme-ink">{group.label}</span>
                                            <span className="tabular-nums text-theme-ink-muted">
                                                {money(group.total, company)}
                                            </span>
                                        </div>
                                        <ul className="space-y-1 pl-2 text-xs text-theme-ink-muted">
                                            {(group.rows || []).map((row, i) => (
                                                <li key={`${group.label}-${i}`} className="flex justify-between gap-2">
                                                    <span className="truncate">
                                                        {row.date} · {row.label}
                                                        {row.detail ? ` · ${row.detail}` : ''}
                                                    </span>
                                                    <span className="shrink-0 tabular-nums">
                                                        {money(row.amount, company)}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

function KpiGrid({ stats, company, highlight = false, onNetProfitClick }) {
    return (
        <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <StatCard
                label="Revenue"
                value={money(stats?.revenue, company)}
                icon={Sun}
                tone="indigo"
                highlight={highlight}
            />
            <StatCard
                label="Cost of Goods"
                value={money(stats?.cost_of_goods, company)}
                icon={Boxes}
                tone="orange"
                highlight={highlight}
            />
            <StatCard
                label="Net Profit"
                value={money(stats?.net_profit, company)}
                icon={TrendingUp}
                tone="emerald"
                highlight={highlight}
                onClick={stats?.net_profit_breakdown ? onNetProfitClick : null}
            />
            <StatCard
                label="Transactions"
                value={Number(stats?.transactions || 0).toLocaleString()}
                icon={Activity}
                tone="sky"
                highlight={highlight}
            />
            <StatCard
                label="Customers"
                value={Number(stats?.customers || 0).toLocaleString()}
                icon={Users}
                tone="violet"
                highlight={highlight}
            />
            <StatCard
                label="Average Receipt"
                value={money(stats?.average_receipt, company)}
                icon={ArrowLeftRight}
                tone="amber"
                highlight={highlight}
            />
        </div>
    );
}

export default function Dashboard({
    tenant,
    selected_branch: selectedBranch,
    start_date: startDate,
    end_date: endDate,
    today_label: todayLabel,
    show_shift_reminder: showShiftReminder,
    today_stats: todayStats,
    period_stats: periodStats,
    revenue_chart_daily: revenueChartDaily,
    expenses_chart_daily: expensesChartDaily,
    category_breakdown: categoryBreakdown,
    operational_comparison: operationalComparison,
    customer_receivables: customerReceivables,
    supplier_payables: supplierPayables,
    top_items: topItems,
    low_stock_items: lowStockItems,
    money_source_balances: moneySourceBalances = [],
}) {
    const { company } = usePage().props;
    const [from, setFrom] = useState(startDate);
    const [to, setTo] = useState(endDate);
    const [profitMode, setProfitMode] = useState(null);

    useEffect(() => {
        setFrom(startDate);
        setTo(endDate);
    }, [startDate, endDate]);

    const moneyConfig = useMemo(
        () => ({
            currency: company?.currency,
            currency_symbol: company?.currency_symbol,
            currency_position: company?.currency_position,
            decimal_points: company?.decimal_points,
        }),
        [
            company?.currency,
            company?.currency_symbol,
            company?.currency_position,
            company?.decimal_points,
        ],
    );

    const formatAmount = useMemo(
        () => (value) => formatMoney(coerceMoneyNumber(value), moneyConfig),
        [moneyConfig],
    );

    const applyFilters = (e) => {
        e.preventDefault();
        router.get(
            route('admin.dashboard'),
            { start_date: from, end_date: to },
            { preserveState: true, replace: true },
        );
    };

    const fundsTotal = (moneySourceBalances || []).reduce(
        (sum, row) => sum + Number(row.balance || 0),
        0,
    );

    return (
        <AdminLayout title={null}>
            <Head title="Dashboard" />

            <div className="space-y-6">
                {showShiftReminder && (
                    <div className="rounded-lg border-l-4 border-amber-400 bg-amber-50 p-4">
                        <div className="flex items-start gap-3">
                            <Clock3 className="mt-0.5 h-5 w-5 shrink-0 text-amber-500" />
                            <div className="flex-1">
                                <h3 className="text-sm font-medium text-amber-900">Start your shift</h3>
                                <p className="mt-1 text-sm text-amber-800">
                                    Open a cash drawer shift before selling on POS or taking payments.
                                </p>
                                <Link
                                    href={route('admin.shifts.index')}
                                    className="mt-3 inline-flex rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700"
                                >
                                    Open shifts
                                </Link>
                            </div>
                        </div>
                    </div>
                )}

                <div className="dp-card p-4">
                    <form
                        onSubmit={applyFilters}
                        className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div>
                            <h1 className="text-2xl font-bold text-theme-ink">Dashboard</h1>
                            <p className="mt-1 text-sm text-theme-ink-muted">
                                {selectedBranch?.name || tenant?.name}
                            </p>
                        </div>
                        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end lg:justify-end">
                            <div>
                                <label className="mb-1 block text-xs font-medium text-theme-ink-muted">
                                    Start date
                                </label>
                                <input
                                    type="date"
                                    value={from}
                                    onChange={(e) => setFrom(e.target.value)}
                                    className="h-11 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink sm:w-auto"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium text-theme-ink-muted">
                                    End date
                                </label>
                                <input
                                    type="date"
                                    value={to}
                                    onChange={(e) => setTo(e.target.value)}
                                    className="h-11 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink sm:w-auto"
                                />
                            </div>
                            <button
                                type="submit"
                                className="dp-btn-primary inline-flex h-11 shrink-0 items-center justify-center px-4 text-sm"
                            >
                                Apply
                            </button>
                        </div>
                    </form>
                </div>

                <div>
                    <div className="mb-3 flex items-center gap-2 text-sm font-medium text-theme-ink-muted">
                        <CalendarDays className="h-4 w-4 text-theme-primary" />
                        <span>Today, {todayLabel}</span>
                    </div>
                    <KpiGrid
                        stats={todayStats}
                        company={company}
                        onNetProfitClick={() => setProfitMode('today')}
                    />
                </div>

                <div>
                    <div className="mb-3 flex items-center gap-2 text-sm font-medium text-theme-ink-muted">
                        <CalendarDays className="h-4 w-4 text-theme-primary" />
                        <span>{periodStats?.label || `${startDate} – ${endDate}`}</span>
                    </div>
                    <KpiGrid
                        stats={periodStats}
                        company={company}
                        highlight
                        onNetProfitClick={() => setProfitMode('period')}
                    />
                </div>

                <PeriodLineChart
                    dailyData={revenueChartDaily}
                    valueKey="revenue"
                    title="Revenue"
                    activeClass="bg-indigo-600"
                    borderColor="#4f46e5"
                    backgroundColor="rgba(79, 70, 229, 0.12)"
                    formatAmount={formatAmount}
                />

                <PeriodLineChart
                    dailyData={expensesChartDaily}
                    valueKey="expenses"
                    title="Expenses"
                    activeClass="bg-orange-600"
                    borderColor="#ea580c"
                    backgroundColor="rgba(234, 88, 12, 0.12)"
                    formatAmount={formatAmount}
                />

                <OperationalChart
                    comparison={operationalComparison}
                    periodLabel={periodStats?.label || `${startDate} – ${endDate}`}
                    formatAmount={formatAmount}
                />

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="dp-card p-5 lg:col-span-1">
                        <div className="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <h2 className="text-lg font-semibold text-theme-ink">Sales by category</h2>
                                <p className="mt-1 text-sm text-theme-ink-muted">
                                    {periodStats?.label || `${startDate} – ${endDate}`}
                                </p>
                            </div>
                            <div className="shrink-0 rounded-lg border border-theme-primary/20 bg-theme-primary/10 px-3 py-2 text-right">
                                <p className="text-[10px] font-semibold uppercase tracking-wide text-theme-primary">
                                    Revenue
                                </p>
                                <p className="text-lg font-bold tabular-nums text-theme-ink">
                                    {money(categoryBreakdown?.total, company)}
                                </p>
                            </div>
                        </div>
                        <CategoryChart breakdown={categoryBreakdown} formatAmount={formatAmount} />
                    </div>

                    <div className="dp-card overflow-hidden lg:col-span-2">
                        <div className="flex items-center justify-between gap-3 border-b border-theme-border px-5 py-4">
                            <div>
                                <h2 className="text-lg font-semibold text-theme-ink">Funds overview</h2>
                                <p className="mt-0.5 text-sm text-theme-ink-muted">
                                    Money sources for {selectedBranch?.name}
                                </p>
                            </div>
                            <div className="text-right">
                                <p className="text-[10px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                                    Total
                                </p>
                                <p className="text-lg font-bold tabular-nums text-theme-ink">
                                    {money(fundsTotal, company)}
                                </p>
                            </div>
                        </div>
                        {(moneySourceBalances || []).length === 0 ? (
                            <p className="px-5 py-10 text-center text-sm text-theme-ink-muted">
                                No money sources assigned to this branch.
                            </p>
                        ) : (
                            <div className="grid gap-3 p-5 sm:grid-cols-2">
                                {moneySourceBalances.map((row) => (
                                    <div
                                        key={row.id}
                                        className="rounded-xl border border-theme-border bg-theme-bg px-4 py-3"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div>
                                                <p className="font-medium text-theme-ink">{row.name}</p>
                                                <p className="mt-0.5 text-xs uppercase tracking-wide text-theme-ink-muted">
                                                    {row.type}
                                                </p>
                                            </div>
                                            <p className="text-base font-semibold tabular-nums text-theme-ink">
                                                {money(row.balance, company)}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <OutstandingTable
                        title="Receivables"
                        report={customerReceivables}
                        emptyLabel="No customer balances due."
                        href={route('admin.reports.receivables')}
                    />
                    <OutstandingTable
                        title="Payables"
                        report={supplierPayables}
                        emptyLabel="No supplier balances due."
                        href={route('admin.reports.payables')}
                    />
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div className="dp-card overflow-hidden">
                        <div className="flex items-center justify-between gap-3 border-b border-theme-border px-5 py-4">
                            <div>
                                <h2 className="text-lg font-semibold text-theme-ink">Top selling items</h2>
                                <p className="mt-0.5 text-sm text-theme-ink-muted">
                                    {topItems?.label} · {money(topItems?.total_revenue, company)}
                                </p>
                            </div>
                            <Package className="h-4 w-4 text-theme-ink-muted" />
                        </div>
                        {(topItems?.items || []).length === 0 ? (
                            <p className="px-5 py-10 text-center text-sm text-theme-ink-muted">
                                No item sales in this period.
                            </p>
                        ) : (
                            <table className="min-w-full text-sm">
                                <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                                    <tr>
                                        <th className="px-4 py-2 text-left font-semibold">Item</th>
                                        <th className="px-4 py-2 text-right font-semibold">Qty</th>
                                        <th className="px-4 py-2 text-right font-semibold">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-theme-border">
                                    {topItems.items.map((item, i) => (
                                        <tr key={`${item.name}-${i}`}>
                                            <td className="px-4 py-2.5 font-medium text-theme-ink">
                                                {item.name}
                                            </td>
                                            <td className="px-4 py-2.5 text-right tabular-nums text-theme-ink-muted">
                                                {item.qty}
                                            </td>
                                            <td className="px-4 py-2.5 text-right tabular-nums text-theme-ink">
                                                {money(item.revenue, company)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>

                    <div className="dp-card overflow-hidden">
                        <div className="flex items-center justify-between gap-3 border-b border-theme-border px-5 py-4">
                            <div>
                                <h2 className="text-lg font-semibold text-theme-ink">Low stock</h2>
                                <p className="mt-0.5 text-sm text-theme-ink-muted">
                                    {lowStockItems?.total || 0} items at or below alert level
                                </p>
                            </div>
                            <AlertTriangle className="h-4 w-4 text-amber-500" />
                        </div>
                        {(lowStockItems?.rows || []).length === 0 ? (
                            <p className="px-5 py-10 text-center text-sm text-theme-ink-muted">
                                Stock levels look healthy.
                            </p>
                        ) : (
                            <table className="min-w-full text-sm">
                                <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                                    <tr>
                                        <th className="px-4 py-2 text-left font-semibold">Item</th>
                                        <th className="px-4 py-2 text-right font-semibold">On hand</th>
                                        <th className="px-4 py-2 text-right font-semibold">Min</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-theme-border">
                                    {lowStockItems.rows.map((row, i) => (
                                        <tr key={`${row.name}-${i}`}>
                                            <td className="px-4 py-2.5 font-medium text-theme-ink">
                                                {row.name}
                                            </td>
                                            <td className="px-4 py-2.5 text-right tabular-nums text-rose-700">
                                                {row.current} {row.unit}
                                            </td>
                                            <td className="px-4 py-2.5 text-right tabular-nums text-theme-ink-muted">
                                                {row.min_level}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </div>
            </div>

            <NetProfitModal
                open={!!profitMode}
                mode={profitMode}
                onClose={() => setProfitMode(null)}
                todayStats={todayStats}
                periodStats={periodStats}
            />
        </AdminLayout>
    );
}
