import Drawer from '@/Components/Ui/Drawer';
import Button from '@/Components/Ui/Button';
import Input, { Field, TextArea } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const empty = {
    code: '',
    name: '',
    description: '',
    product_limit: '',
    user_limit: '',
    branch_limit: '',
    ads_enabled: false,
    monthly_price: 0,
    quarterly_price: 0,
    yearly_price: 0,
    is_active: true,
    sort_order: 0,
};

function fromPlan(plan) {
    if (!plan) return empty;
    return {
        code: plan.code || '',
        name: plan.name || '',
        description: plan.description || '',
        product_limit: plan.product_limit ?? '',
        user_limit: plan.user_limit ?? '',
        branch_limit: plan.branch_limit ?? '',
        ads_enabled: !!plan.ads_enabled,
        monthly_price: plan.monthly_price ?? 0,
        quarterly_price: plan.quarterly_price ?? 0,
        yearly_price: plan.yearly_price ?? 0,
        is_active: plan.is_active !== false,
        sort_order: plan.sort_order ?? 0,
    };
}

export default function PlanFormDrawer({ open, onClose, plan = null }) {
    const isEdit = !!plan;
    const form = useForm(fromPlan(plan));

    useEffect(() => {
        if (!open) return;
        form.clearErrors();
        form.setData(fromPlan(plan));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, plan?.id]);

    const submit = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onClose() };
        if (isEdit) {
            form.put(route('platform.plans.update', plan.id), options);
            return;
        }
        form.post(route('platform.plans.store'), options);
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={isEdit ? 'Edit plan' : 'Add plan'}
            description="Limits left blank mean no cap. Prices are what a company pays for each billing period."
            width="lg"
        >
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Field label="Name" required error={form.errors.name}>
                        <Input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            error={!!form.errors.name}
                        />
                    </Field>
                    <Field
                        label="Code"
                        required
                        error={form.errors.code}
                        hint="Lowercase, used internally. Example: starter."
                    >
                        <Input
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value.toLowerCase())}
                            error={!!form.errors.code}
                        />
                    </Field>
                    <Field label="Products" error={form.errors.product_limit} hint="Blank means no cap.">
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
                    <Field label="Branches" error={form.errors.branch_limit} hint="Blank means no cap.">
                        <Input
                            type="number"
                            min="0"
                            value={form.data.branch_limit}
                            onChange={(e) => form.setData('branch_limit', e.target.value)}
                            error={!!form.errors.branch_limit}
                            placeholder="No cap"
                        />
                    </Field>
                    <Field label="Sort order" error={form.errors.sort_order}>
                        <Input
                            type="number"
                            min="0"
                            value={form.data.sort_order}
                            onChange={(e) => form.setData('sort_order', e.target.value)}
                            error={!!form.errors.sort_order}
                        />
                    </Field>
                    <Field label="Monthly price" required error={form.errors.monthly_price}>
                        <Input
                            type="number"
                            step="0.01"
                            min="0"
                            value={form.data.monthly_price}
                            onChange={(e) => form.setData('monthly_price', e.target.value)}
                            error={!!form.errors.monthly_price}
                        />
                    </Field>
                    <Field label="Quarterly price" required error={form.errors.quarterly_price}>
                        <Input
                            type="number"
                            step="0.01"
                            min="0"
                            value={form.data.quarterly_price}
                            onChange={(e) => form.setData('quarterly_price', e.target.value)}
                            error={!!form.errors.quarterly_price}
                        />
                    </Field>
                    <Field label="Yearly price" required error={form.errors.yearly_price}>
                        <Input
                            type="number"
                            step="0.01"
                            min="0"
                            value={form.data.yearly_price}
                            onChange={(e) => form.setData('yearly_price', e.target.value)}
                            error={!!form.errors.yearly_price}
                        />
                    </Field>
                    <Field
                        label="Description"
                        error={form.errors.description}
                        className="md:col-span-2"
                    >
                        <TextArea
                            rows={2}
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            error={!!form.errors.description}
                        />
                    </Field>
                </div>
                <label className="flex items-center gap-2 text-sm text-theme-ink">
                    <input
                        type="checkbox"
                        checked={!!form.data.ads_enabled}
                        onChange={(e) => form.setData('ads_enabled', e.target.checked)}
                        className="h-4 w-4 rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                    />
                    Show ads on the phone app
                </label>
                <label className="flex items-center gap-2 text-sm text-theme-ink">
                    <input
                        type="checkbox"
                        checked={!!form.data.is_active}
                        onChange={(e) => form.setData('is_active', e.target.checked)}
                        className="h-4 w-4 rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                    />
                    Active — available when creating a company
                </label>
                <div className="flex justify-end gap-2 border-t border-theme-border pt-4">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {isEdit ? 'Save plan' : 'Create plan'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
