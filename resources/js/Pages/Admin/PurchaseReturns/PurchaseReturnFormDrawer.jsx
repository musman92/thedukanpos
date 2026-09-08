import Button from '@/Components/Ui/Button';
import { formatAmount as money } from '@/lib/money';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field, TextArea } from '@/Components/Ui/Input';
import { lineMatchesQuery, pickExactLine } from '@/lib/catalogSearch';
import { router, useForm } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

const selectClass =
    'h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

function localToday() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function buildItems(selectedPurchase, editing) {
    const qtyMap = {};
    (editing?.items || []).forEach((row) => {
        qtyMap[String(row.purchase_item_id)] = row.quantity ?? '';
    });

    return (selectedPurchase?.items || []).map((item) => ({
        purchase_item_id: item.id,
        quantity: qtyMap[String(item.id)] ?? '',
        _meta: item,
    }));
}

export default function PurchaseReturnFormDrawer({
    open,
    purchaseReturn = null,
    suppliers = [],
    purchases = [],
    selectedPurchase = null,
    formSupplierId = null,
    listQuery = {},
    onClose,
}) {
    const editing = !!purchaseReturn?.id;

    const form = useForm({
        supplier_id: formSupplierId ? String(formSupplierId) : '',
        purchase_id: selectedPurchase?.id ? String(selectedPurchase.id) : '',
        return_date: purchaseReturn?.return_date || localToday(),
        notes: purchaseReturn?.notes || '',
        items: buildItems(selectedPurchase, purchaseReturn),
    });

    const [itemQuery, setItemQuery] = useState('');
    const [highlightId, setHighlightId] = useState(null);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();
        form.setData({
            supplier_id: formSupplierId
                ? String(formSupplierId)
                : purchaseReturn?.supplier_id
                  ? String(purchaseReturn.supplier_id)
                  : '',
            purchase_id: selectedPurchase?.id
                ? String(selectedPurchase.id)
                : purchaseReturn?.purchase_id
                  ? String(purchaseReturn.purchase_id)
                  : '',
            return_date: purchaseReturn?.return_date || localToday(),
            notes: purchaseReturn?.notes || '',
            items: buildItems(selectedPurchase, purchaseReturn),
        });
        setItemQuery('');
        setHighlightId(null);

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, formSupplierId, selectedPurchase?.id, purchaseReturn?.id]);

    const reloadForm = (overrides = {}) => {
        router.get(
            route('admin.returns.purchases.index'),
            {
                ...listQuery,
                open: editing ? undefined : 1,
                edit: editing ? purchaseReturn.id : undefined,
                form_supplier_id: overrides.form_supplier_id ?? formSupplierId ?? undefined,
                purchase_id: overrides.purchase_id,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const onSupplierChange = (supplierId) => {
        if (editing) return;
        form.setData({
            ...form.data,
            supplier_id: supplierId,
            purchase_id: '',
            items: [],
        });
        if (!supplierId) {
            reloadForm({ form_supplier_id: undefined, purchase_id: undefined });
            return;
        }
        reloadForm({ form_supplier_id: supplierId, purchase_id: undefined });
    };

    const onPurchaseChange = (purchaseId) => {
        if (editing) return;
        form.setData('purchase_id', purchaseId);
        if (!purchaseId) {
            reloadForm({
                form_supplier_id: form.data.supplier_id || formSupplierId,
                purchase_id: undefined,
            });
            return;
        }
        reloadForm({
            form_supplier_id: form.data.supplier_id || formSupplierId,
            purchase_id: purchaseId,
        });
    };

    const total = useMemo(
        () =>
            form.data.items.reduce((sum, row) => {
                const qty = Number(row.quantity || 0);
                const price = Number(row._meta?.unit_price || 0);
                return sum + qty * price;
            }, 0),
        [form.data.items],
    );

    const setQty = (index, value) => {
        form.setData(
            'items',
            form.data.items.map((item, i) =>
                i === index ? { ...item, quantity: value } : item,
            ),
        );
    };

    const visibleItems = useMemo(() => {
        return form.data.items
            .map((row, index) => ({ row, index }))
            .filter(({ row }) => lineMatchesQuery(row._meta || {}, itemQuery));
    }, [form.data.items, itemQuery]);

    const applyScan = (rawQuery) => {
        const matchIndex = pickExactLine(
            form.data.items,
            rawQuery,
            (row) => row._meta || {},
        );
        if (matchIndex < 0) return false;

        const row = form.data.items[matchIndex];
        const max = Number(row._meta?.returnable_quantity || 0);
        const current = Number(row.quantity || 0);
        const next = Math.min(max, current + 1);
        setQty(matchIndex, next > 0 ? String(next) : '');
        setHighlightId(row.purchase_item_id);
        setItemQuery('');
        return true;
    };

    const onItemSearchKeyDown = (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        applyScan(e.currentTarget.value);
    };

    const submit = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...(editing ? { _method: 'put' } : {}),
            ...(editing ? {} : { purchase_id: data.purchase_id }),
            return_date: data.return_date,
            notes: data.notes,
            items: data.items.map((row) => ({
                purchase_item_id: row.purchase_item_id,
                quantity: row.quantity || 0,
            })),
        }));
        const url = editing
            ? route('admin.returns.purchases.update', purchaseReturn.id)
            : route('admin.returns.purchases.store');
        form.post(url, {
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
            title={editing ? 'Edit purchase return' : 'New purchase return'}
            description={
                editing
                    ? 'Update return quantities, date, and notes.'
                    : 'Choose a supplier, pick a purchase, then return stock.'
            }
            width="wide"
            bodyClassName="overflow-y-auto flex flex-col"
        >
            <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
                <div className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Field label="Supplier" required>
                            <select
                                value={form.data.supplier_id}
                                onChange={(e) => onSupplierChange(e.target.value)}
                                className={selectClass}
                                autoFocus={!editing}
                                disabled={editing}
                            >
                                <option value="">Select supplier</option>
                                {suppliers.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Purchase" required error={form.errors.purchase_id}>
                            <select
                                value={form.data.purchase_id}
                                onChange={(e) => onPurchaseChange(e.target.value)}
                                className={selectClass}
                                disabled={editing || !form.data.supplier_id}
                            >
                                <option value="">
                                    {form.data.supplier_id
                                        ? 'Select purchase'
                                        : 'Select supplier first'}
                                </option>
                                {purchases.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.number}
                                        {p.purchase_date ? ` · ${p.purchase_date}` : ''}
                                        {` · ${money(p.total)}`}
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

                    {selectedPurchase && (
                        <div className="rounded-lg border border-theme-border bg-theme-bg px-3 py-2 text-sm">
                            <p className="text-xs uppercase tracking-wide text-theme-ink-muted">
                                Purchase balance due
                            </p>
                            <p className="mt-0.5 font-medium tabular-nums text-theme-ink">
                                {money(selectedPurchase.balance_due)}
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
                        <div className="flex flex-col gap-3 border-b border-theme-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="text-sm font-semibold text-theme-ink">
                                Returnable lines
                            </div>
                            {form.data.items.length > 0 && (
                                <div className="relative w-full sm:max-w-sm">
                                    <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-theme-ink-muted" />
                                    <input
                                        value={itemQuery}
                                        onChange={(e) => setItemQuery(e.target.value)}
                                        onKeyDown={onItemSearchKeyDown}
                                        placeholder="Scan barcode or search lines…"
                                        className="h-10 w-full rounded-lg border border-theme-border bg-theme-surface py-2 pl-8 pr-3 text-sm text-theme-ink outline-none placeholder:text-theme-ink-muted focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                    />
                                    <p className="mt-1 text-[11px] text-theme-ink-muted">
                                        Enter on an exact barcode or short code adds 1 to return
                                        qty.
                                    </p>
                                </div>
                            )}
                        </div>
                        {!form.data.supplier_id && (
                            <p className="px-4 py-10 text-center text-sm text-theme-ink-muted">
                                Select a supplier to see their purchases.
                            </p>
                        )}
                        {form.data.supplier_id && !selectedPurchase && (
                            <p className="px-4 py-10 text-center text-sm text-theme-ink-muted">
                                {purchases.length === 0
                                    ? 'No returnable purchases for this supplier.'
                                    : 'Select a purchase to load returnable lines.'}
                            </p>
                        )}
                        {selectedPurchase && form.data.items.length === 0 && (
                            <p className="px-4 py-10 text-center text-sm text-theme-ink-muted">
                                No returnable quantity left on this purchase.
                            </p>
                        )}
                        {form.data.items.length > 0 && visibleItems.length === 0 && (
                            <p className="px-4 py-10 text-center text-sm text-theme-ink-muted">
                                No lines match that barcode or search.
                            </p>
                        )}
                        {visibleItems.length > 0 && (
                            <div className="overflow-x-auto">
                                <table className="w-full table-fixed text-left text-sm">
                                    <colgroup>
                                        <col className="w-auto" />
                                        <col className="w-28" />
                                        <col className="w-28" />
                                        <col className="w-28" />
                                        <col className="w-32" />
                                        <col className="w-28" />
                                    </colgroup>
                                    <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                                        <tr>
                                            <th className="px-3 py-3 font-semibold">Product</th>
                                            <th className="px-3 py-3 font-semibold">Bought</th>
                                            <th className="px-3 py-3 font-semibold">Returnable</th>
                                            <th className="px-3 py-3 font-semibold">Price</th>
                                            <th className="px-3 py-3 font-semibold">Return qty</th>
                                            <th className="px-3 py-3 text-right font-semibold">
                                                Line
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {visibleItems.map(({ row, index }) => {
                                            const meta = row._meta || {};
                                            const line =
                                                Number(row.quantity || 0) *
                                                Number(meta.unit_price || 0);
                                            const highlighted =
                                                String(highlightId) ===
                                                String(row.purchase_item_id);
                                            return (
                                                <tr
                                                    key={row.purchase_item_id}
                                                    className={`border-t border-theme-border ${
                                                        highlighted
                                                            ? 'bg-theme-primary/5'
                                                            : ''
                                                    }`}
                                                >
                                                    <td className="px-3 py-3 text-theme-ink">
                                                        <span className="line-clamp-2">
                                                            {meta.product_name}
                                                            {meta.variant_name
                                                                ? ` — ${meta.variant_name}`
                                                                : ''}
                                                        </span>
                                                        {(meta.short_code || meta.barcode) && (
                                                            <span className="mt-0.5 block text-xs text-theme-ink-muted">
                                                                {[meta.short_code, meta.barcode]
                                                                    .filter(Boolean)
                                                                    .join(' · ')}
                                                            </span>
                                                        )}
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
                                                            onChange={(e) =>
                                                                setQty(index, e.target.value)
                                                            }
                                                            className="h-10 w-full max-w-[7.5rem] rounded-lg border border-theme-border bg-theme-surface px-2.5 text-sm tabular-nums text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
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
                        {form.errors.purchase && (
                            <p className="border-t border-theme-border px-4 py-3 text-sm text-theme-danger">
                                {form.errors.purchase}
                            </p>
                        )}
                    </div>
                </div>

                <div className="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-theme-border pt-5">
                    <p className="text-sm text-theme-ink">
                        Return total:{' '}
                        <span className="font-semibold tabular-nums">{money(total)}</span>
                    </p>
                    <div className="flex gap-2">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing || !selectedPurchase || total <= 0}
                        >
                            {editing ? 'Save changes' : 'Return & deduct stock'}
                        </Button>
                    </div>
                </div>
            </form>
        </Drawer>
    );
}
