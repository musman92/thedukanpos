import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field, TextArea } from '@/Components/Ui/Input';
import SearchableSelect from '@/Components/Ui/SearchableSelect';
import {
    formatAmount as money,
    formatAmountInput,
    formatMoney,
    moneySymbol,
} from '@/lib/money';
import { useForm } from '@inertiajs/react';
import { Trash2, Truck } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

const selectClass =
    'h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

function localToday() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function calcLine(qty, unitPrice, lineDiscount, tax) {
    const gross = Number(qty || 0) * Number(unitPrice || 0) - Number(lineDiscount || 0);
    let lineNet = gross;
    let taxAmount = 0;

    if (tax && Number(tax.rate) > 0) {
        if (tax.is_inclusive) {
            lineNet = gross / (1 + Number(tax.rate) / 100);
            taxAmount = gross - lineNet;
        } else {
            taxAmount = gross * (Number(tax.rate) / 100);
        }
    }

    return {
        lineNet,
        taxAmount,
        lineTotal: lineNet + taxAmount,
    };
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

const emptyData = (defaults = {}) => ({
    customer_id: defaults.defaultCustomerId ? String(defaults.defaultCustomerId) : '',
    business_date: defaults.todayDate || localToday(),
    discount_total: '0',
    notes: '',
    is_delivery: false,
    delivery_charge: '0',
    delivery_address: '',
    rider_id: '',
    money_source_id: defaults.moneySourceId ? String(defaults.moneySourceId) : '',
    paid_amount: '0',
    items: [],
});

export default function OrderFormDrawer({
    open,
    onClose,
    customers = [],
    variants = [],
    money_sources: moneySources = [],
    riders = [],
    default_customer_id: defaultCustomerId = null,
    today_date: todayDate = null,
    allow_credit: allowCredit = true,
    enable_delivery: enableDelivery = false,
}) {
    const defaultMoneySourceId = moneySources[0]?.id ?? null;
    const form = useForm(
        emptyData({
            defaultCustomerId,
            todayDate,
            moneySourceId: defaultMoneySourceId,
        }),
    );
    const [pickerKey, setPickerKey] = useState(0);
    const [discountMode, setDiscountMode] = useState('amount');
    const [discountInput, setDiscountInput] = useState('0');

    useEffect(() => {
        if (!open) return undefined;

        form.clearErrors();
        form.setData(
            emptyData({
                defaultCustomerId,
                todayDate,
                moneySourceId: defaultMoneySourceId,
            }),
        );
        setPickerKey((k) => k + 1);
        setDiscountMode('amount');
        setDiscountInput('0');

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, defaultCustomerId, todayDate, defaultMoneySourceId]);

    const customerOptions = useMemo(
        () =>
            customers.map((c) => ({
                value: c.id,
                label: c.name,
                meta: [
                    c.is_walk_in ? 'Walk-in' : null,
                    c.phone,
                    c.balance != null ? `bal ${money(c.balance)}` : '',
                ]
                    .filter(Boolean)
                    .join(' · '),
            })),
        [customers],
    );

    const customerMap = useMemo(
        () => Object.fromEntries(customers.map((c) => [String(c.id), c])),
        [customers],
    );

    const catalogOptions = useMemo(
        () =>
            variants.map((v) => ({
                value: v.id,
                label: `${v.short_code ? `${v.short_code} — ` : ''}${v.label}`,
                meta: [
                    v.sale_unit?.name || v.sale_unit?.code,
                    v.sale_price != null ? `price ${money(v.sale_price)}` : '',
                ]
                    .filter(Boolean)
                    .join(' · '),
            })),
        [variants],
    );

    const variantMap = useMemo(
        () => Object.fromEntries(variants.map((v) => [String(v.id), v])),
        [variants],
    );

    const moneySourceOptions = useMemo(
        () =>
            moneySources.map((s) => ({
                value: s.id,
                label: s.name,
                meta: s.type,
            })),
        [moneySources],
    );

    const riderOptions = useMemo(
        () => riders.map((r) => ({ value: r.id, label: r.name })),
        [riders],
    );

    const lineTotals = useMemo(
        () =>
            form.data.items.map((item) => {
                const variant = variantMap[item.variant_id];
                const tax = variant?.tax || item.tax;
                return calcLine(item.quantity, item.unit_price, item.discount, tax);
            }),
        [form.data.items, variantMap],
    );

    const subtotal = useMemo(
        () => lineTotals.reduce((sum, line) => sum + line.lineNet, 0),
        [lineTotals],
    );

    const taxTotal = useMemo(
        () => lineTotals.reduce((sum, line) => sum + line.taxAmount, 0),
        [lineTotals],
    );

    const discountAmount = useMemo(() => {
        const raw = Number(discountInput || 0);
        const base = subtotal + taxTotal;
        if (discountMode === 'percent') {
            return Math.min(base, Math.max(0, (base * raw) / 100));
        }
        return Math.min(base, Math.max(0, raw));
    }, [discountInput, discountMode, subtotal, taxTotal]);

    useEffect(() => {
        form.setData('discount_total', formatAmountInput(discountAmount));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [discountAmount]);

    const deliveryCharge = form.data.is_delivery ? Number(form.data.delivery_charge || 0) : 0;
    const total = Math.max(0, subtotal + taxTotal - discountAmount + deliveryCharge);
    const paidAmount = Number(form.data.paid_amount || 0);
    const balanceDue = Math.max(0, total - paidAmount);
    const paymentStatus = paymentStatusFor(total, paidAmount);

    const selectedCustomer = customerMap[form.data.customer_id] || null;
    const isWalkIn = !!selectedCustomer?.is_walk_in;
    const needsNamedCustomer = form.data.is_delivery || (paidAmount <= 0 && !allowCredit);

    const addFromCatalog = (variantId) => {
        if (variantId === null || variantId === '') return;

        const variant = variants.find((v) => String(v.id) === String(variantId));
        if (!variant) return;

        const unitId = variant.sale_unit_id;
        const suggested =
            variant.sale_price != null && Number(variant.sale_price) > 0
                ? formatAmountInput(variant.sale_price)
                : '';

        form.setData('items', [
            ...form.data.items,
            {
                variant_id: String(variant.id),
                unit_id: unitId ? String(unitId) : '',
                quantity: '',
                unit_price: suggested,
                discount: '0',
                display_name: variant.label,
                short_code: variant.short_code || '',
                sale_unit_label: variant.sale_unit?.code || variant.sale_unit?.name || '—',
                tax: variant.tax || null,
            },
        ]);
        setPickerKey((k) => k + 1);
    };

    const setItem = (index, key, value) => {
        form.setData(
            'items',
            form.data.items.map((item, i) => (i === index ? { ...item, [key]: value } : item)),
        );
    };

    const removeItem = (index) => {
        form.setData(
            'items',
            form.data.items.filter((_, i) => i !== index),
        );
    };

    const switchDiscountMode = (mode) => {
        if (mode === discountMode) return;
        const base = subtotal + taxTotal;
        if (mode === 'percent') {
            const pct = base > 0 ? ((Number(discountInput || 0) / base) * 100).toFixed(2) : '0';
            setDiscountInput(pct);
        } else {
            setDiscountInput(formatAmountInput(discountAmount));
        }
        setDiscountMode(mode);
    };

    const payFullAmount = () => {
        form.setData('paid_amount', formatAmountInput(total));
    };

    const submit = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            customer_id: data.customer_id || null,
            business_date: data.business_date,
            discount_total: formatAmountInput(discountAmount),
            notes: data.notes,
            is_delivery: Boolean(data.is_delivery),
            delivery_charge: data.is_delivery ? data.delivery_charge : 0,
            delivery_address: data.is_delivery ? data.delivery_address : null,
            rider_id: data.is_delivery && data.rider_id ? data.rider_id : null,
            money_source_id: data.money_source_id || null,
            paid_amount: data.paid_amount || 0,
            items: data.items.map((item) => ({
                variant_id: item.variant_id,
                unit_id: item.unit_id,
                quantity: item.quantity,
                unit_price: item.unit_price,
                discount: item.discount || 0,
            })),
        }));
        form.post(route('admin.orders.store'), {
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
            title="New order"
            description="Create a sale from the back office — stock is deducted and payments post like POS."
            width="wide"
            bodyClassName="overflow-y-auto flex flex-col"
        >
            <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
                <div className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Field label="Customer" error={form.errors.customer_id}>
                            <SearchableSelect
                                options={customerOptions}
                                value={form.data.customer_id || null}
                                onChange={(val) => {
                                    const id = val ? String(val) : '';
                                    form.setData('customer_id', id);
                                    const customer = customerMap[id];
                                    if (customer?.address && !form.data.delivery_address) {
                                        form.setData('delivery_address', customer.address || '');
                                    }
                                }}
                                placeholder={
                                    needsNamedCustomer
                                        ? 'Select a customer'
                                        : 'Walk-in default if empty'
                                }
                                searchable
                            />
                        </Field>
                        <Field label="Business date" required error={form.errors.business_date}>
                            <Input
                                type="date"
                                value={form.data.business_date}
                                onChange={(e) => form.setData('business_date', e.target.value)}
                                error={!!form.errors.business_date}
                            />
                        </Field>
                    </div>

                    {enableDelivery && (
                        <div className="rounded-lg border border-theme-border bg-theme-bg/40 p-4">
                            <label className="flex cursor-pointer items-center gap-2 text-sm font-medium text-theme-ink">
                                <input
                                    type="checkbox"
                                    checked={!!form.data.is_delivery}
                                    onChange={(e) => {
                                        form.setData('is_delivery', e.target.checked);
                                        if (!e.target.checked) {
                                            form.setData('delivery_charge', '0');
                                            form.setData('rider_id', '');
                                        }
                                    }}
                                    className="rounded border-theme-border"
                                />
                                <Truck className="h-4 w-4 text-theme-primary" strokeWidth={1.75} />
                                Delivery order
                            </label>
                            {form.errors.is_delivery && (
                                <p className="mt-2 text-sm text-theme-danger">{form.errors.is_delivery}</p>
                            )}
                            {form.data.is_delivery && (
                                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                    <Field label="Delivery charge" error={form.errors.delivery_charge}>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={form.data.delivery_charge}
                                            onChange={(e) =>
                                                form.setData('delivery_charge', e.target.value)
                                            }
                                        />
                                    </Field>
                                    <Field label="Rider" error={form.errors.rider_id}>
                                        <SearchableSelect
                                            options={riderOptions}
                                            value={form.data.rider_id || null}
                                            onChange={(val) =>
                                                form.setData('rider_id', val ? String(val) : '')
                                            }
                                            placeholder="Optional"
                                            searchable
                                        />
                                    </Field>
                                    <Field
                                        label="Delivery address"
                                        className="sm:col-span-2"
                                        error={form.errors.delivery_address}
                                    >
                                        <TextArea
                                            rows={2}
                                            value={form.data.delivery_address}
                                            onChange={(e) =>
                                                form.setData('delivery_address', e.target.value)
                                            }
                                            placeholder="Required for delivery"
                                        />
                                    </Field>
                                </div>
                            )}
                        </div>
                    )}

                    <div className="space-y-3">
                        <div className="border-b border-theme-border pb-2">
                            <h3 className="text-base font-semibold text-theme-ink">Line items</h3>
                            <p className="mt-1 text-sm text-theme-ink-muted">
                                Add products — quantities and prices deduct stock on save.
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
                                        <th className="min-w-[200px] px-3 py-3 font-semibold">Item</th>
                                        <th className="w-36 px-3 py-3 text-right font-semibold">Unit price</th>
                                        <th className="w-44 px-3 py-3 font-semibold">Quantity</th>
                                        <th className="w-28 px-3 py-3 text-right font-semibold">Discount</th>
                                        <th className="w-28 px-3 py-3 text-right font-semibold">Total</th>
                                        <th className="w-20 px-3 py-3" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {form.data.items.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="px-6 py-10 text-center text-sm text-theme-ink-muted"
                                            >
                                                No items yet. Search above to add products.
                                            </td>
                                        </tr>
                                    )}
                                    {form.data.items.map((item, index) => {
                                        const line = lineTotals[index] || { lineTotal: 0 };
                                        return (
                                            <tr
                                                key={`${item.variant_id}-${index}`}
                                                className="border-t border-theme-border align-middle"
                                            >
                                                <td className="px-3 py-3 text-theme-ink-muted">{index + 1}</td>
                                                <td className="px-3 py-3">
                                                    <p className="font-medium text-theme-ink">
                                                        {item.display_name || '—'}
                                                    </p>
                                                    {item.short_code && (
                                                        <span className="text-xs text-theme-ink-muted">
                                                            {item.short_code}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <div className="relative ml-auto w-full max-w-[8.5rem]">
                                                        <span className="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-xs text-theme-ink-muted">
                                                            {moneySymbol()}
                                                        </span>
                                                        <input
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            required
                                                            value={item.unit_price}
                                                            onChange={(e) =>
                                                                setItem(index, 'unit_price', e.target.value)
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
                                                                setItem(index, 'quantity', e.target.value)
                                                            }
                                                            className="h-10 w-24 rounded-lg border border-theme-border bg-theme-surface px-2 text-sm tabular-nums outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                                        />
                                                        <span className="whitespace-nowrap text-sm font-medium text-theme-ink-soft">
                                                            {item.sale_unit_label}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-3 py-3">
                                                    <div className="relative ml-auto w-full max-w-[7rem]">
                                                        <span className="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-xs text-theme-ink-muted">
                                                            {moneySymbol()}
                                                        </span>
                                                        <input
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            value={item.discount}
                                                            onChange={(e) =>
                                                                setItem(index, 'discount', e.target.value)
                                                            }
                                                            className="h-10 w-full rounded-lg border border-theme-border bg-theme-surface py-2 pl-7 pr-2 text-right text-sm tabular-nums outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                                        />
                                                    </div>
                                                </td>
                                                <td className="px-3 py-3 text-right tabular-nums font-medium text-theme-ink">
                                                    {money(line.lineTotal)}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <button
                                                        type="button"
                                                        onClick={() => removeItem(index)}
                                                        className="inline-flex rounded-lg p-2 text-theme-danger hover:bg-theme-danger/10"
                                                        title="Remove"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
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

                    <div className="grid gap-5 lg:grid-cols-2">
                        <div className="rounded-lg border border-theme-border p-4">
                            <h3 className="text-sm font-semibold text-theme-ink">Payment</h3>
                            <div className="mt-3 grid gap-4 sm:grid-cols-2">
                                <Field label="Money source" error={form.errors.money_source_id}>
                                    <SearchableSelect
                                        options={moneySourceOptions}
                                        value={form.data.money_source_id || null}
                                        onChange={(val) =>
                                            form.setData('money_source_id', val ? String(val) : '')
                                        }
                                        placeholder="Select source"
                                        searchable={false}
                                    />
                                </Field>
                                <Field label="Amount paid" error={form.errors.paid_amount}>
                                    <div className="flex gap-2">
                                        <div className="relative min-w-0 flex-1">
                                            <span className="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-theme-ink-muted">
                                                {moneySymbol()}
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
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            className="shrink-0"
                                            onClick={payFullAmount}
                                        >
                                            Full
                                        </Button>
                                    </div>
                                </Field>
                            </div>
                            <div className="mt-3 flex flex-wrap items-center gap-2 text-sm">
                                <PaymentStatusBadge status={paymentStatus} />
                                {paymentStatus === 'pending' && allowCredit && !isWalkIn && (
                                    <span className="text-theme-ink-muted">
                                        Unpaid balance posts to customer account.
                                    </span>
                                )}
                                {paymentStatus === 'pending' && (isWalkIn || !allowCredit) && (
                                    <span className="text-theme-ink-muted">
                                        {isWalkIn
                                            ? 'Walk-in orders need full payment.'
                                            : 'Credit is disabled — collect payment or pick a customer.'}
                                    </span>
                                )}
                                {paymentStatus === 'partial' && (
                                    <span className="text-theme-ink-muted">
                                        Balance due {money(balanceDue)}.
                                    </span>
                                )}
                            </div>
                        </div>

                        <div className="ml-auto w-full space-y-2 border-t border-theme-border pt-1 text-sm lg:border-t-0 lg:pt-0">
                            <div className="flex items-center justify-between gap-4">
                                <span className="text-theme-ink-muted">Subtotal</span>
                                <span className="tabular-nums font-medium text-theme-ink">
                                    {money(subtotal)}
                                </span>
                            </div>
                            <div className="flex items-center justify-between gap-4">
                                <span className="text-theme-ink-muted">Tax</span>
                                <span className="tabular-nums font-medium text-theme-ink">
                                    {money(taxTotal)}
                                </span>
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
                                            {moneySymbol()}
                                        </button>
                                    </div>
                                    <div className="relative w-28">
                                        {discountMode === 'amount' && (
                                            <span className="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-theme-ink-muted">
                                                {moneySymbol()}
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
                                                discountMode === 'amount' ? 'pl-8 pr-2' : 'pl-2 pr-7'
                                            }`}
                                        />
                                    </div>
                                </div>
                            </div>
                            {discountMode === 'percent' && discountAmount > 0 && (
                                <p className="text-right text-xs text-theme-ink-muted">
                                    = {formatMoney(discountAmount)}
                                </p>
                            )}
                            {form.data.is_delivery && deliveryCharge > 0 && (
                                <div className="flex items-center justify-between gap-4">
                                    <span className="text-theme-ink-muted">Delivery</span>
                                    <span className="tabular-nums font-medium text-theme-ink">
                                        {money(deliveryCharge)}
                                    </span>
                                </div>
                            )}
                            <div className="flex items-center justify-between gap-4 border-t border-theme-border pt-2 text-base font-semibold text-theme-ink">
                                <span>Total</span>
                                <span className="tabular-nums">{money(total)}</span>
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
                    <Button type="submit" disabled={form.processing || form.data.items.length === 0}>
                        {form.processing ? 'Saving…' : 'Create order'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
