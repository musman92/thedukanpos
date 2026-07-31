import AdminLayout from '@/Layouts/AdminLayout';
import BarcodeField from '@/Components/Ui/BarcodeField';
import Button from '@/Components/Ui/Button';
import ImageUploadField from '@/Components/Ui/ImageUploadField';
import Input, { Field } from '@/Components/Ui/Input';
import SearchableSelect from '@/Components/Ui/SearchableSelect';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function today() {
    return new Date().toISOString().slice(0, 10);
}

function emptyVariant(units, option = null) {
    const pcs = units.find((u) => u.code === 'pcs');
    const ctn = units.find((u) => u.code === 'ctn');
    return {
        id: null,
        variation_option_id: option?.id || null,
        name: option?.name || '',
        short_code: option?.code || '',
        barcode: '',
        purchase_unit_id: ctn?.id || units[0]?.id || '',
        sale_unit_id: pcs?.id || units[0]?.id || '',
        conversion_rate: 1,
        sale_price: 0,
        cost_per_unit: 0,
        section_id: '',
        rack_id: '',
        is_active: true,
        track_serial: false,
        opening_stock: '',
        opening_stock_date: today(),
    };
}

function revokePreview(url) {
    if (url?.startsWith('blob:')) {
        URL.revokeObjectURL(url);
    }
}

