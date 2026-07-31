import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import PageLimitSelect from '@/Components/Ui/PageLimitSelect';
import Pagination from '@/Components/Ui/Pagination';
import SearchableSelect from '@/Components/Ui/SearchableSelect';
import { Head, router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';

function formatQty(value) {
    const n = Number(value || 0);
    if (!n) return '—';
    if (Math.abs(n - Math.round(n)) < 0.0001) {
        return n.toLocaleString(undefined, { maximumFractionDigits: 0 });
    }
    return n.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 4,
    });
}

const fieldClass =
    'mt-1 block h-9 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

export default function ProductLedger({
    filters: initial = {},
    summary = null,
    rows = null,
    product = null,
    products = [],
    branches = [],
    branch = null,
}) {
    const [branchId, setBranchId] = useState(
        initial.branch_id != null ? String(initial.branch_id) : '',
    );
    const [from, setFrom] = useState(initial.from || '');
    const [to, setTo] = useState(initial.to || '');
    const [productId, setProductId] = useState(
        initial.product_id != null ? String(initial.product_id) : '',
    );

    useEffect(() => {
        setBranchId(initial.branch_id != null ? String(initial.branch_id) : '');
        setFrom(initial.from || '');
        setTo(initial.to || '');
        setProductId(initial.product_id != null ? String(initial.product_id) : '');
    }, [initial.branch_id, initial.from, initial.to, initial.product_id]);

    const apply = (e) => {
        e?.preventDefault?.();
        router.get(
            route('admin.inventory.product-ledger'),
            {
                branch_id: branchId || undefined,
                from,
                to,
                product_id: productId || undefined,
                per_page: initial.per_page || undefined,
            },
            { preserveState: true },
        );
    };

    const list = rows?.data || [];
    const title = branch?.name ? `Product ledger · ${branch.name}` : 'Product ledger';

    return (
        <AdminLayout
            title={title}
            description="In/out stock history for a selected product. Choose a product to load its ledger."
        >
            <Head title="Product ledger" />

            <div className="dp-card overflow-visible">
                <form
                    onSubmit={apply}
                    className="relative z-20 flex flex-wrap items-end gap-3 border-b border-theme-border px-4 py-3"
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

                    <div className="w-40">
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

                    <div className="w-40">
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

                    <div className="min-w-[16rem] flex-1">
                        <label className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                            Product
                        </label>
                        <div className="mt-1">
                            <SearchableSelect
                                options={products}
                                value={productId || null}
                                onChange={(value) => {
                                    const next = value ? String(value) : '';
                                    setProductId(next);
                                    router.get(
                                        route('admin.inventory.product-ledger'),
                                        {
                                            branch_id: branchId || undefined,
                                            from,
                                            to,
                                            product_id: next || undefined,
                                            per_page: initial.per_page || undefined,
                                        },
                                        { preserveState: true },
                                    );
                                }}
                                placeholder="Select a product…"
                                size="sm"
                            />
                        </div>
                    </div>

                    <Button type="submit" variant="secondary" size="sm">
                        <RefreshCw className="h-3.5 w-3.5" />
                        Apply
                    </Button>
                </form>

                {!product && (
                    <div className="px-4 py-16 text-center">
                        <p className="text-sm font-medium text-theme-ink">Select a product</p>
                        <p className="mt-1 text-sm text-theme-ink-muted">
                            Choose branch, dates, and a product to see all stock in/out.
                        </p>
                    </div>
                )}

                {product && (
                    <>
                        {summary && (
                            <div className="grid gap-3 border-b border-theme-border p-4 sm:grid-cols-3">
                                <div className="rounded-xl border border-theme-border bg-theme-bg px-4 py-3">
                                    <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                                        Movements
                                    </p>
                                    <p className="mt-1 text-xl font-semibold tabular-nums text-theme-ink">
                                        {summary.movement_count}
                                    </p>
                                </div>
                                <div className="rounded-xl border border-theme-border bg-theme-bg px-4 py-3">
                                    <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                                        Total in
                                    </p>
                                    <p className="mt-1 text-xl font-semibold tabular-nums text-emerald-700">
                                        {formatQty(summary.total_in)}
                                    </p>
                                </div>
                                <div className="rounded-xl border border-theme-border bg-theme-bg px-4 py-3">
                                    <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                                        Total out
                                    </p>
                                    <p className="mt-1 text-xl font-semibold tabular-nums text-rose-700">
                                        {formatQty(summary.total_out)}
                                    </p>
                                </div>
                            </div>
                        )}

                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-theme-border px-4 py-3">
                            <div>
                                <p className="text-sm font-semibold text-theme-ink">{product.name}</p>
                                <p className="text-xs text-theme-ink-muted">
                                    Stock movements · oldest first
                                </p>
                            </div>
                            {rows && (
                                <PageLimitSelect
                                    pageKey="product-ledger"
                                    routeName="admin.inventory.product-ledger"
                                    current={initial.per_page}
                                    companyDefault={initial.company_page_limit}
                                    extraQuery={{
                                        branch_id: initial.branch_id,
                                        from: initial.from,
                                        to: initial.to,
                                        product_id: initial.product_id,
                                    }}
                                />
                            )}
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead className="bg-theme-bg/70 text-theme-ink-muted">
                                    <tr>
                                        <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                            Date
                                        </th>
                                        <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                            Variant
                                        </th>
                                        <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                            Type
                                        </th>
                                        <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide">
                                            In
                                        </th>
                                        <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide">
                                            Out
                                        </th>
                                        <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide">
                                            Balance
                                        </th>
                                        <th className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide">
                                            Notes
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {list.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="px-4 py-10 text-center text-sm text-theme-ink-muted"
                                            >
                                                No stock movements for this product in the selected
                                                range.
                                            </td>
                                        </tr>
                                    )}
                                    {list.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-t border-theme-border/80"
                                        >
                                            <td className="px-4 py-2.5 whitespace-nowrap text-theme-ink-soft">
                                                {row.created_at}
                                            </td>
                                            <td className="px-4 py-2.5 text-theme-ink">
                                                {row.variant}
                                            </td>
                                            <td className="px-4 py-2.5 text-theme-ink-soft">
                                                {row.type_label}
                                            </td>
                                            <td className="px-4 py-2.5 text-right tabular-nums text-emerald-700">
                                                {formatQty(row.qty_in)}
                                            </td>
                                            <td className="px-4 py-2.5 text-right tabular-nums text-rose-700">
                                                {formatQty(row.qty_out)}
                                            </td>
                                            <td className="px-4 py-2.5 text-right font-semibold tabular-nums text-theme-ink">
                                                {row.balance_after != null
                                                    ? formatQty(row.balance_after)
                                                    : '—'}
                                            </td>
                                            <td className="max-w-xs truncate px-4 py-2.5 text-theme-ink-muted">
                                                {row.notes || '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {rows && <Pagination paginator={rows} />}
                    </>
                )}
            </div>
        </AdminLayout>
    );
}
