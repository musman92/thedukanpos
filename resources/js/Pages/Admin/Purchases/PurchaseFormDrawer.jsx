import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field, TextArea } from '@/Components/Ui/Input';
import SearchableSelect from '@/Components/Ui/SearchableSelect';
import { useForm } from '@inertiajs/react';
import { CalendarDays, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

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

function paymentStatusFor(total, paid) {
    const t = Number(total || 0);
    const p = Number(paid || 0);
    if (t <= 0.0001 || p + 0.0001 >= t) return 'paid';
    if (p > 0.0001) return 'partial';
    return 'pending';
}

function PaymentStatusBadge({ status }) {
    if (status === 'paid') {
        return (
            <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                Paid
            </span>
        );
    }
    if (status === 'partial') {
        return (
            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                Partial
            </span>
        );
    }
    return (
        <span className="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-semibold text-stone-600">
            Credit
        </span>
    );
}

const emptyData = (moneySources = []) => ({
    supplier_id: '',
    number: '',
    purchase_date: localToday(),
    tax_total: '0',
    discount_total: '0',
    notes: '',
    money_source_id: moneySources[0]?.id ? String(moneySources[0].id) : '',
    paid_amount: '0',
    items: [],
});

function dataFromPurchase(purchase, moneySources = []) {
    if (!purchase) return emptyData(moneySources);

    return {
        supplier_id: purchase.supplier_id ? String(purchase.supplier_id) : '',
        number: purchase.number || '',
        purchase_date: purchase.purchase_date || localToday(),
        tax_total: String(purchase.tax_total ?? 0),
        discount_total: String(purchase.discount_total ?? 0),
        notes: purchase.notes || '',
        money_source_id: purchase.money_source_id
            ? String(purchase.money_source_id)
            : moneySources[0]?.id
              ? String(moneySources[0].id)
              : '',
        paid_amount: String(purchase.paid_amount ?? 0),
        items: (purchase.items || []).map((item) => ({
            variant_id: String(item.variant_id),
            unit_id: String(item.unit_id),
            quantity: item.quantity ?? '',
            bonus_quantity: item.bonus_quantity ?? '0',
            bonus_unit_id: item.bonus_unit_id ? String(item.bonus_unit_id) : '',
            unit_price: item.unit_price ?? '',
            expiry_date: item.expiry_date || '',
            display_name: item.display_name || '—',
            short_code: item.short_code || '',
            purchase_unit_label: item.purchase_unit_label || '—',
        })),
    };
}

export default function PurchaseFormDrawer({
    open,
    purchase = null,
    suppliers = [],
    variants = [],
    moneySources = [],
    onClose,
}) {
    const editing = !!purchase?.id;
    const form = useForm(emptyData(moneySources));
    const [pickerKey, setPickerKey] = useState(0);
    const [discountMode, setDiscountMode] = useState('amount'); // amount | percent
    const [discountInput, setDiscountInput] = useState('0');
    const [expiryOpen, setExpiryOpen] = useState({});

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();
        const next = dataFromPurchase(purchase, moneySources);
        form.setData(next);
        setPickerKey((k) => k + 1);
        setDiscountMode('amount');
        setDiscountInput(String(next.discount_total || '0'));
        const openExpiry = {};
        next.items.forEach((item, index) => {
            if (item.expiry_date) openExpiry[index] = true;
        });
        setExpiryOpen(openExpiry);

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, purchase?.id]);

    const catalogOptions = useMemo(
        () =>
            variants.map((v) => ({
                value: v.id,
                label: `${v.short_code ? `${v.short_code} — ` : ''}${v.label}`,
                meta: [
                    v.purchase_unit?.name || v.purchase_unit?.code,
                    v.cost_per_unit != null ? `cost ${v.cost_per_unit}` : '',
                ]
                    .filter(Boolean)
                    .join(' · '),
            })),
        [variants],
    );

    const subtotal = useMemo(
        () =>
            form.data.items.reduce(
                (sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_price || 0),
                0,
            ),
        [form.data.items],
    );

    const discountAmount = useMemo(() => {
        const raw = Number(discountInput || 0);
        if (discountMode === 'percent') {
            return Math.min(subtotal, Math.max(0, (subtotal * raw) / 100));
        }
        return Math.min(subtotal, Math.max(0, raw));
    }, [discountInput, discountMode, subtotal]);

    useEffect(() => {
        form.setData('discount_total', discountAmount.toFixed(2));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [discountAmount]);

    const total = Math.max(
        0,
        subtotal + Number(form.data.tax_total || 0) - discountAmount,
    );

    const paidAmount = Number(form.data.paid_amount || 0);
    const balanceDue = Math.max(0, total - paidAmount);
    const paymentStatus = paymentStatusFor(total, paidAmount);
    const needsMoneySource = paidAmount > 0.0001;

    const addFromCatalog = (variantId) => {
        if (variantId === null || variantId === '') return;

        const variant = variants.find((v) => String(v.id) === String(variantId));
        if (!variant) return;

        const saleCost = Number(variant.cost_per_unit || 0);
        const rate = Number(variant.conversion_rate || 1) || 1;
        const suggested = saleCost > 0 ? (saleCost * rate).toFixed(2) : '';

        form.setData('items', [
            ...form.data.items,
            {
                variant_id: String(variant.id),
                unit_id: variant.purchase_unit_id ? String(variant.purchase_unit_id) : '',
                quantity: '',
                bonus_quantity: '0',
                bonus_unit_id: variant.sale_unit_id ? String(variant.sale_unit_id) : '',
                unit_price: suggested,
                expiry_date: '',
                display_name: variant.label,
                short_code: variant.short_code || '',
                purchase_unit_label:
                    variant.purchase_unit?.code || variant.purchase_unit?.name || '—',
            },
        ]);
        setPickerKey((k) => k + 1);
    };

    const setItem = (index, key, value) => {
        form.setData(
            'items',
            form.data.items.map((item, i) =>
                i === index ? { ...item, [key]: value } : item,
            ),
        );
    };

    const removeItem = (index) => {
        form.setData(
            'items',
            form.data.items.filter((_, i) => i !== index),
        );
        setExpiryOpen((prev) => {
            const next = {};
            Object.keys(prev).forEach((key) => {
                const i = Number(key);
                if (i < index) next[i] = prev[i];
                if (i > index) next[i - 1] = prev[i];
            });
            return next;
        });
    };

    const toggleExpiry = (index) => {
        setExpiryOpen((prev) => ({ ...prev, [index]: !prev[index] }));
    };

    const switchDiscountMode = (mode) => {
        if (mode === discountMode) return;
        if (mode === 'percent') {
            const pct =
                subtotal > 0
                    ? ((Number(discountInput || 0) / subtotal) * 100).toFixed(2)
                    : '0';
            setDiscountInput(pct);
        } else {
            setDiscountInput(discountAmount.toFixed(2));
        }
        setDiscountMode(mode);
    };

    const submit = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...(editing ? { _method: 'put' } : {}),
            number: data.number?.trim() || null,
            supplier_id: data.supplier_id || null,
            purchase_date: data.purchase_date,
            tax_total: data.tax_total,
            discount_total: discountAmount.toFixed(2),
            notes: data.notes,
            money_source_id: data.money_source_id || null,
            paid_amount: data.paid_amount === '' || data.paid_amount == null ? 0 : data.paid_amount,
            items: data.items.map((item) => ({
                variant_id: item.variant_id,
                unit_id: item.unit_id,
                quantity: item.quantity,
                bonus_quantity: item.bonus_quantity || 0,
                bonus_unit_id: item.bonus_unit_id || null,
                unit_price: item.unit_price,
                expiry_date: item.expiry_date || null,
            })),
        }));
        const url = editing
            ? route('admin.purchases.update', purchase.id)
            : route('admin.purchases.store');
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
            title={editing ? 'Edit purchase' : 'New purchase'}
            description={
                editing
                    ? 'Update lines, payment, and stock for this purchase.'
                    : 'Receive stock, set payment, and update inventory.'
            }
            width="wide"
            bodyClassName="overflow-y-auto flex flex-col"
        >
            <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
                <div className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Field label="Supplier" error={form.errors.supplier_id}>
                            <select
                                value={form.data.supplier_id}
                                onChange={(e) => form.setData('supplier_id', e.target.value)}
                                className={selectClass}
                                autoFocus
                            >
                                <option value="">Select supplier</option>
                                {suppliers.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Date" required error={form.errors.purchase_date}>
                            <Input
                                type="date"
                                value={form.data.purchase_date}
                                onChange={(e) => form.setData('purchase_date', e.target.value)}
                                error={!!form.errors.purchase_date}
                            />
                        </Field>
                        <Field
                            label="Ref no"
                            error={form.errors.number}
                            hint="Leave blank to auto-generate."
                        >
                            <Input
                                value={form.data.number}
                                onChange={(e) => form.setData('number', e.target.value)}
                                placeholder="Auto if blank"
                                error={!!form.errors.number}
                            />
                        </Field>
                    </div>

                    <div className="space-y-3">
                        <div className="border-b border-theme-border pb-2">
                            <h3 className="text-base font-semibold text-theme-ink">
                                Purchase Items
                            </h3>
                            <p className="mt-1 text-sm text-theme-ink-muted">
                                Search and select items — use the calendar icon to set expiry when
                                needed.
                            </p>
                        </div>

                        <Field label="Add item" required>
                            <SearchableSelect
                                key={pickerKey}
                                options={catalogOptions}
                                value={null}
                                onChange={addFromCatalog}
                                placeholder="Search products or variants…"
                                searchable
                            />
                        </Field>

                        <div className="overflow-x-auto rounded-lg border border-theme-border">
                            <table className="min-w-full text-left text-sm">
                                <thead className="bg-theme-bg text-[11px] uppercase tracking-wide text-theme-ink-muted">
                                    <tr>
                                        <th className="w-10 px-3 py-3 font-semibold">#</th>
                                        <th className="min-w-[200px] px-3 py-3 font-semibold">
                                            Item
                                        </th>
                                        <th className="w-36 px-3 py-3 text-right font-semibold">
                                            Unit price
                                        </th>
                                        <th className="w-44 px-3 py-3 font-semibold">Quantity</th>
                                        <th className="w-28 px-3 py-3 text-right font-semibold">
                                            Total
                                        </th>
                                        <th className="w-20 px-3 py-3" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {form.data.items.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="px-6 py-10 text-center text-sm text-theme-ink-muted"
                                            >
                                                No items yet. Use the search box above to add
                                                products.
                                            </td>
                                        </tr>
                                    )}
                                    {form.data.items.map((item, index) => {
                                        const lineTotal =
                                            Number(item.quantity || 0) *
                                            Number(item.unit_price || 0);
                                        const showExpiry = !!expiryOpen[index] || !!item.expiry_date;

                                        return (
                                            <tr
                                                key={`${item.variant_id}-${index}`}
                                                className="border-t border-theme-border align-middle"
                                            >
                                                <td className="px-3 py-3 text-theme-ink-muted">
                                                    {index + 1}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <p className="font-medium text-theme-ink">
                                                        {item.display_name || '—'}
                                                    </p>
                                                    <div className="mt-0.5 flex flex-wrap items-center gap-2">
                                                        {item.short_code && (
                                                            <span className="text-xs text-theme-ink-muted">
                                                                {item.short_code}
                                                            </span>
                                                        )}
                                                        {showExpiry && (
                                                            <input
                                                                type="date"
                                                                value={item.expiry_date || ''}
                                                                onChange={(e) =>
                                                                    setItem(
                                                                        index,
                                                                        'expiry_date',
                                                                        e.target.value,
                                                                    )
                                                                }
                                                                className="h-7 w-[9.5rem] rounded border border-theme-border bg-theme-surface px-1.5 text-xs text-theme-ink outline-none focus:border-theme-primary focus:ring-1 focus:ring-theme-primary/20"
                                                                title="Expiry date"
                                                            />
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-3 py-3">
                                                    <div className="relative ml-auto w-full max-w-[8.5rem]">
                                                        <span className="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-xs text-theme-ink-muted">
                                                            Rs
                                                        </span>
                                                        <input
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            required
                                                            value={item.unit_price}
                                                            onChange={(e) =>
                                                                setItem(
                                                                    index,
                                                                    'unit_price',
                                                                    e.target.value,
                                                                )
                                                            }
                                                            className="h-10 w-full rounded-lg border border-theme-border bg-theme-surface py-2 pl-7 pr-2 text-right text-sm tabular-nums outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                                        />
                                                    </div>
                                                </td>
                                                <td className="px-3 py-3">
                                                    <div className="flex items-center gap-2">
                                                        <input
                                                            type="number"
                                                            step="0.01"
                                                            min="0.01"
                                                            required
                                                            placeholder="0"
                                                            value={item.quantity}
                                                            onChange={(e) =>
                                                                setItem(
                                                                    index,
                                                                    'quantity',
                                                                    e.target.value,
                                                                )
                                                            }
                                                            className="h-10 w-24 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm tabular-nums outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                                        />
                                                        <span className="whitespace-nowrap text-sm font-medium text-theme-ink-soft">
                                                            {item.purchase_unit_label}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-3 py-3 text-right tabular-nums font-medium text-theme-ink">
                                                    {money(lineTotal)}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <button
                                                            type="button"
                                                            onClick={() => toggleExpiry(index)}
                                                            className={`inline-flex rounded-lg p-2 ${
                                                                showExpiry || item.expiry_date
                                                                    ? 'bg-theme-primary/10 text-theme-primary'
                                                                    : 'text-theme-ink-muted hover:bg-theme-bg hover:text-theme-ink'
                                                            }`}
                                                            title="Expiry date"
                                                            aria-label="Expiry date"
                                                        >
                                                            <CalendarDays className="h-4 w-4" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => removeItem(index)}
                                                            className="inline-flex rounded-lg p-2 text-theme-danger hover:bg-theme-danger/10"
                                                            title="Remove"
                                                            aria-label="Remove"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                        {form.errors.items && (
                            <p className="text-sm text-theme-danger">{form.errors.items}</p>
                        )}
                    </div>

                    <div className="grid gap-6 border-t border-theme-border pt-5 lg:grid-cols-2">
                        <div className="space-y-3">
                            <div className="flex items-center justify-between gap-2">
                                <p className="text-sm font-medium text-theme-ink">Payment</p>
                                <PaymentStatusBadge status={paymentStatus} />
                            </div>

                            <Field
                                label="Money source"
                                required={needsMoneySource}
                                error={form.errors.money_source_id}
                                hint={
                                    needsMoneySource
                                        ? undefined
                                        : 'Required when paid amount is greater than zero.'
                                }
                            >
                                <select
                                    value={form.data.money_source_id}
                                    onChange={(e) =>
                                        form.setData('money_source_id', e.target.value)
                                    }
                                    className={selectClass}
                                >
                                    <option value="">Select source</option>
                                    {moneySources.map((m) => (
                                        <option key={m.id} value={m.id}>
                                            {m.name} ({money(m.balance)})
                                        </option>
                                    ))}
                                </select>
                            </Field>

                            <p className="text-xs text-theme-ink-muted">
                                {paymentStatus === 'pending' &&
                                    'Paid 0 → credit (supplier balance increases by the total).'}
                                {paymentStatus === 'partial' &&
                                    `Partial · balance due ${money(balanceDue)}.`}
                                {paymentStatus === 'paid' && 'Fully paid.'}
                            </p>
                        </div>

                        <div className="ml-auto w-full space-y-2 text-sm md:max-w-sm">
                            <div className="flex items-center justify-between gap-4">
                                <span className="text-theme-ink-muted">Subtotal</span>
                                <span className="tabular-nums font-medium text-theme-ink">
                                    {money(subtotal)}
                                </span>
                            </div>

                            <div className="flex items-center justify-between gap-4">
                                <span className="text-theme-ink-muted">Paid</span>
                                <div className="relative w-36">
                                    <span className="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-theme-ink-muted">
                                        Rs
                                    </span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={form.data.paid_amount}
                                        onChange={(e) =>
                                            form.setData('paid_amount', e.target.value)
                                        }
                                        className="h-10 w-full rounded-lg border border-theme-border bg-theme-surface py-2 pl-8 pr-2 text-right text-sm outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                    />
                                </div>
                            </div>
                            {form.errors.paid_amount && (
                                <p className="text-right text-xs text-theme-danger">
                                    {form.errors.paid_amount}
                                </p>
                            )}

                            <div className="flex items-center justify-between gap-4">
                                <span className="text-theme-ink-muted">Tax</span>
                                <div className="relative w-36">
                                    <span className="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-theme-ink-muted">
                                        Rs
                                    </span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={form.data.tax_total}
                                        onChange={(e) => form.setData('tax_total', e.target.value)}
                                        className="h-10 w-full rounded-lg border border-theme-border bg-theme-surface py-2 pl-8 pr-2 text-right text-sm outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                    />
                                </div>
                            </div>

                            <div className="flex items-center justify-between gap-4">
                                <span className="text-theme-ink-muted">Discount</span>
                                <div className="flex items-center gap-1.5">
                                    <div className="inline-flex rounded-lg border border-theme-border p-0.5">
                                        <button
                                            type="button"
                                            onClick={() => switchDiscountMode('percent')}
                                            className={`rounded-md px-2 py-1.5 text-xs font-semibold ${
                                                discountMode === 'percent'
                                                    ? 'bg-theme-ink text-theme-surface'
                                                    : 'text-theme-ink-muted hover:text-theme-ink'
                                            }`}
                                        >
                                            %
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => switchDiscountMode('amount')}
                                            className={`rounded-md px-2 py-1.5 text-xs font-semibold ${
                                                discountMode === 'amount'
                                                    ? 'bg-theme-ink text-theme-surface'
                                                    : 'text-theme-ink-muted hover:text-theme-ink'
                                            }`}
                                        >
                                            Rs
                                        </button>
                                    </div>
                                    <div className="relative w-28">
                                        {discountMode === 'amount' && (
                                            <span className="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-theme-ink-muted">
                                                Rs
                                            </span>
                                        )}
                                        {discountMode === 'percent' && (
                                            <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-theme-ink-muted">
                                                %
                                            </span>
                                        )}
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max={discountMode === 'percent' ? 100 : undefined}
                                            value={discountInput}
                                            onChange={(e) => setDiscountInput(e.target.value)}
                                            className={`h-10 w-full rounded-lg border border-theme-border bg-theme-surface py-2 text-right text-sm outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20 ${
                                                discountMode === 'amount'
                                                    ? 'pl-8 pr-2'
                                                    : 'pl-2 pr-7'
                                            }`}
                                        />
                                    </div>
                                </div>
                            </div>
                            {discountMode === 'percent' && discountAmount > 0 && (
                                <p className="text-right text-xs text-theme-ink-muted">
                                    = Rs {money(discountAmount)}
                                </p>
                            )}

                            <div className="flex items-center justify-between gap-4 border-t border-theme-border pt-2 text-base font-semibold text-theme-ink">
                                <span>Total</span>
                                <span className="tabular-nums">{money(total)}</span>
                            </div>
                            <div className="flex items-center justify-between gap-4 text-theme-ink-soft">
                                <span>Balance due</span>
                                <span className="tabular-nums">{money(balanceDue)}</span>
                            </div>
                        </div>
                    </div>

                    <Field label="Notes" error={form.errors.notes}>
                        <TextArea
                            rows={3}
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            placeholder="Optional notes"
                        />
                    </Field>
                </div>

                <div className="mt-auto flex justify-end gap-2 border-t border-theme-border pt-5">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        disabled={form.processing || form.data.items.length === 0}
                    >
                        {editing ? 'Save changes' : 'Receive & update stock'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