export default function Form({ product, options, branchId }) {
    const editing = !!product;
    const initialType = product?.type || (product?.variants?.length > 1 ? 'variant' : 'single');
    const units = options.units || [];

    const firstVariant = product?.variants?.[0];
    const firstLoc = firstVariant?.locations?.[0];

    const [previewUrl, setPreviewUrl] = useState(product?.image_url || null);

    const { data, setData, post, processing, errors, clearErrors, transform } = useForm({
        type: initialType,
        name: product?.name || '',
        short_code: product?.short_code || '',
        brand_id: product?.brand_id || '',
        category_id: product?.category_id || '',
        variation_id: product?.variation_id || '',
        tax_id: product?.tax_id || '',
        min_qty_alert: product?.min_qty_alert ?? '',
        barcode: product?.barcode || firstVariant?.barcode || '',
        purchase_unit_id:
            product?.purchase_unit_id ||
            firstVariant?.purchase_unit_id ||
            units.find((u) => u.code === 'ctn')?.id ||
            units[0]?.id ||
            '',
        sale_unit_id:
            product?.sale_unit_id ||
            firstVariant?.sale_unit_id ||
            units.find((u) => u.code === 'pcs')?.id ||
            units[0]?.id ||
            '',
        conversion_rate: product?.conversion_rate ?? firstVariant?.conversion_rate ?? 1,
        sale_price: product?.sale_price ?? firstVariant?.sale_price ?? 0,
        cost_per_unit: product?.cost_per_unit ?? firstVariant?.cost_per_unit ?? 0,
        opening_stock: '',
        opening_stock_date: today(),
        section_id: firstLoc?.section_id || '',
        rack_id: firstLoc?.rack_id || '',
        track_stock: product?.track_stock ?? true,
        track_serial: !!firstVariant?.track_serial,
        is_active: product?.is_active ?? true,
        notes: product?.notes || '',
        image: null,
        remove_image: false,
        variants:
            initialType === 'variant' && product?.variants?.length
                ? product.variants.map((v) => {
                      const loc = v.locations?.[0];
                      return {
                          id: v.id,
                          variation_option_id: v.variation_option_id || null,
                          name: v.name || v.variation_option?.name || '',
                          short_code: v.short_code || '',
                          barcode: v.barcode || '',
                          purchase_unit_id: v.purchase_unit_id,
                          sale_unit_id: v.sale_unit_id,
                          conversion_rate: v.conversion_rate,
                          sale_price: v.sale_price,
                          cost_per_unit: v.cost_per_unit ?? 0,
                          section_id: loc?.section_id || '',
                          rack_id: loc?.rack_id || '',
                          is_active: v.is_active,
                          track_serial: !!v.track_serial,
                          opening_stock: '',
                          opening_stock_date: today(),
                      };
                  })
                : [],
    });

    useEffect(() => {
        return () => revokePreview(previewUrl);
    }, [previewUrl]);

    const brandOptions = (options.brands || []).map((b) => ({ value: b.id, label: b.name }));
    const categoryOptions = (options.categories || []).map((c) => ({
        value: c.id,
        label: c.name,
        meta: c.default_tax_id ? `tax:${c.default_tax_id}` : '',
    }));
    const taxOptions = (options.taxes || []).map((t) => ({
        value: t.id,
        label: `${t.name} (${t.rate}%)`,
    }));
    const unitOptions = units.map((u) => ({ value: u.id, label: `${u.name} (${u.code})` }));
    const sectionOptions = (options.sections || []).map((s) => ({ value: s.id, label: s.name }));
    const variationOptions = (options.variations || []).map((v) => ({
        value: v.id,
        label: v.name,
        meta: v.code || '',
    }));
    const selectedVariation =
        (options.variations || []).find((v) => String(v.id) === String(data.variation_id)) || null;
    const masterOptions = selectedVariation?.options || [];

    const setVariant = (index, patch) => {
        setData(
            'variants',
            data.variants.map((v, i) => (i === index ? { ...v, ...patch } : v)),
        );
    };

    const toggleHasVariants = (checked) => {
        if (editing) return;
        const type = checked ? 'variant' : 'single';
        if (type === data.type) return;
        setData({
            ...data,
            type,
            variation_id: '',
            variants: [],
        });
    };

    const changeVariation = (variationId) => {
        if (editing) return;
        setData({
            ...data,
            variation_id: variationId || '',
            variants: [],
        });
    };

    const isOptionSelected = (optionId) =>
        data.variants.some((v) => String(v.variation_option_id) === String(optionId));

    const toggleOption = (option) => {
        if (isOptionSelected(option.id)) {
            const next = data.variants.filter(
                (v) => String(v.variation_option_id) !== String(option.id),
            );
            setData('variants', next);
            return;
        }

        const template = data.variants[0];
        const row = emptyVariant(units, option);
        if (template) {
            row.purchase_unit_id = template.purchase_unit_id;
            row.sale_unit_id = template.sale_unit_id;
            row.conversion_rate = template.conversion_rate;
            row.section_id = template.section_id;
            row.rack_id = template.rack_id;
        }
        setData('variants', [...data.variants, row]);
    };

    const onSelectImage = (file) => {
        setPreviewUrl((prev) => {
            revokePreview(prev);
            return URL.createObjectURL(file);
        });
        setData((current) => ({
            ...current,
            image: file,
            remove_image: false,
        }));
        clearErrors('image');
    };

    const onClearImage = () => {
        setPreviewUrl((prev) => {
            revokePreview(prev);
            return null;
        });
        setData((current) => ({
            ...current,
            image: null,
            remove_image: !!product?.image_url,
        }));
        clearErrors('image');
    };

    const submit = (e) => {
        e.preventDefault();
        // transform() does not chain — call then post separately (multipart needs POST).
        transform((formData) => ({
            ...formData,
            ...(editing ? { _method: 'put' } : {}),
        }));
        post(editing ? route('admin.products.update', product.id) : route('admin.products.store'), {
            forceFormData: true,
        });
    };

    return (
        <AdminLayout
            title={editing ? 'Edit product' : 'New product'}
            description={
                branchId
                    ? 'Locations and opening stock apply to the current branch.'
                    : 'Create a catalog product.'
            }
            actions={
                <Link href={route('admin.products.index')}>
                    <Button variant="secondary">Back to list</Button>
                </Link>
            }
        >
            <Head title={editing ? 'Edit product' : 'New product'} />

            <form onSubmit={submit} className="mx-auto max-w-4xl space-y-6">
                <div className="space-y-4 rounded-xl border border-theme-border bg-theme-surface p-5">
                    <p className="text-xs font-semibold uppercase tracking-wide text-theme-ink-muted">
                        Basic info
                    </p>

                    <ImageUploadField
                        label="Product image"
                        previewUrl={previewUrl}
                        error={errors.image}
                        onSelect={onSelectImage}
                        onClear={onClearImage}
                        resetKey={product?.id ?? 'new'}
                    />

                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Name" required error={errors.name} className="md:col-span-2">
                            <Input
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Pepsi"
                                error={!!errors.name}
                            />
                        </Field>
                        <Field
                            label="Code"
                            error={errors.short_code}
                            hint="Optional — blank auto-assigns P01, P02…"
                        >
                            <Input
                                value={data.short_code}
                                onChange={(e) => setData('short_code', e.target.value)}
                                placeholder="Auto"
                                error={!!errors.short_code}
                            />
                        </Field>
                        <Field label="Tax" error={errors.tax_id}>
                            <SearchableSelect
                                options={[{ value: '', label: 'Exempt / none' }, ...taxOptions]}
                                value={data.tax_id}
                                onChange={(v) => setData('tax_id', v || '')}
                                placeholder="Tax"
                                error={!!errors.tax_id}
                            />
                        </Field>
                        <Field label="Brand" error={errors.brand_id}>
                            <SearchableSelect
                                options={[{ value: '', label: '— None —' }, ...brandOptions]}
                                value={data.brand_id}
                                onChange={(v) => setData('brand_id', v || '')}
                                placeholder="Brand"
                            />
                        </Field>
                        <Field label="Category" error={errors.category_id}>
                            <SearchableSelect
                                options={[{ value: '', label: '— None —' }, ...categoryOptions]}
                                value={data.category_id}
                                onChange={(v) => {
                                    const cat = options.categories.find(
                                        (c) => String(c.id) === String(v),
                                    );
                                    setData({
                                        ...data,
                                        category_id: v || '',
                                        tax_id: cat?.default_tax_id || data.tax_id,
                                    });
                                }}
                                placeholder="Category"
                            />
                        </Field>
                        <Field
                            label="Min qty alert"
                            error={errors.min_qty_alert}
                            hint="Low-stock warning threshold (sale units)."
                        >
                            <Input
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.min_qty_alert}
                                onChange={(e) => setData('min_qty_alert', e.target.value)}
                                error={!!errors.min_qty_alert}
                            />
                        </Field>

                        <div className="flex flex-col gap-3 md:col-span-2">
                            <label className="flex items-start gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    className="mt-0.5"
                                    checked={data.type === 'variant'}
                                    disabled={editing}
                                    onChange={(e) => toggleHasVariants(e.target.checked)}
                                />
                                <span>
                                    <span className="font-medium text-theme-ink">Has variants</span>
                                    <span className="mt-0.5 block text-xs text-theme-ink-muted">
                                        {editing
                                            ? 'Type cannot be changed after create.'
                                            : 'Uses options from Catalog → Variations (Size, Color, etc.). Leave unchecked for a single SKU.'}
                                    </span>
                                </span>
                            </label>
                            <div className="flex flex-wrap gap-4">
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={!!data.is_active}
                                        onChange={(e) => setData('is_active', e.target.checked)}
                                    />
                                    Active
                                </label>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={!!data.track_stock}
                                        onChange={(e) => setData('track_stock', e.target.checked)}
                                    />
                                    Track stock
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {data.type === 'single' && (
                    <div className="space-y-4 rounded-xl border border-theme-border bg-theme-surface p-5">
                        <p className="text-xs font-semibold uppercase tracking-wide text-theme-ink-muted">
                            Units, price & barcode
                        </p>
                        <div className="grid gap-4 md:grid-cols-3">
                            <Field label="Purchase unit" required error={errors.purchase_unit_id}>
                                <SearchableSelect
                                    options={unitOptions}
                                    value={data.purchase_unit_id}
                                    onChange={(v) => setData('purchase_unit_id', v)}
                                    error={!!errors.purchase_unit_id}
                                />
                            </Field>
                            <Field label="Sale unit" required error={errors.sale_unit_id}>
                                <SearchableSelect
                                    options={unitOptions}
                                    value={data.sale_unit_id}
                                    onChange={(v) => setData('sale_unit_id', v)}
                                    error={!!errors.sale_unit_id}
                                />
                            </Field>
                            <Field
                                label="Conversion"
                                required
                                error={errors.conversion_rate}
                                hint="1 purchase unit = ? sale units"
                            >
                                <Input
                                    type="number"
                                    step="0.0001"
                                    min="0.0001"
                                    value={data.conversion_rate}
                                    onChange={(e) => setData('conversion_rate', e.target.value)}
                                    error={!!errors.conversion_rate}
                                />
                            </Field>
                            <div className="md:col-span-3">
                                <BarcodeField
                                    value={data.barcode}
                                    onChange={(v) => setData('barcode', v)}
                                    error={errors.barcode}
                                />
                            </div>
                            <Field label="Sale price" required error={errors.sale_price}>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.sale_price}
                                    onChange={(e) => setData('sale_price', e.target.value)}
                                    error={!!errors.sale_price}
                                />
                            </Field>
                            <Field
                                label="Purchase price"
                                error={errors.cost_per_unit}
                                hint="Cost per sale unit"
                            >
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.cost_per_unit}
                                    onChange={(e) => setData('cost_per_unit', e.target.value)}
                                    error={!!errors.cost_per_unit}
                                />
                            </Field>
                            <Field label="Section" error={errors.section_id}>
                                <SearchableSelect
                                    options={[{ value: '', label: '—' }, ...sectionOptions]}
                                    value={data.section_id}
                                    onChange={(v) =>
                                        setData({ ...data, section_id: v || '', rack_id: '' })
                                    }
                                />
                            </Field>
                            <Field label="Rack" error={errors.rack_id}>
                                <SearchableSelect
                                    options={[
                                        { value: '', label: '—' },
                                        ...(
                                            options.sections.find(
                                                (s) => String(s.id) === String(data.section_id),
                                            )?.racks || []
                                        ).map((r) => ({ value: r.id, label: r.name })),
                                    ]}
                                    value={data.rack_id}
                                    onChange={(v) => setData('rack_id', v || '')}
                                />
                            </Field>
                            <div className="flex items-end pb-2">
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={!!data.track_serial}
                                        onChange={(e) => setData('track_serial', e.target.checked)}
                                    />
                                    Track serial / IMEI
                                </label>
                            </div>
                        </div>

                        {!editing && (
                            <div className="grid gap-4 border-t border-theme-border pt-4 md:grid-cols-2">
                                <Field
                                    label="Opening stock"
                                    error={errors.opening_stock}
                                    hint="Quantity in sale units for this branch."
                                >
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={data.opening_stock}
                                        onChange={(e) => setData('opening_stock', e.target.value)}
                                    />
                                </Field>
                                <Field label="Opening stock date" error={errors.opening_stock_date}>
                                    <Input
                                        type="date"
                                        value={data.opening_stock_date}
                                        onChange={(e) => setData('opening_stock_date', e.target.value)}
                                    />
                                </Field>
                            </div>
                        )}
                    </div>
                )}

                {data.type === 'variant' && (
                    <div className="space-y-4">
                        <div className="space-y-4 rounded-xl border border-theme-border bg-theme-surface p-5">
                            <div>
                                <h2 className="text-lg font-semibold text-theme-ink">Variation</h2>
                                <p className="text-sm text-theme-ink-muted">
                                    Choose a variation from Catalog → Variations, then pick which options apply.
                                </p>
                            </div>
                            <Field label="Variation type" required error={errors.variation_id}>
                                <SearchableSelect
                                    options={variationOptions}
                                    value={data.variation_id}
                                    onChange={changeVariation}
                                    placeholder="e.g. Size, Color"
                                    disabled={editing}
                                    error={!!errors.variation_id}
                                />
                            </Field>

                            {selectedVariation && (
                                <div>
                                    <p className="mb-2 text-sm font-medium text-theme-ink">
                                        Options{' '}
                                        <span className="font-normal text-theme-ink-muted">
                                            (select some or all)
                                        </span>
                                    </p>
                                    {masterOptions.length === 0 ? (
                                        <p className="text-sm text-theme-ink-muted">
                                            No active options on this variation. Add them under Variations.
                                        </p>
                                    ) : (
                                        <div className="flex flex-wrap gap-3">
                                            {masterOptions.map((opt) => (
                                                <label
                                                    key={opt.id}
                                                    className="flex items-center gap-2 rounded-lg border border-theme-border px-3 py-2 text-sm"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={isOptionSelected(opt.id)}
                                                        onChange={() => toggleOption(opt)}
                                                    />
                                                    <span>
                                                        {opt.name}
                                                        {opt.code ? (
                                                            <span className="ml-1 text-xs text-theme-ink-muted">
                                                                ({opt.code})
                                                            </span>
                                                        ) : null}
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>

                        {data.variants.length > 0 && (
                            <div className="space-y-3">
                                <div>
                                    <h2 className="text-lg font-semibold text-theme-ink">
                                        Option values
                                    </h2>
                                    <p className="text-sm text-theme-ink-muted">
                                        Fill required fields for each selected option.
                                    </p>
                                </div>

                                {data.variants.map((variant, index) => {
                                    const racks =
                                        options.sections.find(
                                            (s) => String(s.id) === String(variant.section_id),
                                        )?.racks || [];
                                    const optionLabel =
                                        masterOptions.find(
                                            (o) =>
                                                String(o.id) ===
                                                String(variant.variation_option_id),
                                        )?.name ||
                                        variant.name ||
                                        `Option #${index + 1}`;

                                    return (
                                        <div
                                            key={variant.variation_option_id || variant.id || index}
                                            className="space-y-4 rounded-xl border border-theme-border bg-theme-surface p-5"
                                        >
                                            <p className="text-sm font-semibold text-theme-ink">
                                                {optionLabel}
                                            </p>
                                            <div className="grid gap-4 md:grid-cols-3">
                                                <Field
                                                    label="Code"
                                                    error={errors[`variants.${index}.short_code`]}
                                                    hint="Optional — auto if blank"
                                                >
                                                    <Input
                                                        value={variant.short_code}
                                                        onChange={(e) =>
                                                            setVariant(index, {
                                                                short_code: e.target.value,
                                                            })
                                                        }
                                                        placeholder="Auto"
                                                    />
                                                </Field>
                                                <div className="md:col-span-2">
                                                    <BarcodeField
                                                        value={variant.barcode}
                                                        onChange={(v) =>
                                                            setVariant(index, { barcode: v })
                                                        }
                                                        error={errors[`variants.${index}.barcode`]}
                                                    />
                                                </div>
                                                <Field
                                                    label="Purchase unit"
                                                    required
                                                    error={
                                                        errors[
                                                            `variants.${index}.purchase_unit_id`
                                                        ]
                                                    }
                                                >
                                                    <SearchableSelect
                                                        options={unitOptions}
                                                        value={variant.purchase_unit_id}
                                                        onChange={(v) =>
                                                            setVariant(index, {
                                                                purchase_unit_id: v,
                                                            })
                                                        }
                                                    />
                                                </Field>
                                                <Field
                                                    label="Sale unit"
                                                    required
                                                    error={
                                                        errors[`variants.${index}.sale_unit_id`]
                                                    }
                                                >
                                                    <SearchableSelect
                                                        options={unitOptions}
                                                        value={variant.sale_unit_id}
                                                        onChange={(v) =>
                                                            setVariant(index, { sale_unit_id: v })
                                                        }
                                                    />
                                                </Field>
                                                <Field
                                                    label="Conversion"
                                                    required
                                                    error={
                                                        errors[
                                                            `variants.${index}.conversion_rate`
                                                        ]
                                                    }
                                                >
                                                    <Input
                                                        type="number"
                                                        step="0.0001"
                                                        value={variant.conversion_rate}
                                                        onChange={(e) =>
                                                            setVariant(index, {
                                                                conversion_rate: e.target.value,
                                                            })
                                                        }
                                                    />
                                                </Field>
                                                <Field
                                                    label="Sale price"
                                                    required
                                                    error={
                                                        errors[`variants.${index}.sale_price`]
                                                    }
                                                >
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        value={variant.sale_price}
                                                        onChange={(e) =>
                                                            setVariant(index, {
                                                                sale_price: e.target.value,
                                                            })
                                                        }
                                                    />
                                                </Field>
                                                <Field label="Purchase price">
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        value={variant.cost_per_unit}
                                                        onChange={(e) =>
                                                            setVariant(index, {
                                                                cost_per_unit: e.target.value,
                                                            })
                                                        }
                                                    />
                                                </Field>
                                                <Field label="Section">
                                                    <SearchableSelect
                                                        options={[
                                                            { value: '', label: '—' },
                                                            ...sectionOptions,
                                                        ]}
                                                        value={variant.section_id}
                                                        onChange={(v) =>
                                                            setVariant(index, {
                                                                section_id: v || '',
                                                                rack_id: '',
                                                            })
                                                        }
                                                    />
                                                </Field>
                                                <Field label="Rack">
                                                    <SearchableSelect
                                                        options={[
                                                            { value: '', label: '—' },
                                                            ...racks.map((r) => ({
                                                                value: r.id,
                                                                label: r.name,
                                                            })),
                                                        ]}
                                                        value={variant.rack_id}
                                                        onChange={(v) =>
                                                            setVariant(index, {
                                                                rack_id: v || '',
                                                            })
                                                        }
                                                    />
                                                </Field>
                                                <div className="flex items-end pb-2">
                                                    <label className="flex items-center gap-2 text-sm">
                                                        <input
                                                            type="checkbox"
                                                            checked={!!variant.track_serial}
                                                            onChange={(e) =>
                                                                setVariant(index, {
                                                                    track_serial: e.target.checked,
                                                                })
                                                            }
                                                        />
                                                        Track serial / IMEI
                                                    </label>
                                                </div>
                                                {!editing && (
                                                    <>
                                                        <Field label="Opening stock">
                                                            <Input
                                                                type="number"
                                                                step="0.01"
                                                                min="0"
                                                                value={variant.opening_stock}
                                                                onChange={(e) =>
                                                                    setVariant(index, {
                                                                        opening_stock:
                                                                            e.target.value,
                                                                    })
                                                                }
                                                            />
                                                        </Field>
                                                        <Field label="Opening stock date">
                                                            <Input
                                                                type="date"
                                                                value={variant.opening_stock_date}
                                                                onChange={(e) =>
                                                                    setVariant(index, {
                                                                        opening_stock_date:
                                                                            e.target.value,
                                                                    })
                                                                }
                                                            />
                                                        </Field>
                                                    </>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}

                        {errors.variants && (
                            <p className="text-xs text-theme-danger">{errors.variants}</p>
                        )}
                        {errors.variation_id && (
                            <p className="text-xs text-theme-danger">{errors.variation_id}</p>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving…' : editing ? 'Save product' : 'Create product'}
                    </Button>
                    <Link
                        href={route('admin.products.index')}
                        className="text-sm text-theme-ink-muted hover:text-theme-ink"
                    >
                        Cancel
                    </Link>
                </div>
            </form>
        </AdminLayout>
    );
}
