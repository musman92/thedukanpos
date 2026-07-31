import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import ImageUploadField from '@/Components/Ui/ImageUploadField';
import Input, { Field, TextArea } from '@/Components/Ui/Input';
import SearchableSelect from '@/Components/Ui/SearchableSelect';
import { useI18n } from '@/hooks/useI18n';
import { formatMoney } from '@/lib/money';
import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const TABS = [
    { id: 'general', labelKey: 'settings.general' },
    { id: 'preferences', labelKey: 'settings.preferences' },
    { id: 'pos', labelKey: 'settings.pos' },
    { id: 'receipt', labelKey: 'settings.receipt' },
];

const WEEKDAYS = [
    { value: 'monday', label: 'Monday' },
    { value: 'tuesday', label: 'Tuesday' },
    { value: 'wednesday', label: 'Wednesday' },
    { value: 'thursday', label: 'Thursday' },
    { value: 'friday', label: 'Friday' },
    { value: 'saturday', label: 'Saturday' },
    { value: 'sunday', label: 'Sunday' },
];

const DEFAULT_SECTION_LABELS = {
    logo: 'Logo',
    branch_name: 'Branch name',
    address: 'Address',
    phone: 'Phone number',
    tax_id: 'Tax ID / NTN',
    invoice_title: '"INVOICE" title',
    sale_number: 'Sale number',
    date_cashier: 'Date & cashier (one line)',
    customer_block: 'Customer details',
    items_header: 'Column headers (Item / Qty / Price)',
    item_variants: 'Variant name (e.g. size)',
    item_unit_price: 'Unit price under item',
    subtotal: 'Subtotal',
    discount: 'Discount',
    tax: 'Tax',
    payment_info: 'Payment method & status',
    thank_you: 'Thank you / footer message',
};

const DEFAULT_SECTION_GROUPS = [
    { id: 'header', label: 'Header', keys: ['logo', 'branch_name', 'address', 'phone', 'tax_id'] },
    { id: 'invoice_info', label: 'Invoice info', keys: ['invoice_title', 'sale_number', 'date_cashier'] },
    { id: 'customer', label: 'Customer', keys: ['customer_block'] },
    {
        id: 'items',
        label: 'Line items',
        keys: ['items_header', 'item_variants', 'item_unit_price'],
    },
    {
        id: 'totals',
        label: 'Totals',
        description:
            'Grand total is always shown. Subtotal is hidden automatically when it matches the total.',
        keys: ['subtotal', 'discount', 'tax'],
    },
    { id: 'payment', label: 'Payment', keys: ['payment_info'] },
    { id: 'footer', label: 'Footer', keys: ['thank_you'] },
];

const SAMPLE_SALE = {
    number: 'SL-20260731-0001',
    created_at: '31/07/2026 3:25 PM',
    subtotal: 3300,
    discount_total: 50,
    tax_total: 162.5,
    total: 3412.5,
    paid_total: 3412.5,
    cashier: { name: 'Admin' },
    customer: { name: 'Walk-in Customer', phone: '0300-1234567' },
    items: [
        { name: 'Premium Rice 5kg', variant: null, qty: 2, unit_price: 1250, line_total: 2500 },
        { name: 'Cooking Oil', variant: '1 Litre', qty: 1, unit_price: 480, line_total: 480 },
        { name: 'Detergent Powder', variant: null, qty: 1, unit_price: 320, line_total: 320 },
    ],
    payments: [{ name: 'Cash', amount: 3412.5 }],
};

function defaultReceiptSections(saved = {}) {
    return Object.keys(DEFAULT_SECTION_LABELS).reduce((acc, key) => {
        acc[key] = saved?.[key] !== false;
        return acc;
    }, {});
}

function sectionOn(sections, key) {
    return sections?.[key] !== false;
}

function shouldShowSubtotal(sections, sale) {
    if (!sectionOn(sections, 'subtotal')) return false;
    const subtotal = Number(sale.subtotal || 0);
    const total = Number(sale.total || 0);
    const discount = Number(sale.discount_total || 0);
    const tax = Number(sale.tax_total || 0);
    if (
        Math.abs(subtotal - total) < 0.01 &&
        discount <= 0.01 &&
        tax <= 0.01
    ) {
        return false;
    }
    return true;
}

