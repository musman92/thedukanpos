import Drawer from '@/Components/Ui/Drawer';
import Button from '@/Components/Ui/Button';
import Input, { Field, TextArea } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';

const selectClass =
    'w-full rounded-lg border border-theme-border bg-theme-surface px-3.5 py-2.5 text-sm text-theme-ink outline-none transition focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

const defaultForm = {
    name: '',
    code: '',
    email: '',
    phone: '',
    address: '',
    tax_id: '',
    currency: 'PKR',
    timezone: 'Asia/Karachi',
    status: 'active',
    trial_days: 14,
    trial_ends_at: '',
    plan: 'starter',
    billing_status: 'trial',
    monthly_fee: 0,
    billing_notes: '',
    admin_name: '',
    admin_email: '',
    admin_password: '',
    admin_password_confirmation: '',
    create_default_branch: true,
    is_demo: false,
};

function tenantToForm(tenant) {
    if (!tenant) return null;
    return {
        ...defaultForm,
        name: tenant.name || '',
        code: tenant.code || '',
        email: tenant.email || '',
        phone: tenant.phone || '',
        address: tenant.address || '',
        tax_id: tenant.tax_id || '',
        currency: tenant.currency || 'PKR',
        timezone: tenant.timezone || 'Asia/Karachi',
        status: tenant.status || (tenant.is_active ? 'active' : 'suspended'),
        trial_ends_at: tenant.trial_ends_at || '',
        plan: tenant.plan || 'starter',
        billing_status: tenant.billing_status || 'trial',
        monthly_fee: tenant.monthly_fee ?? 0,
        billing_notes: tenant.billing_notes || '',
        is_demo: !!tenant.is_demo,
    };
}

