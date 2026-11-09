import Input, { Field } from '@/Components/Ui/Input';

const selectClass =
    'w-full rounded-lg border border-theme-border bg-theme-surface px-3.5 py-2.5 text-sm text-theme-ink outline-none transition focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

export function planPrice(plan, interval) {
    if (!plan) return 0;
    if (interval === 'quarterly') return plan.quarterly_price ?? 0;
    if (interval === 'yearly') return plan.yearly_price ?? 0;
    return plan.monthly_price ?? 0;
}

export function limitText(value) {
    if (value === null || value === undefined || value === '') return 'No cap';
    return String(value);
}

export function defaultPlanId(plans = []) {
    const free = plans.find((plan) => plan.code === 'free' && plan.is_active);
    const active = plans.find((plan) => plan.is_active);
    return (free || active || plans[0])?.id ?? '';
}

export default function PlanAssignmentFields({
    form,
    plans = [],
    intervals = [],
}) {
    const selected = plans.find((plan) => String(plan.id) === String(form.data.plan_id));
    const choices = plans.filter(
        (plan) => plan.is_active || String(plan.id) === String(form.data.plan_id),
    );
    const interval = form.data.billing_interval || 'monthly';
    const intervalLabel =
        intervals.find((item) => item.value === interval)?.label || 'Monthly';

    const choosePlan = (planId) => {
        const plan = plans.find((item) => String(item.id) === String(planId));
        form.setData({
            ...form.data,
            plan_id: planId,
            monthly_fee: planPrice(plan, form.data.billing_interval || 'monthly'),
        });
    };

    const chooseInterval = (next) => {
        form.setData({
            ...form.data,
            billing_interval: next,
            monthly_fee: planPrice(selected, next),
        });
    };

    return (
        <>
            <Field label="Plan" required error={form.errors.plan_id}>
                <select
                    value={form.data.plan_id || ''}
                    onChange={(e) => choosePlan(e.target.value)}
                    className={selectClass}
                >
                    <option value="">Select a plan</option>
                    {choices.map((plan) => (
                        <option key={plan.id} value={plan.id}>
                            {plan.name}
                            {plan.is_active ? '' : ' (inactive)'}
                        </option>
                    ))}
                </select>
            </Field>
            <Field label="Billing period" required error={form.errors.billing_interval}>
                <select
                    value={interval}
                    onChange={(e) => chooseInterval(e.target.value)}
                    className={selectClass}
                >
                    {(intervals.length ? intervals : [
                        { value: 'monthly', label: 'Monthly' },
                        { value: 'quarterly', label: 'Quarterly' },
                        { value: 'yearly', label: 'Yearly' },
                    ]).map((item) => (
                        <option key={item.value} value={item.value}>
                            {item.label}
                        </option>
                    ))}
                </select>
            </Field>
            <Field
                label={`${intervalLabel} amount`}
                error={form.errors.monthly_fee}
                hint="Filled from the plan. Change it when this company pays a custom price."
            >
                <Input
                    type="number"
                    step="0.01"
                    min="0"
                    value={form.data.monthly_fee}
                    onChange={(e) => form.setData('monthly_fee', e.target.value)}
                    error={!!form.errors.monthly_fee}
                />
            </Field>
            <div className="md:col-span-2">
                <label className="flex items-center gap-2 text-sm text-theme-ink">
                    <input
                        type="checkbox"
                        checked={!!form.data.override_limits}
                        onChange={(e) => form.setData('override_limits', e.target.checked)}
                        className="h-4 w-4 rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                    />
                    Override plan limits for this company
                </label>
                {!form.data.override_limits && selected && (
                    <p className="mt-1 pl-6 text-xs text-theme-ink-muted">
                        Plan limits: {limitText(selected.product_limit)} products,{' '}
                        {limitText(selected.user_limit)} users, {limitText(selected.branch_limit)}{' '}
                        branches.
                    </p>
                )}
            </div>
            {form.data.override_limits && (
                <>
                    <Field
                        label="Products"
                        error={form.errors.product_limit}
                        hint="Blank means no cap."
                    >
                        <Input
                            type="number"
                            min="0"
                            value={form.data.product_limit}
                            onChange={(e) => form.setData('product_limit', e.target.value)}
                            error={!!form.errors.product_limit}
                            placeholder="No cap"
                        />
                    </Field>
                    <Field label="Users" error={form.errors.user_limit} hint="Blank means no cap.">
                        <Input
                            type="number"
                            min="0"
                            value={form.data.user_limit}
                            onChange={(e) => form.setData('user_limit', e.target.value)}
                            error={!!form.errors.user_limit}
                            placeholder="No cap"
                        />
                    </Field>
                    <Field
                        label="Branches"
                        error={form.errors.branch_limit}
                        hint="Blank means no cap."
                    >
                        <Input
                            type="number"
                            min="0"
                            value={form.data.branch_limit}
                            onChange={(e) => form.setData('branch_limit', e.target.value)}
                            error={!!form.errors.branch_limit}
                            placeholder="No cap"
                        />
                    </Field>
                </>
            )}
        </>
    );
}