function revokePreview(url) {
    if (url?.startsWith('blob:')) {
        URL.revokeObjectURL(url);
    }
}

function dataFromSettings(settings) {
    return {
        shop_name: settings.shop_name || '',
        email: settings.email || '',
        address: settings.address || '',
        phone: settings.phone || '',
        tax_id: settings.tax_id || '',
        logo: null,
        remove_logo: false,
        currency: settings.currency || 'PKR',
        currency_symbol: settings.currency_symbol || '',
        currency_position: settings.currency_position || 'left',
        decimal_points: String(settings.decimal_points ?? 2),
        timezone: settings.timezone || 'UTC',
        locale: settings.locale || 'en',
        rtl: !!settings.rtl,
        date_format: settings.date_format || 'Y-m-d',
        time_format: settings.time_format || '12',
        week_starts_on: settings.week_starts_on || 'monday',
        list_page_limit: String(settings.list_page_limit ?? 15),
        activity_logging_enabled: !!settings.activity_logging_enabled,
        pos_allow_credit: !!settings.pos_allow_credit,
        pos_show_stock: !!settings.pos_show_stock,
        pos_show_product_image: settings.pos_show_product_image !== false,
        pos_catalog_mode: settings.pos_catalog_mode === 'grouped' ? 'grouped' : 'flat',
        receipt_footer: settings.receipt_footer || '',
        receipt_paper_width: String(settings.receipt_paper_width || '80'),
        receipt_font_size: String(settings.receipt_font_size ?? 14),
        receipt_show_address: !!settings.receipt_show_address,
        receipt_sections: defaultReceiptSections(settings.receipt_sections),
    };
}

/** Settings that change shared UI (locale, dir, money/date formatting, POS chrome). */
const RELOAD_ON_CHANGE_KEYS = [
    'locale',
    'rtl',
    'shop_name',
    'currency',
    'currency_symbol',
    'currency_position',
    'decimal_points',
    'timezone',
    'date_format',
    'time_format',
    'week_starts_on',
    'list_page_limit',
    'pos_allow_credit',
    'pos_show_stock',
    'pos_show_product_image',
    'pos_catalog_mode',
];

function settingValuesDiffer(before, after) {
    if (typeof before === 'boolean' || typeof after === 'boolean') {
        return Boolean(before) !== Boolean(after);
    }

    return String(before ?? '') !== String(after ?? '');
}

function shouldReloadAfterSave(previousSettings, nextData) {
    const baseline = dataFromSettings(previousSettings);

    return RELOAD_ON_CHANGE_KEYS.some((key) =>
        settingValuesDiffer(baseline[key], nextData[key]),
    );
}

