import Drawer from '@/Components/Ui/Drawer';
import Button from '@/Components/Ui/Button';
import Input, { Field } from '@/Components/Ui/Input';
import PlanAssignmentFields from '@/Pages/Platform/Tenants/PlanAssignmentFields';
import { router, useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const selectClass =
    'w-full rounded-lg border border-theme-border bg-theme-surface px-3.5 py-2.5 text-sm text-theme-ink outline-none transition focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

export default function TenantManageDrawer({ open, onClose, tenant, formMeta = {} }) {
    const form = useForm({
        plan_id: '',
        billing_interval: 'monthly',
        billing_status: 'trial',
        monthly_fee: 0,
        override_limits: false,
        product_limit: '',
        user_limit: '',
        branch_limit: '',
        trial_ends_at: '',
        billing_notes: '',
        is_active: true,
        is_demo: false,
    });

    useEffect(() => {
        if (!open || !tenant) return;
        form.clearErrors();
        form.setData({
            plan_id: tenant.plan_id || '',
            billing_interval: tenant.billing_interval || 'monthly',
            billing_status: tenant.billing_status || 'trial',
            monthly_fee: tenant.monthly_fee ?? 0,
            override_limits: !!tenant.override_limits,
            product_limit: tenant.product_limit ?? '',
            user_limit: tenant.user_limit ?? '',
            branch_limit: tenant.branch_limit ?? '',
            trial_ends_at: tenant.trial_ends_at || '',
            billing_notes: '',
            is_active: !!tenant.is_active,
            is_demo: !!tenant.is_demo,
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, tenant?.id]);

    if (!tenant) return null;

    const submit = (e) => {
        e.preventDefault();
        form.put(route('platform.tenants.billing', tenant.id), {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={`Manage · ${tenant.code}`}
            description={tenant.name}
            width="lg"
        >
            <form onSubmit={submit} className="space-y-4">
                <PlanAssignmentFields
                    form={form}
                    plans={formMeta.plans || []}
                    intervals={formMeta.billing_intervals || []}
                />
                <Field label="Billing status" required>
                    <select
                        className={selectClass}
                        value={form.data.billing_status}
                        onChange={(e) => form.setData('billing_status', e.target.value)}
                    >
                        {['trial', 'active', 'past_due', 'cancelled'].map((s) => (
                            <option key={s} value={s}>
                                {s}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field label="Trial ends">
                    <Input
                        type="date"
                        value={form.data.trial_ends_at}
                        onChange={(e) => form.setData('trial_ends_at', e.target.value)}
                    />
                </Field>
                <Field label="Billing notes" error={form.errors.billing_notes}>
                    <Input
                        value={form.data.billing_notes}
                        onChange={(e) => form.setData('billing_notes', e.target.value)}
                        error={!!form.errors.billing_notes}
                    />
                </Field>
                <label className="inline-flex cursor-pointer items-center gap-2 text-sm text-theme-ink">
                    <input
                        type="checkbox"
                        checked={form.data.is_active}
                        onChange={(e) => form.setData('is_active', e.target.checked)}
                        className="rounded border-theme-border text-theme-primary focus:ring-theme-primary/20"
                    />
                    Tenant active
                </label>
                <label className="inline-flex cursor-pointer items-center gap-2 text-sm text-theme-ink">
                    <input
                        type="checkbox"
                        checked={form.data.is_demo}
                        onChange={(e) => form.setData('is_demo', e.target.checked)}
                        className="rounded border-theme-border text-theme-primary focus:ring-theme-primary/20"
                    />
                    Demo company
                </label>

                <div className="flex flex-wrap gap-2 border-t border-theme-border pt-4">
                    <Button type="submit" disabled={form.processing}>
                        Save billing
                    </Button>
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={() =>
                            router.post(route('platform.tenants.support-login', tenant.id), {}, {
                                preserveScroll: true,
                            })
                        }
                    >
                        Support login (15m)
                    </Button>
                    <Button type="button" variant="ghost" onClick={onClose}>
                        Close
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
