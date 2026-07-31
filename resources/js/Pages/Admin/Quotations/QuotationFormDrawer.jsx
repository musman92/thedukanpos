import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field, TextArea } from '@/Components/Ui/Input';
import SearchableSelect from '@/Components/Ui/SearchableSelect';
import { useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

const selectClass =
    'h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

const STATUSES = [
    { value: 'draft', label: 'Draft' },
    { value: 'sent', label: 'Sent' },
    { value: 'accepted', label: 'Accepted' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'expired', label: 'Expired' },
    { value: 'converted', label: 'Converted' },
];

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

const emptyData = () => ({
    customer_id: '',
    number: '',
    quote_date: localToday(),
    valid_until: '',
    status: 'draft',
    discount_total: '0',
    notes: '',
    items: [],
});

function dataFromQuotation(quotation) {
    if (!quotation) return emptyData();

    return {
        customer_id: quotation.customer_id ? String(quotation.customer_id) : '',
        number: quotation.number || '',
        quote_date: quotation.quote_date || localToday(),
        valid_until: quotation.valid_until || '',
        status: quotation.status || 'draft',
        discount_total: String(quotation.discount_total ?? 0),
        notes: quotation.notes || '',
        items: (quotation.items || []).map((item) => ({
            variant_id: String(item.variant_id),
            unit_id: String(item.unit_id),
            quantity: item.quantity ?? '',
            unit_price: item.unit_price ?? '',
            discount: item.discount ?? '0',
            display_name: item.display_name || '—',
            short_code: item.short_code || '',
            sale_unit_label: item.sale_unit_label || '—',
            tax: item.tax || null,
        })),
    };
}

export default function QuotationFormDrawer({
    open,
    quotation = null,
    customers = [],
    variants = [],
    onClose,
}) {
    const editing = !!quotation?.id;
    const form = useForm(emptyData());
    const [pickerKey, setPickerKey] = useState(0);
    const [discountMode, setDiscountMode] = useState('amount');
    const [discountInput, setDiscountInput] = useState('0');

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();
        const next = dataFromQuotation(quotation);
        form.setData(next);
        setPickerKey((k) => k + 1);
        setDiscountMode('amount');
        setDiscountInput(String(next.discount_total || '0'));

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, quotation?.id]);

    const customerOptions = useMemo(
        () =>
            customers.map((c) => ({
                value: c.id,
                label: c.name,
                meta: [c.phone, c.balance != null ? `bal ${money(c.balance)}` : '']
                    .filter(Boolean)
                    .join(' · '),
            })),
        [customers],
    );

    const catalogOptions = useMemo(
        () =>
            variants.map((v) => ({
                value: v.id,
                label: `${v.short_code ? `${v.short_code} — ` : ''}${v.label}`,
                meta: [
                    v.sale_unit?.name || v.sale_unit?.code,
                    v.sale_price != null ? `price ${v.sale_price}` : '',
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
        form.setData('discount_total', discountAmount.toFixed(2));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [discountAmount]);

    const total = Math.max(0, subtotal + taxTotal - discountAmount);

    const addFromCatalog = (variantId) => {
        if (variantId === null || variantId === '') return;

        const variant = variants.find((v) => String(v.id) === String(variantId));
        if (!variant) return;

        const unitId = variant.sale_unit_id || variant.purchase_unit_id;
        const suggested =
            variant.sale_price != null && Number(variant.sale_price) > 0
                ? String(Number(variant.sale_price).toFixed(2))
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
    };

    const switchDiscountMode = (mode) => {
        if (mode === discountMode) return;
        const base = subtotal + taxTotal;
        if (mode === 'percent') {
            const pct = base > 0 ? ((Number(discountInput || 0) / base) * 100).toFixed(2) : '0';
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
            customer_id: data.customer_id || null,
            number: data.number?.trim() || null,
            quote_date: data.quote_date,
            valid_until: data.valid_until || null,
            status: data.status,
            discount_total: discountAmount.toFixed(2),
            notes: data.notes,
            items: data.items.map((item) => ({
                variant_id: item.variant_id,
                unit_id: item.unit_id,
                quantity: item.quantity,
                unit_price: item.unit_price,
                discount: item.discount || 0,
            })),
        }));
        const url = editing
            ? route('admin.quotations.update', quotation.id)
            : route('admin.quotations.store');
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
            title={editing ? 'Edit quotation' : 'New quotation'}
            description={
                editing
                    ? 'Update quote lines, customer, and validity.'
                    : 'Build a price quote for a customer — no stock impact.'
            }
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
                                onChange={(val) =>
                                    form.setData('customer_id', val ? String(val) : '')
                                }
                                placeholder="Select customer (optional)"
                                searchable
                            />
                        </Field>
                        <Field label="Quote date" required error={form.errors.quote_date}>
                            <Input
                                type="date"
                                value={form.data.quote_date}
                                onChange={(e) => form.setData('quote_date', e.target.value)}
                                error={!!form.errors.quote_date}
                            />
                        </Field>
                        <Field label="Valid until" error={form.errors.valid_until}>
                            <Input
                                type="date"
                                value={form.data.valid_until}
                                onChange={(e) => form.setData('valid_until', e.target.value)}
                                error={!!form.errors.valid_until}
                            />
                        </Field>
                        <Field label="Status" error={form.errors.status}>
                            <select
                                value={form.data.status}
                                onChange={(e) => form.setData('status', e.target.value)}
                                className={selectClass}
                            >
                                {STATUSES.map((s) => (
                                    <option key={s.value} value={s.value}>
                                        {s.label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field
                            label="Quote no"
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
                            <h3 className="text-base font-semibold text-theme-ink">Line items</h3>
                            <p className="mt-1 text-sm text-theme-ink-muted">
                                Search and add products — quantities and prices only, no stock
                                changes.
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
                                            Discount
                                        </th>
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
                                                colSpan={7}
                                                className="px-6 py-10 text-center text-sm text-theme-ink-muted"
                                            >
                                                No items yet. Use the search box above to add
                                                products.
                                            </td>
                                        </tr>
                                    )}
                                    {form.data.items.map((item, index) => {
                                        const line = lineTotals[index] || {
                                            lineTotal: 0,
                                        };

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
                                                    {item.short_code && (
                                                        <span className="text-xs text-theme-ink-muted">
                                                            {item.short_code}
                                                        </span>
                                                    )}
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
                                                            {item.sale_unit_label}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-3 py-3">
                                                    <div className="relative ml-auto w-full max-w-[7rem]">
                                                        <span className="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-xs text-theme-ink-muted">
                                                            Rs
                                                        </span>
                                                        <input
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            value={item.discount}
                                                            onChange={(e) =>
                                                                setItem(
                                                                    index,
                                                                    'discount',
                                                                    e.target.value,
                                                                )
                                                            }
                                                            className="h-10 w-full rounded-lg border border-theme-border bg-theme-surface py-2 pl-7 pr-2 text-right text-sm tabular-nums outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                                        />
                                                    </div>
                                                </td>
                                                <td className="px-3 py-3 text-right tabular-nums font-medium text-theme-ink">
                                                    {money(line.lineTotal)}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <div className="flex items-center justify-end">
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

                    <div className="ml-auto w-full space-y-2 border-t border-theme-border pt-5 text-sm md:max-w-sm">
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
                        {editing ? 'Save changes' : 'Save quotation'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
