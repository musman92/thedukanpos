import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field, TextArea } from '@/Components/Ui/Input';
import SearchableSelect from '@/Components/Ui/SearchableSelect';
import { router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

function formatQty(value) {
    if (value === null || value === undefined || !Number.isFinite(Number(value))) {
        return '—';
    }
    const n = Number(value);
    if (Math.abs(n - Math.round(n)) < 0.0001) {
        return n.toLocaleString(undefined, { maximumFractionDigits: 0 });
    }
    return n.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 4,
    });
}

export default function AdjustmentFormDrawer({
    open,
    variants = [],
    adjustment = null,
    branch = null,
    onClose,
}) {
    const editing = !!adjustment;
    const form = useForm({
        variant_id: '',
        mode: 'change',
        unit: 'sale',
        quantity: '',
        notes: '',
    });

    const [stock, setStock] = useState(null);
    const [stockLoading, setStockLoading] = useState(false);
    const [stockError, setStockError] = useState('');
    const [submitError, setSubmitError] = useState('');

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();
        setSubmitError('');
        setStock(null);
        setStockError('');

        if (adjustment) {
            form.setData({
                variant_id: adjustment.variant_id ? String(adjustment.variant_id) : '',
                mode: 'change',
                unit: 'sale',
                quantity:
                    adjustment.signed_quantity != null
                        ? String(adjustment.signed_quantity)
                        : '',
                notes: adjustment.notes || '',
            });
        } else {
            form.setData({
                variant_id: '',
                mode: 'change',
                unit: 'sale',
                quantity: '',
                notes: '',
            });
        }

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, adjustment?.id]);

    const selectedVariant = useMemo(
        () => variants.find((v) => String(v.id) === String(form.data.variant_id)) || null,
        [variants, form.data.variant_id],
    );

    const hasDualUnits = !!(stock?.has_dual_units || selectedVariant?.has_dual_units);

    const saleUnit =
        stock?.sale_unit_name || selectedVariant?.sale_unit_name || 'pcs';
    const purchaseUnit =
        stock?.purchase_unit_name || selectedVariant?.purchase_unit_name || saleUnit;
    const conversionRate = Number(
        stock?.conversion_rate || selectedVariant?.conversion_rate || 1,
    ) || 1;

    const activeUnitLabel =
        form.data.unit === 'purchase' && hasDualUnits ? purchaseUnit : saleUnit;

    const fetchStock = async (variantId) => {
        setStockError('');
        if (!variantId) {
            setStock(null);
            return;
        }

        setStockLoading(true);
        try {
            const params = {
                variant_id: variantId,
            };
            if (editing && adjustment?.id) {
                params.exclude_adjustment_id = adjustment.id;
            }

            const response = await fetch(
                route('admin.inventory.adjustments.stock', params),
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                },
            );

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.message || 'Could not load current stock.');
            }

            const data = await response.json();
            setStock(data);
            if (!data.has_dual_units) {
                form.setData('unit', 'sale');
            }
        } catch (e) {
            setStock(null);
            setStockError(e.message || 'Could not load current stock.');
        } finally {
            setStockLoading(false);
        }
    };

    useEffect(() => {
        if (!open) {
            return undefined;
        }
        if (form.data.variant_id) {
            fetchStock(form.data.variant_id);
        } else {
            setStock(null);
        }
        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, form.data.variant_id, adjustment?.id]);

    const currentInActiveUnit = useMemo(() => {
        if (!stock) return null;
        if (form.data.unit === 'purchase' && hasDualUnits) {
            return Number(stock.quantity_purchase);
        }
        return Number(stock.quantity_sale);
    }, [stock, form.data.unit, hasDualUnits]);

    const inputAsSale = useMemo(() => {
        const raw = Number(form.data.quantity);
        if (!Number.isFinite(raw)) return null;
        if (form.data.unit === 'purchase' && hasDualUnits) {
            return raw * conversionRate;
        }
        return raw;
    }, [form.data.quantity, form.data.unit, hasDualUnits, conversionRate]);

    const previewDelta = useMemo(() => {
        if (inputAsSale === null || !stock) return null;
        if (form.data.mode === 'exact') {
            return inputAsSale - Number(stock.quantity_sale);
        }
        return inputAsSale;
    }, [inputAsSale, stock, form.data.mode]);

    const previewNewStock = useMemo(() => {
        if (!stock || previewDelta === null) return null;
        return Number(stock.quantity_sale) + previewDelta;
    }, [stock, previewDelta]);

    const formatWithUnit = (saleQty) => {
        if (saleQty === null || !Number.isFinite(Number(saleQty))) return '—';
        const sale = `${formatQty(saleQty)} ${saleUnit}`;
        if (hasDualUnits) {
            const purchase = Number(saleQty) / conversionRate;
            return `${sale} (${formatQty(purchase)} ${purchaseUnit})`;
        }
        return sale;
    };

    const onVariantChange = (value) => {
        form.setData('variant_id', value ? String(value) : '');
        setSubmitError('');
    };

    const submit = (e) => {
        e.preventDefault();
        setSubmitError('');

        if (!form.data.variant_id) {
            setSubmitError('Select a product from the list.');
            return;
        }

        const qty = Number(form.data.quantity);
        if (!Number.isFinite(qty)) {
            setSubmitError('Enter a valid quantity.');
            return;
        }
        if (form.data.mode === 'change' && Math.abs(qty) < 0.0001) {
            setSubmitError('Quantity change must be non-zero (use a negative value to decrease).');
            return;
        }
        if (form.data.mode === 'exact' && qty < 0) {
            setSubmitError('Exact quantity cannot be negative.');
            return;
        }
        if (previewDelta !== null && Math.abs(previewDelta) < 0.0001) {
            setSubmitError(
                form.data.mode === 'exact'
                    ? 'Exact quantity matches current stock — nothing to adjust.'
                    : 'Quantity change must be non-zero.',
            );
            return;
        }

        form.transform((data) => ({
            ...data,
            ...(editing ? { _method: 'put' } : {}),
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
            onFinish: () => form.transform((d) => d),
            onError: () => setSubmitError(''),
        };

        if (editing) {
            form.post(route('admin.inventory.adjustments.update', adjustment.id), options);
        } else {
            form.post(route('admin.inventory.adjustments.store'), options);
        }
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={editing ? 'Edit inventory adjustment' : 'New inventory adjustment'}
            description={
                editing
                    ? 'Update the quantity change or notes. Stock is recalculated from the revised change. Product cannot be changed.'
                    : 'Increase or decrease stock: change by a delta, or set the exact on-hand quantity. A stock movement is recorded with your notes.'
            }
            width="md"
            bodyClassName="overflow-y-auto flex flex-col"
        >
            <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
                <div className="space-y-5">
                    {branch?.name && (
                        <div>
                            <p className="text-sm font-medium text-theme-ink">Branch</p>
                            <p className="mt-0.5 text-sm text-theme-ink-soft">{branch.name}</p>
                        </div>
                    )}

                    {editing ? (
                        <div>
                            <p className="text-sm font-medium text-theme-ink">Product</p>
                            <p className="mt-0.5 text-sm text-theme-ink-soft">
                                {adjustment.variant?.name || '—'}
                            </p>
                        </div>
                    ) : (
                        <Field label="Product" required error={form.errors.variant_id}>
                            <SearchableSelect
                                options={variants}
                                value={form.data.variant_id || null}
                                onChange={onVariantChange}
                                placeholder="Search products…"
                                error={!!form.errors.variant_id}
                            />
                        </Field>
                    )}

                    {form.data.variant_id && (
                        <div className="rounded-lg border border-theme-border bg-theme-bg px-4 py-3">
                            <p className="text-xs font-medium uppercase tracking-wide text-theme-ink-muted">
                                {editing ? 'Stock before this adjustment' : 'Current stock'}
                            </p>
                            {stockLoading && (
                                <p className="mt-1 text-sm text-theme-ink-muted">Loading…</p>
                            )}
                            {!stockLoading && stockError && (
                                <p className="mt-1 text-sm text-theme-danger">{stockError}</p>
                            )}
                            {!stockLoading && !stockError && stock && (
                                <p className="mt-1 text-sm font-semibold text-theme-ink">
                                    {formatWithUnit(stock.quantity_sale)}
                                </p>
                            )}
                        </div>
                    )}

                    {!editing && (
                        <>
                            <fieldset className="space-y-2">
                                <legend className="text-sm font-medium text-theme-ink">
                                    How to adjust <span className="text-theme-danger">*</span>
                                </legend>
                                <div className="flex flex-wrap gap-4">
                                    <label className="inline-flex cursor-pointer items-center gap-2 text-sm text-theme-ink">
                                        <input
                                            type="radio"
                                            name="mode"
                                            value="change"
                                            checked={form.data.mode === 'change'}
                                            onChange={() => form.setData('mode', 'change')}
                                            className="border-theme-border text-theme-primary focus:ring-theme-primary/20"
                                        />
                                        Change stock
                                    </label>
                                    <label className="inline-flex cursor-pointer items-center gap-2 text-sm text-theme-ink">
                                        <input
                                            type="radio"
                                            name="mode"
                                            value="exact"
                                            checked={form.data.mode === 'exact'}
                                            onChange={() => form.setData('mode', 'exact')}
                                            className="border-theme-border text-theme-primary focus:ring-theme-primary/20"
                                        />
                                        Set exact quantity
                                    </label>
                                </div>
                                <p className="text-xs text-theme-ink-muted">
                                    {form.data.mode === 'change'
                                        ? 'Enter how much to add (positive) or remove (negative).'
                                        : 'Enter the final on-hand quantity after this adjustment.'}
                                </p>
                            </fieldset>

                            {hasDualUnits && (
                                <fieldset className="space-y-2">
                                    <legend className="text-sm font-medium text-theme-ink">
                                        Unit <span className="text-theme-danger">*</span>
                                    </legend>
                                    <div className="flex flex-wrap gap-4">
                                        <label className="inline-flex cursor-pointer items-center gap-2 text-sm text-theme-ink">
                                            <input
                                                type="radio"
                                                name="unit"
                                                value="sale"
                                                checked={form.data.unit === 'sale'}
                                                onChange={() => form.setData('unit', 'sale')}
                                                className="border-theme-border text-theme-primary focus:ring-theme-primary/20"
                                            />
                                            {saleUnit}
                                            <span className="text-xs text-theme-ink-muted">
                                                (sale)
                                            </span>
                                        </label>
                                        <label className="inline-flex cursor-pointer items-center gap-2 text-sm text-theme-ink">
                                            <input
                                                type="radio"
                                                name="unit"
                                                value="purchase"
                                                checked={form.data.unit === 'purchase'}
                                                onChange={() => form.setData('unit', 'purchase')}
                                                className="border-theme-border text-theme-primary focus:ring-theme-primary/20"
                                            />
                                            {purchaseUnit}
                                            <span className="text-xs text-theme-ink-muted">
                                                (purchase)
                                            </span>
                                        </label>
                                    </div>
                                </fieldset>
                            )}
                        </>
                    )}

                    <Field
                        label={
                            form.data.mode === 'change' || editing
                                ? `Quantity change (${activeUnitLabel})`
                                : `Exact quantity (${activeUnitLabel})`
                        }
                        required
                        error={form.errors.quantity}
                        hint={
                            form.data.mode === 'change' || editing
                                ? 'Positive adds stock; negative removes stock.'
                                : currentInActiveUnit !== null
                                  ? `Current in this unit: ${formatQty(currentInActiveUnit)} ${activeUnitLabel}`
                                  : undefined
                        }
                    >
                        <Input
                            type="number"
                            step="0.01"
                            min={form.data.mode === 'exact' && !editing ? '0' : undefined}
                            value={form.data.quantity}
                            onChange={(e) => {
                                form.setData('quantity', e.target.value);
                                setSubmitError('');
                            }}
                            placeholder={
                                form.data.mode === 'change' || editing
                                    ? 'e.g. 3 or -1'
                                    : 'e.g. 10'
                            }
                        />
                    </Field>

                    {stock && previewDelta !== null && Number.isFinite(previewDelta) && (
                        <div className="space-y-1 rounded-lg border border-theme-primary/20 bg-theme-primary/5 px-4 py-3">
                            <p className="text-xs font-medium uppercase tracking-wide text-theme-primary">
                                Preview
                            </p>
                            <p className="text-sm text-theme-ink">
                                Change:{' '}
                                <span className="font-semibold">
                                    {(previewDelta >= 0 ? '+' : '') +
                                        formatWithUnit(previewDelta)}
                                </span>
                            </p>
                            <p className="text-sm text-theme-ink">
                                New stock:{' '}
                                <span
                                    className={`font-semibold ${
                                        previewNewStock < -0.0001
                                            ? 'text-theme-danger'
                                            : 'text-theme-ink'
                                    }`}
                                >
                                    {formatWithUnit(previewNewStock)}
                                </span>
                            </p>
                        </div>
                    )}

                    <Field label="Notes" required error={form.errors.notes}>
                        <TextArea
                            rows={3}
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            placeholder="Reason for this adjustment"
                        />
                    </Field>

                    {(submitError || form.errors.variant_id) && (
                        <p className="text-sm text-theme-danger">
                            {submitError || form.errors.variant_id}
                        </p>
                    )}
                </div>

                <div className="mt-auto flex justify-end gap-2 border-t border-theme-border pt-5">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {editing ? 'Update adjustment' : 'Save adjustment'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
