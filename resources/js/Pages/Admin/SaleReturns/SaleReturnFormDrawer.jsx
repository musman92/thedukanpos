import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field, TextArea } from '@/Components/Ui/Input';
import { router, useForm } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';

const selectClass =
    'h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

function money(value) {
    return Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function localToday() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function buildItems(selectedSale) {
    return (selectedSale?.items || []).map((item) => ({
        sale_item_id: item.id,
        quantity: '',
        _include: false,
        _meta: item,
    }));
}

export default function SaleReturnFormDrawer({
    open,
    sales = [],
    selectedSale = null,
    listQuery = {},
    onClose,
}) {
    const form = useForm({
        sale_id: selectedSale?.id ? String(selectedSale.id) : '',
        return_date: localToday(),
        notes: '',
        items: buildItems(selectedSale),
    });

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();
        form.setData({
            sale_id: selectedSale?.id ? String(selectedSale.id) : '',
            return_date: localToday(),
            notes: '',
            items: buildItems(selectedSale),
        });

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, selectedSale?.id]);

    const reloadForm = (overrides = {}) => {
        router.get(
            route('admin.returns.sales.index'),
            {
                ...listQuery,
                open: 1,
                sale_id: overrides.sale_id,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const onSaleChange = (saleId) => {
        form.setData({
            ...form.data,
            sale_id: saleId,
            items: [],
        });
        if (!saleId) {
            reloadForm({ sale_id: undefined });
            return;
        }
        reloadForm({ sale_id: saleId });
    };

    const total = useMemo(
        () =>
            form.data.items.reduce((sum, row) => {
                if (!row._include) return sum;
                const qty = Number(row.quantity || 0);
                const price = Number(row._meta?.unit_price || 0);
                return sum + qty * price;
            }, 0),
        [form.data.items],
    );

    const setLine = (index, patch) => {
        form.setData(
            'items',
            form.data.items.map((item, i) => (i === index ? { ...item, ...patch } : item)),
        );
    };

    const submit = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            sale_id: data.sale_id,
            return_date: data.return_date,
            notes: data.notes,
            items: data.items
                .filter((row) => row._include && Number(row.quantity) > 0)
                .map((row) => ({
                    sale_item_id: row.sale_item_id,
                    quantity: row.quantity || 0,
                })),
        }));
        form.post(route('admin.returns.sales.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
            onFinish: () => form.transform((d) => d),
        });
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title="New refund"
            description="Choose a completed sale, then select lines and quantities to refund and restock."
            width="wide"
            bodyClassName="overflow-y-auto flex flex-col"
        >
            <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
                <div className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Field label="Sale" required error={form.errors.sale_id}>
                            <select
                                value={form.data.sale_id}
                                onChange={(e) => onSaleChange(e.target.value)}
                                className={selectClass}
                                autoFocus
                            >
                                <option value="">Select sale</option>
                                {sales.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.number}
                                        {s.sale_date ? ` · ${s.sale_date}` : ''}
                                        {s.customer_name ? ` · ${s.customer_name}` : ''}
                                        {` · ${money(s.total)}`}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Return date" required error={form.errors.return_date}>
                            <Input
                                type="date"
                                value={form.data.return_date}
                                onChange={(e) => form.setData('return_date', e.target.value)}
                            />
                        </Field>
                    </div>

                    {selectedSale && (
                        <div className="rounded-lg border border-theme-border bg-theme-bg px-3 py-2 text-sm">
                            <p className="text-xs uppercase tracking-wide text-theme-ink-muted">
                                Original sale total
                            </p>
                            <p className="mt-0.5 font-medium tabular-nums text-theme-ink">
                                {money(selectedSale.total)}
                                {selectedSale.customer_name
                                    ? ` · ${selectedSale.customer_name}`
                                    : ''}
                            </p>
                        </div>
                    )}

                    <Field label="Notes" error={form.errors.notes}>
                        <TextArea
                            rows={2}
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            placeholder="Optional"
                        />
                    </Field>

                    <div className="overflow-hidden rounded-lg border border-theme-border">
                        <div className="border-b border-theme-border px-4 py-3 text-sm font-semibold text-theme-ink">
                            Returnable lines
                        </div>
                        {!selectedSale && (
                            <p className="px-4 py-10 text-center text-sm text-theme-ink-muted">
                                {sales.length === 0
                                    ? 'No returnable sales found.'
                                    : 'Select a sale to load returnable lines.'}
                            </p>
                        )}
                        {selectedSale && form.data.items.length === 0 && (
                            <p className="px-4 py-10 text-center text-sm text-theme-ink-muted">
                                No returnable quantity left on this sale.
                            </p>
                        )}
                        {form.data.items.length > 0 && (
                            <div className="overflow-x-auto">
                                <table className="w-full table-fixed text-left text-sm">
                                    <colgroup>
                                        <col className="w-10" />
                                        <col className="w-auto" />
                                        <col className="w-28" />
                                        <col className="w-28" />
                                        <col className="w-28" />
                                        <col className="w-32" />
                                        <col className="w-28" />
                                    </colgroup>
                                    <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                                        <tr>
                                            <th className="px-3 py-3 font-semibold" />
                                            <th className="px-3 py-3 font-semibold">Product</th>
                                            <th className="px-3 py-3 font-semibold">Sold</th>
                                            <th className="px-3 py-3 font-semibold">Returnable</th>
                                            <th className="px-3 py-3 font-semibold">Price</th>
                                            <th className="px-3 py-3 font-semibold">Return qty</th>
                                            <th className="px-3 py-3 text-right font-semibold">
                                                Line
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {form.data.items.map((row, index) => {
                                            const meta = row._meta || {};
                                            const line =
                                                row._include && Number(row.quantity || 0) > 0
                                                    ? Number(row.quantity) *
                                                      Number(meta.unit_price || 0)
                                                    : 0;
                                            return (
                                                <tr
                                                    key={row.sale_item_id}
                                                    className="border-t border-theme-border"
                                                >
                                                    <td className="px-3 py-3">
                                                        <input
                                                            type="checkbox"
                                                            checked={row._include}
                                                            onChange={(e) =>
                                                                setLine(index, {
                                                                    _include: e.target.checked,
                                                                    quantity: e.target.checked
                                                                        ? row.quantity ||
                                                                          String(
                                                                              meta.returnable_quantity,
                                                                          )
                                                                        : '',
                                                                })
                                                            }
                                                            className="rounded border-theme-border"
                                                        />
                                                    </td>
                                                    <td className="px-3 py-3 text-theme-ink">
                                                        <span className="line-clamp-2">
                                                            {meta.product_name}
                                                            {meta.variant_name
                                                                ? ` — ${meta.variant_name}`
                                                                : ''}
                                                        </span>
                                                    </td>
                                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                                        {meta.quantity} {meta.unit_code || ''}
                                                    </td>
                                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                                        {meta.returnable_quantity}
                                                    </td>
                                                    <td className="px-3 py-3 tabular-nums text-theme-ink-soft">
                                                        {money(meta.unit_price)}
                                                    </td>
                                                    <td className="px-3 py-3">
                                                        <input
                                                            type="number"
                                                            step="0.0001"
                                                            min="0"
                                                            max={meta.returnable_quantity}
                                                            value={row.quantity}
                                                            disabled={!row._include}
                                                            onChange={(e) =>
                                                                setLine(index, {
                                                                    quantity: e.target.value,
                                                                })
                                                            }
                                                            className="h-10 w-full max-w-[7.5rem] rounded-lg border border-theme-border bg-theme-surface px-2.5 text-sm tabular-nums text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20 disabled:opacity-50"
                                                        />
                                                    </td>
                                                    <td className="px-3 py-3 text-right tabular-nums font-medium text-theme-ink">
                                                        {money(line)}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {form.errors.items && (
                            <p className="border-t border-theme-border px-4 py-3 text-sm text-theme-danger">
                                {form.errors.items}
                            </p>
                        )}
                    </div>
                </div>

                <div className="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-theme-border pt-5">
                    <p className="text-sm text-theme-ink">
                        Refund total:{' '}
                        <span className="font-semibold tabular-nums">{money(total)}</span>
                    </p>
                    <div className="flex gap-2">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing || !selectedSale || total <= 0}
                        >
                            Refund & restock
                        </Button>
                    </div>
                </div>
            </form>
        </Drawer>
    );
}
