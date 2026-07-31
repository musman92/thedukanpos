import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field, TextArea } from '@/Components/Ui/Input';
import SearchableSelect from '@/Components/Ui/SearchableSelect';
import { useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
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

const emptyData = () => ({
    to_branch_id: '',
    notes: '',
    items: [],
});

export default function TransferFormDrawer({
    open,
    branches = [],
    variants = [],
    branch = null,
    onClose,
}) {
    const form = useForm(emptyData());
    const [pickerKey, setPickerKey] = useState(0);
    const [submitError, setSubmitError] = useState('');

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();
        setSubmitError('');
        form.setData(emptyData());
        setPickerKey((k) => k + 1);

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const selectedIds = useMemo(
        () => new Set(form.data.items.map((item) => String(item.variant_id))),
        [form.data.items],
    );

    const catalogOptions = useMemo(
        () =>
            variants
                .filter((v) => !selectedIds.has(String(v.id)))
                .map((v) => ({
                    value: v.id,
                    label: v.label || v.short_code || `Variant #${v.id}`,
                    meta: [
                        v.short_code,
                        v.quantity_on_hand != null
                            ? `on hand ${formatQty(v.quantity_on_hand)} ${v.sale_unit_name || 'pcs'}`
                            : null,
                    ]
                        .filter(Boolean)
                        .join(' · '),
                })),
        [variants, selectedIds],
    );

    const branchOptions = useMemo(
        () =>
            branches.map((b) => ({
                value: b.id,
                label: b.name,
            })),
        [branches],
    );

    const addFromCatalog = (variantId) => {
        if (variantId === null || variantId === '') return;

        const variant = variants.find((v) => String(v.id) === String(variantId));
        if (!variant) return;

        if (selectedIds.has(String(variant.id))) {
            setSubmitError('This product is already on the transfer.');
            setPickerKey((k) => k + 1);
            return;
        }

        form.setData('items', [
            ...form.data.items,
            {
                variant_id: String(variant.id),
                quantity: '',
                display_name: variant.label,
                short_code: variant.short_code || '',
                sale_unit_name: variant.sale_unit_name || 'pcs',
                quantity_on_hand: Number(variant.quantity_on_hand || 0),
            },
        ]);
        setSubmitError('');
        setPickerKey((k) => k + 1);
    };

    const setItem = (index, key, value) => {
        form.setData(
            'items',
            form.data.items.map((item, i) =>
                i === index ? { ...item, [key]: value } : item,
            ),
        );
        setSubmitError('');
    };

    const removeItem = (index) => {
        form.setData(
            'items',
            form.data.items.filter((_, i) => i !== index),
        );
    };

    const submit = (e) => {
        e.preventDefault();
        setSubmitError('');

        if (!form.data.to_branch_id) {
            setSubmitError('Select a destination branch.');
            return;
        }

        if (form.data.items.length === 0) {
            setSubmitError('Add at least one item to transfer.');
            return;
        }

        for (const item of form.data.items) {
            const qty = Number(item.quantity);
            if (!Number.isFinite(qty) || qty < 0.0001) {
                setSubmitError(`Enter a valid quantity for ${item.display_name || 'each item'}.`);
                return;
            }
            if (qty - Number(item.quantity_on_hand || 0) > 0.0001) {
                setSubmitError(
                    `Not enough stock for ${item.display_name}. Available: ${formatQty(item.quantity_on_hand)} ${item.sale_unit_name}.`,
                );
                return;
            }
        }

        form.transform((data) => ({
            to_branch_id: data.to_branch_id,
            notes: data.notes || null,
            items: data.items.map((item) => ({
                variant_id: Number(item.variant_id),
                quantity: Number(item.quantity),
            })),
        }));

        form.post(route('admin.inventory.transfers.store'), {
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
            title="New stock transfer"
            description="Move stock from this branch to another. Search and add multiple products, then set quantities."
            width="wide"
            bodyClassName="overflow-y-auto flex flex-col"
        >
            <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
                <div className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p className="text-sm font-medium text-theme-ink">From</p>
                            <p className="mt-0.5 text-sm text-theme-ink-soft">
                                {branch?.name || 'Current branch'}
                            </p>
                        </div>
                        <Field
                            label="Destination branch"
                            required
                            error={form.errors.to_branch_id}
                        >
                            <SearchableSelect
                                options={branchOptions}
                                value={form.data.to_branch_id || null}
                                onChange={(value) => {
                                    form.setData('to_branch_id', value ? String(value) : '');
                                    setSubmitError('');
                                }}
                                placeholder="Select branch…"
                                error={!!form.errors.to_branch_id}
                            />
                        </Field>
                    </div>

                    <div className="space-y-3">
                        <div className="border-b border-theme-border pb-2">
                            <h3 className="text-base font-semibold text-theme-ink">Items</h3>
                            <p className="mt-1 text-sm text-theme-ink-muted">
                                Search and select products — you can add more than one.
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
                                            On hand
                                        </th>
                                        <th className="w-44 px-3 py-3 font-semibold">Quantity</th>
                                        <th className="w-12 px-3 py-3" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {form.data.items.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="px-6 py-10 text-center text-sm text-theme-ink-muted"
                                            >
                                                No items yet. Use the search box above to add
                                                products.
                                            </td>
                                        </tr>
                                    )}
                                    {form.data.items.map((item, index) => (
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
                                                    <p className="mt-0.5 font-mono text-xs text-theme-ink-muted">
                                                        {item.short_code}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-3 py-3 text-right tabular-nums text-theme-ink-soft">
                                                {formatQty(item.quantity_on_hand)}{' '}
                                                <span className="text-xs text-theme-ink-muted">
                                                    {item.sale_unit_name}
                                                </span>
                                            </td>
                                            <td className="px-3 py-3">
                                                <div className="flex items-center gap-2">
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        min="0.01"
                                                        required
                                                        value={item.quantity}
                                                        onChange={(e) =>
                                                            setItem(
                                                                index,
                                                                'quantity',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="text-right tabular-nums"
                                                        placeholder="0"
                                                    />
                                                    <span className="shrink-0 text-xs text-theme-ink-muted">
                                                        {item.sale_unit_name}
                                                    </span>
                                                </div>
                                                {form.errors[`items.${index}.quantity`] && (
                                                    <p className="mt-1 text-xs text-theme-danger">
                                                        {form.errors[`items.${index}.quantity`]}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-3 py-3 text-right">
                                                <button
                                                    type="button"
                                                    title="Remove"
                                                    aria-label="Remove"
                                                    onClick={() => removeItem(index)}
                                                    className="inline-flex rounded-md p-1.5 text-theme-danger hover:bg-theme-danger/10"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {form.errors.items && (
                            <p className="text-sm text-theme-danger">{form.errors.items}</p>
                        )}
                    </div>

                    <Field label="Notes" error={form.errors.notes}>
                        <TextArea
                            rows={3}
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            placeholder="Optional reason for this transfer"
                        />
                    </Field>

                    {submitError && (
                        <p className="text-sm text-theme-danger">{submitError}</p>
                    )}
                </div>

                <div className="mt-auto flex justify-end gap-2 border-t border-theme-border pt-5">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        Transfer stock
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