export default function Edit({ settings, section = 'general', options = {} }) {
    const { t } = useI18n();
    const active = TABS.some((tab) => tab.id === section) ? section : 'general';
    const form = useForm(dataFromSettings(settings));
    const [previewUrl, setPreviewUrl] = useState(settings.logo_url || null);
    const resetKey = `${settings.logo_url || 'none'}`;

    useEffect(() => {
        form.setData(dataFromSettings(settings));
        setPreviewUrl((prev) => {
            revokePreview(prev);
            return settings.logo_url || null;
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [settings]);

    useEffect(() => () => revokePreview(previewUrl), [previewUrl]);

    const currencyOptions = useMemo(
        () => (options.currencies || []).map((c) => ({ value: c, label: c })),
        [options.currencies],
    );

    const timezoneOptions = useMemo(
        () => (options.timezones || []).map((tz) => ({ value: tz, label: tz })),
        [options.timezones],
    );

    const dateFormatOptions = useMemo(
        () =>
            (options.date_formats || []).map((f) => ({
                value: f,
                label: f,
            })),
        [options.date_formats],
    );

    const switchTab = (id) => {
        router.get(
            route('admin.settings.edit'),
            { section: id },
            { preserveState: true, preserveScroll: true },
        );
    };

    const onSelectImage = (file) => {
        setPreviewUrl((prev) => {
            revokePreview(prev);
            return URL.createObjectURL(file);
        });
        form.setData((data) => ({
            ...data,
            logo: file,
            remove_logo: false,
        }));
        form.clearErrors('logo');
    };

    const onClearImage = () => {
        setPreviewUrl((prev) => {
            revokePreview(prev);
            return null;
        });
        form.setData((data) => ({
            ...data,
            logo: null,
            remove_logo: !!settings.logo_url,
        }));
        form.clearErrors('logo');
    };

    const setSection = (key, value) => {
        form.setData((data) => ({
            ...data,
            receipt_sections: {
                ...data.receipt_sections,
                [key]: value,
            },
            ...(key === 'address' ? { receipt_show_address: value } : {}),
        }));
    };

    const submit = (e) => {
        e.preventDefault();

        const reloadAfterSave = shouldReloadAfterSave(settings, form.data);

        form.transform((data) => ({
            ...data,
            _method: 'put',
            decimal_points: Number(data.decimal_points),
            list_page_limit: Number(data.list_page_limit),
            receipt_font_size: Number(data.receipt_font_size),
        }));

        form.post(route('admin.settings.update'), {
            forceFormData: true,
            preserveScroll: !reloadAfterSave,
            onFinish: () => form.transform((d) => d),
            onSuccess: () => {
                if (!reloadAfterSave) {
                    return;
                }

                // Full reload so locale/RTL/dir and shared company config apply cleanly.
                window.location.assign(
                    route('admin.settings.edit', { section: active }),
                );
            },
        });
    };

    return (
        <AdminLayout
            title="Company settings"
            description="Shop profile, locale, POS behavior, and receipt layout for this company."
        >
            <Head title="Settings" />

            <div className="mb-4 flex flex-wrap gap-1 border-b border-theme-border">
                {TABS.map((tab) => (
                    <button
                        key={tab.id}
                        type="button"
                        onClick={() => switchTab(tab.id)}
                        className={`px-4 py-2.5 text-sm transition ${
                            active === tab.id
                                ? 'border-b-2 border-theme-primary font-medium text-theme-primary'
                                : 'text-theme-ink-muted hover:text-theme-ink'
                        }`}
                    >
                        {t(tab.labelKey)}
                    </button>
                ))}
            </div>

            <form
                onSubmit={submit}
                className="dp-card w-full space-y-5 p-6"
            >
                {active === 'general' && (
                    <div className="grid gap-6 lg:grid-cols-2">
                        <div className="space-y-4">
                            <Field label="Business name" error={form.errors.shop_name}>
                                <Input
                                    value={form.data.shop_name}
                                    onChange={(e) => form.setData('shop_name', e.target.value)}
                                    error={!!form.errors.shop_name}
                                />
                            </Field>
                            <Field label="Email" error={form.errors.email}>
                                <Input
                                    type="email"
                                    value={form.data.email}
                                    onChange={(e) => form.setData('email', e.target.value)}
                                    error={!!form.errors.email}
                                />
                            </Field>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Phone" error={form.errors.phone}>
                                    <Input
                                        value={form.data.phone}
                                        onChange={(e) => form.setData('phone', e.target.value)}
                                        error={!!form.errors.phone}
                                    />
                                </Field>
                                <Field label="Tax ID / NTN" error={form.errors.tax_id}>
                                    <Input
                                        value={form.data.tax_id}
                                        onChange={(e) => form.setData('tax_id', e.target.value)}
                                        error={!!form.errors.tax_id}
                                    />
                                </Field>
                            </div>
                            <Field label="Address" error={form.errors.address}>
                                <TextArea
                                    rows={4}
                                    value={form.data.address}
                                    onChange={(e) => form.setData('address', e.target.value)}
                                    error={!!form.errors.address}
                                />
                            </Field>
                        </div>
                        <div>
                            <ImageUploadField
                                label="Logo"
                                previewUrl={previewUrl}
                                error={form.errors.logo}
                                resetKey={resetKey}
                                onSelect={onSelectImage}
                                onClear={onClearImage}
                                onReject={(message) => form.setError('logo', message)}
                                hint="Color logo for the app. A black & white print copy is generated automatically for thermal receipts."
                            />
                            {settings.logo_print_url && !form.data.remove_logo && !form.data.logo && (
                                <div className="mt-4 rounded-lg border border-theme-border bg-theme-bg p-3">
                                    <p className="text-xs font-medium uppercase tracking-wide text-theme-ink-muted">
                                        Receipt print logo
                                    </p>
                                    <div className="mt-2 flex items-center justify-center rounded-md bg-white p-3 ring-1 ring-theme-border">
                                        <img
                                            src={settings.logo_print_url}
                                            alt="Print logo"
                                            className="max-h-16 w-auto object-contain"
                                        />
                                    </div>
                                    <p className="mt-2 text-xs text-theme-ink-muted">
                                        High-contrast version used on printed receipts.
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {active === 'preferences' && (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label={t('settings.language')}
                                error={form.errors.locale}
                                hint={t('settings.language_hint')}
                            >
                                <select
                                    className="dp-select-reset h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                    value={form.data.locale}
                                    onChange={(e) => form.setData('locale', e.target.value)}
                                >
                                    {(options.locales || []).map((locale) => (
                                        <option key={locale.value} value={locale.value}>
                                            {locale.native} ({locale.label})
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field
                                label={t('settings.rtl')}
                                error={form.errors.rtl}
                                hint={t('settings.rtl_hint')}
                            >
                                <label className="flex h-10 items-center gap-2 text-sm text-theme-ink">
                                    <input
                                        type="checkbox"
                                        className="rounded border-theme-border text-theme-primary focus:ring-theme-primary/30"
                                        checked={!!form.data.rtl}
                                        onChange={(e) => form.setData('rtl', e.target.checked)}
                                    />
                                    <span>{t('settings.rtl_enable')}</span>
                                </label>
                            </Field>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Currency" error={form.errors.currency}>
                                <SearchableSelect
                                    options={currencyOptions}
                                    value={form.data.currency}
                                    onChange={(v) => form.setData('currency', v || 'PKR')}
                                    error={!!form.errors.currency}
                                />
                            </Field>
                            <Field
                                label="Currency symbol"
                                error={form.errors.currency_symbol}
                                hint="Optional override (e.g. Rs). Leave blank to use the default for the currency."
                            >
                                <Input
                                    value={form.data.currency_symbol}
                                    onChange={(e) =>
                                        form.setData('currency_symbol', e.target.value)
                                    }
                                    error={!!form.errors.currency_symbol}
                                    placeholder="Rs"
                                />
                            </Field>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Symbol position" error={form.errors.currency_position}>
                                <select
                                    className="dp-select-reset h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                    value={form.data.currency_position}
                                    onChange={(e) =>
                                        form.setData('currency_position', e.target.value)
                                    }
                                >
                                    <option value="left">Left (Rs 100)</option>
                                    <option value="right">Right (100 Rs)</option>
                                </select>
                            </Field>
                            <Field label="Decimal places" error={form.errors.decimal_points}>
                                <select
                                    className="dp-select-reset h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                    value={form.data.decimal_points}
                                    onChange={(e) =>
                                        form.setData('decimal_points', e.target.value)
                                    }
                                >
                                    {[0, 1, 2, 3, 4].map((n) => (
                                        <option key={n} value={n}>
                                            {n}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        </div>
                        <Field label="Timezone" error={form.errors.timezone}>
                            <SearchableSelect
                                options={timezoneOptions}
                                value={form.data.timezone}
                                onChange={(v) => form.setData('timezone', v || 'UTC')}
                                error={!!form.errors.timezone}
                                placeholder="Search timezone…"
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Date format" error={form.errors.date_format}>
                                <select
                                    className="dp-select-reset h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                    value={form.data.date_format}
                                    onChange={(e) =>
                                        form.setData('date_format', e.target.value)
                                    }
                                >
                                    {dateFormatOptions.map((o) => (
                                        <option key={o.value} value={o.value}>
                                            {o.label}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field label="Time format" error={form.errors.time_format}>
                                <select
                                    className="dp-select-reset h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                    value={form.data.time_format}
                                    onChange={(e) =>
                                        form.setData('time_format', e.target.value)
                                    }
                                >
                                    <option value="12">12-hour</option>
                                    <option value="24">24-hour</option>
                                </select>
                            </Field>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Week starts on"
                                error={form.errors.week_starts_on}
                                hint="Used for weekly reports and closing periods."
                            >
                                <select
                                    className="dp-select-reset h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                    value={form.data.week_starts_on}
                                    onChange={(e) =>
                                        form.setData('week_starts_on', e.target.value)
                                    }
                                >
                                    {WEEKDAYS.map((day) => (
                                        <option key={day.value} value={day.value}>
                                            {day.label}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field
                                label="Default page limit"
                                error={form.errors.list_page_limit}
                                hint="Used on listing pages unless the user picks another limit."
                            >
                                <select
                                    className="dp-select-reset h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                    value={form.data.list_page_limit}
                                    onChange={(e) =>
                                        form.setData('list_page_limit', e.target.value)
                                    }
                                >
                                    {[10, 15, 25, 50].map((n) => (
                                        <option key={n} value={n}>
                                            {n} rows
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        </div>
                        <label className="flex items-center gap-2 text-sm text-theme-ink">
                            <input
                                type="checkbox"
                                checked={form.data.activity_logging_enabled}
                                onChange={(e) =>
                                    form.setData('activity_logging_enabled', e.target.checked)
                                }
                                className="rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                            />
                            Activity logging enabled
                        </label>
                    </>
                )}

                {active === 'pos' && (
                    <div className="space-y-4">
                        <label className="flex items-center gap-2 text-sm text-theme-ink">
                            <input
                                type="checkbox"
                                checked={form.data.pos_allow_credit}
                                onChange={(e) =>
                                    form.setData('pos_allow_credit', e.target.checked)
                                }
                                className="rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                            />
                            Allow credit / partial sales
                        </label>
                        <label className="flex items-center gap-2 text-sm text-theme-ink">
                            <input
                                type="checkbox"
                                checked={form.data.pos_show_stock}
                                onChange={(e) =>
                                    form.setData('pos_show_stock', e.target.checked)
                                }
                                className="rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                            />
                            Show stock on POS product search
                        </label>
                        <label className="flex items-center gap-2 text-sm text-theme-ink">
                            <input
                                type="checkbox"
                                checked={form.data.pos_show_product_image}
                                onChange={(e) =>
                                    form.setData('pos_show_product_image', e.target.checked)
                                }
                                className="rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                            />
                            Show product images on POS
                        </label>
                        <Field
                            label="Product catalog display"
                            error={form.errors.pos_catalog_mode}
                        >
                            <select
                                value={form.data.pos_catalog_mode}
                                onChange={(e) =>
                                    form.setData('pos_catalog_mode', e.target.value)
                                }
                                className="w-full rounded-lg border border-theme-border bg-theme-surface px-3 py-2 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                            >
                                <option value="flat">
                                    Flat list — each variant as its own tile
                                </option>
                                <option value="grouped">
                                    Grouped — one tile per product, pick variant if needed
                                </option>
                            </select>
                            <p className="mt-1.5 text-xs text-theme-ink-muted">
                                Search and barcode scan always add the matching variant
                                directly.
                            </p>
                        </Field>
                    </div>
                )}

                {active === 'receipt' && (
                    <ReceiptSettingsPanel
                        form={form}
                        options={options}
                        previewUrl={previewUrl}
                        printLogoUrl={settings.logo_print_url || null}
                        setSection={setSection}
                    />
                )}

                <div className="flex justify-end border-t border-theme-border pt-5">
                    <Button type="submit" disabled={form.processing}>
                        Save settings
                    </Button>
                </div>
            </form>
        </AdminLayout>
    );
}

function ReceiptSettingsPanel({ form, options, previewUrl, printLogoUrl, setSection }) {
    const sections = form.data.receipt_sections || {};
    const paperMm = Number(form.data.receipt_paper_width || 80);
    const fontSize = Number(form.data.receipt_font_size || 14);
    const receiptLogo =
        form.data.logo || form.data.remove_logo
            ? previewUrl
            : printLogoUrl || previewUrl;
    const moneyCfg = {
        currency_symbol: form.data.currency_symbol || form.data.currency,
        currency: form.data.currency,
        currency_position: form.data.currency_position,
        decimal_points: Number(form.data.decimal_points ?? 2),
    };
    const money = (n) => formatMoney(n, moneyCfg);

    const labels = options.receipt_section_labels || DEFAULT_SECTION_LABELS;
    const groups = options.receipt_section_groups
        ? Object.entries(options.receipt_section_groups).map(([id, group]) => ({
              id,
              ...group,
          }))
        : DEFAULT_SECTION_GROUPS;

    const sale = SAMPLE_SALE;
    const businessName = form.data.shop_name || 'Business name';
    const branchName = 'Main Branch';
    const showBranch =
        sectionOn(sections, 'branch_name') &&
        branchName.toLowerCase() !== businessName.toLowerCase();
    const showSubtotal = shouldShowSubtotal(sections, sale);
    const due = Number(sale.total) - Number(sale.paid_total);

    return (
        <div className="grid gap-8 xl:grid-cols-2">
            <div className="space-y-5">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Receipt font size" error={form.errors.receipt_font_size}>
                        <select
                            className="dp-select-reset h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                            value={form.data.receipt_font_size}
                            onChange={(e) =>
                                form.setData('receipt_font_size', e.target.value)
                            }
                        >
                            {Array.from({ length: 11 }, (_, i) => i + 10).map((n) => (
                                <option key={n} value={n}>
                                    {n}px{n === 14 ? ' (default)' : ''}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Receipt paper width" error={form.errors.receipt_paper_width}>
                        <select
                            className="dp-select-reset h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                            value={form.data.receipt_paper_width}
                            onChange={(e) =>
                                form.setData('receipt_paper_width', e.target.value)
                            }
                        >
                            <option value="80">80 mm (default)</option>
                            <option value="58">58 mm</option>
                        </select>
                    </Field>
                </div>

                <Field label="Footer text" error={form.errors.receipt_footer}>
                    <Input
                        value={form.data.receipt_footer}
                        onChange={(e) => form.setData('receipt_footer', e.target.value)}
                        error={!!form.errors.receipt_footer}
                        placeholder="Thank you for shopping with us"
                    />
                </Field>

                <div className="rounded-lg border border-theme-border bg-theme-surface p-4">
                    <p className="text-sm font-semibold text-theme-ink">Invoice content</p>
                    <p className="mt-1 text-xs text-theme-ink-muted">
                        Uncheck lines you want to remove from the receipt (e.g. thank-you note,
                        address, customer details).
                    </p>

                    <div className="mt-4 max-h-[32rem] space-y-3 overflow-y-auto pr-1">
                        {groups.map((group) => (
                            <div
                                key={group.id || group.label}
                                className="rounded-lg border border-theme-border bg-theme-bg p-3"
                            >
                                <p className="text-sm font-medium text-theme-ink">
                                    {group.label}
                                </p>
                                {group.description && (
                                    <p className="mt-1 text-xs text-theme-ink-muted">
                                        {group.description}
                                    </p>
                                )}
                                <div className="mt-2 space-y-2">
                                    {group.keys.map((key) => (
                                        <label
                                            key={key}
                                            className="flex cursor-pointer items-start gap-3 text-sm text-theme-ink"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={!!sections[key]}
                                                onChange={(e) =>
                                                    setSection(key, e.target.checked)
                                                }
                                                className="mt-0.5 rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                                            />
                                            <span>{labels[key] || key}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            <div className="xl:sticky xl:top-4 xl:self-start">
                <p className="mb-3 text-sm font-semibold text-theme-ink">Live preview</p>
                <div className="overflow-x-auto rounded-lg border border-theme-border bg-theme-bg p-4">
                    <div
                        className="mx-auto bg-white text-black shadow-md"
                        style={{
                            width: `${paperMm}mm`,
                            maxWidth: '100%',
                            fontSize: `${fontSize}px`,
                            fontFamily: 'Arial, Helvetica, sans-serif',
                            fontWeight: 700,
                            lineHeight: 1.35,
                            padding: '1.5mm 2mm',
                            boxSizing: 'border-box',
                        }}
                    >
                        <div
                            style={{
                                textAlign: 'center',
                                marginBottom: 8,
                                borderBottom: '1px dashed #000',
                                paddingBottom: 8,
                            }}
                        >
                            {sectionOn(sections, 'logo') && receiptLogo && (
                                <div style={{ marginBottom: 6 }}>
                                    <img
                                        src={receiptLogo}
                                        alt=""
                                        style={{
                                            maxHeight: 48,
                                            maxWidth: 140,
                                            objectFit: 'contain',
                                            margin: '0 auto',
                                        }}
                                    />
                                </div>
                            )}
                            <div
                                style={{
                                    fontSize: '1.15em',
                                    textTransform: 'uppercase',
                                }}
                            >
                                {businessName}
                            </div>
                            {showBranch && (
                                <div style={{ fontSize: '0.9em', marginTop: 2 }}>
                                    {branchName}
                                </div>
                            )}
                            {sectionOn(sections, 'address') && form.data.address && (
                                <div
                                    style={{
                                        fontSize: '0.9em',
                                        marginTop: 2,
                                        whiteSpace: 'pre-line',
                                    }}
                                >
                                    {form.data.address}
                                </div>
                            )}
                            {sectionOn(sections, 'phone') && form.data.phone && (
                                <div style={{ fontSize: '0.9em', marginTop: 2 }}>
                                    Tel: {form.data.phone}
                                </div>
                            )}
                            {sectionOn(sections, 'tax_id') && form.data.tax_id && (
                                <div style={{ fontSize: '0.85em', marginTop: 2 }}>
                                    NTN: {form.data.tax_id}
                                </div>
                            )}
                        </div>

                        {sectionOn(sections, 'invoice_title') && (
                            <div
                                style={{
                                    fontSize: '1.05em',
                                    fontWeight: 'bold',
                                    textAlign: 'center',
                                    margin: '0.5em 0',
                                }}
                            >
                                INVOICE
                            </div>
                        )}

                        {(sectionOn(sections, 'sale_number') ||
                            sectionOn(sections, 'date_cashier') ||
                            sectionOn(sections, 'customer_block')) && (
                            <div
                                style={{
                                    textAlign: 'center',
                                    fontSize: '0.9em',
                                    marginBottom: 8,
                                    borderBottom: '1px dashed #000',
                                    paddingBottom: 8,
                                }}
                            >
                                {sectionOn(sections, 'sale_number') && (
                                    <p style={{ margin: '2px 0' }}>
                                        <strong>Sale #:</strong> {sale.number}
                                    </p>
                                )}
                                {sectionOn(sections, 'date_cashier') && (
                                    <p style={{ margin: '2px 0' }}>
                                        <strong>Date:</strong> {sale.created_at}
                                        {sale.cashier?.name && (
                                            <>
                                                {' '}
                                                · <strong>Cashier:</strong> {sale.cashier.name}
                                            </>
                                        )}
                                    </p>
                                )}
                                {sectionOn(sections, 'customer_block') && sale.customer?.name && (
                                    <p style={{ margin: '2px 0' }}>
                                        <strong>Customer:</strong> {sale.customer.name}
                                        {sale.customer.phone ? ` · ${sale.customer.phone}` : ''}
                                    </p>
                                )}
                            </div>
                        )}

                        <table
                            style={{
                                width: '100%',
                                tableLayout: 'fixed',
                                borderCollapse: 'collapse',
                                fontSize: '0.9em',
                            }}
                        >
                            {sectionOn(sections, 'items_header') && (
                                <thead>
                                    <tr>
                                        <th
                                            style={{
                                                textAlign: 'left',
                                                padding: '3px 0',
                                                borderBottom: '1px dashed #000',
                                            }}
                                        >
                                            Item
                                        </th>
                                        <th
                                            style={{
                                                textAlign: 'center',
                                                padding: '3px 0',
                                                borderBottom: '1px dashed #000',
                                                width: '14%',
                                            }}
                                        >
                                            Qty
                                        </th>
                                        <th
                                            style={{
                                                textAlign: 'right',
                                                padding: '3px 0',
                                                borderBottom: '1px dashed #000',
                                                width: '32%',
                                            }}
                                        >
                                            Price
                                        </th>
                                    </tr>
                                </thead>
                            )}
                            <tbody>
                                {sale.items.map((item) => (
                                    <tr key={item.name}>
                                        <td
                                            style={{
                                                padding: '3px 0',
                                                borderBottom: '1px dotted #ccc',
                                            }}
                                        >
                                            <div style={{ fontWeight: 'bold' }}>{item.name}</div>
                                            {sectionOn(sections, 'item_variants') &&
                                                item.variant && (
                                                    <div style={{ fontSize: '0.88em' }}>
                                                        {item.variant}
                                                    </div>
                                                )}
                                            {sectionOn(sections, 'item_unit_price') && (
                                                <div
                                                    style={{
                                                        fontSize: '0.85em',
                                                        fontWeight: 400,
                                                    }}
                                                >
                                                    {item.qty} × {money(item.unit_price)}
                                                </div>
                                            )}
                                        </td>
                                        <td
                                            style={{
                                                textAlign: 'center',
                                                padding: '3px 0',
                                                borderBottom: '1px dotted #ccc',
                                            }}
                                        >
                                            {item.qty}
                                        </td>
                                        <td
                                            style={{
                                                textAlign: 'right',
                                                padding: '3px 0',
                                                borderBottom: '1px dotted #ccc',
                                                whiteSpace: 'nowrap',
                                            }}
                                        >
                                            {money(item.line_total)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        <div
                            style={{
                                marginTop: 6,
                                borderTop: '1px dashed #000',
                                paddingTop: 4,
                                fontSize: '0.9em',
                            }}
                        >
                            {showSubtotal && (
                                <div
                                    style={{
                                        display: 'flex',
                                        justifyContent: 'space-between',
                                        margin: '2px 0',
                                    }}
                                >
                                    <span>Subtotal</span>
                                    <span>{money(sale.subtotal)}</span>
                                </div>
                            )}
                            {sectionOn(sections, 'discount') &&
                                Number(sale.discount_total) > 0 && (
                                    <div
                                        style={{
                                            display: 'flex',
                                            justifyContent: 'space-between',
                                            margin: '2px 0',
                                        }}
                                    >
                                        <span>Discount</span>
                                        <span>-{money(sale.discount_total)}</span>
                                    </div>
                                )}
                            {sectionOn(sections, 'tax') && (
                                <div
                                    style={{
                                        display: 'flex',
                                        justifyContent: 'space-between',
                                        margin: '2px 0',
                                    }}
                                >
                                    <span>Tax</span>
                                    <span>{money(sale.tax_total)}</span>
                                </div>
                            )}
                            <div
                                style={{
                                    display: 'flex',
                                    justifyContent: 'space-between',
                                    borderTop: '1px solid #000',
                                    borderBottom: '1px solid #000',
                                    padding: '0.35em 0',
                                    margin: '0.35em 0',
                                    fontSize: '1.1em',
                                }}
                            >
                                <span>TOTAL</span>
                                <span>{money(sale.total)}</span>
                            </div>
                        </div>

                        {sectionOn(sections, 'payment_info') && (
                            <div
                                style={{
                                    marginTop: 6,
                                    fontSize: '0.9em',
                                    textAlign: 'center',
                                }}
                            >
                                {sale.payments.map((p) => (
                                    <p key={p.name} style={{ margin: '2px 0' }}>
                                        <strong>Payment:</strong> {p.name} · {money(p.amount)}
                                    </p>
                                ))}
                                {due > 0.01 && (
                                    <p style={{ margin: '2px 0' }}>
                                        <strong>On account:</strong> {money(due)}
                                    </p>
                                )}
                            </div>
                        )}

                        {sectionOn(sections, 'thank_you') && form.data.receipt_footer && (
                            <div
                                style={{
                                    textAlign: 'center',
                                    marginTop: '0.75em',
                                    paddingTop: '0.5em',
                                    borderTop: '1px dashed #000',
                                    fontSize: '0.88em',
                                }}
                            >
                                <p>{form.data.receipt_footer}</p>
                            </div>
                        )}
                    </div>
                </div>
                <p className="mt-2 text-xs text-theme-ink-muted">
                    Sample receipt — updates live as you change options. Amounts use your
                    currency settings.
                </p>
            </div>
        </div>
    );
}
