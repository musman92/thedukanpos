import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const emptyData = () => ({
    name: '',
    code: '',
    parent_id: '',
    default_tax_id: '',
    is_active: true,
});

export default function CategoryFormDrawer({
    open,
    category = null,
    parentOptions = [],
    taxes = [],
    onClose,
}) {
    const editing = !!category;
    const form = useForm(emptyData());

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();

        if (category) {
            form.setData({
                name: category.name || '',
                code: category.code || '',
                parent_id: category.parent_id ?? '',
                default_tax_id: category.default_tax_id ?? '',
                is_active: !!category.is_active,
            });
        } else {
            form.setData(emptyData());
        }

        return undefined;
        // Reset only when drawer opens or the edited category changes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, category?.id]);

    const availableParents = parentOptions.filter((opt) => !category || opt.id !== category.id);

    const submit = (e) => {
        e.preventDefault();

        form.transform((data) => ({
            ...data,
            parent_id: data.parent_id === '' ? null : data.parent_id,
            default_tax_id: data.default_tax_id === '' ? null : data.default_tax_id,
            ...(editing ? { _method: 'put' } : {}),
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (!editing) {
                    form.reset();
                }
                onClose();
            },
            onFinish: () => form.transform((data) => data),
        };

        if (editing) {
            form.post(route('admin.categories.update', category.id), options);
            return;
        }

        form.post(route('admin.categories.store'), options);
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={editing ? 'Edit category' : 'Add category'}
            description="Categories group products and can set a default tax."
            width="half"
        >
            <form onSubmit={submit} className="flex h-full flex-col">
                <div className="space-y-4">
                    <Field label="Name" required error={form.errors.name}>
                        <Input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            error={!!form.errors.name}
                            autoFocus
                        />
                    </Field>
                    <Field
                        label="Code"
                        error={form.errors.code}
                        hint="Optional. If empty, we assign C01, C02, … automatically."
                    >
                        <Input
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                            error={!!form.errors.code}
                            placeholder="e.g. BEV"
                        />
                    </Field>

                    <Field label="Parent" error={form.errors.parent_id} hint="Optional. Nest under another category.">
                        <select
                            value={form.data.parent_id}
                            onChange={(e) => form.setData('parent_id', e.target.value)}
                            className="h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                        >
                            <option value="">None (top level)</option>
                            {availableParents.map((opt) => (
                                <option key={opt.id} value={opt.id}>
                                    {opt.name}
                                    {opt.code ? ` (${opt.code})` : ''}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <Field
                        label="Default tax"
                        error={form.errors.default_tax_id}
                        hint="Applied when assigning this category on a product."
                    >
                        <select
                            value={form.data.default_tax_id}
                            onChange={(e) => form.setData('default_tax_id', e.target.value)}
                            className="h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                        >
                            <option value="">None</option>
                            {taxes.map((tax) => (
                                <option key={tax.id} value={tax.id}>
                                    {tax.name}
                                    {tax.rate != null ? ` (${tax.rate}%)` : ''}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <label className="flex items-center gap-2 text-sm text-theme-ink">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(e) => form.setData('is_active', e.target.checked)}
                            className="rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                        />
                        Active
                    </label>
                </div>

                <div className="mt-auto flex justify-end gap-2 border-t border-theme-border pt-5">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {editing ? 'Save changes' : 'Create category'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