export default function TenantFormDrawer({ open, onClose, formMeta = {}, tenant = null }) {
    const isEdit = !!tenant;
    const currencies = formMeta.currencies || ['PKR', 'USD', 'EUR', 'GBP', 'AED', 'SAR'];
    const timezones = formMeta.timezones || [
        { value: 'Asia/Karachi', label: 'Karachi (PKT) (Asia/Karachi)' },
    ];
    const trialOptions = formMeta.trial_options || [
        { value: 0, label: 'No trial' },
        { value: 7, label: '7 days' },
        { value: 14, label: '14 days' },
        { value: 30, label: '30 days' },
        { value: 60, label: '60 days' },
        { value: 90, label: '90 days' },
    ];
    const defaultTrialDays = formMeta.default_trial_days ?? 14;

    const initial = useMemo(() => {
        if (tenant) return tenantToForm(tenant);
        return {
            ...defaultForm,
            trial_days: defaultTrialDays,
            currency: currencies.includes('PKR') ? 'PKR' : currencies[0] || 'PKR',
            timezone: timezones[0]?.value || 'Asia/Karachi',
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, tenant?.id]);

    const form = useForm(initial);

    useEffect(() => {
        if (!open) return;
        form.clearErrors();
        form.setData(initial);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, initial]);

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            form.put(route('platform.tenants.update', tenant.id), {
                preserveScroll: true,
                onSuccess: () => onClose(),
            });
            return;
        }
        form.post(route('platform.tenants.store'), {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={isEdit ? 'Edit Company' : 'Create New Company'}
            description={
                isEdit
                    ? 'Update company information and billing.'
                    : 'Add a new company to the system.'
            }
            width="xl"
        >
            <form onSubmit={submit} className="space-y-6">
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Field label="Company Name" required error={form.errors.name}>
                        <Input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            error={!!form.errors.name}
                            placeholder="e.g., Acme Restaurant Group"
                        />
                    </Field>
                    <Field
                        label="Slug"
                        error={form.errors.code}
                        hint={
                            isEdit
                                ? 'Shop login code (e.g. admin@slug).'
                                : 'Leave empty to auto-generate from company name'
                        }
                    >
                        <Input
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value.toLowerCase())}
                            error={!!form.errors.code}
                            placeholder="auto-generated from name"
                        />
                    </Field>
                    <Field label="Email" required error={form.errors.email}>
                        <Input
                            type="email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                            error={!!form.errors.email}
                            placeholder="company@example.com"
                        />
                    </Field>
                    <Field label="Phone" error={form.errors.phone}>
                        <Input
                            value={form.data.phone}
                            onChange={(e) => form.setData('phone', e.target.value)}
                            error={!!form.errors.phone}
                            placeholder="+1234567890"
                        />
                    </Field>
                </div>

                <Field label="Address" error={form.errors.address}>
                    <TextArea
                        rows={3}
                        value={form.data.address}
                        onChange={(e) => form.setData('address', e.target.value)}
                        error={!!form.errors.address}
                        placeholder="Street address, City, State, ZIP"
                    />
                </Field>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Field label="Tax ID" error={form.errors.tax_id}>
                        <Input
                            value={form.data.tax_id}
                            onChange={(e) => form.setData('tax_id', e.target.value)}
                            error={!!form.errors.tax_id}
                            placeholder="Tax identification number"
                        />
                    </Field>
                    <Field
                        label="Currency"
                        error={form.errors.currency}
                        hint="3-letter currency code (e.g., USD, EUR, GBP)"
                    >
                        <select
                            value={form.data.currency}
                            onChange={(e) => form.setData('currency', e.target.value)}
                            className={selectClass}
                        >
                            {currencies.map((code) => (
                                <option key={code} value={code}>
                                    {code}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Timezone" error={form.errors.timezone}>
                        <select
                            value={form.data.timezone}
                            onChange={(e) => form.setData('timezone', e.target.value)}
                            className={selectClass}
                        >
                            {timezones.map((tz) => (
                                <option key={tz.value} value={tz.value}>
                                    {tz.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Status" required error={form.errors.status}>
                        <select
                            value={form.data.status}
                            onChange={(e) => form.setData('status', e.target.value)}
                            className={selectClass}
                        >
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </Field>
                </div>

                <div className="border-t border-theme-border pt-6">
                    <h3 className="text-base font-semibold text-theme-ink">Subscription & billing</h3>
                    <p className="mt-1 text-sm text-theme-ink-muted">
                        {isEdit
                            ? 'Update plan, fee, and trial details for this tenant.'
                            : 'Offer a free trial for new tenants. Charging begins after the trial unless you set amount to 0 with a long due date.'}
                    </p>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    {!isEdit && (
                        <Field
                            label="Free trial period"
                            error={form.errors.trial_days}
                            hint="Billing due date is set to the trial end date automatically."
                        >
                            <select
                                value={form.data.trial_days}
                                onChange={(e) =>
                                    form.setData('trial_days', Number(e.target.value))
                                }
                                className={selectClass}
                            >
                                {trialOptions.map((opt) => (
                                    <option key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    )}
                    {isEdit && (
                        <Field label="Billing status" error={form.errors.billing_status}>
                            <select
                                value={form.data.billing_status}
                                onChange={(e) => form.setData('billing_status', e.target.value)}
                                className={selectClass}
                            >
                                {['trial', 'active', 'past_due', 'cancelled'].map((s) => (
                                    <option key={s} value={s}>
                                        {s}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    )}
                    <Field
                        label={isEdit ? 'Trial ends' : 'Billing due date (optional override)'}
                        error={form.errors.trial_ends_at}
                        hint={
                            isEdit
                                ? undefined
                                : 'Leave blank to use trial end. Set far future for complimentary access with $0 amount.'
                        }
                    >
                        <Input
                            type="date"
                            value={form.data.trial_ends_at}
                            onChange={(e) => form.setData('trial_ends_at', e.target.value)}
                            error={!!form.errors.trial_ends_at}
                        />
                    </Field>
                    <Field label="Plan" error={form.errors.plan}>
                        <Input
                            value={form.data.plan}
                            onChange={(e) => form.setData('plan', e.target.value)}
                            error={!!form.errors.plan}
                            placeholder="starter"
                        />
                    </Field>
                    <Field
                        label="Amount per interval"
                        error={form.errors.monthly_fee}
                        hint="Monthly fee after trial. Use 0 for complimentary."
                    >
                        <Input
                            type="number"
                            step="0.01"
                            min="0"
                            value={form.data.monthly_fee}
                            onChange={(e) => form.setData('monthly_fee', e.target.value)}
                            error={!!form.errors.monthly_fee}
                            placeholder="e.g. 99"
                        />
                    </Field>
                    <Field
                        label="Billing notes"
                        error={form.errors.billing_notes}
                        className="md:col-span-2"
                    >
                        <TextArea
                            rows={2}
                            value={form.data.billing_notes}
                            onChange={(e) => form.setData('billing_notes', e.target.value)}
                            error={!!form.errors.billing_notes}
                        />
                    </Field>
                </div>

                {!isEdit && (
                    <>
                        <div className="border-t border-theme-border pt-6">
                            <h3 className="text-base font-semibold text-theme-ink">
                                Company Admin User
                            </h3>
                            <p className="mt-1 text-sm text-theme-ink-muted">
                                Create an admin user who can login and manage this company.
                            </p>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <Field label="Admin Name" required error={form.errors.admin_name}>
                                <Input
                                    value={form.data.admin_name}
                                    onChange={(e) => form.setData('admin_name', e.target.value)}
                                    error={!!form.errors.admin_name}
                                    placeholder="e.g., John Doe"
                                />
                            </Field>
                            <Field label="Admin Email" required error={form.errors.admin_email}>
                                <Input
                                    type="email"
                                    value={form.data.admin_email}
                                    onChange={(e) => form.setData('admin_email', e.target.value)}
                                    error={!!form.errors.admin_email}
                                    placeholder="admin@example.com"
                                />
                            </Field>
                            <Field
                                label="Admin Password"
                                required
                                error={form.errors.admin_password}
                            >
                                <Input
                                    type="password"
                                    value={form.data.admin_password}
                                    onChange={(e) =>
                                        form.setData('admin_password', e.target.value)
                                    }
                                    error={!!form.errors.admin_password}
                                    placeholder="Minimum 8 characters"
                                    autoComplete="new-password"
                                />
                            </Field>
                            <Field
                                label="Confirm Password"
                                required
                                error={form.errors.admin_password_confirmation}
                            >
                                <Input
                                    type="password"
                                    value={form.data.admin_password_confirmation}
                                    onChange={(e) =>
                                        form.setData(
                                            'admin_password_confirmation',
                                            e.target.value,
                                        )
                                    }
                                    error={!!form.errors.admin_password_confirmation}
                                    placeholder="Confirm password"
                                    autoComplete="new-password"
                                />
                            </Field>
                        </div>
                    </>
                )}

                <div>
                    <label className="flex items-center gap-2 text-sm text-theme-ink">
                        <input
                            type="checkbox"
                            checked={!!form.data.is_demo}
                            onChange={(e) => form.setData('is_demo', e.target.checked)}
                            className="h-4 w-4 rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                        />
                        Demo company (for testing and sharing)
                    </label>
                    <p className="mt-1 pl-6 text-xs text-theme-ink-muted">
                        Mark as demo so you can identify trial/share accounts in the tenant list.
                    </p>
                </div>

                {!isEdit && (
                    <div>
                        <label className="flex items-center gap-2 text-sm text-theme-ink">
                            <input
                                type="checkbox"
                                checked={!!form.data.create_default_branch}
                                onChange={(e) =>
                                    form.setData('create_default_branch', e.target.checked)
                                }
                                className="h-4 w-4 rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                            />
                            Create a default branch for this company
                        </label>
                        <p className="mt-1 pl-6 text-xs text-theme-ink-muted">
                            A default branch will be created automatically with the company name. You
                            can add more branches later.
                        </p>
                    </div>
                )}

                <div className="flex justify-end gap-2 border-t border-theme-border pt-4">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {isEdit ? 'Update company' : 'Create company'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
